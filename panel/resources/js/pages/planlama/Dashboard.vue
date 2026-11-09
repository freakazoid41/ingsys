<template>
  <div class="tdk-dashboard">
    <PlanlamaHeader />
    <PlanlamaStats />

    <div class="tdk-dashboard__grid tdk-dashboard__grid--2">
      <PlanlamaStatusChart />
      <PlanlamaMonthlyChart />
    </div>

    <PlanlamaRecentRequests />

    <div class="tdk-dashboard__grid tdk-dashboard__grid--2">
      <PlanlamaActivity />
      <PlanlamaQuickActions />
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
import PlanlamaHeader from '@/components/Dashboard/Planlama/PlanlamaHeader.vue';
import PlanlamaStats from '@/components/Dashboard/Planlama/PlanlamaStats.vue';
import PlanlamaStatusChart from '@/components/Dashboard/Planlama/PlanlamaStatusChart.vue';
import PlanlamaMonthlyChart from '@/components/Dashboard/Planlama/PlanlamaMonthlyChart.vue';
import PlanlamaRecentRequests from '@/components/Dashboard/Planlama/PlanlamaRecentRequests.vue';
import PlanlamaActivity from '@/components/Dashboard/Planlama/PlanlamaActivity.vue';
import PlanlamaQuickActions from '@/components/Dashboard/Planlama/PlanlamaQuickActions.vue';

export default {
  name: 'PlanlamaDashboard',
  components: {
    PlanlamaHeader,
    PlanlamaStats,
    PlanlamaStatusChart,
    PlanlamaMonthlyChart,
    PlanlamaRecentRequests,
    PlanlamaActivity,
    PlanlamaQuickActions,
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
@media (max-width: 1100px) {
  .tdk-dashboard__grid--2 { grid-template-columns: minmax(0,1fr); }
}
@media (max-width: 768px) {
  .tdk-dashboard { padding: 1rem; gap: 1rem; }
}
</style>
