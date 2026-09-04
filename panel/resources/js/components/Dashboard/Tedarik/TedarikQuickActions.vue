<template>
  <div class="tdk-quick">
    <h5 class="tdk-quick__title">Hızlı Erişim</h5>
    <div class="tdk-quick__grid">
      <router-link to="/tedarikpanel/orders" class="tdk-quick__card">
        <div class="tdk-quick__icon" style="background:#fff7ed;color:#FF5A1F;">
          <i class="ki-outline ki-document"></i>
        </div>
        <div class="tdk-quick__text">
          <span class="tdk-quick__label">Siparişler</span>
          <span class="tdk-quick__desc">Tüm siparişleri görüntüle</span>
        </div>
      </router-link>
      <router-link to="/tedarikpanel/documents" class="tdk-quick__card">
        <div class="tdk-quick__icon" style="background:#ede9fe;color:#7c3aed;">
          <i class="ki-outline ki-file"></i>
        </div>
        <div class="tdk-quick__text">
          <span class="tdk-quick__label">Dökümanlar</span>
          <span class="tdk-quick__desc">Dosya yönetimi</span>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TedarikQuickActions'
};
</script>

<style scoped>
.tdk-quick {
  background: #fff;
  border-radius: 14px;
  padding: 1.5rem;
  border: 1px solid #f1f5f9;
}
.tdk-quick__title { font-size: 0.95rem; font-weight: 700; color: #111827; margin: 0 0 1rem; }
.tdk-quick__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.tdk-quick__card {
  display: flex; align-items: center; gap: 0.85rem; padding: 1rem;
  border-radius: 12px; border: 1px solid #f1f5f9; text-decoration: none;
  transition: all 0.2s;
}
.tdk-quick__card:hover { border-color: #FF5A1F33; background: #fff7ed; transform: translateY(-2px); }
.tdk-quick__icon {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;
}
.tdk-quick__text { display: flex; flex-direction: column; gap: 0.15rem; }
.tdk-quick__label { font-size: 0.85rem; font-weight: 700; color: #111827; }
.tdk-quick__desc { font-size: 0.72rem; color: #9ca3af; }
</style>
