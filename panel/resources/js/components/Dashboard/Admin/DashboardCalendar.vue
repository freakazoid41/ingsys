<template>
  <div class="calendar-card">
    <div class="card-header">
      <h5 class="card-title">Takvim / Önemli Tarihler</h5>
    </div>
    <div class="notifications-list">
      <div
        v-for="(event, idx) in importantDates"
        :key="event.doc_id ?? idx"
        class="notification-item awaitingUser"
        @click="openImportantDate(event)"
        style="cursor:pointer;"
      >
        <span class="notification-icon">
          <svg fill="currentColor" viewBox="0 0 24 24" width="18" height="18">
            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z" />
          </svg>
        </span>

        <div class="notification-content">
          <p class="notification-text no-before">
            {{ event.text }}
          </p>

          <span class="notification-time no-before">
            {{ event.date }}
          </span>
        </div>
      </div>
    </div>
    <a href="" alt="" title="" class="notifications-footer-btn">Tüm Takvimi Görüntüle <i class="fa-solid fa-angle-right"></i></a>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
import Swal from 'sweetalert2';
import Plib from '@/lib/pickle';

export default {
  name: 'DashboardCalendar',
  data() {
    return {
      authStore: useAuthStore(),
      navigationStore: useNavigationStore(),
      importantDates: []
    };
  },
  mounted() {
    this.importantDatesLoad();
  },
  methods: {
    async importantDatesLoad() {
      try {
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/importantinfo', method: 'GET' }, null);
        if (Array.isArray(rsp)) {
          this.importantDates = rsp.map(it => ({ text: it.text || it.title || '', event: it.event, date: it.date || it.dt || '', doc_id: it.doc_id ?? null, type: it.type ?? null }));
        } else if (rsp && typeof rsp === 'object' && Array.isArray(rsp.data)) {
          this.importantDates = rsp.data.map(it => ({ text: it.text || it.title || '', event: it.event, date: it.date || '', doc_id: it.doc_id ?? null, type: it.type ?? null }));
        }
      } catch (e) {
        console.error('Failed to load important dates:', e);
      }
    },
    openImportantDate(event) {
      try {
        if (!event) return;
        const id = event.doc_id || event.id;
        const type = (event.event || '').toLowerCase();

        if (id) {
          if (type.includes('offer')) {
            this.$router.push({ name: 'OForm', params: { id } });
            return;
          }
          this.$router.push({ name: 'RequestForm', params: { id } });
          return;
        }

        Swal.fire({ title: 'Etkinlik', html: `<div style="text-align:left">${event.text || ''}<br/><small style="color:#666">${event.date || ''}</small></div>` });
      } catch (e) {
        console.warn('openImportantDate failed', e);
      }
    }
  }
};
</script>

<style scoped>
.calendar-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  height: 100%;
  
  border: 1px solid var(--border-color);
  justify-content: space-between;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
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
  max-height: 300px;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 24px;
  padding: 10px;
  position: relative;
  transition: 0.25s ease;
}

.notification-item:not(:last-child) {
  border-bottom: 1px solid #edf1f7;
}

.notification-item:hover {
  background: #fafcff;
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
}

.awaitingUser .notification-icon {
  background: #f3f6ff;
  color: #2563ff;
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

.notification-text.no-before::before {
  content: unset !important;
  display: none;
}

.notification-time.no-before {
  margin-left: unset !important;
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
