<script setup lang="ts">
  import { computed, onUnmounted, reactive, ref, watch } from "vue";
  import type { Shipment, User } from "../types";
  import { currentUser, api, post, ApiError } from "../lib/api";
  import { issueLabels } from "../lib/presentation";
  import { enqueue, pending, discard } from "../lib/offline";
  import { CheckIcon, ExclamationTriangleIcon, ArrowRightIcon } from "@heroicons/vue/16/solid";
  const props = defineProps<{ shipment: Shipment }>();
  const emit = defineEmits<{ updated: [] }>();
  const chosen = ref(""),
    busy = ref(false),
    inspectionPhoto = ref<File | null>(null),
    liveTracking = ref(false),
    error = ref(""),
    success = ref(""),
    directory = ref<User[]>([]),
    fields = ref<Record<string, string[]>>({});
  const form = reactive({
    reason: "",
    packaging: false,
    seal: false,
    label: false,
    temperature: "",
    courier_id: "",
    location: "",
    latitude: null as number | null,
    longitude: null as number | null,
    speed_kmh: null as number | null,
    telemetry_temperature: "",
    issue_type: "seal",
    decision: "return",
    ready: true,
    received_by: "",
  });
  const admin = computed(() => currentUser.value?.role === "admin");
  const checker = computed(() => currentUser.value?.role === "checker");
  const buyer = computed(() => currentUser.value?.role === "buyer");
  const assignment = computed(() => props.shipment.assignments?.at(-1));
  const courier = computed(
    () =>
      currentUser.value?.role === "courier" &&
      assignment.value?.courier_id === currentUser.value.id &&
      assignment.value.status === "accepted",
  );
  const openIssue = computed(() => props.shipment.issues?.find((i) => i.status === "open"));
  let gpsTimer: ReturnType<typeof setInterval> | null = null;
  let gpsSending = false;
  let gpsGeneration = 0;
  const inspectStep = ref(1),
    scan = ref("");
  const podPhoto = ref<File | null>(null),
    signature = ref("");
  const restoredDraft = ref("");
  async function scanBarcode(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    const Detector = (
      window as unknown as {
        BarcodeDetector?: new () => { detect(image: ImageBitmap): Promise<{ rawValue: string }[]> };
      }
    ).BarcodeDetector;
    if (!Detector) {
      error.value = "Pemindai belum didukung browser ini. Ketik resi dari label fisik paket.";
      return;
    }
    let bitmap: ImageBitmap | undefined;
    try {
      bitmap = await createImageBitmap(file);
      const matches = await new Detector().detect(bitmap);
      if (!matches.length)
        throw new Error(
          "Barcode belum terbaca. Ambil foto yang lebih jelas atau ketik resi fisik.",
        );
      scan.value = matches[0]!.rawValue.trim();
      error.value = "";
    } catch (e) {
      error.value = (e as Error).message;
    } finally {
      bitmap?.close();
    }
  }
  async function restoreInspection() {
    if (!currentUser.value) return;
    try {
      const records = await pending(currentUser.value.id);
      const record = records.find(
        (r) => r.path === `shipments/${props.shipment.id}/actions/inspect` && r.kind === "form",
      );
      if (!record) {
        error.value = "Tidak ada draft pemeriksaan offline untuk paket ini.";
        return;
      }
      const values = Object.fromEntries(record.body as [string, string | File][]);
      form.packaging = values.packaging === "1";
      form.seal = values.seal === "1";
      form.label = values.label === "1";
      form.temperature = String(values.temperature || "");
      form.reason = String(values.reason || "");
      scan.value = String(values.scanned_tracking_number || "");
      inspectionPhoto.value = values.photo_proof instanceof File ? values.photo_proof : null;
      restoredDraft.value = record.id;
      inspectStep.value = 1;
      success.value =
        "Draft dipulihkan. Periksa ulang checklist dan foto sebelum mengirim dengan versi terbaru.";
    } catch (e) {
      error.value = (e as Error).message;
    }
  }
  const actions = computed(() => {
    const s = props.shipment,
      result: { key: string; title: string; copy: string }[] = [];
    const add = (key: string, title: string, copy: string) => result.push({ key, title, copy });
    if (
      checker.value &&
      currentUser.value?.id === s.inspector_id &&
      (["inspection", "pending_approval"].includes(s.status) ||
        (s.status === "held" && !openIssue.value))
    )
      add(
        "inspect",
        "Periksa paket",
        "Catat kondisi kemasan, segel, dan label yang benar-benar diperiksa.",
      );
    if (admin.value && ["inspection", "pending_approval"].includes(s.status))
      add(
        "approve",
        "Setujui pengiriman",
        "Sistem akan memblokir persetujuan bila pemeriksaan belum lengkap.",
      );
    if (admin.value && ["inspection", "pending_approval", "approved"].includes(s.status))
      add(
        "hold",
        "Tahan pengiriman",
        "Paket tidak ditugaskan sampai diperiksa dan disetujui ulang.",
      );
    if (admin.value && ["inspection", "pending_approval", "approved"].includes(s.status))
      add(
        "cancel",
        "Batalkan kiriman",
        "Pembatalan sebelum penugasan wajib memiliki alasan dan tetap masuk audit.",
      );
    if (admin.value && s.status === "approved")
      add("assign", "Pilih kurir", "Kurir menerima instruksi dan perlu menjawab tugas ini.");
    if (
      currentUser.value?.role === "courier" &&
      assignment.value?.courier_id === currentUser.value.id &&
      s.status === "assigned"
    ) {
      add("accept", "Terima tugas", "Kamu siap mengambil dan mengantar sesuai instruksi.");
      add("reject", "Tolak tugas", "Admin akan mendapat pemberitahuan untuk memilih kurir lain.");
    }
    if (courier.value && s.status === "accepted")
      add("depart", "Mulai perjalanan", "Pastikan paket sesuai instruksi sebelum berangkat.");
    if (courier.value && s.status === "in_transit")
      add("arrive", "Sudah di lokasi", "Catat lokasi terakhir sebelum serah terima.");
    if (courier.value && ["in_transit", "held"].includes(s.status))
      add(
        "telemetry",
        "Kirim lokasi GPS",
        "Bagikan koordinat perjalanan dan catatan suhu ke monitor.",
      );
    if (courier.value && s.status === "arrived")
      add(
        "deliver",
        "Catat penerimaan",
        "Catat nama orang yang menerima dan hasil pemeriksaan saat serah terima.",
      );
    if (courier.value && ["accepted", "in_transit", "arrived"].includes(s.status))
      add(
        "issue",
        "Laporkan kendala",
        "Pengiriman langsung ditahan. Tunggu keputusan Admin sebelum melanjutkan.",
      );
    if (admin.value && s.status === "held" && openIssue.value)
      add(
        "decide",
        "Putuskan tindak lanjut",
        "Alasan dan instruksi disimpan, lalu dikirim ke kurir dan penerima.",
      );
    if (courier.value && s.status === "returning")
      add(
        "return_complete",
        "Catat paket kembali",
        "Pastikan paket benar-benar diterima kembali di lokasi yang diinstruksikan.",
      );
    if (admin.value && s.status === "approved")
      result.sort((a, b) => Number(b.key === "assign") - Number(a.key === "assign"));
    if (admin.value && s.status === "pending_approval")
      result.sort((a, b) => Number(b.key === "approve") - Number(a.key === "approve"));
    return s.closed_at ? [] : result;
  });
  const active = computed(() => actions.value.find((a) => a.key === chosen.value));
  watch(
    () => props.shipment.id,
    () => {
      chosen.value = "";
      success.value = "";
      error.value = "";
      form.reason = "";
      inspectionPhoto.value = null;
      podPhoto.value = null;
      signature.value = "";
      scan.value = "";
      inspectStep.value = 1;
      stopLiveTracking();
    },
    { immediate: true },
  );
  watch(
    actions,
    () => {
      if (!actions.value.some((a) => a.key === chosen.value))
        chosen.value = actions.value[0]?.key || "";
    },
    { immediate: true },
  );
  watch(
    () => props.shipment.status,
    (status) => {
      if (!["in_transit", "held"].includes(status)) stopLiveTracking();
    },
  );
  async function choose(key: string) {
    chosen.value = key;
    error.value = "";
    success.value = "";
    fields.value = {};
    if (key === "assign" && !directory.value.length) {
      try {
        directory.value = await api<User[]>("directory");
      } catch (e) {
        error.value = (e as Error).message;
      }
    }
  }
  watch(chosen, (key) => {
    if (key !== "telemetry") stopLiveTracking();
    if (key === "assign" && !directory.value.length) void choose(key);
  });
  async function submit() {
    if (chosen.value === "inspect" && inspectStep.value === 1) {
      inspectStep.value = 2;
      return;
    }
    if (busy.value || !active.value) return;
    busy.value = true;
    error.value = "";
    success.value = "";
    fields.value = {};
    const action = chosen.value;
    try {
      const payload: Record<string, unknown> = {
        version: props.shipment.version,
        reason: form.reason,
        request_id: crypto.randomUUID(),
      };
      if (action === "inspect")
        Object.assign(payload, {
          packaging: form.packaging,
          seal: form.seal,
          label: form.label,
          temperature: form.temperature === "" ? null : Number(form.temperature),
        });
      if (action === "inspect") {
        if (scan.value.trim().toUpperCase() !== props.shipment.tracking_number.toUpperCase())
          throw new Error("Scan atau ketik resi fisik yang cocok dengan kiriman ini.");
        payload.scanned_tracking_number = scan.value.trim();
      }
      if (action === "assign") payload.courier_id = Number(form.courier_id);
      if (["depart", "arrive", "return_complete"].includes(action))
        payload.location = form.location;
      if (action === "issue") payload.issue_type = form.issue_type;
      if (action === "decide") payload.decision = form.decision;
      if (action === "ready") payload.ready = form.ready;
      if (action === "deliver") payload.received_by = form.received_by;
      if (action === "telemetry") {
        if (form.latitude === null || form.longitude === null) {
          throw new Error("Ambil lokasi GPS sebelum mengirim pembaruan.");
        }
        const telemetry = {
          latitude: form.latitude,
          longitude: form.longitude,
          speed_kmh: form.speed_kmh,
          recorded_at: new Date().toISOString(),
          client_event_id: crypto.randomUUID(),
          temperature:
            form.telemetry_temperature === "" ? null : Number(form.telemetry_temperature),
        };
        try {
          await post(`shipments/${props.shipment.id}/telemetry`, telemetry);
        } catch (e) {
          if (e instanceof ApiError && e.status === 0 && currentUser.value) {
            await enqueue(
              currentUser.value.id,
              `shipments/${props.shipment.id}/telemetry`,
              telemetry,
            );
            success.value = "GPS dan suhu tersimpan offline, belum terkirim ke server.";
            return;
          }
          throw e;
        }
        success.value = "Lokasi terkirim dan pemeriksaan otomatis selesai.";
      } else if (["inspect", "deliver", "return_complete"].includes(action)) {
        const photo = action === "inspect" ? inspectionPhoto.value : podPhoto.value;
        if (!photo) {
          throw new Error("Lampirkan foto kondisi fisik paket sebelum mengirim hasil pemeriksaan.");
        }
        if (photo.size > 5 * 1024 * 1024)
          throw new Error("Foto maksimal 5 MB. Gunakan foto yang lebih kecil.");
        const upload = new FormData();
        for (const [key, value] of Object.entries(payload)) {
          if (value !== null && value !== undefined)
            upload.append(key, typeof value === "boolean" ? (value ? "1" : "0") : String(value));
        }
        upload.append("photo_proof", photo);
        if (action !== "inspect") upload.append("receiver_signature", signature.value);
        const path = `shipments/${props.shipment.id}/actions/${action}`;
        try {
          await post<Shipment>(path, upload);
        } catch (e) {
          if (
            action === "inspect" &&
            e instanceof ApiError &&
            e.status === 0 &&
            currentUser.value
          ) {
            await enqueue(currentUser.value.id, path, upload);
            success.value =
              "Pemeriksaan tersimpan di antrean browser, belum masuk server. Akan dicoba saat koneksi pulih.";
            return;
          }
          throw e;
        }
        if (restoredDraft.value && currentUser.value) {
          await discard(restoredDraft.value, currentUser.value.id);
          restoredDraft.value = "";
        }
        success.value = "Bukti dan catatan tersimpan.";
      } else {
        await post<Shipment>(`shipments/${props.shipment.id}/actions/${action}`, payload);
        success.value = "Pembaruan tersimpan. Riwayat dan notifikasi sudah disiapkan.";
      }
      form.reason = "";
      if (action === "inspect") inspectionPhoto.value = null;
      emit("updated");
    } catch (e) {
      error.value = (e as Error).message;
      if (e instanceof ApiError) fields.value = e.errors;
    } finally {
      busy.value = false;
    }
  }
  function captureGps() {
    error.value = "";
    if (!navigator.geolocation) {
      error.value = "Perangkat ini tidak menyediakan layanan lokasi.";
      return;
    }
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        form.latitude = coords.latitude;
        form.longitude = coords.longitude;
        form.speed_kmh = coords.speed === null ? null : coords.speed * 3.6;
      },
      () => {
        error.value = "Lokasi tidak dapat diakses. Izinkan akses lokasi lalu coba lagi.";
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 10000 },
    );
  }
  function selectInspectionPhoto(event: Event) {
    inspectionPhoto.value = (event.target as HTMLInputElement).files?.[0] || null;
  }
  function stopLiveTracking() {
    gpsGeneration++;
    if (gpsTimer) clearInterval(gpsTimer);
    gpsTimer = null;
    liveTracking.value = false;
  }
  async function sendLiveGps() {
    if (gpsSending) return;
    if (!["in_transit", "held"].includes(props.shipment.status)) {
      stopLiveTracking();
      return;
    }
    if (!navigator.geolocation) {
      error.value = "Perangkat ini tidak menyediakan layanan lokasi.";
      stopLiveTracking();
      return;
    }
    gpsSending = true;
    const generation = gpsGeneration;
    const shipmentId = props.shipment.id;
    navigator.geolocation.getCurrentPosition(
      async ({ coords }) => {
        if (generation !== gpsGeneration || shipmentId !== props.shipment.id) {
          gpsSending = false;
          return;
        }
        try {
          const payload = {
            latitude: coords.latitude,
            longitude: coords.longitude,
            speed_kmh: coords.speed === null ? null : coords.speed * 3.6,
            temperature: null,
            recorded_at: new Date().toISOString(),
            client_event_id: crypto.randomUUID(),
          };
          try {
            await post(`shipments/${shipmentId}/telemetry`, payload);
          } catch (e) {
            if (e instanceof ApiError && e.status === 0 && currentUser.value) {
              await enqueue(
                currentUser.value.id,
                `shipments/${props.shipment.id}/telemetry`,
                payload,
              );
              success.value = "GPS tersimpan offline, belum terkirim ke server.";
            } else throw e;
          }
          form.latitude = coords.latitude;
          form.longitude = coords.longitude;
          form.speed_kmh = coords.speed === null ? null : coords.speed * 3.6;
          emit("updated");
        } catch (e) {
          error.value = (e as Error).message;
          stopLiveTracking();
        } finally {
          gpsSending = false;
        }
      },
      () => {
        error.value = "Lokasi tidak dapat diakses. Izinkan akses lokasi lalu coba lagi.";
        gpsSending = false;
        stopLiveTracking();
      },
      { enableHighAccuracy: true, timeout: 9000, maximumAge: 0 },
    );
  }
  function startLiveTracking() {
    if (props.shipment.temperature_profile === "controlled") {
      error.value = "Kirim pembacaan suhu manual bersama lokasi agar data cold-chain tidak kosong.";
      return;
    }
    error.value = "";
    liveTracking.value = true;
    void sendLiveGps();
    gpsTimer = setInterval(() => void sendLiveGps(), 10000);
  }
  onUnmounted(stopLiveTracking);
