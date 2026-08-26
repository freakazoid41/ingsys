<template>
  <div class="stats-section">
    <div class="stat-card stat-card-primary">
      <div class="stat-icon-box primary-icon">
        <i class="ki-outline ki-time"></i>
      </div>
      <div class="stat-content">
        <span class="stat-label">İncelenen Teklifler</span>
        <p class="stat-value primary-value">{{ stats.pendingOffers }}</p>
        <router-link :to="{ name: 'OList' }" class="stat-link primary-link">Detayları gör →</router-link>
      </div>
    </div>

    <div class="stat-card stat-card-danger">
      <div class="stat-icon-box danger-icon">
        <i class="ki-outline ki-cross"></i>
      </div>
      <div class="stat-content">
        <span class="stat-label">Reddedilen Teklifler</span>
        <p class="stat-value danger-value">{{ stats.rejectedOffers ?? 0 }}</p>
        <router-link :to="{ name: 'OList' }" class="stat-link danger-link">Detayları gör →</router-link>
      </div>
    </div>

    <div class="stat-card stat-card-success">
      <div class="stat-icon-box success-icon">
        <i class="ki-outline ki-check"></i>
      </div>
      <div class="stat-content">
        <span class="stat-label">Onaylanan Teklifler</span>
        <p class="stat-value success-value">{{ stats.approvedOffers ?? 0 }}</p>
        <router-link :to="{ name: 'OList' }" class="stat-link success-link">Detayları gör →</router-link>
      </div>
    </div>

    <div class="stat-card stat-card-warning">
      <div class="stat-icon-box warning-icon">
        <i class="ki-outline ki-information"></i>
      </div>
      <div class="stat-content">
        <span class="stat-label">Revize Bekleyen</span>
        <p class="stat-value warning-value">{{ stats.revisionsNeeded }}</p>
        <router-link :to="{ name: 'OList' }" class="stat-link warning-link">Detayları gör →</router-link>
      </div>
    </div>
  </div>
</template>

<script>
import Plib from '@/lib/pickle';

export default {
  name: 'ClientStats',
  data() {
    return {
      stats: {
        approvedOffers: 0,
        pendingOffers: 0,
        revisionsNeeded: 0,
        rejectedOffers: 0
      }
    };
  },
  mounted() {
    this.loadStats();
  },
  methods: {
    async loadStats() {
      try {
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/monthlyoffers/client', method: 'GET' }, null);

        if (Array.isArray(rsp)) {
          rsp.forEach(item => {
            switch (item.key) {
              case 'doc_trans_offer_approved':
                this.stats.approvedOffers = item.value || 0;
                break;
              case 'doc_trans_offer_review':
                this.stats.pendingOffers = item.value || 0;
                break;
              case 'doc_trans_offer_revision':
                this.stats.revisionsNeeded = item.value || 0;
                break;
              case 'doc_trans_offer_rejected':
                this.stats.rejectedOffers = item.value || 0;
                break;
            }
          });
        }
      } catch (error) {
        console.warn('Failed to load stats:', error);
      }
    }
  }
};
</script>

<style scoped>
.stats-section {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 2rem;
  margin-bottom: 2.5rem;
  background-color: #fff;
  border-radius: 15px;
  padding: 2rem;
}

.stat-card {
  display: flex;
  align-items: center;
  text-align: center;
  gap: 1.2rem;
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  border: 1px solid transparent;
  position: relative;
  width: 100%;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  opacity: 0;
  transition: opacity 0.35s ease;
}

