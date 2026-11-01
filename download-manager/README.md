# SwiftGet Download Manager

একটা multi-connection download manager, ব্যক্তিগত ব্যবহারের জন্য বানানো। IDM-এর মত মূল ফিচারগুলো আছে, তবে
কোডটা পুরোপুরি নতুন করে লেখা (IDM-এর কোনো কোড, ডিজাইন বা নাম ব্যবহার করা হয়নি)। Download engine শুধু Python
standard library দিয়ে লেখা। দেখতে Windows 11 এর মত modern করার জন্য ঐচ্ছিক `sv-ttk` theme ব্যবহার করা হয়েছে।

*A multi-connection download manager for personal use, written from scratch in pure Python (standard library only).*

## ফিচার (Features)

| ফিচার | বিবরণ |
|---|---|
| **Multi-connection download** | একটা ফাইল ১–৩২টা অংশে ভাগ করে একসাথে নামায় (HTTP Range), তাই speed অনেক বেশি পাওয়া যায় |
| **Dynamic segmentation** | কোনো connection নিজের অংশ শেষ করলে, বাকি সবচেয়ে বড় অংশটা দুই ভাগ করে সেটাও ধরে, তাই সব connection শেষ পর্যন্ত ব্যস্ত থাকে |
| **Pause / Resume** | যেকোনো সময় pause করুন। অ্যাপ বন্ধ করলে বা PC restart দিলেও পরে যেখানে থেমেছিল সেখান থেকে আবার শুরু হয় |
| **Auto retry** | নেট কেটে গেলে প্রতিটা connection নিজে থেকে আবার চেষ্টা করে |
| **Browser integration** | Chrome / Edge / Brave extension দেওয়া আছে। বেছে নেওয়া ধরনের ফাইল browser থেকে সরাসরি SwiftGet-এ চলে আসে, আর link-এ right-click করলে "Download with SwiftGet" অপশন পাবেন |
| **Clipboard monitor** | download link copy করলেই Add dialog খুলে যায় |
| **Categories** | Video, Music, Programs, Compressed, Documents, Images — নিজে থেকে আলাদা folder-এ সেভ হয় |
| **Queue + Scheduler** | "Download Later" দিলে ফাইল queue-তে অপেক্ষা করে। নির্দিষ্ট সময়ে queue শুরু বা বন্ধ করা যায়, আর শেষ হলে PC shutdown-ও করা যায় |
| **Speed limiter** | পুরো অ্যাপের জন্য একটা speed limit (Downloads ▸ Speed limiter) |
| **Batch download** | একসাথে অনেক link দেওয়া যায়, আর `file[01-50].jpg` এর মত pattern নিজে থেকে বড় হয়ে যায় |
| **Modern UI** | Light/Dark mode (☾ বাটন বা Settings থেকে), card-style list যেখানে প্রতিটা ফাইলের নিজের progress bar আছে, sidebar-এ গোনা সংখ্যা, search box |
| **Progress window** | প্রতিটা connection-এর অবস্থা, segment map, speed আর বাকি সময় দেখায় |
| **Expired link refresh** | Properties থেকে নতুন link বসিয়ে আগের progress রেখেই resume করা যায় |
| **Proxy, User-Agent, cookies/referer** | browser থেকে আসা download-এর cookies আর referer সাথে যায়, তাই login লাগে এমন সাইটেও কাজ করে |

## Install (IDM-এর মতই)

1. GitHub-এ repo-র **Actions** ট্যাব খুলুন → **Build SwiftGet for Windows** → সবচেয়ে নতুন সফল run খুলুন।
2. নিচের **Artifacts** অংশ থেকে **SwiftGet-Setup** ডাউনলোড করুন। zip খুললে `SwiftGet-Setup.exe` পাবেন।
3. `SwiftGet-Setup.exe` চালান, তারপর **Next → Install → Finish** চাপুন। Setup-টা sign করা নেই, তাই SmartScreen দেখালে
   **More info → Run anyway** চাপুন।
4. Install হলে যা যা হয়:
   - Start Menu-তে **SwiftGet**, আর চাইলে Desktop-এ shortcut
   - Windows চালু হলে SwiftGet নিজে থেকে tray-তে চালু হয়, তাই প্রতিবার খুলতে হয় না
   - window-এর ✕ চাপলে অ্যাপ বন্ধ হয় না, ঘড়ির পাশের tray icon-এ চলতে থাকে। পুরো বন্ধ করতে tray icon-এ
     right-click করে **Exit** চাপুন
   - download শেষ হলে notification আসে
   - Uninstall করতে: **Settings → Apps → SwiftGet → Uninstall**

Install না করে চালাতে চাইলে **SwiftGet-portable** artifact-ও আছে।

### Digital signature
GitHub-এ যে `SwiftGet.exe`, `SwiftGet-Setup.exe` আর uninstaller তৈরি হয়, তিনটাতেই digital signature থাকে। ফাইলে
right-click করে **Properties → Digital Signatures** খুললে signer হিসেবে "SwiftGet" দেখা যাবে। এতে বোঝা যায় build-এর পর
ফাইলে কেউ হাত দেয়নি।

- এখনকার signature একটা **self-signed** certificate দিয়ে করা। প্রতিটা build নতুন certificate বানায়, আর তার private key
  build শেষ হলেই মুছে যায়।