</script>
<template>
  <section id="actions" class="action-panel" aria-labelledby="action-heading">
    <p class="overline">Langkah berikutnya</p>
    <h2 id="action-heading">
      {{
        admin
          ? "Keputusanmu menjaga paket."
          : checker
            ? "Pemeriksaan awal yang teliti."
            : buyer
              ? "Pantau paketmu di sini."
              : "Lanjutkan sesuai instruksi."
      }}
    </h2>
    <p v-if="!actions.length" class="muted">
      {{
        shipment.status === "held"
          ? "Paket sedang ditahan. Tunggu keputusan Admin; jangan lanjutkan pengiriman."
          : "Tidak ada tindakan yang perlu dilakukan oleh akunmu pada tahap ini."
      }}
    </p>
    <template v-else>
      <div class="action-options">
        <button
          v-for="a in actions"
          :key="a.key"
          type="button"
          :aria-pressed="chosen === a.key"
          @click="choose(a.key)"
        >
          {{ a.title }}
          <ArrowRightIcon aria-hidden="true" />
        </button>
      </div>
      <form v-if="active" :aria-busy="busy" @submit.prevent="submit">
        <h3>{{ active.title }}</h3>
        <p class="small muted">{{ active.copy }}</p>
        <fieldset v-if="chosen === 'inspect'" class="checklist">
          <legend class="sr-only">Pemeriksaan wajib</legend>
          <p class="small">Langkah {{ inspectStep }} dari 2</p>
          <button type="button" class="button secondary" @click="restoreInspection">
            Pulihkan draft offline
          </button>
          <div v-show="inspectStep === 1">
            <label for="scanned-tracking">Resi pada fisik paket</label>
            <input
              id="scanned-tracking"
              v-model="scan"
              name="scanned_tracking_number"
              placeholder="Scan atau ketik nomor resi"
              required
            />
            <label for="barcode-photo">Scan foto barcode / QR (opsional)</label>
            <input
              id="barcode-photo"
              type="file"
              accept="image/*"
              capture="environment"
              @change="scanBarcode"
            />
            <p class="small muted">
              Browser yang mendukung BarcodeDetector dapat membaca foto label. Bila tidak, ketik
              resi fisik; sistem tetap mencocokkannya.
            </p>
            <label>
              <input v-model="form.packaging" name="packaging" type="checkbox" />
              Kemasan utuh dan sesuai instruksi
            </label>
            <label>
              <input v-model="form.seal" name="seal" type="checkbox" />
              Segel utuh, belum terbuka
            </label>
            <label>
              <input v-model="form.label" name="label" type="checkbox" />
              Label sesuai data penerima
            </label>
            <p class="small muted">
              {{ Number(form.packaging) + Number(form.seal) + Number(form.label) }}/3 pemeriksaan
              sesuai
            </p>
            <template v-if="shipment.temperature_profile === 'controlled'">
              <label for="temperature">Suhu yang diukur (°C)</label>
              <input
                id="temperature"
                v-model="form.temperature"
                type="number"
                step="0.1"
                min="-50"
                max="60"
                name="temperature"
                required
              />
              <p class="small">
                Instruksi paket: {{ shipment.temperature_min }}–{{ shipment.temperature_max }}°C.
                Catatan manual, bukan sensor.
              </p>
            </template>
          </div>
          <div v-if="inspectStep === 2">
            <label for="inspection-photo">Foto bukti kondisi paket / segel</label>
            <input
              id="inspection-photo"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              capture="environment"
              required
              @change="selectInspectionPhoto"
            />
            <p class="small muted">JPG, PNG, atau WebP · maksimal 5 MB.</p>
            <p v-if="inspectionPhoto" class="small">
              Foto terpilih: {{ inspectionPhoto.name }}. Pilih ulang bila kondisi paket berubah.
            </p>
          </div>
          <button
            v-if="inspectStep === 1"
            type="button"
            class="button secondary"
            @click="inspectStep = 2"
          >
            Lanjut ke foto bukti
          </button>
          <button v-else type="button" class="button secondary" @click="inspectStep = 1">
            Kembali ke checklist
          </button>
        </fieldset>
        <template v-if="chosen === 'assign'">
          <label for="courier">Pilih kurir</label>
          <select id="courier" v-model="form.courier_id" name="courier_id" required>
            <option value="">Pilih petugas</option>
            <option
              v-for="u in directory.filter((u) => u.role === 'courier')"
              :key="u.id"
              :value="u.id"
            >
              {{ u.name }} · cold-box {{ u.cold_box_capacity_kg ?? "belum terdaftar" }} kg
            </option>
          </select>
        </template>
        <template v-if="['depart', 'arrive', 'return_complete'].includes(chosen)">
          <label for="location">Lokasi saat ini</label>
          <input
            id="location"
            v-model="form.location"
            name="location"
            required
            maxlength="180"
            placeholder="Misal: Hub Cakung atau Klinik Cilandak"
          />
        </template>
        <fieldset v-if="chosen === 'telemetry'" class="checklist">
          <legend>Pelacakan perjalanan</legend>
          <button
            v-if="shipment.temperature_profile !== 'controlled'"
            type="button"
            class="button secondary"
            :aria-pressed="liveTracking"
            @click="liveTracking ? stopLiveTracking() : startLiveTracking()"
          >
            {{ liveTracking ? "Hentikan pelacakan otomatis" : "Mulai pelacakan GPS otomatis" }}
          </button>
          <p v-if="liveTracking" class="small muted" role="status">
            Lokasi perangkat dikirim tiap 10 detik selama halaman ini terbuka.
          </p>
          <button type="button" class="button secondary" @click="captureGps">
            Ambil lokasi GPS
          </button>
          <p v-if="form.latitude !== null && form.longitude !== null" class="small">
            {{ form.latitude.toFixed(6) }}, {{ form.longitude.toFixed(6) }}
            <span v-if="form.speed_kmh !== null">· {{ form.speed_kmh.toFixed(1) }} km/j</span>
          </p>
          <template v-if="shipment.temperature_profile === 'controlled'">
            <label for="telemetry-temperature">Suhu yang terbaca (°C)</label>
            <input
              id="telemetry-temperature"
              v-model="form.telemetry_temperature"
              type="number"
              step="0.1"
              min="-50"
              max="60"
              required
            />
            <p class="small muted">
              Masukkan pembacaan sensor/perangkat. Jangan mengira-ngira nilai.
            </p>
          </template>
          <p v-else class="small muted">
            Koordinat diambil dari GPS perangkat. Suhu tidak diwajibkan.
          </p>
        </fieldset>
        <template v-if="chosen === 'deliver'">
          <label for="received-by">Nama penerima paket</label>
          <input
            id="received-by"
            v-model="form.received_by"
            name="received_by"
            required
            maxlength="150"
          />
        </template>
        <template v-if="chosen === 'issue'">
          <label for="issue-type">Apa kendalanya?</label>
          <select id="issue-type" v-model="form.issue_type" name="issue_type">
            <option v-for="(label, key) in issueLabels" :key="key" :value="key">
              {{ label }}
            </option>
          </select>
          <p class="notice danger small">
            <ExclamationTriangleIcon />
            Setelah dikirim, paket ditahan sampai ada keputusan Admin.
          </p>
        </template>
        <template v-if="chosen === 'decide'">
          <label for="decision">Tindakan untuk kurir</label>
          <select id="decision" v-model="form.decision" name="decision">
            <option value="return">Kembalikan paket (retur)</option>
            <option value="recheck">Periksa dan minta persetujuan ulang</option>
            <option
              v-if="
                openIssue &&
                [
                  'recipient_unavailable',
                  'address',
                  'other',
                  'signal_loss',
                  'temperature',
                ].includes(openIssue.type)
              "
              value="resume"
            >
              Lanjutkan dengan instruksi Admin
            </option>
            <option
              v-if="openIssue && !['seal', 'packaging', 'label'].includes(openIssue.type)"
              value="reroute"
            >
              Alihkan ke hub / cold-storage
            </option>
          </select>
          <p class="notice warning small">
            {{
              form.decision === "return"
                ? "Kurir akan diminta mengembalikan paket, bukan menyerahkannya ke penerima."
                : form.decision === "recheck"
                  ? "Penugasan lama dibatalkan. Pemeriksaan dan persetujuan ulang wajib dilakukan."
                  : "Paket melanjutkan tahap sebelumnya. Tulis instruksi yang jelas."
            }}
          </p>
        </template>
        <fieldset v-if="chosen === 'ready'">
          <legend>Kesiapan penerima</legend>
          <label class="radio-option">
            <input v-model="form.ready" type="radio" name="ready" :value="true" />
            Siap menerima paket
          </label>
          <label class="radio-option">
            <input v-model="form.ready" type="radio" name="ready" :value="false" />
            Belum ada yang bisa menerima
          </label>
        </fieldset>
        <fieldset v-if="['deliver', 'return_complete'].includes(chosen)" class="checklist">
          <label for="pod-photo">Foto bukti penyerahan / pengembalian</label>
          <input
            id="pod-photo"
            name="photo_proof"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            capture="environment"
            required
            @change="podPhoto = ($event.target as HTMLInputElement).files?.[0] || null"
          />
          <label for="receiver-signature">Nama penerima yang menyetujui serah terima</label>
          <input
            id="receiver-signature"
            v-model="signature"
            name="receiver_signature"
            minlength="2"
            maxlength="150"
            required
          />
          <p class="small muted">
            Catatan persetujuan nama, bukan tanda tangan digital tersertifikasi.
          </p>
        </fieldset>
        <label v-if="chosen !== 'telemetry'" for="action-reason">
          {{ chosen === "issue" ? "Ceritakan kondisi paket" : "Catatan dan alasan" }}
        </label>
        <textarea
          v-if="chosen !== 'telemetry'"
          id="action-reason"
          v-model="form.reason"
          name="reason"
          required
          minlength="3"
          maxlength="2000"
          rows="4"
          :aria-invalid="!!fields.reason"
          :aria-describedby="error ? 'action-error' : undefined"
          placeholder="Jelaskan yang diperiksa, diputuskan, atau perlu dilakukan."
        />
        <div v-if="error" id="action-error" class="notice danger" role="alert">
          <p>{{ error }}</p>
          <ul v-if="Object.keys(fields).length" role="list">
            <li v-for="(messages, field) in fields" :key="field">
              {{ messages.join(" ") }}
            </li>
          </ul>
        </div>
        <p v-if="success" class="notice success" role="status">
          <CheckIcon />
          {{ success }}
        </p>
        <button
          v-if="chosen !== 'inspect' || inspectStep === 2"
          type="submit"
          class="button primary"
          :disabled="busy"
        >
          {{ active.title }}
          <span v-if="busy" class="spinner" aria-label="Menyimpan" />
          <ArrowRightIcon v-else />
        </button>
        <p class="small muted">Nama, waktu, dan alasan akan masuk riwayat kiriman.</p>
      </form>
    </template>
  </section>
</template>
