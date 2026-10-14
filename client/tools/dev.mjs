import { existsSync } from "node:fs";
import { spawn } from "node:child_process";
import path from "node:path";
import net from "node:net";
import { resolvePhpBinary } from "./php-binary.mjs";

const root = path.resolve(import.meta.dirname, "..");
const backend = path.resolve(root, "..", "server");
const php = resolvePhpBinary();
if (!existsSync(path.join(backend, "artisan"))) {
  throw new Error(`Laravel backend tidak ditemukan: ${backend}`);
}
const children = [];
let closing = false;
function stop(code = 0) {
  if (closing) return;
  closing = true;
  for (const child of children) {
    if (!child.pid) continue;
    if (process.platform === "win32")
      spawn("taskkill", ["/pid", String(child.pid), "/t", "/f"], {
        windowsHide: true,
        stdio: "ignore",
      });
    else child.kill("SIGTERM");
  }
  setTimeout(() => process.exit(code), 500);
}
async function isPortAvailable(port) {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.once("error", (error) => {
      if (error.code === "EADDRINUSE") resolve(false);
      else reject(error);
    });
    server.listen(port, "127.0.0.1", () => server.close(() => resolve(true)));
  });
}

if (!(await isPortAvailable(3170))) {
  throw new Error("Port 3170 sudah digunakan. Tutup Vite/Frontend Pharma yang lama sebelum menjalankan launcher.");
}

let apiPort;
for (let port = 8170; port <= 8190; port++) {
  if (await isPortAvailable(port)) {
    apiPort = port;
    break;
  }
}
if (!apiPort) {
  throw new Error("Tidak ada port API yang tersedia pada rentang 8170-8190.");
}

function start(command, args, cwd = root, label = command) {
  const child = spawn(command, args, {
    cwd,
    env: { ...process.env, PHARMA_API_PORT: String(apiPort) },
    stdio: "inherit",
    windowsHide: true,
  });
  children.push(child);
  child.on("error", (e) => {
    if (e.code === "ENOENT" && command === php) {
      console.error(
        `PHP tidak ditemukan ("${php}"). Pastikan PHP ada di PATH atau set PHP_BINARY ke lokasi php.exe.`,
      );
    } else {
      console.error(`${label} gagal dijalankan: ${e.message}`);
    }
    stop(1);
  });
  child.on("exit", (code) => {
    if (!closing) stop(code || 0);
  });
}
start(
  php,
  ["artisan", "serve", "--host=127.0.0.1", `--port=${apiPort}`],
  backend,
  "Laravel API",
);
start(php, ["artisan", "schedule:work"], backend, "Laravel scheduler");
start(process.execPath, [
  "node_modules/vite/bin/vite.js",
  "--host",
  "0.0.0.0",
  "--port",
  "3170",
  "--strictPort",
]);
process.on("SIGINT", () => stop());
process.on("SIGTERM", () => stop());
console.log(
  `Anteraja Pharma: http://localhost:3170 (API: ${apiPort}) — Ctrl+C menghentikan server milik launcher ini.`,
);
