<template>
  <div class="dashboard-container">
    <!-- Header Section -->
    <div class="header-section">
      <div class="header-content">
        <div>
          <h1 class="greeting-title">{{ greeting }}, <span class="user-name">{{ userName }}</span></h1>
          <p class="header-subtitle">Tedarikçi Paneli</p>
        </div>
        <div class="header-icons">
          <button class="icon-btn notification-btn" @click="openNotifications">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span v-if="hasNotifications" class="notification-badge"></span>
          </button>
          <button class="icon-btn profile-btn" @click="openProfile">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-section">
      <div class="stat-card stat-card-primary">
        <div class="stat-icon-box primary-icon">
          <i class="ki-outline ki-time"></i>
        </div>
        <div class="stat-content">
          <span class="stat-label">İncelenen Teklifler</span>
          <p class="stat-value primary-value">{{ stats.pendingOffers }}</p>
          <router-link :to="{ name: 'OList' }" class="stat-link success-link">Detayları gör →</router-link>
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

    <!-- Quick Operations -->
    <div class="quick-ops-section">
      <h5 class="section-title">Hızlı İşlemler</h5>
      <div class="quick-ops-grid">
        <div class="quick-op-card primary-card" @click="handleQuickAction('requests')">
          <div class="quick-op-icon primary-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="48" height="48">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <h6 class="quick-op-title">Talepler</h6>
          <p class="quick-op-desc">Talepleri görüntüle</p>
        </div>

        <div class="quick-op-card success-card" @click="handleQuickAction('offers')">
          <div class="quick-op-icon success-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="48" height="48">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
          </div>
          <h6 class="quick-op-title">Teklifler</h6>
          <p class="quick-op-desc">Tekliflerinizi yönetin</p>
        </div>

        <div class="quick-op-card info-card" @click="handleQuickAction('companies')">
          <div class="quick-op-icon info-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="48" height="48">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <h6 class="quick-op-title">Firmalar</h6>
          <p class="quick-op-desc">Firma bilgilerini yönetin</p>
        </div>


      </div>
    </div>

    <!-- Notifications and Requests -->
    <div class="info-section">
      <!-- Notifications -->
      <div class="info-card notifications-card">
        <div class="info-header">
          <h5 class="section-title mb-0">Bildirimler</h5>
        </div>
        <div class="info-body">
          <div v-if="notificationList.length === 0" style="text-align:center;color:#999;padding:2rem;">
            Yeni bildirim yok
          </div>
          <div v-for="(notification, idx) in notificationList.slice(0, 5)" :key="notification.id || idx"
            class="info-item" @click="notification.onclick && notification.onclick()">
            <div class="notification-icon"
              style="background: linear-gradient(135deg, #0d6efd 0%, #0f3a6b 100%);color:#fff;">
              <i :class="notification.iconClass || 'ki-outline ki-bell'" style="font-size:1rem;"></i>
            </div>
            <div class="item-content">
              <p class="item-title">{{ notification.text || notification.title }}</p>
              <small class="item-time">{{ notification.time }}</small>
            </div>
          </div>
        </div>
      </div>

      <!-- My Requests -->
      <div class="info-card requests-card">
        <div class="info-header">
          <h5 class="section-title mb-0">Talepler</h5>
        </div>
        <div class="info-body">
          <div id="request-table"></div>
        </div>
        <router-link :to="{ name: 'RequestList' }" class="info-link">Tüm talepleri görüntüle →</router-link>

      </div>
    </div>

    <!-- Offers Table -->
    <div class="table-section">
      <div class="table-header">
        <h5 class="section-title mb-0">Verdiğim Teklifler</h5>
      </div>
      <div id="offer-table" class="p-3">

      </div>
      <div class="table-footer">
        <router-link :to="{ name: 'OList' }" class="view-all-link">Tüm tekliflerimi görüntüle →</router-link>
      </div>
    </div>
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
.header-section {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  margin-bottom: 2.5rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.greeting-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: #212529;
  margin: 0;
}

