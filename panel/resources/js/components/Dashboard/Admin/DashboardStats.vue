<template>
  <div class="stats-section">
    <div class="stat-card" v-for="statKey in topStatsList" :key="statKey" @click="handleStatAction(statKey)">
      <div class="stat-card-inner">
        <div class="stat-icon-panel">
          <div :class="['stat-icon-box', topStats[statKey].iconClass]">
            <span v-if="topStats[statKey].subtext" class="stat-icon-badge">{{ topStats[statKey].subtext }}</span>
            <i :class="topStats[statKey].keenIcon"></i>
          </div>
        </div>
        <div class="stat-content">
          <div class="stat-header-row">
            <span class="stat-label">{{ topStats[statKey].label }}</span>
          </div>
          <div class="stat-footer-row">
            <p :class="['stat-value', topStats[statKey].valueClass]">
              <span v-if="topStatsLoading[statKey]" class="spinner-border spinner-border-sm me-2" role="status">
                <span class="visually-hidden">Yükleniyor...</span>
              </span>
              <span v-else>{{ topStats[statKey].value }}</span>
            </p>
            <a href="#" @click.prevent="handleStatAction(statKey)" :class="['stat-link', topStats[statKey].linkClass]">→</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Plib from '@/lib/pickle';

export default {
  name: 'DashboardStats',
  data() {
    return {
      topStats: {
        totalRequests: {
          id: 1,
          label: 'Toplam Talep',
          value: 42,
          subtext: 'Bu ay',
          action: 'Detay',
          actionKey: 'requests',
          iconClass: 'primary-icon',
          keenIcon: 'ki-outline ki-document',
          valueClass: 'primary-value',
          linkClass: 'primary-link',
          iconPath: 'M15.5 1h-8C6.12 1 5 2.12 5 3.5v17C5 21.88 6.12 23 7.5 23h8c1.38 0 2.5-1.12 2.5-2.5v-17C18 2.12 16.88 1 15.5 1zm-4 21c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4.5-4H7V4h9v14z'
        },
        totalOffers: {
          id: 2,
          label: 'Toplam Teklif',
          value: 117,
          subtext: 'Bu ay',
          action: 'Detay',
          actionKey: 'totalOffers',
          iconClass: 'success-icon',
          keenIcon: 'ki-outline ki-document',
          valueClass: 'success-value',
          linkClass: 'success-link',
          iconPath: 'M15.5 1h-8C6.12 1 5 2.12 5 3.5v17C5 21.88 6.12 23 7.5 23h8c1.38 0 2.5-1.12 2.5-2.5v-17C18 2.12 16.88 1 15.5 1zm-4 21c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4.5-4H7V4h9v14zM9 11l2 2 4-4'
        },
        approvedOffers: {
          id: 3,
          label: 'Onaylanan Teklif',
          value: 29,
          subtext: 'Bu ay',
          action: 'Detay',
          actionKey: 'approvedOffers',
          iconClass: 'warning-icon',
          keenIcon: 'ki-outline ki-double-check',
          valueClass: 'warning-value',
          linkClass: 'warning-link',
          iconPath: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z'
        },
        allClients: {
          id: 4,
          label: 'Aktif Firma',
          value: 58,
          subtext: 'Toplam',
          action: 'Detay',
          actionKey: 'companies',
          iconClass: 'info-icon',
          keenIcon: 'ki-outline ki-user',
          valueClass: 'info-value',
          linkClass: 'info-link',
          iconPath: 'M15.5 1h-8C6.12 1 5 2.12 5 3.5v17C5 21.88 6.12 23 7.5 23h8c1.38 0 2.5-1.12 2.5-2.5v-17C18 2.12 16.88 1 15.5 1zm-4 21c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4.5-4H7V4h9v14z'
        },
        awaitingOffers: {
          id: 5,
          label: 'Bekleyen Teklifler',
          value: 16,
          subtext: 'Toplam',
          action: 'Detay',
          actionKey: 'awaitingOffers',
          iconClass: 'danger-icon',
          keenIcon: 'ki-outline ki-briefcase',
          valueClass: 'danger-value',
          linkClass: 'danger-link',
          iconPath: 'M15.5 1h-8C6.12 1 5 2.12 5 3.5v17C5 21.88 6.12 23 7.5 23h8c1.38 0 2.5-1.12 2.5-2.5v-17C18 2.12 16.88 1 15.5 1zm-4 21c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4.5-4H7V4h9v14z'
        },
        todaysOffers: {
          id: 6,
          label: 'Bugünkü Teklifler',
          value: 5,
          subtext: 'Toplam',
          action: 'Detay',
          actionKey: 'todaysOffers',
          iconClass: 'secondaray-icon',
          keenIcon: 'ki-outline ki-calendar',
          valueClass: 'secondary-value',
          linkClass: 'secondary-link',
          iconPath: 'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z'
        }
      },
      topStatsList: ['totalRequests', 'totalOffers', 'allClients', 'awaitingOffers', 'todaysOffers'],
      topStatsLoading: {
        totalRequests: false,
        totalOffers: false,
        approvedOffers: false,
        allClients: false,
        awaitingOffers: false,
        todaysOffers: false
      }
    };
  },
  mounted() {
    this.topDataLoad();
  },
  methods: {
    async topDataLoad() {
      // Set all stats to loading state
      Object.keys(this.topStatsLoading).forEach(key => {
        this.topStatsLoading[key] = true;
      });

      // Load top statistics data from API
      try {
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/topstats', method: 'GET' }, null);
        if (rsp && typeof rsp === 'object') {
          // Update topStats with API data
          if (rsp.totalRequests !== undefined) this.topStats.totalRequests.value = rsp.totalRequests;
          if (rsp.totalOffers !== undefined) this.topStats.totalOffers.value = rsp.totalOffers;
          if (rsp.approvedOffers !== undefined) this.topStats.approvedOffers.value = rsp.approvedOffers;
          if (rsp.allClients !== undefined) this.topStats.allClients.value = rsp.allClients;
          if (rsp.awaitingOffers !== undefined) this.topStats.awaitingOffers.value = rsp.awaitingOffers;
          if (rsp.todaysOffers !== undefined) this.topStats.todaysOffers.value = rsp.todaysOffers;

          // Force component update to reflect changes
          this.$forceUpdate();
        }
      } catch (error) {
        console.error('Failed to load top statistics:', error);
        // Keep default values if API fails
      } finally {
        // Stop loading for all stats
        Object.keys(this.topStatsLoading).forEach(key => {
          this.topStatsLoading[key] = false;
        });
      }
    },

    handleStatAction(statKey) {
      // Handle stat card click based on actionKey
      const actionKey = this.topStats[statKey]?.actionKey;
      switch (actionKey) {
        case 'requests':
          this.$router.push({ name: 'OrderList' });
          break;
        case 'totalOffers':
          this.$router.push({ name: 'OrderList' });
          break;
        case 'approvedOffers':
          this.$router.push({ name: 'OrderList' });
          break;
        case 'companies':
          this.$router.push({ name: 'CList' });
          break;
        case 'awaitingOffers':
          this.$router.push({ name: 'OrderList' });
          break;
        case 'todaysOffers':
          this.$router.push({ name: 'OrderList' });
          break;
        default:
          console.log('Unknown action:', actionKey);
      }
    }
  }
};
</script>

