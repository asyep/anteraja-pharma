<script setup lang="ts">
  import { ref, reactive, onMounted, watch } from "vue";
  import { useRouter, RouterLink } from "vue-router";
  import type { User, Shipment } from "../types";
  import { api, post, ApiError, capabilities } from "../lib/api";
  import { ArrowLeftIcon, ArrowRightIcon } from "@heroicons/vue/16/solid";
  const router = useRouter(),
    users = ref<User[]>([]),
    busy = ref(false),
    error = ref(""),
    fields = ref<Record<string, string[]>>({});
  const form = reactive({
    is_demo: false,
    pharma_category: "REGULAR",
    weight_kg: 1,
    buyer_id: "",
    inspector_id: "",
    sender_name: "",
    sender_chat_id: "",
    recipient_name: "",
    recipient_phone: "",
    address: "",
    origin_city: "Jakarta Timur",
    destination_city: "",
    item_summary: "",
    package_count: 1,
    handling_instructions: "",
    scheduled_at: "",
    temperature_profile: "ambient",
    temperature_min: 2,
    temperature_max: 8,
  });
  watch(
    () => form.pharma_category,
    (category) => {
      form.temperature_profile = category === "REGULAR" ? "ambient" : "controlled";
      [form.temperature_min, form.temperature_max] =
        category === "DEEP_FREEZE"
          ? [-25, -15]
          : category === "CONTROLLED_ROOM_TEMP"
            ? [15, 25]
            : [2, 8];
    },
  );
  const inputLabels = {
    sender_name: "Nama pengirim",
    sender_chat_id: "Chat ID Telegram pengirim (opsional)",
    recipient_name: "Nama penerima / fasilitas",
    recipient_phone: "Nomor telepon penerima",
    origin_city: "Kota asal",
    destination_city: "Kota tujuan",
    item_summary: "Ringkasan isi paket",
  } as const;
  onMounted(async () => {
    try {
      users.value = await api<User[]>("directory");
    } catch (e) {
      error.value = (e as Error).message;
    }
  });
  async function submit() {
    if (busy.value) return;
    busy.value = true;
    error.value = "";
    fields.value = {};
    try {
      const s = await post<Shipment>("shipments", {
        ...form,
        buyer_id: Number(form.buyer_id),
        inspector_id: Number(form.inspector_id),
        scheduled_at: new Date(form.scheduled_at).toISOString(),
        sender_chat_id: form.sender_chat_id || null,
        temperature_min: form.temperature_profile === "controlled" ? form.temperature_min : null,
        temperature_max: form.temperature_profile === "controlled" ? form.temperature_max : null,
      });
      await router.push("/kiriman/" + s.id);
    } catch (e) {
      error.value = (e as Error).message;
      if (e instanceof ApiError) fields.value = e.errors;
    } finally {
      busy.value = false;
    }
  }
