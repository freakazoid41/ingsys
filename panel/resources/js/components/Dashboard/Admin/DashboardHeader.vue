<template>
  <div class="header-section">
    <div class="header-content">
      <div>
        <h1 class="greeting-title">{{ greeting }}, <span class="user-name">{{ userName }}</span></h1>
        <p class="header-subtitle">Yönetici Paneli</p>
      </div>
      <div class="header-icons">
        <button @click="showNotifications" class="icon-btn notification-btn">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          <span v-if="hasNotifications" class="notification-badge"></span>
        </button>
        <router-link 
          v-if="authStore.personId"
          :to="{ name: 'UForm', params: { id: authStore.personId } }"
          class="icon-btn profile-btn"
          title="Profil">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
import Swal from 'sweetalert2';

export default {
  name: 'DashboardHeader',
  setup() {
    return {
      useAuthStore,
      useNavigationStore,
      Swal
    };
  },
  data() {
    return {
      authStore: useAuthStore(),
      navigationStore: useNavigationStore(),
      greeting: 'Hoş Geldiniz',
      userName: useAuthStore().userName,
      notifications: [],
      notificationList: []
    };
  },
  computed: {
    hasNotifications() {
      let count = 0;
      const notifs = this.navigationStore?.notifications || {};
      
      // Count from each category
      if (Array.isArray(notifs.awaitingUsers)) count += notifs.awaitingUsers.length;
      if (Array.isArray(notifs.clientChanges)) count += notifs.clientChanges.length;
      if (Array.isArray(notifs.newOffer)) count += notifs.newOffer.length;
      if (Array.isArray(notifs.offerRevisionRequests)) count += notifs.offerRevisionRequests.length;
      if (Array.isArray(notifs.offerChanges)) count += notifs.offerChanges.length;
      
      // Add rejected files
      count += (this.notifications || []).length;
      
      return count > 0;
    }
  },
  mounted() {
    this.loadNotifications();
    this.mergeNotifications();
  },
  watch: {
    'navigationStore.notifications': {
      handler() {
        // React to navigationStore notification changes
        this.mergeNotifications();
      },
      deep: true
    }
  },
  methods: {
    loadNotifications() {
      // Fallback to authStore if navigationStore method doesn't exist
      this.notifications = (this.authStore.currentStatus?.rejectedFiles || []).map((fl) => {
        return {
          title: 'Reddedilen Dosya',
          message: `${fl.title} reddedildi.`,
          time: `${fl.rejected_by} tarafından`,
          type: 'clientFile',
          onclick: () => {
            this.$router.push({ name: 'CForm', params: { id: fl.cli_id } });
          },
        };
      });
      // Fetch notifications from navigationStore
      this.navigationStore.getNotifications();
    },

    mergeNotifications() {
      // Build in-component notification list from navigationStore.notifications
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
                iconClass: 'ki-outline ki-user',
                onclick: () => { this.$router.push({ name: 'UForm', params: { id: u.id } }); }
              }))];
              break;
            case 'clientChanges':
              list = [...list, ...(addNotifications[key] || []).map(u => ({
                id: `clientChange-${u.id}`,
                text: `Müşteri güncellemesi (${u.title})`, 
                time: `Kayıt tarihi: ${u.created_at}`,
                type: 'clientChange',
                iconClass: 'ki-outline ki-file',
                onclick: () => { this.$router.push({ name: 'CForm', params: { id: u.cli_id } }); }
              }))];
              break;
            case 'offerRevisionRequests':
            case 'offerChanges':
            case 'newOffer':
              const offers = (addNotifications[key] || []).map(offr => {
                let title = '';
                try {
                  JSON.parse(offr.main_attr || '[]').forEach(det => {
                    if (det.Key == 'clititle') title = det.Value;
                  });
                } catch (e) {}
                return {
                  id: `offer-${offr.id}`,
                  text: (key === 'offerRevisionRequests' ? 'Teklif revizyon talebi' : key === 'newOffer' ? 'Yeni Teklif' : 'Teklif güncellemesi') + (title ? ` — ${title}` : ''),
                  time: `Kayıt tarihi: ${offr.created_at}`,
                  type: 'newOffer',
                  iconClass: 'ki-outline ki-bell',
                  onclick: () => { this.$router.push({ name: 'OForm', params: { id: offr.id } }); }
                };
              });
              list = [...list, ...offers];
              break;
            default:
              break;
          }
        }

        // Merge with rejected files / local notifications
        // normalize local `notifications` entries to the same shape
        const local = (this.notifications || []).map((n, i) => ({
          id: n.id ?? `local-${i}`,
          text: n.title ?? n.text ?? n.message ?? '',
          time: n.time ?? n.created_at ?? '',
          type: n.type ?? 'local',
          iconPath: n.iconPath ?? 'M12 4v16m8-8H4',
          onclick: n.onclick ?? null
        }));

        list = [...list, ...local];
        this.notificationList = list;
      } catch (e) {
        console.warn('mergeNotifications failed', e);
      }
    },

    showNotifications() {
      // Use the component's normalized notificationList for the modal
      this.mergeNotifications();
      const list = (this.notificationList || []).map(n => ({
        title: n.title || n.text || '',
        message: n.message || n.text || '',
        time: n.time || n.date || '',
        iconClass: n.iconClass || 'ki-outline ki-bell',
        type: n.type || '',
        onclick: typeof n.onclick === 'function' ? n.onclick : null
      }));

      if (!list || list.length === 0) {
        Swal.fire({
          title: 'Bildirimler',
          html: '<div style="text-align:center;padding:20px;color:#999;">Yeni bildirim yok</div>',
          width: '480px',
          showCloseButton: true,
          showCancelButton: false,
          showConfirmButton: false,
        });
        return;
      }

      const html = `
  <div 
    class="notifications-list"
    style="
      background:#fff;
      border:1px solid #edf1f7;
      border-radius:10px;
      overflow-y:auto;
      max-height:300px;
    "
  >
    ${list.map((n, idx) => `
      <div 
        class="notification-item ${n.type || ''} swal-notification-item"
        data-index="${idx}"
        style="
          display:flex;
          align-items:flex-start;
          gap:24px;
          padding:1rem;
          position:relative;
          transition:0.25s ease;
          border-bottom:${idx !== list.length - 1 ? '1px solid #edf1f7' : 'none'};
          cursor:pointer;
        "
      >
        <span 
          class="notification-icon"
          style="
            min-width:52px;
            width:52px;
            height:52px;
            border-radius:12px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:32px;
            background:${
              n.type === 'awaitingUser'
                ? '#f3f6ff'
                : n.type === 'clientChange'
                ? '#eefbf2'
                : '#fff7ed'
            };
            color:${
              n.type === 'awaitingUser'
                ? '#2563ff'
                : n.type === 'clientChange'
                ? '#0ea85d'
                : '#f97316'
            };
          "
        >
          <i 
            class="${n.iconClass}"
            style="
              display:flex;
              align-items:center;
              justify-content:center;
            "
          ></i>
        </span>

        <div 
          class="notification-content"
          style="
            flex:1;
            padding-right:20px;
            text-align:left;
          "
        >
          <p 
            class="notification-text"
            style="
              position:relative;
              font-size:1rem;
              line-height:1.3rem;
              font-weight:700;
              color:#0f172a;
              margin:0 0 5px 0;
              display:flex;
              align-items:flex-start;
              gap:14px;
            "
          >
            <span
              style="
                min-width:12px;
                width:12px;
                height:12px;
                border-radius:50%;
                margin-top:2.5px;
                background:${
                  n.type === 'awaitingUser'
                    ? '#2563ff'
                    : n.type === 'clientChange'
                    ? '#0ea85d'
                    : '#f97316'
                };
              "
            ></span>

            ${n.message}
          </p>

          <span 
            class="notification-time"
            style="
              display:flex;
              align-items:center;
              gap:10px;
              font-size:14px;
              color:#8a94a6;
              font-weight:500;
              margin-left:27px;
            "
          >
            ${n.time}
          </span>
        </div>

        <span
          style="
            position:absolute;
            right:30px;
            top:50%;
            transform:translateY(-50%);
            font-size:18px;
            color:#1e293b;
            opacity:.8;
          "
        >
          <i class="fa-solid fa-chevron-right"></i>
        </span>
      </div>
    `).join('')}
  </div>
`;

      Swal.fire({
        title: 'Bildirimler',
        html,
        width: '480px',
        showCloseButton: true,
        showCancelButton: false,
        showConfirmButton: false,
        didOpen: () => {
          document.querySelectorAll('.swal-notification-item').forEach((el) => {
            el.addEventListener('mouseover', () => el.style.background = 'rgba(21, 75, 145, 0.03)');
            el.addEventListener('mouseout', () => el.style.background = 'transparent');
            el.addEventListener('click', () => {
              const idx = Number(el.dataset.index);
              const item = list?.[idx];
              if (item && typeof item.onclick === 'function') item.onclick();
              Swal.close();
            });
          });
        }
      });
    }
  }
};
</script>

