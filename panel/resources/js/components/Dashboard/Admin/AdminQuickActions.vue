<template>
  <div class="adm-qa">
    <h5 class="adm-qa__title">Hızlı İşlemler</h5>
    <div class="adm-qa__grid">
      <button v-for="a in actions" :key="a.label" class="adm-qa__btn" @click="go(a)">
        <span class="adm-qa__icon" :style="{ background: a.bg, color: a.color }"><i :class="a.icon"></i></span>
        <span class="adm-qa__label">{{ a.label }}</span>
        <i class="adm-qa__arrow ki-outline ki-arrow-right"></i>
      </button>
    </div>
  </div>
</template>
<script>
export default {
  name: 'AdminQuickActions',
  data(){
    return {
      actions: [
        { label: 'Siparişler', icon: 'ki-outline ki-document', bg: '#eff6ff', color: '#154b91', route: 'OrderList' },
        { label: 'Dökümanlar', icon: 'ki-outline ki-file-added', bg: '#fff7ed', color: '#c2410c', route: 'DList' },
        { label: 'Firmalar', icon: 'ki-outline ki-briefcase', bg: '#ede9fe', color: '#6d28d9', route: 'CList' },
        { label: 'Kullanıcılar', icon: 'ki-outline ki-profile-user', bg: '#dcfce7', color: '#166534', route: 'UList' },
        { label: 'Sistem Logları', icon: 'ki-outline ki-note', bg: '#fef3c7', color: '#92400e', route: 'LList' },
        { label: 'Bildirimler', icon: 'ki-outline ki-notification-bing', bg: '#fce7f3', color: '#be185d', route: 'NList' },
      ]
    };
  },
  methods:{
    go(a){ this.$router.push({ name: a.route }).catch(()=>{}); }
  }
};
</script>
<style scoped>
.adm-qa{ background:#fff; border-radius:18px; padding:1.5rem; border:1px solid #eef2f7; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
.adm-qa__title{ font-size:0.95rem; font-weight:800; color:#0f172a; margin:0 0 1rem; }
.adm-qa__grid{ display:grid; grid-template-columns: repeat(2, 1fr); gap:0.75rem; }
.adm-qa__btn{ display:flex; align-items:center; gap:0.8rem; padding:0.9rem 1rem; border:1px solid #f1f5f9; border-radius:14px; background:#fff; cursor:pointer; transition: all 0.18s; text-align:left; }
.adm-qa__btn:hover{ background:#f8fafc; border-color:#cbd5e1; transform: translateY(-1px); box-shadow:0 6px 18px rgba(15,23,42,0.06); }
.adm-qa__icon{ width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.adm-qa__label{ flex:1; font-size:0.84rem; font-weight:700; color:#1e293b; }
.adm-qa__arrow{ font-size:14px; color:#94a3b8; }
@media(max-width: 640px){ .adm-qa__grid{ grid-template-columns: 1fr; } }
</style>
