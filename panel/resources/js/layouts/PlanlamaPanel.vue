<template>
  <div class="planlama-panel">
    <header class="planlama-panel__head">
      <div>
        <strong>Malzeme Planlama</strong>
        <span>İhtiyaç Listesi • Talep Dökümanları</span>
      </div>
      <div class="planlama-panel__actions">
        <div class="planlama-bell">
          <button class="planlama-bell__btn" @click="showNotif = !showNotif" title="Bildirimler">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.3V11a6 6 0 0 0-4-5.7V5a2 2 0 0 0-4 0v.3A6 6 0 0 0 6 11v3.3a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 0 1-6 0v-1m6 0H9"/></svg>
            <span v-if="unreadTotal > 0" class="planlama-bell__badge">{{ unreadTotal }}</span>
          </button>
          <div v-if="showNotif" class="planlama-bell__drop" @click.self="showNotif = false">
            <div class="planlama-bell__card">
              <div class="planlama-bell__title">Bildirimler <span v-if="unreadTotal">({{ unreadTotal }})</span></div>
              <div v-if="!planlamaItems.length" class="planlama-bell__empty">Yeni bildirim yok</div>
              <button v-for="n in planlamaItems" :key="n.id" class="planlama-bell__item" @click="goItem(n)">
                <span class="planlama-bell__dot" :style="{ background: n.color }"></span>
                <span class="planlama-bell__text"><strong>{{ n.title }}</strong><small>{{ n.sub }}</small></span>
              </button>
            </div>
          </div>
        </div>
        <button @click="showModuleModal = true">Modüller</button>
      </div>
    </header>
    <main class="planlama-panel__body">
      <nav class="planlama-panel__nav">
        <router-link to="/planlamapanel">Panel</router-link>
        <router-link to="/planlamapanel/talepler">İhtiyaç Listesi</router-link>
        <router-link v-if="(authStore.permissions || []).some(p => ['per-041-05-01','per-041-05-03','per-041-05-04'].includes(p))" to="/planlamapanel/talep-dokumanlari">Talep Dökümanları</router-link>
      </nav>
      <router-view />
    </main>
    <div v-if="showModuleModal" class="module-modal-overlay" @click.self="showModuleModal = false">
      <div class="module-modal-card">
        <h3>Modüller</h3>
        <div v-for="m in modules" :key="m.key">
          <button :disabled="m.disabled" @click="goModule(m)">{{ m.title }}</button>
        </div>
        <button @click="showModuleModal = false">Kapat</button>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