.user-name {
  color: #0d6efd;
}

.header-subtitle {
  color: #6c757d;
  font-size: 0.95rem;
  margin: 0.5rem 0 0;
}

.header-icons {
  display: flex;
  gap: 1rem;
}

.icon-btn {
  width: 44px;
  height: 44px;
  border: none;
  background: #f8f9fa;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6c757d;
  transition: all 0.3s ease;
}

.icon-btn:hover {
  background: #0d6efd;
  color: white;
  transform: scale(1.05);
}

.notification-btn {
  position: relative;
}

.notification-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 12px;
  height: 12px;
  background: #dc3545;
  border-radius: 50%;
  animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  box-shadow: 0 0 0 rgba(220, 53, 69, 0.7);
}

@keyframes pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
  }

  50% {
    box-shadow: 0 0 0 6px rgba(220, 53, 69, 0);
  }

  100% {
    box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
  }
}

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

.stat-card {
  /* background: white; */
  /* padding: 2rem; */
  /* border-radius: 16px; */
  /* box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 2px 6px rgba(0, 0, 0, 0.05); */
  display: flex;
  /* flex-direction: column; */
  align-items: center;
  text-align: center;
  gap: 1.2rem;
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  border: 1px solid transparent;
  position: relative;
  /* overflow: hidden; */
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

.primary-value {
  color: #0d6efd;
  background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);
  -webkit-background-clip: text;

  background-clip: text;
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

.info-value {
  color: #0dcaf0;
  background: linear-gradient(135deg, #0dcaf0 0%, #0097a7 100%);
  -webkit-background-clip: text;

  background-clip: text;
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

/* Quick Operations */
.quick-ops-section {
  margin-bottom: 2.5rem;
  background: #fff;
  border-radius: 15px;
  padding: 2rem;
}

.quick-ops-grid {
  display: flex;
  gap: 1.8rem;
  margin: 2rem 0 0 0;
  width: 100%;
}

.quick-op-card {
  cursor: pointer;
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  text-align: left;
  padding-left: 80px;
  overflow: unset !important;
  box-shadow: unset !important;
  width: 100%;
  min-height: 75px;
  width: 100%;
  justify-content: center;
}

.quick-op-card::after{
  content: "";
  background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NDAgNjQwIj48IS0tIUZvbnQgQXdlc29tZSBGcmVlIDcuMi4wIGJ5IEBmb250YXdlc29tZSAtIGh0dHBzOi8vZm9udGF3ZXNvbWUuY29tIExpY2Vuc2UgLSBodHRwczovL2ZvbnRhd2Vzb21lLmNvbS9saWNlbnNlL2ZyZWUgQ29weXJpZ2h0IDIwMjYgRm9udGljb25zLCBJbmMuLS0+PHBhdGggZD0iTTQzOS4xIDI5Ny40QzQ1MS42IDMwOS45IDQ1MS42IDMzMC4yIDQzOS4xIDM0Mi43TDI3OS4xIDUwMi43QzI2Ni42IDUxNS4yIDI0Ni4zIDUxNS4yIDIzMy44IDUwMi43QzIyMS4zIDQ5MC4yIDIyMS4zIDQ2OS45IDIzMy44IDQ1Ny40TDM3MS4yIDMyMEwyMzMuOSAxODIuNkMyMjEuNCAxNzAuMSAyMjEuNCAxNDkuOCAyMzMuOSAxMzcuM0MyNDYuNCAxMjQuOCAyNjYuNyAxMjQuOCAyNzkuMiAxMzcuM0w0MzkuMiAyOTcuM3oiLz48L3N2Zz4=');
  background-size: 100% 100%;
  width: 25px;
  height: 25px;
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
}

.primary-card {
  border-color: #0d6efd;
}

.success-card {
  border-color: #198754;
}

.info-card {
  border-color: #0dcaf0;
}

.warning-card {
  border-color: #ffc107;
}

.quick-op-icon {
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 70px;
  height: 70px;
}
.quick-op-icon svg{
  width: 35px;
  height: 35px;
}

.quick-op-title {
  font-weight: 700;
  color: #212529;
  margin-bottom: 0.5rem;
  font-size: 1.2rem;
  letter-spacing: -0.3px;
}

.quick-op-desc {
  color: #8a92a3;
  font-size: 0.85rem;
  margin: 0;
  transition: color 0.25s ease;
}

.quick-op-card:hover .quick-op-desc {
  color: #6c757d;
}

.section-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: #212529;
  margin-bottom: 2rem;
  letter-spacing: -0.5px;

}

/* Info Section */
.info-section {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  margin-bottom: 2.5rem;
}

.info-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.info-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e9ecef;
}

.info-body {
  padding: 1.5rem;
  max-height: 400px;
  overflow-y: auto;
}

.info-item,
.request-item {
  display: flex;
  gap: 1rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid #e9ecef;
  transition: background 0.2s ease;
}

.info-item:last-child,
.request-item:last-child {
  border-bottom: none;
}

.info-item:hover,
.request-item:hover {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 0.75rem;
  cursor: pointer;
}

.notification-icon {
  width: 15px;
  height: 15px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.item-content {
  flex: 1;
}

.item-title {
  font-weight: 600;
  color: #212529;
  font-size: 0.95rem;
  margin: 0 0 0.25rem;
}

.item-desc {
  color: #6c757d;
  font-size: 0.85rem;
  margin: 0;
}

.item-time {
  color: #adb5bd;
  font-size: 0.8rem;
}

.request-info {
  flex: 1;
}

.request-badge {
  padding: 0.35rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  margin-top: 0.25rem;
}

.info-link {
  display: block;
  text-align: center;
  padding: 1rem;
  color: #0d6efd;
  text-decoration: none;
  font-size: 0.9rem;
  border-top: 1px solid #e9ecef;
  transition: all 0.2s ease;
}

.info-link:hover {
  background: #f8f9fa;
  text-decoration: underline;
}

/* Table Section */
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

  .quick-ops-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .dashboard-container {
    padding: 1.5rem;
  }

  .header-section {
    padding: 1.5rem;
  }

  .info-section {
    grid-template-columns: 1fr;
  }

  .header-content {
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }

  .header-icons {
    align-self: flex-end;
  }

  .quick-ops-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 1.2rem;
  }

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

  .greeting-title {
    font-size: 1.5rem;
  }

  .section-title {
    font-size: 1.2rem;
  }
}

