import { ref } from "vue";
import type { User } from "../types";

export const currentUser = ref<User | null>(null);
export const capabilities = ref({ demo_enabled: false });
let csrf = "";
export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors: Record<string, string[]> = {},
  ) {
    super(message);
  }
}
export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const controller = new AbortController();
  const abort = () => controller.abort();
  options.signal?.addEventListener("abort", abort, { once: true });
  if (options.signal?.aborted) controller.abort();
  const timer = setTimeout(abort, 12000);
  try {
    const headers = new Headers({
      Accept: "application/json",
      "X-CSRF-TOKEN": csrf,
      ...options.headers,
    });
    if (!(options.body instanceof FormData)) headers.set("Content-Type", "application/json");
    const response = await fetch((import.meta.env.VITE_API_BASE_URL || "") + "/api/" + path, {
      ...options,
      signal: controller.signal,
      credentials: "include",
      headers,
    });
    const result = await response.json();
    if (!response.ok) {
      if (response.status === 401) currentUser.value = null;
      const message = [401, 419].includes(response.status)
        ? "Sesi sudah berakhir. Muat ulang dan masuk kembali."
        : response.status === 409
          ? "Data kiriman sudah berubah. Muat ulang sebelum melanjutkan."
          : response.status === 403
            ? "Akunmu tidak memiliki izin untuk tindakan ini."
            : response.status === 404
              ? "Kiriman tidak ditemukan atau bukan milik akunmu."
              : response.status === 429
                ? "Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi."
                : response.status >= 500
                  ? "Server belum dapat memproses permintaan. Coba lagi sebentar."
                  : result.message || "Permintaan belum berhasil. Coba lagi sebentar.";
      throw new ApiError(message, response.status, result.errors);
    }
    if (result.csrf_token) csrf = result.csrf_token;
    return result as T;
  } catch (error) {
    if (error instanceof ApiError) throw error;
    if (options.signal?.aborted) throw error;
    throw new ApiError(
      "Koneksi belum berhasil. Periksa jaringan lalu coba lagi; isianmu tetap tersimpan.",
      0,
    );
  } finally {
    clearTimeout(timer);
    options.signal?.removeEventListener("abort", abort);
  }
}
export async function loadSession() {
  const data = await api<{ user: User | null; capabilities?: { demo_enabled: boolean } }>(
    "session",
  );
  currentUser.value = data.user;
  capabilities.value = data.capabilities || { demo_enabled: false };
}
export function post<T>(path: string, data: unknown) {
  return api<T>(path, {
    method: "POST",
    body: data instanceof FormData ? data : JSON.stringify(data),
  });
}