.stat-card-primary::before {
  background: linear-gradient(90deg, transparent 0%, #0d6efd 50%, transparent 100%);
}

.stat-card-primary {
  color: #0d6efd;
}

.stat-card-success::before {
  background: linear-gradient(90deg, transparent 0%, #198754 50%, transparent 100%);
}

.stat-card-success {
  color: #198754;
}

.stat-card-warning::before {
  background: linear-gradient(90deg, transparent 0%, #ffc107 50%, transparent 100%);
}

.stat-card-warning {
  color: #ffc107;
}

.stat-card-danger::before {
  background: linear-gradient(90deg, transparent 0%, #dc3545 50%, transparent 100%);
}

.stat-card-danger {
  color: #dc3545;
}

.stat-card-info::before {
  background: linear-gradient(90deg, transparent 0%, #0dcaf0 50%, transparent 100%);
}

.stat-card-info {
  color: #0dcaf0;
}

.stat-icon-box {
  width: 70px;
  height: 80px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  position: relative;
  transition: all 0.35s ease;
  font-weight: 600;
  font-size: 2rem;
}

.stat-icon-box i {
  font-size: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-icon-box::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 14px;
  opacity: 0;
  transition: opacity 0.35s ease;
}

.primary-icon {
  background: linear-gradient(135deg, #e7f1ff 0%, #f0f7ff 100%);
  color: #0d6efd;
  box-shadow: inset 0 2px 4px rgba(13, 110, 253, 0.1);
}

.primary-icon::after {
  box-shadow: inset 0 0 20px rgba(13, 110, 253, 0.15);
}

.success-icon {
  background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f6 100%);
  color: #198754;
  box-shadow: inset 0 2px 4px rgba(25, 135, 84, 0.1);
}

.success-icon::after {
  box-shadow: inset 0 0 20px rgba(25, 135, 84, 0.15);
}

.warning-icon {
  background: linear-gradient(135deg, #fff3e0 0%, #fff9f4 100%);
  color: #ff9800;
  box-shadow: inset 0 2px 4px rgba(255, 152, 0, 0.1);
}

.warning-icon::after {
  box-shadow: inset 0 0 20px rgba(255, 152, 0, 0.15);
}

.danger-icon {
  background: linear-gradient(135deg, #ffe5e5 0%, #fff0f0 100%);
  color: #dc3545;
  box-shadow: inset 0 2px 4px rgba(220, 53, 69, 0.1);
}

.danger-icon::after {
  box-shadow: inset 0 0 20px rgba(220, 53, 69, 0.15);
}

.info-icon {
  background: linear-gradient(135deg, #e1f5ff 0%, #f0f8ff 100%);
  color: #0dcaf0;
  box-shadow: inset 0 2px 4px rgba(13, 202, 240, 0.1);
}

.info-icon::after {
  box-shadow: inset 0 0 20px rgba(13, 202, 240, 0.15);
}

.stat-card:hover .stat-icon-box {
  transform: scale(1.08) rotate(-2deg);
}

.stat-card:hover .stat-icon-box::after {
  opacity: 1;
}

.stat-content {
  flex: 1;
  min-width: 0;
  width: 100%;
  display: flex;
  flex-direction: column;
  text-align: left;
}

.stat-label {
  display: block;
  font-size: 0.9rem;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  font-weight: 900;
  color: #000;
}

.stat-value {
  font-size: 2.5rem;
  font-weight: 800;
  margin: 0.5rem 0;
  letter-spacing: -1px;
  line-height: 2rem;
}

.stat-link {
  display: inline-block;
  font-size: 0.85rem;
  text-decoration: none;
  font-weight: 600;
  padding: 0.4rem 0;
  transition: all 0.25s ease;
  position: relative;
}

.stat-link::after {
  content: '';
  position: absolute;
  bottom: -2px;
  left: 0;
  right: 0;
  height: 2px;
  background: currentColor;
  transform: scaleX(0);
  transform-origin: right;
  transition: transform 0.25s ease;
}

.stat-link:hover::after {
  transform: scaleX(1);
  transform-origin: left;
}

.primary-link {
  color: #0d6efd;
}

.success-link {
  color: #198754;
}

.warning-link {
  color: #ffc107;
}

.danger-link {
  color: #dc3545;
}

.danger-value {
  color: #dc3545;
  background: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%);
  -webkit-background-clip: text;
  background-clip: text;
}

.success-value {
  color: #198754;
  background: linear-gradient(135deg, #198754 0%, #145a32 100%);
  -webkit-background-clip: text;
  background-clip: text;
}

.warning-value {
  color: #ffc107;
  background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
  -webkit-background-clip: text;
  background-clip: text;
}

@media (max-width: 768px) {
  .stats-section {
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
  }

  .stat-card {
    padding: 1.5rem;
    gap: 1rem;
  }

  .stat-icon-box {
    width: 70px;
    height: 70px;
  }

  .stat-value {
    font-size: 2rem;
  }
}

@media (max-width: 576px) {
  .stats-section {
    grid-template-columns: 1fr;
    gap: 1.2rem;
  }

  .stat-card {
    padding: 1.25rem;
  }

  .stat-icon-box {
    width: 65px;
    height: 65px;
  }

  .stat-value {
    font-size: 1.75rem;
  }
}
</style>
