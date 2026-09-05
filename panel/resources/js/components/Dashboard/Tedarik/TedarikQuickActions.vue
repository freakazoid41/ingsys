<template>
  <div class="tdk-quick">
    <h5 class="tdk-quick__title">Hızlı Erişim</h5>
    <div class="tdk-quick__grid">
      <router-link to="/tedarikpanel/orders" class="tdk-quick__card">
        <div class="tdk-quick__icon" style="background:#fff7ed;color:#FF5A1F;"><i class="ki-outline ki-document"></i></div>
        <div class="tdk-quick__text"><span class="tdk-quick__label">Siparişler</span><span class="tdk-quick__desc">Sipariş takibi</span></div>
        <i class="ki-outline ki-arrow-right tdk-quick__arrow"></i>
      </router-link>
      <router-link to="/tedarikpanel/documents" class="tdk-quick__card">
        <div class="tdk-quick__icon" style="background:#fef3c7;color:#d97706;"><i class="ki-outline ki-file-added"></i></div>
        <div class="tdk-quick__text"><span class="tdk-quick__label">Dökümanlar</span><span class="tdk-quick__desc">Dosya yönetimi</span></div>
        <i class="ki-outline ki-arrow-right tdk-quick__arrow"></i>
      </router-link>
      <router-link to="/tedarikpanel/orders" class="tdk-quick__card">
        <div class="tdk-quick__icon" style="background:#dcfce7;color:#16a34a;"><i class="ki-outline ki-check-circle"></i></div>
        <div class="tdk-quick__text"><span class="tdk-quick__label">Onay Bekleyen</span><span class="tdk-quick__desc">Kontrol edilecek</span></div>
        <i class="ki-outline ki-arrow-right tdk-quick__arrow"></i>
      </router-link>
      <a href="javascript:;" @click="goProfile" class="tdk-quick__card">
        <div class="tdk-quick__icon" style="background:#ede9fe;color:#7c3aed;"><i class="ki-outline ki-user"></i></div>
        <div class="tdk-quick__text"><span class="tdk-quick__label">Profilim</span><span class="tdk-quick__desc">Bilgilerim</span></div>
        <i class="ki-outline ki-arrow-right tdk-quick__arrow"></i>
      </a>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
export default {
  name: 'TedarikQuickActions',
  methods: {
    goProfile(){
      const auth = useAuthStore();
      if(auth?.personId) this.$router.push({ name: 'UForm', params:{ id: auth.personId }}).catch(()=>{});
    }
  }
};
</script>

<style scoped>
.tdk-quick { background: #fff; border-radius: 18px; padding: 1.5rem; border: 1px solid #ffe4cc; box-shadow:0 1px 3px rgba(255,90,31,.06); }
.tdk-quick__title { font-size: 0.95rem; font-weight: 800; color: #431407; margin: 0 0 1rem; }
.tdk-quick__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.tdk-quick__card { display: flex; align-items: center; gap: 0.85rem; padding: 1rem 1.1rem; border-radius: 14px; border: 1px solid #fff7ed; background:#fff; text-decoration: none; transition: all 0.2s; position:relative; }
.tdk-quick__card:hover { border-color: #fed7aa; background: #fff7ed; transform: translateY(-2px); box-shadow:0 8px 18px rgba(255,90,31,.08); }
.tdk-quick__icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.tdk-quick__text { display: flex; flex-direction: column; gap: 0.15rem; flex:1; min-width:0; }
.tdk-quick__label { font-size: 0.85rem; font-weight: 800; color: #431407; }
.tdk-quick__desc { font-size: 0.72rem; color: #9a3412; opacity:.7; }
.tdk-quick__arrow{ font-size:14px; color:#fed7aa; margin-left:auto; }
.tdk-quick__card:hover .tdk-quick__arrow{ color:#FF5A1F; }
@media(max-width: 640px){ .tdk-quick__grid{ grid-template-columns: 1fr; } }
</style>
