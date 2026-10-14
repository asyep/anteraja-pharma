import { test, expect, type Page, type BrowserContext } from "@playwright/test";
import fs from "node:fs";
import { readDemoAccounts } from '../../tools/demo-accounts.mjs';
const { passwords } = readDemoAccounts();
const emails = {
  admin: "admin@pharma.test",
  checker: "checker@pharma.test",
  courier: "satria@pharma.test",
  buyer: "vendor@pharma.test",
};
const photo = {
  name: "proof.png",
  mimeType: "image/png",
  buffer: Buffer.from(
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=",
    "base64",
  ),
};
async function login(page: Page, role: keyof typeof emails) {
  await page.goto("/masuk");
  await page.getByLabel("Email", { exact: true }).fill(emails[role]);
  await page.getByLabel("Kata sandi", { exact: true }).fill(passwords[role]);
  for (let attempt = 0; attempt < 2; attempt++) {
    const response = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/api/login'));
    await page.getByRole('button', { name: 'Masuk ke Pharma' }).click();
    const result = await response;
    if (result.status() !== 429) { expect(result.ok()).toBeTruthy(); break; }
    // respect the real login throttle; do not disable security for tests.
    const seconds = Math.max(1, Math.min(60, Number(result.headers()['retry-after']) || 60));
    await new Promise(resolve => setTimeout(resolve, (seconds + 1) * 1000));
  }
  await expect(page).toHaveURL(/\/$/);
}
async function request(context: BrowserContext, path: string, data?: unknown) {
  const session = await context.request.get("/api/session");
  const token = (await session.json()).csrf_token;
  const response = data
    ? await context.request.post("/api/" + path, {
        data,
        headers: { "X-CSRF-TOKEN": token, Accept: "application/json" },
      })
    : await context.request.get("/api/" + path);
  expect(response.ok(), path + ": " + response.status()).toBeTruthy();
  return response.json();
}
async function act(page: Page, title: string, fill?: () => Promise<void>) {
  await page.locator(".action-options").getByRole("button", { name: title, exact: true }).click();
  if (fill) await fill();
  if (await page.locator("#action-reason").count())
    await page.locator("#action-reason").fill("Hasil pemeriksaan dan instruksi QA yang tercatat.");
  const response = page.waitForResponse(
    (r) =>
      r.request().method() === "POST" && /\/api\/shipments\/\d+\/(actions|telemetry)/.test(r.url()),
  );
  await page.locator(".action-panel button[type=submit]").click();
  const result = await response;
  if (!result.ok() && title !== "Setujui pengiriman")
    throw new Error(title + ": " + result.status() + " " + (await result.text()));
}
test("four-role flow: checklist gate, physical proof, GPS, simulated hold, return, private PDF", async ({
  browser,
}) => {
  test.setTimeout(150000);
  fs.mkdirSync("output/screenshots", { recursive: true });
  const contexts = await Promise.all(
    Object.keys(emails).map(() =>
      browser.newContext({
        geolocation: { latitude: -6.2, longitude: 106.84 },
        permissions: ["geolocation"],
      }),
    ),
  );
  const [admin, checker, courier, buyer] = await Promise.all(contexts.map((c) => c.newPage()));
  const errors: string[] = [];
  [admin!, checker!, courier!, buyer!].forEach((p) =>
    p.on("pageerror", (e) => errors.push(e.message)),
  );
  try {
    await login(admin!, "admin");
    await login(checker!, "checker");
    await login(courier!, "courier");
    await login(buyer!, "buyer");
    const users = (await request(contexts[0]!, "directory")) as {
      id: number;
      role: string;
      name: string;
    }[];
    await admin!.goto("/kiriman/baru");
    await admin!.getByLabel("Nama pengirim", { exact: true }).fill("Distribusi QA");
    await admin!.getByLabel("Nama penerima / fasilitas").fill("Klinik QA");
    await admin!.getByLabel("Nomor telepon penerima").fill("081200000000");
    await admin!.getByLabel("Kota tujuan", { exact: true }).fill("Jakarta Selatan");
    await admin!
      .getByLabel("Ringkasan isi paket")
      .fill("Cold-chain contoh, bukan produk medis nyata");
    await admin!
      .locator("#buyer-id")
      .selectOption(String(users.find((u) => u.name === "Nadia Putri")!.id));
    await admin!
      .locator("#inspector-id")
      .selectOption(String(users.find((u) => u.role === "checker")!.id));
    await admin!.getByLabel("Alamat lengkap tujuan").fill("Jl. Cilandak KKO No. 8");
    await admin!.getByLabel("Kategori penanganan").selectOption("COLD_CHAIN_2_8");
    await admin!
      .getByLabel("Jadwal tiba")
      .fill(new Date(Date.now() + 86400000 * 2).toISOString().slice(0, 16));
    await admin!
      .getByLabel("Instruksi untuk pemeriksaan dan pengantaran")
      .fill("Simpan 2–8°C. Segel utuh. Hentikan jika terjadi penyimpangan.");
    await admin!.getByRole("checkbox", { name: "Data demonstrasi" }).check();
    await admin!.getByRole("button", { name: "Simpan kiriman" }).click();
    await expect(admin!).toHaveURL(/\/kiriman\/\d+$/);
    const detail = new URL(admin!.url()).pathname,
      id = detail.split("/").at(-1);
    const shipment = await request(contexts[0]!, "shipments/" + id);
    await act(admin!, "Setujui pengiriman");
    await expect(admin!.getByRole("alert")).toContainText("Paket belum boleh berangkat");
    await checker!.goto(detail);
    await checker!.locator("#scanned-tracking").fill(shipment.tracking_number);
    await checker!.getByLabel("Kemasan utuh dan sesuai instruksi").check();
    await checker!.getByLabel("Segel utuh, belum terbuka").check();
    await checker!.getByLabel("Label sesuai data penerima").check();
    await checker!.locator("#temperature").fill("5");
    await checker!.getByRole("button", { name: "Lanjut ke foto bukti" }).click();
    await checker!.locator("#inspection-photo").setInputFiles(photo);
    await checker!
      .locator("#action-reason")
      .fill("Kemasan, segel, label, jumlah dan suhu sudah diperiksa.");
    await checker!.locator(".action-panel button[type=submit]").click();
    await expect(checker!.locator(".shipment-hero")).toContainText("Menunggu persetujuan");
    await admin!.reload();
    await act(admin!, "Setujui pengiriman");
    await expect(admin!.locator(".shipment-hero")).toContainText("Siap ditugaskan");
    await act(admin!, "Pilih kurir", async () => {
      await admin!
        .locator("#courier")
        .selectOption(String(users.find((u) => u.name === "Satria Aditya")!.id));
    });
    await courier!.goto(detail);
    await act(courier!, "Terima tugas");
    await expect(courier!.locator(".shipment-hero")).toContainText("Kurir terpilih");
    await act(courier!, "Mulai perjalanan", async () => {
      await courier!.locator("#location").fill("Hub Jakarta");
    });
    await act(courier!, "Kirim lokasi GPS", async () => {
      await courier!.getByRole("button", { name: "Ambil lokasi GPS", exact: true }).click();
      await expect(courier!.locator(".action-panel")).toContainText("-6.200000");
      await courier!.locator("#telemetry-temperature").fill("5");
    });
    await buyer!.goto(detail);
    await expect(buyer!.locator(".leaflet-container")).toBeVisible();
    await expect(buyer!.locator(".action-panel button[type=submit]")).toHaveCount(0);
    await admin!.reload();
    await admin!.getByRole("button", { name: "Mode demo" }).click();
    await admin!.locator("#demo-shipment").selectOption(String(id));
    await admin!.getByRole("button", { name: "Lonjakan 12.5°C" }).click();
    await expect(admin!.locator(".shipment-hero")).toContainText("Ditahan");
    await expect(buyer!.locator(".shipment-hero")).toContainText("Ditahan", { timeout: 12000 });
    await admin!.getByRole("button", { name: "Tutup panel demo" }).click();
    await act(admin!, "Putuskan tindak lanjut", async () => {
      await admin!.locator("#decision").selectOption("return");
    });
    await courier!.reload();
    await act(courier!, "Catat paket kembali", async () => {
      await courier!.locator("#location").fill("Hub cold-storage QA");
      await courier!.locator("#pod-photo").setInputFiles(photo);
      await courier!.locator("#receiver-signature").fill("Petugas QA");
    });
    await expect(courier!.locator(".shipment-hero")).toContainText("Sudah dikembalikan");
    for (const [index, p] of [admin!, courier!, buyer!].entries()) {
      await p.goto(detail);
      await expect(p.getByRole("link", { name: /Unduh PDF/ })).toBeVisible();
      const pdf = await p.context().request.get("/api/shipments/" + id + "/report.pdf");
      expect(pdf.status()).toBe(200);
      expect((await pdf.body()).subarray(0, 4).toString()).toBe("%PDF");
      if (!index) fs.writeFileSync("output/qa-shipment-report.pdf", await pdf.body());
    }
    const denied = await contexts[1]!.request.get("/api/shipments/" + id + "/report.pdf");
    expect(denied.status()).toBe(403);
    await buyer!.screenshot({ path: "output/screenshots/flowchart-report.png", fullPage: true });
    expect(errors).toEqual([]);
  } finally {
    await Promise.all(contexts.map((c) => c.close()));
  }
});
test("each role stays usable without overflow at mobile, tablet and desktop", async ({ page }) => {
  for (const role of Object.keys(emails) as (keyof typeof emails)[]) {
    await login(page, role);
    for (const width of [375, 768, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      for (const path of ["/", "/kiriman", "/pesan"]) {
        await page.goto(path);
        await expect(page.locator("main")).toBeVisible();
        expect(
          await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1),
          role + path + width,
        ).toBe(true);
      }
    }
    await page.context().clearCookies();
  }
  await login(page, "buyer");
  await page.goto("/does-not-exist");
  await expect(page.locator("body")).toContainText(/tidak ditemukan/i);
});
test("demo cannot be used by Customer and reports remain private", async ({ page }) => {
  await login(page, "buyer");
  expect((await page.request.get("/api/demo/shipments")).status()).toBe(403);
  expect((await page.request.get("/api/directory")).status()).toBe(403);
  await page.context().clearCookies();
  expect(
    (
      await page.request.get("/api/shipments", { headers: { Accept: "application/json" } })
    ).status(),
  ).toBe(401);
});

