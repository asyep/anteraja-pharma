import { test, expect, type Page, type BrowserContext } from "@playwright/test";
import fs from "node:fs";
import type { Shipment, User, Paginated } from "../../frontend/types";
const env = fs.readFileSync("backend/.env", "utf8");
const password = env.match(/^DEMO_PASSWORD=(.+)$/m)![1]!.trim();
const emails = {
  admin: "admin@pharma.test",
  courier: "satria@pharma.test",
  buyer: "pembeli@pharma.test",
};
fs.mkdirSync("output/screenshots", { recursive: true });

async function login(page: Page, role: keyof typeof emails) {
  await page.goto("/masuk");
  await page.getByLabel("Email", { exact: true }).fill(emails[role]);
  await page.getByLabel("Kata sandi", { exact: true }).fill(password);
  await page.getByRole("button", { name: "Masuk ke Pharma" }).click();
  await expect(page).toHaveURL("http://localhost:3170/");
  await expect(page.locator(".metrics")).toBeVisible();
}
async function request<T>(context: BrowserContext, path: string, body?: unknown): Promise<T> {
  const session = await context.request.get("/api/session");
  const token = (await session.json()).csrf_token;
  const response = body
    ? await context.request.post("/api/" + path, {
        data: body,
        headers: { "X-CSRF-TOKEN": token, Accept: "application/json" },
      })
    : await context.request.get("/api/" + path);
  expect(response.ok(), `API ${path}: ${response.status()}`).toBeTruthy();
  return response.json();
}

