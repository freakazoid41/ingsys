<template>
  <div class="dashboard-container">
    <ClientHeader
      :has-notifications="hasNotifications"
      @open-notifications="openNotifications"
      @open-profile="openProfile"
    />
    <ClientStats />
    <ClientQuickOps />
    <ClientInfoSection ref="infoSection" />
    <ClientOfferTable />
  </div>
</template>

<style scoped>
:root {
  --primary-color: #0d6efd;
  --success-color: #198754;
  --warning-color: #ffc107;
  --info-color: #0dcaf0;
  --danger-color: #dc3545;
  --light-bg: #f8f9fa;
  --dark-text: #212529;
}

/* Ensure colors are available at component level */
:host {
  --primary-color: #0d6efd;
  --success-color: #198754;
  --warning-color: #ffc107;
  --info-color: #0dcaf0;
  --danger-color: #dc3545;
  --light-bg: #f8f9fa;
  --dark-text: #212529;
}

:deep(.status-pill) {
  display: inline-flex;
  align-items: center;
  height: 26px;
  padding: 0 12px;
  border-radius: 20px;
  font-size: .78rem;
  font-weight: 700;
  border: 1px solid transparent;
  cursor: pointer;
  white-space: nowrap;
}

:deep(.status-pill--success) {
  background: rgba(23, 198, 83, .1);
  color: #198754;
  border-color: rgba(23, 198, 83, .25);
}

:deep(.status-pill--danger) {
  background: rgba(248, 40, 90, .1);
  color: #dc3545;
  border-color: rgba(248, 40, 90, .25);
}

:deep(.status-pill--warning) {
  background: rgba(246, 192, 0, .1);
  color: #ffc107;
  border-color: rgba(246, 192, 0, .25);
}

:deep(.status-pill--secondary) {
  background: #f8f9fa;
  color: #6c757d;
  border-color: #dee2e6;
}

:deep(.pickletable th),
:deep(.pickletable td) {
  white-space: nowrap;
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
}

:deep(.pickletable thead) {
  --bs-emphasis-color: rgba(255, 255, 255, .6);
}

:deep(.pickletable thead tr) {
  background: #154b91 !important;
}

:deep(.pickletable thead th) {
  background: #154b91 !important;
  color: rgba(255, 255, 255, .85) !important;
  font-size: .82rem !important;
  font-weight: 600 !important;
  letter-spacing: .04em;
  text-transform: uppercase;
  padding: 13px 16px !important;
  border: none !important;
  border-right: 1px solid rgba(255, 255, 255, .1) !important;
  white-space: nowrap;
}

:deep(.pickletable thead th:last-child) {
  border-right: none !important;
}

:deep(.pickletable thead th svg),
:deep(.pickletable thead th i) {
  color: rgba(255, 255, 255, .6) !important;
  background: transparent !important;
}

:deep(.pickletable thead th input) {
  background: rgba(255, 255, 255, .1) !important;
  border: 1px solid rgba(255, 255, 255, .2) !important;
  border-radius: 5px !important;
  color: #fff !important;
  font-size: .78rem !important;
  padding: 4px 8px !important;
  margin-top: 6px !important;
  width: 100% !important;
  outline: none !important;
}

:deep(.pickletable thead th input::placeholder) {
  color: rgba(255, 255, 255, .4) !important;
}

:deep(.pickletable thead th input:focus) {
  background: rgba(255, 255, 255, .18) !important;
  border-color: rgba(255, 255, 255, .4) !important;
}

:deep(.pickletable tbody tr) {
  border-bottom: 1px solid #eef0f4 !important;
  background: #fff !important;
  transition: background .12s;
}

:deep(.pickletable tbody tr:hover) {
  background: #f7f9fd !important;
}

:deep(.pickletable tbody td) {
  padding: 12px 16px !important;
  font-size: .9rem !important;
  color: #2d3748 !important;
  background: transparent !important;
  border: none !important;
  border-right: 1px solid #f0f2f7 !important;
  vertical-align: middle !important;
}

:deep(.pickletable tbody td:last-child) {
  border-right: none !important;
}



.dashboard-container {
  min-height: 100vh;
  padding: 2rem;
}

/* Header Section */

/* Stats Cards */
.stats-section {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 2rem;
  margin-bottom: 2.5rem;
  background-color: #fff;
  border-radius: 15px;
  padding: 2rem;
}



