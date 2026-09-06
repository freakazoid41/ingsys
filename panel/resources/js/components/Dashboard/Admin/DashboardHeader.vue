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
      if (Array.isArray(notifs.orderImported)) count += notifs.orderImported.length;
      if (Array.isArray(notifs.orderSent)) count += notifs.orderSent.length;
      if (Array.isArray(notifs.pendingFiles)) count += notifs.pendingFiles.length;
      if (Array.isArray(notifs.fileApproved)) count += notifs.fileApproved.length;
      if (Array.isArray(notifs.fileRejected)) count += notifs.fileRejected.length;
      if (Array.isArray(notifs.orderApproved)) count += notifs.orderApproved.length;
      if (Array.isArray(notifs.orderRejected)) count += notifs.orderRejected.length;
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
        const parseOrder = (o) => {
          let orderNo = o.order_no || o.group_key || '';
          let ctitle = '';
          try { JSON.parse(o.main_attr||'[]').forEach(d=>{ if(d.Key==='order_no'&&!orderNo) orderNo=d.Value; if(d.Key==='ctitle'&&!ctitle) ctitle=d.Value; }); } catch(e){}
          return { orderNo: orderNo||o.id||'-', ctitle, qnid: o.qnid||o.id||o.relation_qnid };
        };
        const parseFile = (f) => ({ orderNo: f.group_key||'-', title: f.type_title||f.file_type||'Dosya', qnid: f.qnid||f.id||f.relation_qnid });
        for (const key in addNotifications) {
          switch (key) {
            case 'orderImported':
              list = [...list, ...(addNotifications[key]||[]).map(o=>{ const m=parseOrder(o); return { id:`ted01-${o.qnid||o.id}`, text: `Sipariş Sisteme Geldi (SAP) — ${m.orderNo}${m.ctitle?' — '+m.ctitle:''}`, time: `Kayıt: ${o.created_at||''}`, type: 'tedarik01', opKey:'tedarik-01', targetQnid:m.qnid, iconClass: 'ki-outline ki-package', onclick:()=>this.$router.push({name:'OrderForm',params:{id:m.qnid}}) }; })];
              break;
            case 'orderSent':
              list = [...list, ...(addNotifications[key]||[]).map(o=>{ const m=parseOrder(o); return { id:`ted02-${o.qnid||o.id}`, text: `Sipariş Onaya Gönderildi — ${m.orderNo}`, time: `Kayıt: ${o.created_at||''}`, type: 'tedarik02', opKey:'tedarik-02', targetQnid:m.qnid, iconClass: 'ki-outline ki-send', onclick:()=>this.$router.push({name:'OrderForm',params:{id:m.qnid}}) }; })];
              break;
            case 'pendingFiles':
              list = [...list, ...(addNotifications[key]||[]).map(f=>{ const m=parseFile(f); return { id:`ted03-${f.qnid||f.id}`, text: `İnceleme Bekleyen Dosyalar — ${m.title} (${m.orderNo})`, time: `Kayıt: ${f.created_at||''}`, type: 'tedarik03', opKey:'tedarik-03', targetQnid:m.qnid, iconClass: 'ki-outline ki-file', onclick:()=>this.$router.push({name:'DList'}) }; })];
              break;
            case 'fileApproved':
              list = [...list, ...(addNotifications[key]||[]).map(f=>{ const m=parseFile(f); return { id:`ted04-${f.qnid||f.id}`, text: `Sipariş Dosyası Onaylandı — ${m.title} (${m.orderNo})`, time: `Kayıt: ${f.created_at||''}`, type: 'tedarik04', opKey:'tedarik-04', targetQnid:m.qnid, iconClass: 'ki-outline ki-check', onclick:()=>this.$router.push({name:'DForm',params:{id:m.qnid}}) }; })];
              break;
            case 'fileRejected':
              list = [...list, ...(addNotifications[key]||[]).map(f=>{ const m=parseFile(f); return { id:`ted05-${f.qnid||f.id}`, text: `Sipariş Dosyası Yeniden Talep — ${m.title} (${m.orderNo})`, time: `Kayıt: ${f.created_at||''}`, type: 'tedarik05', opKey:'tedarik-05', targetQnid:m.qnid, iconClass: 'ki-outline ki-cross', onclick:()=>this.$router.push({name:'DForm',params:{id:m.qnid}}) }; })];
              break;
            case 'orderApproved':
              list = [...list, ...(addNotifications[key]||[]).map(o=>{ const m=parseOrder(o); return { id:`ted06-${o.qnid||o.id}`, text: `Sipariş Kalite Onayı Verildi — ${m.orderNo}`, time: `Kayıt: ${o.created_at||''}`, type: 'tedarik06', opKey:'tedarik-06', targetQnid:m.qnid, iconClass: 'ki-outline ki-medal-star', onclick:()=>this.$router.push({name:'OrderForm',params:{id:m.qnid}}) }; })];
              break;
            case 'orderRejected':
              list = [...list, ...(addNotifications[key]||[]).map(o=>{ const m=parseOrder(o); return { id:`ted07-${o.qnid||o.id}`, text: `Sipariş Reddedildi — ${m.orderNo}`, time: `Kayıt: ${o.created_at||''}`, type: 'tedarik07', opKey:'tedarik-07', targetQnid:m.qnid, iconClass: 'ki-outline ki-cross-square', onclick:()=>this.$router.push({name:'OrderForm',params:{id:m.qnid}}) }; })];
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
        opKey: n.opKey || '',
        targetQnid: n.targetQnid || '',
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
              n.type === 'tedarik04' ? '#ecfdf5' :
              n.type === 'tedarik05' ? '#fef2f2' :
              n.type === 'tedarik06' ? '#f0fdf4' :
              n.type === 'tedarik07' ? '#fef2f2' :
              n.type === 'tedarik01' ? '#eff6ff' :
              n.type === 'tedarik02' ? '#fff7ed' :
              n.type === 'tedarik03' ? '#fef3c7' : '#fff7ed'
            };
            color:${
              n.type === 'tedarik04' ? '#059669' :
              n.type === 'tedarik05' ? '#dc2626' :
              n.type === 'tedarik06' ? '#16a34a' :
              n.type === 'tedarik07' ? '#991b1b' :
              n.type === 'tedarik01' ? '#2563eb' :
              n.type === 'tedarik02' ? '#f59e0b' :
              n.type === 'tedarik03' ? '#d97706' : '#f97316'
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
                  n.type === 'tedarik04' ? '#059669' :
                  n.type === 'tedarik05' ? '#dc2626' :
                  n.type === 'tedarik06' ? '#16a34a' :
                  n.type === 'tedarik07' ? '#991b1b' :
                  n.type === 'tedarik01' ? '#2563eb' :
                  n.type === 'tedarik02' ? '#f59e0b' :
                  n.type === 'tedarik03' ? '#d97706' : '#f97316'
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
              // Mark as read
              if(item && item.opKey && item.targetQnid){
                this.navigationStore.markNotificationRead(item.opKey, item.targetQnid);
              }
              // Navigate
              if(item && typeof item.onclick === 'function') item.onclick();
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
  padding: 1.5rem 1.75rem;
  border-radius: 14px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  margin-bottom: 0;
  border-top: 3px solid var(--primary-color);
  width: 100%;
  max-width: 100%;
  min-height: max-content;
  box-sizing: border-box;
  overflow: hidden;
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