<style scoped>
.header-section {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  margin-bottom: 2.5rem;
  border-top: 3px solid var(--primary-color);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.greeting-title {
  font-size: 1.875rem;
  font-weight: 700;
  color: var(--dark-text);
  margin: 0;
  font-family: 'Inter', sans-serif;
}

.user-name {
  color: var(--primary-color);
  font-weight: 800;
}

.header-subtitle {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin: 0.5rem 0 0;
  font-weight: 500;
}

.header-icons {
  display: flex;
  gap: 1rem;
}

.icon-btn {
  width: 44px;
  height: 44px;
  border: none;
  background: var(--light-bg);
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
  transition: all 0.3s ease;
  border: 2px solid transparent;
}

.icon-btn:hover {
  background: lightblue;
  color: white;
  transform: scale(1.05);
  border-color: var(--primary-color);
}

.notification-btn {
  position: relative;
}

.notification-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 12px;
  height: 12px;
  background: var(--danger-color);
  border-radius: 50%;
  animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  box-shadow: 0 0 0 rgba(248, 40, 90, 0.7);
}

@keyframes pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(248, 40, 90, 0.7);
  }
  50% {
    box-shadow: 0 0 0 6px rgba(248, 40, 90, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(248, 40, 90, 0.7);
  }
}

/* Responsive Design */
@media (max-width: 768px) {
  .header-content {
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }

  .header-section {
    padding: 1.5rem;
  }

  .greeting-title {
    font-size: 1.5rem;
  }
}

@media (max-width: 576px) {
  .header-section {
    padding: 1rem;
  }

  .greeting-title {
    font-size: 1.3rem;
  }

  .header-subtitle {
    font-size: 0.85rem;
  }

  .icon-btn {
    width: 40px;
    height: 40px;
  }

  .header-icons {
    gap: 0.75rem;
  }
}
</style>