test("inspection proof survives a failed connection and syncs without duplication", async ({
  browser,
}) => {
  const adminContext = await browser.newContext(),
    checkerContext = await browser.newContext();
  try {
    const admin = await adminContext.newPage(),
      checker = await checkerContext.newPage();
    await login(admin, "admin");
    await login(checker, "checker");
    const users = (await request(adminContext, "directory")) as { id: number; role: string }[];
    const s = await request(adminContext, "shipments", {
      buyer_id: users.find((u) => u.role === "buyer")!.id,
      inspector_id: users.find((u) => u.role === "checker")!.id,
      sender_name: "Offline QA",
      recipient_name: "Klinik offline QA",
      recipient_phone: "081200000000",
      address: "Jl. QA",
      origin_city: "Jakarta",
      destination_city: "Bekasi",
      item_summary: "Paket uji offline",
      package_count: 1,
      handling_instructions: "Periksa segel",
      scheduled_at: new Date(Date.now() + 86400000).toISOString(),
      temperature_profile: "ambient",
      is_demo: true,
    });
    await checker.goto("/kiriman/" + s.id);
    await checker.locator("#scanned-tracking").fill(s.tracking_number);
    await checker.getByLabel("Kemasan utuh dan sesuai instruksi").check();
    await checker.getByLabel("Segel utuh, belum terbuka").check();
    await checker.getByLabel("Label sesuai data penerima").check();
    await checker.getByRole("button", { name: "Lanjut ke foto bukti" }).click();
    await checker.locator("#inspection-photo").setInputFiles(photo);
    await checker
      .locator("#action-reason")
      .fill("Foto dan checklist offline yang sudah diperiksa.");
    const pattern = "**/api/shipments/" + s.id + "/actions/inspect";
    await checker.route(pattern, (route) => route.abort("internetdisconnected"));
    await checker.locator(".action-panel button[type=submit]").click();
    await expect(checker.locator(".action-panel")).toContainText("tersimpan di antrean browser");
    await checker.unroute(pattern);
    await checker.evaluate(() => window.dispatchEvent(new Event("online")));
    await expect(checker.locator(".shipment-hero")).toContainText("Menunggu persetujuan", {
      timeout: 15000,
    });
    const detail = await request(adminContext, "shipments/" + s.id);
    expect(detail.inspections).toHaveLength(1);
    expect(detail.inspections[0].photo_proof_url).toBeTruthy();
  } finally {
    await adminContext.close();
    await checkerContext.close();
  }
});
