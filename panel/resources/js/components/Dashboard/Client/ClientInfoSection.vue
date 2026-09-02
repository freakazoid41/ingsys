<template>
  <div class="info-section">
    <!-- Notifications -->
    <div class="info-card notifications-card">
      <div class="info-header">
        <h5 class="section-title mb-0">Bildirimler</h5>
      </div>
      <div class="info-body">
        <div v-if="notificationList.length === 0" style="text-align:center;color:#999;padding:2rem;">
          Yeni bildirim yok
        </div>
        <div
          v-for="(notification, idx) in notificationList.slice(0, 5)"
          :key="notification.id || idx"
          class="info-item"
          @click="notification.onclick && notification.onclick()"
        >
          <div
            class="notification-icon"
            style="background: linear-gradient(135deg, #0d6efd 0%, #0f3a6b 100%);color:#fff;"
          >
            <i :class="notification.iconClass || 'ki-outline ki-bell'" style="font-size:1rem;"></i>
          </div>
          <div class="item-content">
            <p class="item-title">{{ notification.text || notification.title }}</p>
            <small class="item-time">{{ notification.time }}</small>
          </div>
        </div>
      </div>
    </div>

    <!-- My Requests -->
    <div class="info-card requests-card">
      <div class="info-header">
        <h5 class="section-title mb-0">Talepler</h5>
      </div>
      <div class="info-body">
        <div id="request-table"></div>
      </div>
      <router-link :to="{ name: 'RequestList' }" class="notifications-footer-btn">
        Tüm talepleri görüntüle <i class="fa-solid fa-angle-right"></i>
      </router-link>
    </div>

    <div class="info-card requests-card" v-if="sysCode == 'GDZ'">
      <div class="info-header">
        <h5 class="section-title mb-0">Talepler (Rodevans)</h5>
      </div>
      <div class="info-body">
        <div id="request-rodevans-table"></div>
      </div>
      <router-link :to="{ name: 'RequestList' }" class="notifications-footer-btn">
        Tüm talepleri görüntüle <i class="fa-solid fa-angle-right"></i>
      </router-link>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
import Swal from 'sweetalert2';
import PickleTable from 'pickletable';

