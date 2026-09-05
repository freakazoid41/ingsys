<template>
  <div class="adm-orders">
    <div class="adm-orders__head">
      <h5 class="adm-orders__title">Son Siparişler</h5>
      <router-link :to="{ name: 'OrderList' }" class="adm-orders__link">Tümünü Gör →</router-link>
    </div>
    <div v-if="loading" class="adm-orders__loading"><div class="adm-orders__spinner"></div></div>
    <div v-else-if="!orders.length" class="adm-orders__empty">Henüz sipariş bulunmuyor</div>
    <div v-else class="adm-orders__table-wrap">
      <table class="adm-orders__table">
        <thead><tr><th>Sipariş No</th><th>Firma</th><th>Alım Kodu</th><th>Tarih</th><th>Durum</th></tr></thead>
        <tbody>
          <tr v-for="row in orders" :key="row.id" class="adm-orders__row" @click="go(row.id)">
            <td class="adm-orders__cell--bold">{{ row._orderNo || '—' }}</td>
            <td class="adm-orders__cell--firm">{{ row._ctitle || '—' }}</td>
            <td class="adm-orders__cell--muted">{{ row._buyingNo || '—' }}</td>
            <td class="adm-orders__cell--muted">{{ row._dateFmt }}</td>
            <td><span class="adm-pill" :class="row._statusCls">{{ row._statusLabel }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
<script>
import Plib from '@/lib/pickle';
const SKEY = {
  'doc_trans_order_created': ['Yeni', 'adm-pill--gray'],
  'doc_trans_order_transfer_sent': ['Kontrol Bekliyor', 'adm-pill--amber'],
  'doc_trans_order_ready_for_shipment': ['Sevke Hazır', 'adm-pill--blue'],
  'doc_trans_order_approved': ['Onaylandı', 'adm-pill--green'],
  'doc_trans_order_rejected': ['Reddedildi', 'adm-pill--red'],
  'doc_trans_order_files_rejected': ['Dosya Reddedildi', 'adm-pill--orange'],
};
export default {
  name: 'AdminRecentOrders',
  data(){ return { loading:true, orders:[] }; },
  mounted(){ this.load(); },
  methods:{
    async load(){
      try{
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/admin-orders', method: 'GET' }, null);
        if(!Array.isArray(rsp)) return;
        this.orders = rsp.map(r=>{
          let orderNo='', ctitle='', buyingNo='';
          try{
            JSON.parse(r.main_attr||'[]').forEach(a=>{
              if(a.Key==='order_no') orderNo=a.Value;
              if(a.Key==='ctitle') ctitle=a.Value;
              if(a.Key==='buying_no') buyingNo=a.Value;
            });
          }catch{}
          const statusKey=(r.status||'').split('**')[0];
          const sl=SKEY[statusKey]||['Bilinmiyor','adm-pill--gray'];
          return {
            ...r,
            _orderNo: orderNo,
            _ctitle: ctitle,
            _buyingNo: buyingNo,
            _dateFmt: r.created_at ? new Date(r.created_at).toLocaleDateString('tr-TR',{day:'2-digit',month:'2-digit',year:'numeric'}) : '—',
            _statusLabel: sl[0],
            _statusCls: sl[1],
          };
        });
      }catch(e){ console.error('admin-orders failed',e); }
      finally{ this.loading=false; }
    },
    go(id){ this.$router.push({ name: 'OrderForm', params: { id } }).catch(()=>{}); }
  }
};
</script>
<style scoped>
.adm-orders{ background:#fff; border-radius:18px; padding:1.5rem; border:1px solid #eef2f7; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
.adm-orders__head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.adm-orders__title{ font-size:0.95rem; font-weight:800; color:#0f172a; margin:0; }
.adm-orders__link{ font-size:0.8rem; font-weight:700; color:#154b91; text-decoration:none; }
.adm-orders__link:hover{ color:#0f3a6b; }
.adm-orders__loading{ display:flex; justify-content:center; padding:2rem; }
.adm-orders__spinner{ width:28px; height:28px; border:3px solid #e5e7eb; border-top-color:#154b91; border-radius:50%; animation: adm-spin 0.6s linear infinite; }
@keyframes adm-spin{ to{ transform:rotate(360deg);} }
.adm-orders__empty{ text-align:center; padding:2rem; color:#9ca3af; font-size:0.9rem; }
.adm-orders__table-wrap{ overflow-x:auto; }
.adm-orders__table{ width:100%; border-collapse:collapse; }
.adm-orders__table th{ text-align:left; font-size:0.68rem; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:0.06em; padding:0 0 0.75rem; border-bottom:1px solid #f1f5f9; white-space:nowrap; }
.adm-orders__table td{ padding:0.75rem 0; font-size:0.85rem; color:#334155; border-bottom:1px solid #f9fafb; white-space:nowrap; }
.adm-orders__row{ cursor:pointer; transition: background 0.15s; }
.adm-orders__row:hover{ background:#f8fafc; }
.adm-orders__cell--bold{ font-weight:800; color:#0f172a; }
.adm-orders__cell--firm{ font-weight:600; color:#1e293b; max-width:180px; overflow:hidden; text-overflow:ellipsis; }
.adm-orders__cell--muted{ color:#64748b; font-weight:500; }
.adm-pill{ display:inline-block; padding:0.22rem 0.7rem; border-radius:999px; font-size:0.72rem; font-weight:700; white-space:nowrap; border:1px solid transparent; }
.adm-pill--gray{ background:#f1f5f9; color:#475569; border-color:#e2e8f0; }
.adm-pill--amber{ background:#fef3c7; color:#92400e; border-color:#fde68a; }
.adm-pill--blue{ background:#dbeafe; color:#1e40af; border-color:#bfdbfe; }
.adm-pill--green{ background:#dcfce7; color:#166534; border-color:#bbf7d0; }
.adm-pill--red{ background:#fee2e2; color:#991b1b; border-color:#fecaca; }
.adm-pill--orange{ background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
</style>
