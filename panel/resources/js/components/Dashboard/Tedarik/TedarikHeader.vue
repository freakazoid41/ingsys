<template>
  <div class="tdk-header">
    <div class="tdk-header__left">
      <h1 class="tdk-header__greeting">{{ greeting }}, <span class="tdk-header__name">{{ userName }}</span></h1>
      <p class="tdk-header__subtitle">Tedarikçi Paneli</p>
    </div>
    <div class="tdk-header__right">
      <div class="tdk-header__video-wrap" @mouseenter="showVideoMenu = true" @mouseleave="showVideoMenu = false">
        <button class="tdk-header__bell tdk-header__video" aria-label="Yardım Videoları" @click="showVideoMenu = !showVideoMenu">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9A2.25 2.25 0 004.5 18.75z"/>
          </svg>
        </button>
        <div v-if="showVideoMenu" class="tdk-video-dropdown" @mouseenter="showVideoMenu = true" @mouseleave="showVideoMenu = false">
          <div v-for="sec in visibleVideoSections" :key="sec.title" class="tdk-video-sec">
            <div class="tdk-video-sec-title">{{ sec.title }}</div>
            <a v-for="it in sec.items" :key="it" href="javascript:;" class="tdk-video-item" @click.prevent="openHelpVideo(it)">{{ it }}</a>
          </div>
        </div>
      </div>
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
import { getCatMeta } from '@/lib/notificationMaps';
import { fmtDateTime } from '@/lib/dateUtils';

