<template>
  <div class="adm-monthly">
    <div class="adm-monthly__head">
      <h5 class="adm-monthly__title">Aylık Sipariş Trendi</h5>
      <span class="adm-monthly__sub">Son 6 ay</span>
    </div>
    <div class="adm-monthly__canvas-wrap">
      <canvas ref="chart" class="adm-monthly__canvas"></canvas>
    </div>
  </div>
</template>
<script>
import Plib from '@/lib/pickle';
let ChartJS = null;
export default {
  name: 'AdminMonthlyChart',
  data(){ return { data: [], _chart: null }; },
  mounted(){ this.load(); },
  beforeUnmount(){ if(this._chart) this._chart.destroy(); },
  methods:{
    async load(){
      try{
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/admin-monthly', method: 'GET' }, null);
        if(Array.isArray(rsp)) this.data = rsp;
      }catch(e){ console.error('admin-monthly failed',e); }
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
            backgroundColor: values.map(v=> v>0 ? '#154b91' : '#e2e8f0'),
            borderRadius: 8,
            borderSkipped: false,
            barThickness: 22,
            maxBarThickness: 28,
            hoverBackgroundColor: '#1e40af'
          }]
        },
        options:{
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ display:false }, tooltip:{ backgroundColor:'#0f172a', padding:10, cornerRadius:10, titleFont:{size:12}, bodyFont:{size:12} } },
          scales:{
            y:{ beginAtZero:true, ticks:{ precision:0, color:'#94a3b8', font:{ size:11 } }, grid:{ color:'#f1f5f9' }, border:{ display:false } },
            x:{ ticks:{ color:'#64748b', font:{ size:11, weight:600 } }, grid:{ display:false }, border:{ display:false } }
          },
          animation:{ duration:800 }
        }
      });
    }
  }
};
</script>
<style scoped>
.adm-monthly{ background:#fff; border-radius:18px; padding:1.5rem; border:1px solid #eef2f7; box-shadow:0 1px 3px rgba(0,0,0,0.04); display:flex; flex-direction:column; }
.adm-monthly__head{ display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
.adm-monthly__title{ font-size:0.95rem; font-weight:800; color:#0f172a; margin:0; }
.adm-monthly__sub{ font-size:0.72rem; font-weight:700; color:#64748b; background:#f8fafc; border:1px solid #e2e8f0; padding:0.3rem 0.7rem; border-radius:999px; }
.adm-monthly__canvas-wrap{ height:220px; position:relative; }
.adm-monthly__canvas{ width:100% !important; height:100% !important; }
</style>