test("required Pharma journey through real UI: block, inspect, approve, assign, seal issue, return", async ({
  browser,
}) => {
  const contexts = await Promise.all([
    browser.newContext(),
    browser.newContext(),
    browser.newContext(),
  ]);
  const [admin, courier, buyer] = await Promise.all(contexts.map((c) => c.newPage()));
  await login(admin!, "admin");
  await login(courier!, "courier");
  await login(buyer!, "buyer");
  const users = await request<User[]>(contexts[0]!, "directory");
  await admin!.goto("/kiriman/baru");
  await admin!.getByLabel("Nama pengirim", { exact: true }).fill("Distribusi sehat — QA");
  await admin!.getByLabel("Nama penerima / fasilitas").fill("Klinik Pharma QA " + Date.now());
  await admin!.getByLabel("Nomor telepon penerima").fill("081200000000");
  await admin!.getByLabel("Kota tujuan").fill("Jakarta Selatan");
  await admin!.getByLabel("Ringkasan isi paket").fill("Perlengkapan kesehatan — contoh uji");
  await admin!
    .getByLabel("Akun pembeli yang bisa melihat paket")
    .selectOption(String(users.find((u) => u.role === "buyer" && u.name === "Nadia Putri")!.id));
  await admin!
    .getByLabel("Satria yang memeriksa paket")
    .selectOption(
      String(users.find((u) => u.role === "courier" && u.name === "Dimas Saputra")!.id),
    );
  await admin!.getByLabel("Alamat lengkap tujuan").fill("Jl. Cilandak KKO No. 8, Jakarta Selatan");
  await admin!.getByLabel("Jadwal tiba").fill("2026-10-16T18:00");
  await admin!
    .getByLabel("Instruksi untuk pemeriksaan dan pengantaran")
    .fill("Paket tetap tegak. Segel diperiksa bersama penerima. Jika rusak, hentikan pengiriman.");
  await admin!.getByRole("button", { name: "Simpan kiriman" }).click();
  await expect(admin!).toHaveURL(/\/kiriman\/\d+$/);
  const detailUrl = admin!.url();
  await admin!.getByRole("button", { name: "Setujui pengiriman", exact: true }).first().click();
  await admin!
    .getByLabel("Catatan dan alasan")
    .fill("Menguji pemblokiran saat checklist belum lengkap.");
  await admin!.getByRole("button", { name: "Setujui pengiriman", exact: true }).last().click();
  await expect(admin!.getByRole("alert")).toContainText("Paket belum boleh berangkat");
  await admin!.screenshot({
    path: "output/screenshots/required-01-blocked.png",
    fullPage: true,
  });
  await courier!.goto(detailUrl);
  await courier!.getByLabel("Kemasan utuh dan sesuai instruksi").check();
  await courier!.getByLabel("Segel utuh, belum terbuka").check();
  await courier!.getByLabel("Label sesuai data penerima").check();
  await courier!
    .getByLabel("Catatan dan alasan")
    .fill("Kemasan, segel, dan label sesuai hasil pemeriksaan.");
  await courier!.getByRole("button", { name: "Periksa paket", exact: true }).last().click();
  await expect(courier!.locator(".shipment-hero")).toContainText("Menunggu persetujuan");
  await admin!.reload();
  await admin!.getByRole("button", { name: "Setujui pengiriman", exact: true }).first().click();
  await admin!
    .getByLabel("Catatan dan alasan")
    .fill("Hasil pemeriksaan lengkap. Paket boleh ditugaskan.");
  await admin!.getByRole("button", { name: "Setujui pengiriman", exact: true }).last().click();
  await expect(admin!.locator(".shipment-hero")).toContainText("Siap ditugaskan");
  await admin!.getByRole("button", { name: "Tugaskan Satria", exact: true }).first().click();
  await admin!
    .getByLabel("Pilih Satria")
    .selectOption(String(users.find((u) => u.name === "Dimas Saputra")!.id));
  await admin!
    .getByLabel("Catatan dan alasan")
    .fill("Antar sesuai instruksi; laporkan masalah segel.");
  await admin!.getByRole("button", { name: "Tugaskan Satria", exact: true }).last().click();
  await expect(admin!.locator(".shipment-hero")).toContainText("Menunggu jawaban Satria");
  await courier!.reload();
  await courier!
    .getByLabel("Catatan dan alasan")
    .fill("Saya siap mengambil dan mengantar sesuai instruksi.");
  await courier!.getByRole("button", { name: "Terima tugas", exact: true }).last().click();
  await expect(courier!.locator(".shipment-hero")).toContainText("Siap diambil");
  await courier!.getByLabel("Lokasi saat ini").fill("Hub Cakung");
  await courier!.getByLabel("Catatan dan alasan").fill("Berangkat ke Klinik Cilandak.");
  await courier!.getByRole("button", { name: "Mulai perjalanan", exact: true }).last().click();
  await expect(courier!.locator(".shipment-hero")).toContainText("Dalam perjalanan");
  await buyer!.goto(detailUrl);
  await expect(buyer!.locator(".shipment-hero")).toContainText("Dalam perjalanan");
  await courier!.getByRole("button", { name: "Laporkan kendala", exact: true }).first().click();
  await courier!.getByLabel("Apa kendalanya?").selectOption("seal");
  await courier!
    .getByLabel("Ceritakan kondisi paket")
    .fill(
      "Segel terlihat terbuka saat paket diperiksa sebelum serah terima. Paket tidak diserahkan.",
    );
  await courier!.getByRole("button", { name: "Laporkan kendala", exact: true }).last().click();
  await expect(courier!.locator(".shipment-hero")).toContainText("Ditahan sementara");
  await courier!.screenshot({
    path: "output/screenshots/required-02-held.png",
    fullPage: true,
  });
  await admin!.reload();
  await admin!.getByLabel("Tindakan untuk Satria").selectOption("return");
  await admin!
    .getByLabel("Catatan dan alasan")
    .fill("Jangan serahkan ke penerima. Kembalikan ke Hub Cakung untuk pemeriksaan QA.");
  await admin!.getByRole("button", { name: "Putuskan tindak lanjut", exact: true }).last().click();
  await expect(admin!.locator(".shipment-hero")).toContainText("Dalam proses retur");
  await courier!.reload();
  await courier!.getByLabel("Lokasi saat ini").fill("Hub Cakung — meja QA");
  await courier!
    .getByLabel("Catatan dan alasan")
    .fill("Paket diterima kembali petugas QA sesuai instruksi Admin.");
  await courier!.getByRole("button", { name: "Catat paket kembali", exact: true }).last().click();
  await expect(courier!.locator(".shipment-hero")).toContainText("Sudah dikembalikan");
  await buyer!.reload();
  await expect(buyer!.locator(".shipment-hero")).toContainText("Sudah dikembalikan");
  await admin!.reload();
  await expect(admin!.locator(".shipment-hero")).toContainText("Sudah dikembalikan");
  await admin!.screenshot({
    path: "output/screenshots/required-03-returned.png",
    fullPage: true,
  });
  const id = detailUrl.split("/").at(-1);
  const final = await request<Shipment>(contexts[0]!, "shipments/" + id);
  expect(final.status).toBe("returned");
  expect(final.issues?.[0]?.decisions[0]?.action).toBe("return");
  const msgs = await request<Paginated<{ subject: string }>>(contexts[0]!, "messages");
  expect(msgs.total).toBeGreaterThan(0);
  await admin!.goto("/laporan");
  await admin!.getByRole("button", { name: "Buat laporan" }).click();
  await expect(admin!.getByRole("button", { name: "Buat laporan" })).toBeEnabled();
  await expect(admin!.locator(".report-card").first()).toBeVisible();
  const downloadPromise = admin!.waitForEvent("download");
  await admin!.getByRole("link", { name: "Unduh riwayat CSV" }).first().click();
  const download = await downloadPromise;
  await download.saveAs("output/e2e-report.csv");
  expect(fs.readFileSync("output/e2e-report.csv", "utf8")).toContain("Segel terlihat terbuka");
  expect(fs.readFileSync("output/e2e-report.csv", "utf8")).toContain(final.tracking_number);
  for (const c of contexts) await c.close();
});

