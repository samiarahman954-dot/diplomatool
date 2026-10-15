# Snake (classic phone style) — Android

Old-school monochrome-LCD snake with a 12-key phone keypad. Ready-to-install build: **`snake.apk`** (Android 7.0+).

## Install
1. Copy `snake.apk` to your phone (or download it from GitHub on the phone).
2. Open it and allow "Install unknown apps" for your file manager/browser when asked.

## Controls
| Key / gesture | Action |
|---|---|
| 2 4 6 8 or swipe | Up / Left / Right / Down |
| 5 or tap screen | Start / pause / continue |
| # | Change level 1–9 (speed) on the start screen |
| * | Sound on/off |

Walls wrap around (like the original). Each food is worth *level* points; every 5th food a bonus critter appears for a few seconds — eat it fast for more points. High score is saved.

## Rebuild
No Android SDK needed — `build.py` writes the binary manifest/resources itself and signs with APK Signature Scheme v2.

```
python3 build.py --android-jar android-4.1.1.4.jar --dx-jar dalvik-dx-16.0.1.jar
```
Both jars are on Maven Central (`com.google.android:android:4.1.1.4`, `com.jakewharton.android.repackaged:dalvik-dx:16.0.1`). Also needs a JDK and `pip install cryptography pillow`.

`key.pem` is a throwaway debug signing key kept in the repo so rebuilt APKs install as updates over the old one.
