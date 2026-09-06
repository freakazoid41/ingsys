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
              list = [...list, ...(addNotifications[key]||[]).map(o=>{ const m=parseOrder(o); return { id:`ted01-${o.qnid||o.id}`, text: `Sipariş Sisteme Geldi (SAP) — ${m.orderNo}${m.ctitle?' — '+m.ctitle:''}`, time: `Kayıt: ${o.created_at||''}`, type:'tedarik01', iconClass:'ki-outline ki-package', onclick:()=>this.$router.push({name:'OrderForm',params:{id:m.qnid}}) }; })];
              break;
            case 'orderSent':
              list = [...list, ...(addNotifications[key]||[]).map(o=>{ const m=parseOrder(o); return { id:`ted02-${o.qnid||o.id}`, text: `Sipariş Onaya Gönderildi — ${m.orderNo}`, time: `Kayıt: ${o.created_at||''}`, type:'tedarik02', iconClass:'ki-outline ki-send', onclick:()=>this.$router.push({name:'OrderForm',params:{id:m.qnid}}) }; })];
              break;
            case 'pendingFiles':
              list = [...list, ...(addNotifications[key]||[]).map(f=>{ const m=parseFile(f); return { id:`ted03-${f.qnid||f.id}`, text: `İnceleme Bekleyen Dosyalar — ${m.title} (${m.orderNo})`, time: `Kayıt: ${f.created_at||''}`, type:'tedarik03', iconClass:'ki-outline ki-file', onclick:()=>this.$router.push({name:'DList'}) }; })];
              break;
            case 'fileApproved':
              list = [...list, ...(addNotifications[key]||[]).map(f=>{ const m=parseFile(f); return { id:`ted04-${f.qnid||f.id}`, text: `Sipariş Dosyası Onaylandı — ${m.title} (${m.orderNo})`, time: `Kayıt: ${f.created_at||''}`, type:'tedarik04', iconClass:'ki-outline ki-check', onclick:()=>this.$router.push({name:'DForm',params:{id:m.qnid}}) }; })];
              break;
            case 'fileRejected':
              list = [...list, ...(addNotifications[key]||[]).map(f=>{ const m=parseFile(f); return { id:`ted05-${f.qnid||f.id}`, text: `Sipariş Dosyası Yeniden Talep — ${m.title} (${m.orderNo})`, time: `Kayıt: ${f.created_at||''}`, type:'tedarik05', iconClass:'ki-outline ki-cross', onclick:()=>this.$router.push({name:'DForm',params:{id:m.qnid}}) }; })];
              break;
            case 'orderApproved':
              list = [...list, ...(addNotifications[key]||[]).map(o=>{ const m=parseOrder(o); return { id:`ted06-${o.qnid||o.id}`, text: `Sipariş Kalite Onayı Verildi — ${m.orderNo}`, time: `Kayıt: ${o.created_at||''}`, type:'tedarik06', iconClass:'ki-outline ki-medal-star', onclick:()=>this.$router.push({name:'OrderForm',params:{id:m.qnid}}) }; })];
              break;
            case 'orderRejected':
              list = [...list, ...(addNotifications[key]||[]).map(o=>{ const m=parseOrder(o); return { id:`ted07-${o.qnid||o.id}`, text: `Sipariş Reddedildi — ${m.orderNo}`, time: `Kayıt: ${o.created_at||''}`, type:'tedarik07', iconClass:'ki-outline ki-cross-square', onclick:()=>this.$router.push({name:'OrderForm',params:{id:m.qnid}}) }; })];
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
.tedarik01 .notification-icon { background: #eff6ff; color: #2563eb; }
.tedarik02 .notification-icon { background: #fff7ed; color: #f59e0b; }
.tedarik03 .notification-icon { background: #fef3c7; color: #d97706; }
.tedarik04 .notification-icon { background: #ecfdf5; color: #059669; }
.tedarik05 .notification-icon { background: #fef2f2; color: #dc2626; }
.tedarik06 .notification-icon { background: #f0fdf4; color: #16a34a; }
.tedarik07 .notification-icon { background: #fef2f2; color: #991b1b; }

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
.tedarik01 .notification-text::before { background: #2563eb; }
.tedarik02 .notification-text::before { background: #f59e0b; }
.tedarik03 .notification-text::before { background: #d97706; }
.tedarik04 .notification-text::before { background: #059669; }
.tedarik05 .notification-text::before { background: #dc2626; }
.tedarik06 .notification-text::before { background: #16a34a; }
.tedarik07 .notification-text::before { background: #991b1b; }

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
