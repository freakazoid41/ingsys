<template>
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
</template>

<script>
import Plib from '@/lib/pickle';

export default {
  name: 'DashboardDistribution',
  data() {
    return {
      branchDistribution: []
    };
  },
  computed: {
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
  mounted() {
    this.monthlyDistributionLoad();
  },
  methods: {
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
    }
  }
};
</script>

<style scoped>
.distribution-section {
  background: #fff;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 1px 3px #00000014, 0 4px 12px #0000000a;
}

.distribution-section .card-title {
  margin: 0 0 16px;
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  letter-spacing: .3px;
}

.distribution-cards {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.distribution-card {
  display: flex;
  align-items: center;
  gap: 14px;
  transition: all .2s ease;
  padding: unset;
}

.distribution-icon {
  width: 50px;
  height: 50px;
  background: #fff;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #0d6efd;
  font-size: 22px;
  box-shadow: 0 1px 2px #0000000d;
  border: 1px solid #e9ecef;
}

.distribution-icon i {
  display: flex;
  align-items: center;
  justify-content: center;
  color: black;
}

.distribution-content {
  flex: 1;
  min-width: 0;
}

.distribution-label {
  margin: 0 0 6px;
  font-size: 18px;
  line-height: 18px;
  font-weight: 900;
  color: #1e293b;
}

.distribution-stats {
  display: flex;
  flex-wrap: wrap;
  flex-direction:column;
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

.totals-card .distribution-label,
.totals-card .stat-item strong {
  color: #0a58ca;
}

.totals-card .distribution-icon {
  background: #e2f0ff;
  border-color: #b6e0fe;
}

@media (max-width: 991px) {
  .distribution-cards {
    flex-direction: column;
    gap: 12px;
  }
}

@media (max-width: 768px) {
  .distribution-cards {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }
}

@media (max-width: 576px) {
  .distribution-section {
    padding: 16px;
  }
}
</style>