</script>
<template>
  <RouterLink to="/kiriman" class="text-link">
    <ArrowLeftIcon />
    Kembali ke pengiriman
  </RouterLink>
  <section class="page-heading">
    <div>
      <p class="overline">Kiriman baru</p>
      <h1>Mulai dengan instruksi yang jelas.</h1>
      <p class="muted">Paket akan masuk pemeriksaan Checker, bukan langsung ditugaskan ke kurir.</p>
    </div>
  </section>
  <form class="create-form" :aria-busy="busy" @submit.prevent="submit">
    <section class="panel">
      <h2>Pengirim & penerima</h2>
      <div class="form-grid">
        <div v-for="(label, key) in inputLabels" :key="key">
          <label :for="key">{{ label }}</label>
          <input
            :id="key"
            v-model="form[key]"
            :name="key"
            :required="key !== 'sender_chat_id'"
            maxlength="150"
            :aria-invalid="!!fields[key]"
            :aria-describedby="fields[key] ? key + '-error' : undefined"
          />
          <p v-if="fields[key]" :id="key + '-error'" class="field-error">
            {{ fields[key]?.join(" ") }}
          </p>
        </div>
        <div>
          <label for="buyer-id">Akun Vendor / Customer untuk pemantauan</label>
          <select id="buyer-id" v-model="form.buyer_id" name="buyer_id" required>
            <option value="">Pilih akun</option>
            <option v-for="u in users.filter((u) => u.role === 'buyer')" :key="u.id" :value="u.id">
              {{ u.name }}
            </option>
          </select>
        </div>
        <div>
          <label for="inspector-id">Checker yang memeriksa paket</label>
          <select id="inspector-id" v-model="form.inspector_id" name="inspector_id" required>
            <option value="">Pilih petugas</option>
            <option
              v-for="u in users.filter((u) => u.role === 'checker')"
              :key="u.id"
              :value="u.id"
            >
              {{ u.name }}
            </option>
          </select>
        </div>
      </div>
      <label for="address">Alamat lengkap tujuan</label>
      <textarea
        id="address"
        v-model="form.address"
        name="address"
        rows="3"
        required
        maxlength="1000"
      />
    </section>
    <section class="panel">
      <h2>Jadwal & penanganan</h2>
      <div class="form-grid">
        <div>
          <label for="pharma-category">Kategori penanganan</label>
          <select id="pharma-category" v-model="form.pharma_category" name="pharma_category">
            <option value="REGULAR">Reguler sesuai instruksi</option>
            <option value="COLD_CHAIN_2_8">Cold-chain 2–8°C</option>
            <option value="DEEP_FREEZE">Deep freeze — atur sesuai produk</option>
            <option value="CONTROLLED_ROOM_TEMP">Suhu ruangan terkendali</option>
          </select>
          <p class="small muted">
            Preset dapat diubah sesuai instruksi produk, bukan rekomendasi medis.
          </p>
        </div>
        <div>
          <label for="weight-kg">Berat total (kg)</label>
          <input
            id="weight-kg"
            v-model.number="form.weight_kg"
            name="weight_kg"
            type="number"
            min="0.01"
            max="1000"
            step="0.01"
            required
          />
        </div>
        <div>
          <label for="schedule">Jadwal tiba</label>
          <input
            id="schedule"
            v-model="form.scheduled_at"
            name="scheduled_at"
            type="datetime-local"
            required
          />
        </div>
        <div>
          <label for="package-count">Jumlah paket</label>
          <input
            id="package-count"
            v-model="form.package_count"
            name="package_count"
            type="number"
            min="1"
            max="100"
            required
          />
        </div>
        <div>
          <label for="temperature-profile">Penanganan suhu</label>
          <select
            id="temperature-profile"
            v-model="form.temperature_profile"
            name="temperature_profile"
          >
            <option value="ambient">Ikuti instruksi penyimpanan paket</option>
            <option value="controlled">Rentang suhu khusus</option>
          </select>
        </div>
        <template v-if="form.temperature_profile === 'controlled'">
          <div>
            <label for="temp-min">Batas bawah (°C)</label>
            <input
              id="temp-min"
              v-model="form.temperature_min"
              name="temperature_min"
              type="number"
              step="0.1"
              min="-50"
              max="60"
              required
            />
          </div>
          <div>
            <label for="temp-max">Batas atas (°C)</label>
            <input
              id="temp-max"
              v-model="form.temperature_max"
              name="temperature_max"
              type="number"
              step="0.1"
              :min="form.temperature_min"
              max="60"
              required
            />
          </div>
        </template>
      </div>
      <label for="instructions">Instruksi untuk pemeriksaan dan pengantaran</label>
      <textarea
        id="instructions"
        v-model="form.handling_instructions"
        name="handling_instructions"
        required
        maxlength="2000"
        rows="4"
        placeholder="Misal: simpan tegak, hindari panas, periksa segel bersama penerima."
      />
      <p class="small muted">
        Gunakan instruksi sesuai isi paket. Jangan mencantumkan diagnosis atau data pasien.
      </p>
    </section>
    <section class="notice neutral">
      <p>
        Setelah disimpan, Checker yang dipilih dapat memeriksa kemasan, segel, dan label. Admin
        tetap harus menyetujui sebelum penugasan pengantaran.
      </p>
    </section>
    <label v-if="capabilities.demo_enabled" class="radio-option">
      <input v-model="form.is_demo" name="is_demo" type="checkbox" />
      Data demonstrasi (boleh dipakai simulator)
    </label>
    <section class="panel">
      <h2>Preview kabar untuk customer</h2>
      <p>
        {{ form.recipient_name || "Nama penerima" }}, kiriman
        {{ form.item_summary || "produk farmasi" }} sudah terdaftar dan menunggu pemeriksaan.
        Setelah masuk, kamu bisa memantau status, lokasi, dan laporan kirimanmu.
      </p>
      <p class="small muted">
        Pesan masuk antrean setelah kiriman tersimpan. Terkirim hanya jika provider telah
        mengonfirmasi.
      </p>
    </section>
    <div v-if="error" id="create-error" class="notice danger" role="alert">
      <p>{{ error }}</p>
      <ul role="list">
        <li v-for="(messages, key) in fields" :key="key">
          {{ messages.join(" ") }}
        </li>
      </ul>
    </div>
    <footer class="form-footer">
      <RouterLink to="/kiriman" class="button secondary">Batal</RouterLink>
      <button type="submit" class="button primary" :disabled="busy">
        Simpan kiriman
        <span v-if="busy" class="spinner" aria-label="Menyimpan" />
        <ArrowRightIcon v-else />
      </button>
    </footer>
  </form>
</template>
