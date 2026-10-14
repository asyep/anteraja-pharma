<script setup lang="ts">
  import { computed, ref } from "vue";
  import { useRouter } from "vue-router";
  import { api, post, currentUser, capabilities } from "../lib/api";
  import type { Shipment } from "../types";
  import { BeakerIcon, XMarkIcon } from "@heroicons/vue/16/solid";
  const router = useRouter();
  const open = ref(false),
    busy = ref(false),
    notice = ref("");
  const shipments = ref<Shipment[]>([]),
    selected = ref(""),
    temperature = ref(12.5),
    speed = ref("10"),
    chat = ref("");
  const enabled = computed(
    () => currentUser.value?.role === "admin" && capabilities.value.demo_enabled,
  );
  async function toggle() {
    open.value = !open.value;
    if (!open.value) return;
    try {
      shipments.value = await api<Shipment[]>("demo/shipments");
      selected.value ||= String(shipments.value[0]?.id || "");
    } catch (e) {
      notice.value = (e as Error).message;
    }
  }
  async function run(scenario: string) {
    if (busy.value || !selected.value) return;
    busy.value = true;
    notice.value = "";
    try {
      const result = await post<{ message: string }>("demo/shipments/" + selected.value, {
        scenario,
        temperature: temperature.value,
        speed: speed.value,
        event_id: crypto.randomUUID(),
      });
      notice.value = result.message;
      await router.push("/kiriman/" + selected.value);
      window.dispatchEvent(new Event("pharma:refresh"));
    } catch (e) {
      notice.value = (e as Error).message;
    } finally {
      busy.value = false;
    }
  }
  async function send() {
    if (busy.value) return;
    busy.value = true;
    try {
      const result = await post<{ status: string; message: string }>("demo/message", {
        chat_id: chat.value,
      });
      notice.value = result.status + ": " + result.message;
    } catch (e) {
      notice.value = (e as Error).message;
    } finally {
      busy.value = false;
    }
  }
</script>
<template>
  <aside v-if="enabled" class="demo-widget" aria-label="Control panel demonstrasi">
    <button v-if="!open" type="button" class="button secondary demo-launcher" @click="toggle">
      <BeakerIcon />
      Mode demo
    </button>
    <section v-else class="demo-window">
      <header>
        <div>
          <p class="overline">Hanya data demonstrasi</p>
          <h2>Uji alur Pharma</h2>
        </div>
        <button
          type="button"
          class="icon-button"
          aria-label="Tutup panel demo"
          @click="open = false"
        >
          <XMarkIcon />
        </button>
      </header>
      <p class="small muted">
        Pilih kiriman demo yang sudah diperiksa, disetujui, dan sedang diantar. Simulasi tidak
        melewati gerbang persetujuan.
      </p>
      <label for="demo-shipment">Kiriman demo aktif</label>
      <select id="demo-shipment" v-model="selected" name="shipment_id">
        <option value="">Pilih kiriman</option>
        <option v-for="s in shipments" :key="s.id" :value="String(s.id)">
          {{ s.tracking_number }} · {{ s.status }}
        </option>
      </select>
      <p v-if="!shipments.length" class="notice neutral small">
        Belum ada kiriman demo aktif. Buat kiriman dengan tanda demonstrasi, lalu jalankan
        pemeriksaan dan penugasan.
      </p>
      <label for="demo-temperature">Suhu simulasi {{ temperature }}°C</label>
      <input
        id="demo-temperature"
        v-model.number="temperature"
        name="temperature"
        type="range"
        min="-10"
        max="40"
        step="0.5"
      />
      <div class="demo-actions">
        <button
          type="button"
          class="button secondary"
          :disabled="busy || !selected"
          @click="run('normal')"
        >
          Kirim suhu ini
        </button>
        <button
          type="button"
          class="button secondary"
          :disabled="busy || !selected"
          @click="
            temperature = 12.5;
            run('spike');
          "
        >
          Lonjakan 12.5°C
        </button>
        <button
          type="button"
          class="button secondary"
          :disabled="busy || !selected"
          @click="run('trend')"
        >
          Tren suhu naik
        </button>
        <button
          type="button"
          class="button secondary"
          :disabled="busy || !selected"
          @click="run('seal')"
        >
          Segel rusak
        </button>
        <button
          type="button"
          class="button secondary"
          :disabled="busy || !selected"
          @click="run('signal_loss')"
        >
          Sinyal putus 15 menit
        </button>
      </div>
      <label for="demo-speed">Kecepatan demonstrasi rute</label>
      <select id="demo-speed" v-model="speed" name="speed">
        <option value="1">1x</option>
        <option value="10">10x</option>
        <option value="60">60x</option>
      </select>
      <button
        type="button"
        class="button secondary"
        :disabled="busy || !selected"
        @click="run('route')"
      >
        Peragakan jejak rute
      </button>
      <details>
        <summary>Uji Telegram nyata</summary>
        <form @submit.prevent="send">
          <label for="demo-chat">Chat ID akun uji (sudah /start)</label>
          <input id="demo-chat" v-model="chat" name="chat_id" required pattern="-?[0-9]+" />
          <button type="submit" class="button secondary" :disabled="busy">Kirim pesan uji</button>
        </form>
      </details>
      <p v-if="busy" role="status">Memproses skenario…</p>
      <p v-if="notice" class="notice neutral small" role="status">{{ notice }}</p>
    </section>
  </aside>
</template>
