"""Tkinter user interface."""

import datetime
import os
import queue
import sys
import threading
import time
import tkinter as tk
import tkinter.font as tkfont
from tkinter import filedialog, messagebox, ttk

from . import APP_NAME, VERSION, engine
from .engine import ACTIVE_STATES, COMPLETED, DOWNLOADING, ERROR, PAUSED, QUEUED, WAITING
from .server import IntegrationServer
from .utils import (CATEGORY_NAMES, category_for, expand_batch_pattern, extension_of, human_size,
                    human_speed, human_time, is_url, open_path, reveal_in_folder, sanitize_filename)

ACCENT = "#1a73e8"


def make_icon(size=32):
    """Draw a simple round icon with a down arrow (no image files needed)."""
    img = tk.PhotoImage(width=size, height=size)
    c = (size - 1) / 2
    r = size / 2 - 1
    rows = []
    outside = []
    for y in range(size):
        row = []
        for x in range(size):
            dx, dy = x - c, y - c
            inside = dx * dx + dy * dy <= r * r
            nx, ny = x / size, y / size
            shaft = 0.42 <= nx <= 0.58 and 0.18 <= ny <= 0.55
            head = 0.5 <= ny <= 0.78 and abs(nx - 0.5) <= (0.78 - ny) * 1.1
            bar = 0.78 < ny <= 0.84 and 0.28 <= nx <= 0.72
            if not inside:
                outside.append((x, y))
                row.append("#ffffff")
            elif shaft or head or bar:
                row.append("#ffffff")
            else:
                row.append(ACCENT)
        rows.append("{" + " ".join(row) + "}")
    img.put(" ".join(rows))
    for x, y in outside:
        img.transparency_set(x, y, True)
    return img


