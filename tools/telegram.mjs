import fs from "node:fs";
import path from "node:path";
const root = path.resolve(import.meta.dirname, "..");
const text = fs.readFileSync(path.join(root, "backend/.env"), "utf8");
const env = Object.fromEntries(
  text
    .split(/\r?\n/)
    .filter((s) => s.includes("=") && !s.startsWith("#"))
    .map((s) => {
      const p = s.indexOf("=");
      return [s.slice(0, p), s.slice(p + 1).replace(/^"|"$/g, "")];
    }),
);
const token = env.TELEGRAM_BOT_TOKEN;
if (!token) {
  console.error(
    "Isi TELEGRAM_BOT_TOKEN dalam backend/.env terlebih dahulu. Jangan kirim token ke chat.",
  );
  process.exit(1);
}
const mode = process.argv[2] || "info";
let method = "getMe",
  payload = {};
if (mode === "chats") {
  method = "getUpdates";
  payload = { timeout: 0, limit: 100 };
} else if (mode === "webhook") {
  if (!env.FRONTEND_URL.startsWith("https://") || !env.TELEGRAM_WEBHOOK_SECRET)
    throw new Error("Webhook membutuhkan FRONTEND_URL HTTPS publik dan TELEGRAM_WEBHOOK_SECRET.");
  method = "setWebhook";
  payload = {
    url: env.FRONTEND_URL.replace(/\/$/, "") + "/api/webhooks/telegram",
    secret_token: env.TELEGRAM_WEBHOOK_SECRET,
    allowed_updates: ["message"],
  };
} else if (mode !== "info") throw new Error("Gunakan info, chats, atau webhook.");
try {
  const response = await fetch("https://api.telegram.org/bot" + token + "/" + method, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
    signal: AbortSignal.timeout(12000),
  });
  const result = await response.json();
  if (!response.ok || !result.ok)
    throw new Error(
      `Telegram belum menerima permintaan (HTTP ${response.status}). Periksa token, status webhook, dan koneksi.`,
    );
  if (mode === "chats") {
    const chats = [
      ...new Map(
        result.result
          .filter((u) => u.message?.chat)
          .map((u) => [
            u.message.chat.id,
            {
              chat_id: u.message.chat.id,
              name: u.message.chat.first_name || u.message.chat.title,
            },
          ]),
      ).values(),
    ];
    fs.mkdirSync(path.join(root, ".private"), { recursive: true });
    fs.writeFileSync(
      path.join(root, ".private/telegram-chats.json"),
      JSON.stringify(chats, null, 2),
    );
    console.log(
      `${chats.length} chat tersimpan privat di .private/telegram-chats.json. Akun harus mengirim /start terlebih dahulu.`,
    );
  } else if (mode === "info") console.log("Bot terhubung: @" + result.result.username);
  else console.log("Webhook Telegram berhasil didaftarkan.");
} catch (e) {
  console.error(
    e.message.includes(token) ? "Koneksi Telegram belum berhasil. Periksa konfigurasi." : e.message,
  );
  process.exitCode = 1;
}
