"""Generates src/snake.ico (multi-size) with Pillow."""
from PIL import Image, ImageDraw

def draw(size):
    s = 256
    im = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    d.rounded_rectangle((8, 8, s - 8, s - 8), radius=56, fill=(24, 30, 40, 255))
    body = [(60, 190), (60, 130), (120, 130), (120, 70), (190, 70)]
    d.line(body, fill=(60, 170, 100, 255), width=44, joint="curve")
    for x, y in body[:-1]:
        d.ellipse((x - 22, y - 22, x + 22, y + 22), fill=(60, 170, 100, 255))
    d.ellipse((165, 45, 215, 95), fill=(140, 235, 120, 255))
    d.ellipse((188, 56, 202, 70), fill=(255, 255, 255, 255))
    d.ellipse((193, 60, 200, 67), fill=(20, 20, 20, 255))
    d.ellipse((160, 160, 210, 210), fill=(235, 70, 80, 255))
    d.ellipse((186, 148, 204, 162), fill=(90, 180, 90, 255))
    return im.resize((size, size), Image.LANCZOS)

if __name__ == "__main__":
    import os
    here = os.path.dirname(os.path.abspath(__file__))
    big = draw(256)
    big.save(os.path.join(here, "src", "snake.ico"),
             sizes=[(16, 16), (24, 24), (32, 32), (48, 48), (64, 64), (128, 128), (256, 256)])
    big.save(os.path.join(here, "src", "snake.png"))
