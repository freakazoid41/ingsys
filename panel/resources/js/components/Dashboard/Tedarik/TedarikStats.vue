<template>
  <div class="tdk-stats">
    <div v-for="s in statList" :key="s.key" class="tdk-stat" @click="go">
      <div class="tdk-stat__icon" :style="{ background: s.bg, color: s.color }"><i :class="s.icon"></i></div>
      <div class="tdk-stat__body">
        <span class="tdk-stat__label" v-once>{{ s.label }}</span>
        <span class="tdk-stat__value" :style="{ color: s.color }">
          <span v-if="loading" class="tdk-stat__loader"></span>
          <span v-else>{{ stats[s.key] }}</span>
        </span>
      </div>
    </div>
  </div>
</template>

<script>
import Plib from '@/lib/pickle';
export default {
  name: 'TedarikStats',
  data() {
    return {
      loading: true,
      stats: { totalOrders: 0, pendingFiles: 0, rejectedFiles: 0, approvedOrders: 0 },
      statList: [
        { key: 'totalOrders', label: 'TOPLAM SİPARİŞ', icon: 'ki-outline ki-document', bg: '#fff7ed', color: '#FF5A1F' },
        { key: 'pendingFiles', label: 'BEKLEYEN DOSYA', icon: 'ki-outline ki-time', bg: '#fef3c7', color: '#d97706' },
        { key: 'rejectedFiles', label: 'REDDEDİLEN', icon: 'ki-outline ki-cross-circle', bg: '#fee2e2', color: '#dc2626' },
        { key: 'approvedOrders', label: 'ONAYLANAN', icon: 'ki-outline ki-check-circle', bg: '#dcfce7', color: '#16a34a' },
      ]
    };
  },
  mounted() { this.load(); },
  methods: {
    async load() {
      try {
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/tedarik-stats', method: 'GET' }, null);
        if (rsp && typeof rsp === 'object') Object.keys(this.stats).forEach(k => { if (rsp[k] !== undefined) this.stats[k] = rsp[k]; });
      } catch (e) { console.error('tedarik-stats failed', e); }
      finally { this.loading = false; }
    },
    go() { this.$router.push({ name: 'TedarikOrderList' }); }
  }
};
</script>

<style scoped>
.tdk-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
.tdk-stat { background: #fff; border-radius: 14px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.25s; border: 1px solid #f1f5f9; }
.tdk-stat:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.06); border-color: #FF5A1F22; }
.tdk-stat__icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.tdk-stat__body { display: flex; flex-direction: column; gap: 0.25rem; }
.tdk-stat__label { font-size: 0.7rem; font-weight: 700; color: #6b7280; letter-spacing: 0.06em; }
.tdk-stat__value { font-size: 1.75rem; font-weight: 800; line-height: 1.1; }
.tdk-stat__loader { display: inline-block; width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top-color: #FF5A1F; border-radius: 50%; animation: tdk-spin 0.6s linear infinite; }
@keyframes tdk-spin { to { transform: rotate(360deg); } }
@media (max-width: 1024px) { .tdk-stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 576px) { .tdk-stats { grid-template-columns: 1fr; } }
</style>
