<template>
  <div class="adm-chart">
    <div class="adm-chart__head">
      <h5 class="adm-chart__title">Sipariş Durumu</h5>
      <span class="adm-chart__badge">{{ total }} toplam</span>
    </div>
    <div class="adm-chart__body">
      <div class="adm-chart__canvas-wrap">
        <div v-if="!data.length && !loading" class="adm-chart__empty">Veri yok</div>
        <canvas v-show="data.length" ref="chart" class="adm-chart__canvas"></canvas>
      </div>
      <div class="adm-chart__legend">
        <div v-for="item in data" :key="item.key || item.label" class="adm-chart__item" @click="filterBy(item)">
          <span class="adm-chart__dot" :style="{ background: item.color }"></span>
          <span class="adm-chart__label">{{ item.label }}</span>
          <span class="adm-chart__val">{{ item.value }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Plib from '@/lib/pickle';
let ChartJS = null;
export default {
  name: 'AdminStatusChart',
  data() { return { data: [], loading: true, _chart: null }; },
  computed: { total() { return this.data.reduce((s,v)=>s+(Number(v.value)||0),0); } },
  mounted() { this.load(); },
  beforeUnmount() { if (this._chart) { this._chart.destroy(); this._chart = null; } },
  methods: {
    async load() {
      try {
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/admin-status', method: 'GET' }, null);
        if (Array.isArray(rsp) && rsp.length) this.data = rsp.filter(i=> i.value>0 || rsp.length<=1);
      } catch(e){ console.error('admin-status failed', e); }
      this.loading = false;
      if(this.data.length) this.$nextTick(()=>this.initChart());
    },
    async initChart() {
      if(!ChartJS){ const mod = await import('chart.js/auto'); ChartJS = mod.default; }
      const ctx = this.$refs.chart?.getContext('2d'); if(!ctx) return;
      if(this._chart) this._chart.destroy();
      this._chart = new ChartJS(ctx, {
        type: 'doughnut',
        data: {
          labels: this.data.map(d=>d.label),
          datasets: [{ data: this.data.map(d=>d.value), backgroundColor: this.data.map(d=>d.color), borderColor: '#fff', borderWidth: 3, hoverOffset: 6 }]
        },
        options: {
          responsive: true, maintainAspectRatio: true, cutout: '68%',
          plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', titleFont: { size: 13 }, bodyFont: { size: 12 }, padding: 10, cornerRadius: 10 } },
          animation: { duration: 700, easing: 'easeOutQuart' }
        },
        plugins: [{
          id: 'admCenter',
          beforeDatasetsDraw(chart){
            const c=chart.ctx; c.save();
            const total = chart.data.datasets[0].data.reduce((s,v)=>s+(Number(v)||0),0);
            c.font='bold 30px Inter, sans-serif'; c.textBaseline='middle'; c.textAlign='center'; c.fillStyle='#0f172a';
            c.fillText(String(total), chart.width/2, chart.height/2 - 8);
            c.font='600 11px Inter, sans-serif'; c.fillStyle='#94a3b8'; c.fillText('TOPLAM', chart.width/2, chart.height/2 + 14);
            c.restore();
          }
        }]
      });
    },
    filterBy(item){
      // quick filter: push to OrderList with status filter
      if(item.key && item.key!=='empty'){
        this.$router.push({ name: 'OrderList', query: { status: item.key } }).catch(()=>{});
        // fallback: if no query handling, just go to list
        // OList will handle initialFilter but we also support query param in future
      }
    }
  }
};
</script>

<style scoped>
.adm-chart { background: #fff; border-radius: 18px; padding: 1.5rem; border: 1px solid #eef2f7; display: flex; flex-direction: column; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.adm-chart__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.1rem; }
.adm-chart__title { font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0; }
.adm-chart__badge { font-size: 0.72rem; font-weight: 700; color: #154b91; background: #eff6ff; border: 1px solid #bfdbfe; padding: 0.3rem 0.7rem; border-radius: 999px; }
.adm-chart__body { display: flex; gap: 1.4rem; align-items: center; flex: 1; }
.adm-chart__canvas-wrap { flex: 0 0 46%; display: flex; align-items: center; justify-content: center; min-height: 200px; }
.adm-chart__canvas { width: 100% !important; max-height: 210px; }
.adm-chart__empty { font-size: 0.85rem; color: #9ca3af; }
.adm-chart__legend { flex: 1; display: flex; flex-direction: column; gap: 0.55rem; }
.adm-chart__item { display: flex; align-items: center; gap: 0.6rem; padding: 0.45rem 0.6rem; border-radius: 10px; cursor: pointer; transition: background 0.15s; }
.adm-chart__item:hover { background: #f8fafc; }
.adm-chart__dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.adm-chart__label { flex: 1; font-size: 0.82rem; color: #475569; font-weight: 600; }
.adm-chart__val { font-size: 0.85rem; font-weight: 800; color: #0f172a; min-width: 24px; text-align: right; }
@media (max-width: 768px){ .adm-chart__body{ flex-direction: column; } .adm-chart__canvas-wrap{ flex: none; width: 100%; } }
</style>
