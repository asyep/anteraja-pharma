<script setup lang="ts">
  import { onMounted, ref } from "vue";
  import { RouterLink } from "vue-router";
  import { ArrowPathIcon, ChatBubbleLeftRightIcon } from "@heroicons/vue/16/solid";
  import { useResource } from "../composables/useResource";
  import { currentUser, post } from "../lib/api";
  import { dateTime } from "../lib/presentation";
  import type { OutboundMessage, Paginated } from "../types";
  import ResourceState from "../components/ResourceState.vue";
  const { data, loading, error, load } = useResource<Paginated<OutboundMessage>>();
  const chatId = ref(""),
    notice = ref(""),
    saving = ref(false);
  const statuses: Record<string, string> = {
    pending: "Menunggu pengiriman",
    processing: "Sedang dikirim",
    sent: "Terkirim ke Telegram",
    unconfigured: "Telegram belum dipasang",
    retry: "Akan dicoba kembali",
    failed: "Pengiriman belum berhasil",
    demo: "Riwayat contoh, tidak dikirim",
  };
  const audience: Record<string, string> = {
    admin: "Admin",
    courier: "Satria",
    buyer: "Pembeli",
    sender: "Pengirim",
  };
  const reload = (page = 1) => load("messages?page=" + page);
  onMounted(() => reload());
  async function save() {
    saving.value = true;
    notice.value = "";
    try {
      await post("settings", { telegram_chat_id: chatId.value || null });
      notice.value = "Tujuan notifikasi disimpan. Pesan berikutnya memakai akun Telegram ini.";
    } catch (e) {
      notice.value = (e as Error).message;
    } finally {
      saving.value = false;
    }
  }
  async function retry(id: number) {
    try {
      await post("messages/" + id + "/retry", {});
      reload(data.value?.current_page);
    } catch (e) {
      notice.value = (e as Error).message;
    }
  }
</script>
<template>
  <section class="page-heading">
    <div>
      <p class="overline">Kabar yang bisa ditelusuri</p>
      <h1>Notifikasi, bukan sekadar terkirim.</h1>
      <p class="muted">
        Lihat kapan pesan dibuat, siapa penerimanya, dan apakah Telegram sudah menerima pesannya.
      </p>
    </div>
    <button type="button" class="button secondary" @click="reload()">
      <ArrowPathIcon />
      Muat ulang
    </button>
  </section>
  <details class="panel notification-settings">
    <summary>Hubungkan Telegram untuk akunmu</summary>
    <form class="settings-form" @submit.prevent="save">
      <div>
        <label for="chat-id">Chat ID Telegram</label>
        <input
          id="chat-id"
          v-model="chatId"
          name="telegram_chat_id"
          inputmode="numeric"
          pattern="-?[0-9]+"
          placeholder="Chat ID dari akun uji"
        />
        <p class="small muted">
          Bot harus sudah menerima /start dari akunmu. Token bot dipasang oleh pengelola di server.
        </p>
      </div>
      <button type="submit" class="button secondary" :disabled="saving">Simpan tujuan</button>
    </form>
  </details>
  <p v-if="notice" class="notice neutral" role="status">{{ notice }}</p>
  <ResourceState
    :loading="loading"
    :error="error"
    label="Memuat riwayat notifikasi…"
    @retry="reload()"
  />
  <section v-if="data && !loading">
    <div v-if="!data.data.length" class="empty-state">
      <ChatBubbleLeftRightIcon />
      <h2>Belum ada kabar baru.</h2>
      <p>Notifikasi muncul saat kiriman berubah atau ada tugas yang perlu ditangani.</p>
      <RouterLink to="/kiriman" class="button secondary">Lihat pengiriman</RouterLink>
    </div>
    <article v-for="m in data.data" :key="m.id" class="message-row">
      <header>
        <p class="overline">Untuk {{ audience[m.audience] }} · {{ dateTime(m.created_at) }}</p>
        <p
          class="message-state"
          :class="
            m.status === 'sent' ? 'text-success' : m.status === 'failed' ? 'text-danger' : 'muted'
          "
        >
          {{ statuses[m.status] || m.status }}
        </p>
      </header>
      <h2>{{ m.subject }}</h2>
      <p class="message-body">{{ m.body }}</p>
      <p v-if="m.last_error" class="small muted">{{ m.last_error }}</p>
      <footer>
        <RouterLink v-if="m.shipment_id" :to="'/kiriman/' + m.shipment_id" class="text-link">
          Buka kiriman ↗
        </RouterLink>
        <p class="small muted">
          {{
            m.sent_at
              ? "Diterima Telegram " + dateTime(m.sent_at) + " · ID " + m.provider_message_id
              : "Belum ada konfirmasi pengiriman"
          }}
          · {{ m.attempts }} percobaan
        </p>
        <button
          v-if="
            currentUser?.role === 'admin' && ['failed', 'retry', 'unconfigured'].includes(m.status)
          "
          type="button"
          class="button secondary"
          @click="retry(m.id)"
        >
          Jadwalkan ulang
        </button>
      </footer>
    </article>
    <nav v-if="data.last_page > 1" class="pagination" aria-label="Halaman riwayat pesan">
      <button
        type="button"
        class="button secondary"
        :disabled="data.current_page === 1"
        @click="reload(data.current_page - 1)"
      >
        Sebelumnya
      </button>
      <p>Halaman {{ data.current_page }} dari {{ data.last_page }}</p>
      <button
        type="button"
        class="button secondary"
        :disabled="data.current_page === data.last_page"
        @click="reload(data.current_page + 1)"
      >
        Berikutnya
      </button>
    </nav>
  </section>
</template>
