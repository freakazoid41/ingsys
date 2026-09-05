<template>
  <div class="tdk-dashboard">
    <TedarikHeader />
    <TedarikStats />

    <div class="tdk-dashboard__grid tdk-dashboard__grid--2">
      <TedarikStatusChart />
      <TedarikMonthlyChart />
    </div>

    <TedarikRecentOrders />

    <div class="tdk-dashboard__grid tdk-dashboard__grid--2">
      <TedarikFiles />
      <TedarikActivity />
    </div>

    <TedarikQuickActions />
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
import TedarikHeader from '@/components/Dashboard/Tedarik/TedarikHeader.vue';
import TedarikStats from '@/components/Dashboard/Tedarik/TedarikStats.vue';
import TedarikStatusChart from '@/components/Dashboard/Tedarik/TedarikStatusChart.vue';
import TedarikMonthlyChart from '@/components/Dashboard/Tedarik/TedarikMonthlyChart.vue';
import TedarikRecentOrders from '@/components/Dashboard/Tedarik/TedarikRecentOrders.vue';
import TedarikFiles from '@/components/Dashboard/Tedarik/TedarikFiles.vue';
import TedarikActivity from '@/components/Dashboard/Tedarik/TedarikActivity.vue';
import TedarikQuickActions from '@/components/Dashboard/Tedarik/TedarikQuickActions.vue';

export default {
  name: 'TedarikDashboard',
  components: {
    TedarikHeader,
    TedarikStats,
    TedarikStatusChart,
    TedarikMonthlyChart,
    TedarikRecentOrders,
    TedarikFiles,
    TedarikActivity,
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
  padding: 1.5rem 1.25rem 2.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  background: transparent;
  box-sizing: border-box;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  overflow: hidden;
}
.tdk-dashboard, .tdk-dashboard * { box-sizing: border-box; }
.tdk-dashboard__grid { display: grid; gap: 1.25rem; width: 100%; max-width: 100%; min-width: 0; }
.tdk-dashboard__grid--2 { grid-template-columns: minmax(0,1fr) minmax(0,1fr); }
.tdk-dashboard__grid--1-6 { grid-template-columns: minmax(0,1.6fr) minmax(0,1fr); }
@media (max-width: 1100px) {
  .tdk-dashboard__grid--2, .tdk-dashboard__grid--1-6 { grid-template-columns: minmax(0,1fr); }
}
@media (max-width: 768px) {
  .tdk-dashboard { padding: 1rem; gap: 1rem; }
}
</style>