@media (max-width: 576px) {
  .dashboard-container {
    padding: 1rem;
  }

  .stats-section {
    grid-template-columns: 1fr;
    gap: 1.2rem;
  }

  .quick-ops-grid {
    grid-template-columns: 1fr;
  }

  .greeting-title {
    font-size: 1.3rem;
  }

  .header-section {
    padding: 1rem;
    margin-bottom: 1.5rem;
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

  .section-title {
    font-size: 1.1rem;
    margin-bottom: 1rem;
  }
}
</style>

<script>
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
import Swal from 'sweetalert2';
import Plib from '@/lib/pickle';
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';

export default {
  name: 'ClientDashboard',
  data() {
    const authStore = useAuthStore();
    return {
      authStore,
      navigationStore: useNavigationStore(),
      userName: authStore.userName,
      greeting: 'Hoş Geldiniz',
      notificationList: [],
      stats: {
        approvedOffers: 0,
        pendingOffers: 0,
        revisionsNeeded: 0,
        rejectedOffers: 0
      },
      notifications: []
    };
  },
  mounted() {
    this.loadNotifications();
    this.mergeNotifications();
    this.loadStats();
    this.buildRequestTable();
    this.buildOfferTable();
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
      count += (this.notifications || []).length;
      return count > 0;
    }
  },
  watch: {
    'navigationStore.notifications': {
      handler() {
        this.mergeNotifications();
      },
      deep: true
    }
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
    },
    loadNotifications() {
      this.notifications = (this.authStore.currentStatus?.rejectedFiles || []).map((fl) => {
        return {
          title: 'Reddedilen Dosya',
          message: `${fl.title} reddedildi.`,
          time: `${fl.rejected_by} tarafından`,
          type: 'clientFile',
          onclick: () => {
            this.$router.push({ name: 'CForm', params: { id: fl.cli_id } });
          },
        };
      });

      if (typeof this.navigationStore?.getNotifications === 'function') {
        this.navigationStore.getNotifications();
      }
    },
    mergeNotifications() {
      try {
        const addNotifications = this.navigationStore?.notifications || {};
        let list = [];

        for (const key in addNotifications) {
          switch (key) {
            case 'awaitingUsers':
              list = [...list, ...(addNotifications[key] || []).map(u => ({
                id: `awaitingUser-${u.id}`,
                text: `Yeni kullanıcı kayıt bekliyor: ${u.username}`,
                time: `Kayıt tarihi: ${u.created_at}`,
                type: 'awaitingUser',
                iconClass: 'ki-outline ki-user',
                onclick: () => { this.$router.push({ name: 'UForm', params: { id: u.id } }); }
              }))];
              break;
            case 'clientChanges':
              list = [...list, ...(addNotifications[key] || []).map(u => ({
                id: `clientChange-${u.id}`,
                text: `Müşteri güncellemesi (${u.title})`,
                time: `Kayıt tarihi: ${u.created_at}`,
                type: 'clientChange',
                iconClass: 'ki-outline ki-file',
                onclick: () => { this.$router.push({ name: 'CForm', params: { id: u.cli_id } }); }
              }))];
              break;
            case 'offerRevisionRequests':
            case 'offerChanges':
            case 'newOffer':
              const offers = (addNotifications[key] || []).map(offr => {
                let title = '';
                try {
                  JSON.parse(offr.main_attr || '[]').forEach(det => {
                    if (det.Key == 'clititle') title = det.Value;
                  });
                } catch (e) { }
                return {
                  id: `offer-${offr.id}`,
                  text: (key === 'offerRevisionRequests' ? 'Teklif revizyon talebi' : key === 'newOffer' ? 'Yeni Teklif' : 'Teklif güncellemesi') + (title ? ` — ${title}` : ''),
                  time: `Kayıt tarihi: ${offr.created_at}`,
                  type: 'newOffer',
                  iconClass: 'ki-outline ki-bell',
                  onclick: () => { this.$router.push({ name: 'OForm', params: { id: offr.id } }); }
                };
              });
              list = [...list, ...offers];
              break;
            default:
              break;
          }
        }

        const local = (this.notifications || []).map((n, i) => ({
          id: n.id ?? `local-${i}`,
          text: n.title ?? n.text ?? n.message ?? '',
          time: n.time ?? n.created_at ?? '',
          type: n.type ?? 'local',
          iconClass: n.iconClass ?? 'ki-outline ki-bell',
          onclick: n.onclick ?? null
        }));

        list = [...list, ...local];
        this.notificationList = list;
      } catch (e) {
        console.warn('mergeNotifications failed', e);
      }
    },
    showNotifications() {
      this.mergeNotifications();
      const list = (this.notificationList || []).map(n => ({
        title: n.title || n.text || '',
        message: n.message || n.text || '',
        time: n.time || n.date || '',
        iconClass: n.iconClass || 'ki-outline ki-bell',
        onclick: typeof n.onclick === 'function' ? n.onclick : null
      }));

      if (!list || list.length === 0) {
        Swal.fire({
          title: 'Bildirimler',
          html: '<div style="text-align:center;padding:20px;color:#999;">Yeni bildirim yok</div>',
          width: '480px',
          showCloseButton: true,
          showCancelButton: false,
          showConfirmButton: false,
        });
        return;
      }

      const html = `
        <div style="text-align:left;max-height:280px;overflow-y:auto;">
          ${list.map((n, idx) => `
            <div class="swal-notification-item" data-index="${idx}" style="margin-bottom:12px;padding:10px;border-radius:8px;border:1px solid #dee2e6;cursor:pointer;transition:background 0.2s;display:flex;align-items:center;">
              <div style="width:10px;height:10px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:linear-gradient(180deg, #0d6efd 0%, #0f3a6b 100%);color:#fff;margin-right:12px;flex-shrink:0;font-size:16px;">
                <i class="${n.iconClass}"></i>
              </div>
              <div style="flex:1;">
                <div style="font-weight:600;margin-bottom:3px;color:#212529;">${n.title}</div>
                <div style="font-size:13px;margin-bottom:5px;color:#6c757d;">${n.message}</div>
                <div style="font-size:11px;color:#6c757d;font-weight:500;">${n.time}</div>
              </div>
            </div>
          `).join('')}
        </div>
      `;

      Swal.fire({
        title: 'Bildirimler',
        html,
        width: '480px',
        showCloseButton: true,
        showCancelButton: false,
        showConfirmButton: false,
        didOpen: () => {
          document.querySelectorAll('.swal-notification-item').forEach((el) => {
            el.addEventListener('mouseover', () => el.style.background = 'rgba(21, 75, 145, 0.03)');
            el.addEventListener('mouseout', () => el.style.background = 'transparent');
            el.addEventListener('click', () => {
              const idx = Number(el.dataset.index);
              const item = list?.[idx];
              if (item && typeof item.onclick === 'function') item.onclick();
              Swal.close();
            });
          });
        }
      });
    },
    openNotifications() {
      this.showNotifications();
    },
    openProfile() {
      if (this.authStore?.personId) {
        this.$router.push({ name: 'UForm', params: { id: this.authStore.personId } });
      } else {
        this.$router.push({ name: 'UForm' });
      }
    },
    handleQuickAction(action) {
      const routeMap = {
        'offers': 'OList',
        'companies': 'CList',
        'requests': 'RequestList'
      };
      const routeName = routeMap[action];
      if (routeName) {
        this.$router.push({ name: routeName });
      }
    },
    buildRequestTable() {
      //set headers
      const headers = [
        {
          title: 'Talep Kodu',
          key: 'req_no',
          order: true,
          width: '100px',
          type: 'string'
        }, {
          title: 'Talep Başlık',
          key: 'title',
          order: true,
          type: 'string', // if column is string then make type string
        }, {
          title: 'Santral',
          key: 'target_type',
          order: true,
          type: 'string', // if column is string then make type string
        },

      ];

      //initiate table with responsive settings
      this.table = new PickleTable({
        container: '#request-table', //table target div
        headers: headers,
        pageLimit: 10, // -1 for closing pagination
        height: '30vh',
        type: 'ajax',
        columnSearch: false, // true - false for opening and closig
        paginationType: 'number',// scroll - number (number for default)
        ajax: {
          url: '/api/v1/table/documents',
          data: {
            //order:{},
          }
        },
        initialFilter: [
          {
            key: 'form-type',
            type: '=',
            value: 'op-doc-request-form'
          }, {
            key: 'type',
            type: '=',
            value: 'op-doc-request'
          }
        ],
        nextPageIcon: '<i class="ki-outline ki-arrow-right"></i>',
        prevPageIcon: '<i class="ki-outline ki-arrow-left"></i>',
        rowFormatter: (elm, data) => {
          //console.log(elm,data);
          //modify row element
          //elm.style.backgroundColor = 'yellow';
          //modify data
          JSON.parse(data.main_attr).forEach(element => {
            data[element['Key']] = element['Value'];
            //if(data['cont_name'] == undefined) data['cont_name'] = []
            //if(element['Key'].includes('cont_name')) data['cont_name'].push(element['Value']);
          });
          //data['cont_name'] = (data['cont_name'] ?? []).join(' , ');
          //data.status = JSON.parse(data.status).OpTitle;
          return data;
        },
      });
    },
    buildOfferTable() {

      //set headers
      const headers = [
        {
          title: 'Cari',
          key: 'clititle',
          order: true,
          type: 'string', // if column is string then make type string
        }, {
          title: 'Santral',
          key: 'target_type',
          order: true,
          type: 'string', // if column is string then make type string
        }, {
          title: 'Teklif tipi',
          key: 'offer_type',
          order: true,
          type: 'string', // if column is string then make type string
          columnFormatter: (elm, rowData, columnData) => {
            return columnData.split('**')[1];
          }
        }, {
          title: 'Belge Tarihi',
          key: 'date',
          order: true,
          type: 'string', // if column is string then make type string
        }, {

          title: 'Talep',
          key: 'addional',
          order: true,
          type: 'string', // if column is string then make type string
          columnFormatter: (elm, rowData, columnData) => {
            const data = JSON.parse(columnData ?? '{}');
            for (const key in data) {
              if (data[key]?.Key === 'title') {
                const spn = document.createElement('span');
                spn.innerHTML = data[key]?.Value ?? '-';
                const viewBtn = document.createElement('button');
                viewBtn.classList.add('btn', 'ms-2', 'btn-secondary', 'action-icon-btn', 'me-1');
                viewBtn.title = 'Detay';
                viewBtn.innerHTML = '<i class="ki-outline ki-eye fs-2"></i>';
                viewBtn.onclick = () => {
                  this.$router.push({ name: 'RequestForm', params: { id: rowData.request_id } });
                };


                spn.appendChild(viewBtn);



                return spn;
              }
            }
            return '-';
          }
        }, {
          title: 'Güncel Durum',
          key: 'status',
          order: true,
          type: 'string', // if column is string then make type string
          columnFormatter: (elm, rowData, columnData) => {
            const key = rowData.status?.split('**');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.classList.add('status-pill');

            switch (key?.[0]) {
              case 'doc_trans_offer_approved':
                btn.classList.add('status-pill--success');
                break;
              case 'doc_trans_offer_rejected':
                btn.classList.add('status-pill--danger');
                break;
              case 'doc_trans_offer_revision':
              case 'doc_trans_offer_revised':
              case 'doc_trans_offer_review':
                btn.classList.add('status-pill--warning');
                break;
              default:
                btn.classList.add('status-pill--secondary');
                break;
            }
            btn.textContent = key?.[1] ?? 'Teklif Gönderildi';
            //here we are looking request form permissions
            //btn.onclick = () => this.authStore.permissions?.includes('per-05-02') ? this.openStatusChangeModal(rowData) : {};


            return btn;
          }
        }
      ];

      //initiate table
      this.table = new PickleTable({
        container: '#offer-table', //table target div
        headers: headers,
        pageLimit: 10, // -1 for closing pagination
        height: '50vh',
        type: 'ajax',
        columnSearch: false, // true - false for opening and closig
        paginationType: 'number',// scroll - number (number for default)
        ajax: {
          url: '/api/v1/table/documents',
          data: {
            //order:{},
          }
        },
        initialFilter: [
          {
            key: 'form-type',
            type: '=',
            value: 'op-doc-offer-form'
          }, {
            key: 'type',
            type: '=',
            value: 'op-doc-offer'
          }
        ],
        nextPageIcon: '<i class="ki-outline ki-arrow-right"></i>',
        prevPageIcon: '<i class="ki-outline ki-arrow-left"></i>',
        rowFormatter: (elm, data) => {
          //console.log(elm,data);
          //modify row element
          //elm.style.backgroundColor = 'yellow';
          //modify data
          JSON.parse(data.main_attr).forEach(element => {
            data[element['Key']] = element['Value'];
            //if(data['cont_name'] == undefined) data['cont_name'] = []
            //if(element['Key'].includes('cont_name')) data['cont_name'].push(element['Value']);
          });
          //data['cont_name'] = (data['cont_name'] ?? []).join(' , ');
          //data.status = JSON.parse(data.status).OpTitle;
          return data;
        },
      });
    },
  },
};
</script>
