<template>
  <div class="admin-dashboard adm-dashboard">
    <DashboardHeader />

    <!-- 8-card stats (order-centric) -->
    <AdminStats />

    <!-- Row: Status doughnut + Monthly trend -->
    <div class="adm-grid adm-grid--2">
      <AdminStatusChart />
      <AdminMonthlyChart />
    </div>

    <!-- Recent orders full width -->
    <AdminRecentOrders />

    <!-- Row: Files + Activity -->
    <div class="adm-grid adm-grid--2">
      <AdminFiles />
      <AdminActivity />
    </div>

    <!-- Quick actions -->
    <AdminQuickActions />
  </div>
</template>

<script>
import { useNavigationStore } from '@/stores/navigation';
import DashboardHeader from './Admin/DashboardHeader.vue';
import AdminStats from './Admin/AdminStats.vue';
import AdminStatusChart from './Admin/AdminStatusChart.vue';
import AdminMonthlyChart from './Admin/AdminMonthlyChart.vue';
import AdminRecentOrders from './Admin/AdminRecentOrders.vue';
import AdminFiles from './Admin/AdminFiles.vue';
import AdminActivity from './Admin/AdminActivity.vue';
import AdminQuickActions from './Admin/AdminQuickActions.vue';

export default {
    name: 'AdminDashboard',
    components: {
        DashboardHeader,
        AdminStats,
        AdminStatusChart,
        AdminMonthlyChart,
        AdminRecentOrders,
        AdminFiles,
        AdminActivity,
        AdminQuickActions
    },
    data() {
        return {
            sysCode : useNavigationStore().sys_code
        };
    },
    mounted() {
      document.body.classList.add('admin-dashboard-active');
      // remove container-xxl so dashboard can use full wrapper width (not 1320px capped)
      const el = document.getElementById('kt_content_container');
      if(el){
        el.classList.remove('container-xxl');
        el.style.maxWidth = 'none';
        el.style.width = '100%';
        el.style.paddingLeft = '0';
        el.style.paddingRight = '0';
      }
      // neutralize hero-overlap only on marginTop, keep layout intact (don't touch wrapper/simplebar)
      const content = document.getElementById('kt_content');
      if(content){
        content.classList.remove('hero-overlap');
        content.style.marginTop = '0px';
      }
      const header = document.getElementById('kt_header');
      if(header) header.hidden = true;
      // Dashboard.vue re-adds hero-overlap after 500ms — undo it
      this._heroFix = setTimeout(()=>{
        const c2 = document.getElementById('kt_content');
        if(c2){ c2.classList.remove('hero-overlap'); c2.style.marginTop='0px'; }
      }, 650);
    },
    beforeUnmount() {
        document.body.classList.remove('admin-dashboard-active');
        const el = document.getElementById('kt_content_container');
        if(el){
          el.classList.add('container-xxl');
          el.style.maxWidth=''; el.style.width=''; el.style.paddingLeft=''; el.style.paddingRight='';
        }
        const content = document.getElementById('kt_content');
        if(content){
          content.classList.add('hero-overlap');
          content.style.marginTop='-100px';
        }
        const header = document.getElementById('kt_header');
        if(header) header.hidden = false;
        if(this._heroFix) clearTimeout(this._heroFix);
    },
    methods: {}
};
</script>


<style scoped>
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
.adm-dashboard {
  width: 100%;
  max-width: 100%;
  height: calc(100vh - 40px);
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  background: #f8fafc;
  box-sizing: border-box;
  overflow: auto;
  min-width: 0;
  flex: 1 1 auto;
}
.adm-dashboard, .adm-dashboard * { box-sizing: border-box; }
.adm-grid { display: grid; gap: 1.25rem; width: 100%; max-width: 100%; min-width: 0; }
.adm-grid--2 { grid-template-columns: minmax(0,1fr) minmax(0,1fr); }
.adm-grid--3 { grid-template-columns: repeat(3, minmax(0,1fr)); }
@media (max-width: 1100px){
  .adm-grid--2, .adm-grid--3 { grid-template-columns: minmax(0,1fr); }
}
@media (max-width: 768px){
  .adm-dashboard { padding: 1rem; gap: 1rem; }
}
</style>

<style>
/* Fix: aside-fixed makes sidebar position:fixed (out of flex flow) → wrapper goes under it.
   Force aside to participate in flex ONLY when admin-dashboard is active, so mobile drawer still works elsewhere. */
@media (min-width: 992px) {
  body.admin-dashboard-active.aside-fixed .aside {
    position: relative !important;
    top: auto !important;
    left: auto !important;
    bottom: auto !important;
    flex-shrink: 0 !important;
    align-self: stretch !important;
    min-height: 100vh !important;
  }
  body.admin-dashboard-active.aside-fixed .page {
    display: flex !important;
    flex-direction: row !important;
    min-height: 100vh !important;
    align-items: stretch !important;
  }
  body.admin-dashboard-active.aside-fixed #kt_wrapper {
    flex: 1 1 0% !important;
    min-width: 0 !important;
    width: auto !important;
    max-width: none !important;
    display: flex !important;
    flex-direction: column !important;
    min-height: 100vh !important;
  }
  body.admin-dashboard-active.aside-fixed #kt_content {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    max-width: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    min-height: 0 !important;
  }
  body.admin-dashboard-active.aside-fixed #kt_content_container {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    max-width: 100% !important;
    display: flex !important;
    flex-direction: column !important;
  }
  body.admin-dashboard-active.aside-fixed .simplebar-content-wrapper,
  body.admin-dashboard-active.aside-fixed .simplebar-content,
  body.admin-dashboard-active.aside-fixed .dashboard-page {
    flex: 1 1 auto !important;
    min-height: 0 !important;
  }
}
</style>
