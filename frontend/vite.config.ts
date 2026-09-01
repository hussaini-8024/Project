import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

const proxy = {
  "/api": "http://127.0.0.1:18000",
  "/ws": { target: "ws://127.0.0.1:18000", ws: true },
  "/docs": "http://127.0.0.1:18000",
  "/openapi.json": "http://127.0.0.1:18000",
};

export default defineConfig({
  plugins: [react()],
  server: {
    host: "0.0.0.0",
    port: 18173,
    allowedHosts: true,
    proxy,
  },
  preview: {
    host: "0.0.0.0",
    port: 18173,
    allowedHosts: true,
    proxy,
  },
});
