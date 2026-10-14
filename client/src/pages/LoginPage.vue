<script setup lang="ts">
  import { reactive, ref, onMounted, onUnmounted } from "vue";
  import { useRouter, useRoute } from "vue-router";
  import {
    ArrowRightIcon,
    PauseIcon,
    PlayIcon,
    EyeIcon,
    EyeSlashIcon,
    ShieldCheckIcon,
    TruckIcon,
    UserIcon,
    LockClosedIcon,
  } from "@heroicons/vue/16/solid";
  import BrandLogo from "../components/BrandLogo.vue";
  import { post, currentUser, loadSession } from "../lib/api";
  import type { User, Role } from "../types";
  import "../login.css";
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
      image: "pharma-inspection.webp",
      title: "Periksa sebelum berangkat.",
      copy: "Kemasan, segel, dan label diperiksa. Admin memastikan paket siap melanjutkan perjalanan.",
    },
    {
      image: "pharma-delivery.webp",
      title: "Ada kendala? Jangan lanjut dulu.",
      copy: "Kurir mengirim pembaruan perjalanan. Admin menindaklanjuti penyimpangan yang terdeteksi.",
    },
    {
      image: "pharma-handover.webp",
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
      if (!document.hidden && !paused.value && !hover.value && !focused.value && !reduced.value)
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
      checker: "checker@pharma.test",
      courier: "satria@pharma.test",
      buyer: "vendor@pharma.test",
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
  <main id="main-content" class="pharma-access">
    <header class="access-header">
      <BrandLogo />
      <p>
        Pengiriman kesehatan
        <span>/</span>
        Ruang kerja Pharma
      </p>
    </header>
    <div class="access-layout">
      <section
        class="pharma-story"
        aria-label="Tentang penanganan paket Pharma"
        aria-roledescription="carousel"
        @mouseenter="hover = true"
        @mouseleave="hover = false"
        @focusin="focused = true"
        @focusout="focused = false"
      >
        <div class="story-photograph">
          <img
            v-for="(s, i) in slides"
            :key="s.image"
            :src="'/images/' + s.image"
            alt=""
            width="1440"
            height="960"
            class="auth-image"
            :class="{ visible: slide === i }"
            :fetchpriority="i === 0 ? 'high' : 'auto'"
          />
        </div>
        <div class="story-caption">
          <div class="story-caption-heading">
            <p>Dari pemeriksaan sampai penerimaan</p>
            <p class="story-number">
              0{{ slide + 1 }}
              <span>/ 03</span>
            </p>
          </div>
          <div class="story-copy-stack">
            <section
              v-for="(s, i) in slides"
              :key="s.image"
              :class="{ 'is-current': slide === i }"
              :aria-hidden="slide !== i"
            >
              <h2>{{ s.title }}</h2>
              <p>{{ s.copy }}</p>
            </section>
          </div>
          <div class="story-controls">
            <div class="story-indicators">
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
              class="story-pause"
              :aria-label="paused ? 'Putar cerita otomatis' : 'Hentikan cerita otomatis'"
              @click="paused = !paused"
            >
              <component :is="paused ? PlayIcon : PauseIcon" />
            </button>
          </div>
        </div>
      </section>
      <section class="pharma-signin" aria-labelledby="signin-title">
        <div class="signin-heading">
          <p class="overline">Akses operasional</p>
          <h1 id="signin-title">Masuk ke Pharma.</h1>
          <p>Periksa paket, lanjutkan tugas, dan ikuti kabar pengiriman di satu tempat.</p>
        </div>
        <fieldset class="demo-roles">
          <legend>Coba dengan akun demo</legend>
          <div class="demo-role-grid">
            <button
              v-for="role in ['admin', 'checker', 'courier', 'buyer'] as Role[]"
              :key="role"
              type="button"
              :aria-pressed="selected === role"
              @click="choose(role)"
            >
              <component
                :is="{
                  admin: ShieldCheckIcon,
                  checker: ShieldCheckIcon,
                  courier: TruckIcon,
                  buyer: UserIcon,
                }[role]"
              />
              {{
                {
                  admin: "Admin",
                  checker: "Checker",
                  courier: "Kurir",
                  buyer: "Vendor / Customer",
                }[role]
              }}
            </button>
          </div>
          <p class="demo-role-help">
            Pilihan ini hanya mengisi email. Gunakan kata sandi akun demo.
          </p>
        </fieldset>
        <form class="pharma-login-form" :aria-busy="busy" @submit.prevent="submit">
          <p v-if="error" id="login-error" class="notice danger" role="alert">
            {{ error }}
          </p>
          <div class="signin-field">
            <label for="email">Email</label>
            <input
              id="email"
              v-model="form.email"
              name="email"
              type="email"
              required
              autocomplete="username"
              placeholder="nama@contoh.com"
              :aria-describedby="error ? 'login-error' : undefined"
            />
          </div>
          <div class="signin-field">
            <label for="password">Kata sandi</label>
            <div class="password-control">
              <input
                id="password"
                v-model="form.password"
                name="password"
                :type="reveal ? 'text' : 'password'"
                required
                autocomplete="current-password"
                placeholder="Masukkan kata sandi"
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
          </div>
          <button class="button primary pharma-submit" type="submit" :disabled="busy">
            <span>Masuk ke Pharma</span>
            <span v-if="busy" class="spinner" aria-label="Sedang masuk" />
            <ArrowRightIcon v-else />
          </button>
        </form>
        <p class="signin-privacy">
          <LockClosedIcon />
          <span>
            Akses mengikuti peran akun. Informasi paket hanya terlihat oleh pihak yang menangani
            atau menerimanya.
          </span>
        </p>
      </section>
    </div>
    <footer class="access-footer">
      <p>
        Anteraja Pharma
        <span>·</span>
        Penanganan yang tercatat.
      </p>
      <p>Prototype hackathon · Data contoh</p>
    </footer>
  </main>
</template>