test("visual QA all roles at mobile, tablet and desktop; navigation, empty state and no new console errors", async ({
  browser,
}) => {
  // This combined audit captures 33 pages across three roles, not one interaction.
  test.setTimeout(240000);
  for (const role of ["admin", "courier", "buyer"] as const) {
    const context = await browser.newContext({ baseURL: "http://localhost:3170" }),
      page = await context.newPage();
    const errors: string[] = [];
    page.on("pageerror", (e) => errors.push(e.message));
    page.on("console", (m) => {
      if (m.type() === "error" && !m.text().includes("Failed to load resource"))
        errors.push(m.text());
    });
    await login(page, role);
    for (const width of [375, 768, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto("/");
      await expect(page.locator(".metrics")).toBeVisible();
      await page.screenshot({
        path: `output/screenshots/${role}-${width}-dashboard.png`,
        fullPage: true,
      });
      for (const path of [
        "/kiriman",
        "/pesan",
        ...(role === "admin" ? ["/kiriman/baru", "/laporan"] : []),
      ]) {
        await page.goto(path);
        await expect(page.locator("main h1")).toBeVisible();
        await expect(page.locator(".loading-state")).toHaveCount(0);
        const overflow = await page.evaluate(
          () => document.documentElement.scrollWidth > innerWidth + 1,
        );
        expect(overflow, `${role} ${path} ${width} overflow`).toBeFalsy();
        await page.screenshot({
          path: `output/screenshots/${role}-${width}-${path.replaceAll("/", "_")}.png`,
          fullPage: true,
        });
      }
      await page.goto("/kiriman");
      await page.getByLabel("Cari kiriman").fill("NO-RESULT-XYZ");
      await page.getByRole("button", { name: "Cari", exact: true }).click();
      await expect(page.locator(".empty-state")).toBeVisible();
    }
    expect(errors).toEqual([]);
    await context.close();
  }
});

test("login carousel, manual controls, reduced motion, keyboard and request failure recovery", async ({
  browser,
}) => {
  const context = await browser.newContext({
      viewport: { width: 1440, height: 900 },
    }),
    page = await context.newPage();
  await page.goto("/masuk");
  await expect(page.locator(".auth-image.visible")).toHaveAttribute("src", "/images/sorting.webp");
  await page.waitForTimeout(4300);
  await expect(page.locator(".auth-image.visible")).toHaveAttribute("src", "/images/courier.webp");
  await page.getByRole("button", { name: "Hentikan cerita otomatis" }).click();
  const src = await page.locator(".auth-image.visible").getAttribute("src");
  await page.waitForTimeout(4300);
  expect(await page.locator(".auth-image.visible").getAttribute("src")).toBe(src);
  await page.getByRole("button", { name: "Tampilkan cerita 3" }).click();
  await expect(page.locator(".auth-image.visible")).toHaveAttribute("src", "/images/handoff.webp");
  await page.screenshot({
    path: "output/screenshots/login-desktop.png",
    fullPage: true,
  });
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.getByRole("button", { name: "Putar cerita otomatis" }).click();
  await page.getByLabel("Email", { exact: true }).focus();
  await page.waitForTimeout(4300);
  await expect(page.locator(".auth-image.visible")).toHaveAttribute("src", "/images/handoff.webp");
  await page.getByLabel("Email", { exact: true }).fill(emails.admin);
  await page.getByLabel("Kata sandi", { exact: true }).fill(password);
  await page.getByLabel("Kata sandi", { exact: true }).press("Enter");
  await expect(page).toHaveURL("http://localhost:3170/");
  await page.evaluate(() => {
    (window as unknown as Record<string, unknown>).spaSentinel = "alive";
  });
  await page.getByRole("link", { name: "Pengiriman", exact: true }).click();
  expect(
    await page.evaluate(() => (window as unknown as Record<string, unknown>).spaSentinel),
  ).toBe("alive");
  await page.route("**/api/dashboard", (r) => r.abort());
  await page.goto("/");
  await expect(page.getByRole("alert")).toContainText("Koneksi belum berhasil");
  await page.screenshot({
    path: "output/screenshots/network-error.png",
    fullPage: true,
  });
  await page.unroute("**/api/dashboard");
  await page.getByRole("button", { name: "Coba lagi" }).click();
  await expect(page.locator(".metrics")).toBeVisible();
  const noCsrf = await context.request.post("/api/settings", {
    data: { telegram_chat_id: "123" },
  });
  expect(noCsrf.status()).toBe(419);
  await context.close();
});
