<template>
  <div class="quick-ops-section">
    <h5 class="section-title">Hızlı İşlemler</h5>
    <div class="quick-ops-grid">
      <div class="quick-op-card" @click="handleQuickAction('requests')">
        <div class="quick-op-icon primary-icon">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="28" height="28">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <div class="quick-op-content">
          <h6 class="quick-op-title">Talepler</h6>
          <p class="quick-op-desc">Talepleri görüntüle</p>
        </div>
        <span class="quick-op-arrow" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 18l6-6-6-6" />
          </svg>
        </span>
      </div>

      <div class="quick-op-card" @click="handleQuickAction('offers')">
        <div class="quick-op-icon success-icon">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="28" height="28">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
        </div>
        <div class="quick-op-content">
          <h6 class="quick-op-title">Teklifler</h6>
          <p class="quick-op-desc">Tekliflerinizi yönetin</p>
        </div>
        <span class="quick-op-arrow" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 18l6-6-6-6" />
          </svg>
        </span>
      </div>

      <div class="quick-op-card" @click="handleQuickAction('companies')">
        <div class="quick-op-icon info-icon">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="28" height="28">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
          </svg>
        </div>
        <div class="quick-op-content">
          <h6 class="quick-op-title">Firmalar</h6>
          <p class="quick-op-desc">Firma bilgilerini yönetin</p>
        </div>
        <span class="quick-op-arrow" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 18l6-6-6-6" />
          </svg>
        </span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ClientQuickOps',
  methods: {
    handleQuickAction(action) {
      const routeMap = {
        offers: 'OList',
        companies: 'CList',
        requests: 'RequestList'
      };
      const routeName = routeMap[action];
      if (routeName) {
        this.$router.push({ name: routeName });
      }
    }
  }
};
</script>

<style scoped>
.quick-ops-section {
  margin-bottom: 2.5rem;
  background: #fff;
  border: 1px solid #edf1f8;
  border-radius: 24px;
  padding: 1.75rem 1.75rem;
}

.quick-ops-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
  margin-top: 1.5rem;
}

.quick-op-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  background: #fff;
  border-radius: 20px;
  padding: 1.3rem 1.4rem;
  min-height: 118px;
  cursor: pointer;
  transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
}

.quick-op-card:hover {
  transform: translateY(-2px);
  border-color: #d8e4ff;
  box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
}

.quick-op-icon {
  min-width: 56px;
  min-height: 56px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.primary-icon {
  background: rgba(13, 110, 253, 0.12);
  color: #0d6efd;
}

.success-icon {
  background: rgba(25, 135, 84, 0.12);
  color: #198754;
}

.info-icon {
  background: rgba(13, 202, 240, 0.12);
  color: #0dcaf0;
}

.quick-op-content {
  min-width: 0;
  flex: 1;
}

.quick-op-title {
  margin: 0 0 0.25rem;
  font-weight: 700;
  font-size: 1rem;
  color: #14172b;
}

.quick-op-desc {
  margin: 0;
  color: #667085;
  font-size: 0.92rem;
  line-height: 1.5;
}

.quick-op-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #98a2b3;
}

.quick-op-arrow svg {
  display: block;
}

.section-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: #101828;
}

@media (max-width: 1024px) {
  .quick-ops-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .quick-ops-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.9rem;
  }
}

@media (max-width: 576px) {
  .quick-ops-grid {
    grid-template-columns: 1fr;
  }
}
</style>
