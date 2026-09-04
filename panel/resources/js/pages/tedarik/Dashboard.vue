<template>
  <div class="tdk-dashboard">
    <TedarikHeader />
    <TedarikStats />

    <div class="tdk-dashboard__grid">
      <div class="tdk-dashboard__col-main">
        <TedarikRecentOrders />
      </div>
      <div class="tdk-dashboard__col-side">
        <TedarikStatusChart />
        <TedarikQuickActions />
      </div>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
import TedarikHeader from '@/components/Dashboard/Tedarik/TedarikHeader.vue';
import TedarikStats from '@/components/Dashboard/Tedarik/TedarikStats.vue';
import TedarikStatusChart from '@/components/Dashboard/Tedarik/TedarikStatusChart.vue';
import TedarikRecentOrders from '@/components/Dashboard/Tedarik/TedarikRecentOrders.vue';
import TedarikQuickActions from '@/components/Dashboard/Tedarik/TedarikQuickActions.vue';

export default {
  name: 'TedarikDashboard',
  components: {
    TedarikHeader,
    TedarikStats,
    TedarikStatusChart,
    TedarikRecentOrders,
    TedarikQuickActions,
  },
  data() {
    return { authStore: useAuthStore() };
  },
  async mounted() {
    await this.authStore.getPermissions();
  }
};
</script>

<style scoped>
.tdk-dashboard {
  padding: 1.5rem 2rem 3rem;
}
.tdk-dashboard__grid {
  display: grid;
  grid-template-columns: 1.6fr 1fr;
  gap: 1.5rem;
  margin-top: 1.5rem;
}
.tdk-dashboard__col-side {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}
@media (max-width: 1100px) {
  .tdk-dashboard__grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .tdk-dashboard { padding: 1rem; }
}
</style>
