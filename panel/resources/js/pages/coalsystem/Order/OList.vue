<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';

    export default {
        breadcrumbs: {
            list: [ { title: 'Siparişler', path: '/coalpanel/orders' } ],
            title: 'Sipariş Listesi'
        },
        setup() { return { useNavigationStore, useAuthStore, PickleTable, Plib, wTrans, Swal } },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTable();
            setTimeout(() => this.navigationStore.toggle(false), 300);
        },
        data() {
            return {
                plib : new Plib(),
                navigationStore : useNavigationStore(),
                authStore : useAuthStore(),
            }
        },
        methods: {
            searchTable(){
                this.table.setFilter([{ key:'all', type:'=', value: document.getElementById('mainSearch').value.trim() }]);
            },
            resetSearch(){
                document.getElementById('mainSearch').value = '';
                this.table.setFilter([]);
            },
            exportTable(){
                this.plib.openTab('POST', '/api/v1/export/orders', this.table.currentFilter,'_blank');
            },
            formatDate(val){
                if(!val) return '-';
                // try DD.MM.YYYY from entity (BEDAT) or ISO
                if(val.includes('.')) return val;
                if(val.includes('-')){
                    const parts = val.split(' ')[0].split('-');
                    if(parts.length===3) return `${parts[2]}.${parts[1]}.${parts[0]}`;
                }
                try { const d=new Date(val); if(!isNaN(d)) return d.toLocaleDateString('tr-TR'); } catch(e){}
                return val;
            },
            buildTable(){
                const headers = [
                    {
                        title: 'Sipariş No',
                        key: 'transfer_no',
                        order: true,
                        width: '140px',
                        type: 'string',
                        columnFormatter: (elm,row)=>{
                            const v = row.transfer_no || row.order_no || row.EBELN || row.id?.substring(0,12) || '-';
                            const span=document.createElement('span');
                            span.textContent=v;
                            span.style.fontWeight='700';
                            span.style.color='#0f172a';
                            span.title=v;
                            span.style.cursor='pointer';
                            span.onclick=()=> this.$router.push({name:'OrderForm', params:{id:row.id}});
                            return span;
                        }
                    },
                    {
                        title: 'Tedarikçi',
                        key: 'ctitle',
                        order: true,
                        type: 'string',
                        columnFormatter: (elm,row)=>{
                            const v = row.ctitle || row.MCOD1 || row.spec_code || row.clititle || '-';
                            const span=document.createElement('span');
                            span.textContent=v.length>20? v.substring(0,20)+'…':v;
                            span.title=v;
                            span.style.color='#334155';
                            return span;
                        }
                    },
                    {
                        title: 'Alım No',
                        key: 'buying_no',
                        order: true,
                        width: '130px',
                        type: 'string',
                        columnFormatter: (elm,row)=>{
                            const v = row.buying_no || row.SUBMI || '-';
                            const s=document.createElement('span'); s.textContent=v; s.style.color='#334155'; return s;
                        }
                    },
                    {
                        title: 'Sipariş Tarih',
                        key: 'siparis_tarih',
                        order: true,
                        width: '120px',
                        type: 'string',
                        columnFormatter: (elm,row)=>{
                            // BEDAT or created_at entity
                            const raw = row.BEDAT || row.created_at || row.siparis_tarih || row.date || '';
                            const s=document.createElement('span'); s.textContent=this.formatDate(raw); s.style.color='#334155'; return s;
                        }
                    },
                    {
                        title: 'Ekleme Tarih',
                        key: 'ekleme_tarih',
                        order: true,
                        width: '120px',
                        type: 'string',
                        columnFormatter: (elm,row)=>{
                            const raw = row.ekleme_tarih || row.inserted_at || row.created_at || '';
                            // document created_at is also in row.created_at but overwritten by entity; try to use raw document time if entity missing
                            const s=document.createElement('span'); s.textContent=this.formatDate(raw); s.style.color='#334155'; return s;
                        }
                    },
                    {
                        title: 'Durum',
                        key: 'status',
                        order: true,
                        width: '190px',
                        type: 'string',
                        columnFormatter: (elm,row)=>{
                            const parts = String(row.status||'').split('**');
                            const opKey = parts[0]||'';
                            const label = parts[1]|| parts[0] || 'Beklemede';
                            const btn=document.createElement('span');
                            btn.textContent=label;
                            btn.style.display='inline-flex';
                            btn.style.alignItems='center';
                            btn.style.justifyContent='center';
                            btn.style.padding='6px 14px';
                            btn.style.borderRadius='8px';
                            btn.style.fontSize='0.82rem';
                            btn.style.fontWeight='700';
                            btn.style.whiteSpace='nowrap';
                            btn.style.cursor='pointer';
                            btn.style.border='1px solid transparent';
                            // screenshot: green Kalite Onayı Verildi, orange Dosyalar Kontrol Ediliyor
                            if(label.includes('Kalite Onayı') || opKey==='doc_trans_transfer_approved' || opKey==='doc_trans_order_approved'){
                                btn.style.background='#22c55e';
                                btn.style.color='#fff';
                                btn.style.borderColor='#16a34a';
                            } else if(label.includes('Kontrol Ediliyor') || opKey==='doc_trans_transfer_sent' || opKey==='doc_trans_order_transfer_sent' || opKey==='doc_file_waiting'){
                                btn.style.background='#f97316';
                                btn.style.color='#fff';
                                btn.style.borderColor='#ea580c';
                            } else if(opKey.includes('rejected')){
                                btn.style.background='#ef4444'; btn.style.color='#fff';
                            } else {
                                btn.style.background='#e2e8f0'; btn.style.color='#475569'; btn.style.borderColor='#cbd5e1';
                            }
                            btn.title='Durum değiştir';
                            btn.onclick=()=>{
                                Swal.fire({
                                    title:'Durum Değiştir',
                                    showConfirmButton:false, showCloseButton:true,
                                    html:`<div class="d-flex flex-column gap-2 p-2">
                                        <button class="btn btn-success doc-status" data-key="doc_trans_transfer_approved" style="background:#22c55e;border:none;">Kalite Onayı Verildi</button>
                                        <button class="btn doc-status" data-key="doc_trans_transfer_sent" style="background:#f97316;color:#fff;border:none;">Dosyalar Kontrol Ediliyor</button>
                                        <button class="btn btn-danger doc-status" data-key="doc_trans_transfer_rejected">Reddedildi</button>
                                    </div>`,
                                    willOpen:()=>{
                                        document.querySelectorAll('.doc-status').forEach(b=>b.addEventListener('click', async e=>{
                                            const fd=new FormData(); fd.append('id',row.id); fd.append('op_key',e.target.dataset.key); fd.append('note','Durum güncellendi');
                                            const rsp=await this.plib.request({url:'/api/v1/trans/set-status', method:'POST'}, null, fd);
                                            if(rsp.success){
                                                const newLabel = e.target.textContent.trim();
                                                this.table.updateRow(row.id,{status:e.target.dataset.key+'**'+newLabel});
                                                this.plib.toast(this.Swal,'success','Durum güncellendi');
                                            } else Swal.showValidationMessage(rsp.msg||'Hata');
                                        }))
                                    }
                                })
                            };
                            return btn;
                        }
                    },
                    {
                        title: '',
                        key: 'id',
                        order:false,
                        width:'190px',
                        type:'string',
                        columnFormatter:(elm,row)=>{
                            const wrap=document.createElement('div');
                            wrap.style.display='flex';
                            wrap.style.justifyContent='flex-end';
                            wrap.style.gap='6px';
                            const btn=document.createElement('button');
                            btn.textContent='Detay';
                            btn.className='btn';
                            btn.style.background='#e5e7eb';
                            btn.style.color='#374151';
                            btn.style.border='1px solid #d1d5db';
                            btn.style.borderRadius='8px';
                            btn.style.padding='6px 14px';
                            btn.style.fontSize='0.82rem';
                            btn.style.fontWeight='600';
                            btn.style.cursor='pointer';
                            btn.onmouseenter=()=> btn.style.background='#d1d5db';
                            btn.onmouseleave=()=> btn.style.background='#e5e7eb';
                            btn.onclick=()=>{
                                // simple action: go to detail
                                this.$router.push({name:'OrderForm', params:{id:row.id}});
                            };
                            wrap.appendChild(btn);
                            const cancelBtn=document.createElement('button');
                            cancelBtn.textContent='İptal Et';
                            cancelBtn.className='btn';
                            cancelBtn.style.background='#fef2f2';
                            cancelBtn.style.color='#dc2626';
                            cancelBtn.style.border='1px solid #fecaca';
                            cancelBtn.style.borderRadius='8px';
                            cancelBtn.style.padding='6px 14px';
                            cancelBtn.style.fontSize='0.82rem';
                            cancelBtn.style.fontWeight='600';
                            cancelBtn.style.cursor='pointer';
                            cancelBtn.onmouseenter=()=> cancelBtn.style.background='#fee2e2';
                            cancelBtn.onmouseleave=()=> cancelBtn.style.background='#fef2f2';
                            cancelBtn.onclick=()=> this.cancelOrder(row.id);
                            wrap.appendChild(cancelBtn);
                            return wrap;
                        }
                    }
                ];
                this.table = new PickleTable({
                    container:'#div_table', headers, pageLimit:10, height:'70vh', type:'ajax', columnSearch:false, paginationType:'number',
                    ajax:{ url:'/api/v1/table/documents', data:{} },
                    // Orders ARE transfers when partially sent: EBELN and EBELN-X are both op-doc-order.
                    // Screenshot shows EBELN-X rows which are cloned orders (partial shipment).
                    initialFilter:[
                        { key:'form-type', type:'=', value:'op-doc-order-form' },
                        { key:'type', type:'=', value:'op-doc-order' }
                    ],
                    nextPageIcon:'<i class="ki-outline ki-arrow-right"></i>', prevPageIcon:'<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter:(elm,data)=>{
                        try{
                            const attrs = JSON.parse(data.main_attr||'[]');
                            attrs.forEach(el=>{ data[el['Key']]=el['Value']; });
                        }catch(e){}
                        // fallbacks for display
                        data.transfer_no = data['transfer_no'] || data['order_no'] || data['EBELN'] || data['transfer_no'] || '-';
                        data.ctitle = data['ctitle'] || data['MCOD1'] || data['clititle'] || data['spec_code'] || '-';
                        data.buying_no = data['buying_no'] || data['SUBMI'] || '-';
                        // Sipariş Tarih: BEDAT or transfer creation entity
                        data.siparis_tarih = data['BEDAT'] || data['created_at'] || data['siparis_tarih'] || '';
                        data.ekleme_tarih = data['ekleme_tarih'] || data['inserted_at'] || '';
                        // if still empty, use document created_at (ISO) which is in data.created_at column (overwritten? keep original via _created_at)
                        if(!data.ekleme_tarih && data.created_at) data.ekleme_tarih = data.created_at;
                        return data;
                    },
                });
            },
            async cancelOrder(orderQnid){
                const conf = await this.Swal.fire({
                    title:'Siparişi İptal Et',
                    text:'Sipariş tamamen reddedilecek ve iptal edilecek. Emin misiniz?',
                    icon:'warning', showCancelButton:true, confirmButtonText:'Evet, İptal Et', cancelButtonText:'Vazgeç',
                });
                if(!conf.isConfirmed) return;
                const fd=new FormData(); fd.append('id',orderQnid); fd.append('note','Sipariş listesinden reddedildi ve iptal edildi');
                const rsp=await this.plib.request({url:'/api/v1/orders/cancel', method:'POST'}, null, fd);
                this.plib.toast(this.Swal, rsp.success?'success':'error', rsp.msg||'İşlem Tamamlandı',()=>{
                    if(rsp.success) window.location.reload();
                });
            }
        }
    }