.stat-missing-badge {
  display: inline-block;
  background: linear-gradient(135deg, #ffe5e5 0%, #ffd7d7 100%);
  color: #dc3545;
  padding: 0.4rem 0.85rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 700;
  margin: 0.75rem auto;
  letter-spacing: 0.3px;
  box-shadow: 0 2px 4px rgba(220, 53, 69, 0.15);
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

.info-link {
  color: #0dcaf0;
}

/* Table Section *//* Table Section */
.table-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.table-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e9ecef;
}

.table-responsive {
  overflow-x: auto;
}

.offers-table {
  width: 100%;
  border-collapse: collapse;
  margin: 0;
}

.offers-table thead {
  background: linear-gradient(135deg, #1a3a52 0%, #2d5a7b 100%);
}

.offers-table th {
  padding: 1.25rem;
  color: white;
  font-weight: 600;
  text-align: left;
  font-size: 0.9rem;
}

.offers-table tbody tr {
  border-bottom: 1px solid #e9ecef;
  transition: background 0.2s ease;
}

.offers-table tbody tr:hover {
  background: #f8f9fa;
}

.offers-table td {
  padding: 1rem 1.25rem;
  color: #212529;
  font-size: 0.9rem;
}

.offers-table td.fw-medium {
  font-weight: 600;
  color: #0d6efd;
}

.action-btn {
  background: none;
  border: none;
  color: #0d6efd;
  cursor: pointer;
  padding: 0.5rem;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.action-btn:hover {
  color: #0d6efd;
  transform: scale(1.1);
  opacity: 0.8;
}

.table-footer {
  padding: 1.5rem;
  border-top: 1px solid #e9ecef;
  text-align: center;
}

.view-all-link {
  color: #0d6efd;
  text-decoration: none;
  font-size: 0.9rem;
  transition: all 0.2s ease;
}

.view-all-link:hover {
  text-decoration: underline;
  opacity: 0.8;
}

/* Responsive */
@media (max-width: 1024px) {
  .stats-section {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .dashboard-container {
    padding: 1.5rem;
  }

  .section-title {
    font-size: 1.2rem;
  }
}

@media (max-width: 576px) {
  .dashboard-container {
    padding: 1rem;
  }

  .section-title {
    font-size: 1.1rem;
    margin-bottom: 1rem;
  }
}

.notifications-all-btn {
    height: 52px;
    padding: 0 24px;
    border-radius: 18px;
    border: 1px solid #e5eaf6;
    background: #fff;
    color: #2563ff;
    font-weight: 600;
    font-size: 18px;

    display: flex;
    align-items: center;
    gap: 12px;

    cursor: pointer;
    transition: 0.3s;
}

.notifications-all-btn:hover {
    background: #f7f9ff;
    transform: translateY(-2px);
}

.notifications-list {
    background: #fff;
    border: 1px solid #edf1f7;
    border-radius: 10px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: 24px;

    padding: 30px 28px;
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

.notification-icon i{
    display: flex;
    align-items: center;
    justify-content: center;
}

.awaitingUser .notification-icon {
    background: #f3f6ff;
    color: #2563ff;
}

.clientChange .notification-icon {
    background: #eefbf2;
    color: #0ea85d;
}

.newOffer .notification-icon {
    background: #fff7ed;
    color: #f97316;
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

.notification-text::before {
    content: "";
    min-width: 12px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-top: 2.5px;
}

.awaitingUser .notification-text::before {
    background: #2563ff;
}

.clientChange .notification-text::before {
    background: #0ea85d;
}

.newOffer .notification-text::before {
    background: #f97316;
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

/* .notification-time::before {
    content: "\f073";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    font-size: 18px;
} */
.notification-item::after {
    content: "\f054";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;

    position: absolute;
    right: 30px;
    top: 50%;
    transform: translateY(-50%);

    font-size: 18px;
    color: #1e293b;
    opacity: 0.8;
}

/* ALT BUTON */
.notifications-footer {
    padding: 24px;
    border-top: 1px solid #edf1f7;
    background: #fff;
}

.notifications-footer-btn {
        width: 95%;
    margin: 0 auto 1rem auto;
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

.pickletable{
  box-shadow: unset!important;
  border: unset!important;
}
</style>

<script>
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
import ClientHeader from './Client/ClientHeader.vue';
import ClientStats from './Client/ClientStats.vue';
import ClientQuickOps from './Client/ClientQuickOps.vue';
import ClientInfoSection from './Client/ClientInfoSection.vue';
import ClientOfferTable from './Client/ClientOfferTable.vue';

export default {
  name: 'ClientDashboard',
  components: {
    ClientHeader,
    ClientStats,
    ClientQuickOps,
    ClientInfoSection,
    ClientOfferTable
  },
  data() {
    const authStore = useAuthStore();
    return {
      authStore,
      sysCode: useNavigationStore().sys_code,
      navigationStore: useNavigationStore(),
      userName: authStore.userName,
      greeting: 'Hoş Geldiniz',
    };
  },
  computed: {
    hasNotifications() {
      let count = 0;
      const notifs = this.navigationStore?.notifications || {};
      if (Array.isArray(notifs.awaitingUsers)) count += notifs.awaitingUsers.length;
      if (Array.isArray(notifs.clientChanges)) count += notifs.clientChanges.length;
      if (Array.isArray(notifs.newOffer)) count += notifs.newOffer.length;
      if (Array.isArray(notifs.offerRevisionRequests)) count += notifs.offerRevisionRequests.length;
      if (Array.isArray(notifs.offerChanges)) count += notifs.offerChanges.length;
      count += (this.authStore.currentStatus?.rejectedFiles || []).length;
      return count > 0;
    }
  },
  methods: {
    openNotifications() {
      this.$refs.infoSection?.openNotifications?.();
    },
    openProfile() {
      if (this.authStore?.personId) {
        this.$router.push({ name: 'UForm', params: { id: this.authStore.personId } });
      } else {
        this.$router.push({ name: 'UForm' });
      }
    }
  }
};
</script>