export default {
  name: 'TedarikHeader',
  data() {
    return {
      authStore: useAuthStore(),
      navigationStore: useNavigationStore(),
      greeting: 'Hoş Geldiniz',
      notifCount: 0,
      showVideoMenu: false,
      videoSections: [
        { title: 'Tedarikçi', items: ['Tek Parça Sevkiyat', 'Parçalı Sevkiyat', 'Dosya Görüntüleme'] },
        { title: 'Sesli Anlatım', items: ['Sesli Anlatım Eğitim Videosu'] },
        { title: 'İş Birimi', items: ['Doküman Kontrol', 'Aksiyondan Sevkiyat Kapatma', 'Fabrika Kabul Formu İsteme', 'İptal Edilen Dosyayı Tekrar Kabul Etme'] },
      ],
    };
  },
  computed: {
    userName() {
      return this.authStore.userName || this.authStore.currentStatus?.main_name || 'Kullanıcı';
    },
    isTedarikUser() {
      const k = String(this.authStore.typeKey || '').toLowerCase();
      if (!k) return true;
      return k.includes('reseller') || k.includes('tedarik');
    },
    visibleVideoSections() {
      if (this.isTedarikUser) return this.videoSections.filter(s => s.title === 'Tedarikçi');
      return this.videoSections;
    },
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
      // ── build raw items (sorted newest first via created_at + main_id) ──
      const rejected = (this.authStore.currentStatus?.rejectedFiles || []).map(fl => ({
        text: `${fl.title} reddedildi`,
        sub: `${fl.rejected_by ? fl.rejected_by + ' tarafından' : ''}`,
        time: fl.created_at || '',
        fmtTime: fl.created_at ? this.fmtTime(fl.created_at) : (fl.rejected_by ? fl.rejected_by + ' tarafından' : ''),
        rawTime: fl.created_at || 0,
        rawId: 0,
        cat: 'rejected',
        targetQnid: fl.cli_id || '',
        opKey: null,
      }));
      const notifs = this.navigationStore?.notifications || {};
      const items = [];
      const parseOrder = (o)=>{ let orderNo=o.order_no||o.group_key||''; let ctitle=''; try{ JSON.parse(o.main_attr||'[]').forEach(d=>{ if(d.Key==='order_no'&&!orderNo) orderNo=d.Value; if(d.Key==='ctitle'&&!ctitle) ctitle=d.Value; }); }catch(e){} const statusAt = o.last_trans_at || o.created_at || ''; return {orderNo:orderNo||o.qnid||o.id||'-',ctitle,qnid:o.qnid||o.id||o.relation_qnid, main_id:o.main_id||0, created_at:statusAt}; };
      const parseFile = (f)=> { let sAt = f.last_trans_at || ''; if(!sAt && f.last_status){ try{ const j = typeof f.last_status==='string'?JSON.parse(f.last_status):f.last_status; sAt = j.created_at || ''; }catch{} } sAt = sAt || f.created_at || ''; return {orderNo:f.group_key||'-',title:f.type_title||f.file_type||'Dosya',qnid:f.qnid||f.id||f.relation_qnid, main_id:f.main_id||0, created_at:sAt}; };
      const push = (obj, cat, opKey, title) => {
        const meta = getCatMeta(cat);
        items.push({
          text: title,
          sub: obj.ctitle || obj.title || '',
          time: obj.created_at || '',
          fmtTime: obj.created_at ? this.fmtTime(obj.created_at) : '',
          rawTime: obj.created_at || 0,
          rawId: obj.main_id || 0,
          cat, opKey, targetQnid: obj.qnid,
          meta,
          orderNo: obj.orderNo,
        });
      };
      if (Array.isArray(notifs.orderImported)) notifs.orderImported.forEach(o=>{ const m=parseOrder(o); const title = `Sipariş Sisteme Geldi — ${m.orderNo}`; push(m, 'tedarik-01', 'tedarik-01', title); });
      if (Array.isArray(notifs.orderSent)) notifs.orderSent.forEach(o=>{ const m=parseOrder(o); push(m, 'tedarik-02', 'tedarik-02', `Onaya Gönderildi — ${m.orderNo}`); });
      if (Array.isArray(notifs.pendingFiles)) notifs.pendingFiles.forEach(f=>{ const m=parseFile(f); push(m, 'tedarik-03', 'tedarik-03', `İnceleme Bekliyor — ${m.title}`); });
      if (Array.isArray(notifs.fileApproved)) notifs.fileApproved.forEach(f=>{ const m=parseFile(f); push(m, 'tedarik-04', 'tedarik-04', `Dosya Onaylandı — ${m.title}`); });
      if (Array.isArray(notifs.fileRejected)) notifs.fileRejected.forEach(f=>{ const m=parseFile(f); push(m, 'tedarik-05', 'tedarik-05', `Yeniden Talep — ${m.title}`); });
      if (Array.isArray(notifs.orderApproved)) notifs.orderApproved.forEach(o=>{ const m=parseOrder(o); push(m, 'tedarik-06', 'tedarik-06', `Kalite Onayı — ${m.orderNo}`); });
      if (Array.isArray(notifs.orderRejected)) notifs.orderRejected.forEach(o=>{ const m=parseOrder(o); push(m, 'tedarik-07', 'tedarik-07', `Reddedildi — ${m.orderNo}`); });

      const sortByNewest = (a,b)=>{
        const aT = a.rawTime ? new Date(a.rawTime).getTime() : 0;
        const bT = b.rawTime ? new Date(b.rawTime).getTime() : 0;
        if(aT !== bT) return bT - aT;
        const aId = parseInt(a.rawId||0,10), bId = parseInt(b.rawId||0,10);
        if(aId && bId && aId !== bId) return bId - aId;
        return String(b.targetQnid||'').localeCompare(String(a.targetQnid||''));
      };
      items.sort(sortByNewest);
      rejected.sort(sortByNewest);
      const all = [...items, ...rejected].sort(sortByNewest);

      // ── empty state ──
      if (!all.length) {
        const emptyHtml = `
          <div class="tdk-notif-wrap">
            <div class="tdk-notif-head">
              <div class="tdk-notif-head-left">
                <div class="tdk-notif-head-icon"><i class="ki-outline ki-notification-bing" style="font-size:22px"></i></div>
                <div>
                  <h3 class="tdk-notif-head-title">Bildirimler</h3>
                  <p class="tdk-notif-head-sub">Henüz bir şey yok</p>
                </div>
              </div>
              <button class="tdk-notif-head-close" id="tdk-notif-close-empty" aria-label="Kapat"><i class="ki-outline ki-cross" style="font-size:16px"></i></button>
            </div>
            <div class="tdk-notif-empty">
              <div class="tdk-notif-empty-icon"><i class="ki-outline ki-notification-bing"></i></div>
              <h4>Tertemiz — bildirim yok</h4>
              <p>Yeni siparişler, dosya onayları ve sistem güncellemeleri burada görünecek.</p>
            </div>
          </div>`;
        Swal.fire({ html: emptyHtml, width: 440, padding: 0, showConfirmButton: false, showCloseButton: false, background: '#fff', customClass: { popup: 'tdk-notif-popup', htmlContainer: 'tdk-notif-html' },
          didOpen: () => { document.getElementById('tdk-notif-close-empty')?.addEventListener('click', ()=> Swal.close()); }
        });
        return;
      }

      const unread = all.length;
      // ── build cards html ── clean single-line title + meta, no overlapping pills
      const cards = all.map((n, idx) => {
        const meta = n.meta || getCatMeta(n.cat);
        const icon = meta.icon;
        const metaLine = [n.fmtTime || n.time || '', n.sub && n.sub !== n.orderNo ? n.sub : ''].filter(Boolean).map(s=>this.esc(s)).join(' • ');
        return `
        <div class="tdk-n-item tdk-n-item--${n.cat}" data-idx="${idx}" role="button" tabindex="0">
          <div class="tdk-n-icon tdk-n-icon--${n.cat}"><i class="${icon}" style="font-size:18px"></i></div>
          <div class="tdk-n-main">
            <div class="tdk-n-title" title="${this.esc(n.text)}">${this.esc(n.text)}</div>
            ${metaLine ? `<div class="tdk-n-meta" title="${metaLine}"><i class="ki-outline ki-time" style="font-size:11px;opacity:.6"></i> ${metaLine}</div>` : ''}
          </div>
          <div class="tdk-n-arrow"><i class="ki-outline ki-arrow-right" style="font-size:14px"></i></div>
        </div>`;
      }).join('');

      const html = `
        <div class="tdk-notif-wrap">
          <div class="tdk-notif-head">
            <div class="tdk-notif-head-left">
              <div class="tdk-notif-head-icon"><i class="ki-outline ki-notification-bing" style="font-size:22px"></i></div>
              <div>
                <h3 class="tdk-notif-head-title">Bildirimler <span class="tdk-notif-head-badge">${unread}</span></h3>
                <p class="tdk-notif-head-sub">${unread} okunmamış • en yeni üstte</p>
              </div>
            </div>
            <button class="tdk-notif-head-close" id="tdk-notif-close" aria-label="Kapat"><i class="ki-outline ki-cross" style="font-size:14px"></i></button>
          </div>
          <div class="tdk-notif-body" id="tdk-notif-body">${cards}</div>
          <div class="tdk-notif-foot">
            <button class="tdk-notif-btn tdk-notif-btn--ghost" id="tdk-mark-all"><i class="ki-outline ki-check" style="font-size:14px"></i> Tümünü Okundu</button>
            <button class="tdk-notif-btn tdk-notif-btn--primary" id="tdk-go-bilgi">Tümünü Gör <i class="ki-outline ki-arrow-right" style="font-size:14px"></i></button>
          </div>
        </div>`;

      Swal.fire({
        html, width: 460, padding: 0, showConfirmButton: false, showCloseButton: false, background: '#fff',
        customClass: { popup: 'tdk-notif-popup', htmlContainer: 'tdk-notif-html' },
        didOpen: () => {
          document.getElementById('tdk-notif-close')?.addEventListener('click', ()=> Swal.close());
          document.getElementById('tdk-go-bilgi')?.addEventListener('click', ()=>{ Swal.close(); this.$router.push({ name:'TedarikBilgilendirmeler' }).catch(()=>{}); });
          document.getElementById('tdk-mark-all')?.addEventListener('click', async ()=>{
            try{ await this.navigationStore.markAllNotificationsRead(); Swal.close(); }catch(e){ Swal.close(); }
          });
          document.querySelectorAll('.tdk-n-item').forEach(el => {
            const go = () => {
              const idx = Number(el.dataset.idx);
              const item = all[idx];
              if(item?.opKey && item?.targetQnid) this.navigationStore.markNotificationRead(item.opKey, item.targetQnid).catch(()=>{});
              else if(item?.cat === 'rejected' && item?.targetQnid) { /* local only */ }
              if(item?.targetQnid){
                const isFile = ['tedarik-03','tedarik-04','tedarik-05'].includes(item.cat);
                if(isFile) this.$router.push({ name: 'TedarikDForm', params: { id: item.targetQnid } }).catch(()=>{});
                else this.$router.push({ name: 'TedarikOrderForm', params: { id: item.targetQnid } }).catch(()=>{});
              }
              Swal.close();
            };
            el.addEventListener('click', go);
            el.addEventListener('keydown', (e)=>{ if(e.key==='Enter'||e.key===' ') { e.preventDefault(); go(); }});
          });
        }
      });
    },
    openHelpVideo(title){
      const map = {
        'Tek Parça Sevkiyat': '/coaltheme/demoVideos/tekParcaSiparis.mp4',
        'Tek Parca Sevkiyat': '/coaltheme/demoVideos/tekParcaSiparis.mp4',
        'Parçalı Sevkiyat': '/coaltheme/demoVideos/parcaliSiparis.mp4',
        'Parcali Sevkiyat': '/coaltheme/demoVideos/parcaliSiparis.mp4',
        'Dosya Görüntüleme': '/coaltheme/demoVideos/tedarikciDokumanlar.mp4',
        'Dosya Goruntuleme': '/coaltheme/demoVideos/tedarikciDokumanlar.mp4',
        'Sesli Anlatım Eğitim Videosu': '/coaltheme/demoVideos/egitim-video.mp4',
        'Sesli Anlatim Egitim Videosu': '/coaltheme/demoVideos/egitim-video.mp4',
        'Doküman Kontrol': '/coaltheme/demoVideos/dosyaKontrol.mp4',
        'Dokuman Kontrol': '/coaltheme/demoVideos/dosyaKontrol.mp4',
        'Döküman Kontrol': '/coaltheme/demoVideos/dosyaKontrol.mp4',
        'İptal Edilen Dosyayı Tekrar Kabul Etme': '/coaltheme/demoVideos/iptalYenileme.mp4',
        'Iptal Edilen Dosyayi Tekrar Kabul Etme': '/coaltheme/demoVideos/iptalYenileme.mp4',
        'İptal Edilen Dosyayı Yeniden Kabul Etme': '/coaltheme/demoVideos/iptalYenileme.mp4',
        'Iptal Edilen Dosyayi Yeniden Kabul Etme': '/coaltheme/demoVideos/iptalYenileme.mp4',
        'Aksiyondan Sevkiyat Kapatma': '/coaltheme/demoVideos/sevkOnayi.mp4',
        'Aksiyondan Sevkiyat Kapama': '/coaltheme/demoVideos/sevkOnayi.mp4',
      };
      const src = map[title] || '/coaltheme/demoVideos/loginVideo.mp4';
      let fallback = '/coaltheme/demoVideos/loginVideo.mov';
      if(src.includes('tekParca')) fallback = '/coaltheme/demoVideos/tekParcaSiparis.mov';
      else if(src.includes('parcali')) fallback = '/coaltheme/demoVideos/parcaliSiparis.mov';
      else if(src.includes('tedarikciDokumanlar')) fallback = '/coaltheme/demoVideos/tedarikciDokumanlar.mov';
      else if(src.includes('egitim-video')) fallback = '/coaltheme/demoVideos/egitim-video.mp4';
      else if(src.includes('dosyaKontrol')) fallback = '/coaltheme/demoVideos/dosyaKontrol.mp4';
      else if(src.includes('iptalYenileme')) fallback = '/coaltheme/demoVideos/iptalYenileme.mov';
      else if(src.includes('sevkOnayi')) fallback = '/coaltheme/demoVideos/sevkOnayi.mov';
      Swal.fire({
        title: title,
        html: `<div style="border-radius:12px;overflow:hidden;background:#000;"><video controls autoplay playsinline style="width:100%;max-height:62vh;display:block;"><source src="${src}" type="video/mp4"><source src="${fallback}" type="video/quicktime">Tarayıcınız video etiketini desteklemiyor.</video></div><div style="margin-top:10px;font-size:12px;color:#64748b;text-align:center;">${this.esc(title)} — tanıtım videosu</div>`,
        width: '860px',
        padding: '18px 18px 14px',
        showCloseButton: true,
        showConfirmButton: false,
        background: '#fff',
        customClass: { popup: 'tdk-notif-popup' },
        didClose: () => {
          const v = Swal.getHtmlContainer()?.querySelector('video');
          if (v) { v.pause(); v.removeAttribute('src'); v.load(); }
        }
      });
      this.showVideoMenu = false;
    },
    fmtTime(v){
      try { return fmtDateTime(v); } catch { return String(v||'').slice(0,16); }
    },
    esc(s){
      return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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
.tdk-header__right{ display:flex; align-items:center; gap:10px; position:relative; }
.tdk-header__bell {
  width: 44px; height: 44px; border: 1.5px solid #ffedd5; background: #fff; border-radius: 50%;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  color: #FF5A1F; font-size: 21px; position: relative; transition: all 0.2s; box-shadow: 0 1px 4px rgba(255,90,31,.08);
  flex-shrink: 0;
}
.tdk-header__bell:hover { background: #FF5A1F; color: #fff; border-color: #FF5A1F; transform: scale(1.06); box-shadow: 0 4px 12px rgba(255,90,31,.18); }
.tdk-header__bell.has-notif{ border-color: #FF5A1F; background: #fff7ed; }
.tdk-header__bell.has-notif:hover{ background: #FF5A1F; color: #fff; }
.tdk-header__video-wrap{ position:relative; display:flex; }
.tdk-header__video{ border-color:#ffe4cc; color:#9a3412; background:#fff; }
.tdk-header__video:hover{ background:#FF5A1F; color:#fff; border-color:#FF5A1F; }
.tdk-video-dropdown{
  position:absolute; top:52px; right:0; width:300px; background:#fff; border:1px solid #ffe4cc; border-radius:16px;
  box-shadow:0 12px 32px rgba(15,23,42,.12), 0 2px 8px rgba(15,23,42,.06); padding:14px 14px 10px; z-index:40;
}
.tdk-video-sec{ margin-bottom:12px; }
.tdk-video-sec:last-child{ margin-bottom:2px; }
.tdk-video-sec-title{ font-size:13.5px; font-weight:800; color:#FF5A1F; margin:0 0 6px; letter-spacing:-.01em; }
.tdk-video-item{ display:block; font-size:13px; font-weight:500; color:#1e293b; text-decoration:none; padding:5px 8px; border-radius:8px; line-height:1.35; }
.tdk-video-item:hover{ background:#fff7ed; color:#9a3412; }
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

<style>
/* ── SweetAlert overrides for tedarik notifications ── */
.tdk-notif-popup{ border-radius:18px !important; padding:0 !important; overflow:hidden !important; box-shadow:0 24px 64px rgba(15,23,42,.18), 0 8px 24px rgba(15,23,42,.08) !important; border:1px solid #ffe4cc !important; background:#fff !important; }
.tdk-notif-html{ padding:0 !important; margin:0 !important; }
.swal2-html-container.tdk-notif-html{ padding:0 !important; margin:0 !important; }
/* ── modal shell ── */
.tdk-notif-wrap{ width:100%; max-width:460px; margin:0 auto; background:#fff; border-radius:18px; overflow:hidden; text-align:left; }
.tdk-notif-head{ background: linear-gradient(135deg,#fff 0%,#fff7ed 100%); border-bottom:1px solid #ffe4cc; padding:16px 18px; display:flex; align-items:center; justify-content:space-between; gap:14px; }
.tdk-notif-head-left{ display:flex; align-items:center; gap:12px; min-width:0; }
.tdk-notif-head-icon{ width:44px; height:44px; border-radius:13px; background: linear-gradient(135deg,#FF5A1F 0%,#ff8a4a 100%); display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0; box-shadow:0 4px 14px rgba(255,90,31,.22); }
.tdk-notif-head-title{ font-size:15.5px; font-weight:800; color:#1e293b; margin:0; line-height:1.2; display:flex; align-items:center; gap:8px; letter-spacing:-.02em; }
.tdk-notif-head-badge{ background:#FF5A1F; color:#fff; font-size:11px; font-weight:800; padding:3px 7px; border-radius:999px; line-height:1; box-shadow:0 2px 6px rgba(255,90,31,.18); }
.tdk-notif-head-sub{ font-size:11.5px; color:#9a3412; opacity:.68; margin:2px 0 0; font-weight:500; }
.tdk-notif-head-close{ width:34px; height:34px; border-radius:50%; border:1.5px solid #e2e8f0; background:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#64748b; flex-shrink:0; transition:.15s; }
.tdk-notif-head-close:hover{ background:#fff7ed; border-color:#fed7aa; color:#9a3412; transform:rotate(90deg); }
.tdk-notif-body{ max-height:360px; overflow-y:auto; padding:12px 12px 10px; background:#fcfcfe; }
.tdk-notif-body::-webkit-scrollbar{ width:6px; }
.tdk-notif-body::-webkit-scrollbar-thumb{ background:#fed7aa; border-radius:999px; }
.tdk-notif-body::-webkit-scrollbar-thumb:hover{ background:#fdba74; }
.tdk-notif-body::-webkit-scrollbar-track{ background:transparent; }
.tdk-n-item{ display:flex; align-items:center; gap:12px; padding:11px 12px; background:#fff; border:1px solid #f1f5f9; border-left:3px solid transparent; border-radius:13px; cursor:pointer; transition:all .18s cubic-bezier(.2,.8,.2,1); margin-bottom:9px; box-sizing:border-box; }
.tdk-n-item:last-child{ margin-bottom:2px; }
.tdk-n-item:hover{ transform:translateY(-1px); box-shadow:0 8px 22px rgba(15,23,42,.08); border-color:#ffe4cc; border-left-color:#FF5A1F; background:#fff; }
.tdk-n-item:active{ transform:translateY(0); box-shadow:0 2px 8px rgba(15,23,42,.05); }
.tdk-n-icon{ width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; border:1.5px solid; font-size:17px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
.tdk-n-icon--tedarik-01{ background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
.tdk-n-icon--tedarik-02{ background:#fef3c7; border-color:#fde68a; color:#92400e; }
.tdk-n-icon--tedarik-03{ background:#ffedd5; border-color:#fed7aa; color:#9a3412; }
.tdk-n-icon--tedarik-04{ background:#dcfce7; border-color:#86efac; color:#065f46; }
.tdk-n-icon--tedarik-05{ background:#fee2e2; border-color:#fecaca; color:#991b1b; }
.tdk-n-icon--tedarik-06{ background:#d1fae5; border-color:#6ee7b7; color:#064e3b; }
.tdk-n-icon--tedarik-07{ background:#fee2e2; border-color:#fca5a5; color:#7f1d1d; }
.tdk-n-icon--rejected{ background:#fee2e2; border-color:#fecaca; color:#991b1b; }
.tdk-n-main{ flex:1; min-width:0; display:flex; flex-direction:column; gap:3px; }
.tdk-n-title{ font-size:13px; font-weight:700; color:#0f172a; line-height:1.35; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; letter-spacing:-.01em; }
.tdk-n-meta{ font-size:11.5px; color:#64748b; font-weight:500; display:flex; align-items:center; gap:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tdk-n-meta i{ flex-shrink:0; }
.tdk-n-arrow{ width:28px; height:28px; border-radius:50%; background:#f8fafc; border:1.5px solid #f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8; flex-shrink:0; transition:.18s; }
.tdk-n-item:hover .tdk-n-arrow{ background:#FF5A1F; border-color:#FF5A1F; color:#fff; transform:translateX(2px); box-shadow:0 2px 8px rgba(255,90,31,.18); }
.tdk-notif-foot{ display:flex; gap:10px; padding:14px 14px; background:#fff; border-top:1px solid #f1f5f9; }
.tdk-notif-btn{ flex:1; height:42px; border-radius:11px; font-size:13px; font-weight:800; display:flex; align-items:center; justify-content:center; gap:6px; cursor:pointer; transition:.18s; border:1.5px solid transparent; letter-spacing:-.01em; }
.tdk-notif-btn--ghost{ background:#fff; border-color:#e2e8f0; color:#475569; }
.tdk-notif-btn--ghost:hover{ background:#f8fafc; border-color:#cbd5e1; color:#1e293b; transform:translateY(-1px); box-shadow:0 2px 8px rgba(0,0,0,.04); }
.tdk-notif-btn--ghost:active{ transform:translateY(0); }
.tdk-notif-btn--primary{ background:linear-gradient(135deg,#FF5A1F 0%,#ff7a3a 100%); color:#fff; border-color:#FF5A1F; box-shadow:0 3px 10px rgba(255,90,31,.22); }
.tdk-notif-btn--primary:hover{ background:linear-gradient(135deg,#ea4a0f 0%,#ff6a1f 100%); box-shadow:0 6px 16px rgba(255,90,31,.28); transform:translateY(-1px); }
.tdk-notif-btn--primary:active{ transform:translateY(0); }
.tdk-notif-empty{ text-align:center; padding:32px 22px 28px; color:#64748b; background:#fcfcfe; }
.tdk-notif-empty-icon{ width:64px; height:64px; border-radius:20px; background:#fff7ed; border:1.5px solid #fed7aa; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; font-size:28px; color:#FF5A1F; box-shadow:0 4px 12px rgba(255,90,31,.08); }
.tdk-notif-empty h4{ margin:0 0 6px; font-size:15px; font-weight:800; color:#1e293b; }
.tdk-notif-empty p{ margin:0 auto; font-size:13px; line-height:1.5; max-width:280px; color:#94a3b8; }
@media (max-width: 480px){
  .tdk-notif-popup{ width:92% !important; margin:0 auto !important; }
  .tdk-notif-body{ max-height:50vh; padding:10px 10px 8px; }
  .tdk-n-item{ padding:10px 11px; gap:10px; }
  .tdk-n-icon{ width:36px; height:36px; font-size:16px; }
  .tdk-n-title{ white-space:normal; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  .tdk-n-meta{ white-space:normal; display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden; }
}
</style>
