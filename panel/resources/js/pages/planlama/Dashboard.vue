<template>
  <div class="planlama-dashboard">
    <h2>Malzeme Planlama</h2>
    <p>Boş iskelet — içerik daha sonra eklenecek.</p>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
export default {
  name: 'PlanlamaDashboard',
  async mounted() {
    try { await useAuthStore().getPermissions(); } catch(e) {}
  },
};
</script>

<style scoped>
.planlama-dashboard { background: #fff; border-radius: 12px; padding: 24px; }
.planlama-dashboard h2 { margin: 0 0 8px; }
.planlama-dashboard p { margin: 0; color: #64748b; }
</style>
