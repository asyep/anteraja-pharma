<script setup lang="ts">
  import { computed, watch, onMounted, onUnmounted } from "vue";
  import { useRoute, RouterLink } from "vue-router";
  import {
    ArrowLeftIcon,
    ArrowPathIcon,
    ShieldCheckIcon,
    ExclamationTriangleIcon,
    CheckIcon,
    ClockIcon,
  } from "@heroicons/vue/16/solid";
  import { useResource } from "../composables/useResource";
  import type { Shipment } from "../types";
  import { dateTime, issueLabels, nextStep } from "../lib/presentation";
  import StatusLabel from "../components/StatusLabel.vue";
  import ResourceState from "../components/ResourceState.vue";
  import ActionPanel from "../components/ActionPanel.vue";
  import ShipmentMonitor from "../components/ShipmentMonitor.vue";
  import { currentUser } from "../lib/api";
  const route = useRoute();
  const { data, loading, error, load } = useResource<Shipment>();
  function reload() {
    load("shipments/" + route.params.id);
  }
  watch(
    () => route.params.id,
    () => {
      data.value = null;
      reload();
    },
    { immediate: true },
  );
  let poll: ReturnType<typeof setInterval>;
  const refresh = () => load("shipments/" + route.params.id, { silent: true });
  onMounted(() => {
    poll = setInterval(() => {
      if (
        !document.hidden &&
        navigator.onLine &&
        data.value &&
        !data.value.closed_at &&
        !loading.value
      )
        void refresh();
    }, 5000);
    window.addEventListener("pharma:refresh", refresh);
  });
  onUnmounted(() => {
    clearInterval(poll);
    window.removeEventListener("pharma:refresh", refresh);
  });
  const lastInspection = computed(() => data.value?.inspections?.at(-1));
  const openIssue = computed(() => data.value?.issues?.find((i) => i.status === "open"));
  const latestEvaluation = computed(() => data.value?.ai_evaluations?.at(-1));
  function printReport() {
    window.print();
  }
