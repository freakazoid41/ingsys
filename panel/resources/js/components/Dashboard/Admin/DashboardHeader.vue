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
import { getCatMeta } from '@/lib/notificationMaps';
import { fmtDateTime } from '@/lib/dateUtils';

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
      // Build normalized list with raw timestamps for correct `order by id desc` (newest first)
      try {
        const addNotifications = this.navigationStore?.notifications || {};
        let list = [];
        const parseOrder = (o) => {
          let orderNo = o.order_no || o.group_key || '';
          let ctitle = '';
          try { JSON.parse(o.main_attr||'[]').forEach(d=>{ if(d.Key==='order_no'&&!orderNo) orderNo=d.Value; if(d.Key==='ctitle'&&!ctitle) ctitle=d.Value; }); } catch(e){}
          return { orderNo: orderNo||o.qnid||o.id||'-', ctitle, qnid: o.qnid||o.id||o.relation_qnid, main_id:o.main_id||0, created_at:o.created_at||'' };
        };
        const parseFile = (f) => ({ orderNo: f.group_key||'-', title: f.type_title||f.file_type||'Dosya', qnid: f.qnid||f.id||f.relation_qnid, main_id:f.main_id||0, created_at:f.created_at||'' });
        for (const key in addNotifications) {
          switch (key) {
            case 'orderImported':
              list = [...list, ...(addNotifications[key]||[]).map(o=>{ const m=parseOrder(o); const sub=m.ctitle; return { id:`ted01-${m.qnid}`, text: `Sipariş Sisteme Geldi (SAP) — ${m.orderNo}${sub?' — '+sub:''}`, sub, orderNo:m.orderNo, time: `Kayıt: ${o.created_at||''}`, fmtTime: o.created_at?this.fmtTime(o.created_at):'', rawTime:o.created_at||'', rawId:m.main_id, type: 'tedarik01', cat:'tedarik-01', opKey:'tedarik-01', targetQnid:m.qnid, qnid:m.qnid, main_id:m.main_id, created_at:o.created_at||'', iconClass: 'ki-outline ki-package', onclick:()=>this.$router.push({name:'OrderForm',params:{id:m.qnid}}) }; })];
              break;
            case 'orderSent':
              list = [...list, ...(addNotifications[key]||[]).map(o=>{ const m=parseOrder(o); return { id:`ted02-${m.qnid}`, text: `Sipariş Onaya Gönderildi — ${m.orderNo}`, sub:m.ctitle||'', orderNo:m.orderNo, time: `Kayıt: ${o.created_at||''}`, fmtTime: o.created_at?this.fmtTime(o.created_at):'', rawTime:o.created_at||'', rawId:m.main_id, type: 'tedarik02', cat:'tedarik-02', opKey:'tedarik-02', targetQnid:m.qnid, qnid:m.qnid, main_id:m.main_id, created_at:o.created_at||'', iconClass: 'ki-outline ki-send', onclick:()=>this.$router.push({name:'OrderForm',params:{id:m.qnid}}) }; })];
              break;
            case 'pendingFiles':
              list = [...list, ...(addNotifications[key]||[]).map(f=>{ const m=parseFile(f); return { id:`ted03-${m.qnid}`, text: `İnceleme Bekleyen Dosyalar — ${m.title} (${m.orderNo})`, sub:`${m.title} • ${m.orderNo}`, orderNo:m.orderNo, time: `Kayıt: ${f.created_at||''}`, fmtTime: f.created_at?this.fmtTime(f.created_at):'', rawTime:f.created_at||'', rawId:m.main_id, type: 'tedarik03', cat:'tedarik-03', opKey:'tedarik-03', targetQnid:m.qnid, qnid:m.qnid, main_id:m.main_id, created_at:f.created_at||'', iconClass: 'ki-outline ki-file', onclick:()=>this.$router.push({name:'DList'}) }; })];
              break;
            case 'fileApproved':
              list = [...list, ...(addNotifications[key]||[]).map(f=>{ const m=parseFile(f); return { id:`ted04-${m.qnid}`, text: `Sipariş Dosyası Onaylandı — ${m.title} (${m.orderNo})`, sub:`${m.title} • ${m.orderNo}`, orderNo:m.orderNo, time: `Kayıt: ${f.created_at||''}`, fmtTime: f.created_at?this.fmtTime(f.created_at):'', rawTime:f.created_at||'', rawId:m.main_id, type: 'tedarik04', cat:'tedarik-04', opKey:'tedarik-04', targetQnid:m.qnid, qnid:m.qnid, main_id:m.main_id, created_at:f.created_at||'', iconClass: 'ki-outline ki-check', onclick:()=>this.$router.push({name:'DForm',params:{id:m.qnid}}) }; })];
              break;
            case 'fileRejected':
              list = [...list, ...(addNotifications[key]||[]).map(f=>{ const m=parseFile(f); return { id:`ted05-${m.qnid}`, text: `Sipariş Dosyası Yeniden Talep — ${m.title} (${m.orderNo})`, sub:`${m.title} • ${m.orderNo}`, orderNo:m.orderNo, time: `Kayıt: ${f.created_at||''}`, fmtTime: f.created_at?this.fmtTime(f.created_at):'', rawTime:f.created_at||'', rawId:m.main_id, type: 'tedarik05', cat:'tedarik-05', opKey:'tedarik-05', targetQnid:m.qnid, qnid:m.qnid, main_id:m.main_id, created_at:f.created_at||'', iconClass: 'ki-outline ki-cross', onclick:()=>this.$router.push({name:'DForm',params:{id:m.qnid}}) }; })];
              break;
            case 'orderApproved':
              list = [...list, ...(addNotifications[key]||[]).map(o=>{ const m=parseOrder(o); return { id:`ted06-${m.qnid}`, text: `Sipariş Kalite Onayı Verildi — ${m.orderNo}`, sub:m.ctitle||'', orderNo:m.orderNo, time: `Kayıt: ${o.created_at||''}`, fmtTime: o.created_at?this.fmtTime(o.created_at):'', rawTime:o.created_at||'', rawId:m.main_id, type: 'tedarik06', cat:'tedarik-06', opKey:'tedarik-06', targetQnid:m.qnid, qnid:m.qnid, main_id:m.main_id, created_at:o.created_at||'', iconClass: 'ki-outline ki-medal-star', onclick:()=>this.$router.push({name:'OrderForm',params:{id:m.qnid}}) }; })];
              break;
            case 'orderRejected':
              list = [...list, ...(addNotifications[key]||[]).map(o=>{ const m=parseOrder(o); return { id:`ted07-${m.qnid}`, text: `Sipariş Reddedildi — ${m.orderNo}`, sub:m.ctitle||'', orderNo:m.orderNo, time: `Kayıt: ${o.created_at||''}`, fmtTime: o.created_at?this.fmtTime(o.created_at):'', rawTime:o.created_at||'', rawId:m.main_id, type: 'tedarik07', cat:'tedarik-07', opKey:'tedarik-07', targetQnid:m.qnid, qnid:m.qnid, main_id:m.main_id, created_at:o.created_at||'', iconClass: 'ki-outline ki-cross-square', onclick:()=>this.$router.push({name:'OrderForm',params:{id:m.qnid}}) }; })];
              break;
            default:
              break;
          }
        }
        const local = (this.notifications || []).map((n, i) => ({
          id: n.id ?? `local-${i}`,
          text: n.title ?? n.text ?? n.message ?? '',
          sub: n.message ?? '',
          orderNo:'', time: n.time ?? n.created_at ?? '', fmtTime: n.time ?? '', rawTime: n.created_at||'', rawId: n.main_id||0,
          type: n.type ?? 'local', cat:'rejected', qnid: n.qnid||'', main_id: n.main_id||0, created_at: n.created_at||'',
          iconClass: 'ki-outline ki-information',
          onclick: n.onclick ?? null
        }));
        list = [...list, ...local];
        // newest first — mirrors SQL order by id desc via created_at + main_id
        list.sort((a,b)=>{
          const aT = a.rawTime ? new Date(a.rawTime).getTime() : (a.created_at ? new Date(a.created_at).getTime() : 0);
          const bT = b.rawTime ? new Date(b.rawTime).getTime() : (b.created_at ? new Date(b.created_at).getTime() : 0);
          if(aT!==bT) return bT - aT;
          const aId = parseInt(a.rawId||a.main_id||0,10), bId = parseInt(b.rawId||b.main_id||0,10);
          if(aId && bId && aId!==bId) return bId - aId;
          return String(b.qnid||b.targetQnid||'').localeCompare(String(a.qnid||a.targetQnid||''));
        });
        this.notificationList = list;
      } catch (e) {
        console.warn('mergeNotifications failed', e);
      }
    },

    showNotifications() {
      this.mergeNotifications();
      const raw = this.notificationList || [];
      // already sorted newest-first in merge; re-sort to be safe and handle direct calls
      const list = [...raw].sort((a,b)=>{
        const aT = a.rawTime ? new Date(a.rawTime).getTime() : (a.created_at ? new Date(a.created_at).getTime() : 0);
        const bT = b.rawTime ? new Date(b.rawTime).getTime() : (b.created_at ? new Date(b.created_at).getTime() : 0);
        if(aT!==bT) return bT - aT;
        const aId = parseInt(a.rawId||a.main_id||0,10), bId = parseInt(b.rawId||b.main_id||0,10);
        if(aId && bId && aId!==bId) return bId - aId;
        return String(b.qnid||'').localeCompare(String(a.qnid||''));
      });

      if (!list || list.length === 0) {
        const emptyHtml = `
          <div class="adm-notif-wrap">
            <div class="adm-notif-head">
              <div class="adm-notif-head-left">
                <div class="adm-notif-head-icon"><i class="ki-outline ki-notification-bing" style="font-size:22px"></i></div>
                <div><h3 class="adm-notif-head-title">Bildirimler</h3><p class="adm-notif-head-sub">Henüz bir şey yok</p></div>
              </div>
              <button class="adm-notif-head-close" id="adm-notif-close-empty"><i class="ki-outline ki-cross" style="font-size:16px"></i></button>
            </div>
            <div class="adm-notif-empty"><div class="adm-notif-empty-icon"><i class="ki-outline ki-notification-circle"></i></div><h4>Tertemiz — bildirim yok</h4><p>Yeni siparişler, dosya onayları ve sistem güncellemeleri burada görünecek.</p></div>
          </div>`;
        Swal.fire({ html: emptyHtml, width: 460, padding:0, showConfirmButton:false, showCloseButton:false, background:'#fff', customClass:{ popup:'adm-notif-popup', htmlContainer:'adm-notif-html' }, didOpen:()=> document.getElementById('adm-notif-close-empty')?.addEventListener('click',()=> Swal.close()) });
        return;
      }

      const cards = list.map((n, idx)=>{
        const cat = n.cat || (n.type==='tedarik01'?'tedarik-01': n.type==='tedarik02'?'tedarik-02': n.type==='tedarik03'?'tedarik-03': n.type==='tedarik04'?'tedarik-04': n.type==='tedarik05'?'tedarik-05': n.type==='tedarik06'?'tedarik-06': n.type==='tedarik07'?'tedarik-07':'tedarik-01');
        const meta = getCatMeta(cat);
        const icon = n.iconClass || meta.icon;
        const title = this.esc(n.text || n.title || n.message || '');
        const sub = [n.fmtTime || n.time || '', n.sub || ''].filter(Boolean).map(s=> this.esc(s)).join(' • ');
        return `
        <div class="adm-n-item adm-n-item--${cat}" data-idx="${idx}" role="button" tabindex="0">
          <div class="adm-n-icon adm-n-icon--${cat}"><i class="${icon}" style="font-size:17px"></i></div>
          <div class="adm-n-main">
            <div class="adm-n-title" title="${title}">${title}</div>
            ${sub ? `<div class="adm-n-meta" title="${sub}"><i class="ki-outline ki-time" style="font-size:11px;opacity:.6"></i> ${sub}</div>` : ''}
          </div>
          <div class="adm-n-arrow"><i class="ki-outline ki-arrow-right" style="font-size:13px"></i></div>
        </div>`;
      }).join('');

      const html = `
        <div class="adm-notif-wrap">
          <div class="adm-notif-head">
            <div class="adm-notif-head-left">
              <div class="adm-notif-head-icon"><i class="ki-outline ki-notification-bing" style="font-size:22px"></i></div>
              <div>
                <h3 class="adm-notif-head-title">Bildirimler <span class="adm-notif-head-badge">${list.length}</span></h3>
                <p class="adm-notif-head-sub">${list.length} okunmamış • en yeni üstte</p>
              </div>
            </div>
            <button class="adm-notif-head-close" id="adm-notif-close"><i class="ki-outline ki-cross" style="font-size:14px"></i></button>
          </div>
          <div class="adm-notif-body">${cards}</div>
          <div class="adm-notif-foot">
            <button class="adm-notif-btn adm-notif-btn--ghost" id="adm-mark-all"><i class="ki-outline ki-check" style="font-size:14px"></i> Tümünü Okundu</button>
            <button class="adm-notif-btn adm-notif-btn--primary" id="adm-close">Kapat</button>
          </div>
        </div>`;

      Swal.fire({ html, width: 460, padding:0, showConfirmButton:false, showCloseButton:false, background:'#fff', customClass:{ popup:'adm-notif-popup', htmlContainer:'adm-notif-html' },
        didOpen: () => {
          document.getElementById('adm-notif-close')?.addEventListener('click',()=> Swal.close());
          document.getElementById('adm-close')?.addEventListener('click',()=> Swal.close());
          document.getElementById('adm-mark-all')?.addEventListener('click', async ()=>{ try{ await this.navigationStore.markAllNotificationsRead(); }catch(e){} Swal.close(); });
          document.querySelectorAll('.adm-n-item').forEach((el)=>{
            const go = ()=>{
              const idx = Number(el.dataset.idx);
              const item = list?.[idx];
              if(item?.opKey && (item?.targetQnid||item?.qnid)) this.navigationStore.markNotificationRead(item.opKey, item.targetQnid||item.qnid).catch(()=>{});
              if(item && typeof item.onclick==='function'){ try{ item.onclick(); }catch(e){} }
              Swal.close();
            };
            el.addEventListener('click', go);
            el.addEventListener('keydown', e=>{ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); go(); }});
          });
        }
      });
    },
    fmtTime(v){ try{ return fmtDateTime(v); }catch{ return String(v||'').replace('Kayıt:','').trim().slice(0,16); } },
    esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); },
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

