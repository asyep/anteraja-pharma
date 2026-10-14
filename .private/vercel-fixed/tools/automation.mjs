import fs from "node:fs";
import crypto from "node:crypto";
const text = fs.readFileSync(new URL("../../server/.env", import.meta.url), "utf8");
const secret = text.match(/^AUTOMATION_WEBHOOK_SECRET=(.+)$/m)?.[1]?.trim();
if (!secret) throw new Error("Pasang AUTOMATION_WEBHOOK_SECRET di server/.env.");
const timestamp = String(Math.floor(Date.now() / 1000));
const date = new Intl.DateTimeFormat("en-CA", {
  timeZone: "Asia/Jakarta",
  year: "numeric",
  month: "2-digit",
  day: "2-digit",
}).format(new Date());
const body = JSON.stringify({
  event_id: "manual-report-" + Date.now(),
  type: "daily_report",
  date,
});
const response = await fetch("http://localhost:8170/api/webhooks/automation", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
    "X-Pharma-Timestamp": timestamp,
    "X-Pharma-Signature": crypto
      .createHmac("sha256", secret)
      .update(timestamp + "." + body)
      .digest("hex"),
  },
  body,
  signal: AbortSignal.timeout(12000),
});
if (!response.ok) throw new Error(`Webhook belum berhasil (HTTP ${response.status}).`);
console.log("Webhook terverifikasi:", await response.json());