</script>
<template>
  <nav class="detail-nav" aria-label="Navigasi kiriman">
    <RouterLink to="/kiriman" class="text-link">
      <ArrowLeftIcon />
      Semua kiriman
    </RouterLink>
    <button type="button" class="button secondary" :disabled="loading" @click="reload">
      <ArrowPathIcon />
      Muat ulang
    </button>
  </nav>
  <ResourceState :loading="loading" :error="error" @retry="reload" />
  <template v-if="data">
    <section v-if="data.status === 'held'" class="notice prominent danger">
      <ExclamationTriangleIcon />
      <div>
        <h2>Paket ditahan. Jangan lanjutkan dulu.</h2>
        <p>
          {{
            openIssue ? issueLabels[openIssue.type] + ": " + openIssue.description : nextStep(data)
          }}
        </p>
      </div>
      <a class="button secondary" href="#actions">Lihat tindak lanjut</a>
    </section>
    <section class="shipment-hero">
      <div>
        <p class="overline">Pengiriman Pharma</p>
        <h1>{{ data.tracking_number }}</h1>
        <p class="route-text">
          {{ data.origin_city }}
          <span aria-label="menuju">→</span>
          {{ data.destination_city }}
        </p>
        <StatusLabel :status="data.status" />
        <p class="hero-next">{{ nextStep(data) }}</p>
      </div>
      <dl>
        <div>
          <dt>Jadwal tiba</dt>
          <dd>{{ dateTime(data.scheduled_at) }}</dd>
        </div>
        <div>
          <dt>Lokasi terakhir</dt>
          <dd>{{ data.last_location || "Belum tersedia untuk akun ini" }}</dd>
        </div>
        <div>
          <dt>Penerima</dt>
          <dd>{{ data.recipient_name }}</dd>
        </div>
        <div>
          <dt>Jumlah paket</dt>
          <dd>{{ data.package_count }} paket</dd>
        </div>
      </dl>
    </section>
    <div class="detail-grid">
      <div class="detail-primary">
        <ShipmentMonitor v-if="currentUser?.role !== 'checker'" :shipment="data" />
        <section class="panel instructions">
          <header class="section-heading">
            <h2>
              <ShieldCheckIcon />
              Instruksi penanganan
            </h2>
            <p class="small muted">Baca sebelum mengambil paket</p>
          </header>
          <p>{{ data.handling_instructions }}</p>
          <p v-if="data.temperature_profile === 'controlled'" class="notice neutral small">
            Rentang suhu khusus paket: {{ data.temperature_min }}–{{ data.temperature_max }}°C. Suhu
            dicatat manual oleh petugas.
          </p>
          <dl class="inline-details">
            <div>
              <dt>Isi paket</dt>
              <dd>{{ data.item_summary }}</dd>
            </div>
            <div>
              <dt>Alamat tujuan</dt>
              <dd>{{ data.address }}</dd>
            </div>
            <div>
              <dt>Pengirim</dt>
              <dd>{{ data.sender_name }}</dd>
            </div>
            <div>
              <dt>Kesiapan penerima</dt>
              <dd>
                {{
                  data.recipient_ready === null
                    ? "Belum dikonfirmasi"
                    : data.recipient_ready
                      ? "Siap menerima"
                      : "Belum siap menerima"
                }}
              </dd>
            </div>
          </dl>
        </section>
        <section class="panel">
          <header class="section-heading">
            <h2>Pemeriksaan sebelum berangkat</h2>
            <span :class="lastInspection?.passed ? 'text-success' : 'muted'">
              {{ lastInspection?.passed ? "Lengkap" : "Belum lengkap" }}
            </span>
          </header>
          <ul class="inspection-summary" role="list">
            <li
              v-for="(label, key) in {
                packaging: 'Kemasan sesuai',
                seal: 'Segel utuh',
                label: 'Label sesuai',
              }"
              :key="key"
            >
              <component
                :is="
                  lastInspection?.[key as 'packaging' | 'seal' | 'label'] ? CheckIcon : ClockIcon
                "
                :class="
                  lastInspection?.[key as 'packaging' | 'seal' | 'label'] ? 'text-success' : 'muted'
                "
              />
              {{ label }}
            </li>
          </ul>
          <p class="small muted">
            {{
              lastInspection
                ? "Diperiksa oleh " +
                  (lastInspection.user?.name || "Checker") +
                  " · " +
                  dateTime(lastInspection.created_at)
                : "Petugas belum menyimpan hasil pemeriksaan."
            }}
          </p>
          <p v-if="lastInspection" class="small">{{ lastInspection.note }}</p>
          <a
            v-if="lastInspection?.photo_proof_url"
            :href="lastInspection.photo_proof_url"
            target="_blank"
            rel="noreferrer"
            class="proof-preview"
          >
            <img
              :src="lastInspection.photo_proof_url"
              alt="Foto bukti pemeriksaan paket"
              width="240"
              height="160"
              loading="lazy"
            />
          </a>
          <p v-if="data.approvals?.length" class="notice success small">
            Keputusan terakhir:
            {{ data.approvals.at(-1)?.decision === "approved" ? "disetujui" : "ditahan" }}
            oleh {{ data.approvals.at(-1)?.user?.name }}.
            {{ data.approvals.at(-1)?.reason }}
          </p>
        </section>
        <section v-if="data.tracking_points?.length" class="panel">
          <header class="section-heading">
            <div>
              <p class="overline">Pelacakan GPS</p>
              <h2>Jejak perjalanan kurir</h2>
            </div>
            <p class="small muted">{{ data.tracking_points.length }} titik tercatat</p>
          </header>
          <p class="weight">
            Lokasi terakhir:
            {{ data.tracking_points.at(-1)?.latitude.toFixed(6) }},
            {{ data.tracking_points.at(-1)?.longitude.toFixed(6) }}
          </p>
          <p class="small muted">
            Pembaruan terbaru {{ dateTime(data.tracking_points.at(-1)?.recorded_at) }}
            <template v-if="data.tracking_points.at(-1)?.speed_kmh !== null">
              · {{ data.tracking_points.at(-1)?.speed_kmh?.toFixed(1) }} km/j
            </template>
          </p>
          <div
            v-if="latestEvaluation"
            class="notice telemetry-evaluation"
            :class="latestEvaluation.condition_result ? 'success' : 'danger'"
            role="status"
          >
            <h3>Evaluasi otomatis: {{ latestEvaluation.final_ai_status }}</h3>
            <p v-if="latestEvaluation.anomaly_details?.summary">
              {{ latestEvaluation.anomaly_details.summary }}
            </p>
            <p v-if="latestEvaluation.anomaly_details?.assistant_summary" class="small">
              Ringkasan AI opsional: {{ latestEvaluation.anomaly_details.assistant_summary }}
            </p>
            <p
              v-if="latestEvaluation.anomaly_details?.assistant_status === 'unavailable'"
              class="small"
            >
              Ringkasan AI belum tersedia. Alasan dan keputusan berbasis data tetap berlaku.
            </p>
            <p v-if="latestEvaluation.anomaly_details?.analysis_warning" class="small">
              {{ latestEvaluation.anomaly_details.analysis_warning }}
            </p>
            <p
              v-else-if="latestEvaluation.anomaly_details?.analysis_source === 'rules'"
              class="small"
            >
              Keputusan memakai aturan keselamatan. Ringkasan AI bersifat opsional dan tidak
              mengubah HOLD.
            </p>
          </div>
          <ol class="timeline" role="list">
            <li v-for="point in [...data.tracking_points].reverse().slice(0, 8)" :key="point.id">
              <span class="timeline-dot" aria-hidden="true" />
              <article>
                <p class="small muted">{{ dateTime(point.recorded_at) }}</p>
                <p>
                  {{ point.latitude.toFixed(6) }}, {{ point.longitude.toFixed(6) }}
                  <template v-if="point.temperature !== null">· {{ point.temperature }}°C</template>
                </p>
              </article>
            </li>
          </ol>
        </section>
        <section v-if="data.final_report" class="panel final-report">
          <header class="section-heading">
            <div>
              <p class="overline">Reportment · Laporan akhir</p>
              <h2>
                {{
                  data.final_report.final_status === "COMPLETED_SAFE"
                    ? "Selesai · Aman"
                    : "Selesai · Ada penyimpangan"
                }}
              </h2>
            </div>
            <button type="button" class="button secondary print-report" @click="printReport">
              Cetak / simpan sebagai PDF
            </button>
            <a
              :href="data.final_report.download_url || '/api/shipments/' + data.id + '/report.pdf'"
              class="button secondary"
            >
              Unduh PDF laporan
            </a>
          </header>
          <p class="small muted">
            ID {{ data.final_report.report_id }} · {{ dateTime(data.final_report.completed_at) }}
          </p>
          <p>
            Laporan memuat pemeriksaan, GPS, evaluasi, kendala, dan alasan Admin. Tersedia di
            aplikasi untuk {{ data.final_report.delivered_to.join(", ") }}. Status pengiriman
            Telegram dapat dilihat di Notifikasi.
          </p>
          <dl class="inline-details">
            <div>
              <dt>Resi / tujuan</dt>
              <dd>
                {{ data.final_report.snapshot.tracking_number }} ·
                {{ data.final_report.snapshot.destination_city }}
              </dd>
            </div>
            <div>
              <dt>Pengirim / penerima</dt>
              <dd>
                {{ data.final_report.snapshot.sender_name }} ·
                {{ data.final_report.snapshot.recipient_name }}
              </dd>
            </div>
          </dl>
          <h3>Pemeriksaan awal</h3>
          <ul role="list">
            <li v-for="(exam, i) in data.final_report.snapshot.inspections" :key="i">
              {{ exam.passed ? "Lulus" : "Perlu tindak lanjut" }} · kemasan
              {{ exam.packaging ? "sesuai" : "bermasalah" }}, segel
              {{ exam.seal ? "utuh" : "bermasalah" }}, label
              {{ exam.label ? "sesuai" : "bermasalah" }} · {{ dateTime(exam.created_at) }}
              <template v-if="exam.note">· {{ exam.note }}</template>
              <template v-if="exam.photo_proof_url">
                ·
                <a :href="exam.photo_proof_url" target="_blank" rel="noreferrer">
                  Lihat foto bukti
                </a>
              </template>
            </li>
          </ul>
          <h3>Evaluasi otomatis</h3>
          <ul role="list">
            <li
              v-for="evaluation in data.final_report.snapshot.ai_evaluations"
              :key="evaluation.evaluation_id"
            >
              {{ evaluation.final_ai_status }} ·
              {{ evaluation.incident_type || "tanpa penyimpangan" }}
              <template v-if="evaluation.anomaly_details?.summary">
                · {{ evaluation.anomaly_details.summary }}
              </template>
              · {{ dateTime(evaluation.evaluation_time) }}
            </li>
            <li v-if="!data.final_report.snapshot.ai_evaluations.length">
              Belum ada penyimpangan yang terekam otomatis.
            </li>
          </ul>
          <h3>Riwayat lokasi GPS</h3>
          <ul role="list">
            <li v-for="point in data.final_report.snapshot.tracking_points" :key="point.id">
              {{ point.latitude }}, {{ point.longitude }}
              <template v-if="point.temperature !== null">· {{ point.temperature }}°C</template>
              · {{ dateTime(point.recorded_at) }}
            </li>
            <li v-if="!data.final_report.snapshot.tracking_points.length">
              Tidak ada titik GPS tercatat.
            </li>
          </ul>
          <h3>Jejak audit</h3>
          <ul role="list">
            <li v-for="(event, i) in data.final_report.snapshot.events" :key="i">
              {{ event.title }} · {{ event.actor_name }} · {{ dateTime(event.created_at) }}
              <template v-if="event.reason">· {{ event.reason }}</template>
            </li>
          </ul>
          <p class="small muted">
            Unduhan dibuat server dan dilindungi hak akses. QR memverifikasi digest dokumen.
            Kepatuhan arsip PDF/A penuh memerlukan validasi sebelum penggunaan regulasi.
          </p>
        </section>
        <section class="panel timeline-section">
          <header class="section-heading">
            <div>
              <p class="overline">Jejak pengiriman</p>
              <h2>Apa yang sudah terjadi?</h2>
            </div>
            <p class="small muted">{{ data.events?.length || 0 }} kegiatan tercatat</p>
          </header>
          <ol class="timeline" role="list">
            <li
              v-for="(event, i) in [...(data.events || [])].reverse()"
              :key="event.id"
              :class="{ latest: i === 0 }"
            >
              <span class="timeline-dot" aria-hidden="true" />
              <article>
                <p class="small muted">{{ dateTime(event.created_at) }} · {{ event.actor_name }}</p>
                <h3>{{ event.title }}</h3>
                <p>{{ event.reason }}</p>
              </article>
            </li>
          </ol>
        </section>
        <section v-if="data.issues?.length" class="panel">
          <header class="section-heading"><h2>Kendala & keputusan</h2></header>
          <article v-for="issue in data.issues" :key="issue.id" class="history-item">
            <p class="overline">
              {{ issue.status === "open" ? "Menunggu Admin" : "Sudah diputuskan" }}
            </p>
            <h3>{{ issueLabels[issue.type] }}</h3>
            <p>{{ issue.description }}</p>
            <p class="small muted">{{ issue.user?.name }} · {{ dateTime(issue.created_at) }}</p>
            <div v-for="decision in issue.decisions" :key="decision.id" class="decision-record">
              <p class="weight">
                {{
                  {
                    return: "Retur",
                    recheck: "Periksa ulang",
                    resume: "Lanjutkan pengiriman",
                  }[decision.action as "return" | "recheck" | "resume"]
                }}
                · {{ decision.user?.name }}
              </p>
              <p>{{ decision.reason }}</p>
              <p class="small muted">{{ dateTime(decision.created_at) }}</p>
            </div>
          </article>
        </section>
        <details class="panel">
          <summary>Semua hasil pemeriksaan & penugasan</summary>
          <section v-for="inspection in data.inspections" :key="inspection.id" class="history-item">
            <h3>
              {{ inspection.passed ? "Pemeriksaan lengkap" : "Pemeriksaan belum lengkap" }}
            </h3>
            <p>
              Kemasan: {{ inspection.packaging ? "sesuai" : "belum sesuai" }} · Segel:
              {{ inspection.seal ? "utuh" : "belum sesuai" }} · Label:
              {{ inspection.label ? "sesuai" : "belum sesuai" }}
            </p>
            <p>{{ inspection.note }}</p>
            <p class="small muted">
              {{ inspection.user?.name }} ·
              {{ dateTime(inspection.created_at) }}
            </p>
          </section>
          <article v-for="assignment in data.assignments" :key="assignment.id" class="history-item">
            <h3>{{ assignment.courier?.name }}</h3>
            <p>
              {{
                {
                  pending: "Menunggu jawaban",
                  accepted: "Tugas diterima",
                  rejected: "Tugas ditolak",
                  cancelled: "Penugasan dibatalkan",
                }[assignment.status as "pending" | "accepted" | "rejected" | "cancelled"]
              }}
            </p>
            <p class="small muted">
              {{ assignment.reason }} · {{ dateTime(assignment.created_at) }}
            </p>
          </article>
        </details>
      </div>
      <ActionPanel :shipment="data" @updated="reload" />
    </div>
  </template>
</template>
