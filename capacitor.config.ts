import type { CapacitorConfig } from "@capacitor/cli";
const config: CapacitorConfig = {
  appId: "id.anteraja.pharma.prototype",
  appName: "Anteraja Pharma",
  webDir: "dist",
  ...(process.env.PHARMA_MOBILE_URL
    ? {
        server: {
          url: process.env.PHARMA_MOBILE_URL,
          cleartext: process.env.PHARMA_MOBILE_URL.startsWith("http://"),
        },
      }
    : {}),
};
export default config;
