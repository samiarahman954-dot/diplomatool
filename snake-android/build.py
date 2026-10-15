#!/usr/bin/env python3
"""Build snake.apk without the Android SDK.

Needs: a JDK (javac), android.jar (any API >= 16 stub, e.g. Maven Central
com.google.android:android:4.1.1.4), dx.jar (Maven Central
com.jakewharton.android.repackaged:dalvik-dx:16.0.1), Python with
`cryptography` and `Pillow`.

    python3 build.py --android-jar path/android.jar --dx-jar path/dx.jar

Writes the binary AndroidManifest.xml and resources.arsc by hand, aligns the
zip, and signs it with APK Signature Scheme v2 using key.pem (a debug key is
created on first run; keep it so later builds install as updates).
"""
import argparse
import datetime
import hashlib
import io
import os
import shutil
import struct
import subprocess
import tempfile
import zipfile

from cryptography import x509
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding, rsa
from cryptography.x509.oid import NameOID
from PIL import Image, ImageDraw

HERE = os.path.dirname(os.path.abspath(__file__))
PACKAGE = "com.classic.snake"
VERSION_CODE = 1
VERSION_NAME = "1.0"
MIN_SDK = 24  # v2-only signature needs Android 7.0+
TARGET_SDK = 34
ICON_ID = 0x7F010000

# ---------------------------------------------------------------- binary XML

ANDROID_NS = "http://schemas.android.com/apk/res/android"
ATTR_IDS = {
    "theme": 0x01010000, "label": 0x01010001, "icon": 0x01010002, "name": 0x01010003,
    "exported": 0x01010010, "screenOrientation": 0x0101001E, "configChanges": 0x0101001F,
    "minSdkVersion": 0x0101020C, "versionCode": 0x0101021B, "versionName": 0x0101021C,
    "targetSdkVersion": 0x01010270,
}
T_REF, T_STRING, T_INT_DEC, T_INT_HEX, T_BOOL = 0x01, 0x03, 0x10, 0x11, 0x12


def string_pool(strings, utf8=False):
    offsets, data = [], bytearray()
    for s in strings:
        offsets.append(len(data))
        if utf8:
            b = s.encode("utf-8")
            assert len(s) < 0x80 and len(b) < 0x80
            data += bytes([len(s), len(b)]) + b + b"\0"
        else:
            data += struct.pack("<H", len(s)) + s.encode("utf-16-le") + b"\0\0"
    while len(data) % 4:
        data += b"\0"
    header = 28
    strings_start = header + 4 * len(strings)
    size = strings_start + len(data)
    flags = 0x100 if utf8 else 0
    return (struct.pack("<HHIIIIII", 0x0001, header, size, len(strings), 0, flags, strings_start, 0)
            + struct.pack("<%dI" % len(strings), *offsets) + bytes(data))


def binary_manifest():
    # (tag, [(ns?, name, type, value)], children)
    A = lambda name, typ, val: (True, name, typ, val)
    tree = ("manifest", [(False, "package", T_STRING, PACKAGE),
                         A("versionCode", T_INT_DEC, VERSION_CODE),
                         A("versionName", T_STRING, VERSION_NAME)], [
        ("uses-sdk", [A("minSdkVersion", T_INT_DEC, MIN_SDK),
                      A("targetSdkVersion", T_INT_DEC, TARGET_SDK)], []),
        ("application", [A("label", T_STRING, "Snake"), A("icon", T_REF, ICON_ID),
                         A("theme", T_REF, 0x0103000A)], [  # Theme.Black.NoTitleBar.Fullscreen
            ("activity", [A("name", T_STRING, PACKAGE + ".MainActivity"),
                          A("exported", T_BOOL, True),
                          A("screenOrientation", T_INT_DEC, 1),  # portrait
                          A("configChanges", T_INT_HEX, 0x04A0)], [  # orientation|keyboardHidden|screenSize
                ("intent-filter", [], [
                    ("action", [A("name", T_STRING, "android.intent.action.MAIN")], []),
                    ("category", [A("name", T_STRING, "android.intent.category.LAUNCHER")], []),
                ]),
            ]),
        ]),
    ])

    attr_names = sorted(ATTR_IDS, key=ATTR_IDS.get)
    strings = list(attr_names)

    def idx(s):
        if s not in strings:
            strings.append(s)
        return strings.index(s)

    for s in ("android", ANDROID_NS):
        idx(s)
    body = bytearray()
    END = 0xFFFFFFFF

    def walk(node, line):
        tag, attrs, children = node
        attrs = sorted(attrs, key=lambda a: ATTR_IDS.get(a[1], 0xFFFFFFFF) if a[0] else 0)
        packed = bytearray()
        for is_android, name, typ, val in attrs:
            ns = idx(ANDROID_NS) if is_android else END
            nidx = idx(name)
            if typ == T_STRING:
                raw = data = idx(val)
            else:
                raw = END
                data = (0xFFFFFFFF if val else 0) if typ == T_BOOL else val
            packed += struct.pack("<IIIHBBI", ns, nidx, raw, 8, 0, typ, data)
        body.extend(struct.pack("<HHIIIIIHHHHHH", 0x0102, 16, 36 + len(packed), line, END,
                                END, idx(tag), 20, 20, len(attrs), 0, 0, 0))
        body.extend(packed)
        for ch in children:
            line = walk(ch, line + 1)
        body.extend(struct.pack("<HHIIIII", 0x0103, 16, 24, line, END, END, idx(tag)))
        return line + 1

    walk(tree, 2)
    ns_start = struct.pack("<HHIIIII", 0x0100, 16, 24, 1, 0xFFFFFFFF, idx("android"), idx(ANDROID_NS))
    ns_end = struct.pack("<HHIIIII", 0x0101, 16, 24, 1, 0xFFFFFFFF, idx("android"), idx(ANDROID_NS))
    pool = string_pool(strings)
    resmap = struct.pack("<HHI", 0x0180, 8, 8 + 4 * len(attr_names)) + struct.pack(
        "<%dI" % len(attr_names), *[ATTR_IDS[n] for n in attr_names])
    content = pool + resmap + ns_start + bytes(body) + ns_end
    return struct.pack("<HHI", 0x0003, 8, 8 + len(content)) + content


