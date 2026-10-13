import { chromium } from "playwright";
import assert from "node:assert/strict";
const browser = await chromium.launch({ channel: "chrome" });
const baseURL = process.env.PHARMA_QA_URL || "http://localhost:3170";
const errors = [];
for (const width of [375, 768, 1440, 1920]) {
  const context = await browser.newContext({
    viewport: { width, height: 900 },
    reducedMotion: "reduce",
  });
  const page = await context.newPage();
  page.on("pageerror", (e) => errors.push(e.message));
  await page.goto(baseURL + "/masuk");
  await page.locator(".auth-image.visible").waitFor();
  await page.evaluate(() =>
    Promise.all([...document.images].map((i) => i.decode().catch(() => {}))),
  );
  const buttons = await page.locator(".demo-role-grid button").evaluateAll((elements) =>
    elements.map((e) => {
      const r = e.getBoundingClientRect();
      return { top: r.top, width: r.width, height: r.height };
    }),
  );
  assert.equal(buttons.length, 3);
  assert.ok(buttons.every((b) => b.top === buttons[0].top && b.height >= 48 && b.width > 80));
  assert.equal(await page.locator(".brand").count(), 1);
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
  for (const [label, email] of [
    ["Admin", "admin@pharma.test"],
    ["Satria", "satria@pharma.test"],
    ["Pembeli", "pembeli@pharma.test"],
  ]) {
    await page.getByRole("button", { name: label, exact: true }).click();
    assert.equal(await page.locator("#email").inputValue(), email);
  }
  await page.getByRole("button", { name: "Tampilkan cerita 1" }).click();
  for (const index of [1, 2, 3]) {
    await page.getByRole("button", { name: `Tampilkan cerita ${index}` }).click();
    const photo = await page.locator(".auth-image.visible").evaluate((image) => {
      const bounds = image.getBoundingClientRect();
      return {
        displayed: bounds.width / bounds.height,
        original: image.naturalWidth / image.naturalHeight,
        fit: getComputedStyle(image).objectFit,
      };
    });
    assert.equal(photo.fit, "contain");
    assert.ok(
      Math.abs(photo.displayed - photo.original) < 0.01,
      `Uncropped slide ${index}, ${width}px`,
    );
    await page.screenshot({
      path: `output/screenshots/pharma-photo-${width}-${index}.png`,
      fullPage: true,
    });
  }
  await page.getByRole("button", { name: "Tampilkan cerita 1" }).click();
  await page.screenshot({ path: `output/screenshots/pharma-login-${width}.png`, fullPage: true });
  if (width >= 1440)
    assert.ok(
      await page
        .locator("button[type=submit]")
        .evaluate((e) => e.getBoundingClientRect().bottom < innerHeight),
    );
  if (width === 375)
    assert.ok(
      await page.locator(".pharma-signin").evaluate((e) => e.getBoundingClientRect().top < 140),
    );
  await context.close();
}
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
await page.goto(baseURL + "/masuk");
await page.locator(".pharma-story").hover();
let current = await page.locator(".auth-image.visible").getAttribute("src");
await page.waitForTimeout(4300);
assert.equal(await page.locator(".auth-image.visible").getAttribute("src"), current);
await page.getByRole("button", { name: "Tampilkan cerita 2" }).focus();
await page.locator("#email").hover();
await page.waitForTimeout(4300);
assert.equal(await page.locator(".auth-image.visible").getAttribute("src"), current);
for (const i of [1, 2, 3]) {
  await page.getByRole("button", { name: `Tampilkan cerita ${i}` }).click();
  await page.waitForTimeout(700);
  await page.screenshot({ path: `output/screenshots/pharma-story-${i}.png`, fullPage: true });
}
await page.locator("#email").fill("salah@pharma.test");
await page.locator("#password").fill("incorrect-password");
await page.locator("button[type=submit]").click();
await page.getByRole("alert").waitFor();
assert.equal(await page.locator("#email").getAttribute("aria-describedby"), "login-error");
await page.screenshot({ path: "output/screenshots/pharma-login-error.png", fullPage: true });
await context.close();
await browser.close();
assert.deepEqual(errors, []);
console.log(
  "PASS: 4 login breakpoints, 12 uncropped photo checks, aligned 3-role controls, one logo, mobile form first, carousel hover/focus pause, 3 slides, invalid login and console.",
);
