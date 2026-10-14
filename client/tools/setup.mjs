import fs from "node:fs";
import path from "node:path";
import crypto from "node:crypto";
import { spawnSync } from "node:child_process";
import { resolvePhpBinary } from "./php-binary.mjs";

const root = path.resolve(import.meta.dirname, "..");
const php = resolvePhpBinary();
const backend = path.resolve(root, "..", "server");
if (process.platform === "win32" && fs.existsSync(path.join(path.dirname(php), "ext/php_gd.dll")))
  process.env.PHP_INI_SCAN_DIR = path.join(backend, "runtime/windows");
if (!fs.existsSync(path.join(backend, "artisan"))) {
  throw new Error(`Laravel backend tidak ditemukan: ${backend}`);
}
for (const directory of [
  "bootstrap/cache",
  "storage/framework/sessions",
  "storage/framework/views",
  "storage/framework/cache/data",
  "storage/logs",
  "storage/app/private",
]) {
  fs.mkdirSync(path.join(backend, directory), { recursive: true });
}
fs.mkdirSync(path.join(root, ".private"), { recursive: true });
const envFile = path.join(backend, ".env");
if (!fs.existsSync(envFile)) {
  const password = crypto.randomBytes(10).toString("base64url") + "!a7";
  fs.writeFileSync(
    envFile,
    `APP_NAME="Anteraja Pharma"\nAPP_ENV=local\nPHARMA_DEMO_ENABLED=true\nAPP_KEY=base64:${crypto.randomBytes(32).toString("base64")}\nAPP_DEBUG=false\nAPP_URL=http://localhost:8170\nFRONTEND_URL=http://localhost:3170\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=anteraja_pharma_hackathon\nDB_USERNAME=root\nDB_PASSWORD=\nSESSION_DRIVER=file\nSESSION_COOKIE=pharma_session\nSESSION_SAME_SITE=lax\nSESSION_LIFETIME=120\nCACHE_STORE=file\nQUEUE_CONNECTION=database\nLOG_CHANNEL=stack\nDEMO_PASSWORD=${password}\nAUTOMATION_WEBHOOK_SECRET=${crypto.randomBytes(32).toString("hex")}\nTELEGRAM_WEBHOOK_SECRET=${crypto.randomBytes(24).toString("hex")}\nTELEGRAM_BOT_TOKEN=\nTELEGRAM_ADMIN_CHAT_ID=\nTELEGRAM_COURIER_CHAT_ID=\nTELEGRAM_BUYER_CHAT_ID=\nTELEGRAM_SENDER_CHAT_ID=\n`,
  );
  fs.writeFileSync(
    path.join(root, ".private", "akun-demo.txt"),
    `akun contoh, hanya untuk lokal\nadmin: admin@pharma.test\nsatria: satria@pharma.test\npembeli: pembeli@pharma.test\npassword ketiganya: ${password}\njangan bagikan file ini atau masukkan ke git.\n`,
  );
}
let envText = fs.readFileSync(envFile, "utf8");
let envChanged = false;
function ensureEnvValue(key, generatedValue) {
  const currentValue = envText
    .match(new RegExp(`^${key}=(.*)$`, "m"))?.[1]
    ?.trim()
    .replace(/^"(.*)"$/, "$1");
  if (currentValue) return currentValue;

  const line = `${key}=${generatedValue}`;
  if (new RegExp(`^${key}=`, "m").test(envText)) {
    envText = envText.replace(new RegExp(`^${key}=.*$`, "m"), line);
  } else {
    envText += `${envText.endsWith("\n") ? "" : "\n"}${line}\n`;
  }
  envChanged = true;
  return generatedValue;
}
ensureEnvValue("APP_KEY", `base64:${crypto.randomBytes(32).toString("base64")}`);
const demoPassword = ensureEnvValue(
  "DEMO_PASSWORD",
  crypto.randomBytes(10).toString("base64url") + "!a7",
);
if (envChanged) fs.writeFileSync(envFile, envText);
fs.writeFileSync(
  path.join(root, ".private", "akun-demo.txt"),
  `akun contoh, hanya untuk lokal\nadmin: admin@pharma.test\nchecker: checker@pharma.test\nkurir: satria@pharma.test\nvendor: vendor@pharma.test\ncustomer: customer@pharma.test\nkurir lain: satria.lain@pharma.test\npassword semua akun: ${demoPassword}\njangan bagikan file ini atau masukkan ke git.\n`,
  { mode: 0o600 },
);
for (const args of [
  [path.join(root, "tools", "init-db.php")],
  ["artisan", "migrate", "--force"],
  ["artisan", "db:seed", "--force"],
]) {
  const result = spawnSync(php, args, { cwd: backend, stdio: "inherit" });
  if (result.status !== 0) process.exit(result.status || 1);
}
console.log(
  "Database Pharma siap. Akun lokal: .private/akun-demo.txt. Tidak ada database lama yang diubah.",
);
