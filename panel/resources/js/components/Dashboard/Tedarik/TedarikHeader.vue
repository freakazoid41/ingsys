<template>
  <div class="tdk-header">
    <div class="tdk-header__left">
      <h1 class="tdk-header__greeting">{{ greeting }}, <span class="tdk-header__name">{{ userName }}</span></h1>
      <p class="tdk-header__subtitle">Tedarikçi Paneli</p>
    </div>
    <div class="tdk-header__right">
      <button @click="showNotifications" class="tdk-header__bell" :class="{ 'has-notif': notifCount > 0 }">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
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
      if (Array.isArray(notifs.orderImported)) count += notifs.orderImported.length;
      if (Array.isArray(notifs.orderSent)) count += notifs.orderSent.length;
      if (Array.isArray(notifs.pendingFiles)) count += notifs.pendingFiles.length;
      if (Array.isArray(notifs.fileApproved)) count += notifs.fileApproved.length;
      if (Array.isArray(notifs.fileRejected)) count += notifs.fileRejected.length;
      if (Array.isArray(notifs.orderApproved)) count += notifs.orderApproved.length;
      if (Array.isArray(notifs.orderRejected)) count += notifs.orderRejected.length;
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
      const parseOrder = (o)=>{ let orderNo=o.order_no||o.group_key||''; let ctitle=''; try{ JSON.parse(o.main_attr||'[]').forEach(d=>{ if(d.Key==='order_no'&&!orderNo) orderNo=d.Value; if(d.Key==='ctitle'&&!ctitle) ctitle=d.Value; }); }catch(e){} return {orderNo:orderNo||o.id||'-',ctitle,qnid:o.qnid||o.id||o.relation_qnid}; };
      const parseFile = (f)=> ({orderNo:f.group_key||'-',title:f.type_title||f.file_type||'Dosya',qnid:f.qnid||f.id||f.relation_qnid});
      if (Array.isArray(notifs.orderImported)) notifs.orderImported.forEach(o=>{ const m=parseOrder(o); items.push({ text: `Sipariş Sisteme Geldi (SAP) — ${m.orderNo}`, time: o.created_at, type: 'ted01', opKey:'tedarik-01', targetQnid:m.qnid }); });
      if (Array.isArray(notifs.orderSent)) notifs.orderSent.forEach(o=>{ const m=parseOrder(o); items.push({ text: `Sipariş Onaya Gönderildi — ${m.orderNo}`, time: o.created_at, type: 'ted02', opKey:'tedarik-02', targetQnid:m.qnid }); });
      if (Array.isArray(notifs.pendingFiles)) notifs.pendingFiles.forEach(f=>{ const m=parseFile(f); items.push({ text: `İnceleme Bekleyen Dosyalar — ${m.title} (${m.orderNo})`, time: f.created_at, type: 'ted03', opKey:'tedarik-03', targetQnid:m.qnid }); });
      if (Array.isArray(notifs.fileApproved)) notifs.fileApproved.forEach(f=>{ const m=parseFile(f); items.push({ text: `Sipariş Dosyası Onaylandı — ${m.title} (${m.orderNo})`, time: f.created_at, type: 'ted04', opKey:'tedarik-04', targetQnid:m.qnid }); });
      if (Array.isArray(notifs.fileRejected)) notifs.fileRejected.forEach(f=>{ const m=parseFile(f); items.push({ text: `Sipariş Dosyası Yeniden Talep — ${m.title} (${m.orderNo})`, time: f.created_at, type: 'ted05', opKey:'tedarik-05', targetQnid:m.qnid }); });
      if (Array.isArray(notifs.orderApproved)) notifs.orderApproved.forEach(o=>{ const m=parseOrder(o); items.push({ text: `Sipariş Kalite Onayı — ${m.orderNo}`, time: o.created_at, type: 'ted06', opKey:'tedarik-06', targetQnid:m.qnid }); });
      if (Array.isArray(notifs.orderRejected)) notifs.orderRejected.forEach(o=>{ const m=parseOrder(o); items.push({ text: `Sipariş Reddedildi — ${m.orderNo}`, time: o.created_at, type: 'ted07', opKey:'tedarik-07', targetQnid:m.qnid }); });
      const all = [...items, ...rejected];
      if (!all.length) {
        Swal.fire({ title: 'Bildirimler', html: '<div style="text-align:center;padding:20px;color:#999;">Bildirim yok</div>', width: 420, showCloseButton: true, showConfirmButton: false });
        return;
      }
      const html = `<div style="max-height:320px;overflow-y:auto;">${all.map((n, idx) => `
        <div class="tdk-notif-item" data-idx="${idx}" style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-bottom:1px solid #f1f1f4;cursor:pointer;border-radius:6px;transition:background .15s;">
          <span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${n.type === 'rejected' ? '#ef4444' : '#f59e0b'}"></span>
          <div style="flex:1"><div style="font-weight:600;font-size:13px;color:#1e293b;">${n.text}</div><div style="font-size:11px;color:#8a94a6;">${n.time||''}</div></div>
        </div>`).join('')}</div><div style="margin-top:12px;text-align:center;"><a href="javascript:;" id="tdk-go-bilgi" style="display:inline-flex;align-items:center;gap:6px;background:#FF5A1F;color:#fff;padding:8px 16px;border-radius:999px;font-size:13px;font-weight:700;text-decoration:none;">Tüm Bilgilendirmeleri Gör →</a></div>`;
      Swal.fire({ title: 'Bildirimler', html, width: 420, showCloseButton: true, showConfirmButton: false,
        didOpen: () => {
          document.querySelectorAll('.tdk-notif-item').forEach(el => {
            el.addEventListener('mouseover', () => el.style.background = '#fff7ed');
            el.addEventListener('mouseout', () => el.style.background = 'transparent');
            el.addEventListener('click', () => {
              const idx = Number(el.dataset.idx);
              const item = all[idx];
              if(item && item.opKey && item.targetQnid){
                this.navigationStore.markNotificationRead(item.opKey, item.targetQnid);
              }
              // Navigate to order
              if(item && item.targetQnid){
                this.$router.push({ name: 'TedarikOrderForm', params: { id: item.targetQnid } });
              }
              Swal.close();
            });
          });
          const goBtn = document.getElementById('tdk-go-bilgi');
          if(goBtn) goBtn.addEventListener('click', ()=>{ Swal.close(); this.$router.push({ name:'TedarikBilgilendirmeler' }).catch(()=>{}); });
        }
      });
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
  width: 44px; height: 44px; border: 1.5px solid #ffedd5; background: #fff; border-radius: 50%;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  color: #FF5A1F; font-size: 21px; position: relative; transition: all 0.2s; box-shadow: 0 1px 4px rgba(255,90,31,.08);
  flex-shrink: 0;
}
.tdk-header__bell:hover { background: #FF5A1F; color: #fff; border-color: #FF5A1F; transform: scale(1.06); box-shadow: 0 4px 12px rgba(255,90,31,.18); }
.tdk-header__bell.has-notif{ border-color: #FF5A1F; background: #fff7ed; }
.tdk-header__bell.has-notif:hover{ background: #FF5A1F; color: #fff; }
.tdk-header__badge {
  position: absolute; top: 3px; right: 3px; width: 12px; height: 12px;
  background: #ef4444; border-radius: 50%; border: 2px solid #fff;
  box-shadow: 0 0 0 0 rgba(239,68,68,.5); animation: tdk-badge-pulse 1.8s infinite;
}
@keyframes tdk-badge-pulse{
  0%{ box-shadow: 0 0 0 0 rgba(239,68,68,.55); transform: scale(1); }
  60%{ box-shadow: 0 0 0 7px rgba(239,68,68,0); transform: scale(1); }
  100%{ box-shadow: 0 0 0 0 rgba(239,68,68,0); transform: scale(1); }
}
</style>
