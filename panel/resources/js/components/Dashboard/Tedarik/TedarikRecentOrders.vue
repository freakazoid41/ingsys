<template>
  <div class="tdk-recent">
    <div class="tdk-recent__head">
      <h5 class="tdk-recent__title">Son Siparişler</h5>
      <router-link to="/tedarikpanel/orders" class="tdk-recent__link">Tümünü Gör →</router-link>
    </div>
    <div v-if="loading" class="tdk-recent__loading"><div class="tdk-recent__spinner"></div></div>
    <div v-else-if="!orders.length" class="tdk-recent__empty">Henüz sipariş bulunmuyor</div>
    <div v-else class="tdk-recent__scroll">
      <table class="tdk-recent__table">
        <thead><tr><th>Sipariş No</th><th>Firma</th><th>Tarih</th><th>Durum</th></tr></thead>
        <tbody>
          <tr v-for="row in orders" :key="row.id" class="tdk-recent__row" @click="goToOrder(row.id)">
            <td class="tdk-recent__cell--bold">{{ row._orderNo || '—' }}</td>
            <td>{{ row._ctitle || '—' }}</td>
            <td class="tdk-recent__cell--muted">{{ row._dateFmt }}</td>
            <td><span class="tdk-recent__pill" :class="row._statusCls">{{ row._statusLabel }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import Plib from '@/lib/pickle';

const SKEY = {
  'doc_trans_order_created': ['Yeni', 'tdk-pill--gray'],
  'doc_trans_order_transfer_sent': ['Dosya Bekleniyor', 'tdk-pill--amber'],
  'doc_trans_order_ready_for_shipment': ['Sevke Hazır', 'tdk-pill--blue'],
  'doc_trans_order_approved': ['Onaylandı', 'tdk-pill--green'],
  'doc_trans_order_rejected': ['Reddedildi', 'tdk-pill--red'],
  'doc_trans_order_files_rejected': ['Dosya Reddedildi', 'tdk-pill--orange'],
};

export default {
  name: 'TedarikRecentOrders',
  data() { return { loading: true, orders: [] }; },
  mounted() { this.load(); },
  methods: {
    async load() {
      try {
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/tedarik-orders', method: 'GET' }, null);
        if (!Array.isArray(rsp)) return;
        this.orders = rsp.map(r => {
          let orderNo = '', ctitle = '';
          try {
            JSON.parse(r.main_attr || '[]').forEach(a => {
              if (a.Key === 'order_no') orderNo = a.Value;
              if (a.Key === 'ctitle') ctitle = a.Value;
            });
          } catch {}
          const statusKey = (r.status || '').split('**')[0];
          const sl = SKEY[statusKey] || ['Bilinmiyor', 'tdk-pill--gray'];
          return {
            ...r,
            _orderNo: orderNo,
            _ctitle: ctitle,
            _dateFmt: r.created_at ? new Date(r.created_at).toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—',
            _statusLabel: sl[0],
            _statusCls: sl[1],
          };
        });
      } catch (e) { console.error('tedarik-orders failed', e); }
      finally { this.loading = false; }
    },
    goToOrder(id) { this.$router.push({ name: 'TedarikOrderForm', params: { id } }); }
  }
};
</script>

<style scoped>
.tdk-recent { background: #fff; border-radius: 14px; padding: 1.5rem; border: 1px solid #f1f5f9; }
.tdk-recent__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.tdk-recent__title { font-size: 0.95rem; font-weight: 700; color: #111827; margin: 0; }
.tdk-recent__link { font-size: 0.8rem; font-weight: 600; color: #FF5A1F; text-decoration: none; }
.tdk-recent__link:hover { color: #c2410c; }
.tdk-recent__loading { display: flex; justify-content: center; padding: 2rem; }
.tdk-recent__spinner { width: 28px; height: 28px; border: 3px solid #e5e7eb; border-top-color: #FF5A1F; border-radius: 50%; animation: tdk-spin 0.6s linear infinite; }
@keyframes tdk-spin { to { transform: rotate(360deg); } }
.tdk-recent__empty { text-align: center; padding: 2rem; color: #9ca3af; font-size: 0.9rem; }
.tdk-recent__scroll { max-height: 380px; overflow-y: auto; }
.tdk-recent__table { width: 100%; border-collapse: collapse; }
.tdk-recent__table th { text-align: left; font-size: 0.7rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; padding: 0 0 0.75rem; border-bottom: 1px solid #f1f5f9; position: sticky; top: 0; background: #fff; }
.tdk-recent__table td { padding: 0.7rem 0; font-size: 0.85rem; color: #374151; border-bottom: 1px solid #f9fafb; }
.tdk-recent__row { cursor: pointer; transition: background 0.15s; }
.tdk-recent__row:hover { background: #fff7ed; }
.tdk-recent__cell--bold { font-weight: 700; color: #111827; }
.tdk-recent__cell--muted { color: #9ca3af; font-size: 0.8rem; }
.tdk-recent__pill { display: inline-block; padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.72rem; font-weight: 600; white-space: nowrap; }
.tdk-pill--gray { background: #f3f4f6; color: #6b7280; }
.tdk-pill--amber { background: #fef3c7; color: #92400e; }
.tdk-pill--blue { background: #dbeafe; color: #1e40af; }
.tdk-pill--green { background: #dcfce7; color: #166534; }
.tdk-pill--red { background: #fee2e2; color: #991b1b; }
.tdk-pill--orange { background: #fff7ed; color: #9a3412; }
</style>
