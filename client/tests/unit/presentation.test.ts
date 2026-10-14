import { describe, it, expect } from "vitest";
import { labels, tone, dateTime, nextStep } from "../../src/lib/presentation";
import type { Shipment } from "../../src/types";
describe("UI states", () => {
  it("every workflow stage has human-readable text", () => {
    for (const status of Object.keys(labels))
      expect(nextStep({ status } as Shipment).length).toBeGreaterThan(10);
  });
  it("issue states are distinct from normal and waiting", () => {
    expect(tone("held")).toBe("danger");
    expect(tone("pending_approval")).toBe("warning");
    expect(tone("delivered")).toBe("success");
  });
  it("invalid and missing dates never show NaN", () => {
    expect(dateTime("bad-date")).toBe("Waktu belum tersedia");
    expect(dateTime(null)).toBe("Belum tercatat");
  });
  it("formatting uses Indonesian time explicitly", () => {
    expect(dateTime("2026-10-13T00:00:00Z")).toContain("07.00");
    expect(dateTime("2026-10-13T00:00:00Z")).toContain("WIB");
  });
});
