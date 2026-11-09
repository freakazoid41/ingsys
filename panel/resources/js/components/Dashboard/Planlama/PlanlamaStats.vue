<template>
  <div class="tdk-stats">
    <div v-for="s in statList" :key="s.key" class="tdk-stat" :class="{ 'tdk-stat--accent': s.accent }" @click="go(s)">
      <div class="tdk-stat__icon" :style="{ background: s.bg, color: s.color }"><i :class="s.icon"></i></div>
      <div class="tdk-stat__body">
        <span class="tdk-stat__label">{{ s.label }}</span>
        <span class="tdk-stat__value" :style="{ color: s.valueColor || s.color }">
          <span v-if="loading" class="tdk-stat__loader"></span>
          <span v-else>{{ stats[s.key] ?? 0 }}</span>
        </span>
        <span v-if="s.sub" class="tdk-stat__sub">{{ s.sub }}</span>
      </div>
      <i class="tdk-stat__arrow ki-outline ki-arrow-right"></i>
    </div>
  </div>
</template>

<script>
import Plib from '@/lib/pickle';
import { useNavigationStore } from '@/stores/navigation';
export default {
  name: 'PlanlamaStats',
  data() {
    return {
      loading: true,
      stats: { totalTaleps: 0, awaiting: 0, approved: 0, stdApproved: 0, rejected: 0, totalIhtiyac: 0 },
      statList: [
        { key: 'totalTaleps', label: 'TOPLAM TALEP', icon: 'ki-outline ki-document', bg: '#fff7ed', color: '#FF5A1F', valueColor: '#FF5A1F', sub: 'Aktif', accent: true },
        { key: 'awaiting', label: 'ONAY BEKLİYOR', icon: 'ki-outline ki-time', bg: '#fef3c7', color: '#92400e', sub: 'Müdürlük' },
        { key: 'rejected', label: 'REDDEDİLEN', icon: 'ki-outline ki-cross-circle', bg: '#fee2e2', color: '#dc2626', sub: 'Revize' },
        { key: 'approved', label: 'MÜDÜR ONAYI', icon: 'ki-outline ki-check-circle', bg: '#dcfce7', color: '#0e7490', sub: 'Müdürlük' },
        { key: 'stdApproved', label: 'STD. ONAY', icon: 'ki-outline ki-shield-tick', bg: '#dcfce7', color: '#16a34a', sub: 'Standardizasyon' },
        { key: 'totalIhtiyac', label: 'İHTİYAÇ LİSTESİ', icon: 'ki-outline ki-parcel', bg: '#ede9fe', color: '#7c3aed', sub: 'Başlık' },
      ]
    };
  },
  mounted() { this.load(); },
  methods: {
    async load() {
      const plib = new Plib();
      try {
        const nav = useNavigationStore();
        try { await nav.getNotifications(); } catch {}
        const n = nav.notifications || {};
        if (Array.isArray(n.planlamaSent)) this.stats.awaiting = n.planlamaSent.length;
        if (Array.isArray(n.planlamaRejected)) this.stats.rejected = n.planlamaRejected.length;
        if (Array.isArray(n.planlamaApproved)) this.stats.approved = n.planlamaApproved.length;
        // totals via tableList (correct keys: filter + scale, read totalCount)
        try {
          const rsp = await plib.request({ url: '/api/v1/table/documents', method: 'POST', data: { tableReq: JSON.stringify({ filter: [{ key: 'form-type', type: '=', value: 'op-doc-requestf-form' }, { key: 'type', type: '=', value: 'op-doc-requestf' }], scale: { page: 1, limit: 1 } }) } }, null);
          const total = rsp?.totalCount || 0;
          if (total) this.stats.totalTaleps = total;
        } catch {}
        try {
          const rsp2 = await plib.request({ url: '/api/v1/table/documents', method: 'POST', data: { tableReq: JSON.stringify({ filter: [{ key: 'form-type', type: '=', value: 'op-doc-request-form' }, { key: 'type', type: '=', value: 'op-doc-request' }], scale: { page: 1, limit: 1 } }) } }, null);
          const total2 = rsp2?.totalCount || 0;
          if (total2) this.stats.totalIhtiyac = total2;
        } catch {}
        // std-approved count via transactions filter if available, else 0
        try {
          const rsp3 = await plib.request({ url: '/api/v1/table/documents', method: 'POST', data: { tableReq: JSON.stringify({ filter: [{ key: 'form-type', type: '=', value: 'op-doc-requestf-form' }, { key: 'type', type: '=', value: 'op-doc-requestf' }, { key: 'transactions', type: '=', value: 'tr-request-std-approved' }], scale: { page: 1, limit: 1 } }) } }, null);
          const total3 = rsp3?.totalCount || 0;
          if (total3) this.stats.stdApproved = total3;
        } catch {}
      } catch (e) { console.error('planlama-stats failed', e); }
      finally { this.loading = false; }
    },
    go(s) {
      const map = { totalTaleps: 'PlanlamaFList', awaiting: 'PlanlamaFList', rejected: 'PlanlamaFList', approved: 'PlanlamaFList', stdApproved: 'PlanlamaFList', totalIhtiyac: 'PlanlamaRList' };
      const r = map[s.key];
      if (!r) return;
      this.$router.push({ name: r }).catch(()=>{});
    }
  }
};
</script>

<style scoped>
.tdk-stats { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 1rem; margin-bottom: 1.5rem; width:100%; max-width:100%; box-sizing:border-box; }
.tdk-stat { background: #fff; border-radius: 18px; padding: 1.2rem 1.25rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.25s ease; border: 1px solid #fef3c7; position: relative; overflow: hidden; min-width:0; }
.tdk-stat::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: transparent; transition: background 0.25s; }
.tdk-stat--accent::before { background: linear-gradient(90deg, #FF5A1F, #fb923c); }
.tdk-stat:hover { transform: translateY(-4px); box-shadow: 0 14px 28px rgba(255,90,31,.12); border-color: #fed7aa; }
.tdk-stat:hover .tdk-stat__arrow { opacity: 1; transform: translateX(0); }
.tdk-stat__icon { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.tdk-stat__body { display: flex; flex-direction: column; gap: 0.15rem; flex: 1; min-width: 0; }
.tdk-stat__label { font-size: 0.68rem; font-weight: 800; color: #9a3412; letter-spacing: 0.06em; line-height: 1.1; }
.tdk-stat__value { font-size: 1.65rem; font-weight: 800; line-height: 1.1; }
.tdk-stat__sub { font-size: 0.72rem; color: #9ca3af; font-weight: 600; }
.tdk-stat__arrow { font-size: 16px; color: #f97316; opacity: 0; transform: translateX(-6px); transition: all 0.2s; flex-shrink: 0; }
.tdk-stat__loader { display: inline-block; width: 22px; height: 22px; border: 3px solid #e5e7eb; border-top-color: #FF5A1F; border-radius: 50%; animation: tdk-spin 0.6s linear infinite; }
@keyframes tdk-spin { to { transform: rotate(360deg); } }
@media (max-width: 1024px) { .tdk-stats { grid-template-columns: repeat(2, minmax(0,1fr)); } }
@media (max-width: 576px) { .tdk-stats { grid-template-columns: 1fr; } }
</style>
