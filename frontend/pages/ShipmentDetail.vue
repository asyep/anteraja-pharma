<script setup lang="ts">
  import { computed, watch } from "vue";
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
  const route = useRoute();
  const { data, loading, error, load } = useResource<Shipment>();
  function reload() {
    load("shipments/" + route.params.id);
  }
  watch(() => route.params.id, reload, { immediate: true });
  const lastInspection = computed(() => data.value?.inspections?.at(-1));
  const openIssue = computed(() => data.value?.issues?.find((i) => i.status === "open"));
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
  <template v-if="data && !loading">
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
          <dd>{{ data.last_location }}</dd>
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
                  lastInspection.user?.name +
                  " · " +
                  dateTime(lastInspection.created_at)
                : "Petugas belum menyimpan hasil pemeriksaan."
            }}
          </p>
          <p v-if="lastInspection" class="small">{{ lastInspection.note }}</p>
          <p v-if="data.approvals?.length" class="notice success small">
            Keputusan terakhir:
            {{ data.approvals.at(-1)?.decision === "approved" ? "disetujui" : "ditahan" }}
            oleh {{ data.approvals.at(-1)?.user?.name }}.
            {{ data.approvals.at(-1)?.reason }}
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
