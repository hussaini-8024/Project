import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

const proxy = {
  "/api": "http://127.0.0.1:8000",
  "/ws": { target: "ws://127.0.0.1:8000", ws: true },
  "/docs": "http://127.0.0.1:8000",
  "/openapi.json": "http://127.0.0.1:8000",
};

export default defineConfig({
  plugins: [react()],
  server: {
    host: "0.0.0.0",
    port: 5173,
    // Vite 6 blocks unknown Host headers by default; allow LAN IPs and hostnames.
    allowedHosts: true,
    proxy,
  },
  preview: {
    host: "0.0.0.0",
    port: 5173,
    allowedHosts: true,
    proxy,
  },
});
