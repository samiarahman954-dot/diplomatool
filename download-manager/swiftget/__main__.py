"""Start SwiftGet:  python -m swiftget [URL ...]"""

import os
import sys


def _enable_dpi_awareness():
    if sys.platform.startswith("win"):
        try:
            import ctypes
            ctypes.windll.shcore.SetProcessDpiAwareness(1)
        except Exception:  # noqa: BLE001 - older Windows
            pass


def main(argv=None):
    import tkinter as tk

    from .config import Settings
    from .engine import DownloadManager
    from .gui import AddDialog, App
    from .server import other_instance_running

    argv = sys.argv[1:] if argv is None else argv
    start_hidden = "--tray" in argv  # used by the Windows start-up entry
    argv = [a for a in argv if a != "--tray"]
    settings = Settings()
    if settings["browser_integration"] and other_instance_running(int(settings["browser_port"]),
                                                                  show=not start_hidden):
        return 0  # already running (the window was brought to front)

    _enable_dpi_awareness()
    root = tk.Tk()
    manager = DownloadManager(settings)
    app = App(root, settings, manager, start_hidden=start_hidden)
    manager.start_loop()
    for url in argv:
        if url.lower().startswith(("http://", "https://", "ftp://")):
            root.after(500, lambda u=url: AddDialog(app, url=u))
    root.mainloop()
    # Everything is saved by now. Some tray back-ends leave non-daemon threads behind,
    # so end the process explicitly instead of waiting for them.
    sys.stdout.flush()
    sys.stderr.flush()
    os._exit(0)


if __name__ == "__main__":
    sys.exit(main())