def center_on(win, parent):
    win.update_idletasks()
    try:
        px, py = parent.winfo_rootx(), parent.winfo_rooty()
        pw, ph = parent.winfo_width(), parent.winfo_height()
        w, h = win.winfo_reqwidth(), win.winfo_reqheight()
        x = px + max(0, (pw - w) // 2)
        y = py + max(0, (ph - h) // 3)
        win.geometry(f"+{x}+{y}")
    except tk.TclError:
        pass


def bring_to_front(win):
    try:
        win.deiconify()
        win.lift()
        win.attributes("-topmost", True)
        win.after(300, lambda: win.attributes("-topmost", False))
        win.focus_force()
    except tk.TclError:
        pass


class App:
    COLUMNS = (
        ("name", "File Name", 300, "w"),
        ("size", "Size", 90, "e"),
        ("status", "Status", 120, "w"),
        ("speed", "Transfer rate", 100, "e"),
        ("eta", "Time left", 80, "e"),
        ("conn", "Conns", 64, "center"),
        ("added", "Date added", 130, "w"),
    )

    def __init__(self, root, settings, manager):
        self.root = root
        self.settings = settings
        self.manager = manager
        self.filter = "All"
        self.sort = ("added", False)
        self._rows = {}
        self.progress_windows = {}
        self._clip_last = None
        self.server = None
        self.icon = make_icon()

        root.title(f"{APP_NAME} Download Manager")
        root.iconphoto(True, self.icon)
        ico = resource_path("assets", "swiftget.ico")
        if sys.platform.startswith("win") and os.path.exists(ico):
            try:
                root.iconbitmap(default=ico)
            except tk.TclError:
                pass
        root.geometry("1120x580")
        root.minsize(760, 380)
        self._setup_style()
        self._build_menu()
        self._build_toolbar()
        self._build_body()
        self._build_statusbar()
        self._bind_keys()
        self.start_server()

        root.protocol("WM_DELETE_WINDOW", self.on_close)
        root.after(300, self._refresh)
        root.after(200, self._process_events)
        root.after(1500, self._poll_clipboard)

    # ------------------------------------------------------------------ UI
    def _setup_style(self):
        style = ttk.Style(self.root)
        themes = style.theme_names()
        style.theme_use("vista" if "vista" in themes else "clam")
        base = tkfont.nametofont("TkDefaultFont")
        line = base.metrics("linespace")
        style.configure("Treeview", rowheight=line + 8)
        style.configure("Horizontal.TProgressbar", background=ACCENT)
        style.configure("Toolbar.TButton", padding=(4, 3))
        style.configure("Status.TLabel", padding=(6, 2))
        style.configure("Big.TLabel", font=(base.actual("family"), base.actual("size") + 3, "bold"))
        self.bold_font = (base.actual("family"), base.actual("size"), "bold")

    def _build_menu(self):
        m = tk.Menu(self.root)
        tasks = tk.Menu(m, tearoff=False)
        tasks.add_command(label="Add new download...", accelerator="Ctrl+N", command=self.add_url)
        tasks.add_command(label="Add batch download...", accelerator="Ctrl+B", command=self.add_batch)
        tasks.add_separator()
        tasks.add_command(label="Exit", command=self.on_close)
        m.add_cascade(label="Tasks", menu=tasks)

        dl = tk.Menu(m, tearoff=False)
        dl.add_command(label="Resume", command=self.resume_selected)
        dl.add_command(label="Pause", command=self.pause_selected)
        dl.add_command(label="Pause all", command=self.manager.pause_all)
        dl.add_command(label="Resume all", command=self.manager.resume_all)
        dl.add_separator()
        dl.add_command(label="Delete...", accelerator="Del", command=self.delete_selected)
        dl.add_command(label="Delete completed", command=self.manager.remove_completed)
        dl.add_separator()
        self.limit_var = tk.IntVar(value=int(self.settings["speed_limit_kb"] or 0))
        limiter = tk.Menu(dl, tearoff=False)
        for label, kb in (("Unlimited", 0), ("128 KB/s", 128), ("256 KB/s", 256), ("512 KB/s", 512),
                          ("1 MB/s", 1024), ("2 MB/s", 2048), ("5 MB/s", 5120), ("10 MB/s", 10240)):
            limiter.add_radiobutton(label=label, value=kb, variable=self.limit_var,
                                    command=lambda kb=kb: self.set_speed_limit(kb))
        dl.add_cascade(label="Speed limiter", menu=limiter)
        dl.add_command(label="Options...", command=self.open_options)
        m.add_cascade(label="Downloads", menu=dl)

        q = tk.Menu(m, tearoff=False)
        q.add_command(label="Start queue", command=self.start_queue)
        q.add_command(label="Stop queue", command=self.manager.stop_queue)
        q.add_command(label="Scheduler...", command=self.open_scheduler)
        m.add_cascade(label="Queue", menu=q)

        h = tk.Menu(m, tearoff=False)
        h.add_command(label="Browser integration help", command=self.browser_help)
        h.add_command(label="About", command=self.about)
        m.add_cascade(label="Help", menu=h)
        self.root.config(menu=m)

    def _build_toolbar(self):
        bar = ttk.Frame(self.root, padding=(6, 6, 6, 2))
        bar.pack(side="top", fill="x")
        buttons = [
            ("+ Add URL", self.add_url), ("☰ Batch", self.add_batch), None,
            ("▶ Resume", self.resume_selected), ("❚❚ Pause", self.pause_selected),
            ("■ Pause All", self.manager.pause_all), None,
            ("✖ Delete", self.delete_selected), ("✔ Clear Done", self.manager.remove_completed), None,
            ("▶▶ Start Queue", self.start_queue), ("■ Stop Queue", self.manager.stop_queue),
            ("◷ Scheduler", self.open_scheduler), ("⚙ Options", self.open_options),
        ]
        for b in buttons:
            if b is None:
                ttk.Separator(bar, orient="vertical").pack(side="left", fill="y", padx=6, pady=2)
                continue
            ttk.Button(bar, text=b[0], command=b[1], style="Toolbar.TButton", width=-1).pack(side="left", padx=1)

    def _build_body(self):
        paned = ttk.PanedWindow(self.root, orient="horizontal")
        paned.pack(side="top", fill="both", expand=True, padx=6, pady=4)

        left = ttk.Frame(paned)
        self.cat_tree = ttk.Treeview(left, show="tree", selectmode="browse")
        self.cat_tree.pack(fill="both", expand=True)
        self.cat_tree.insert("", "end", iid="All", text="All Downloads", open=True)
        for name in CATEGORY_NAMES:
            self.cat_tree.insert("All", "end", iid=f"cat:{name}", text=name)
        self.cat_tree.insert("", "end", iid="Unfinished", text="Unfinished")
        self.cat_tree.insert("", "end", iid="Finished", text="Finished")
        self.cat_tree.insert("", "end", iid="Queue", text="Queue")
        self.cat_tree.selection_set("All")
        self.cat_tree.bind("<<TreeviewSelect>>", self._on_filter)
        paned.add(left, weight=0)

        right = ttk.Frame(paned)
        cols = [c[0] for c in self.COLUMNS]
        self.tree = ttk.Treeview(right, columns=cols, show="headings", selectmode="extended")
        for key, title, width, anchor in self.COLUMNS:
            self.tree.heading(key, text=title, command=lambda k=key: self._sort_by(k))
            self.tree.column(key, width=width, anchor=anchor, stretch=(key == "name"))
        vsb = ttk.Scrollbar(right, orient="vertical", command=self.tree.yview)
        self.tree.configure(yscrollcommand=vsb.set)
        self.tree.grid(row=0, column=0, sticky="nsew")
        vsb.grid(row=0, column=1, sticky="ns")
        right.rowconfigure(0, weight=1)
        right.columnconfigure(0, weight=1)
        paned.add(right, weight=1)

        self.tree.tag_configure("done", foreground="#2e7d32")
        self.tree.tag_configure("error", foreground="#c62828")
        self.tree.tag_configure("active", foreground=ACCENT)
        self.tree.tag_configure("waiting", foreground="#6b6b6b")
        self.tree.bind("<Double-1>", self._on_double_click)
        self.tree.bind("<Return>", self._on_double_click)
        self.tree.bind("<Button-3>", self._on_right_click)
        self.tree.bind("<Button-2>", self._on_right_click)

        self.ctx = tk.Menu(self.root, tearoff=False)
        self.ctx.add_command(label="Open", command=self.open_selected)
        self.ctx.add_command(label="Open folder", command=self.open_folder_selected)
        self.ctx.add_separator()
        self.ctx.add_command(label="Resume", command=self.resume_selected)
        self.ctx.add_command(label="Pause", command=self.pause_selected)
        self.ctx.add_command(label="Redownload", command=self.redownload_selected)
        self.ctx.add_command(label="Show progress", command=self.progress_selected)
        self.ctx.add_separator()
        self.ctx.add_command(label="Copy address", command=self.copy_url_selected)
        self.ctx.add_command(label="Properties...", command=self.properties_selected)
        self.ctx.add_separator()
        self.ctx.add_command(label="Delete...", command=self.delete_selected)

    def _build_statusbar(self):
        bar = ttk.Frame(self.root, relief="sunken")
        bar.pack(side="bottom", fill="x")
        self.status_left = ttk.Label(bar, style="Status.TLabel")
        self.status_left.pack(side="left")
        self.status_right = ttk.Label(bar, style="Status.TLabel")
        self.status_right.pack(side="right")
        self._message = ""
        self._message_until = 0

    def _bind_keys(self):
        self.root.bind("<Control-n>", lambda e: self.add_url())
        self.root.bind("<Control-b>", lambda e: self.add_batch())
        self.root.bind("<Control-a>", lambda e: self.tree.selection_set(self.tree.get_children()))
        self.tree.bind("<Delete>", lambda e: self.delete_selected())
        self.root.bind("<Control-v>", self._paste_url)

    # ------------------------------------------------------------- refresh
    def _matches(self, d):
        f = self.filter
        if f == "All":
            return True
        if f.startswith("cat:"):
            return d.category == f[4:]
        if f == "Unfinished":
            return d.status != COMPLETED
        if f == "Finished":
            return d.status == COMPLETED
        if f == "Queue":
            return d.status in (QUEUED, WAITING) or (d.from_queue and d.is_active)
        return True

    def _values(self, d):
        status = d.status
        if d.status == DOWNLOADING:
            status = f"{d.progress:.1f}%" if d.size > 0 else human_size(d.downloaded)
        elif status == COMPLETED:
            status = "Complete"
        elif status == ERROR:
            status = f"Error: {d.error}"
        elif status == PAUSED and d.size > 0:
            status = f"Paused {d.progress:.1f}%"
        active = d.status in ACTIVE_STATES
        return (
            d.filename,
            human_size(d.size) if d.size >= 0 else "",
            status,
            human_speed(d.speed) if active else "",
            human_time(d.eta) if active and d.eta is not None else "",
            str(d.active_connections) if active and d.active_connections else "",
            datetime.datetime.fromtimestamp(d.added).strftime("%Y-%m-%d %H:%M"),
        )

    @staticmethod
    def _tag(d):
        if d.status == COMPLETED:
            return ("done",)
        if d.status == ERROR:
            return ("error",)
        if d.status in ACTIVE_STATES:
            return ("active",)
        if d.status in (QUEUED, WAITING):
            return ("waiting",)
        return ()

    def _sort_key(self, d):
        key = self.sort[0]
        if key == "name":
            return d.filename.lower()
        if key == "size":
            return d.size
        if key == "status":
            return d.status
        if key == "speed":
            return d.speed
        if key == "eta":
            return d.eta or 0
        return d.added

    def _sort_by(self, key):
        col, rev = self.sort
        self.sort = (key, not rev if col == key else False)
        self._refresh(reschedule=False)

    def _on_filter(self, _event=None):
        sel = self.cat_tree.selection()
        self.filter = sel[0] if sel else "All"
        self._refresh(reschedule=False)

    def _refresh(self, reschedule=True):
        try:
            all_items = self.manager.snapshot()
            items = sorted((d for d in all_items if self._matches(d)), key=self._sort_key, reverse=self.sort[1])
            wanted = [d.id for d in items]
            existing = set(self.tree.get_children())
            for iid in existing - set(wanted):
                self.tree.delete(iid)
                self._rows.pop(iid, None)
            for idx, d in enumerate(items):
                vals = self._values(d)
                if d.id not in existing:
                    self.tree.insert("", idx, iid=d.id, values=vals, tags=self._tag(d))
                else:
                    if self._rows.get(d.id) != vals:
                        self.tree.item(d.id, values=vals, tags=self._tag(d))
                    if self.tree.index(d.id) != idx:
                        self.tree.move(d.id, "", idx)
                self._rows[d.id] = vals

            active = [d for d in all_items if d.status in ACTIVE_STATES]
            total_speed = sum(d.speed for d in active)
            queued = sum(1 for d in all_items if d.status == QUEUED)
            text = f"Active: {len(active)}   Queued: {queued}   Total: {len(all_items)}"
            if total_speed:
                text += f"   Speed: {human_speed(total_speed)}"
            if self.settings["speed_limit_kb"]:
                text += f"   (limit {human_speed(int(self.settings['speed_limit_kb']) * 1024)})"
            if self._message and time.time() < self._message_until:
                text = self._message + "    |    " + text
            self.status_left.config(text=text)
            if self.server and self.server.httpd:
                self.status_right.config(text=f"Browser integration: on (port {self.server.port})")
            elif self.server and self.server.error:
                self.status_right.config(text=f"Browser integration: port {self.server.port} busy")
            else:
                self.status_right.config(text="Browser integration: off")
            if active:
                pct = [d.progress for d in active if d.size > 0]
                title = f"{sum(pct) / len(pct):.0f}% - {APP_NAME}" if pct else APP_NAME
                self.root.title(f"{title} Download Manager")
            else:
                self.root.title(f"{APP_NAME} Download Manager")
        finally:
            if reschedule:
                self.root.after(500, self._refresh)

    def flash(self, message, seconds=6):
        self._message = message
        self._message_until = time.time() + seconds

    # --------------------------------------------------------------- events
    def _process_events(self):
        try:
            while True:
                kind, payload = self.manager.events.get_nowait()
                if kind == "completed":
                    d = self.manager.get(payload)
                    if d:
                        win = self.progress_windows.pop(d.id, None)
                        if win:
                            win.destroy()
                        self.flash(f"Completed: {d.filename}")
                        if self.settings["show_complete_dialog"]:
                            CompleteDialog(self, d)
                elif kind == "error":
                    d = self.manager.get(payload)
                    if d:
                        self.flash(f"Error: {d.filename} - {d.error}")
                elif kind == "browser_add":
                    self.handle_browser_add(payload)
                elif kind == "show":
                    bring_to_front(self.root)
                elif kind == "shutdown":
                    self._shutdown_computer()
                elif kind == "info":
                    self.flash(str(payload))
        except queue.Empty:
            pass
        self.root.after(250, self._process_events)

    def _poll_clipboard(self):
        try:
            text = self.root.clipboard_get()
        except tk.TclError:
            text = ""
        text = (text or "").strip()
        if self._clip_last is None:
            self._clip_last = text  # ignore whatever was on the clipboard at start-up
        elif text != self._clip_last:
            self._clip_last = text
            if (self.settings["clipboard_monitor"] and is_url(text)
                    and extension_of(text.split("?")[0]) in self.settings.capture_extensions()):
                AddDialog(self, url=text)
        self.root.after(1000, self._poll_clipboard)

    def _paste_url(self, _event=None):
        try:
            text = self.root.clipboard_get().strip()
        except tk.TclError:
            return
        if is_url(text):
            AddDialog(self, url=text)

    def handle_browser_add(self, data):
        url = str(data.get("url", ""))
        headers = {}
        if data.get("referrer"):
            headers["Referer"] = str(data["referrer"])
        if data.get("cookies"):
            headers["Cookie"] = str(data["cookies"])
        if data.get("userAgent"):
            headers["User-Agent"] = str(data["userAgent"])
        name = data.get("filename")
        name = sanitize_filename(os.path.basename(str(name).replace("\\", "/"))) if name else None
        if self.settings["browser_skip_dialog"]:
            d = self.manager.add(url, filename=name, headers=headers, start=True)
            self.flash(f"Added from browser: {d.filename}")
            if self.settings["show_progress_window"]:
                self.show_progress(d)
        else:
            AddDialog(self, url=url, filename=name, headers=headers)

    def _shutdown_computer(self):
        engine.shutdown_computer()
        if messagebox.askyesno(APP_NAME, "All scheduled downloads are finished.\n\n"
                               "The computer will shut down in about 60 seconds.\n"
                               "Do you want to CANCEL the shutdown?", parent=self.root):
            engine.cancel_shutdown()

    # -------------------------------------------------------------- helpers
    def selected(self):
        return [d for d in (self.manager.get(i) for i in self.tree.selection()) if d]

    def _on_double_click(self, event=None):
        items = self.selected()
        if not items:
            return
        d = items[0]
        if d.status == COMPLETED:
            self._open(d)
        else:
            self.show_progress(d)

    def _on_right_click(self, event):
        row = self.tree.identify_row(event.y)
        if row and row not in self.tree.selection():
            self.tree.selection_set(row)
        if self.tree.selection():
            self.ctx.tk_popup(event.x_root, event.y_root)

    def _open(self, d):
        path = d.saved_path or d.path
        if not os.path.exists(path):
            messagebox.showerror(APP_NAME, f"File not found:\n{path}", parent=self.root)
            return
        try:
            open_path(path)
        except OSError as exc:
            messagebox.showerror(APP_NAME, str(exc), parent=self.root)

    def show_progress(self, d):
        win = self.progress_windows.get(d.id)
        if win and win.winfo_exists():
            bring_to_front(win)
            return
        self.progress_windows[d.id] = ProgressWindow(self, d)

    # -------------------------------------------------------------- actions
    def add_url(self):
        url = ""
        try:
            clip = self.root.clipboard_get().strip()
            if is_url(clip):
                url = clip
        except tk.TclError:
            pass
        AddDialog(self, url=url)

    def add_batch(self):
        BatchDialog(self)

    def resume_selected(self):
        for d in self.selected():
            if d.status != COMPLETED and not d.is_active:
                self.manager.resume(d)

    def pause_selected(self):
        for d in self.selected():
            self.manager.pause(d)

    def redownload_selected(self):
        items = self.selected()
        if items and messagebox.askyesno(APP_NAME, "Download the selected file(s) again from the start?",
                                         parent=self.root):
            for d in items:
                self.manager.redownload(d)

    def progress_selected(self):
        for d in self.selected():
            if d.status != COMPLETED:
                self.show_progress(d)

    def open_selected(self):
        for d in self.selected()[:1]:
            self._open(d)

    def open_folder_selected(self):
        for d in self.selected()[:1]:
            path = d.saved_path or d.path
            folder = os.path.dirname(path)
            if os.path.exists(path):
                reveal_in_folder(path)
            elif os.path.isdir(folder):
                open_path(folder)

    def copy_url_selected(self):
        items = self.selected()
        if items:
            self.root.clipboard_clear()
            self.root.clipboard_append("\n".join(d.url for d in items))
            self._clip_last = "\n".join(d.url for d in items)

    def properties_selected(self):
        for d in self.selected()[:1]:
            PropertiesDialog(self, d)

    def delete_selected(self):
        items = self.selected()
        if items:
            DeleteDialog(self, items)

    def start_queue(self):
        self.manager.start_queue()
        self.flash("Queue started")

    def set_speed_limit(self, kb):
        self.settings["speed_limit_kb"] = int(kb)
        self.settings.save()
        self.manager.apply_settings()
        self.limit_var.set(int(kb))

    def open_options(self):
        OptionsDialog(self)

    def open_scheduler(self):
        SchedulerDialog(self)

    def start_server(self):
        if self.server:
            self.server.stop()
            self.server = None
        if self.settings["browser_integration"]:
            self.server = IntegrationServer(self, int(self.settings["browser_port"]))
            self.server.start()

    def browser_help(self):
        ext_dir = extension_dir()
        messagebox.showinfo(
            "Browser integration",
            "To send browser downloads to SwiftGet, install the extension once:\n\n"
            "1. Open chrome://extensions (or edge://extensions)\n"
            "2. Turn on 'Developer mode'\n"
            "3. Click 'Load unpacked' and choose the folder:\n"
            f"   {ext_dir}\n\n"
            "After that, downloads of the file types listed in Options > Browser are caught "
            "automatically, and you can right-click any link > 'Download with SwiftGet'.\n"
            "Keep SwiftGet running while you browse.",
            parent=self.root)

    def about(self):
        messagebox.showinfo("About", f"{APP_NAME} Download Manager {VERSION}\n\n"
                            "Multi-connection downloads, pause/resume, queue, scheduler, "
                            "speed limiter and browser integration.\n\nFor personal use.",
                            parent=self.root)

    def on_close(self):
        active = [d for d in self.manager.snapshot() if d.is_active]
        if active and not messagebox.askyesno(
                APP_NAME, f"{len(active)} download(s) are running.\n\n"
                "They will be paused and can be resumed next time. Exit now?", parent=self.root):
            return
        if self.server:
            self.server.stop()
        self.manager.shutdown()
        self.root.destroy()


def resource_path(*parts):
    """Path of a bundled file, both from source and inside the PyInstaller .exe."""
    base = getattr(sys, "_MEIPASS", None) or os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    return os.path.join(base, *parts)


def extension_dir():
    candidates = []
    exe_dir = os.path.dirname(os.path.abspath(sys.executable if getattr(sys, "frozen", False) else __file__))
    candidates.append(os.path.join(exe_dir, "browser-extension"))
    candidates.append(os.path.join(os.path.dirname(exe_dir), "browser-extension"))
    for c in candidates:
        if os.path.isdir(c):
            return c
    return candidates[-1]


# ====================================================================== dialogs
class Dialog(tk.Toplevel):
    def __init__(self, app, title, modal=False):
        super().__init__(app.root)
        self.app = app
        self.title(title)
        self.resizable(True, False)
        self.transient(app.root)
        self.body = ttk.Frame(self, padding=12)
        self.body.pack(fill="both", expand=True)
        self.bind("<Escape>", lambda e: self.destroy())
        if modal:
            self.grab_set()

    def finish_layout(self):
        center_on(self, self.app.root)
        bring_to_front(self)


class AddDialog(Dialog):
    def __init__(self, app, url="", filename=None, headers=None):
        super().__init__(app, "Download File Info")
        self.headers = headers or {}
        self.name_edited = bool(filename)
        self.folder_edited = False
        self.probe_result = None
        self._probe_id = 0
        b = self.body
        b.columnconfigure(1, weight=1)

        ttk.Label(b, text="URL:").grid(row=0, column=0, sticky="w", pady=3)
        self.url_var = tk.StringVar(value=url)
        url_entry = ttk.Entry(b, textvariable=self.url_var, width=70)
        url_entry.grid(row=0, column=1, columnspan=2, sticky="ew", pady=3)
        url_entry.bind("<FocusOut>", lambda e: self.start_probe())
        url_entry.bind("<Return>", lambda e: self.start_probe())

        ttk.Label(b, text="Category:").grid(row=1, column=0, sticky="w", pady=3)
        self.cat_var = tk.StringVar(value=category_for(filename or ""))
        cat = ttk.Combobox(b, textvariable=self.cat_var, values=CATEGORY_NAMES, state="readonly", width=18)
        cat.grid(row=1, column=1, sticky="w", pady=3)
        cat.bind("<<ComboboxSelected>>", lambda e: self._update_folder())

        ttk.Label(b, text="Save as:").grid(row=2, column=0, sticky="w", pady=3)
        self.name_var = tk.StringVar(value=filename or "")
        name_entry = ttk.Entry(b, textvariable=self.name_var)
        name_entry.grid(row=2, column=1, columnspan=2, sticky="ew", pady=3)
        name_entry.bind("<Key>", lambda e: setattr(self, "name_edited", True))

        ttk.Label(b, text="Folder:").grid(row=3, column=0, sticky="w", pady=3)
        self.folder_var = tk.StringVar()
        folder_entry = ttk.Entry(b, textvariable=self.folder_var)
        folder_entry.grid(row=3, column=1, sticky="ew", pady=3)
        folder_entry.bind("<Key>", lambda e: setattr(self, "folder_edited", True))
        ttk.Button(b, text="Browse...", command=self._browse).grid(row=3, column=2, padx=(6, 0))

        ttk.Label(b, text="Connections:").grid(row=4, column=0, sticky="w", pady=3)
        self.conn_var = tk.IntVar(value=int(app.settings["max_connections"]))
        ttk.Spinbox(b, from_=1, to=32, textvariable=self.conn_var, width=6).grid(row=4, column=1, sticky="w")

        self.info_var = tk.StringVar(value="")
        ttk.Label(b, textvariable=self.info_var, foreground="#555").grid(row=5, column=0, columnspan=3,
                                                                          sticky="w", pady=(8, 4))
        btns = ttk.Frame(b)
        btns.grid(row=6, column=0, columnspan=3, sticky="e", pady=(8, 0))
        start_btn = ttk.Button(btns, text="Start Download", command=lambda: self.submit(True))
        start_btn.pack(side="left", padx=3)
        ttk.Button(btns, text="Download Later", command=lambda: self.submit(False)).pack(side="left", padx=3)
        ttk.Button(btns, text="Cancel", command=self.destroy).pack(side="left", padx=3)
        self.bind("<Control-Return>", lambda e: self.submit(True))

        self._update_folder()
        self.finish_layout()
        url_entry.focus_set()
        if is_url(url):
            self.start_probe()
            start_btn.focus_set()

    def _update_folder(self):
        if self.folder_edited:
            return
        base = self.app.settings["download_dir"]
        if self.app.settings["use_category_folders"]:
            base = os.path.join(base, self.cat_var.get() or "Other")
        self.folder_var.set(base)

    def _browse(self):
        folder = filedialog.askdirectory(parent=self, initialdir=self.folder_var.get() or os.path.expanduser("~"))
        if folder:
            self.folder_var.set(folder)
            self.folder_edited = True

    def start_probe(self):
        url = self.url_var.get().strip()
        if not is_url(url):
            if url:
                self.info_var.set("Please enter a valid http(s) or ftp address.")
            return
        if self.probe_result and self.probe_result[0] == url:
            return
        self._probe_id += 1
        probe_id = self._probe_id
        self.info_var.set("Checking file information...")
        result = {}

        def work():
            try:
                result["d"] = self.app.manager.probe(url, self.headers)
            except Exception as exc:  # noqa: BLE001
                result["error"] = engine.friendly_error(exc)

        t = threading.Thread(target=work, daemon=True)
        t.start()
        self._poll_probe(t, result, url, probe_id)

    def _poll_probe(self, thread, result, url, probe_id):
        if not self.winfo_exists() or probe_id != self._probe_id:
            return
        if thread.is_alive():
            self.after(100, lambda: self._poll_probe(thread, result, url, probe_id))
            return
        if "error" in result:
            self.info_var.set(f"Could not check the file ({result['error']}). You can still try to download it.")
            return
        d = result["d"]
        self.probe_result = (url, d)
        if not self.name_edited or not self.name_var.get().strip():
            self.name_var.set(d.filename)
        self.cat_var.set(category_for(self.name_var.get()))
        self._update_folder()
        resume = "Yes" if d.resumable else "No (single connection)"
        self.info_var.set(f"Size: {human_size(d.size)}     Resume capability: {resume}")

    def submit(self, start):
        url = self.url_var.get().strip()
        if not is_url(url):
            messagebox.showerror(APP_NAME, "Please enter a valid download address.", parent=self)
            return
        name = sanitize_filename(self.name_var.get().strip()) if self.name_var.get().strip() else None
        folder = self.folder_var.get().strip() or None
        try:
            conns = max(1, min(32, int(self.conn_var.get())))
        except (tk.TclError, ValueError):
            conns = None
        size, resumable = -1, None
        if self.probe_result and self.probe_result[0] == url:
            info = self.probe_result[1]
            size, resumable = info.size, info.resumable
            name = name or info.filename
        if folder and name and os.path.exists(os.path.join(folder, name)):
            if not messagebox.askyesno(APP_NAME, f"'{name}' already exists in this folder.\n\n"
                                       "Download anyway? (the new file gets a number added)", parent=self):
                return
        d = self.app.manager.add(url, filename=name, folder=folder, headers=self.headers, connections=conns,
                                 start=start, size=size, resumable=resumable)
        self.destroy()
        if start and self.app.settings["show_progress_window"]:
            self.app.show_progress(d)


class BatchDialog(Dialog):
    def __init__(self, app):
        super().__init__(app, "Batch download")
        self.resizable(True, True)
        b = self.body
        b.columnconfigure(0, weight=1)
        b.rowconfigure(1, weight=1)
        ttk.Label(b, text="One address per line. Patterns like file[1-20].jpg, file[01-20].jpg "
                          "or part[a-f].zip are expanded.").grid(row=0, column=0, columnspan=2, sticky="w")
        self.text = tk.Text(b, width=80, height=12, wrap="none")
        self.text.grid(row=1, column=0, columnspan=2, sticky="nsew", pady=6)
        try:
            clip = app.root.clipboard_get()
            if any(is_url(line.strip()) for line in clip.splitlines()):
                self.text.insert("1.0", clip.strip())
        except tk.TclError:
            pass
        frm = ttk.Frame(b)
        frm.grid(row=2, column=0, columnspan=2, sticky="ew")
        frm.columnconfigure(1, weight=1)
        ttk.Label(frm, text="Folder (empty = automatic by category):").grid(row=0, column=0, sticky="w")
        self.folder_var = tk.StringVar()
        ttk.Entry(frm, textvariable=self.folder_var).grid(row=0, column=1, sticky="ew", padx=6)
        ttk.Button(frm, text="Browse...", command=self._browse).grid(row=0, column=2)
        btns = ttk.Frame(b)
        btns.grid(row=3, column=0, columnspan=2, sticky="e", pady=(10, 0))
        ttk.Button(btns, text="Add & start queue", command=lambda: self.submit(True)).pack(side="left", padx=3)
        ttk.Button(btns, text="Add to queue", command=lambda: self.submit(False)).pack(side="left", padx=3)
        ttk.Button(btns, text="Cancel", command=self.destroy).pack(side="left", padx=3)
        self.finish_layout()
        self.text.focus_set()

    def _browse(self):
        folder = filedialog.askdirectory(parent=self)
        if folder:
            self.folder_var.set(folder)

    def submit(self, start):
        urls = []
        for line in self.text.get("1.0", "end").splitlines():
            line = line.strip()
            if line:
                urls.extend(u for u in expand_batch_pattern(line) if is_url(u))
        if not urls:
            messagebox.showerror(APP_NAME, "No valid addresses found.", parent=self)
            return
        folder = self.folder_var.get().strip() or None
        for u in urls:
            self.app.manager.add(u, folder=folder, start=False)
        if start:
            self.app.manager.start_queue()
        self.app.flash(f"{len(urls)} download(s) added to the queue")
        self.destroy()


class ProgressWindow(tk.Toplevel):
    BAR_W = 520

    def __init__(self, app, d):
        super().__init__(app.root)
        self.app = app
        self.d = d
        self.resizable(False, False)
        self.title(d.filename)
        body = ttk.Frame(self, padding=12)
        body.pack(fill="both", expand=True)
        body.columnconfigure(1, weight=1)
        self.vars = {}
        rows = [("url", "Address"), ("status", "Status"), ("size", "File size"), ("done", "Downloaded"),
                ("speed", "Transfer rate"), ("eta", "Time left"), ("resume", "Resume capability")]
        for i, (key, label) in enumerate(rows):
            ttk.Label(body, text=label + ":").grid(row=i, column=0, sticky="w", pady=1)
            var = tk.StringVar()
            ttk.Label(body, textvariable=var, width=60 if key == "url" else None,
                      font=self.app.bold_font if key == "status" else None).grid(row=i, column=1, sticky="w")
            self.vars[key] = var
        r = len(rows)
        self.pbar = ttk.Progressbar(body, maximum=100, length=self.BAR_W)
        self.pbar.grid(row=r, column=0, columnspan=2, sticky="ew", pady=(10, 4))
        self.canvas = tk.Canvas(body, width=self.BAR_W, height=16, highlightthickness=1,
                                highlightbackground="#bbb", bg="#e8e8e8")
        self.canvas.grid(row=r + 1, column=0, columnspan=2, sticky="ew")
        ttk.Label(body, text="Connections:").grid(row=r + 2, column=0, sticky="w", pady=(8, 2))
        self.conn_tree = ttk.Treeview(body, columns=("n", "dl", "info"), show="headings", height=6)
        for key, title, w in (("n", "N.", 40), ("dl", "Downloaded", 120), ("info", "Info", 300)):
            self.conn_tree.heading(key, text=title)
            self.conn_tree.column(key, width=w, anchor="w")
        self.conn_tree.grid(row=r + 3, column=0, columnspan=2, sticky="ew")
        btns = ttk.Frame(body)
        btns.grid(row=r + 4, column=0, columnspan=2, sticky="e", pady=(10, 0))
        self.toggle_btn = ttk.Button(btns, text="Pause", command=self.toggle)
        self.toggle_btn.pack(side="left", padx=3)
        ttk.Button(btns, text="Cancel", command=self.cancel).pack(side="left", padx=3)
        ttk.Button(btns, text="Hide", command=self.destroy).pack(side="left", padx=3)
        self.protocol("WM_DELETE_WINDOW", self.destroy)
        center_on(self, app.root)
        self.update_view()

    def destroy(self):
        self.app.progress_windows.pop(self.d.id, None)
        super().destroy()

    def toggle(self):
        if self.d.is_active:
            self.app.manager.pause(self.d)
        elif self.d.status != COMPLETED:
            self.app.manager.resume(self.d)

    def cancel(self):
        self.app.manager.pause(self.d)
        self.destroy()

    def update_view(self):
        if not self.winfo_exists():
            return
        d = self.d
        if self.app.manager.get(d.id) is None:
            self.destroy()
            return
        pct = d.progress
        self.title(f"{pct:.0f}% {d.filename}")
        self.vars["url"].set(d.url if len(d.url) < 90 else d.url[:87] + "...")
        status = d.status if d.status != ERROR else f"Error: {d.error}"
        if d.status == DOWNLOADING:
            status = "Receiving data..."
        self.vars["status"].set(status)
        self.vars["size"].set(human_size(d.size))
        done = human_size(d.downloaded)
        self.vars["done"].set(f"{done} ({pct:.2f}%)" if d.size > 0 else done)
        active = d.status in ACTIVE_STATES
        self.vars["speed"].set(human_speed(d.speed) if active else "")
        self.vars["eta"].set(human_time(d.eta) if active and d.eta is not None else "")
        if d.probed:
            self.vars["resume"].set("Yes" if d.resumable else "No")
        self.pbar["value"] = pct
        self.toggle_btn.config(text="Pause" if d.is_active else "Resume")
        self._draw_segments()
        self.after(400, self.update_view)

    def _draw_segments(self):
        d = self.d
        c = self.canvas
        c.delete("all")
        w = max(1, c.winfo_width() - 2) if c.winfo_width() > 10 else self.BAR_W
        segs = d.segment_snapshot()
        rows = []
        if d.size > 0 and segs:
            for i, (start, end, pos, active) in enumerate(segs):
                x0 = start * w / d.size
                x1 = pos * w / d.size
                if x1 > x0:
                    c.create_rectangle(x0, 0, x1, 18, fill=ACCENT if active else "#4f8fe8", width=0)
                state = "Complete" if pos > end else ("Receiving data..." if active else "Waiting")
                rows.append((str(i + 1), human_size(pos - start), state))
        elif d.size > 0:
            c.create_rectangle(0, 0, d.progress * w / 100, 18, fill=ACCENT, width=0)
            if d.is_active:
                rows.append(("1", human_size(d.downloaded), "Receiving data..."))
        children = self.conn_tree.get_children()
        for i, row in enumerate(rows):
            iid = str(i)
            if iid in children:
                self.conn_tree.item(iid, values=row)
            else:
                self.conn_tree.insert("", "end", iid=iid, values=row)
        for iid in children:
            if int(iid) >= len(rows):
                self.conn_tree.delete(iid)


class CompleteDialog(Dialog):
    def __init__(self, app, d):
        super().__init__(app, "Download complete")
        self.d = d
        b = self.body
        ttk.Label(b, text="Download complete", style="Big.TLabel").grid(row=0, column=0, columnspan=2,
                                                                       sticky="w", pady=(0, 8))
        info = [("File", d.filename), ("Size", human_size(d.size)), ("Saved to", d.saved_path or ""),
                ("Address", d.url if len(d.url) < 80 else d.url[:77] + "...")]
        for i, (k, v) in enumerate(info, start=1):
            ttk.Label(b, text=k + ":").grid(row=i, column=0, sticky="nw", padx=(0, 8))
            ttk.Label(b, text=v, wraplength=460).grid(row=i, column=1, sticky="w")
        self.dont_show = tk.BooleanVar(value=False)
        ttk.Checkbutton(b, text="Don't show this dialog again", variable=self.dont_show).grid(
            row=6, column=0, columnspan=2, sticky="w", pady=(10, 0))
        btns = ttk.Frame(b)
        btns.grid(row=7, column=0, columnspan=2, sticky="e", pady=(10, 0))
        ttk.Button(btns, text="Open", command=self.open_file).pack(side="left", padx=3)
        ttk.Button(btns, text="Open folder", command=self.open_folder).pack(side="left", padx=3)
        ttk.Button(btns, text="Close", command=self.close).pack(side="left", padx=3)
        self.finish_layout()

    def close(self):
        if self.dont_show.get():
            self.app.settings["show_complete_dialog"] = False
            self.app.settings.save()
        self.destroy()

    def open_file(self):
        self.app._open(self.d)
        self.close()

    def open_folder(self):
        if self.d.saved_path:
            reveal_in_folder(self.d.saved_path)
        self.close()


class DeleteDialog(Dialog):
    def __init__(self, app, items):
        super().__init__(app, "Delete downloads", modal=True)
        self.items = items
        b = self.body
        text = f"Remove {len(items)} download(s) from the list?" if len(items) > 1 else \
            f"Remove '{items[0].filename}' from the list?"
        ttk.Label(b, text=text, wraplength=420).pack(anchor="w")
        self.delete_files = tk.BooleanVar(value=False)
        ttk.Checkbutton(b, text="Also delete the file(s) from disk", variable=self.delete_files).pack(
            anchor="w", pady=(10, 0))
        btns = ttk.Frame(b)
        btns.pack(anchor="e", pady=(12, 0))
        ttk.Button(btns, text="Delete", command=self.ok).pack(side="left", padx=3)
        ttk.Button(btns, text="Cancel", command=self.destroy).pack(side="left", padx=3)
        self.finish_layout()

    def ok(self):
        delete = self.delete_files.get()
        self.destroy()
        for d in self.items:
            win = self.app.progress_windows.pop(d.id, None)
            if win:
                win.destroy()
            self.app.manager.remove(d, delete_file=delete)


class PropertiesDialog(Dialog):
    def __init__(self, app, d):
        super().__init__(app, "Properties")
        self.d = d
        b = self.body
        b.columnconfigure(1, weight=1)
        ttk.Label(b, text="Address:").grid(row=0, column=0, sticky="w", pady=3)
        self.url_var = tk.StringVar(value=d.url)
        state = "normal" if d.status != COMPLETED else "readonly"
        ttk.Entry(b, textvariable=self.url_var, width=80, state=state).grid(row=0, column=1, sticky="ew")
        info = [
            ("File", d.filename), ("Saved to", d.saved_path or d.path), ("Size", human_size(d.size)),
            ("Status", d.status + (f" - {d.error}" if d.error else "")),
            ("Resume capability", "Yes" if d.resumable else ("No" if d.probed else "Unknown")),
            ("Added", datetime.datetime.fromtimestamp(d.added).strftime("%Y-%m-%d %H:%M:%S")),
        ]
        if d.finished_at:
            info.append(("Finished", datetime.datetime.fromtimestamp(d.finished_at).strftime("%Y-%m-%d %H:%M:%S")))
        if d.headers.get("Referer"):
            info.append(("Referer", d.headers["Referer"]))
        for i, (k, v) in enumerate(info, start=1):
            ttk.Label(b, text=k + ":").grid(row=i, column=0, sticky="nw", pady=1)
            ttk.Label(b, text=v, wraplength=560).grid(row=i, column=1, sticky="w", pady=1)
        ttk.Label(b, text="Tip: if a link expired, paste a fresh address above and press Save, "
                          "then Resume.", foreground="#666").grid(row=20, column=0, columnspan=2, sticky="w",
                                                                  pady=(8, 0))
        btns = ttk.Frame(b)
        btns.grid(row=21, column=0, columnspan=2, sticky="e", pady=(10, 0))
        ttk.Button(btns, text="Save", command=self.save).pack(side="left", padx=3)
        ttk.Button(btns, text="Close", command=self.destroy).pack(side="left", padx=3)
        self.finish_layout()

    def save(self):
        url = self.url_var.get().strip()
        if url != self.d.url:
            if not is_url(url):
                messagebox.showerror(APP_NAME, "Invalid address.", parent=self)
                return
            if self.d.is_active:
                self.app.manager.pause(self.d)
            self.d.url = url
            self.app.manager._mark_dirty()
        self.destroy()


class OptionsDialog(Dialog):
    def __init__(self, app):
        super().__init__(app, "Options", modal=True)
        s = app.settings
        nb = ttk.Notebook(self.body)
        nb.pack(fill="both", expand=True)
        self.v = {}

        def var(key, kind=tk.StringVar):
            v = kind(value=s[key])
            self.v[key] = v
            return v

        # General
        g = ttk.Frame(nb, padding=10)
        g.columnconfigure(1, weight=1)
        nb.add(g, text="General")
        ttk.Label(g, text="Download folder:").grid(row=0, column=0, sticky="w", pady=3)
        ttk.Entry(g, textvariable=var("download_dir"), width=50).grid(row=0, column=1, sticky="ew")
        ttk.Button(g, text="Browse...", command=self._browse).grid(row=0, column=2, padx=(6, 0))
        checks = [("use_category_folders", "Save into category sub-folders (Video, Music, Programs...)"),
                  ("show_progress_window", "Show progress window when a download starts"),
                  ("show_complete_dialog", "Show 'Download complete' dialog"),
                  ("clipboard_monitor", "Watch the clipboard for download links")]
        for i, (key, text) in enumerate(checks, start=1):
            ttk.Checkbutton(g, text=text, variable=var(key, tk.BooleanVar)).grid(row=i, column=0, columnspan=3,
                                                                                 sticky="w", pady=2)

        # Connection
        c = ttk.Frame(nb, padding=10)
        c.columnconfigure(1, weight=1)
        nb.add(c, text="Connection")
        spins = [("max_connections", "Connections per download:", 1, 32),
                 ("max_simultaneous", "Downloads at the same time (queue):", 1, 20),
                 ("speed_limit_kb", "Speed limit in KB/s (0 = unlimited):", 0, 1000000),
                 ("max_retries", "Retries per connection:", 0, 100),
                 ("timeout", "Timeout (seconds):", 5, 300)]
        for i, (key, text, lo, hi) in enumerate(spins):
            ttk.Label(c, text=text).grid(row=i, column=0, sticky="w", pady=3)
            ttk.Spinbox(c, from_=lo, to=hi, textvariable=var(key, tk.IntVar), width=10).grid(row=i, column=1,
                                                                                              sticky="w")
        r = len(spins)
        ttk.Label(c, text="Proxy:").grid(row=r, column=0, sticky="w", pady=(10, 3))
        pf = ttk.Frame(c)
        pf.grid(row=r, column=1, sticky="w", pady=(10, 3))
        mode = var("proxy_mode")
        for value, text in (("system", "System"), ("none", "No proxy"), ("manual", "Manual:")):
            ttk.Radiobutton(pf, text=text, value=value, variable=mode).pack(side="left", padx=(0, 6))
        ttk.Entry(pf, textvariable=var("proxy"), width=26).pack(side="left")
        ttk.Label(c, text="User-Agent:").grid(row=r + 1, column=0, sticky="w", pady=3)
        ttk.Entry(c, textvariable=var("user_agent"), width=50).grid(row=r + 1, column=1, sticky="ew")
        ttk.Checkbutton(c, text="Verify HTTPS certificates (recommended)",
                        variable=var("verify_ssl", tk.BooleanVar)).grid(row=r + 2, column=0, columnspan=2,
                                                                        sticky="w", pady=3)

        # Browser
        br = ttk.Frame(nb, padding=10)
        br.columnconfigure(1, weight=1)
        nb.add(br, text="Browser")
        ttk.Checkbutton(br, text="Enable browser integration", variable=var("browser_integration", tk.BooleanVar)
                        ).grid(row=0, column=0, columnspan=2, sticky="w", pady=2)
        ttk.Checkbutton(br, text="Start browser downloads immediately (don't show the dialog)",
                        variable=var("browser_skip_dialog", tk.BooleanVar)).grid(row=1, column=0, columnspan=2,
                                                                                 sticky="w", pady=2)
        ttk.Label(br, text="Local port:").grid(row=2, column=0, sticky="w", pady=3)
        ttk.Spinbox(br, from_=1024, to=65535, textvariable=var("browser_port", tk.IntVar), width=8).grid(
            row=2, column=1, sticky="w")
        ttk.Label(br, text="Capture these file types:").grid(row=3, column=0, columnspan=2, sticky="w", pady=(8, 2))
        self.ext_text = tk.Text(br, height=4, width=60, wrap="word")
        self.ext_text.insert("1.0", s["capture_extensions"])
        self.ext_text.grid(row=4, column=0, columnspan=2, sticky="ew")
        ttk.Label(br, text="Ignore files smaller than (KB):").grid(row=5, column=0, sticky="w", pady=3)
        ttk.Spinbox(br, from_=0, to=10000000, textvariable=var("capture_min_size_kb", tk.IntVar), width=10).grid(
            row=5, column=1, sticky="w")
        ttk.Button(br, text="How to install the browser extension...", command=app.browser_help).grid(
            row=6, column=0, columnspan=2, sticky="w", pady=(10, 0))

        btns = ttk.Frame(self.body)
        btns.pack(anchor="e", pady=(10, 0))
        ttk.Button(btns, text="OK", command=self.save).pack(side="left", padx=3)
        ttk.Button(btns, text="Cancel", command=self.destroy).pack(side="left", padx=3)
        self.finish_layout()

    def _browse(self):
        folder = filedialog.askdirectory(parent=self, initialdir=self.v["download_dir"].get())
        if folder:
            self.v["download_dir"].set(folder)

    def save(self):
        s = self.app.settings
        old_port, old_enabled = s["browser_port"], s["browser_integration"]
        try:
            for key, v in self.v.items():
                s[key] = v.get()
        except tk.TclError:
            messagebox.showerror(APP_NAME, "Please enter valid numbers.", parent=self)
            return
        s["capture_extensions"] = " ".join(self.ext_text.get("1.0", "end").split())
        s.save()
        self.app.manager.apply_settings()
        self.app.limit_var.set(int(s["speed_limit_kb"] or 0))
        if old_port != s["browser_port"] or old_enabled != s["browser_integration"]:
            self.app.start_server()
        self.destroy()


class SchedulerDialog(Dialog):
    def __init__(self, app):
        super().__init__(app, "Scheduler")
        s = app.settings
        b = self.body
        self.enabled = tk.BooleanVar(value=s["scheduler_enabled"])
        self.start_t = tk.StringVar(value=s["scheduler_start"])
        self.stop_t = tk.StringVar(value=s["scheduler_stop"])
        self.shutdown = tk.BooleanVar(value=s["scheduler_shutdown"])
        ttk.Label(b, text="Downloads added with 'Download Later' wait in the queue.\n"
                          "The scheduler starts the queue every day at the chosen time.",
                  foreground="#555").grid(row=0, column=0, columnspan=2, sticky="w", pady=(0, 8))
        ttk.Checkbutton(b, text="Enable scheduler", variable=self.enabled).grid(row=1, column=0, columnspan=2,
                                                                                 sticky="w")
        ttk.Label(b, text="Start queue at (HH:MM, 24h):").grid(row=2, column=0, sticky="w", pady=3)
        ttk.Entry(b, textvariable=self.start_t, width=8).grid(row=2, column=1, sticky="w")
        ttk.Label(b, text="Stop queue at (optional):").grid(row=3, column=0, sticky="w", pady=3)
        ttk.Entry(b, textvariable=self.stop_t, width=8).grid(row=3, column=1, sticky="w")
        ttk.Checkbutton(b, text="Shut down the computer when the queue is finished",
                        variable=self.shutdown).grid(row=4, column=0, columnspan=2, sticky="w", pady=(6, 0))
        btns = ttk.Frame(b)
        btns.grid(row=5, column=0, columnspan=2, sticky="e", pady=(12, 0))
        ttk.Button(btns, text="Start queue now", command=self.start_now).pack(side="left", padx=3)
        ttk.Button(btns, text="OK", command=self.save).pack(side="left", padx=3)
        ttk.Button(btns, text="Cancel", command=self.destroy).pack(side="left", padx=3)
        self.finish_layout()

    @staticmethod
    def _valid(t):
        try:
            datetime.datetime.strptime(t, "%H:%M")
            return True
        except ValueError:
            return False

    def _normalize(self, t):
        return datetime.datetime.strptime(t, "%H:%M").strftime("%H:%M")

    def save(self):
        start, stop = self.start_t.get().strip(), self.stop_t.get().strip()
        if self.enabled.get() and not self._valid(start):
            messagebox.showerror(APP_NAME, "Start time must look like 02:30", parent=self)
            return
        if stop and not self._valid(stop):
            messagebox.showerror(APP_NAME, "Stop time must look like 07:00 (or be empty)", parent=self)
            return
        s = self.app.settings
        s["scheduler_enabled"] = self.enabled.get()
        s["scheduler_start"] = self._normalize(start) if self._valid(start) else start
        s["scheduler_stop"] = self._normalize(stop) if stop else ""
        s["scheduler_shutdown"] = self.shutdown.get()
        s.save()
        self.destroy()

    def start_now(self):
        self.app.manager.start_queue()
        self.app.manager._shutdown_armed = bool(self.shutdown.get())
        self.destroy()
