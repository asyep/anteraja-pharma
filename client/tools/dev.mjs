import { spawn } from "node:child_process";
import path from "node:path";
import net from "node:net";

const root = path.resolve(import.meta.dirname, "..");
const php =
  process.env.PHP_BINARY || (process.platform === "win32" ? "C:/xampp/php/php.exe" : "php");
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
for (const port of [3170, 8170])
  await new Promise((resolve, reject) => {
    const server = net.createServer();
    server.once("error", () =>
      reject(
        new Error(
          `Port ${port} sudah digunakan. Tutup server Pharma lama; server lain tidak akan dihentikan otomatis.`,
        ),
      ),
    );
    server.listen(port, "127.0.0.1", () => server.close(resolve));
  });
function start(command, args, cwd = root) {
  const child = spawn(command, args, {
    cwd,
    stdio: "inherit",
    windowsHide: true,
  });
  children.push(child);
  child.on("error", (e) => {
    console.error(e.message);
    stop(1);
  });
  child.on("exit", (code) => {
    if (!closing) stop(code || 0);
  });
}
start(php, ["artisan", "serve", "--host=127.0.0.1", "--port=8170"], path.join(root, "backend"));
start(php, ["artisan", "schedule:work"], path.join(root, "backend"));
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
  "Anteraja Pharma: http://localhost:3170 — Ctrl+C menghentikan server milik launcher ini.",
);
