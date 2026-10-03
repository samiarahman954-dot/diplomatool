# WhatsApp Chat Exporter (Firefox)

Ekta Firefox extension jeta **WhatsApp Web** (web.whatsapp.com) theke je kono chat **TXT, HTML, JSON ba CSV** file hisebe download kore.
Sob kaj apnar computer-ei hoy. Kono chat kothao pathano hoy na.

A Firefox add-on that exports the currently open WhatsApp Web chat to TXT, HTML, JSON or CSV. Everything runs locally.

## Features

- **4 format:** TXT (WhatsApp-er nijer export-er moto), HTML (WhatsApp-er moto bubble design, dark mode shoho), JSON, CSV (Excel-e Bangla thik moto dekhay)
- **HTML-e chobi shoho:** chat-er image, sticker ar video-r thumbnail HTML file-er bhitore embed hoy. Ekta file-ei sob, internet chara-o khola jay. Chobi-te click korle boro hoye dekhay.
  Je chobi WhatsApp Web-e ekhono download hoyni (jhapsa preview + download button), seta extension nije download kore asol chobi ney.
- **Attachment download (ZIP, PDF, document, photo, video, sticker):** sob attachment ekta folder-e organized vabe save hoy, ar chat file-e protita message tar nijer attachment-er sathe link kora thake:
  ```
  Downloads/
    WhatsApp Chat - Family Group - 2026-10-03/
      WhatsApp Chat - Family Group.html      ← chat (photo, video, file link shoho)
      attachments/
        3-1-2026 10.25 - Rahim - IMG.png
        3-1-2026 10.30 - Rahim - project files.zip
        4-1-2026 10.35 - You - report.pdf
  ```
  TXT-e lekha thake `3/1/2026, 10:30 - Rahim: <attached: attachments/3-1-2026 10.30 - Rahim - project files.zip>`, CSV/JSON-e `attachment` column/field-e path thake.
- **Full history:** chat-er upore auto-scroll kore purono message load kore
- Sender, date, time, reply (quoted message), deleted message, media type (image, video, audio, document, sticker) dhore
- Emoji ar Bangla text thik thake
- Page-e progress box dekhay. "Stop" chaple ja load hoyeche ta diyei export hoy
- "Only last N messages" diye shudhu sheser kichu message export kora jay

> "Download attachments" off thakle: chobi shudhu HTML-e embed hoy ("Include images in HTML export"), ar 1.5 MB-er boro chobi 1600px-e chhoto kora hoy. Onno file download hoy na.
> Attachment download korar somoy extension WhatsApp-er download button nije click kore. WhatsApp je file save korte chay, seta Downloads-er bodole export folder-e jay. Export shesh hole WhatsApp-er download abar swabhabik hoye jay.

## Install (Firefox-e)

### Temporary (test korar jonno, sobcheye shohoj)
1. Firefox-e `about:debugging#/runtime/this-firefox` open korun
2. **"Load Temporary Add-on…"** e click korun
3. Ei folder-er `manifest.json` file-ta select korun
4. Firefox bondho korle add-on chole jabe. Abar use korte hole abar load korte hobe.

### Permanent
Mozilla-r signature chara Firefox permanent add-on install korte dey na. Duita upay ache:
- **Self-sign (recommended):** [addons.mozilla.org Developer Hub](https://addons.mozilla.org/developers/)-e account khule zip-ta "On your own" (unlisted) hisebe upload korun. Signed `.xpi` download kore Firefox-e drag & drop korun.
  Command line diye: `npx web-ext sign --channel=unlisted --api-key=... --api-secret=...`
- **Firefox Developer Edition / Nightly:** `about:config`-e `xpinstall.signatures.required` = `false` kore zip-ta install korun.

### Zip banano
```sh
cd whatsapp-exporter-firefox
npx web-ext build      # web-ext-artifacts/whatsapp_chat_exporter-1.3.0.zip
npx web-ext lint       # check
npx web-ext run        # Firefox-e test run
```

## Kivabe use korben

1. Firefox-e https://web.whatsapp.com open kore QR code diye login korun
2. Je chat export korte chan seta open korun
3. Toolbar-e sobuj extension icon-e click korun
4. Format bachun, option thik korun, **Export chat** chapun
5. WhatsApp tab open rakhun. Upore dan dike progress dekhabe. Shesh hole file Downloads folder-e chole jabe.

Boro chat (hajar hajar message) load hote kichu minute lagte pare.

## Output example (TXT)

```
WhatsApp chat: Family Group
Exported: 10/3/2026, 2:33:37 PM (49 messages)

1/1/2026, 10:00 - Rahim: Assalamu alaikum 😀
1/1/2026, 10:07 - Karim: <Media omitted: image>
2/1/2026, 10:15 - You: [Reply to: "kal kokhon?"] Bikel 5 tay
```

## Files

| File | Kaj |
|---|---|
| `manifest.json` | Extension config (Manifest V2, Firefox 140+) |
| `content.js` | WhatsApp Web page theke message pore, scroll kore, file banay |
| `background.js` | File download kore (downloads API) |
| `popup/` | Toolbar button-er menu |

## Limitations

- "No messages found" dekhale progress box-er **Copy debug info** button chapun ar report-ta developer-ke pathan. Report-e kono message text, naam ba number thake na, shudhu page-er structure thake.
- WhatsApp Web-er HTML structure change hole kichu jinish (jemon media type, reply) bhul dekhate pare. Message text, sender, time sadharonoto `data-pre-plain-text` theke ase, ja beshi stable.
- Phone-e thaka khub purono message WhatsApp Web-e load na hole export-eo asbe na.
- Video ba voice message WhatsApp Web-e load na hole (shudhu play button, kono file nei) seta download hoy na. Chat-e `<Media omitted: video>` thakbe.
- Je file download hoyni, seta chat-e "(not downloaded)" hisebe dekhay, document-er naam shoho.
- Boro chat-e onek attachment thakle export-e onek somoy lagte pare.
- Firefox-e "Always ask you where to save files" on thakle protita file-er jonno dialog aste pare. Settings → General → Downloads-e "Save files to Downloads" select korun.
- WhatsApp phone theke muche fela ba expire hoye jawa purono media load korte na parle shudhu jhapsa preview ba "not included" dekhabe.
