<script setup lang="ts">
  import { computed, reactive, ref, watch } from "vue";
  import type { Shipment, User } from "../types";
  import { currentUser, api, post, ApiError } from "../lib/api";
  import { issueLabels } from "../lib/presentation";
  import { CheckIcon, ExclamationTriangleIcon, ArrowRightIcon } from "@heroicons/vue/16/solid";
  const props = defineProps<{ shipment: Shipment }>();
  const emit = defineEmits<{ updated: [] }>();
  const chosen = ref(""),
    busy = ref(false),
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
    issue_type: "seal",
    decision: "return",
    ready: true,
    received_by: "",
  });
  const admin = computed(() => currentUser.value?.role === "admin");
  const buyer = computed(() => currentUser.value?.role === "buyer");
  const assignment = computed(() => props.shipment.assignments?.at(-1));
  const courier = computed(
    () =>
      currentUser.value?.role === "courier" &&
      assignment.value?.courier_id === currentUser.value.id &&
      assignment.value.status === "accepted",
  );
  const openIssue = computed(() => props.shipment.issues?.find((i) => i.status === "open"));
  const actions = computed(() => {
    const s = props.shipment,
      result: { key: string; title: string; copy: string }[] = [];
    const add = (key: string, title: string, copy: string) => result.push({ key, title, copy });
    if (
      (admin.value || currentUser.value?.id === s.inspector_id) &&
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
    if (admin.value && s.status === "approved")
      add("assign", "Tugaskan Satria", "Satria menerima instruksi dan perlu menjawab tugas ini.");
    if (
      currentUser.value?.role === "courier" &&
      assignment.value?.courier_id === currentUser.value.id &&
      s.status === "assigned"
    ) {
      add("accept", "Terima tugas", "Kamu siap mengambil dan mengantar sesuai instruksi.");
      add("reject", "Tolak tugas", "Admin akan mendapat pemberitahuan untuk memilih Satria lain.");
    }
    if (courier.value && s.status === "accepted")
      add("depart", "Mulai perjalanan", "Pastikan paket sesuai instruksi sebelum berangkat.");
    if (courier.value && s.status === "in_transit")
      add("arrive", "Sudah di lokasi", "Catat lokasi terakhir sebelum serah terima.");
    if (courier.value && s.status === "arrived")
      add(
        "deliver",
        "Catat penerimaan",
        "Catat nama orang yang menerima dan hasil pemeriksaan saat serah terima.",
      );
    if (
      (courier.value && ["accepted", "in_transit", "arrived"].includes(s.status)) ||
      (buyer.value && ["in_transit", "arrived"].includes(s.status))
    )
      add(
        "issue",
        "Laporkan kendala",
        "Pengiriman langsung ditahan. Tunggu keputusan Admin sebelum melanjutkan.",
      );
    if (admin.value && s.status === "held" && openIssue.value)
      add(
        "decide",
        "Putuskan tindak lanjut",
        "Alasan dan instruksi disimpan, lalu dikirim ke Satria dan penerima.",
      );
    if (courier.value && s.status === "returning")
      add(
        "return_complete",
        "Catat paket kembali",
        "Pastikan paket benar-benar diterima kembali di lokasi yang diinstruksikan.",
      );
    if (buyer.value && !["delivered", "returned", "returning"].includes(s.status))
      add(
        "ready",
        "Kesiapan penerimaan",
        "Beri tahu Satria apakah ada orang yang siap menerima paket.",
      );
    if (buyer.value && s.status === "delivered" && !s.events?.some((e) => e.type === "confirm"))
      add(
        "confirm",
        "Konfirmasi diterima",
        "Pastikan kamu atau petugas penerima sudah memeriksa paketnya.",
      );
    if (admin.value && s.status === "approved")
      result.sort((a, b) => Number(b.key === "assign") - Number(a.key === "assign"));
    if (admin.value && s.status === "pending_approval")
      result.sort((a, b) => Number(b.key === "approve") - Number(a.key === "approve"));
    return result;
  });
  const active = computed(() => actions.value.find((a) => a.key === chosen.value));
  watch(
    () => props.shipment.id,
    () => {
      chosen.value = "";
      success.value = "";
      error.value = "";
      form.reason = "";
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
    if (key === "assign" && !directory.value.length) void choose(key);
  });
  async function submit() {
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
      };
      if (action === "inspect")
        Object.assign(payload, {
          packaging: form.packaging,
          seal: form.seal,
          label: form.label,
          temperature: form.temperature === "" ? null : Number(form.temperature),
        });
      if (action === "assign") payload.courier_id = Number(form.courier_id);
      if (["depart", "arrive", "return_complete"].includes(action))
        payload.location = form.location;
      if (action === "issue") payload.issue_type = form.issue_type;
      if (action === "decide") payload.decision = form.decision;
      if (action === "ready") payload.ready = form.ready;
      if (action === "deliver") payload.received_by = form.received_by;
      await post<Shipment>(`shipments/${props.shipment.id}/actions/${action}`, payload);
      success.value = "Pembaruan tersimpan. Riwayat dan notifikasi sudah disiapkan.";
      form.reason = "";
      emit("updated");
    } catch (e) {
      error.value = (e as Error).message;
      if (e instanceof ApiError) fields.value = e.errors;
    } finally {
      busy.value = false;
    }
  }
</script>
<template>
  <section id="actions" class="action-panel" aria-labelledby="action-heading">
    <p class="overline">Langkah berikutnya</p>
    <h2 id="action-heading">
      {{
        admin
          ? "Keputusanmu menjaga paket."
          : buyer
            ? "Bantu proses penerimaan."
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
        </fieldset>
        <template v-if="chosen === 'assign'">
          <label for="courier">Pilih Satria</label>
          <select id="courier" v-model="form.courier_id" name="courier_id" required>
            <option value="">Pilih petugas</option>
            <option
              v-for="u in directory.filter((u) => u.role === 'courier')"
              :key="u.id"
              :value="u.id"
            >
              {{ u.name }}
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
          <label for="decision">Tindakan untuk Satria</label>
          <select id="decision" v-model="form.decision" name="decision">
            <option value="return">Kembalikan paket (retur)</option>
            <option value="recheck">Periksa dan minta persetujuan ulang</option>
            <option
              v-if="
                openIssue && ['recipient_unavailable', 'address', 'other'].includes(openIssue.type)
              "
              value="resume"
            >
              Lanjutkan dengan instruksi Admin
            </option>
          </select>
          <p class="notice warning small">
            {{
              form.decision === "return"
                ? "Satria akan diminta mengembalikan paket, bukan menyerahkannya ke penerima."
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
        <label for="action-reason">
          {{ chosen === "issue" ? "Ceritakan kondisi paket" : "Catatan dan alasan" }}
        </label>
        <textarea
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
        <button type="submit" class="button primary" :disabled="busy">
          {{ active.title }}
          <span v-if="busy" class="spinner" aria-label="Menyimpan" />
          <ArrowRightIcon v-else />
        </button>
        <p class="small muted">Nama, waktu, dan alasan akan masuk riwayat kiriman.</p>
      </form>
    </template>
  </section>
</template>
