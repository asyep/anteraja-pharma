import { ref, shallowRef, onUnmounted } from "vue";
import { api } from "../lib/api";

export function useResource<T>() {
  const data = shallowRef<T | null>(null);
  const loading = ref(false);
  const error = ref("");
  let controller: AbortController | null = null;
  let pending = false;
  async function load(path: string, options: { silent?: boolean } = {}) {
    if (options.silent && pending) return;
    controller?.abort();
    const request = new AbortController();
    controller = request;
    pending = true;
    if (!options.silent || !data.value) loading.value = true;
    error.value = "";
    try {
      const result = await api<T>(path, { signal: request.signal });
      if (!request.signal.aborted) data.value = result;
    } catch (e) {
      if (!request.signal.aborted)
        error.value = e instanceof Error ? e.message : "Data belum bisa dimuat.";
    } finally {
      if (controller === request) pending = false;
      if (!request.signal.aborted) loading.value = false;
    }
  }
  onUnmounted(() => controller?.abort());
  return { data, loading, error, load };
}
