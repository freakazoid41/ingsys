<template>
  <div class="tdk-monthly">
    <div class="tdk-monthly__head">
      <h5 class="tdk-monthly__title">Aylık Trend</h5>
      <span class="tdk-monthly__sub">Son 6 ay</span>
    </div>
    <div class="tdk-monthly__canvas-wrap">
      <canvas ref="chart" class="tdk-monthly__canvas"></canvas>
    </div>
  </div>
</template>
<script>
import Plib from '@/lib/pickle';
let ChartJS = null;
export default {
  name: 'PlanlamaMonthlyChart',
  data(){ return { data: [], _chart: null }; },
  mounted(){ this.load(); },
  beforeUnmount(){ if(this._chart) this._chart.destroy(); },
  methods:{
    async load(){
      try{
        const rsp = await (new Plib).request({ url: '/api/v1/table/documents', method: 'POST', data: { filter: [{ key: 'form-type', type: '=', value: 'op-doc-requestf-form' }, { key: 'type', type: '=', value: 'op-doc-requestf' }], scale: { page: 1, limit: 200 } } }, null);
        const rows = rsp?.data || [];
        const map = {};
        (Array.isArray(rows) ? rows : []).forEach(r => {
          const d = r.created_at ? new Date(r.created_at) : null;
          if (!d || isNaN(d)) return;
          const k = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
          map[k] = (map[k] || 0) + 1;
        });
        const keys = Object.keys(map).sort().slice(-6);
        this.data = keys.map(k => ({ label: k, value: map[k] }));
      }catch(e){ console.error('planlama-monthly failed',e); }
      if(this.data.length) this.$nextTick(()=>this.init());
    },
    async init(){
      if(!ChartJS){ const m=await import('chart.js/auto'); ChartJS=m.default; }
      const ctx=this.$refs.chart?.getContext('2d'); if(!ctx) return;
      const labels=this.data.map(d=>d.label);
      const values=this.data.map(d=>d.value);
      this._chart=new ChartJS(ctx,{
        type:'bar',
        data:{
          labels,
          datasets:[{
            data: values,
            backgroundColor: values.map(v=> v>0 ? '#FF5A1F' : '#ffe4cc'),
            borderRadius: 8,
            borderSkipped: false,
            barThickness: 22,
            maxBarThickness: 28,
            hoverBackgroundColor: '#e04e0f'
          }]
        },
        options:{
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ display:false }, tooltip:{ backgroundColor:'#431407', padding:10, cornerRadius:10, titleFont:{size:12}, bodyFont:{size:12} } },
          scales:{
            y:{ beginAtZero:true, ticks:{ precision:0, color:'#9a3412', font:{ size:11 } }, grid:{ color:'#fff7ed' }, border:{ display:false } },
            x:{ ticks:{ color:'#9a3412', font:{ size:11, weight:600 } }, grid:{ display:false }, border:{ display:false } }
          },
          animation:{ duration:800 }
        }
      });
    }
  }
};
</script>
<style scoped>
.tdk-monthly{ background:#fff; border-radius:18px; padding:1.5rem; border:1px solid #ffe4cc; box-shadow:0 1px 3px rgba(255,90,31,0.06); display:flex; flex-direction:column; }
.tdk-monthly__head{ display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
.tdk-monthly__title{ font-size:0.95rem; font-weight:800; color:#431407; margin:0; }
.tdk-monthly__sub{ font-size:0.72rem; font-weight:700; color:#9a3412; background:#fff7ed; border:1px solid #fed7aa; padding:0.3rem 0.7rem; border-radius:999px; }
.tdk-monthly__canvas-wrap{ height:220px; position:relative; }
.tdk-monthly__canvas{ width:100% !important; height:100% !important; }
</style>
