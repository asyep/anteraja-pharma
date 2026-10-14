<script setup lang="ts">
  import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
  import L from "leaflet";
  import "leaflet/dist/leaflet.css";
  import type { Shipment } from "../types";
  import { dateTime } from "../lib/presentation";
  const props = defineProps<{ shipment: Shipment }>();
  const element = ref<HTMLDivElement | null>(null);
  const tilesFailed = ref(false);
  let map: L.Map | null = null;
  let layer: L.LayerGroup;
  let tiles: L.TileLayer;
  let observer: ResizeObserver | null = null;
  let firstFit = true;
  const points = computed(() =>
    (props.shipment.tracking_points || []).filter(
      (p) => Number.isFinite(p.latitude) && Number.isFinite(p.longitude),
    ),
  );
  const last = computed(() => points.value.at(-1));
  const readings = computed(() =>
    points.value.filter((p) => p.temperature !== null && Number.isFinite(p.temperature)),
  );
  const range = computed(() => {
    const values = readings.value.map((p) => p.temperature!);
    if (props.shipment.temperature_min !== null)
      values.push(Number(props.shipment.temperature_min));
    if (props.shipment.temperature_max !== null)
      values.push(Number(props.shipment.temperature_max));
    return { min: Math.min(...values, 0) - 1, max: Math.max(...values, 10) + 1 };
  });
  const chart = computed(() =>
    readings.value
      .map((p, i) => [
        20 + (i * 560) / Math.max(readings.value.length - 1, 1),
        140 - ((p.temperature! - range.value.min) * 120) / (range.value.max - range.value.min),
      ])
      .map((p) => p.join(","))
      .join(" "),
  );
  const warning = computed(() => props.shipment.ai_evaluations?.at(-1)?.anomaly_details?.warning);
  function updateMap() {
    if (!map) return;
    layer.clearLayers();
    const coordinates: L.LatLngTuple[] = points.value.map((p) => [p.latitude, p.longitude]);
    if (!coordinates.length) return;
    L.polyline(coordinates, { color: "#00685c", weight: 4 }).addTo(layer);
    const p = last.value!;
    const popup = document.createElement("div");
    popup.textContent = `${props.shipment.tracking_number} · ${dateTime(p.recorded_at)}${p.simulated ? " · simulasi" : ""}`;
    L.circleMarker([p.latitude, p.longitude], {
      radius: 8,
      color: "#fff",
      weight: 3,
      fillColor: "#ed008c",
      fillOpacity: 1,
    })
      .bindPopup(popup)
      .addTo(layer);
    if (firstFit) {
      if (coordinates.length > 1)
        map.fitBounds(L.latLngBounds(coordinates), { padding: [28, 28], maxZoom: 15 });
      else map.setView(coordinates[0]!, 14);
      firstFit = false;
    }
  }
  function locate() {
    if (map && last.value) map.setView([last.value.latitude, last.value.longitude], 14);
  }
  onMounted(async () => {
    await nextTick();
    if (!element.value) return;
    map = L.map(element.value, { scrollWheelZoom: false }).setView([-6.2088, 106.8456], 11);
    tiles = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: "&copy; OpenStreetMap contributors",
      maxZoom: 19,
    }).addTo(map);
    tiles.on("tileerror", () => {
      tilesFailed.value = true;
    });
    layer = L.layerGroup().addTo(map);
    observer = new ResizeObserver(() => map?.invalidateSize());
    observer.observe(element.value);
    updateMap();
  });
  watch(points, updateMap);
  onUnmounted(() => {
    observer?.disconnect();
    map?.remove();
    map = null;
  });
</script>
<template>
  <section class="panel live-monitor" aria-labelledby="monitor-title">
    <header class="section-heading">
      <div>
        <p class="overline">Monitor perjalanan</p>
        <h2 id="monitor-title">Lokasi dan suhu paket</h2>
      </div>
      <button type="button" class="button secondary" :disabled="!last" @click="locate">
        Ke titik terakhir
      </button>
    </header>
    <p class="small muted">
      {{
        last
          ? "Pembaruan " + dateTime(last.recorded_at)
          : "Belum ada GPS. Peta menunjukkan area layanan, bukan posisi kurir."
      }}
    </p>
    <p v-if="shipment.is_demo" class="notice neutral small">
      Kiriman demonstrasi. Titik bertanda simulasi bukan GPS atau sensor nyata.
    </p>
    <div ref="element" class="shipment-map" aria-label="Peta jejak lokasi pengiriman" />
    <p v-if="tilesFailed" class="notice warning small" role="status">
      Latar peta belum tersedia. Koordinat dan jejak tetap tercatat.
      <button
        type="button"
        class="button secondary"
        @click="
          tilesFailed = false;
          tiles.redraw();
        "
      >
        Coba peta lagi
      </button>
    </p>
    <dl v-if="last" class="inline-details">
      <div>
        <dt>Koordinat terakhir</dt>
        <dd>{{ last.latitude.toFixed(6) }}, {{ last.longitude.toFixed(6) }}</dd>
      </div>
      <div>
        <dt>Suhu tercatat</dt>
        <dd>
          {{
            last.temperature === null ? "Tidak ada pembacaan" : last.temperature.toFixed(1) + "°C"
          }}
          · {{ last.simulated ? "simulasi" : "perangkat / input petugas" }}
        </dd>
      </div>
    </dl>
    <div v-if="readings.length" class="temperature-history">
      <h3>Riwayat suhu</h3>
      <svg
        viewBox="0 0 600 165"
        role="img"
        aria-label="Grafik suhu sesuai telemetry yang tersimpan"
      >
        <line x1="20" y1="140" x2="580" y2="140" stroke="#d0ddd8" />
        <polyline :points="chart" fill="none" stroke="#00685c" stroke-width="3" />
        <text x="20" y="160">{{ dateTime(readings[0]!.recorded_at) }}</text>
        <text x="580" y="160" text-anchor="end">{{ dateTime(readings.at(-1)!.recorded_at) }}</text>
      </svg>
      <p class="small muted">
        Min {{ Math.min(...readings.map((p) => p.temperature!)).toFixed(1) }}°C · Maks
        {{ Math.max(...readings.map((p) => p.temperature!)).toFixed(1) }}°C · batas paket
        {{ shipment.temperature_min ?? "—" }}–{{ shipment.temperature_max ?? "—" }}°C
      </p>
    </div>
    <p v-else class="small muted">
      Grafik muncul setelah ada pembacaan suhu. Aplikasi tidak menebak suhu kargo.
    </p>
    <p v-if="warning" class="notice warning" role="status">{{ warning }}</p>
  </section>
</template>
