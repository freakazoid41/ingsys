<template>
  <div class="quick-actions-card">
    <h5 class="card-title">Hızlı İşlemler</h5>
    <div class="quick-actions-grid">
      <button
        v-for="action in quickActions"
        :key="action.id"
        class="quick-action-btn"
        @click="handleQuickAction(action)"
      >
        <div class="quick-action-icon" :style="{ backgroundColor: action.color }">
          <i :class="action.iconClass"></i>
        </div>
        <span class="quick-action-label">{{ action.label }}</span>
      </button>
    </div>
  </div>
</template>

<script>
import Swal from 'sweetalert2';

export default {
  name: 'DashboardQuickActions',
  data() {
    return {
      quickActions: [
        { id: 1, label: 'Yeni Talep Oluştur', color: '#0d6efd', iconClass: 'ki-outline ki-document' },
        { id: 4, label: 'Kullanıcı Ekle', color: '#ffc107', iconClass: 'ki-outline ki-user' }
      ]
    };
  },
  methods: {
    handleQuickAction(action) {
      try {
        switch (action.id) {
          case 1:
            this.$router.push({ name: 'RequestForm' });
            break;
          case 2:
            this.$router.push({ name: 'AnnouncementCreate' });
            break;
          case 3:
            this.$router.push({ name: 'ReportBuilder' });
            break;
          case 4:
            this.$router.push({ name: 'UForm' });
            break;
          default:
            console.log('Quick action not configured:', action);
            Swal.fire({ icon: 'info', title: 'Hızlı işlem', text: 'Bu işlem yapılandırılmamış.' });
        }
      } catch (e) {
        console.warn('Quick action navigation failed', e);
        Swal.fire({ icon: 'error', title: 'Hata', text: 'Hızlı işlem gerçekleştirilemedi.' });
      }
    }
  }
};
</script>

<style scoped>
.quick-actions-card {
  background: white;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.04);
  width: 100%;
}

.card-title {
  margin: 0 0 16px 0;
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
  letter-spacing: 0.3px;
  font-family: 'Inter', sans-serif;
}

.quick-actions-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

@media(max-width:600px) {
  .quick-actions-grid {
    grid-template-columns: 1fr;
  }
}

.quick-action-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding-left:16px;
  cursor: pointer;
  transition: all 0.2s ease;
  background-color: white;
  position: relative;
  text-align: left;
  min-height: 72px;
  font-family: 'Inter', sans-serif;
  font-size: 0.95rem;
  font-weight: 700;
  color: #1e293b;
}

.quick-action-btn::after {
  content: "\f054";
  font-family: "Font Awesome 6 Free";
  font-weight: 900;
  position: absolute;
  right: 18px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 16px;
  color: #07123f;
  opacity: 0.75;
}

.quick-action-btn:hover {
  background-color: #f8fafc;
  border-color: #cbd5e1;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.quick-action-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 20px;
  flex-shrink: 0;
}

.quick-action-icon i {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  line-height: 1;
  color: #fff;
}

.quick-action-label {
  font-size: 0.95rem;
  font-weight: 700;
  color: #1e293b;
  font-family: 'Inter', sans-serif;
}
</style>
