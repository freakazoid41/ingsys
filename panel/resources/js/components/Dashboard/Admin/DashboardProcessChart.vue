<template>
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
    <router-link :to="{ name: 'OList' }" class="notifications-footer-btn">
      Detaylara Git <i class="fa-solid fa-angle-right"></i>
    </router-link>
  </div>
</template>

<script>
import Chart from 'chart.js/auto';
import Plib from '@/lib/pickle';

export default {
  name: 'DashboardProcessChart',
  data() {
    return {
      processData: [
        { label: 'Değerlendirme Aşamasında', value: 28, color: '#0d6efd' },
        { label: 'Revize Bekliyor', value: 12, color: '#ffc107' },
        { label: 'Onaylandı', value: 29, color: '#198754' },
        { label: 'Reddedildi', value: 8, color: '#e74c3c' },
        { label: 'İptal Edildi', value: 5, color: '#95a5a6' },
        { label: 'Taslak', value: 35, color: '#d3d3d3' }
      ],
      charts: {}
    };
  },
  mounted() {
    this.monthlyOffersLoad();
    this.$nextTick(() => {
      this.initCharts();
    });
  },
  beforeUnmount() {
    if (this.charts.processChart) {
      this.charts.processChart.destroy();
    }
  },
  methods: {
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
    initCharts() {
      const processCtx = this.$refs.processChart?.getContext('2d');
      if (processCtx) {
        const centerTextPlugin = {
          id: 'centerText',
          beforeDatasetsDraw(chart) {
            const ctx = chart.ctx;
            ctx.save();
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
            ctx.font = 'bold 32px Arial';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'center';
            ctx.fillStyle = '#212529';

            const text = String(totalValue);
            const textX = width / 2;
            const textY = height / 2 - 8;
            ctx.fillText(text, textX, textY);

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
    }
  }
};
</script>

<style scoped>
.chart-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px #0000000d;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    height: 100%;
    border: 1px solid var(--border-color);
}

.process-chart-card {
  justify-content: space-between !important;
}

.card-title {
      margin: 0 0 16px;
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    letter-spacing: .3px;
}

.chart-container {
      display: flex;
    gap: 2rem;
    align-items: center;
    margin-bottom: 1rem;
    min-height: 230px;
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
  height: 100% !important;
}

.chart-content {
    display: flex;
    gap: 2rem;
    align-items: center;
    margin-bottom: 1rem;
    height: 220px !important;
}

.chart-wrapper {
      flex: 0 0 45%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chart-legend-right {
  flex: 1;
    display: flex;
    flex-direction: column;
    gap: .8rem;
    padding-top: .5rem;
}

.legend-item {
  display: flex;
    align-items: center;
    gap: .7rem;
    font-size: .88rem;
}

.legend-text-group {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex: 1;
  gap: 0.75rem;
}

.legend-color {
  width: 14px;
  height: 14px;
  border-radius: 3px;
  flex-shrink: 0;
}

.legend-text {
  font-size: 0.9rem;
  color: var(--text-secondary);
  flex: 1;
  font-weight: 600;
}

.legend-value {
  font-weight: 700;
  color: var(--dark-text);
  font-size: 0.9rem;
  min-width: 32px;
  text-align: right;
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
    transition: .3s;
    background: #cccccc2d;
    font-size: 1rem;
    height: 45px;
    border-radius: 10px;
}

.notifications-footer-btn:hover {
  color: #0f3a6b;
  transform: translateX(4px);
}

@media (max-width: 1024px) {
  .chart-content {
    gap: 1.4rem;
  }
}

@media (max-width: 768px) {
  .chart-content {
    flex-direction: column;
    gap: 1rem;
  }

  .chart-wrapper,
  .chart-legend-right {
    width: 100%;
    flex: 1;
  }

  .chart-container {
    max-width: 100%;
    height: 260px;
  }
}
</style>
