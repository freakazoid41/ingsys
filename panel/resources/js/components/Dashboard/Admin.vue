<template>
  <div class="admin-dashboard">
    <!-- Header Section -->
    <div class="header-section">
      <div class="header-content">
        <div>
          <h1 class="greeting-title">{{ greeting }}, <span class="user-name">{{ userName }}</span></h1>
          <p class="header-subtitle">Yönetici Paneli</p>
        </div>
        <div class="header-icons">
            <button @click="showNotifications" class="icon-btn notification-btn">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span v-if="hasNotifications" class="notification-badge"></span>
            </button>
            <router-link v-if="authStore.personId"
                            :to="{ name: 'UForm', params: { id: authStore.personId } }"
                            class="icon-btn profile-btn"
                            title="Profil">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </router-link>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
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

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
      <!-- Row 3: Distribution and Quick Actions -->
      <div class="grid-row-2">
        <!-- Santral Bazlı Dağılım -->
        <div class="grid-col-1-2">
          <div class="distribution-section">
            <h5 class="card-title">Aylık Santral Bazlı Dağılım</h5>
            <div class="distribution-cards">
              <div v-for="branch in branchDistribution" :key="branch.id" class="distribution-card">
                <div class="distribution-icon">
                  <i class="ki-outline ki-home fs-2x"></i>
                </div>
                <div class="distribution-content">
                  <p class="distribution-label">{{ branch.name }}</p>
                  <div class="distribution-stats">
                    <span class="stat-item">
                      <strong>{{ branch.totalRequests }}</strong> Toplam Talep
                    </span>
                    <span class="stat-item">
                      <strong>{{ branch.totalOffers }}</strong> Toplam Teklif
                    </span>
                  </div>
                </div>
              </div>
              <div class="distribution-card totals-card">
                <div class="distribution-icon">
                  <i class="ki-outline ki-home fs-2x"></i>
                </div>
                <div class="distribution-content">
                  <p class="distribution-label">Toplam</p>
                  <div class="distribution-stats">
                    <span class="stat-item">
                      <strong>{{ distributionTotals.totalRequests }}</strong> Toplam Talep
                    </span>
                    <span class="stat-item">
                      <strong>{{ distributionTotals.totalOffers }}</strong> Toplam Teklif
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Quick Actions -->
        <div class="grid-col-1-2">
          <div class="quick-actions-card">
            <h5 class="card-title">Hızlı İşlemler</h5>
            <div class="quick-actions-grid">
              <button v-for="action in quickActions" :key="action.id" class="quick-action-btn" @click="handleQuickAction(action)">
                <div class="quick-action-icon" :style="{ backgroundColor: action.color }">
                  <i :class="action.iconClass"></i>
                </div>
                <span class="quick-action-label">{{ action.label }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      <!-- Row 1: Charts and Notifications -->
      <div class="grid-row">
        <!-- Teklif Süreç Durumu -->
        <div class="grid-col-1-3">
          <div class="chart-card process-chart-card">
            <h5 class="card-title">Teklif Süreç Durumu (Aylık)</h5>
            <div class="chart-content">
              <div class="chart-wrapper">
                <div class="chart-container process-chart-container">
                  <canvas ref="processChart" class="pie-chart"></canvas>
                </div>
              </div>
              <div class="chart-legend-right">
                <div v-for="item in processData" :key="item.label" class="legend-item">
                  <span :style="{ backgroundColor: item.color }" class="legend-color"></span>
                  <div class="legend-text-group">
                    <span class="legend-text">{{ item.label }}</span>
                    <span class="legend-value">{{ item.value }}</span>
                  </div>
                </div>
              </div>
            </div>
            <router-link :to="{ name: 'OList'}" class="notifications-footer-btn">Detaylara Git <i class="fa-solid fa-angle-right"></i></router-link>
            
          </div>
        </div>

        

        <!-- Bildirimler -->
        <div class="grid-col-1-3">
          <div class="notifications-card">
            <div class="card-header">
              <h5 class="card-title">Bildirimler</h5>
            </div>
            <div class="notifications-list">
              <div v-for="notif in notificationList" :key="notif.id" :class="['notification-item', notif.type]">
                <span class="notification-icon">
                  <i :class="notif.iconClass"></i>
                </span>
                <div class="notification-content">
                  <p class="notification-text">{{ notif.text }}</p>
                  <span class="notification-time">{{ notif.time }}</span>
                </div>
              </div>
            </div>
            <a href="" alt="" title="" class="notifications-footer-btn">Tüm Bildirimleri Görüntüle <i class="fa-solid fa-angle-right"></i></a>
          </div>
        </div>
        <div class="grid-col-1-3">
          <div class="calendar-card">
            <div class="card-header">
              <h5 class="card-title">Takvim / Önemli Tarihler</h5>
            </div>
            <div class="calendar-list">
              <div v-for="(event, idx) in importantDates" :key="event.doc_id ?? idx" class="calendar-item" @click="openImportantDate(event)" style="cursor:pointer;">
                <span class="calendar-icon">
                  <svg fill="currentColor" viewBox="0 0 24 24" width="18" height="18">
                    <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z" />
                  </svg>
                </span>
                <div class="calendar-content">
                  <p class="calendar-text">{{ event.text }}</p>
                  <span class="calendar-date">{{ event.date }}</span>
                </div>
              </div>
            </div>
             <a href="" alt="" title="" class="notifications-footer-btn">Tüm Takvimi Görüntüle <i class="fa-solid fa-angle-right"></i></a>
          </div>
        </div>
      </div>
      <!-- Parking Breakdown -->
      <div class="grid-row">
        <div class="grid-col-1-3-full">
          <div class="chart-card process-chart-card">
            <h5 class="card-title">Parking Usage Breakdown</h5>
            <div class="chart-content">
              <div class="chart-wrapper">
                <div class="chart-container process-chart-container">
                  <canvas ref="parkingChart" class="pie-chart"></canvas>
                </div>
              </div>
              <div class="chart-legend-right">
                <div v-for="item in parkingData" :key="item.label" class="legend-item">
                  <span :style="{ backgroundColor: item.color }" class="legend-color"></span>
                  <div class="legend-text-group">
                    <span class="legend-text">{{ item.label }}</span>
                    <span class="legend-value">{{ item.value }}</span>
                  </div>
                </div>
              </div>
            </div>
            <router-link :to="{ name: 'ParkingSessionList' }" class="notifications-footer-btn">Detaylara Git <i class="fa-solid fa-angle-right"></i></router-link>
          </div>
        </div>
      </div>
      <!-- Row 2: Tables -->
      <div class="row">
        <!-- Son Talepler -->
        <div class="col-12">
          <div class="table-card">
            <div class="card-header">
              <h5 class="card-title">Son Talepler</h5>
              <router-link :to="{ name: 'RequestList'}" class="card-link">Tümünü Gör →</router-link>
            </div>
            <div id="request-table"></div>
          </div>
        </div>

        <!-- Son Teklifler -->
        
      </div>
      <div class="row">
        <div class="col-12">
          <div class="table-card">
            <div class="card-header">
              <h5 class="card-title">Son Teklifler</h5>
              <router-link :to="{ name: 'OList'}" class="card-link">Tümünü Gör →</router-link>
            </div>
            <div id="offer-table"></div>
          </div>
        </div>
      </div>

      

    </div>
  </div>
</template>

<script>
import Chart from 'chart.js/auto';
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
import Swal from 'sweetalert2';
import Plib from '@/lib/pickle';
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';

export default {
    name: 'AdminDashboard',
    data() {
        //get dashboard informations
        return {
            plib : new Plib(),
            authStore   : useAuthStore(),
            navigationStore: useNavigationStore(),
            greeting: 'Hoş Geldiniz',
            userName: useAuthStore().userName,
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
                },
                totalVehicles: {
                    id: 7,
                    label: 'Toplam Araç',
                    value: 0,
                    subtext: 'Kayıtlı',
                    action: 'Detay',
                    actionKey: 'vehicles',
                    iconClass: 'success-icon',
                    keenIcon: 'ki-outline ki-car',
                    valueClass: 'success-value',
                    linkClass: 'success-link'
                },
                availableSpots: {
                    id: 8,
                    label: 'Boş Park Yeri',
                    value: 0,
                    subtext: 'Anlık',
                    action: 'Detay',
                    actionKey: 'parkingSpots',
                    iconClass: 'primary-icon',
                    keenIcon: 'ki-outline ki-location',
                    valueClass: 'primary-value',
                    linkClass: 'primary-link'
                },
                activeSessions: {
                    id: 9,
                    label: 'Aktif Oturum',
                    value: 0,
                    subtext: 'Şu anda',
                    action: 'Detay',
                    actionKey: 'parkingSessions',
                    iconClass: 'warning-icon',
                    keenIcon: 'ki-outline ki-timer',
                    valueClass: 'warning-value',
                    linkClass: 'warning-link'
                }
            },
            topStatsList: ['totalRequests', 'totalOffers', 'allClients', 'awaitingOffers', 'todaysOffers', 'totalVehicles', 'availableSpots', 'activeSessions'],
            processData: [
                { label: 'Değerlendirme Aşamasında', value: 28, color: '#0d6efd' },
                { label: 'Revize Bekliyor', value: 12, color: '#ffc107' },
                { label: 'Onaylandı', value: 29, color: '#198754' },
                { label: 'Reddedildi', value: 8, color: '#e74c3c' },
                { label: 'İptal Edildi', value: 5, color: '#95a5a6' },
                { label: 'Taslak', value: 35, color: '#d3d3d3' }
            ],
            parkingData: [
                { label: 'Open Sessions', value: 0, color: '#0d6efd' },
                { label: 'Reserved Spots', value: 0, color: '#198754' },
                { label: 'Available Spots', value: 0, color: '#ffc107' }
            ],

            notifications: [],
            notificationList: [],
            importantDates: [],
            branchDistribution: [],
            quickActions: [
              { id: 1, label: 'Yeni Talep Oluştur', color: '#0d6efd', iconClass: 'ki-outline ki-document' },
              { id: 4, label: 'Kullanıcı Ekle', color: '#ffc107', iconClass: 'ki-outline ki-user' }
            ],
            topStatsLoading: {
                totalRequests: false,
                totalOffers: false,
                approvedOffers: false,
                allClients: false,
                awaitingOffers: false,
                todaysOffers: false,
                totalVehicles: false,
                availableSpots: false,
                activeSessions: false
            },
            charts: {}
        };
    },
    setup() {
        return {
            useAuthStore,
            useNavigationStore,
            Swal
        }
    },
    mounted() {
      document.getElementById('kt_content_container').classList.remove('container-xxl');
      // Load dynamic data first, then initialize charts
      this.monthlyOffersLoad();
      this.monthlyDistributionLoad();
      this.parkingDataLoad();
      this.importantDatesLoad();
      this.$nextTick(() => {
        this.initCharts();
      });
      this.loadNotifications();
      // populate in-component notification list immediately
      this.mergeNotifications();
      this.topDataLoad();
      this.buildRequestTable();
      this.buildOfferTable();
       
    },
    watch: {
        'navigationStore.notifications': {
            handler() {
                // React to navigationStore notification changes
                this.mergeNotifications();
            },
            deep: true
        }
    },
    beforeUnmount() {
        document.getElementById('kt_content_container').classList.add('container-xxl');
    },
    computed: {
        hasNotifications() {
            let count = 0;
            const notifs = this.navigationStore?.notifications || {};
            
            // Count from each category
            if(Array.isArray(notifs.awaitingUsers)) count += notifs.awaitingUsers.length;
            if(Array.isArray(notifs.clientChanges)) count += notifs.clientChanges.length;
            if(Array.isArray(notifs.newOffer)) count += notifs.newOffer.length;
            if(Array.isArray(notifs.offerRevisionRequests)) count += notifs.offerRevisionRequests.length;
            if(Array.isArray(notifs.offerChanges)) count += notifs.offerChanges.length;
            
            // Add rejected files
            count += (this.notifications || []).length;
            
            return count > 0;
        }
          ,
          distributionTotals() {
            const totals = { totalRequests: 0, totalOffers: 0 };
            try {
              (this.branchDistribution || []).forEach(b => {
                totals.totalRequests += Number(b.totalRequests || 0);
                // prefer totalOffers, fall back to approvedOffers if absent
                totals.totalOffers += Number(b.totalOffers ?? b.approvedOffers ?? 0);
              });
            } catch (e) {
              // ignore
            }
            return totals;
          }
    },
    methods: {
        loadNotifications() {
            // Fallback to authStore if navigationStore method doesn't exist
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
            // Fetch notifications from navigationStore
            this.navigationStore.getNotifications();
        },
        async topDataLoad() {
            // Set all stats to loading state
            Object.keys(this.topStatsLoading).forEach(key => {
                this.topStatsLoading[key] = true;
            });

            // Load top statistics data from API
            try {
                const rsp = await (new Plib).request({url: '/api/v1/dashboard/topstats', method: 'GET'}, null);
                if(rsp && typeof rsp === 'object') {
                    // Update topStats with API data
                    if(rsp.totalRequests !== undefined) this.topStats.totalRequests.value = rsp.totalRequests;
                    if(rsp.totalOffers !== undefined) this.topStats.totalOffers.value = rsp.totalOffers;
                    if(rsp.approvedOffers !== undefined) this.topStats.approvedOffers.value = rsp.approvedOffers;
                    if(rsp.allClients !== undefined) this.topStats.allClients.value = rsp.allClients;
                    if(rsp.awaitingOffers !== undefined) this.topStats.awaitingOffers.value = rsp.awaitingOffers;
                    if(rsp.todaysOffers !== undefined) this.topStats.todaysOffers.value = rsp.todaysOffers;
                    if(rsp.totalVehicles !== undefined) this.topStats.totalVehicles.value = rsp.totalVehicles;
                    if(rsp.availableSpots !== undefined) this.topStats.availableSpots.value = rsp.availableSpots;
                    if(rsp.activeSessions !== undefined) this.topStats.activeSessions.value = rsp.activeSessions;
                    
                    // Force component update to reflect changes
                    this.$forceUpdate();
                }
            } catch(error) {
                console.error('Failed to load top statistics:', error);
                // Keep default values if API fails
            } finally {
                // Stop loading for all stats
                Object.keys(this.topStatsLoading).forEach(key => {
                    this.topStatsLoading[key] = false;
                });
            }
        },

        async monthlyOffersLoad() {
          try {
            const rsp = await (new Plib).request({ url: '/api/v1/dashboard/monthlyoffers', method: 'GET' }, null);

            if (Array.isArray(rsp)) {
              this.processData = rsp.map(item => ({
                label: item.label || item.name || item.status || '',
                value: Number(item.value ?? item.count ?? item.total ?? 0),
                color: item.color || item.color_code || item.hex || '#d3d3d3'
              }));
            } else if (rsp && typeof rsp === 'object') {
              const arr = [];
              // try to parse common shapes: { label: value } or { items: [...] }
              if (Array.isArray(rsp.items)) {
                this.processData = rsp.items.map(item => ({
                  label: item.label || item.name || '',
                  value: Number(item.value ?? item.count ?? 0),
                  color: item.color || '#d3d3d3'
                }));
              } else if (Array.isArray(rsp.labels) && (Array.isArray(rsp.data) || Array.isArray(rsp.values))) {
                const values = Array.isArray(rsp.data) ? rsp.data : rsp.values;
                this.processData = rsp.labels.map((lab, idx) => ({
                  label: lab,
                  value: Number(values[idx] ?? 0),
                  color: (Array.isArray(rsp.colors) && rsp.colors[idx]) || '#d3d3d3'
                }));
              } else {
                for (const key in rsp) {
                  const v = rsp[key];
                  if (typeof v === 'number' || !isNaN(Number(v))) {
                    arr.push({ label: key, value: Number(v), color: '#d3d3d3' });
                  }
                }
                if (arr.length) this.processData = arr;
              }
            }

            this.$forceUpdate();

            // Update chart if already initialized
            if (this.charts.processChart) {
              this.charts.processChart.data.labels = this.processData.map(i => i.label);
              this.charts.processChart.data.datasets[0].data = this.processData.map(i => i.value);
              this.charts.processChart.data.datasets[0].backgroundColor = this.processData.map(i => i.color);
              this.charts.processChart.update();
            }
          } catch (error) {
            console.error('Failed to load monthly offers:', error);
          }
        },
        async parkingDataLoad() {
          try {
            const rsp = await (new Plib).request({ url: '/api/v1/dashboard/parkingstats', method: 'GET' }, null);
            const defaultData = [
              { key: 'openSessions', label: 'Open Sessions', value: 0, color: '#0d6efd' },
              { key: 'reservedSpots', label: 'Reserved Spots', value: 0, color: '#198754' },
              { key: 'availableSpots', label: 'Available Spots', value: 0, color: '#ffc107' }
            ];

            if (Array.isArray(rsp)) {
              this.parkingData = rsp.map((item, idx) => ({
                label: item.label || defaultData[idx]?.label || '',
                value: Number(item.value ?? item.count ?? 0),
                color: item.color || defaultData[idx]?.color || '#d3d3d3'
              }));
            } else if (rsp && typeof rsp === 'object') {
              this.parkingData = defaultData.map(item => ({
                label: item.label,
                value: Number(rsp[item.key] ?? rsp[item.label] ?? rsp[item.label.replace(/\s+/g, '').toLowerCase()] ?? 0),
                color: item.color
              }));
            }

            if (this.charts.parkingChart) {
              this.charts.parkingChart.data.labels = this.parkingData.map(i => i.label);
              this.charts.parkingChart.data.datasets[0].data = this.parkingData.map(i => i.value);
              this.charts.parkingChart.data.datasets[0].backgroundColor = this.parkingData.map(i => i.color);
              this.charts.parkingChart.update();
            }
          } catch (error) {
            console.error('Failed to load parking dashboard data:', error);
          }
        },

        async monthlyDistributionLoad() {
          try {
            const rsp = await (new Plib).request({ url: '/api/v1/dashboard/monthlydistribution', method: 'GET' }, null);

            const normalizeItem = (it, idx) => ({
              id: idx + 1,
              name: it.name || it.label || it.title || it[0] || 'Bilinmiyor',
              totalRequests: Number(it.totalRequests || it.requests || 0),
              totalOffers: Number(it.totalOffers || it.offers || 0),
              approvedOffers: Number(it.approvedOffers || it.approved || 0)
            });

            if (!rsp) return;

            // If backend returned an object keyed by name (associative), convert to array
            if (rsp && typeof rsp === 'object' && !Array.isArray(rsp)) {
              // If it has a data array, prefer that
              if (Array.isArray(rsp.data)) {
                this.branchDistribution = rsp.data.map((it, idx) => normalizeItem(it, idx));
              } else {
                const arr = [];
                let idx = 0;
                for (const k in rsp) {
                  if (!Object.prototype.hasOwnProperty.call(rsp, k)) continue;
                  const it = rsp[k];
                  // if it already contains name, use it; otherwise derive from key
                  const item = (typeof it === 'object' && it !== null) ? Object.assign({}, it) : { name: k, totalRequests: 0, totalOffers: Number(it || 0) };
                  if (!item.name) item.name = k;
                  arr.push(normalizeItem(item, idx));
                  idx++;
                }
                this.branchDistribution = arr;
              }
            } else if (Array.isArray(rsp)) {
              this.branchDistribution = rsp.map((it, idx) => normalizeItem(it, idx));
            }
            this.$forceUpdate();
          } catch (e) {
            console.error('Failed to load monthly distribution:', e);
          }
        },

        async importantDatesLoad() {
          try {
            const rsp = await (new Plib).request({ url: '/api/v1/dashboard/importantinfo', method: 'GET' }, null);
            if (Array.isArray(rsp)) {
              this.importantDates = rsp.map(it => ({ text: it.text || it.title || '',event : it.event, date: it.date || it.dt || '', doc_id: it.doc_id ?? null, type: it.type ?? null }));
            } else if (rsp && typeof rsp === 'object' && Array.isArray(rsp.data)) {
              this.importantDates = rsp.data.map(it => ({ text: it.text || it.title || '',event : it.event, date: it.date || '', doc_id: it.doc_id ?? null, type: it.type ?? null }));
            }
          } catch (e) {
            console.error('Failed to load important dates:', e);
          }
        },

        

        handleStatAction(statKey) {
            // Handle stat card click based on actionKey
            const actionKey = this.topStats[statKey]?.actionKey;
            switch(actionKey) {
                case 'requests':
                    this.$router.push({ name: 'RequestList' });
                    break;
                case 'totalOffers':
                    this.$router.push({ name: 'OList' });
                    break;
                case 'approvedOffers':
                    this.$router.push({ name: 'OList'});
                    break;
                case 'companies':
                    this.$router.push({ name: 'CList' });
                    break;
                case 'awaitingOffers':
                    this.$router.push({ name: 'OList'});
                    break;
                case 'todaysOffers':
                    this.$router.push({ name: 'OList'});
                    break;
                case 'vehicles':
                    this.$router.push({ name: 'VehicleList'});
                    break;
                case 'parkingSpots':
                    this.$router.push({ name: 'ParkingSpotList'});
                    break;
                case 'parkingSessions':
                    this.$router.push({ name: 'ParkingSessionList'});
                    break;
                default:
                    console.log('Unknown action:', actionKey);
            }
        },
        mergeNotifications() {
          // Build in-component notification list from navigationStore.notifications
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
                    } catch (e) {}
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

            // Merge with rejected files / local notifications
            // normalize local `notifications` entries to the same shape
            const local = (this.notifications || []).map((n, i) => ({
              id: n.id ?? `local-${i}`,
              text: n.title ?? n.text ?? n.message ?? '',
              time: n.time ?? n.created_at ?? '',
              type: n.type ?? 'local',
              iconPath: n.iconPath ?? 'M12 4v16m8-8H4',
              onclick: n.onclick ?? null
            }));

            list = [...list, ...local];
            this.notificationList = list;
          } catch (e) {
            console.warn('mergeNotifications failed', e);
          }
        },
        handleQuickAction(action) {
          try {
            switch (action.id) {
              case 1:
                this.$router.push({ name: 'RequestForm' });
                break;
              case 2:
                this.$router.push({ name: 'AnnouncementCreate' });
                break;
              case 3:
                this.$router.push({ name: 'ReportBuilder' });
                break;
              case 4:
                // open user create form if available
                this.$router.push({ name: 'UForm' });
                break;
              default:
                console.log('Quick action not configured:', action);
                Swal.fire({ icon: 'info', title: 'Hızlı işlem', text: 'Bu işlem yapılandırılmamış.' });
            }
          } catch (e) {
            console.warn('Quick action navigation failed', e);
            Swal.fire({ icon: 'error', title: 'Hata', text: 'Hızlı işlem gerçekleştirilemedi.' });
          }
        },
        openImportantDate(event) {
          try {
            if (!event) return;
            const id = event.doc_id || event.id;
            const type = (event.event || '').toLowerCase();
            
            if (id) {
              if (type.includes('offer')) {
                this.$router.push({ name: 'OForm', params: { id } });
                return;
              }
              // default to request form
              this.$router.push({ name: 'RequestForm', params: { id } });
              return;
            }

            // no doc link: show details
            Swal.fire({ title: 'Etkinlik', html: `<div style="text-align:left">${event.text || ''}<br/><small style="color:#666">${event.date || ''}</small></div>` });
          } catch (e) {
            console.warn('openImportantDate failed', e);
          }
        },
        showNotifications() {
          // Use the component's normalized notificationList for the modal
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
                <div class="swal-notification-item" data-index="${idx}" style="margin-bottom:12px;padding:10px;border-radius:8px;border:1px solid var(--border-color);cursor:pointer;transition:background 0.2s;display:flex;align-items:center;">
                  <div style="width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:linear-gradient(180deg, var(--primary-color) 0%, #0f3a6b 100%);color:#fff;margin-right:12px;flex-shrink:0;font-size:16px;">
                    <i class="${n.iconClass}"></i>
                  </div>
                  <div style="flex:1;">
                    <div style="font-weight:600;margin-bottom:3px;color:var(--dark-text);">${n.title}</div>
                    <div style="font-size:13px;margin-bottom:5px;color:var(--text-secondary);">${n.message}</div>
                    <div style="font-size:11px;color:var(--text-secondary);font-weight:500;">${n.time}</div>
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
        initCharts() {
        // Process Chart (Doughnut with center text)
        const processCtx = this.$refs.processChart?.getContext('2d');
        if (processCtx) {
            const centerTextPlugin = {
            id: 'centerText',
            beforeDatasetsDraw(chart) {
              const ctx = chart.ctx;
              ctx.save();

              // compute total dynamically from chart data
              let totalValue = 0;
              try {
                const ds = chart.data && chart.data.datasets && chart.data.datasets[0];
                if (ds && Array.isArray(ds.data)) {
                  totalValue = ds.data.reduce((s, v) => s + (Number(v) || 0), 0);
                }
              } catch (e) {
                totalValue = 0;
              }

              const width = chart.width;
              const height = chart.height;

              // Draw main value
              ctx.font = 'bold 32px Arial';
              ctx.textBaseline = 'middle';
              ctx.textAlign = 'center';
              ctx.fillStyle = '#212529';

              const text = String(totalValue);
              const textX = width / 2;
              const textY = height / 2 - 8;
              ctx.fillText(text, textX, textY);

              // Draw subtext
              ctx.font = '12px Arial';
              ctx.fillStyle = '#8a92a3';
              const subtext = 'Toplam';
              const subtextY = height / 2 + 12;
              ctx.fillText(subtext, textX, subtextY);

              ctx.restore();
            }
            };
            
            this.charts.processChart = new Chart(processCtx, {
            type: 'doughnut',
            data: {
                labels: this.processData.map(item => item.label),
                datasets: [{
                data: this.processData.map(item => item.value),
                backgroundColor: this.processData.map(item => item.color),
                borderColor: '#fff',
                borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                legend: {
                    display: false
                }
                }
            },
            plugins: [centerTextPlugin]
            });
        }

        // Parking breakdown chart
        const parkingCtx = this.$refs.parkingChart?.getContext('2d');
        if (parkingCtx) {
            this.charts.parkingChart = new Chart(parkingCtx, {
                type: 'doughnut',
                data: {
                    labels: this.parkingData.map(item => item.label),
                    datasets: [{
                        data: this.parkingData.map(item => item.value),
                        backgroundColor: this.parkingData.map(item => item.color),
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    return `${label}: ${value}`;
                                }
                            }
                        }
                    }
                }
            });
        }
        },
        buildRequestTable(){
          //set headers
          const headers = [
              {
                  title : 'Talep Kodu',
                  key   : 'req_no',
                  order : true,
                  width : '100px',
                  type  : 'string'
              },{
                  title : 'Talep Başlık',
                  key   : 'title',
                  order : true,
                  type  : 'string', // if column is string then make type string
              },{
                  title : 'Santral',
                  key   : 'target_type',
                  order : true,
                  type  : 'string', // if column is string then make type string
              },{
                  title : 'Sipariş Kapsamı',
                  key   : 'order_radius',
                  order : true,
                  width : '150px',
                  type  : 'string', // if column is string then make type string
              },{
                  title : 'İhale Başlangıç / Bitiş',
                  key   : 'contract_start_date',
                  order : true,
                  width : '200px',
                  type  : 'string', // if column is string then make type string
                  columnFormatter : (elm,rowData) => {
                      return rowData.contract_start_date && rowData.contract_end_date ? 
                          `<div>${rowData.contract_start_date} - ${rowData.contract_end_date}</div>` : '-';
                  }
              },{
                  title : 'Sevkiyat Başlangıç / Bitiş',
                  key   : 'transfer_start_date',
                  order : true,
                  width : '200px',
                  type  : 'string', // if column is string then make type string
                  columnFormatter : (elm,rowData) => {
                      return rowData.transfer_start_date && rowData.transfer_end_date ? 
                          `<div>${rowData.transfer_start_date} - ${rowData.transfer_end_date}</div>` : '-';
                  }
              },{
                  title : 'Güncel Durum',
                  key   : 'status',
                  order : true,
                  width : '200px',
                  type  : 'string', // if column is string then make type string
                  columnFormatter : (elm,rowData,columnData) => {
                      const  btn    = document.createElement('button');
                      btn.classList.add('btn','d-flex','align-items-center');

                      const key = rowData.status?.split('**');
                      
                      let icon  = '';
                      switch(key?.[0]){
                          case 'doc_trans_created':
                          default:
                              if(key?.[1]) key[1] = 'Taslak';
                              btn.classList.add('status-pill','status-pill--secondary');
                              break;
                          case 'doc_trans_request_end':
                              btn.classList.add('status-pill','status-pill--success');
                              break;
                          case 'doc_trans_request_start':
                              btn.classList.add('status-pill','status-pill--warning');
                              break;
                          case 'doc_trans_request_cancelled':
                              btn.classList.add('status-pill','status-pill--danger');
                              break;
                      }
                      btn.innerHTML = icon+' '+(key?.[1] ?? 'Bekleniyor..') ;
                      btn.type      = 'button';
                      return btn;
                  }
              }
          ];
          
          //initiate table with responsive settings
          this.table = new PickleTable({
              container : '#request-table', //table target div
              headers   : headers,
              pageLimit : 10, // -1 for closing pagination
              height    : '50vh',
              type      : 'ajax',
              columnSearch : false, // true - false for opening and closig
              paginationType : 'number',// scroll - number (number for default)
              ajax:{
                  url:'/api/v1/table/documents',
                  data:{
                      //order:{},
                  }
              },
              initialFilter : [
                  {
                      key   : 'form-type',
                      type  : '=',
                      value : 'op-doc-request-form'
                  },{
                      key   : 'type',
                      type  : '=',
                      value : 'op-doc-request'
                  }
              ],
              nextPageIcon : '<i class="ki-outline ki-arrow-right"></i>',
              prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
              rowFormatter:(elm,data)=>{
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
        buildOfferTable(){
                
                //set headers
                const headers = [
                    {
                        title : 'Cari',
                        key   : 'clititle',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Santral',
                        key   : 'target_type',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Teklif tipi',
                        key   : 'offer_type',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            return columnData.split('**')[1];
                        }
                    },{
                        title : 'Belge Tarihi',
                        key   : 'date',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{

                        title : 'Talep',
                        key   : 'addional',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const data = JSON.parse(columnData ?? '{}');
                            for (const key in data) {
                                if (data[key]?.Key === 'title'){
                                    const spn     = document.createElement('span');
                                    spn.innerHTML = data[key]?.Value ?? '-';
                                    const viewBtn = document.createElement('button');
                                    viewBtn.classList.add('btn','ms-2','btn-secondary','action-icon-btn','me-1');
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
                    },{
                        title : 'Güncel Durum',
                        key   : 'status',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const key = rowData.status?.split('**');
                            const btn = document.createElement('button');
                            btn.type  = 'button';
                            btn.classList.add('status-pill');

                            switch(key?.[0]){
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
                    container : '#offer-table', //table target div
                    headers   : headers,
                    pageLimit : 10, // -1 for closing pagination
                    height    : '50vh',
                    type      : 'ajax',
                    columnSearch : false, // true - false for opening and closig
                    paginationType : 'number',// scroll - number (number for default)
                    ajax:{
                        url:'/api/v1/table/documents',
                        data:{
                            //order:{},
                        }
                    },
                    initialFilter : [
                        {
                            key   : 'form-type',
                            type  : '=',
                            value : 'op-doc-offer-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-offer'
                        }
                    ],
                    nextPageIcon : '<i class="ki-outline ki-arrow-right"></i>',
                    prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter:(elm,data)=>{
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
    }
};
</script>

<style scoped>
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
:deep(.status-pill--success)   { background: rgba(23,198,83,.1);   color: var(--success-color); border-color: rgba(23,198,83,.25); }
:deep(.status-pill--danger)    { background: rgba(248,40,90,.1);  color: var(--danger-color); border-color: rgba(248,40,90,.25); }
:deep(.status-pill--warning)   { background: rgba(246,192,0,.1);  color: var(--warning-color); border-color: rgba(246,192,0,.25); }
:deep(.status-pill--secondary) { background: var(--light-bg);      color: var(--text-secondary); border-color: var(--border-color); }

:deep(.pickletable th), :deep(.pickletable td) {
    white-space: nowrap;
    max-width: 320px;
    overflow: hidden;
    text-overflow: ellipsis;
}
:deep(.pickletable thead) { --bs-emphasis-color: rgba(255,255,255,.6); }
:deep(.pickletable thead tr) { background: #154b91 !important; }
:deep(.pickletable thead th) {
    background: #154b91 !important;
    color: rgba(255,255,255,.85) !important;
    font-size: .82rem !important;
    font-weight: 600 !important;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding: 13px 16px !important;
    border: none !important;
    border-right: 1px solid rgba(255,255,255,.1) !important;
    white-space: nowrap;
}
:deep(.pickletable thead th:last-child) { border-right: none !important; }
:deep(.pickletable thead th svg),
:deep(.pickletable thead th i) { color: rgba(255,255,255,.6) !important; background: transparent !important; }
:deep(.pickletable thead th input) {
    background: rgba(255,255,255,.1) !important;
    border: 1px solid rgba(255,255,255,.2) !important;
    border-radius: 5px !important;
    color: #fff !important;
    font-size: .78rem !important;
    padding: 4px 8px !important;
    margin-top: 6px !important;
    width: 100% !important;
    outline: none !important;
}
:deep(.pickletable thead th input::placeholder) { color: rgba(255,255,255,.4) !important; }
:deep(.pickletable thead th input:focus) { background: rgba(255,255,255,.18) !important; border-color: rgba(255,255,255,.4) !important; }
:deep(.pickletable tbody tr) { border-bottom: 1px solid #eef0f4 !important; background: #fff !important; transition: background .12s; }
:deep(.pickletable tbody tr:hover) { background: #f7f9fd !important; }
:deep(.pickletable tbody td) {
    padding: 12px 16px !important;
    font-size: .9rem !important;
    color: #2d3748 !important;
    background: transparent !important;
    border: none !important;
    border-right: 1px solid #f0f2f7 !important;
    vertical-align: middle !important;
}
:deep(.pickletable tbody td:last-child) { border-right: none !important; }

/* Notifications */
.notifications-list {
  max-height: 300px;
  min-height: 300px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.notification-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 8px;
  border-radius: 8px;
}
.distribution-icon i {color: white !important;}
.notification-item:hover { background: #f8f9fa; cursor: pointer; }
.notification-icon {
  width: 15px;
  height: 15px;
  border-radius: 50%;
  background: linear-gradient(180deg, var(--primary-color) 0%, #0f3a6b 100%);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}
.notification-icon i { font-size:18px; }
.swal-notification-item i { font-size:16px; }
.swal-notification-item {
  padding: 12px !important;
  border-radius: 10px !important;
  border: 1px solid var(--border-color) !important;
  transition: all 0.2s ease !important;
}
.swal-notification-item:hover {
  background: rgba(21, 75, 145, 0.03) !important;
  border-color: var(--primary-color) !important;
}

.quick-action-icon i { color: #fff; font-size:20px; }
.quick-action-btn { display:flex; align-items:center; gap:12px; padding:18px; border-radius:12px; background:#fff; border:1px solid var(--border-color); transition: all 0.2s ease; }
.quick-action-btn:hover { border-color: var(--primary-color); box-shadow: 0 2px 8px rgba(21, 75, 145, 0.1); }
.quick-action-btn .quick-action-label { font-weight:600; color: var(--dark-text); }
.notification-content { flex: 1; display: flex; flex-direction: column; }
.notification-text { font-weight:600; color: var(--dark-text); margin:0 0 4px 0; }
.notification-time { font-size:12px; color: var(--text-secondary); }

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


/* Header Section */
.header-section {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  margin-bottom: 2.5rem;
  border-top: 3px solid var(--primary-color);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.greeting-title {
  font-size: 1.875rem;
  font-weight: 700;
  color: var(--dark-text);
  margin: 0;
  font-family: 'Inter', sans-serif;
}

.user-name {
  color: var(--primary-color);
  font-weight: 800;
}

.header-subtitle {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin: 0.5rem 0 0;
  font-weight: 500;
}

.header-icons {
  display: flex;
  gap: 1rem;
}

.icon-btn {
  width: 44px;
  height: 44px;
  border: none;
  background: var(--light-bg);
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
  transition: all 0.3s ease;
  border: 2px solid transparent;
}

.icon-btn:hover {
  background: lightblue;
  color: white;
  transform: scale(1.05);
  border-color: var(--primary-color);
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
  background: var(--danger-color);
  border-radius: 50%;
  animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  box-shadow: 0 0 0 rgba(248, 40, 90, 0.7);
}

@keyframes pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(248, 40, 90, 0.7);
  }
  50% {
    box-shadow: 0 0 0 6px rgba(248, 40, 90, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(248, 40, 90, 0);
  }
}

/* Stats Cards */
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
  /* box-shadow: 0 18px 40px rgba(21, 75, 145, 0.08); */
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  text-align: left;
  transition: transform 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease, background 0.35s ease;
  /* border: 1px solid rgba(21, 75, 145, 0.08); */
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
  /* border: 1px solid rgba(21, 75, 145, 0.08); */
  box-shadow: 0 16px 30px rgba(21, 75, 145, 0.06);
}

.stat-icon-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background: rgba(255,255,255,0.98);
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
  background: rgba(255,255,255,0.88);
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
    display: flex;
    flex-direction: column;
  }
}

.stat-subtext {
  display: block;
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin: 0.15rem 0 0.5rem 0;
  font-weight: 500;
}

.primary-link {
  color: var(--primary-color);
}

.primary-link:hover {
  color: #0f3a6b;
}

/* Main Content Grid */
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

.grid-col-1-3 {
  grid-column: span 1;
}

.grid-col-1-2 {
  grid-column: span 1;
}

.grid-col-1-3-full {
  grid-column: span 3;
}

/* Cards */
.chart-card,
.table-card,
.notifications-card,
.calendar-card,
.quick-actions-card,
.distribution-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  height: 100%;
  border: 1px solid var(--border-color);
}

.process-chart-card {
  justify-content: space-between;
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

/* Charts */
.chart-container {
  position: relative;
  height: 250px;
  margin-bottom: 1rem;
}

.chart-container.small-chart {
  height: 200px;
  margin-bottom: 1rem;
}

.process-chart-container {
  height: 240px;
  margin-bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pie-chart,
.line-chart,
.doughnut-chart {
  width: 100% !important;
  /* height: 100% !important; */
}

.chart-content {
  display: flex;
  gap: 2rem;
  align-items: center;
  margin-bottom: 1rem;
  min-height: 220px;
}

.chart-wrapper {
  flex: 0 0 45%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.chart-legend {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1rem;
}

.chart-legend-right {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
  padding-top: 0.5rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  font-size: 0.88rem;
}

.legend-text-group {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex: 1;
  gap: 0.8rem;
}

.legend-color {
  width: 14px;
  height: 14px;
  border-radius: 3px;
  flex-shrink: 0;
}

.legend-text {
  font-size: 0.8rem;
  color: var(--text-secondary);
  flex: 1;
  font-weight: 500;
}

.legend-value {
  font-weight: 700;
  color: var(--dark-text);
  font-size: 0.85rem;
  min-width: 28px;
  text-align: right;
}



/* Notifications */
.notifications-list i {
   width: 5px !important;
}
.notifications-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
 
  overflow-y: auto;
  margin-bottom: 1rem;
  flex: 1;
}

.notification-item {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-radius: 8px;
  transition: all 0.2s ease;
}

.notification-item.warning {
  background: rgba(246, 192, 0, 0.08);
  border-left: 3px solid var(--warning-color);
}

.notification-item.info {
  background: rgba(114, 57, 234, 0.08);
  border-left: 3px solid var(--info-color);
}

.notification-item.success {
  background: rgba(23, 198, 83, 0.08);
  border-left: 3px solid var(--success-color);
}

.notification-item.error {
  background: rgba(248, 40, 90, 0.08);
  border-left: 3px solid var(--danger-color);
}

.notification-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 50%;
}

.notification-item.warning .notification-icon {
  background: var(--warning-color);
  color: white;
}

.notification-item.info .notification-icon {
  background: var(--info-color);
  color: white;
}

.notification-item.success .notification-icon {
  background: var(--success-color);
  color: white;
}

.notification-item.error .notification-icon {
  background: var(--danger-color);
  color: white;
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-text {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--dark-text);
  margin: 0 0 0.25rem 0;
}

.notification-time {
  font-size: 0.8rem;
  color: var(--text-secondary);
  font-weight: 500;
}

/* Calendar */
.calendar-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  max-height: 300px;
  overflow-y: auto;
  margin-bottom: 1rem;
  flex: 1;
}

.calendar-item {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-radius: 10px;
  background: linear-gradient(135deg, rgba(21,75,145,0.03) 0%, rgba(21,75,145,0.01) 100%);
  transition: all 0.2s ease;
  border: 1px solid var(--border-color);
}

.calendar-item:hover {
  background: linear-gradient(135deg, rgba(21,75,145,0.08) 0%, rgba(21,75,145,0.04) 100%);
  border-color: var(--primary-color);
}

.calendar-icon {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--primary-color) 0%, #0f3a6b 100%);
  color: black !important;
  border-radius: 8px;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(21,75,145,0.15);
}

.calendar-content {
  flex: 1;
  min-width: 0;
}

.calendar-text {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--dark-text);
  margin: 0 0 0.25rem 0;
}

.calendar-date {
  font-size: 0.8rem;
  color: var(--text-secondary);
  font-weight: 500;
}

/* Quick Actions */
.quick-actions-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.25rem;
  flex: 1;
}

.quick-action-btn {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1.25rem;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: #fff;
  color: var(--dark-text);
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.75rem;
  font-weight: 600;
  text-align: center;
  line-height: 1.2;
  position: relative;
}

.quick-action-btn::after {
    content: "\f054";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    position: absolute;
    right: 34px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    color: #07123f;
    opacity: 0.9;
}

.quick-action-btn:hover {
  border-color: var(--primary-color);
  box-shadow: 0 4px 12px rgba(21, 75, 145, 0.08);
  transform: translateY(-2px);
}

.quick-action-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.quick-action-label {
  display: block;
  word-break: break-word;
  font-weight: 600;
}

.quick-action-btn:hover {
  background: white;
  border-color: var(--primary-color);
  box-shadow: 0 4px 12px rgba(21, 75, 145, 0.08);
  transform: translateY(-2px);
}

.quick-action-btn:hover .quick-action-icon {
  transform: scale(1.1);
}

/* Distribution Cards */
.distribution-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
  align-items: start;
}

.distribution-card {
  display: flex;
  gap: 1rem;
  padding: 1.25rem;
  border-radius: 12px;
  /* background: #ffffff; */
  transition: all 0.2s ease;
  /* align-items: center; */
  /* box-shadow: 0 2px 8px rgba(21,75,145,0.04); */
}

/* .distribution-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(21,75,145,0.08);
  border-color: var(--primary-color);
} */

.distribution-icon {
  /* flex: 0 0 56px; */
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(180deg, var(--primary-color) 0%, #0f3a6b 100%);
  color: black !important;
  box-shadow: 0 6px 18px rgba(21,75,145,0.12);
  flex-shrink: 0;
}
.distribution-icon i {
  color: black !important;
  display: flex;
  align-items: center;
  justify-content: center;

}

.distribution-content {
  flex: 1;
  min-width: 0;
}

.distribution-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--dark-text);
  margin: 0 0 0.4rem 0;
}

.distribution-stats {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.stat-item {
  font-size: 0.75rem;
  color: #8a92a3;
}

.stat-item strong {
  color: var(--dark-text);
  font-weight: 700!important;
  font-size: 22px!important;
  line-height: 22px!important;
}

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
  .stats-section {
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
  }

  .grid-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 1024px) {
  .stats-section {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }

  .grid-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .admin-dashboard {
    padding: 1rem;
  }

  .stats-section {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
  }

  .chart-content {
    flex-direction: column;
    gap: 1rem;
  }

  .chart-wrapper {
    flex: 1 !important;
  }

  .chart-legend-right {
    flex: 1;
  }

  .header-content {
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }

  .grid-row {
    grid-template-columns: 1fr;
    gap: 1.25rem;
  }

  .distribution-cards {
    grid-template-columns: repeat(2, 1fr);
  }

  .quick-actions-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }
}

@media (max-width: 576px) {
  .admin-dashboard {
    padding: 0.75rem;
  }

  .stats-section {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }

  .stat-card {
    padding: 1rem 0.75rem;
  }

  .greeting-title {
    font-size: 1.5rem;
  }

  .chart-container {
    height: 200px;
  }

  .quick-actions-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }

  .quick-action-btn {
    padding: 0.75rem;
  }
}


/* .notifications-card {
    background: #fff;
    border-radius: 38px;
    padding: 32px;
    box-shadow:
        0 10px 40px rgba(15, 23, 42, 0.06),
        0 2px 10px rgba(15, 23, 42, 0.04);
    border: 1px solid #f1f3f9;
    max-width: 900px;
    width: 100%;
    font-family: Inter, sans-serif;
} */


.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    /* margin-bottom: 28px; */
}
/* 
.card-title {
    position: relative;
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 34px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
} */

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
    overflow: hidden;
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
    border-radius: 22px;

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

    font-size: 31px;
    line-height: 1.45;
    font-weight: 700;
    color: #0f172a;

    margin: 0 0 14px 0;

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

    font-size: 22px;
    color: #8a94a6;
    font-weight: 500;
    margin-left: 27px;
}

.notification-time::before {
    content: "\f073";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    font-size: 18px;
}
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
    width: 100%;

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









.quick-actions-card {
  background: white;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.04);
  width: 100%;
}

.card-title {
  margin: 0 0 16px 0;
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  letter-spacing: 0.3px;
}

.quick-actions-grid {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.quick-action-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 18px;
  cursor: pointer;
  transition: all 0.2s ease;
  background-color: white;
  flex: 1;
  min-width: 160px;
}

.quick-action-btn:hover {
  background-color: #f8fafc;
  border-color: #cbd5e1;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.quick-action-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 18px;
}

.quick-action-icon i {
  font-style: normal;
}

/* İkonlar için geçici stil - kendi ikon fontunuzu kullanın */
.ki-outline {
  display: inline-block;
  width: 20px;
  height: 20px;
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
}

/* .ki-document {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='white' viewBox='0 0 24 24' width='18' height='18'%3E%3Cpath d='M4 4C4 2.89543 4.89543 2 6 2H14L20 8V20C20 21.1046 19.1046 22 18 22H6C4.89543 22 4 21.1046 4 20V4Z' stroke='white' stroke-width='1.5' fill='none'/%3E%3Cpath d='M14 2V8H20' stroke='white' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");
}

.ki-user {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='white' viewBox='0 0 24 24' width='18' height='18'%3E%3Cpath d='M12 12C14.2091 12 16 10.2091 16 8C16 5.79086 14.2091 4 12 4C9.79086 4 8 5.79086 8 8C8 10.2091 9.79086 12 12 12Z' stroke='white' stroke-width='1.5' fill='none'/%3E%3Cpath d='M5 20V19C5 15.6863 7.68629 13 11 13H13C16.3137 13 19 15.6863 19 19V20' stroke='white' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");
} */

.quick-action-label {
  font-size: 14px;
  font-weight: 500;
  color: #1e293b;
}




/* Distribution Section - Aylık Santral Bazlı Dağılım */
.distribution-section {
  background: white;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.04);
}

.distribution-section .card-title {
  margin: 0 0 16px 0;
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  letter-spacing: 0.3px;
}

.distribution-cards {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.distribution-card {
  display: flex;
  /* align-items: center; */
  gap: 14px;
  /* background: #f8fafc; */
  /* border-radius: 14px; */
  /* padding: 14px 16px; */
  transition: all 0.2s ease;
  padding: unset;
  /* border: 1px solid #eef2f6; */
}

/* .distribution-card:hover {
  background: #f1f5f9;
  border-color: #e2e8f0;
} */

.distribution-icon {
  width: 50px;
  height: 50px;
  background: white;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #0d6efd;
  font-size: 22px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  border: 1px solid #e9ecef;
}

.distribution-content {
  flex: 1;
}

.distribution-label {
  margin: 0 0 6px 0;
  font-size: 18px;
  line-height: 18px;
  font-weight: 900;
  color: #1e293b;
}

.distribution-stats {
  display: flex;
  /* gap: 20px; */
  flex-wrap: wrap;
}

.stat-item {
  font-size: 12px;
  color: #5b6e8c;
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.stat-item strong {
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
  margin-right: 2px;
}

/* Toplam kartı özel stili */
.totals-card .distribution-label {
  color: #0a58ca;
}

.totals-card .stat-item strong {
  color: #0a58ca;
}

.totals-card .distribution-icon {
  background: #e2f0ff;
  border-color: #b6e0fe;
}



</style>
