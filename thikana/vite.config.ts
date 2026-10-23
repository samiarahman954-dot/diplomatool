import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  base: "./",
  plugins: [react()],
  // Don't inherit the PostCSS/Tailwind config of the parent folder.
  css: { postcss: {} },
});
