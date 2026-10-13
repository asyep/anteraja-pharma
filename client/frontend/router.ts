import { createRouter, createWebHistory } from "vue-router";
import { currentUser, loadSession } from "./lib/api";
import LoginPage from "./pages/LoginPage.vue";
import MainLayout from "./components/MainLayout.vue";
import DashboardPage from "./pages/DashboardPage.vue";
import ShipmentsPage from "./pages/ShipmentsPage.vue";
import ShipmentDetail from "./pages/ShipmentDetail.vue";
import CreateShipment from "./pages/CreateShipment.vue";
import MessagesPage from "./pages/MessagesPage.vue";
import ReportsPage from "./pages/ReportsPage.vue";
import NotFound from "./pages/NotFound.vue";

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/masuk", component: LoginPage },
    {
      path: "/",
      component: MainLayout,
      children: [
        { path: "", component: DashboardPage },
        { path: "kiriman", component: ShipmentsPage },
        {
          path: "kiriman/baru",
          component: CreateShipment,
          meta: { admin: true },
        },
        { path: "kiriman/:id", component: ShipmentDetail },
        { path: "pesan", component: MessagesPage },
        { path: "laporan", component: ReportsPage, meta: { admin: true } },
        { path: ":pathMatch(.*)*", component: NotFound },
      ],
    },
  ],
  scrollBehavior(to, from, saved) {
    return saved || (to.path === from.path ? {} : { top: 0 });
  },
});
let initialized = false;
router.beforeEach(async (to) => {
  if (!initialized) {
    try {
      await loadSession();
      initialized = true;
    } catch {
      if (to.path !== "/masuk") return "/masuk";
    }
  }
  if (to.path !== "/masuk" && !currentUser.value)
    return { path: "/masuk", query: { next: to.fullPath } };
  if (to.path === "/masuk" && currentUser.value) return "/";
  if (to.meta.admin && currentUser.value?.role !== "admin") return "/";
});
router.afterEach((to) => {
  document.title = `Anteraja Pharma${to.path === "/masuk" ? " — Masuk" : " — Pengiriman"}`;
  requestAnimationFrame(() =>
    document.querySelector<HTMLElement>("#main-content")?.focus({ preventScroll: true }),
  );
});
