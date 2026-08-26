<template>
  <div class="notifications-card">
    <div class="card-header">
      <h5 class="card-title">Bildirimler</h5>
    </div>
    <div class="notifications-list">
      <div v-for="notif in notificationList" :key="notif.id" :class="['notification-item', notif.type]">
        <span class="notification-icon">
          <i :class="notif.iconClass"></i>
        </span>
        <div class="notification-content">
          <p class="notification-text">{{ notif.text }}</p>
          <span class="notification-time">{{ notif.time }}</span>
        </div>
      </div>
    </div>
    <a href="" alt="" title="" class="notifications-footer-btn">
      Tüm Bildirimleri Görüntüle <i class="fa-solid fa-angle-right"></i>
    </a>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';

export default {
  name: 'DashboardNotifications',
  data() {
    return {
      authStore: useAuthStore(),
      navigationStore: useNavigationStore(),
      notifications: [],
      notificationList: []
    };
  },
  mounted() {
    this.loadNotifications();
    this.mergeNotifications();
  },
  watch: {
    'navigationStore.notifications': {
      handler() {
        this.mergeNotifications();
      },
      deep: true
    }
  },
  methods: {
    loadNotifications() {
      this.notifications = (this.authStore.currentStatus?.rejectedFiles || []).map((fl) => ({
        id: `rejected-${fl.id ?? fl.cli_id ?? Math.random()}`,
        text: `${fl.title || fl.name || ''} reddedildi.`,
        time: `${fl.rejected_by || ''} tarafından`,
        type: 'clientFile',
        iconClass: 'ki-outline ki-information',
        onclick: () => {
          this.$router.push({ name: 'CForm', params: { id: fl.cli_id } });
        }
      }));

      if (typeof this.navigationStore.getNotifications === 'function') {
        this.navigationStore.getNotifications();
      }
    },
    mergeNotifications() {
      try {
        const addNotifications = this.navigationStore?.notifications || {};
        let list = [];

        for (const key in addNotifications) {
          switch (key) {
            case 'awaitingUsers':
              list = [...list, ...(addNotifications[key] || []).map(u => ({
                id: `awaitingUser-${u.id}`,
                text: `Yeni kullanıcı kayıt bekliyor: ${u.username}`,
                time: `Kayıt tarihi: ${u.created_at}`,
                type: 'awaitingUser',
                iconClass: 'ki-outline ki-information',
                onclick: () => { this.$router.push({ name: 'UForm', params: { id: u.id } }); }
              }))];
              break;
            case 'clientChanges':
              list = [...list, ...(addNotifications[key] || []).map(u => ({
                id: `clientChange-${u.id}`,
                text: `Müşteri güncellemesi (${u.title})`, 
                time: `Kayıt tarihi: ${u.created_at}`,
                type: 'clientChange',
                iconClass: 'ki-outline ki-information',
                onclick: () => { this.$router.push({ name: 'CForm', params: { id: u.cli_id } }); }
              }))];
              break;
            case 'offerRevisionRequests':
            case 'offerChanges':
            case 'newOffer':
              list = [...list, ...((addNotifications[key] || []).map(offr => {
                let title = '';
                try {
                  JSON.parse(offr.main_attr || '[]').forEach(det => {
                    if (det.Key === 'clititle') title = det.Value;
                  });
                } catch (e) {
                  title = '';
                }
                return {
                  id: `offer-${offr.id}`,
                  text: (key === 'offerRevisionRequests' ? 'Teklif revizyon talebi' : key === 'newOffer' ? 'Yeni Teklif' : 'Teklif güncellemesi') + (title ? ` — ${title}` : ''),
                  time: `Kayıt tarihi: ${offr.created_at}`,
                  type: 'newOffer',
                  iconClass: 'ki-outline ki-information',
                  onclick: () => { this.$router.push({ name: 'OForm', params: { id: offr.id } }); }
                };
              }))];
              break;
            default:
              break;
          }
        }

        const local = (this.notifications || []).map((n, i) => ({
          id: n.id ?? `local-${i}`,
          text: n.title ?? n.text ?? n.message ?? '',
          time: n.time ?? n.created_at ?? '',
          type: n.type ?? 'local',
          iconClass: 'ki-outline ki-information',
          onclick: n.onclick ?? null
        }));

        list = [...list, ...local];
        this.notificationList = list;
      } catch (e) {
        console.warn('mergeNotifications failed', e);
      }
    }
  }
};
</script>

<style scoped>
.notifications-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  height: 100%;
  border: 1px solid var(--border-color);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--dark-text);
  margin: 0 0 1.25rem 0;
  font-family: 'Inter', sans-serif;
}

.notifications-list {
  background: #fff;
  border: 1px solid #edf1f7;
  border-radius: 10px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 1rem;
  flex: 1;
  padding: 1rem;
  min-height: 220px;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 24px;
  padding: 5px;
  position: relative;
  transition: 0.25s ease;
  border-radius: 8px;
}

.notification-item:not(:last-child) {
  border-bottom: 1px solid #edf1f7;
}

.notification-item:hover {
   background: #f8f9fa;
  cursor:pointer;
}

.notification-icon {
  min-width: 52px;
  width: 52px;
  height: 52px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
}

.notification-icon i {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: xx-large;
}

.awaitingUser .notification-icon {
  background: #f3f6ff;
  color: #2563ff;
}

.clientChange .notification-icon {
  background: #eefbf2;
  color: #0ea85d;
}

.newOffer .notification-icon {
  background: #fff7ed;
  color: #f97316;
}

.notification-content {
  flex: 1;
  padding-right: 50px;
}

.notification-text {
  position: relative;
  font-size: 1rem;
  line-height: 1.3rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 5px 0;
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.notification-text::before {
  content: "";
  min-width: 12px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  margin-top: 2.5px;
}

.awaitingUser .notification-text::before {
  background: #2563ff;
}

.clientChange .notification-text::before {
  background: #0ea85d;
}

.newOffer .notification-text::before {
  background: #f97316;
}

.notification-time {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: #8a94a6;
  font-weight: 500;
  margin-left: 27px;
}

.notification-item::after {
  content: "\f054";
  font-family: "Font Awesome 6 Free";
  font-weight: 900;
  position: absolute;
  right: 30px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 18px;
  color: #1e293b;
  opacity: 0.8;
}

.notifications-footer-btn {
  width: 100%;
  border: 1px solid #edf1f7;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  transition: 0.3s;
  background: #cccccc2d;
  font-size: 1rem;
  height: 45px;
  border-radius: 10px;
}

.notifications-footer-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(37, 99, 255, 0.08);
}
</style>