- Windows self-signed certificate চেনে না, তাই SmartScreen-এর "Unknown publisher" সতর্কবার্তা তবুও আসবে। নিজের PC-তে
  তাড়াতাড়ি সরাতে: ডাউনলোড করা Setup ফাইলে right-click → **Properties** → নিচে **Unblock** টিক দিন → OK।
- **আসল certificate দিয়ে sign করতে** (যাতে সতর্কবার্তা পুরো চলে যায়): CA-এর কাছ থেকে code-signing certificate কিনুন
  (`.pfx`)। তারপর GitHub repo-র **Settings → Secrets and variables → Actions**-এ দুটো secret যোগ করুন:
  - `SIGN_PFX_BASE64`: `.pfx` ফাইলের base64। PowerShell-এ বের করতে:
    `[Convert]::ToBase64String([IO.File]::ReadAllBytes("cert.pfx"))`
  - `SIGN_PFX_PASSWORD`: `.pfx`-এর password

  এরপর থেকে প্রতিটা build নিজে থেকেই সেই certificate দিয়ে sign হবে, কোড বদলাতে হবে না। খেয়াল রাখবেন, অনেক নতুন
  certificate এখন hardware token বা cloud-এ থাকে, `.pfx` হিসেবে দেয় না। সেক্ষেত্রে build-এর sign করার ধাপটা বদলাতে হবে।

### নিজের PC-তে build করুন
1. [python.org](https://www.python.org/downloads/) থেকে Python 3.10 বা তার নতুন version install করুন ("Add to PATH" টিক দিয়ে),
   আর [Inno Setup 6](https://jrsoftware.org/isinfo.php) install করুন।
2. `download-manager` folder-এ `build_windows.bat`-এ double-click করুন।
3. `dist-installer\SwiftGet-Setup.exe` তৈরি হবে।

### Code থেকে সরাসরি চালান
```bash
cd download-manager
pip install -r requirements.txt   # ঐচ্ছিক: modern theme + tray icon
python run.py
```

## Browser extension install (একবারই করতে হবে)
প্রথমবার SwiftGet খুললে Welcome screen-এ এই ধাপগুলো দেখায়, সাথে "Copy address" আর "Open extension folder" বাটনও থাকে।

1. Chrome-এ `chrome://extensions` খুলুন (Edge-এ `edge://extensions`)।
2. উপরে ডানদিকের **Developer mode** চালু করুন।
3. **Load unpacked** চাপুন, তারপর `C:\Program Files\SwiftGet\browser-extension` folder-টা বেছে নিন।
4. এখন থেকে zip, exe, mp4, pdf ইত্যাদি ফাইল ডাউনলোড করলে SwiftGet নিজে থেকে ধরে নেবে। কোন কোন ধরনের ফাইল ধরবে
   সেটা **Options ▸ Browser** থেকে বদলাতে পারবেন।
5. SwiftGet বন্ধ থাকলে browser আগের মত নিজেই ফাইল নামাবে।

## ব্যবহার (Usage tips)
- **Ctrl+F** — search, **Ctrl+N** — নতুন URL, **Ctrl+V** (main window-এ) — clipboard-এর link যোগ করে, **Ctrl+B** — batch, **Del** — delete।
- প্রতিটা card-এর ডানদিকের গোল বাটন: চলতি download হলে pause, থেমে থাকলে resume, শেষ হলে folder খোলে।
- কোনো download-এ double-click করলে: চলতি download হলে progress window খোলে, শেষ হয়ে গেলে ফাইলটা খোলে।
- Right-click মেনুতে আছে Open, Open folder, Redownload, Copy address, Properties।
- Settings আর download list সেভ হয় `%APPDATA%\SwiftGet`-এ।
- ফাইল ডাউনলোড হওয়ার সময় `.swg` নামে থাকে, শেষ হলে আসল নামে বদলে যায়।

## Project structure
```
download-manager/
├── run.py                 # entry point
├── swiftget/
│   ├── engine.py          # segmented downloader, queue, scheduler, persistence
│   ├── gui.py             # main window + dialogs
│   ├── widgets.py         # custom-drawn download cards & sidebar
│   ├── theme.py           # light / dark theme, fonts, colours
│   ├── server.py          # 127.0.0.1 API for the browser extension
│   ├── tray.py            # system-tray icon + notifications
│   ├── autostart.py       # start with Windows
│   ├── config.py          # settings
│   └── utils.py
├── browser-extension/     # Chrome/Edge Manifest V3 extension
├── tests/test_engine.py   # python -m unittest discover -s tests
├── tools/make_icons.py    # regenerates the icons
├── installer/swiftget.iss # Inno Setup script -> SwiftGet-Setup.exe
└── build_windows.bat
```

## Notes
- এটা নিজের ব্যক্তিগত ব্যবহারের জন্য। শুধু সেই কনটেন্ট ডাউনলোড করুন যেটা নামানোর অধিকার আপনার আছে।
- এখনো যা নেই: YouTube বা অন্য streaming সাইট থেকে ভিডিও ধরা (IDM-এর "video grabber")। দরকার হলে পরে যোগ করা যাবে।
- Chrome-এর নিয়মের কারণে browser extension নিজে থেকে install করা যায় না, ওটা একবার হাতে বসাতে হয়।
- Browser integration শুধু `127.0.0.1`-এ শোনে। extension বাদে অন্য কোনো website এতে download যোগ করতে পারে না।
