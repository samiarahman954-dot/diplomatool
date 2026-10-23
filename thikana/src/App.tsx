import { useEffect, useState } from "react";
import { HashRouter, Link, MemoryRouter, Route, Routes, useLocation } from "react-router-dom";
import { readSaved, save } from "./lib/hooks";
import { LangContext, translate, type Lang } from "./lib/i18n";
import { HOSTED_ON_CLAUDE, store, type StoreMode } from "./lib/store";

// The claude.ai viewer doesn't pass "#/path" URLs through, so keep routes in memory there.
const Router = HOSTED_ON_CLAUDE ? MemoryRouter : HashRouter;
import { Home } from "./pages/Home";
import { Mailbox } from "./pages/Mailbox";
import { Write } from "./pages/Write";

// Every visit to /write (including "reply" and "write another") starts a blank letter.
function FreshWrite() {
  return <Write key={useLocation().key} />;
}

export function App() {
  const [lang, setLangState] = useState<Lang>(() => {
    const l = readSaved<Lang>("thikana.lang") ?? "bn";
    document.documentElement.lang = l;
    return l;
  });
  const [mode, setMode] = useState<StoreMode>(store.mode);
  useEffect(() => {
    store.ready.then(setMode);
  }, []);
  const setLang = (l: Lang) => {
    setLangState(l);
    save("thikana.lang", l);
    document.documentElement.lang = l;
  };

  return (
    <LangContext.Provider value={{ lang, setLang }}>
      <Router>
        <div className="wrap">
          <header className="top">
            <Link to="/" className="brand">
              <span aria-hidden>📮</span>
              <span>
                {translate(lang, "appName")} <small>{lang === "bn" ? "Thikana" : "ঠিকানা"}</small>
              </span>
            </Link>
            <button className="lang" onClick={() => setLang(lang === "bn" ? "en" : "bn")}>
              {lang === "bn" ? "English" : "বাংলা"}
            </button>
          </header>
          {mode === "local" && (
            <div className="banner">{translate(lang, HOSTED_ON_CLAUDE ? "signedOut" : "demoMode")}</div>
          )}
          <Routes>
            <Route path="/" element={<Home />} />
            <Route path="/write" element={<FreshWrite />} />
            <Route path="/m/:key" element={<Mailbox />} />
            <Route path="*" element={<Home />} />
          </Routes>
        </div>
      </Router>
    </LangContext.Provider>
  );
}