export default {
  name: 'ClientInfoSection',
  data() {
    const authStore = useAuthStore();
    return {
      authStore,
      sysCode: useNavigationStore().sys_code,
      navigationStore: useNavigationStore(),
      notificationList: [],
      notifications: []
    };
  },
  mounted() {
    this.loadNotifications();
    this.mergeNotifications();
    this.buildRequestTable();
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

      if (typeof this.navigationStore?.getNotifications === 'function') {
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
                } catch (e) { }
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

        const local = (this.notifications || []).map((n, i) => ({
          id: n.id ?? `local-${i}`,
          text: n.title ?? n.text ?? n.message ?? '',
          time: n.time ?? n.created_at ?? '',
          type: n.type ?? 'local',
          iconClass: n.iconClass ?? 'ki-outline ki-bell',
          onclick: n.onclick ?? null
        }));

        list = [...list, ...local];
        this.notificationList = list;
      } catch (e) {
        console.warn('mergeNotifications failed', e);
      }
    },
    showNotifications() {
      this.mergeNotifications();
      const list = (this.notificationList || []).map(n => ({
        title: n.title || n.text || '',
        message: n.message || n.text || '',
        time: n.time || n.date || '',
        iconClass: n.iconClass || 'ki-outline ki-bell',
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
        <div style="text-align:left;max-height:280px;overflow-y:auto;">
          ${list.map((n, idx) => `
            <div class="swal-notification-item" data-index="${idx}" style="margin-bottom:12px;padding:10px;border-radius:8px;border:1px solid #dee2e6;cursor:pointer;transition:background 0.2s;display:flex;align-items:center;">
              <div style="width:10px;height:10px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:linear-gradient(180deg, #0d6efd 0%, #0f3a6b 100%);color:#fff;margin-right:12px;flex-shrink:0;font-size:16px;">
                <i class="${n.iconClass}"></i>
              </div>
              <div style="flex:1;">
                <div style="font-weight:600;margin-bottom:3px;color:#212529;">${n.title}</div>
                <div style="font-size:13px;margin-bottom:5px;color:#6c757d;">${n.message}</div>
                <div style="font-size:11px;color:#6c757d;font-weight:500;">${n.time}</div>
              </div>
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
    },
    openNotifications() {
      this.showNotifications();
    },
    buildRequestTable() {
      const headers = [
        {
          title: 'Talep Kodu',
          key: 'req_no',
          order: true,
          width: '100px',
          type: 'string'
        }, {
          title: 'Talep Başlık',
          key: 'title',
          order: true,
          type: 'string',
        }, {
          title: 'Santral',
          key: 'target_type',
          order: true,
          type: 'string',
        },
      ];

      this.requestTable = new PickleTable({
        container: '#request-table',
        headers: headers,
        pageLimit: 10,
        height: '30vh',
        type: 'ajax',
        columnSearch: false,
        paginationType: 'number',
        ajax: {
          url: '/api/v1/table/documents',
          data: {}
        },
        initialFilter: [
          {
            key: 'form-type',
            type: '=',
            value: 'op-doc-request-form'
          }, {
            key: 'type',
            type: '=',
            value: 'op-doc-request'
          },{
            key: 'is-rodevans',
            type: '=',
            value: 'false'
          }
        ],
        nextPageIcon: '<i class="ki-outline ki-arrow-right"></i>',
        prevPageIcon: '<i class="ki-outline ki-arrow-left"></i>',
        rowFormatter: (elm, data) => {
          JSON.parse(data.main_attr).forEach(element => {
            data[element['Key']] = element['Value'];
          });
          return data;
        },
      });

      if (this.sysCode == 'GDZ') {
        this.requestRodevansTable = new PickleTable({
          container: '#request-rodevans-table',
          headers: headers,
          pageLimit: 10,
          height: '30vh',
          type: 'ajax',
          columnSearch: false,
          paginationType: 'number',
          ajax: {
            url: '/api/v1/table/documents',
            data: {}
          },
          initialFilter: [
            {
              key: 'form-type',
              type: '=',
              value: 'op-doc-request-form'
            }, {
              key: 'type',
              type: '=',
              value: 'op-doc-request'
            },{
              key: 'is-rodevans',
              type: '=',
              value: 'true'
            }
          ],
          nextPageIcon: '<i class="ki-outline ki-arrow-right"></i>',
          prevPageIcon: '<i class="ki-outline ki-arrow-left"></i>',
          rowFormatter: (elm, data) => {
            JSON.parse(data.main_attr).forEach(element => {
              data[element['Key']] = element['Value'];
            });
            return data;
          },
        });
      }
    }
  }
};
</script>

<style scoped>
.info-section {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  margin-bottom: 2.5rem;
}

.info-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.info-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e9ecef;
}

.info-body {
  padding: 1.5rem;
  max-height: 400px;
  overflow-y: auto;
}

.info-item,
.request-item {
  display: flex;
  gap: 1rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid #e9ecef;
  transition: background 0.2s ease;
}

.info-item:last-child,
.request-item:last-child {
  border-bottom: none;
}

.info-item:hover,
.request-item:hover {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 0.75rem;
  cursor: pointer;
}

.notification-icon {
  width: 15px;
  height: 15px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.item-content {
  flex: 1;
}

.item-title {
  font-weight: 600;
  color: #212529;
  font-size: 0.95rem;
  margin: 0 0 0.25rem;
}

.item-desc {
  color: #6c757d;
  font-size: 0.85rem;
  margin: 0;
}

.item-time {
  color: #adb5bd;
  font-size: 0.8rem;
}

.request-info {
  flex: 1;
}

.request-badge {
  padding: 0.35rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  margin-top: 0.25rem;
}

.info-link {
  display: block;
  text-align: center;
  padding: 1rem;
  color: #0d6efd;
  text-decoration: none;
  font-size: 0.9rem;
  border-top: 1px solid #e9ecef;
  transition: all 0.2s ease;
}

.info-link:hover {
  background: #f8f9fa;
  text-decoration: underline;
}

.notifications-footer-btn {
  width: 95%;
  margin: 0 auto 1rem auto;
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

.section-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: #212529;
  margin-bottom: 2rem;
  letter-spacing: -0.5px;
}

@media (max-width: 1024px) {
  .info-section {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 768px) {
  .info-section {
    grid-template-columns: 1fr;
  }

  .section-title {
    font-size: 1.2rem;
  }
}

@media (max-width: 576px) {
  .section-title {
    font-size: 1.1rem;
    margin-bottom: 1rem;
  }
}
</style>
