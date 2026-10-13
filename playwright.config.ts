import { defineConfig } from "@playwright/test";
export default defineConfig({
  testDir: "./tests/e2e",
  timeout: process.env.PHARMA_QA_URL ? 240000 : 90000,
  workers: 1,
  use: {
    baseURL: process.env.PHARMA_QA_URL || "http://localhost:3170",
    channel: "chrome",
    headless: true,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  reporter: [
    ["list"],
    [
      "json",
      {
        outputFile: process.env.PHARMA_QA_URL
          ? "output/cloud-e2e-results.json"
          : "output/e2e-results.json",
      },
    ],
  ],
});
