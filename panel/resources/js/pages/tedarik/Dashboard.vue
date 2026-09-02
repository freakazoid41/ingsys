<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h2 class="m-0" style="font-weight:700; color:#111827;">Tedarik Yönetim Paneli</h2>
      <span class="badge" style="background:#FFF1EC; color:#FF4A15; border:1px solid #FFD6C5; padding:6px 10px; border-radius:999px;">Public — Hoş geldiniz</span>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="card shadow-sm border-0" style="border-radius:14px;">
          <div class="card-body">
            <div class="text-muted" style="font-size:13px;">Siparişler</div>
            <div class="fw-bold" style="font-size:22px; color:#111827;">Siparişlerinizi yönetin</div>
            <div class="text-muted mt-1" style="font-size:12px;">Yakında: liste, transfer, seri giriş, dosya yükleme</div>
            <router-link to="/tedarikpanel/orders" class="btn mt-3" style="background:#FF4A15; color:#fff; border-radius:10px; font-weight:600;">Siparişlere Git</router-link>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm border-0" style="border-radius:14px;">
          <div class="card-body">
            <div class="text-muted" style="font-size:13px;">Durum</div>
            <div class="fw-semibold" style="color:#111827;">Giriş başarılı — {{ authStore.userName || authStore.currentStatus?.main_name || 'Kullanıcı' }}</div>
            <div class="text-muted mt-1" style="font-size:12px;">Rol: {{ authStore.typeKey }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm border-0" style="border-radius:14px;">
          <div class="card-body">
            <div class="text-muted" style="font-size:13px;">Bilgi</div>
            <div style="font-size:13px; color:#374151;">Bu panel, admin <code>/coalpanel</code> ile aynı 2FA akışını kullanır. Login <code>/tedarik</code> → 2FA → <code>/tedarikpanel</code>.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm border-0 mt-4" style="border-radius:14px;">
      <div class="card-body">
        <h5 class="mb-2" style="font-weight:700; color:#111827;">Yapılacaklar</h5>
        <ul class="m-0" style="color:#4b5563; font-size:13px; line-height:1.8;">
          <li>Sipariş listesi (tedarik görünümü) — admin OList mantığı, tedarik filtreli</li>
          <li>Sipariş detay — transfer modu, seri, dosya, Kabul/Cins PDF yazdırma</li>
          <li>Döküman yönetimi</li>
        </ul>
      </div>
    </div>
  </div>
</template>
<script>
import { useAuthStore } from '@/stores/auth';
export default {
  data(){ return { authStore: useAuthStore() } },
  async mounted(){ await this.authStore.getPermissions(); }
}
</script>
