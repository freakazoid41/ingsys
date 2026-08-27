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
                             if(opKey==='doc_trans_order_ready_for_shipment' || label.includes('Sipariş Sevke Hazır')){
                                 btn.style.background='#facc15';
                                 btn.style.color='#713f12';
                                 btn.style.borderColor='#eab308';
                             } else if(label.includes('Kalite Onayı') || opKey==='doc_trans_transfer_approved' || opKey==='doc_trans_order_approved'){
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
                             const statusIcons={
                                 doc_trans_order_created:'fa-file-circle-plus',
                                 doc_trans_order_transfer_sent:'fa-magnifying-glass',
                                 doc_trans_order_ready_for_shipment:'fa-truck-fast',
                                 doc_trans_order_approved:'fa-circle-check',
                                 doc_trans_order_rejected:'fa-circle-xmark',
                                 doc_trans_order_files_rejected:'fa-file-circle-xmark',
                                 doc_file_waiting:'fa-hourglass-half',
                             };
                             const icon=document.createElement('i');
                             icon.className=`fa-solid ${statusIcons[opKey]||''}`;
                             icon.style.marginRight='7px';
                             if(statusIcons[opKey]) btn.prepend(icon);
                             btn.title='Durum değiştir';
                            btn.onclick=()=>{
                                Swal.fire({
                                    title:'Durum Değiştir',
                                     showConfirmButton:false, showCloseButton:true,
                                     html:`<div class="d-flex flex-column gap-2 p-2">
                                         <button class="btn btn-success doc-status" data-key="doc_trans_transfer_approved" style="background:#22c55e;border:none;"><i class="fa-solid fa-circle-check me-2"></i>Kalite Onayı Verildi</button>
                                         <button class="btn doc-status" data-key="doc_trans_order_ready_for_shipment" style="background:#facc15;color:#713f12;border:none;"><i class="fa-solid fa-truck-fast me-2"></i>Sipariş Sevke Hazır</button>
                                         <button class="btn doc-status" data-key="doc_trans_transfer_sent" style="background:#f97316;color:#fff;border:none;"><i class="fa-solid fa-magnifying-glass me-2"></i>Dosyalar Kontrol Ediliyor</button>
                                         <button class="btn btn-danger doc-status" data-key="doc_trans_transfer_rejected"><i class="fa-solid fa-circle-xmark me-2"></i>Reddedildi</button>
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
                        // fallbacks for display — order_no is always the correct display number
                        // (main order = base EBELN, clone = EBELN-X). transfer_no on main order
                        // is metadata set by recordPartiallySent and should NOT be displayed.
                        data.transfer_no = data['order_no'] || data['EBELN'] || data['transfer_no'] || '-';
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
    <div class="order-list-card">
        <div class="order-list-header">
            <div class="order-list-header-left">
                <div class="order-list-icon">
                    <i class="ki-outline ki-document"></i>
                </div>
                <h3 class="order-list-title">Sipariş Listesi</h3>
            </div>
            <div class="order-list-actions">
                <div class="order-search-wrap">
                    <i class="ki-outline ki-magnifier order-search-icon"></i>
                    <input id="mainSearch" class="order-search-input" placeholder="Sipariş ara..." @keyup.enter="searchTable">
                </div>
                <button class="order-btn order-btn-primary" @click="searchTable">Ara</button>
                <button class="order-btn order-btn-ghost" @click="resetSearch">Sıfırla</button>
                <button class="order-btn order-btn-ghost" @click="exportTable"><i class="ki-outline ki-exit-down"></i> Excel</button>
            </div>
        </div>
        <div class="order-list-body">
            <div id="div_table"></div>
        </div>
    </div>
</template>
<style scoped>
.order-list-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02);
}
.order-list-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.order-list-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.order-list-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3b82f6;
    font-size: 17px;
}
.order-list-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.01em;
}
.order-list-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.order-search-wrap {
    position: relative;
}
.order-search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 15px;
    pointer-events: none;
}
.order-search-input {
    height: 38px;
    width: 240px;
    padding: 0 12px 0 36px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.84rem;
    color: #0f172a;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.order-search-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
.order-search-input::placeholder { color: #94a3b8; }
.order-btn {
    height: 38px;
    padding: 0 16px;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
    border: none;
    white-space: nowrap;
}
.order-btn-primary {
    background: #3b82f6;
    color: #fff;
}
.order-btn-primary:hover { background: #2563eb; box-shadow: 0 2px 6px rgba(59,130,246,0.3); }
.order-btn-ghost {
    background: #fff;
    color: #475569;
    border: 1px solid #e2e8f0;
}
.order-btn-ghost:hover { background: #f8fafc; border-color: #cbd5e1; }
.order-list-body {
    background: #fff;
    min-height: 120px;
}
:deep(.pickletable table){
    border-collapse: separate !important;
    border-spacing: 0 !important;
    width: 100%;
}
:deep(.pickletable thead th){
    background: #f8fafc !important;
    color: #64748b !important;
    font-size: 0.72rem !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.04em !important;
    padding: 11px 16px !important;
    border-bottom: 1px solid #e2e8f0 !important;
    border-top: none !important;
}
:deep(.pickletable tbody tr){
    background: #fff !important;
    transition: all 0.15s ease !important;
}
:deep(.pickletable tbody tr:hover){
    background: #f8fafc !important;
}
:deep(.pickletable tbody td){
    padding: 13px 16px !important;
    font-size: 0.86rem !important;
    border-bottom: 1px solid #f1f5f9 !important;
    border-top: none !important;
    border-left: none !important;
    border-right: none !important;
    vertical-align: middle !important;
}
:deep(.pickletable tbody tr:last-child td){
    border-bottom: none !important;
}
:deep(.pickletable .divPagination){
    background: #fff !important;
    border-top: 1px solid #f1f5f9 !important;
    padding: 10px 16px !important;
}
</style>
