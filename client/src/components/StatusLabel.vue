<script setup lang="ts">
  import { labels, tone } from "../lib/presentation";
  import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ClockIcon,
    TruckIcon,
  } from "@heroicons/vue/16/solid";
  defineProps<{ status: string }>();
</script>
<template>
  <span class="status" :class="tone(status)">
    <component
      :is="
        tone(status) === 'danger'
          ? ExclamationTriangleIcon
          : tone(status) === 'success'
            ? CheckCircleIcon
            : tone(status) === 'warning'
              ? ClockIcon
              : TruckIcon
      "
      aria-hidden="true"
    />
    {{ labels[status] || status }}
  </span>
</template>