<style>
/* ── Admin notifications modal — navy enterprise (shared with coalparts) ── */
.adm-notif-popup{ border-radius:18px !important; padding:0 !important; overflow:hidden !important; box-shadow:0 24px 64px rgba(15,23,42,.22), 0 8px 24px rgba(15,23,42,.10) !important; border:1px solid #e2e8f0 !important; background:#fff !important; }
.adm-notif-html{ padding:0 !important; margin:0 !important; }
.swal2-html-container.adm-notif-html{ padding:0 !important; margin:0 !important; }
.adm-notif-wrap{ width:100%; max-width:460px; margin:0 auto; background:#fff; border-radius:18px; overflow:hidden; text-align:left; }
.adm-notif-head{ background: linear-gradient(135deg,#fff 0%,#f1f5f9 100%); border-bottom:1px solid #e2e8f0; border-top:3px solid #154B91; padding:16px 18px; display:flex; align-items:center; justify-content:space-between; gap:14px; }
.adm-notif-head-left{ display:flex; align-items:center; gap:12px; min-width:0; }
.adm-notif-head-icon{ width:44px; height:44px; border-radius:13px; background: linear-gradient(135deg,#154B91 0%,#1e3a8a 100%); display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0; box-shadow:0 4px 14px rgba(21,75,145,.22); }
.adm-notif-head-title{ font-size:15.5px; font-weight:800; color:#0f172a; margin:0; line-height:1.2; display:flex; align-items:center; gap:8px; letter-spacing:-.02em; }
.adm-notif-head-badge{ background:#154B91; color:#fff; font-size:11px; font-weight:800; padding:3px 7px; border-radius:999px; line-height:1; box-shadow:0 2px 6px rgba(21,75,145,.18); }
.adm-notif-head-sub{ font-size:11.5px; color:#475569; opacity:.75; margin:2px 0 0; font-weight:500; }
.adm-notif-head-close{ width:34px; height:34px; border-radius:50%; border:1.5px solid #e2e8f0; background:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#64748b; flex-shrink:0; transition:.15s; }
.adm-notif-head-close:hover{ background:#f1f5f9; border-color:#cbd5e1; color:#0f172a; transform:rotate(90deg); }
.adm-notif-body{ max-height:360px; overflow-y:auto; padding:12px 12px 10px; background:#f8fafc; }
.adm-notif-body::-webkit-scrollbar{ width:6px; }
.adm-notif-body::-webkit-scrollbar-thumb{ background:#cbd5e1; border-radius:999px; }
.adm-notif-body::-webkit-scrollbar-thumb:hover{ background:#94a3b8; }
.adm-notif-body::-webkit-scrollbar-track{ background:transparent; }
.adm-n-item{ display:flex; align-items:center; gap:12px; padding:11px 12px; background:#fff; border:1px solid #e2e8f0; border-left:3px solid transparent; border-radius:13px; cursor:pointer; transition:all .18s cubic-bezier(.2,.8,.2,1); margin-bottom:9px; box-sizing:border-box; }
.adm-n-item:last-child{ margin-bottom:2px; }
.adm-n-item:hover{ transform:translateY(-1px); box-shadow:0 8px 22px rgba(15,23,42,.08); border-color:#cbd5e1; border-left-color:#154B91; background:#fff; }
.adm-n-item:active{ transform:translateY(0); box-shadow:0 2px 8px rgba(15,23,42,.05); }
.adm-n-icon{ width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; border:1.5px solid; font-size:17px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
.adm-n-icon--tedarik-01{ background:#eff6ff; border-color:#dbeafe; color:#1e40af; }
.adm-n-icon--tedarik-02{ background:#fef3c7; border-color:#fde68a; color:#92400e; }
.adm-n-icon--tedarik-03{ background:#fef3c7; border-color:#fde68a; color:#b45309; }
.adm-n-icon--tedarik-04{ background:#dcfce7; border-color:#bbf7d0; color:#166534; }
.adm-n-icon--tedarik-05{ background:#fee2e2; border-color:#fecaca; color:#991b1b; }
.adm-n-icon--tedarik-06{ background:#d1fae5; border-color:#6ee7b7; color:#064e3b; }
.adm-n-icon--tedarik-07{ background:#fee2e2; border-color:#fca5a5; color:#7f1d1d; }
.adm-n-icon--rejected{ background:#fee2e2; border-color:#fecaca; color:#991b1b; }
.adm-n-main{ flex:1; min-width:0; display:flex; flex-direction:column; gap:3px; }
.adm-n-title{ font-size:13px; font-weight:700; color:#0f172a; line-height:1.35; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; letter-spacing:-.01em; }
.adm-n-meta{ font-size:11.5px; color:#64748b; font-weight:500; display:flex; align-items:center; gap:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.adm-n-meta i{ flex-shrink:0; }
.adm-n-arrow{ width:28px; height:28px; border-radius:50%; background:#f1f5f9; border:1.5px solid #e2e8f0; display:flex; align-items:center; justify-content:center; color:#64748b; flex-shrink:0; transition:.18s; }
.adm-n-item:hover .adm-n-arrow{ background:#154B91; border-color:#154B91; color:#fff; transform:translateX(2px); box-shadow:0 2px 8px rgba(21,75,145,.18); }
.adm-notif-foot{ display:flex; gap:10px; padding:14px 14px; background:#fff; border-top:1px solid #e2e8f0; }
.adm-notif-btn{ flex:1; height:42px; border-radius:11px; font-size:13px; font-weight:800; display:flex; align-items:center; justify-content:center; gap:6px; cursor:pointer; transition:.18s; border:1.5px solid transparent; letter-spacing:-.01em; }
.adm-notif-btn--ghost{ background:#fff; border-color:#e2e8f0; color:#475569; }
.adm-notif-btn--ghost:hover{ background:#f8fafc; border-color:#cbd5e1; color:#0f172a; transform:translateY(-1px); box-shadow:0 2px 8px rgba(0,0,0,.04); }
.adm-notif-btn--ghost:active{ transform:translateY(0); }
.adm-notif-btn--primary{ background:linear-gradient(135deg,#154B91 0%,#1e40af 100%); color:#fff; border-color:#154B91; box-shadow:0 3px 10px rgba(21,75,145,.22); }
.adm-notif-btn--primary:hover{ background:linear-gradient(135deg,#0f2f5c 0%,#1e3a8a 100%); box-shadow:0 6px 16px rgba(21,75,145,.28); transform:translateY(-1px); }
.adm-notif-btn--primary:active{ transform:translateY(0); }
.adm-notif-empty{ text-align:center; padding:32px 22px 28px; color:#64748b; background:#f8fafc; }
.adm-notif-empty-icon{ width:64px; height:64px; border-radius:20px; background:#eff6ff; border:1.5px solid #dbeafe; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; font-size:28px; color:#154B91; box-shadow:0 4px 12px rgba(21,75,145,.08); }
.adm-notif-empty h4{ margin:0 0 6px; font-size:15px; font-weight:800; color:#0f172a; }
.adm-notif-empty p{ margin:0 auto; font-size:13px; line-height:1.5; max-width:280px; color:#94a3b8; }
@media (max-width: 480px){
  .adm-notif-popup{ width:92% !important; margin:0 auto !important; }
  .adm-notif-body{ max-height:50vh; padding:10px 10px 8px; }
  .adm-n-item{ padding:10px 11px; gap:10px; }
  .adm-n-icon{ width:36px; height:36px; font-size:16px; }
  .adm-n-title{ white-space:normal; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  .adm-n-meta{ white-space:normal; display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden; }
}
</style>
