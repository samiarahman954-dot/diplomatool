# SwiftGet Download Manager

একটা multi-connection download manager, ব্যক্তিগত ব্যবহারের জন্য বানানো। IDM-এর মত মূল ফিচারগুলো আছে, তবে
কোডটা পুরোপুরি নতুন করে লেখা (IDM-এর কোনো কোড, ডিজাইন বা নাম ব্যবহার করা হয়নি)। পুরোটা Python
standard library দিয়ে লেখা, তাই চালাতে বাড়তি কোনো package লাগে না।

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
| **Progress window** | প্রতিটা connection-এর অবস্থা, segment map, speed আর বাকি সময় দেখায় |
| **Expired link refresh** | Properties থেকে নতুন link বসিয়ে আগের progress রেখেই resume করা যায় |
| **Proxy, User-Agent, cookies/referer** | browser থেকে আসা download-এর cookies আর referer সাথে যায়, তাই login লাগে এমন সাইটেও কাজ করে |

## কীভাবে পাবেন (Get the .exe)

### উপায় ১ — GitHub থেকে তৈরি করা .exe (সবচেয়ে সহজ)
1. GitHub-এ repo-র **Actions** ট্যাবে যান → **Build SwiftGet for Windows** খুলুন।
2. সবচেয়ে নতুন সফল run-টা খুলুন। নিচে **Artifacts** অংশে থাকা **SwiftGet-windows** ডাউনলোড করুন।
3. zip খুললে `SwiftGet.exe` আর `browser-extension` folder পাবেন। দুটো একই folder-এ রাখুন।
4. `SwiftGet.exe` চালান। প্রথমবার Windows SmartScreen দেখালে **More info → Run anyway** চাপুন, কারণ exe-টা sign করা নেই।

### উপায় ২ — নিজের PC-তে build করুন
1. [python.org](https://www.python.org/downloads/) থেকে Python 3.10 বা তার নতুন version install করুন ("Add to PATH" টিক দিয়ে)।
2. `download-manager` folder-এ `build_windows.bat`-এ double-click করুন।
3. `dist\SwiftGet.exe` তৈরি হবে।

### উপায় ৩ — build ছাড়াই সরাসরি চালান
```bash
cd download-manager
python run.py
```

## Browser extension install (একবারই করতে হবে)
1. Chrome-এ `chrome://extensions` খুলুন (Edge-এ `edge://extensions`)।
2. উপরে ডানদিকের **Developer mode** চালু করুন।
3. **Load unpacked** চাপুন, তারপর `browser-extension` folder-টা বেছে নিন।
4. এখন থেকে zip, exe, mp4, pdf ইত্যাদি ফাইল ডাউনলোড করলে SwiftGet নিজে থেকে ধরে নেবে। কোন কোন ধরনের ফাইল ধরবে
   সেটা **Options ▸ Browser** থেকে বদলাতে পারবেন।
5. SwiftGet বন্ধ থাকলে browser আগের মত নিজেই ফাইল নামাবে।

## ব্যবহার (Usage tips)
- **Ctrl+N** — নতুন URL, **Ctrl+V** (main window-এ) — clipboard-এর link যোগ করে, **Ctrl+B** — batch, **Del** — delete।
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
│   ├── gui.py             # Tkinter UI
│   ├── server.py          # 127.0.0.1 API for the browser extension
│   ├── config.py          # settings
│   └── utils.py
├── browser-extension/     # Chrome/Edge Manifest V3 extension
├── tests/test_engine.py   # python -m unittest discover -s tests
├── tools/make_icons.py    # regenerates the icons
└── build_windows.bat
```

## Notes
- এটা নিজের ব্যক্তিগত ব্যবহারের জন্য। শুধু সেই কনটেন্ট ডাউনলোড করুন যেটা নামানোর অধিকার আপনার আছে।
- এখনো যা নেই: YouTube বা অন্য streaming সাইট থেকে ভিডিও ধরা (IDM-এর "video grabber"), system tray icon। দরকার হলে পরে যোগ করা যাবে।
- Browser integration শুধু `127.0.0.1`-এ শোনে। extension বাদে অন্য কোনো website এতে download যোগ করতে পারে না।
