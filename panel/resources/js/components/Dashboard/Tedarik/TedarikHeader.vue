<template>
  <div class="tdk-header">
    <div class="tdk-header__left">
      <h1 class="tdk-header__greeting">{{ greeting }}, <span class="tdk-header__name">{{ userName }}</span></h1>
      <p class="tdk-header__subtitle">Tedarikçi Paneli</p>
    </div>
    <div class="tdk-header__right">
      <button @click="showNotifications" class="tdk-header__bell">
        <i class="ki-outline ki-bell"></i>
        <span v-if="notifCount > 0" class="tdk-header__badge"></span>
      </button>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
import Swal from 'sweetalert2';

export default {
  name: 'TedarikHeader',
  data() {
    return {
      authStore: useAuthStore(),
      navigationStore: useNavigationStore(),
      greeting: 'Hoş Geldiniz',
      notifCount: 0,
    };
  },
  computed: {
    userName() {
      return this.authStore.userName || this.authStore.currentStatus?.main_name || 'Kullanıcı';
    }
  },
  mounted() {
    this.mergeNotifications();
    this.navigationStore.getNotifications();
  },
  watch: {
    'navigationStore.notifications': {
      handler() { this.mergeNotifications(); },
      deep: true
    }
  },
  methods: {
    mergeNotifications() {
      const notifs = this.navigationStore?.notifications || {};
      let count = 0;
      if (Array.isArray(notifs.awaitingUsers)) count += notifs.awaitingUsers.length;
      if (Array.isArray(notifs.clientChanges)) count += notifs.clientChanges.length;
      if (Array.isArray(notifs.newOffer)) count += notifs.newOffer.length;
      count += (this.authStore.currentStatus?.rejectedFiles || []).length;
      this.notifCount = count;
    },
    showNotifications() {
      const rejected = (this.authStore.currentStatus?.rejectedFiles || []).map(fl => ({
        text: `${fl.title} reddedildi`,
        time: `${fl.rejected_by} tarafından`,
        type: 'rejected',
      }));
      const notifs = this.navigationStore?.notifications || {};
      const items = [];
      if (Array.isArray(notifs.awaitingUsers)) {
        notifs.awaitingUsers.forEach(u => items.push({ text: `Yeni kayıt: ${u.username}`, time: u.created_at, type: 'user' }));
      }
      if (Array.isArray(notifs.clientChanges)) {
        notifs.clientChanges.forEach(u => items.push({ text: `Müşteri güncellendi: ${u.title}`, time: u.created_at, type: 'client' }));
      }
      const all = [...items, ...rejected];
      if (!all.length) {
        Swal.fire({ title: 'Bildirimler', html: '<div style="text-align:center;padding:20px;color:#999;">Bildirim yok</div>', width: 420, showCloseButton: true, showConfirmButton: false });
        return;
      }
      const html = `<div style="max-height:320px;overflow-y:auto;">${all.map(n => `
        <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-bottom:1px solid #f1f1f4;">
          <span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${n.type === 'rejected' ? '#ef4444' : '#f59e0b'}"></span>
          <div style="flex:1"><div style="font-weight:600;font-size:13px;color:#1e293b;">${n.text}</div><div style="font-size:11px;color:#8a94a6;">${n.time}</div></div>
        </div>`).join('')}</div>`;
      Swal.fire({ title: 'Bildirimler', html, width: 420, showCloseButton: true, showConfirmButton: false });
    }
  }
};
</script>

<style scoped>
.tdk-header {
  background: #fff;
  padding: 1.5rem 1.75rem;
  border-radius: 18px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  border: 1px solid #ffe4cc;
  border-top: 3px solid #FF5A1F;
  display: flex;
  justify-content: space-between;
  align-items: center;
  width:100%; max-width:100%; box-sizing:border-box;
}
.tdk-header__greeting { font-size: 1.6rem; font-weight: 700; color: #111827; margin: 0; }
.tdk-header__name { color: #FF5A1F; }
.tdk-header__subtitle { color: #6b7280; font-size: 0.85rem; margin: 0.3rem 0 0; font-weight: 500; }
.tdk-header__bell {
  width: 42px; height: 42px; border: none; background: #fff7ed; border-radius: 50%;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  color: #FF5A1F; font-size: 20px; position: relative; transition: all 0.2s;
}
.tdk-header__bell:hover { background: #FF5A1F; color: #fff; transform: scale(1.05); }
.tdk-header__badge {
  position: absolute; top: 6px; right: 6px; width: 10px; height: 10px;
  background: #ef4444; border-radius: 50%; border: 2px solid #fff;
}
</style>