</script>
<template>
    <div class="card" style="border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        <div class="card-header" style="background:#fff;border-bottom:1px solid #e2e8f0;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <h3 class="card-title" style="font-size:1.1rem;font-weight:800;color:#0f172a;margin:0;">Sipariş Listesi</h3>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <div class="position-relative">
                    <i class="ki-outline ki-magnifier position-absolute top-50 start-0 ms-3 translate-middle-y text-muted"></i>
                    <input id="mainSearch" class="form-control ps-10" placeholder="Sipariş ara..." style="height:38px;width:260px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                </div>
                <button class="btn btn-primary" @click="searchTable" style="height:38px;border-radius:8px;background:#154b91;border:none;">Ara</button>
                <button class="btn btn-light" @click="resetSearch" style="height:38px;border-radius:8px;">Sıfırla</button>
                <button class="btn btn-light" @click="exportTable" style="height:38px;border-radius:8px;"><i class="ki-outline ki-exit-down"></i> Excel</button>
                <button class="btn btn-light" style="height:38px;border-radius:8px;"><i class="ki-outline ki-filter"></i> Filtreler</button>
                <button class="btn btn-light" style="height:38px;border-radius:8px;"><i class="ki-outline ki-setting-2"></i> Detaylı Filtre</button>
            </div>
        </div>
        <div class="card-body p-0" style="background:#f8fafc;">
            <div id="div_table"></div>
        </div>
    </div>
