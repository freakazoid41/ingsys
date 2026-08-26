<template>
  <div class="admin-dashboard">
    <!-- Dashboard Header Component -->
    <DashboardHeader />

    <!-- Stats Cards Component -->
    <DashboardStats />

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
      <!-- Row 3: Distribution and Quick Actions -->
      <div class="grid-row-2">
        <!-- Santral Bazlı Dağılım -->
        <div class="grid-col-1-2">
          <DashboardDistribution />
        </div>

        <!-- Right Column: Quick Actions -->
        <div class="grid-col-1-2">
          <DashboardQuickActions />
        </div>
      </div>
      <!-- Row 1: Charts and Notifications -->
      <div class="grid-row">
        <!-- Teklif Süreç Durumu -->
        <div class="grid-col-1-3">
          <DashboardProcessChart />
        </div>

        <!-- Bildirimler -->
        <div class="grid-col-1-3">
          <DashboardNotifications />
        </div>
        <div class="grid-col-1-3">
          <DashboardCalendar />
        </div>
      </div>

      <DashboardRequestTables :sysCode="sysCode" />

      

    </div>
  </div>
</template>

<script>
import { useNavigationStore } from '@/stores/navigation';
import DashboardHeader from './Admin/DashboardHeader.vue';
import DashboardStats from './Admin/DashboardStats.vue';
import DashboardDistribution from './Admin/DashboardDistribution.vue';
import DashboardQuickActions from './Admin/DashboardQuickActions.vue';
import DashboardProcessChart from './Admin/DashboardProcessChart.vue';
import DashboardNotifications from './Admin/DashboardNotifications.vue';
import DashboardCalendar from './Admin/DashboardCalendar.vue';
import DashboardRequestTables from './Admin/DashboardRequestTables.vue';

export default {
    name: 'AdminDashboard',
    components: {
        DashboardHeader,
        DashboardStats,
        DashboardDistribution,
        DashboardQuickActions,
        DashboardProcessChart,
        DashboardNotifications,
        DashboardCalendar,
        DashboardRequestTables
    },
    data() {
        //get dashboard informations
        return {
            sysCode : useNavigationStore().sys_code
        };
    },
    mounted() {
      document.getElementById('kt_content_container').classList.remove('container-xxl');
    },
    beforeUnmount() {
        document.getElementById('kt_content_container').classList.add('container-xxl');
    },
    methods: {}
};
</script>


<style scoped>
/* Notifications are styled in child components */

:root {
  --primary-color: #154B91;
  --success-color: #17C653;
  --warning-color: #F6C000;
  --info-color: #7239EA;
  --danger-color: #F8285A;
  --light-bg: #F9F9F9;
  --dark-text: #071437;
  --text-secondary: #4B5675;
  --border-color: #F1F1F4;
}

.admin-dashboard {
    min-height: 100vh;
    padding: 2rem;
    margin: 50px !important;
}


/* Stats Cards */
.dashboard-grid {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.grid-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
}

.grid-row-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}
@media(max-width:1500px) {
  .grid-row-2 {
    display: flex;
    flex-direction: column;
  }
}

.grid-col-1-3 {
  grid-column: span 1;
}

.grid-col-1-2 {
  grid-column: span 1;
}

/* Cards */
.table-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  height: 100%;
  border: 1px solid var(--border-color);
}

.card-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--dark-text);
  margin: 0 0 1.25rem 0;
  font-family: 'Inter', sans-serif;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
}

.card-header .card-title {
  margin: 0;
}

.card-link {
  text-decoration: none;
  color: var(--primary-color);
  font-size: 0.85rem;
  font-weight: 600;
  transition: all 0.3s ease;
  white-space: nowrap;
}

.card-link:hover {
  color: #0f3a6b;
  transform: translateX(4px);
}



/* Child component styles have been moved into their respective components */



/* Links */
.view-details-link {
  display: block;
  width: 100%;
  font-size: 0.85rem;
  color: var(--primary-color);
  text-decoration: none;
  font-weight: 600;
  transition: all 0.2s ease;
  border-top: 1px solid var(--border-color);
  padding-top: 1rem;
  text-align: center;
  margin-top: auto;
}

.view-details-link:hover {
  color: #0f3a6b;
  text-decoration: none;
}

/* Responsive */
@media (max-width: 1440px) {
  .grid-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 1200px) {
  .grid-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 1024px) {
  .grid-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .admin-dashboard {
    padding: 1rem;
  }


  .grid-row {
    grid-template-columns: 1fr;
    gap: 1.25rem;
  }
}

@media (max-width: 576px) {
  .admin-dashboard {
    padding: 0.75rem;
  }
}
</style>
