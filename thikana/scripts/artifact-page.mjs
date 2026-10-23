// Turns dist/index.html into dist/thikana.html for claude.ai artifact hosting:
// the host wraps the page in its own <html>/<head>/<body>, so we keep only the
// tags inside them, in order.
import { readFileSync, writeFileSync } from "node:fs";

const html = readFileSync("dist/index.html", "utf8");
const head = /<head>([\s\S]*?)<\/head>/.exec(html)[1];
const body = /<body>([\s\S]*?)<\/body>/.exec(html)[1];

const keep = head
  .split("\n")
  .map((l) => l.trim())
  .filter((l) => l && !/<meta charset|<meta name="viewport"|rel="icon"/.test(l));

const page = [
  '<meta name="claude:viewport-fit-sides" content="cover">',
  ...keep,
  body.trim(),
].join("\n");

writeFileSync("dist/thikana.html", page + "\n");
console.log("wrote dist/thikana.html");