# ---------------------------------------------------------------- resources.arsc

def resources_arsc(icon_path):
    values = string_pool([icon_path], utf8=True)
    type_strings = string_pool(["drawable"], utf8=True)
    key_strings = string_pool(["icon"], utf8=True)

    spec = struct.pack("<HHIBBHI", 0x0202, 16, 20, 1, 0, 0, 1) + struct.pack("<I", 0)
    config = bytearray(64)
    struct.pack_into("<I", config, 0, 64)
    struct.pack_into("<H", config, 14, 640)  # density: xxxhdpi
    struct.pack_into("<H", config, 20, 4)    # sdkVersion v4, as aapt adds for density qualifiers
    entries = struct.pack("<HHI", 8, 0, 0) + struct.pack("<HBBI", 8, 0, T_STRING, 0)
    type_header = 20 + 64
    type_chunk = (struct.pack("<HHIBBHII", 0x0201, type_header, type_header + 4 + len(entries),
                              1, 0, 0, 1, type_header + 4)
                  + bytes(config) + struct.pack("<I", 0) + entries)

    pkg_header = 288
    name = PACKAGE.encode("utf-16-le").ljust(256, b"\0")
    type_off = pkg_header
    key_off = type_off + len(type_strings)
    pkg_body = type_strings + key_strings + spec + type_chunk
    package = (struct.pack("<HHII", 0x0200, pkg_header, pkg_header + len(pkg_body), 0x7F) + name
               + struct.pack("<IIIII", type_off, 1, key_off, 1, 0) + pkg_body)
    content = values + package
    return struct.pack("<HHII", 0x0002, 12, 12 + len(content), 1) + content


# ---------------------------------------------------------------- icon

def make_icon(size=192):
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    r = size * 0.22
    d.rounded_rectangle([4, 4, size - 5, size - 5], radius=r, fill=(0x17, 0x22, 0x2E, 255))
    m = size * 0.14
    d.rounded_rectangle([m, m, size - m, size - m], radius=r * 0.35, fill=(0xC7, 0xF0, 0xD8, 255))
    fg = (0x43, 0x52, 0x3D, 255)
    g = (size - 2 * m) / 8.0
    cells = [(1, 5), (2, 5), (3, 5), (4, 5), (4, 4), (4, 3), (4, 2), (5, 2)]
    for i, (cx, cy) in enumerate(cells):
        x0, y0 = m + cx * g, m + cy * g
        ins = g * 0.12
        d.rectangle([x0 + ins, y0 + ins, x0 + g - ins, y0 + g - ins], fill=fg)
        if i + 1 < len(cells):
            nx, ny = cells[i + 1]
            x1, y1 = m + min(cx, nx) * g, m + min(cy, ny) * g
            if nx != cx:
                d.rectangle([x1 + g / 2, y0 + ins, x1 + g * 1.5, y0 + g - ins], fill=fg)
            else:
                d.rectangle([x0 + ins, y1 + g / 2, x0 + g - ins, y1 + g * 1.5], fill=fg)
    hx, hy = m + 5 * g, m + 2 * g
    d.rectangle([hx + g * 0.55, hy + g * 0.25, hx + g * 0.8, hy + g * 0.5], fill=(0xC7, 0xF0, 0xD8, 255))
    fx, fy, t = m + 6.4 * g, m + 1.9 * g, g / 3
    for ox, oy in ((1, 0), (0, 1), (2, 1), (1, 2)):
        d.rectangle([fx + ox * t, fy + oy * t, fx + (ox + 1) * t, fy + (oy + 1) * t], fill=fg)
    buf = io.BytesIO()
    img.save(buf, "PNG", optimize=True)
    return buf.getvalue()


# ---------------------------------------------------------------- zip + signing

