<script setup lang="ts">
  import { RouterLink } from "vue-router";
  import type { Shipment } from "../types";
  import StatusLabel from "./StatusLabel.vue";
  import { dateTime } from "../lib/presentation";
  defineProps<{ shipments: Shipment[] }>();
</script>
<template>
  <div
    class="table-scroll"
    tabindex="0"
    aria-label="Daftar pengiriman, geser untuk melihat semua kolom"
  >
    <table>
      <caption class="sr-only">Daftar kiriman Pharma</caption>
      <thead>
        <tr>
          <th scope="col">Kiriman</th>
          <th scope="col">Tujuan</th>
          <th scope="col">Pemeriksaan</th>
          <th scope="col">Tahap saat ini</th>
          <th scope="col">Jadwal</th>
          <th scope="col"><span class="sr-only">Detail</span></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in shipments" :key="s.id">
          <td>
            <RouterLink :to="'/kiriman/' + s.id" class="strong-link">
              {{ s.tracking_number }}
            </RouterLink>
            <p class="small muted">{{ s.package_count }} paket · {{ s.item_summary }}</p>
          </td>
          <td>
            <p class="weight">{{ s.recipient_name }}</p>
            <p class="small muted">{{ s.destination_city }}</p>
          </td>
          <td>
            <span :class="s.inspections?.[0]?.passed ? 'text-success' : 'muted'">
              {{ s.inspections?.[0]?.passed ? "3/3 lengkap" : "Perlu diperiksa" }}
            </span>
          </td>
          <td><StatusLabel :status="s.status" /></td>
          <td class="small muted">{{ dateTime(s.scheduled_at) }}</td>
          <td>
            <RouterLink
              :to="'/kiriman/' + s.id"
              class="table-action"
              :aria-label="'Lihat ' + s.tracking_number"
            >
              Lihat ↗
            </RouterLink>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
