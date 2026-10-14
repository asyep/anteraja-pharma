import { post, ApiError } from "./api";

export interface Pending {
  id: string;
  owner: number;
  path: string;
  kind: "form" | "json";
  body: unknown;
  created: number;
}
export async function pending(owner: number): Promise<Pending[]> {
  return (await transaction<Pending[]>("readonly", (store) => store.getAll())).filter(
    (r) => r.owner === owner,
  );
}
export async function discard(id: string, owner: number): Promise<void> {
  const record = await transaction<Pending | undefined>("readonly", (store) => store.get(id));
  if (record?.owner === owner) await transaction("readwrite", (store) => store.delete(id));
}
let flushing = false;
function open(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open("pharma-offline-v1", 1);
    request.onupgradeneeded = () => request.result.createObjectStore("pending", { keyPath: "id" });
    request.onsuccess = () => resolve(request.result);
    request.onerror = () =>
      reject(
        new Error(
          "Penyimpanan offline tidak tersedia. Jangan tutup halaman sebelum koneksi pulih.",
        ),
      );
  });
}
async function transaction<T>(
  mode: IDBTransactionMode,
  run: (store: IDBObjectStore) => IDBRequest<T>,
): Promise<T> {
  const db = await open();
  return new Promise((resolve, reject) => {
    const tx = db.transaction("pending", mode),
      request = run(tx.objectStore("pending"));
    let result: T;
    request.onsuccess = () => {
      result = request.result;
    };
    tx.oncomplete = () => {
      db.close();
      resolve(result);
    };
    tx.onerror = tx.onabort = () => {
      db.close();
      reject(new Error("Draft offline belum tersimpan. Periksa ruang browser."));
    };
  });
}
export async function enqueue(owner: number, path: string, body: unknown): Promise<void> {
  if (!/^shipments\/\d+\/(telemetry|actions\/inspect)$/.test(path))
    throw new Error("Tindakan ini tidak boleh diantrekan offline.");
  const records = await transaction<Pending[]>("readonly", (store) => store.getAll());
  if (records.filter((r) => r.owner === owner).length >= 200)
    throw new Error("Antrean offline penuh. Sambungkan internet sebelum merekam lagi.");
  await transaction("readwrite", (store) =>
    store.put({
      id: crypto.randomUUID(),
      owner,
      path,
      kind: body instanceof FormData ? "form" : "json",
      body: body instanceof FormData ? [...body.entries()] : body,
      created: Date.now(),
    } satisfies Pending),
  );
}
export async function flush(
  owner: number,
): Promise<{ sent: number; remaining: number; warning: string }> {
  if (flushing || !navigator.onLine) return { sent: 0, remaining: 0, warning: "" };
  flushing = true;
  let sent = 0,
    warning = "";
  try {
    const records = (await transaction<Pending[]>("readonly", (store) => store.getAll()))
      .filter((r) => r.owner === owner)
      .sort((a, b) => a.created - b.created);
    for (const record of records.slice(0, 10)) {
      if (Date.now() - record.created > 86400000) {
        warning =
          "Ada draft lebih dari 24 jam. Tinjau ulang sebelum dikirim; tidak dikirim otomatis.";
        continue;
      }
      const body = record.kind === "form" ? new FormData() : record.body;
      if (body instanceof FormData)
        for (const [key, value] of record.body as [string, string | File][])
          body.append(key, value);
      try {
        await post(record.path, body);
        await transaction("readwrite", (store) => store.delete(record.id));
        sent++;
      } catch (e) {
        warning =
          e instanceof ApiError && e.status === 409
            ? "Draft lama belum dikirim karena data berubah. Tinjau ulang pemeriksaan; antrean tetap disimpan."
            : (e as Error).message;
        break;
      }
    }
    return { sent, remaining: records.length - sent, warning };
  } finally {
    flushing = false;
  }
}
