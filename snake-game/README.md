# Snake — Windows desktop game

A native Windows snake game (Win32 + GDI, written in C). No runtime, no dependencies:
a single ~100 KB `.exe`.

## Download & play

| File | What it is |
|---|---|
| [`dist/Snake-Setup.exe`](dist/Snake-Setup.exe) | **Installer.** Installs to your user folder (no admin needed), adds Desktop + Start Menu shortcuts and an uninstaller (Settings → Apps). |
| [`dist/Snake.exe`](dist/Snake.exe) | **Portable.** Just double-click to play — nothing to install. |

> Windows SmartScreen may show "Windows protected your PC" because the exe isn't
> code-signed. Click **More info → Run anyway**.

## Controls

| Key | Action |
|---|---|
| Arrow keys / WASD | Move |
| Enter / Space | Start / play again |
| P | Pause / resume |
| Esc | Back to menu (Esc on the menu quits) |

Eat red apples (+1). Every 5 apples a golden star appears for 6 seconds (+5).
The snake speeds up as it grows. Your best score is saved to
`%APPDATA%\SnakeGame\highscore.txt`.

## Building from source

On Linux (or WSL):

```bash
sudo apt-get install gcc-mingw-w64-x86-64 nsis
./build.sh
```

Output goes to `dist/`. `make_icon.py` regenerates `src/snake.ico` (needs Pillow).
