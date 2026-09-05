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
export default {
  name: 'TedarikStats',
  data() {
    return {
      loading: true,
      stats: { totalOrders: 0, pendingFiles: 0, rejectedFiles: 0, approvedOrders: 0, totalItems: 0, todayOrders: 0 },
      statList: [
        { key: 'totalOrders', label: 'TOPLAM SİPARİŞ', icon: 'ki-outline ki-document', bg: '#fff7ed', color: '#FF5A1F', valueColor: '#FF5A1F', sub: 'Aktif', accent: true },
        { key: 'pendingFiles', label: 'KONTROL BEKLİYOR', icon: 'ki-outline ki-time', bg: '#fef3c7', color: '#92400e', sub: 'İnceleniyor' },
        { key: 'rejectedFiles', label: 'REDDEDİLEN', icon: 'ki-outline ki-cross-circle', bg: '#fee2e2', color: '#dc2626', sub: 'Müdahale' },
        { key: 'approvedOrders', label: 'ONAYLANAN', icon: 'ki-outline ki-check-circle', bg: '#dcfce7', color: '#16a34a', sub: 'Kalite' },
        { key: 'totalItems', label: 'TOPLAM KALEM', icon: 'ki-outline ki-parcel', bg: '#ede9fe', color: '#7c3aed', sub: 'Ürün' },
        { key: 'todayOrders', label: 'BUGÜN', icon: 'ki-outline ki-calendar', bg: '#e0f2fe', color: '#0369a1', sub: 'Yeni' },
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
    go(s) {
      const orderStatusMap = {
        pendingFiles: 'doc_trans_order_transfer_sent',
        rejectedFiles: 'doc_trans_order_files_rejected',
        approvedOrders: 'doc_trans_order_approved',
      };
      const map = { totalOrders: 'TedarikOrderList', pendingFiles: 'TedarikOrderList', rejectedFiles: 'TedarikOrderList', approvedOrders: 'TedarikOrderList', totalItems: 'TedarikOrderList', todayOrders: 'TedarikOrderList' };
      const r = map[s.key];
      if (!r) return;
      if(orderStatusMap[s.key]){
        this.$router.push({ name: r, query: { status: orderStatusMap[s.key] } }).catch(()=>{});
      } else {
        this.$router.push({ name: r }).catch(()=>{});
      }
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
