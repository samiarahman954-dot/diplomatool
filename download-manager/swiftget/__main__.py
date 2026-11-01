"""Start SwiftGet:  python -m swiftget [URL ...]"""

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
    settings = Settings()
    if settings["browser_integration"] and other_instance_running(int(settings["browser_port"])):
        return 0  # the running window was brought to front

    _enable_dpi_awareness()
    root = tk.Tk()
    manager = DownloadManager(settings)
    app = App(root, settings, manager)
    manager.start_loop()
    for url in argv:
        if url.lower().startswith(("http://", "https://", "ftp://")):
            root.after(500, lambda u=url: AddDialog(app, url=u))
    root.mainloop()
    return 0


if __name__ == "__main__":
    sys.exit(main())
