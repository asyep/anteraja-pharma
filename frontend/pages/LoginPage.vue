<script setup lang="ts">
  import { reactive, ref, onMounted, onUnmounted } from "vue";
  import { useRouter, useRoute } from "vue-router";
  import {
    ArrowRightIcon,
    PauseIcon,
    PlayIcon,
    EyeIcon,
    EyeSlashIcon,
  } from "@heroicons/vue/16/solid";
  import BrandLogo from "../components/BrandLogo.vue";
  import { post, currentUser, loadSession } from "../lib/api";
  import type { User, Role } from "../types";
  const router = useRouter(),
    route = useRoute();
  const form = reactive({ email: "", password: "" });
  const busy = ref(false),
    error = ref(""),
    reveal = ref(false),
    selected = ref<Role>("admin");
  const slide = ref(0),
    paused = ref(false),
    hover = ref(false),
    focused = ref(false),
    reduced = ref(false);
  const slides = [
    {
      image: "sorting.webp",
      title: "Periksa sebelum berangkat.",
      copy: "Kemasan, segel, dan label diperiksa. Admin memastikan paket siap melanjutkan perjalanan.",
    },
    {
      image: "courier.webp",
      title: "Ada kendala? Jangan lanjut dulu.",
      copy: "Satria melaporkan kondisi paket. Admin memberi keputusan, bukan menebak dari kejauhan.",
    },
    {
      image: "handoff.webp",
      title: "Setiap keputusan punya jejak.",
      copy: "Penerima tahu kabar paketnya. Tim punya catatan yang bisa ditelusuri kembali.",
    },
  ];
  let timer: ReturnType<typeof setInterval>, media: MediaQueryList;
  const updateMotion = () => {
    reduced.value = media.matches;
  };
  onMounted(() => {
    media = window.matchMedia("(prefers-reduced-motion: reduce)");
    updateMotion();
    media.addEventListener("change", updateMotion);
    timer = setInterval(() => {
      if (!paused.value && !hover.value && !focused.value && !reduced.value)
        slide.value = (slide.value + 1) % 3;
    }, 4000);
  });
  onUnmounted(() => {
    clearInterval(timer);
    media?.removeEventListener("change", updateMotion);
  });
  function choose(role: Role) {
    selected.value = role;
    form.email = {
      admin: "admin@pharma.test",
      courier: "satria@pharma.test",
      buyer: "pembeli@pharma.test",
    }[role];
  }
  async function submit() {
    if (busy.value) return;
    busy.value = true;
    error.value = "";
    try {
      await loadSession();
      const result = await post<{ user: User }>("login", form);
      currentUser.value = result.user;
      const next =
        typeof route.query.next === "string" &&
        route.query.next.startsWith("/") &&
        !route.query.next.startsWith("//")
          ? route.query.next
          : "/";
      await router.push(next);
    } catch (e) {
      error.value = (e as Error).message;
    } finally {
      busy.value = false;
    }
  }
</script>
<template>
  <main id="main-content" class="auth-page">
    <section
      class="auth-story"
      aria-label="Tentang penanganan paket Pharma"
      aria-roledescription="carousel"
      @mouseenter="hover = true"
      @mouseleave="hover = false"
      @focusin="focused = true"
      @focusout="focused = false"
    >
      <img
        v-for="(s, i) in slides"
        :key="s.image"
        :src="'/images/' + s.image"
        alt=""
        width="1000"
        height="1200"
        class="auth-image"
        :class="{ visible: slide === i }"
        :fetchpriority="i === 0 ? 'high' : 'auto'"
      />
      <div class="auth-shade" />
      <div class="auth-story-brand">
        <BrandLogo />
        <p>Pengiriman dengan perhatian</p>
      </div>
      <div class="auth-story-copy">
        <p class="overline light">Dari hub sampai penerima</p>
        <Transition name="fade" mode="out-in">
          <section :key="slide">
            <h1>{{ slides[slide]!.title }}</h1>
            <p>{{ slides[slide]!.copy }}</p>
          </section>
        </Transition>
      </div>
      <div class="carousel-controls">
        <div class="carousel-indicators">
          <button
            v-for="(_, i) in slides"
            :key="i"
            type="button"
            :aria-label="'Tampilkan cerita ' + (i + 1)"
            :aria-pressed="slide === i"
            @click="slide = i"
          >
            <span />
          </button>
        </div>
        <button
          type="button"
          class="carousel-pause"
          :aria-label="paused ? 'Putar cerita otomatis' : 'Hentikan cerita otomatis'"
          @click="paused = !paused"
        >
          <component :is="paused ? PlayIcon : PauseIcon" />
        </button>
      </div>
    </section>
    <section class="auth-form">
      <BrandLogo />
      <div class="auth-form-content">
        <p class="overline">Selamat datang kembali</p>
        <h2>
          Masuk, lalu lanjutkan
          <br />
          yang perlu ditangani.
        </h2>
        <p class="muted">Satu alur untuk tim, Satria, dan penerima paket.</p>
        <fieldset class="role-picker">
          <legend>Pilih akun demo</legend>
          <button
            v-for="role in ['admin', 'courier', 'buyer'] as Role[]"
            :key="role"
            type="button"
            :aria-pressed="selected === role"
            @click="choose(role)"
          >
            {{ { admin: "Admin", courier: "Satria", buyer: "Pembeli" }[role] }}
          </button>
        </fieldset>
        <form :aria-busy="busy" @submit.prevent="submit">
          <p v-if="error" id="login-error" class="notice danger" role="alert">
            {{ error }}
          </p>
          <label for="email">Email</label>
          <input
            id="email"
            v-model="form.email"
            name="email"
            type="email"
            required
            autocomplete="username"
            :aria-describedby="error ? 'login-error' : undefined"
          />
          <label for="password">Kata sandi</label>
          <div class="password-control">
            <input
              id="password"
              v-model="form.password"
              name="password"
              :type="reveal ? 'text' : 'password'"
              required
              autocomplete="current-password"
              :aria-describedby="error ? 'login-error' : undefined"
            />
            <button
              type="button"
              class="icon-button"
              :aria-label="reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
              @click="reveal = !reveal"
            >
              <component :is="reveal ? EyeSlashIcon : EyeIcon" />
            </button>
          </div>
          <button class="button primary auth-submit" type="submit" :disabled="busy">
            Masuk ke Pharma
            <ArrowRightIcon />
            <span v-if="busy" class="spinner" aria-label="Sedang masuk" />
          </button>
        </form>
        <p class="auth-footnote">
          Akses sesuai peran akunmu. Data paket hanya terlihat oleh orang yang menangani atau
          menerimanya.
        </p>
      </div>
      <footer class="small muted">Prototype hackathon · Tidak untuk pengiriman nyata</footer>
    </section>
  </main>
</template>
