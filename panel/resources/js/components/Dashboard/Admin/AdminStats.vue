<template>
  <div class="adm-stats">
    <div v-for="s in cards" :key="s.key" class="adm-stat" :class="{ 'adm-stat--accent': s.accent }" @click="go(s)">
      <div class="adm-stat__icon" :style="{ background: s.bg, color: s.color }"><i :class="s.icon"></i></div>
      <div class="adm-stat__body">
        <span class="adm-stat__label">{{ s.label }}</span>
        <span class="adm-stat__value" :style="{ color: s.valueColor || '#111827' }">
          <span v-if="loading" class="adm-stat__loader"></span>
          <span v-else>{{ stats[s.key] ?? 0 }}</span>
        </span>
        <span v-if="s.sub" class="adm-stat__sub">{{ s.sub }}</span>
      </div>
      <i class="adm-stat__arrow ki-outline ki-arrow-right"></i>
    </div>
  </div>
</template>

<script>
import Plib from '@/lib/pickle';
export default {
  name: 'AdminStats',
  data() {
    return {
      loading: true,
      stats: {
        totalOrders: 0, createdOrders: 0, pendingOrders: 0, filesRejected: 0,
        approvedOrders: 0, totalClients: 0, totalUsers: 0, totalFiles: 0,
        waitingFiles: 0, totalItems: 0, activeSessions: 0, todayOrders: 0
      },
      cards: [
        { key: 'totalOrders', label: 'TOPLAM SİPARİŞ', icon: 'ki-outline ki-document', bg: '#eff6ff', color: '#154b91', valueColor: '#154b91', sub: 'Aktif sipariş', accent: true },
        { key: 'pendingOrders', label: 'KONTROL BEKLİYOR', icon: 'ki-outline ki-time', bg: '#fef3c7', color: '#92400e', sub: 'Dosyalar inceleniyor' },
        { key: 'filesRejected', label: 'DOSYA REDDEDİLDİ', icon: 'ki-outline ki-cross-circle', bg: '#fee2e2', color: '#991b1b', sub: 'Müdahale gerekli' },
        { key: 'approvedOrders', label: 'ONAYLANDI', icon: 'ki-outline ki-check-circle', bg: '#dcfce7', color: '#166534', sub: 'Kalite onayı' },
        { key: 'totalClients', label: 'FİRMALAR', icon: 'ki-outline ki-briefcase', bg: '#ede9fe', color: '#6d28d9', sub: 'Kayıtlı cari' },
        { key: 'totalUsers', label: 'KULLANICILAR', icon: 'ki-outline ki-profile-user', bg: '#e0f2fe', color: '#0c4a6e', sub: 'Sistem kullanıcı' },
        { key: 'totalFiles', label: 'TOPLAM DOSYA', icon: 'ki-outline ki-file-added', bg: '#f3f4f6', color: '#374151', sub: 'Yüklenen doküman' },
        { key: 'activeSessions', label: 'AKTİF OTURUM', icon: 'ki-outline ki-pulse', bg: '#ecfdf5', color: '#065f46', sub: 'Son 5 dk' },
      ]
    };
  },
  mounted() { this.load(); },
  methods: {
    async load() {
      try {
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/admin-stats', method: 'GET' }, null);
        if (rsp && typeof rsp === 'object') {
          Object.keys(this.stats).forEach(k => { if (rsp[k] !== undefined) this.stats[k] = rsp[k]; });
        }
      } catch (e) { console.error('admin-stats failed', e); }
      finally { this.loading = false; }
    },
    go(s) {
      const orderStatusMap = {
        pendingOrders: 'doc_trans_order_transfer_sent',
        filesRejected: 'doc_trans_order_files_rejected',
        approvedOrders: 'doc_trans_order_approved',
      };
      const map = {
        totalOrders: 'OrderList', pendingOrders: 'OrderList', filesRejected: 'OrderList', approvedOrders: 'OrderList',
        totalClients: 'CList', totalUsers: 'UList', totalFiles: 'DList', activeSessions: 'UList'
      };
      const route = map[s.key];
      if (!route) return;
      if(orderStatusMap[s.key]){
        this.$router.push({ name: route, query: { status: orderStatusMap[s.key] } }).catch(()=>{});
      } else {
        this.$router.push({ name: route }).catch(()=>{});
      }
    }
  }
};
</script>

<style scoped>
.adm-stats { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 1rem; width: 100%; max-width: 100%; margin-bottom: 0; box-sizing: border-box; }
.adm-stat { background: #fff; border-radius: 18px; padding: 1.2rem 1.25rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.25s ease; border: 1px solid #eef2f7; position: relative; overflow: hidden; }
.adm-stat::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: transparent; transition: background 0.25s; }
.adm-stat--accent::before { background: linear-gradient(90deg, #154b91, #3b82f6); }
.adm-stat:hover { transform: translateY(-4px); box-shadow: 0 14px 36px rgba(21,75,145,0.12); border-color: #cbd5e1; }
.adm-stat:hover .adm-stat__arrow { opacity: 1; transform: translateX(0); }
.adm-stat__icon { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.adm-stat__body { display: flex; flex-direction: column; gap: 0.15rem; flex: 1; min-width: 0; }
.adm-stat__label { font-size: 0.68rem; font-weight: 800; color: #64748b; letter-spacing: 0.07em; line-height: 1.1; }
.adm-stat__value { font-size: 1.65rem; font-weight: 800; line-height: 1.1; }
.adm-stat__sub { font-size: 0.72rem; color: #94a3b8; font-weight: 600; }
.adm-stat__arrow { font-size: 16px; color: #94a3b8; opacity: 0; transform: translateX(-6px); transition: all 0.2s; flex-shrink: 0; }
.adm-stat__loader { display: inline-block; width: 22px; height: 22px; border: 3px solid #e5e7eb; border-top-color: #154b91; border-radius: 50%; animation: adm-spin 0.6s linear infinite; }
@keyframes adm-spin { to { transform: rotate(360deg); } }
@media (max-width: 1280px) { .adm-stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 576px) { .adm-stats { grid-template-columns: 1fr; } }
</style>