<style scoped>
.stats-section {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: #fff;
  padding: 1.5rem;
  border-radius: 24px;
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  text-align: left;
  transition: transform 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease, background 0.35s ease;
  position: relative;
  cursor: pointer;
}

.stat-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 26px 60px rgba(21, 75, 145, 0.14);
  border-color: rgba(21, 75, 145, 0.18);
}

.stat-card-inner {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 1.25rem;
  width: 100%;
  align-items: center;
}

.stat-icon-panel {
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-icon-box {
  position: relative;
  width: 64px;
  height: 64px;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 16px 30px rgba(21, 75, 145, 0.06);
}

.stat-icon-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background: rgba(255, 255, 255, 0.98);
  color: #000;
  padding: 0.25rem 0.65rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 700;
  box-shadow: 0 10px 20px rgba(21, 75, 145, 0.08);
  text-transform: capitalize;
}

.stat-icon-box i {
  font-size: 24px;
  line-height: 1;
  display: inline-block;
  color: inherit;
}

.primary-icon {
  background: rgb(21, 75, 145);
  color: #fff;
}

.success-icon {
  background: rgb(23, 198, 84);
  color: #fff;
}

.warning-icon {
  background: rgb(246, 192, 0);
  color: #fff;
}

.info-icon {
  background: rgb(114, 57, 234);
  color: #fff;
}

.danger-icon {
  background: rgb(248, 40, 89);
  color: #fff;
}

.secondary-icon {
  background: rgba(255, 255, 255, 0.88);
  color: var(--text-secondary);
}

.stat-content {
  width: 100%;
  text-align: left;
}

.stat-header-row {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.stat-label {
  display: block;
  font-size: 0.78rem;
  color: var(--text-secondary);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.stat-subtext-inline {
  background: rgba(21, 75, 145, 0.05);
  color: var(--text-secondary);
  padding: 0.22rem 0.8rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.stat-footer-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: nowrap;
  min-width: 0;
}

.stat-value {
  font-size: 2.5rem;
  font-weight: 800;
  margin: 0;
  line-height: 1.05;
  font-family: 'Inter', sans-serif;
  min-width: 0;
}

.stat-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  text-decoration: none;
  font-weight: 700;
  color: var(--primary-color);
  transition: transform 0.25s ease, color 0.25s ease;
  margin-top: 0;
  white-space: nowrap;
  flex-shrink: 0;
}

.stat-link:hover {
  color: #0f3a6b;
  transform: translateX(4px);
}

.primary-value {
  color: var(--primary-color);
}

.success-value {
  color: var(--success-color);
}

.warning-value {
  color: var(--warning-color);
}

.info-value {
  color: var(--info-color);
}

.danger-value {
  color: var(--danger-color);
}

.secondary-value {
  color: var(--text-secondary);
}

@media (max-width: 1200px) {
  .stat-card {
    flex-direction: column;
    text-align: center;
  }

  .stat-card-inner {
    grid-template-columns: 1fr;
    justify-items: center;
  }

  .stat-header-row {
    justify-content: center;
  }

  .stat-content {
    align-items: center;
  }

  .stat-footer-row {
    justify-content: center;
    gap: 0.5rem;
  }

  .stat-label {
    font-size: 0.7rem;
  }

  .stats-section {
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
  }
}

@media (max-width: 1024px) {
  .stats-section {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }
}

@media (max-width: 768px) {
  .stats-section {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
  }
}

@media (max-width: 576px) {
  .stats-section {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }

  .stat-card {
    padding: 1rem 0.75rem;
  }
}
</style>
