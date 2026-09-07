<script>
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import { getCatMeta } from '@/lib/notificationMaps';
import { fmtDateTime } from '@/lib/dateUtils';
export default {
  setup() {
    return {
      useNavigationStore,
      useAuthStore,
      Plib,
    };
  },
  data() {
    
    return {
      plib: new Plib(),
      navigationStore: useNavigationStore(),
      authStore: useAuthStore(),
      notifications: [],
      sysCode : document.querySelector('input[name="SYS_CODE"]').value
    };
  },
  mounted() {
    this.loadNotifications();
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
  computed: {
    headerBgStyle() {
      const bgMap = { GDZ: 'gdz.jpg', ADM: 'adm.jpg' };
      const img = bgMap[this.sysCode];
      if (!img) return {};
      return {
        backgroundImage: `linear-gradient(rgba(21,75,145,0.9), rgba(21,75,145,0.9)), url(/coaltheme/${img})`,
        backgroundSize: 'cover',
        backgroundPosition: 'bottom',
      };
    },
    addNotifications() {
      return this.navigationStore?.notifications || {};
    },
    totalNotificationCount() {
      let count = 0;
      const notifs = this.navigationStore?.notifications || {};
      if(Array.isArray(notifs.orderImported)) count += notifs.orderImported.length;
      if(Array.isArray(notifs.orderSent)) count += notifs.orderSent.length;
      if(Array.isArray(notifs.pendingFiles)) count += notifs.pendingFiles.length;
      if(Array.isArray(notifs.fileApproved)) count += notifs.fileApproved.length;
      if(Array.isArray(notifs.fileRejected)) count += notifs.fileRejected.length;
      if(Array.isArray(notifs.orderApproved)) count += notifs.orderApproved.length;
      if(Array.isArray(notifs.orderRejected)) count += notifs.orderRejected.length;
      count += (this.notifications || []).length;
      return count;
    },
    breadcrumbItems() {
      return this.navigationStore?.breadcrumps || [];
    },
    breadcrumbTrail() {
      try { return (this.breadcrumbItems || []).map(i => i.title).join(' / '); } catch (e) { return ''; }
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
      // This is called when navigationStore.notifications changes
      // Ensures component reactivity when API updates
      this.$forceUpdate();
    },
    breadcrumbLink(item) {
      if (!item) return null;
      if (item.route) return item.route;
      if (item.name) return { name: item.name, params: item.params || {} };
      if (item.path) return item.path;
      return null;
    },
    breadcrumbClick(item) {
      const link = this.breadcrumbLink(item);
      if (link) {
        this.$router.push(link).catch(() => {});
        return;
      }
      if (item && typeof item.onclick === 'function') {
        item.onclick();
      }
    },
    notifications() {
      // legacy/no-op
    },
    showNotifications() {
      // ── build unified items (same logic as tedarik, admin routes) ──
      const rejected = (this.notifications || []).map(n => ({
        cat: 'rejected',
        text: n.title || 'Reddedilen Dosya',
        sub: n.message || '',
        time: n.time || '',
        fmtTime: n.time || '',
        rawTime: n.created_at || 0,
        rawId: n.main_id || 0,
        qnid: n.qnid || '',
        opKey: null,
        onclick: n.onclick,
      }));
      let list = [];
      const parseOrder = (offr) => {
        let orderNo = offr.order_no || offr.group_key || '';
        let ctitle = offr.ctitle || '';
        try { const arr = JSON.parse(offr.main_attr || '[]'); arr.forEach(det => { if(det.Key === 'order_no' && !orderNo) orderNo = det.Value; if(det.Key === 'ctitle' && !ctitle) ctitle = det.Value; }); } catch(e){}
        const statusAt = offr.last_trans_at || offr.created_at || '';
        return { orderNo: orderNo || offr.qnid || offr.id || '-', ctitle, qnid: offr.qnid || offr.id || offr.relation_qnid, main_id: offr.main_id || 0, created_at: statusAt };
      };
      const parseFile = (f) => { let sAt = f.last_trans_at || ''; if(!sAt && f.last_status){ try{ const j=typeof f.last_status==='string'?JSON.parse(f.last_status):f.last_status; sAt=j.created_at||''; }catch{} } sAt = sAt || f.created_at || ''; return { orderNo: f.group_key || '-', title: f.type_title || f.file_type || 'Dosya', qnid: f.qnid || f.id || f.relation_qnid, main_id: f.main_id || 0, created_at: sAt }; };
      const catMap = {
        orderImported: { cat:'tedarik-01', opKey:'tedarik-01', label:'Sipariş Sisteme Geldi' },
        orderSent: { cat:'tedarik-02', opKey:'tedarik-02', label:'Onaya Gönderildi' },
        pendingFiles: { cat:'tedarik-03', opKey:'tedarik-03', label:'İnceleme Bekliyor' },
        fileApproved: { cat:'tedarik-04', opKey:'tedarik-04', label:'Dosya Onaylandı' },
        fileRejected: { cat:'tedarik-05', opKey:'tedarik-05', label:'Yeniden Talep' },
        orderApproved: { cat:'tedarik-06', opKey:'tedarik-06', label:'Kalite Onayı' },
        orderRejected: { cat:'tedarik-07', opKey:'tedarik-07', label:'Reddedildi' },
      };
      for(let key in this.addNotifications){
        if(key==='blink' || !(key in catMap)) continue;
        const rows = this.addNotifications[key] || [];
        const cfg = catMap[key];
        rows.forEach(offr => {
          const isFile = key==='pendingFiles'||key==='fileApproved'||key==='fileRejected';
          let title='', message='', qnid='', created_at='', main_id=0, cb=null, orderNo='', sub='';
          if(isFile){
            const m = parseFile(offr);
            qnid=m.qnid; created_at=m.created_at; main_id=m.main_id; orderNo=m.orderNo;
            if(key==='pendingFiles'){ title=`İnceleme Bekliyor — ${m.title}`; message=`Sipariş ${m.orderNo}`; sub=m.title; cb=()=> this.$router.push({ name: 'DList' }); }
            else if(key==='fileApproved'){ title=`Dosya Onaylandı — ${m.title}`; message=m.title; sub=m.title; cb=()=> this.$router.push({ name: 'DForm', params:{ id:m.qnid }}); }
            else { let note=''; try{ const j=JSON.parse(offr.last_status||'{}'); note=j.note||j.title||'';}catch(e){} title=`Yeniden Talep — ${m.title}`; message=`${m.title} — ${m.orderNo}${note?' ('+note+')':''}`; sub=m.title; cb=()=> this.$router.push({ name: 'DForm', params:{ id:m.qnid }}); }
          } else {
            const m = parseOrder(offr);
            qnid=m.qnid; created_at=m.created_at; main_id=m.main_id; orderNo=m.orderNo; sub=m.ctitle;
            if(key==='orderImported'){ const msg = m.ctitle ? `${m.orderNo} — ${m.ctitle}` : `${m.orderNo}`; title=`Sipariş Geldi — ${m.orderNo}`; message=msg; }
            else if(key==='orderSent'){ title=`Onaya Gönderildi — ${m.orderNo}`; message=m.orderNo; }
            else if(key==='orderApproved'){ title=`Kalite Onayı — ${m.orderNo}`; message=m.orderNo; }
            else if(key==='orderRejected'){ title=`Reddedildi — ${m.orderNo}`; message=m.orderNo; }
            cb=()=> this.$router.push({ name: 'OrderForm', params:{ id:m.qnid }});
          }
          list.push({ cat:cfg.cat, opKey:cfg.opKey, label:cfg.label, title, message, sub, orderNo, time:`Kayıt: ${created_at||''}`, fmtTime: created_at ? this.fmtTime(created_at) : '', created_at, main_id, qnid, onclick: cb });
        });
      }
      list = [...list, ...rejected];
      list.sort((a,b)=>{
        const aT = a.created_at ? new Date(a.created_at).getTime() : 0;
        const bT = b.created_at ? new Date(b.created_at).getTime() : 0;
        if(aT!==bT) return bT - aT;
        const aId = parseInt(a.main_id||0,10), bId = parseInt(b.main_id||0,10);
        if(aId && bId && aId!==bId) return bId - aId;
        return String(b.qnid||'').localeCompare(String(a.qnid||''));
      });

      if(list.length === 0){
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
        const meta = getCatMeta(n.cat || 'tedarik-01');
        const icon = meta.icon;
        const subLine = [n.fmtTime || n.time || '', n.sub && n.sub!==n.orderNo ? n.sub : n.message].filter(Boolean).map(s=> this.esc(s)).join(' • ');
        // title is already "Label — orderNo", keep single line
        return `
        <div class="adm-n-item adm-n-item--${n.cat||'tedarik-01'}" data-idx="${idx}" role="button" tabindex="0">
          <div class="adm-n-icon adm-n-icon--${n.cat||'tedarik-01'}"><i class="${icon}" style="font-size:17px"></i></div>
          <div class="adm-n-main">
            <div class="adm-n-title" title="${this.esc(n.title)}">${this.esc(n.title)}</div>
            ${subLine ? `<div class="adm-n-meta" title="${subLine}"><i class="ki-outline ki-time" style="font-size:11px;opacity:.6"></i> ${subLine}</div>` : ''}
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
          document.querySelectorAll('.adm-n-item').forEach(el=>{
            const go = ()=>{
              const idx = Number(el.dataset.idx);
              const item = list?.[idx];
              if(item?.opKey && item?.qnid) this.navigationStore.markNotificationRead(item.opKey, item.qnid).catch(()=>{});
              if(item && typeof item.onclick==='function'){ try{ item.onclick(); }catch(e){} }
              Swal.close();
            };
            el.addEventListener('click', go);
            el.addEventListener('keydown', e=>{ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); go(); }});
          });
        }
      });
    },
    fmtTime(v){ try{ return fmtDateTime(v); }catch{ return String(v||'').slice(0,16); } },
    esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); },
  },
};
</script>

<template>
  <div class="header-mobile py-3">
    <div class="container d-flex flex-stack">
      <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
        <router-link :to="{ name: 'CIndex' }">
          <img alt="Logo" :src="`/coaltheme/${sysCode}.svg`" class="h-35px">
        </router-link>
      </div>

      <button class="btn btn-icon btn-active-color-primary me-n4" id="kt_aside_toggle">
        <i class="ki-solid ki-abstract-14 fs-2x">
        </i>
      </button>
    </div>
  </div>
  <div id="kt_header" class="header py-6 py-lg-0" data-kt-sticky="true" data-kt-sticky-name="header"
    data-kt-sticky-offset="{lg: '300px'}"
    :style="headerBgStyle">
    <div class="header-container container-xxl">
      <div
        class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-20 py-3 py-lg-0 me-3">
        <h1 class="d-flex flex-column text-gray-900 fw-bold my-1 w-100">
          <div class="d-flex align-items-center w-100">
            <nav class="breadcrumb-fancy me-3 d-flex align-items-center" aria-label="breadcrumb">
              <router-link class="crumb-item home" to="/coalpanel" title="Anasayfa">
                <span class="crumb-text">Anasayfa</span>
              </router-link>
              
              <template v-if="breadcrumbItems && breadcrumbItems.length">
                <span class="crumb-sep">›</span>
                <template v-for="(b, idx) in breadcrumbItems" :key="idx">
                  <component :is="breadcrumbLink(b) ? 'router-link' : 'a'"
                             v-bind="breadcrumbLink(b) ? { to: breadcrumbLink(b) } : { href: '#', onClick: (e)=>{ e.preventDefault(); breadcrumbClick(b) } }"
                             class="crumb-item"
                             :title="b.title">
                    <span class="crumb-text" :class="{ 'is-current': idx === breadcrumbItems.length - 1 }">{{ b.title }}</span>
                  </component>
                  <span v-if="idx < breadcrumbItems.length - 1" class="crumb-sep">›</span>
                </template>
              </template>

            </nav>

           
          </div>
        </h1>
      </div>
      <div class="d-flex align-items-center flex-wrap">
        <div class="d-flex align-items-center py-3 py-lg-0">
          <div class="me-3">
            <button @click="showNotifications" class="btn btn-icon btn-header position-relative has-sub-btn">
              <i class="ki-outline ki-notification-bing fs-1"></i>
              
              <span v-if="totalNotificationCount > 0"
                class="badge bg-danger h-5px w-5px position-absolute translate-middle top-0 end-0 d-flex justify-content-center animation-blink align-items-center m-1"
                style="padding: 3px 3px; font-size: 10px; border-radius: 10px;">
                
              </span>
            </button>
        </div>

          <div class="me-3">
            <router-link :to="{ name: 'UForm', params: { id: useAuthStore().personId } }" :class="['btn','btn-icon','has-sub-btn','btn-header']">
              <i class="ki-outline ki-user fs-1">
                
              </i>
            </router-link>
          </div>
          <a href="/logout" class="btn btn-icon btn-header"
            >
            <i class="ki-outline ki-entrance-left fs-2x">
             
            </i>
          </a>

        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.breadcrumb-fancy{
  display:flex;
  align-items:center;
  gap:8px;
  padding:6px 10px;
  border-radius:10px;
  background: linear-gradient(90deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
  border:1px solid rgba(255,255,255,0.04);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  box-shadow: 0 6px 18px rgba(9,10,12,0.04);
}
.breadcrumb-fancy .crumb-list{list-style:none}
.breadcrumb-fancy .crumb-wrap{display:flex;align-items:center}
.breadcrumb-fancy .crumb-item{display:inline-flex;align-items:center;gap:8px;color:var(--bs-body-color,#475569);text-decoration:none;padding:6px 10px;border-radius:8px;max-width:260px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;transition:all .14s ease}
.breadcrumb-fancy .crumb-item svg{opacity:0.85}
.breadcrumb-fancy .crumb-item.home{font-weight:600;color:var(--bs-body-color,#475569)}
.breadcrumb-fancy .crumb-item:hover{background:rgba(99,102,241,0.06);color:var(--bs-primary,#4f46e5)}
.breadcrumb-fancy .crumb-item.is-current{background:linear-gradient(90deg, var(--bs-primary,#4f46e5), rgba(79,70,229,0.85));color:#fff}
.breadcrumb-fancy .crumb-item.is-current .crumb-text{color:#fff;font-weight:700}
.breadcrumb-fancy .crumb-item .crumb-text{font-size:14px;color:inherit}
.breadcrumb-fancy .crumb-sep{color:rgba(15,23,42,0.35);margin:0 6px;display:flex;align-items:center}
.page-title-text .title-main{display:inline-block;font-size:20px;font-weight:800;color:var(--bs-heading-color,#071029);letter-spacing:0.2px}
.page-title-text .title-sub{font-size:13px;color:rgba(7,16,41,0.55);margin-top:4px}

.breadcrumb-fancy .crumb-item{display:inline-flex;align-items:center;gap:8px;color:var(--bs-body-color,#475569);text-decoration:none;padding:6px 10px;border-radius:8px;max-width:260px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;transition:transform .16s cubic-bezier(.2,.9,.2,1), box-shadow .16s ease, background .12s ease}
.breadcrumb-fancy .crumb-item:not(.is-current){opacity:0.92}
.breadcrumb-fancy .crumb-item.is-current{background:linear-gradient(90deg, var(--bs-primary,#4f46e5), rgba(79,70,229,0.85));color:#fff;transform:translateY(-1px)}
.breadcrumb-fancy .crumb-item.is-current .crumb-text{color:#fff;font-weight:700}

/* responsive: show only last crumb text on small screens, keep home icon */
@media (max-width: 767px){
  .breadcrumb-fancy{display: none!important;}
  .breadcrumb-fancy .crumb-text{display:none}
  .breadcrumb-fancy .crumb-item.is-current .crumb-text{display:inline-block}
  .breadcrumb-fancy .crumb-item.home .crumb-text{display:none}
  .breadcrumb-fancy .crumb-sep{margin:0 4px}
  .page-title-text .title-main{font-size:16px}
  .page-title-text .title-sub{display:none}
}

</style>

<style>
/* ── Admin notifications modal — navy enterprise (mirrors tedarik but cooler) ── */
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