const PLAN_META = {
  'planlama-01': { label: 'Onaya Talep Geldi', color: '#0e7490' },
  'planlama-02': { label: 'Talep Reddedildi', color: '#dc2626' },
  'planlama-03': { label: 'Müdürlük Onayı', color: '#16a34a' },
};
function parseRequest(o) {
  let title = '', requestNo = '', mudur = '';
  try {
    JSON.parse(o.main_attr || '[]').forEach(d => {
      if (d.Key === 'title' && !title) title = d.Value;
      if (d.Key === 'request_no' && !requestNo) requestNo = d.Value;
      if (d.Key === 'firma_adi' && !mudur) mudur = d.Value;
    });
  } catch {}
  return { title: title || requestNo || o.qnid || '-', sub: mudur, qnid: o.qnid || o.id || '' };
}
export default {
  name: 'PlanlamaPanel',
  setup() { return { useNavigationStore }; },
  data() {
    return {
      authStore: useAuthStore(),
      navigationStore: useNavigationStore(),
      showModuleModal: false,
      showNotif: false,
      allModules: [
        { key: 'admin', title: 'Yönetim Paneli', path: '/coalpanel', color: '#154b91', perm: 'per-041-01' },
        { key: 'tedarik', title: 'Tedarik', path: '/tedarikpanel', color: '#FF5A1F', perm: 'per-041-02' },
        { key: 'planlama', title: 'Malzeme Planlama', path: '/planlamapanel', color: '#0e7490', perm: 'per-041-05' },
      ],
    };
  },
  mounted() {
    try { this.navigationStore.getNotifications(); } catch(e) {}
  },
  computed: {
    modules() {
      const perms = this.authStore.permissions || [];
      const hasAll = perms.includes('per-041') || perms.includes('per-00');
      return this.allModules.filter(m => hasAll || perms.includes(m.perm));
    },
    planlamaItems() {
      const n = this.navigationStore?.notifications || {};
      const out = [];
      const push = (arr, cat) => {
        if (!Array.isArray(arr)) return;
        arr.forEach(o => {
          const m = parseRequest(o);
          out.push({ id: cat + '-' + m.qnid, cat, title: (PLAN_META[cat]?.label || '') + ' — ' + m.title, sub: m.sub, qnid: m.qnid, color: PLAN_META[cat]?.color || '#0e7490' });
        });
      };
      push(n.planlamaSent, 'planlama-01');
      push(n.planlamaRejected, 'planlama-02');
      push(n.planlamaApproved, 'planlama-03');
      return out;
    },
    unreadTotal() { return this.planlamaItems.length; },
  },
  methods: {
    goModule(mod) {
      this.showModuleModal = false;
      if (!mod || !mod.path) return;
      this.$router.push(mod.path);
    },
    goItem(n) {
      this.showNotif = false;
      if (n.qnid) {
        this.navigationStore.markNotificationRead(n.cat, n.qnid).catch(() => {});
        this.$router.push({ name: 'PlanlamaFForm', params: { id: n.qnid } }).catch(() => {});
      }
    },
  },
};
</script>

<style scoped>
.planlama-panel { min-height: 100vh; background: #f2f2f3; }
.planlama-panel__head { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: #fff; border-bottom: 1px solid #e2e8f0; }
.planlama-panel__head span { display: block; font-size: 12px; color: #64748b; }
.planlama-panel__actions { display: flex; align-items: center; gap: 10px; }
.planlama-panel__body { padding: 20px; }
.planlama-panel__nav { display: flex; gap: 12px; margin-bottom: 16px; }
.planlama-panel__nav a { font-weight: 700; color: #0e7490; text-decoration: none; }
.planlama-panel__nav a.router-link-exact-active { text-decoration: underline; }
.planlama-bell { position: relative; }
.planlama-bell__btn { position: relative; width: 40px; height: 40px; border-radius: 999px; border: 1px solid #e2e8f0; background: #fff; color: #0e7490; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.planlama-bell__badge { position: absolute; top: -4px; right: -4px; min-width: 18px; height: 18px; border-radius: 999px; background: #dc2626; color: #fff; font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; padding: 0 4px; }
.planlama-bell__drop { position: fixed; inset: 0; z-index: 9990; }
.planlama-bell__card { position: absolute; top: 64px; right: 16px; width: 340px; max-height: 60vh; overflow-y: auto; background: #fff; border-radius: 14px; box-shadow: 0 16px 48px rgba(15,23,42,.18); border: 1px solid #e2e8f0; padding: 10px; }
.planlama-bell__title { font-size: 14px; font-weight: 800; color: #0f172a; padding: 6px 8px 10px; }
.planlama-bell__empty { font-size: 13px; color: #94a3b8; text-align: center; padding: 18px 0; }
.planlama-bell__item { display: flex; gap: 10px; align-items: flex-start; width: 100%; text-align: left; background: #fff; border: 1px solid #f1f5f9; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; cursor: pointer; }
.planlama-bell__item:hover { border-color: #99f6e4; background: #f0fdfa; }
.planlama-bell__dot { width: 10px; height: 10px; border-radius: 999px; margin-top: 4px; flex-shrink: 0; }
.planlama-bell__text { display: flex; flex-direction: column; gap: 2px; }
.planlama-bell__text strong { font-size: 13px; color: #0f172a; }
.planlama-bell__text small { font-size: 11.5px; color: #64748b; }
.module-modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.48); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.module-modal-card { background: #fff; border-radius: 12px; padding: 20px; min-width: 320px; }
</style>
