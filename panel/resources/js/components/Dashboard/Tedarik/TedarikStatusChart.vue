<template>
  <div class="tdk-chart">
    <h5 class="tdk-chart__title">Sipariş Durumu</h5>
    <div class="tdk-chart__body">
      <div class="tdk-chart__canvas-wrap">
        <div v-if="!data.length && !loading" class="tdk-chart__empty">Veri yok</div>
        <canvas v-show="data.length" ref="chart" class="tdk-chart__canvas"></canvas>
      </div>
      <div class="tdk-chart__legend">
        <div v-for="item in data" :key="item.label" class="tdk-chart__item">
          <span class="tdk-chart__dot" :style="{ background: item.color }"></span>
          <span class="tdk-chart__label">{{ item.label }}</span>
          <span class="tdk-chart__val">{{ item.value }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Plib from '@/lib/pickle';

let ChartJS = null;

export default {
  name: 'TedarikStatusChart',
  data() {
    return { data: [], loading: true, _chart: null };
  },
  mounted() { this.load(); },
  beforeUnmount() { if (this._chart) { this._chart.destroy(); this._chart = null; } },
  methods: {
    async load() {
      try {
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/tedarik-status', method: 'GET' }, null);
        if (Array.isArray(rsp) && rsp.length) this.data = rsp;
      } catch (e) { console.error('tedarik-status failed', e); }
      this.loading = false;
      if (this.data.length) {
        this.$nextTick(() => this.initChart());
      }
    },
    async initChart() {
      if (!ChartJS) {
        const mod = await import('chart.js/auto');
        ChartJS = mod.default;
      }
      const ctx = this.$refs.chart?.getContext('2d');
      if (!ctx) return;
      this._chart = new ChartJS(ctx, {
        type: 'doughnut',
        data: {
          labels: this.data.map(d => d.label),
          datasets: [{ data: this.data.map(d => d.value), backgroundColor: this.data.map(d => d.color), borderColor: '#fff', borderWidth: 2 }]
        },
        options: {
          responsive: true, maintainAspectRatio: true, cutout: '68%',
          plugins: { legend: { display: false } },
          animation: { duration: 600 }
        },
        plugins: [{
          id: 'tdkCenter',
          beforeDatasetsDraw(chart) {
            const c = chart.ctx; c.save();
            const total = chart.data.datasets[0].data.reduce((s, v) => s + (Number(v) || 0), 0);
            c.font = 'bold 28px Inter, sans-serif'; c.textBaseline = 'middle'; c.textAlign = 'center'; c.fillStyle = '#1e293b';
            c.fillText(total, chart.width / 2, chart.height / 2 - 6);
            c.font = '11px Inter, sans-serif'; c.fillStyle = '#9ca3af';
            c.fillText('Toplam', chart.width / 2, chart.height / 2 + 14);
            c.restore();
          }
        }],
      });
    }
  }
};
</script>

<style scoped>
.tdk-chart { background: #fff; border-radius: 14px; padding: 1.5rem; border: 1px solid #f1f5f9; display: flex; flex-direction: column; }
.tdk-chart__title { font-size: 0.95rem; font-weight: 700; color: #111827; margin: 0 0 1rem; }
.tdk-chart__body { display: flex; gap: 1.5rem; align-items: center; flex: 1; }
.tdk-chart__canvas-wrap { flex: 0 0 45%; display: flex; align-items: center; justify-content: center; }
.tdk-chart__canvas { width: 100% !important; max-height: 200px; }
.tdk-chart__empty { font-size: 0.85rem; color: #9ca3af; }
.tdk-chart__legend { flex: 1; display: flex; flex-direction: column; gap: 0.6rem; }
.tdk-chart__item { display: flex; align-items: center; gap: 0.6rem; }
.tdk-chart__dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.tdk-chart__label { flex: 1; font-size: 0.8rem; color: #6b7280; font-weight: 500; }
.tdk-chart__val { font-size: 0.85rem; font-weight: 700; color: #111827; }
@media (max-width: 768px) { .tdk-chart__body { flex-direction: column; } .tdk-chart__canvas-wrap { flex: none; width: 100%; } }
</style>
