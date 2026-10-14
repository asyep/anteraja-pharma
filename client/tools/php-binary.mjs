import { existsSync } from "node:fs";
import { spawnSync } from "node:child_process";
import path from "node:path";

export function resolvePhpBinary() {
  const configured = process.env.PHP_BINARY;
  if (configured && existsSync(configured)) return configured;

  if (configured) {
    console.warn(`PHP_BINARY tidak ditemukan (${configured}); mencoba PHP yang tersedia di PATH.`);
  }

  const lookup = spawnSync(process.platform === "win32" ? "where.exe" : "which", ["php"], {
    encoding: "utf8",
    windowsHide: true,
  });
  const fromPath = lookup.stdout
    ?.split(/\r?\n/)
    .map((candidate) => candidate.trim())
    .find((candidate) => candidate && existsSync(candidate));

  if (fromPath) return path.resolve(fromPath);

  throw new Error("PHP CLI tidak ditemukan. Tambahkan php.exe ke PATH atau set PHP_BINARY ke path yang benar.");
}