</template>
<style scoped>
:deep(.pickletable table){
    border-collapse:separate !important;
    border-spacing:0 8px !important;
    background:#f8fafc !important;
    padding: 8px 12px !important;
}
:deep(.pickletable thead th){
    background:#f8fafc !important;
    color:#64748b !important;
    font-size:0.78rem !important;
    font-weight:600 !important;
    text-transform:none !important;
    border:none !important;
    padding:10px 12px !important;
}
:deep(.pickletable tbody tr){
    background:#fff !important;
    border:1px solid #e2e8f0 !important;
    border-radius:10px !important;
    box-shadow:0 1px 2px rgba(0,0,0,0.04) !important;
    overflow:hidden;
}
:deep(.pickletable tbody tr:hover){
    background:#fff !important;
    box-shadow:0 2px 6px rgba(0,0,0,0.08) !important;
}
:deep(.pickletable tbody td){
    border:none !important;
    padding:14px 12px !important;
    font-size:0.88rem !important;
    vertical-align:middle !important;
    border-top:1px solid #e2e8f0 !important;
    border-bottom:1px solid #e2e8f0 !important;
}
:deep(.pickletable tbody td:first-child){
    border-left:1px solid #e2e8f0 !important;
    border-top-left-radius:10px !important;
    border-bottom-left-radius:10px !important;
}
:deep(.pickletable tbody td:last-child){
    border-right:1px solid #e2e8f0 !important;
    border-top-right-radius:10px !important;
    border-bottom-right-radius:10px !important;
}
:deep(.pickletable .divPagination){
    background:#fff !important;
    border-top:1px solid #e2e8f0 !important;
    padding:12px !important;
}
</style>