def write_aligned_zip(path, entries):
    """entries: list of (name, bytes, stored). Stored entries are 4-byte aligned."""
    with zipfile.ZipFile(path, "w") as zf:
        for name, data, stored in entries:
            info = zipfile.ZipInfo(name, date_time=(2026, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_STORED if stored else zipfile.ZIP_DEFLATED
            if stored:
                offset = zf.fp.tell() + 30 + len(name.encode())
                pad = (4 - offset % 4) % 4
                info.extra = b"\0" * pad
            zf.writestr(info, data)


def load_or_create_key(path):
    if os.path.exists(path):
        pem = open(path, "rb").read()
        key = serialization.load_pem_private_key(pem, None)
        cert = x509.load_pem_x509_certificate(pem[pem.index(b"-----BEGIN CERTIFICATE"):])
        return key, cert
    key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    name = x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, "Snake Debug")])
    now = datetime.datetime(2026, 1, 1)
    cert = (x509.CertificateBuilder().subject_name(name).issuer_name(name)
            .public_key(key.public_key()).serial_number(x509.random_serial_number())
            .not_valid_before(now).not_valid_after(now + datetime.timedelta(days=365 * 30))
            .sign(key, hashes.SHA256()))
    with open(path, "wb") as f:
        f.write(key.private_bytes(serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8,
                                  serialization.NoEncryption()))
        f.write(cert.public_bytes(serialization.Encoding.PEM))
    return key, cert


def lp(b):
    return struct.pack("<I", len(b)) + b


def sign_v2(apk, key, cert):
    eocd_off = apk.rindex(b"PK\x05\x06")
    cd_off, = struct.unpack_from("<I", apk, eocd_off + 16)
    sections = [apk[:cd_off], apk[cd_off:eocd_off], apk[eocd_off:]]
    chunk_digests = []
    for sec in sections:
        for i in range(0, len(sec), 1 << 20):
            chunk = sec[i:i + (1 << 20)]
            chunk_digests.append(hashlib.sha256(b"\xa5" + struct.pack("<I", len(chunk)) + chunk).digest())
    top = hashlib.sha256(b"\x5a" + struct.pack("<I", len(chunk_digests)) + b"".join(chunk_digests)).digest()

    alg = 0x0103  # RSASSA-PKCS1-v1_5 with SHA2-256
    cert_der = cert.public_bytes(serialization.Encoding.DER)
    signed_data = (lp(lp(struct.pack("<I", alg) + lp(top)))
                   + lp(lp(cert_der))
                   + lp(b""))
    sig = key.sign(signed_data, padding.PKCS1v15(), hashes.SHA256())
    pub = key.public_key().public_bytes(serialization.Encoding.DER,
                                        serialization.PublicFormat.SubjectPublicKeyInfo)
    signer = lp(signed_data) + lp(lp(struct.pack("<I", alg) + lp(sig))) + lp(pub)
    value = lp(lp(signer))
    pair = struct.pack("<Q", 4 + len(value)) + struct.pack("<I", 0x7109871A) + value
    block_size = len(pair) + 8 + 16
    block = struct.pack("<Q", block_size) + pair + struct.pack("<Q", block_size) + b"APK Sig Block 42"

    eocd = bytearray(sections[2])
    struct.pack_into("<I", eocd, 16, cd_off + len(block))
    return sections[0] + block + sections[1] + bytes(eocd)


# ---------------------------------------------------------------- main

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--android-jar", required=True)
    ap.add_argument("--dx-jar", required=True)
    ap.add_argument("--out", default=os.path.join(HERE, "snake.apk"))
    ap.add_argument("--key", default=os.path.join(HERE, "key.pem"))
    args = ap.parse_args()

    work = tempfile.mkdtemp(prefix="snake-build-")
    try:
        classes = os.path.join(work, "classes")
        os.makedirs(classes)
        sources = []
        for root, _, files in os.walk(os.path.join(HERE, "src")):
            sources += [os.path.join(root, f) for f in files if f.endswith(".java")]
        subprocess.check_call(["javac", "--release", "8", "-nowarn",
                               "-classpath", args.android_jar, "-d", classes] + sources)
        dex = os.path.join(work, "classes.dex")
        subprocess.check_call(["java", "-cp", args.dx_jar, "com.android.dx.command.Main", "--dex", "--min-sdk-version=24",
                               "--output=" + dex, classes])

        icon_path = "res/drawable-xxxhdpi-v4/icon.png"
        entries = [
            ("AndroidManifest.xml", binary_manifest(), False),
            ("classes.dex", open(dex, "rb").read(), False),
            ("resources.arsc", resources_arsc(icon_path), True),
            (icon_path, make_icon(), True),
        ]
        unsigned = os.path.join(work, "unsigned.apk")
        write_aligned_zip(unsigned, entries)
        key, cert = load_or_create_key(args.key)
        with open(args.out, "wb") as f:
            f.write(sign_v2(open(unsigned, "rb").read(), key, cert))
        print("wrote", args.out, os.path.getsize(args.out), "bytes")
    finally:
        shutil.rmtree(work, ignore_errors=True)


if __name__ == "__main__":
    main()
