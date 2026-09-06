<script>
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
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
      let list = [];
      const parseOrder = (offr) => {
        let orderNo = offr.order_no || offr.group_key || '';
        let ctitle = offr.ctitle || '';
        let spec = offr.spec_code || '';
        try {
          const arr = JSON.parse(offr.main_attr || '[]');
          arr.forEach(det => {
            if(det.Key === 'order_no' && !orderNo) orderNo = det.Value;
            if(det.Key === 'ctitle' && !ctitle) ctitle = det.Value;
            if(det.Key === 'spec_code' && !spec) spec = det.Value;
          });
        } catch(e){}
        return { orderNo: orderNo || offr.id || '-', ctitle, spec, qnid: offr.qnid || offr.id || offr.relation_qnid };
      };
      const parseFile = (f) => {
        return { orderNo: f.group_key || '-', title: f.type_title || f.file_type || 'Dosya', qnid: f.qnid || f.id || f.relation_qnid, created_at: f.created_at };
      };
      for(let key in this.addNotifications){
        switch (key) {
          case 'orderImported': {
            const rows = this.addNotifications[key] || [];
            list = [...list, ...rows.map(offr => {
              const m = parseOrder(offr);
              const msg = m.ctitle ? `${m.orderNo} — ${m.ctitle}` : `${m.orderNo} SAP üzerinden sisteme geldi`;
              return { title: 'Sipariş Sisteme Geldi (SAP)', message: msg, time: `Kayıt: ${offr.created_at || ''}`, type: 'tedarik01', onclick: () => this.$router.push({ name: 'OrderForm', params: { id: m.qnid } }) };
            })];
            break;
          }
          case 'orderSent': {
            const rows = this.addNotifications[key] || [];
            list = [...list, ...rows.map(offr => {
              const m = parseOrder(offr);
              return { title: 'Sipariş Onaya Gönderildi', message: `${m.orderNo} onaya gönderildi`, time: `Kayıt: ${offr.created_at || ''}`, type: 'tedarik02', onclick: () => this.$router.push({ name: 'OrderForm', params: { id: m.qnid } }) };
            })];
            break;
          }
          case 'pendingFiles': {
            const rows = this.addNotifications[key] || [];
            list = [...list, ...rows.map(f => {
              const m = parseFile(f);
              return { title: 'İnceleme Bekleyen Dosyalar Mevcut', message: `${m.title} — Sipariş ${m.orderNo}`, time: `Kayıt: ${f.created_at || ''}`, type: 'tedarik03', onclick: () => this.$router.push({ name: 'DList' }) };
            })];
            break;
          }
          case 'fileApproved': {
            const rows = this.addNotifications[key] || [];
            list = [...list, ...rows.map(f => {
              const m = parseFile(f);
              return { title: 'Sipariş Dosyası Onaylandı', message: `${m.title} onaylandı — ${m.orderNo}`, time: `Kayıt: ${f.created_at || ''}`, type: 'tedarik04', onclick: () => this.$router.push({ name: 'DForm', params: { id: m.qnid } }) };
            })];
            break;
          }
          case 'fileRejected': {
            const rows = this.addNotifications[key] || [];
            list = [...list, ...rows.map(f => {
              const m = parseFile(f);
              let note = '';
              try { const j = JSON.parse(f.last_status || '{}'); note = j.note || j.title || ''; } catch(e){}
              return { title: 'Sipariş Dosyası Yeniden Talep Edildi', message: `${m.title} yeniden talep — ${m.orderNo}${note ? ' ('+note+')' : ''}`, time: `Kayıt: ${f.created_at || ''}`, type: 'tedarik05', onclick: () => this.$router.push({ name: 'DForm', params: { id: m.qnid } }) };
            })];
            break;
          }
          case 'orderApproved': {
            const rows = this.addNotifications[key] || [];
            list = [...list, ...rows.map(offr => {
              const m = parseOrder(offr);
              return { title: 'Sipariş Kalite Onayı Verildi', message: `${m.orderNo} kalite onayı verildi`, time: `Kayıt: ${offr.created_at || ''}`, type: 'tedarik06', onclick: () => this.$router.push({ name: 'OrderForm', params: { id: m.qnid } }) };
            })];
            break;
          }
          case 'orderRejected': {
            const rows = this.addNotifications[key] || [];
            list = [...list, ...rows.map(offr => {
              const m = parseOrder(offr);
              return { title: 'Sipariş Reddedildi', message: `${m.orderNo} reddedildi`, time: `Kayıt: ${offr.created_at || ''}`, type: 'tedarik07', onclick: () => this.$router.push({ name: 'OrderForm', params: { id: m.qnid } }) };
            })];
            break;
          }
          case 'blink':
            break;
          default:
            break;
        }
      }

      // Merge with rejected files notifications
      list = [...list, ...(this.notifications || [])];
      
      // No notifications
      if(list.length === 0) {
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
                    <div style="text-align:left;max-height:280px;overflow-y:auto;">
                        ${list
                          .map(
                            (n, idx) => `<div class="swal-notification-item" data-index="${idx}" style="margin-bottom:12px;padding:10px;border-radius:8px;border:1px solid #eee;cursor:pointer;transition:background 0.2s;">
                            <div style="font-weight:600;margin-bottom:3px;">${n.title}</div>
                            <div style="font-size:13px;margin-bottom:5px;color:#515151;">${n.message}</div>
                            <div style="font-size:11px;color:#999;">${n.time}</div>
                        </div>`
                          )
                          .join('')}
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
            el.addEventListener('mouseover', () => {
              el.style.background = '#f8f9fa';
            });
            el.addEventListener('mouseout', () => {
              el.style.background = 'transparent';
            });
            el.addEventListener('click', () => {
              const idx = Number(el.dataset.index);
              const item = list?.[idx];
              if (item && typeof item.onclick === 'function') {
                item.onclick();
              }
              Swal.close();
            });
          });
        }
      });
    },
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
