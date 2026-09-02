<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import Swal from 'sweetalert2';

    export default {
        breadcrumbs: {
            list: [ { title: 'Siparişler', path: '/coalpanel/orders' } ],
            title: 'Sipariş Listesi'
        },
        setup() { return { useNavigationStore, useAuthStore, Swal } },
        computed: {
            isTedarik() { return this.$route.path.startsWith('/tedarikpanel'); }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTable();
            setTimeout(() => this.navigationStore.toggle(false), 300);
            if(this.isTedarik){
                this.fetchClients();
                this.fetchTedarikciler();
            }
        },
        data() {
            return {
                plib : new Plib(),
                navigationStore : useNavigationStore(),
                authStore : useAuthStore(),
                showDetailed: false,
                detay: {
                    stokKodu: '',
                    siparisKodu: '',
                    alimKodu: '',
                    seriNo: '',
                    uretimTarihi: '',
                    tedarikci: '',
                    sirket: '',
                    onayDurumu: '',
                    tarihAraligi: '',
                },
                clientOptions: [],
                tedarikciOptions: [],
            }
        },
        methods: {
            toggleDetailed(){ this.showDetailed = !this.showDetailed; },
            async fetchClients(){
                try{
                    const fd=new FormData();
                    fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-client-form'},{key:'type',type:'=',value:'op-doc-client'}], scale:{page:1, limit:100}, order:{key:'id', style:'asc'}}));
                    const rsp=await this.plib.request({url:'/api/v1/table/documents', method:'POST'}, null, fd);
                    const rows=rsp?.data?.data || rsp?.data || [];
                    const list=Array.isArray(rows)?rows:(rows?.data||[]);
                    const opts=[];
                    list.forEach(r=>{
                        try{
                            const attrs=JSON.parse(r.main_attr||'[]');
                            const lifnr=(attrs.find(a=>a.Key==='lifnr')||{}).Value||'';
                            const title=(attrs.find(a=>a.Key==='title')||{}).Value||'';
                            const label = title ? `${title} (${lifnr})` : lifnr;
                            if(lifnr) opts.push({value: lifnr, label});
                        }catch(e){}
                    });
                    this.clientOptions=opts;
                    // Tedarikçi Ara same source but may be filtered by users containing client code — fallback to same list
                    if(!this.tedarikciOptions.length) this.tedarikciOptions=opts.slice();
                }catch(e){ console.error('fetchClients',e); }
            },
            async fetchTedarikciler(){
                try{
                    // Tedarikçi Ara: users that contain a client code (userclientgroup)
                    // Try to fetch via persons, fallback to clients if empty
                    const fd=new FormData();
                    fd.append('tableReq', JSON.stringify({filter:[], scale:{page:1, limit:100}, order:{key:'id', style:'asc'}}));
                    const rsp=await this.plib.request({url:'/api/v1/table/persons', method:'POST'}, null, fd);
                    const rows=rsp?.data?.data || rsp?.data || [];
                    const list=Array.isArray(rows)?rows:(rows?.data||[]);
                    // Persons tableList doesn't expose client code, so we will not have lifnr here.
                    // Keep fallback: if we got persons, map to username, but filter will still use lifnr from client list.
                    // For now, if persons empty, keep clientOptions as tedarikci source.
                    if(list.length){ /* could enhance later to fetch per-person client */ }
                }catch(e){ /* silent fallback to clientOptions */ }
            },
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
            applyDetailedFilter(){
                const f=[];
                const v=this.detay;
                if(v.stokKodu.trim()) f.push({key:'stok_kodu', type:'like', value: v.stokKodu.trim()});
                if(v.siparisKodu.trim()) f.push({key:'siparis_kodu', type:'like', value: v.siparisKodu.trim()});
                if(v.alimKodu.trim()) f.push({key:'alim_kodu', type:'like', value: v.alimKodu.trim()});
                if(v.seriNo.trim()) f.push({key:'seri_no', type:'like', value: v.seriNo.trim()});
                if(v.uretimTarihi.trim()) f.push({key:'uretim_tarihi', type:'like', value: v.uretimTarihi.trim()});
                if(v.tedarikci) f.push({key:'tedarikci', type:'like', value: v.tedarikci});
                if(v.sirket) f.push({key:'sirket', type:'like', value: v.sirket});
                if(v.onayDurumu) f.push({key:'transactions', type:'=', value: v.onayDurumu});
                if(v.tarihAraligi.trim()) f.push({key:'tarih_araligi', type:'like', value: v.tarihAraligi.trim()});
                this.table.setFilter(f);
            },
            resetDetailedFilter(){
                this.detay={ stokKodu:'', siparisKodu:'', alimKodu:'', seriNo:'', uretimTarihi:'', tedarikci:'', sirket:'', onayDurumu:'', tarihAraligi:'' };
                this.table.setFilter([]);
            },
            exportDetailed(){
                if(!this.table || !this.table.currentFilter) return this.exportTable();
                // If no detailed filter active, allow export of full list as per note
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
                const _isTedarik = this.isTedarik;
                const headers = [
                    {
                        title: 'Sipariş No',
                        key: 'transfer_no',
                        order: true,
                        width: _isTedarik ? '150px' : '180px',
                        type: 'string',
                        columnFormatter: (elm,row)=>{
                            const v = row.transfer_no || row.order_no || row.EBELN || row.id?.substring(0,12) || '-';
                            const isClone = /\-\d+$/.test(v);
                            const baseNo = isClone ? v.replace(/\-\d+$/, '') : null;

                            const wrap = document.createElement('div');
                            wrap.style.display = 'flex';
                            wrap.style.flexDirection = 'column';
                            wrap.style.gap = '2px';

                            const span = document.createElement('span');
                            span.textContent = v;
                            span.style.fontWeight = '700';
                            span.style.color = '#0f172a';
                            span.style.fontSize = _isTedarik ? '13px' : '14px';
                            span.title = v;
                            span.style.cursor = 'pointer';
                            span.onclick = () => {
                                const rName = _isTedarik ? 'TedarikOrderForm' : 'OrderForm';
                                this.$router.push({name:rName, params:{id:row.id}});
                            };

                            if(isClone && baseNo){
                                const link = document.createElement('a');
                                link.textContent = baseNo + "'den ayrıldı";
                                link.style.fontSize = '0.70rem';
                                link.style.color = '#3b82f6';
                                link.style.textDecoration = 'none';
                                link.style.fontWeight = '500';
                                link.style.cursor = 'pointer';
                                link.style.display = 'inline-flex';
                                link.style.alignItems = 'center';
                                link.style.gap = '3px';
                                const icon = document.createElement('i');
                                icon.className = 'ki-outline ki-arrow-top-right';
                                icon.style.fontSize = '10px';
                                link.prepend(icon);
                                link.onmouseenter = () => link.style.textDecoration = 'underline';
                                link.onmouseleave = () => link.style.textDecoration = 'none';
                                link.onclick = (e) => {
                                    e.stopPropagation();
                                    this.findAndNavigateToOrder(baseNo);
                                };
                                wrap.appendChild(span);
                                wrap.appendChild(link);
                            } else {
                                wrap.appendChild(span);
                            }
                            return wrap;
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
                            span.textContent=v;
                            span.title=v;
                            span.style.color='#334155';
                            span.style.display='inline-block';
                            span.style.maxWidth='180px';
                            span.style.overflow='hidden';
                            span.style.textOverflow='ellipsis';
                            span.style.whiteSpace='nowrap';
                            span.style.verticalAlign='middle';
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
                        width: _isTedarik ? '175px' : '220px',
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
                            btn.style.padding=_isTedarik ? '5px 10px' : '6px 12px';
                            btn.style.borderRadius=_isTedarik ? '6px' : '8px';
                            btn.style.fontSize=_isTedarik ? '11.5px' : '0.82rem';
                            btn.style.fontWeight='600';
                            btn.style.whiteSpace='nowrap';
                            btn.style.cursor='pointer';
                            btn.style.border='1px solid transparent';
                            btn.style.gap='6px';
                            if(_isTedarik){
                                // vivid solid pills as in screenshot
                                if(label.includes('Kalite Onayı') || opKey==='doc_trans_order_approved'){
                                    btn.style.background='#22c55e';
                                    btn.style.color='#ffffff';
                                    btn.style.borderColor='#22c55e';
                                } else if(opKey.includes('rejected')){
                                    btn.style.background='#ef4444';
                                    btn.style.color='#ffffff';
                                    btn.style.borderColor='#ef4444';
                                } else {
                                    // Beklemede + Dosyalar Kontrol Ediliyor = orange solid
                                    btn.style.background='#FF5A1F';
                                    btn.style.color='#ffffff';
                                    btn.style.borderColor='#FF5A1F';
                                }
                            } else {
                                if(opKey==='doc_trans_order_ready_for_shipment' || label.includes('Sipariş Sevke Hazır')){
                                    btn.style.background='#fef3c7';
                                    btn.style.color='#92400e';
                                    btn.style.borderColor='#fcd34d';
                                } else if(label.includes('Kalite Onayı') || opKey==='doc_trans_order_approved'){
                                    btn.style.background='#dcfce7';
                                    btn.style.color='#166534';
                                    btn.style.borderColor='#86efac';
                                } else if(label.includes('Kontrol Ediliyor') || opKey==='doc_trans_order_transfer_sent' || opKey==='doc_file_waiting'){
                                    btn.style.background='#ffedd5';
                                    btn.style.color='#9a3412';
                                    btn.style.borderColor='#fdba74';
                                } else if(opKey.includes('rejected')){
                                    btn.style.background='#fee2e2';
                                    btn.style.color='#991b1b';
                                    btn.style.borderColor='#fca5a5';
                                } else {
                                    btn.style.background='#f1f5f9';
                                    btn.style.color='#475569';
                                    btn.style.borderColor='#e2e8f0';
                                }
                            }
                            const statusIcons={
                                doc_trans_order_created:'ki-outline ki-file-added',
                                doc_trans_order_transfer_sent:'ki-outline ki-magnifier',
                                doc_trans_order_ready_for_shipment:'ki-outline ki-truck',
                                doc_trans_order_approved:'ki-outline ki-check-circle',
                                doc_trans_order_rejected:'ki-outline ki-cross-circle',
                                doc_trans_order_files_rejected:'ki-outline ki-file-danger',
                                doc_file_waiting:'ki-outline ki-hourglass',
                            };
                            const icon=document.createElement('i');
                            icon.className=statusIcons[opKey]||'';
                            icon.style.fontSize='14px';
                            if(statusIcons[opKey]) btn.prepend(icon);
                            btn.title='Durum değiştir';
                            btn.onclick=()=>{
                                Swal.fire({
                                    title:'Durum Değiştir',
                                    showConfirmButton:false, showCloseButton:true,
                                    html:`<div class="d-flex flex-column gap-2 p-2">
                                        <button class="btn btn-success doc-status" data-key="doc_trans_order_approved" style="background:#22c55e;border:none;"><i class="ki-outline ki-check-circle me-2"></i>Kalite Onayı Verildi</button>
                                        <button class="btn doc-status" data-key="doc_trans_order_ready_for_shipment" style="background:#facc15;color:#713f12;border:none;"><i class="ki-outline ki-truck me-2"></i>Sipariş Sevke Hazır</button>
                                        <button class="btn doc-status" data-key="doc_trans_order_transfer_sent" style="background:#f97316;color:#fff;border:none;"><i class="ki-outline ki-magnifier me-2"></i>Dosyalar Kontrol Ediliyor</button>
                                        <button class="btn btn-danger doc-status" data-key="doc_trans_order_rejected"><i class="ki-outline ki-cross-circle me-2"></i>Reddedildi</button>
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
                        width: _isTedarik ? '210px' : '190px',
                        type:'string',
                        columnFormatter:(elm,row)=>{
                            const wrap=document.createElement('div');
                            wrap.style.display='flex';
                            wrap.style.justifyContent='flex-end';
                            wrap.style.gap='6px';
                            if(_isTedarik){
                                const aks=document.createElement('button');
                                aks.textContent='Aksiyonlar';
                                aks.className='btn';
                                aks.style.background='#8e8e93';
                                aks.style.color='#ffffff';
                                aks.style.border='1px solid #8e8e93';
                                aks.style.borderRadius='8px';
                                aks.style.padding='6px 14px';
                                aks.style.fontSize='0.78rem';
                                aks.style.fontWeight='600';
                                aks.style.cursor='pointer';
                                aks.onmouseenter=()=> aks.style.background='#7f7f84';
                                aks.onmouseleave=()=> aks.style.background='#8e8e93';
                                aks.onclick=(e)=>{
                                    e.stopPropagation();
                                    Swal.fire({
                                        title:'Aksiyonlar',
                                        showConfirmButton:false, showCloseButton:true,
                                        html:`<div class="d-flex flex-column gap-2 p-2">
                                            <button class="btn" style="background:#0e8ea4;color:#fff;border:none;" onclick="location.href='#detail'">Detayları Gör</button>
                                            <button class="btn" style="background:#8e8e93;color:#fff;border:none;">Excel Çıktı</button>
                                        </div>`,
                                    });
                                };
                                wrap.appendChild(aks);
                                const det=document.createElement('button');
                                det.textContent='Detaylar';
                                det.className='btn';
                                det.style.background='#0e8ea4';
                                det.style.color='#ffffff';
                                det.style.border='1px solid #0e8ea4';
                                det.style.borderRadius='8px';
                                det.style.padding='6px 14px';
                                det.style.fontSize='0.78rem';
                                det.style.fontWeight='600';
                                det.style.cursor='pointer';
                                det.onmouseenter=()=> det.style.background='#0c7e90';
                                det.onmouseleave=()=> det.style.background='#0e8ea4';
                                det.onclick=()=>{
                                    this.$router.push({name:'TedarikOrderForm', params:{id:row.id}});
                                };
                                wrap.appendChild(det);
                                return wrap;
                            }
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
                            const vClone = row.transfer_no || row.order_no || '';
                            const isCloneRow = /\-\d+$/.test(vClone);
                            cancelBtn.textContent= isCloneRow ? 'Parçayı Sil' : 'İptal Et';
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
                            cancelBtn.onclick=()=> this.cancelOrder(row.id, isCloneRow);
                            wrap.appendChild(cancelBtn);
                            return wrap;
                        }
                    }
                ];
                this.table = new PickleTable({
                    container:'#div_table', headers, pageLimit:10, height: _isTedarik ? 'auto' : '70vh', type:'ajax', columnSearch:false, paginationType:'number',
                    ajax:{ url:'/api/v1/table/documents', data:{} },
                    // Orders ARE transfers when partially sent: EBELN and EBELN-X are both op-doc-order.
                    // Screenshot shows EBELN-X rows which are cloned orders (partial shipment).
                    initialFilter:[
                        { key:'form-type', type:'=', value:'op-doc-order-form' },
                        { key:'type', type:'=', value:'op-doc-order' }
                    ],
                    nextPageIcon:'<i class="ki-outline ki-arrow-right"></i>', prevPageIcon:'<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter:(elm,data)=>{
                        // Parse main_attr entities once per row (guard against re-parse)
                        if(!data._attrsParsed){
                            try{
                                const attrs = JSON.parse(data.main_attr||'[]');
                                attrs.forEach(el=>{ data[el['Key']]=el['Value']; });
                            }catch(e){}
                            data._attrsParsed = true;
                        }
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
            async cancelOrder(orderQnid, isPartial=false){
                const conf = await this.Swal.fire({
                    title: isPartial ? 'Parçayı Sil' : 'Siparişi İptal Et',
                    text: isPartial ? 'Bu parça (EBELN-X) silinecek ve miktarları ana siparişe geri eklenecek. Emin misiniz?' : 'Sipariş tamamen reddedilecek ve iptal edilecek. Emin misiniz?',
                    icon:'warning', showCancelButton:true, confirmButtonText: isPartial ? 'Evet, Sil' : 'Evet, İptal Et', cancelButtonText:'Vazgeç',
                });
                if(!conf.isConfirmed) return;
                const fd=new FormData(); fd.append('id',orderQnid); fd.append('note', isPartial ? 'Parça silindi, miktarlar ana siparişe iade edildi' : 'Sipariş listesinden reddedildi ve iptal edildi');
                const rsp=await this.plib.request({url:'/api/v1/orders/cancel', method:'POST'}, null, fd);
                if(rsp.success){
                    try { this.table.deleteRow(orderQnid); } catch(e) { try { this.table.removeRow(orderQnid); } catch(e2) { console.error('deleteRow failed', e, e2); } }
                    this.plib.toast(this.Swal,'success', rsp.msg||'İşlem Tamamlandı');
                } else {
                    this.plib.toast(this.Swal,'error', rsp.msg||'İşlem başarısız');
                }
            },
            async findAndNavigateToOrder(baseNo){
                try{
                    const fd = new FormData();
                    fd.append('tableReq', JSON.stringify({
                        filter: [
                            { key:'form-type', type:'=', value:'op-doc-order-form' },
                            { key:'type', type:'=', value:'op-doc-order' },
                            { key:'all', type:'=', value: baseNo }
                        ],
                        scale: { page: 1, limit: 5 },
                        order: { key: 'id', style: 'asc' }
                    }));
                    const rsp = await this.plib.request({url:'/api/v1/table/documents', method:'POST'}, null, fd);
                    const rows = rsp?.data?.data || rsp?.data || [];
                    const list = Array.isArray(rows) ? rows : (rows?.data || []);
                    const match = list.find(r => {
                        try {
                            const attrs = JSON.parse(r.main_attr || '[]');
                            const orderNo = attrs.find(a => a.Key === 'order_no');
                            return orderNo && orderNo.Value === baseNo;
                        } catch(e) { return false; }
                    });
                    if(match){
                        this.$router.push({name:'OrderForm', params:{id: match.id}});
                    } else {
                        this.plib.toast(this.Swal, 'info', 'Orijinal sipariş bulunamadı: ' + baseNo);
                    }
                } catch(e) {
                    console.error('findAndNavigateToOrder failed', e);
                }
            }
        }
    }
</script>
<template>
    <div :class="['order-list-card', { 'tedarik-card': isTedarik }]">
        <!-- Admin header -->
        <div v-if="!isTedarik" class="order-list-header">
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
        <!-- Tedarik header 1:1 from screenshot -->
        <div v-else class="tedarik-list-top">
            <div class="tedarik-list-title">
                <span>Sipariş Listesi</span>
                <i class="ki-outline ki-video tedarik-title-icon"></i>
            </div>
            <div class="tedarik-filters">
                <a href="javascript:;" class="tedarik-filter" @click="toggleDetailed"><i class="ki-outline ki-filter"></i> Detaylı Filtre</a>
                <a href="javascript:;" class="tedarik-filter" @click="toggleDetailed"><i class="ki-outline ki-filter"></i> Filtreler</a>
                <a href="javascript:;" class="tedarik-filter" @click="exportTable"><i class="ki-outline ki-exit-down"></i> Excel Çıktı</a>
            </div>
        </div>
        <!-- Detailed search panel — tedarik only, toggles via Detaylı Filtre -->
        <div v-if="isTedarik && showDetailed" class="tedarik-detailed-panel">
            <div class="tedarik-detailed-grid">
                <input v-model="detay.stokKodu" class="tedarik-detailed-input" placeholder="Stok Kodu Giriniz" />
                <input v-model="detay.siparisKodu" class="tedarik-detailed-input" placeholder="Sipariş Kodu Giriniz" />
                <input v-model="detay.alimKodu" class="tedarik-detailed-input" placeholder="Alım Kodu Giriniz" />
                <input v-model="detay.seriNo" class="tedarik-detailed-input" placeholder="Seri No Giriniz" />
                <input v-model="detay.uretimTarihi" class="tedarik-detailed-input" placeholder="Malzeme Üretim Tarihi" />
                <div class="tedarik-detailed-select-wrap">
                    <select v-model="detay.tedarikci" class="tedarik-detailed-select">
                        <option value="">Tedarikçi Ara</option>
                        <option v-for="opt in tedarikciOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <i class="ki-outline ki-down tedarik-detailed-arrow"></i>
                </div>
                <div class="tedarik-detailed-select-wrap">
                    <select v-model="detay.sirket" class="tedarik-detailed-select">
                        <option value="">Şirket Ara</option>
                        <option v-for="opt in clientOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <i class="ki-outline ki-down tedarik-detailed-arrow"></i>
                </div>
                <div class="tedarik-detailed-select-wrap">
                    <select v-model="detay.onayDurumu" class="tedarik-detailed-select">
                        <option value="">Sipariş Onay Durumu</option>
                        <option value="doc_trans_order_created">Sipariş Oluşturuldu</option>
                        <option value="doc_trans_order_transfer_sent">Dosyalar Kontrol Ediliyor</option>
                        <option value="doc_trans_order_ready_for_shipment">Sipariş Sevke Hazır</option>
                        <option value="doc_trans_order_approved">Sipariş Onaylandı</option>
                        <option value="doc_trans_order_rejected">Sipariş Reddedildi</option>
                        <option value="doc_trans_order_files_rejected">Reddedilen Dosyalar Mevcut</option>
                    </select>
                    <i class="ki-outline ki-down tedarik-detailed-arrow"></i>
                </div>
                <input v-model="detay.tarihAraligi" class="tedarik-detailed-input" placeholder="Tarih Aralığı Seçiniz" />
            </div>
            <div class="tedarik-detailed-actions">
                <button class="tedarik-btn-light" @click="applyDetailedFilter">Filtrele</button>
                <button class="tedarik-btn-light" @click="resetDetailedFilter">Sıfırla</button>
                <button class="tedarik-btn-orange" @click="exportDetailed"><i class="ki-outline ki-exit-down"></i> Excel Çıktı</button>
                <span class="tedarik-detailed-note">Eğer yukarıdaki filtrelerden en az biri seçili olmazsa tüm listeyi Excel olarak çıktı alabilirsiniz.</span>
            </div>
        </div>
        <div class="order-list-body">
            <div id="div_table"></div>
        </div>
        <div v-if="isTedarik" class="tedarik-bottom-note">
            <i class="ki-outline ki-information-5"></i>
            <span>Beklemede olan siparişlerinizi <b>"Detaylar"</b> butonuna tıklayarak kontrollerinizi gerçekleştirmeyi unutmayınız.</span>
        </div>
        <!-- hidden search for tedarik filtering (keeps searchTable working) -->
        <input v-if="isTedarik" id="mainSearch" type="hidden" value="" />
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
/* ===== TEDARIK 1:1 Overrides ===== */
.tedarik-card {
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
}
.tedarik-list-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 2px 4px 18px;
    gap: 12px;
    flex-wrap: wrap;
    border-bottom: none;
}
.tedarik-list-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 19px;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.02em;
    line-height: 1;
}
.tedarik-title-icon {
    font-size: 13px;
    color: #9ca3af;
    opacity: 1;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    width: 22px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fff;
}
.tedarik-filters {
    display: flex;
    align-items: center;
    gap: 22px;
}
.tedarik-filter {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13.5px;
    font-weight: 500;
    color: #8a8a8e;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
}
.tedarik-filter i { font-size: 13px; color: #a1a1a6; }
.tedarik-filter:hover { color: #4b5563; }
.tedarik-card .order-list-body {
    background: transparent !important;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.tedarik-card :deep(.pickletable .divTable){
    flex: 1;
    height: auto !important;
    overflow: visible !important;
}
.tedarik-card :deep(.pickletable table){
    border-collapse: separate !important;
    border-spacing: 0 7px !important;
    width: 100% !important;
    table-layout: auto !important;
    border: none !important;
}
.tedarik-card :deep(.pickletable thead th){
    background: transparent !important;
    color: #b0b0b5 !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    text-transform: none !important;
    letter-spacing: 0 !important;
    padding: 0 14px 10px !important;
    border: none !important;
    border-bottom: none !important;
    white-space: nowrap;
    text-align: left !important;
    box-shadow: none !important;
    position: relative !important;
    top: auto !important;
}
.tedarik-card :deep(.pickletable thead th:last-child){
    text-align: right !important;
}
.tedarik-card :deep(.pickletable tbody tr){
    background: transparent !important;
    box-shadow: none !important;
}
.tedarik-card :deep(.pickletable tbody tr:hover td){
    background: #fcfcfc !important;
}
.tedarik-card :deep(.pickletable tbody td){
    background: #fff !important;
    padding: 13px 14px !important;
    font-size: 13.5px !important;
    color: #2b2b33 !important;
    border: 1px solid #e8e8ea !important;
    border-left: 1px solid #e8e8ea !important;
    border-right: 1px solid #e8e8ea !important;
    vertical-align: middle !important;
    white-space: nowrap;
    font-weight: 400;
    text-overflow: clip !important;
    overflow: visible !important;
}
.tedarik-card :deep(.pickletable tbody td:first-child){
    border-left: 1px solid #e8e8ea !important;
    border-top-left-radius: 8px !important;
    border-bottom-left-radius: 8px !important;
    font-weight: 600 !important;
    color: #111827 !important;
}
.tedarik-card :deep(.pickletable tbody td:last-child){
    border-right: 1px solid #e8e8ea !important;
    border-top-right-radius: 8px !important;
    border-bottom-right-radius: 8px !important;
}
.tedarik-card :deep(.pickletable .divPagination){
    background: transparent !important;
    border-top: none !important;
    padding: 10px 0 0 !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    position: relative;
}
.tedarik-card :deep(.pickletable .divPagination .pagination){
    gap: 5px;
    margin-top: 14px;
}
.tedarik-card :deep(.pickletable .divPagination button),
.tedarik-card :deep(.pickletable .divPagination .page-link){
    border: 1px solid #e5e7eb !important;
    background: #fff !important;
    color: #8a8a8e !important;
    border-radius: 6px !important;
    padding: 5px 10px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    min-width: 32px;
    height: 30px;
}
.tedarik-card :deep(.pickletable .divPagination .active button),
.tedarik-card :deep(.pickletable .divPagination .active .page-link){
    background: #fff !important;
    color: #FF5A1F !important;
    border-color: #FF5A1F !important;
    font-weight: 700 !important;
}
.tedarik-bottom-note {
    margin-top: 10px;
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-size: 11.5px;
    color: #8a8a8e;
    line-height: 1.6;
    justify-content: flex-end;
    text-align: right;
    margin-left: auto;
    max-width: 520px;
}
.tedarik-bottom-note i {
    color: #0e9cb8;
    font-size: 13px;
    margin-top: 2px;
    flex-shrink: 0;
}
.tedarik-bottom-note b { color: #4b5563; font-weight: 700; }
/* ===== Detailed search panel ===== */
.tedarik-card{ position: relative; }
.tedarik-detailed-panel{
    position: absolute; top: 52px; left: 4px; right: 4px; z-index: 40;
    background: #fff; border: 1px solid #e8e8ea; border-radius: 14px;
    padding: 18px 18px 16px; margin: 0; box-shadow: 0 12px 32px rgba(0,0,0,0.14);
    animation: tedarikDetailedIn .18s ease;
}
@keyframes tedarikDetailedIn{ from{ opacity:0; transform: translateY(-6px)} to{ opacity:1; transform:none } }
.tedarik-detailed-grid{
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;
}
.tedarik-detailed-input{
    height: 44px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff;
    padding: 10px 14px; font-size: 13.5px; color: #1e293b; outline: none; width: 100%;
    transition: border-color .15s, box-shadow .15s;
}
.tedarik-detailed-input::placeholder{ color:#9ca3af; }
.tedarik-detailed-input:focus{ border-color: #FF5A1F; box-shadow: 0 0 0 3px rgba(255,90,31,0.12); }
.tedarik-detailed-select-wrap{
    position: relative; display: flex; align-items: center;
}
.tedarik-detailed-select{
    height: 44px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff;
    padding: 10px 36px 10px 14px; font-size: 13.5px; color: #1e293b; outline: none; width: 100%;
    appearance: none; -webkit-appearance: none; cursor: pointer;
}
.tedarik-detailed-select:focus{ border-color: #FF5A1F; box-shadow: 0 0 0 3px rgba(255,90,31,0.12); }
.tedarik-detailed-select-wrap .tedarik-detailed-arrow{
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    font-size: 12px; color: #9ca3af; pointer-events: none;
}
.tedarik-detailed-actions{
    display: flex; gap: 12px; align-items: center; margin-top: 16px; flex-wrap: wrap;
}
.tedarik-detailed-actions .tedarik-btn-light{ min-width: 140px; }
.tedarik-detailed-actions .tedarik-btn-orange{ min-width: 180px; }
.tedarik-btn-light{
    height: 44px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff;
    font-size: 14px; font-weight: 600; color: #475569; cursor: pointer; transition: all .15s;
}
.tedarik-btn-light:hover{ background:#f8fafc; border-color:#cbd5e1; color:#1e293b; }
.tedarik-btn-orange{
    height: 44px; border: none; border-radius: 10px; background: #FF5A1F; color:#fff;
    font-size: 14px; font-weight: 700; cursor: pointer; display:inline-flex; align-items:center; justify-content:center; gap:6px;
    box-shadow: 0 2px 8px rgba(255,90,31,0.22); transition: background .15s;
}
.tedarik-btn-orange:hover{ background:#e0541b; }
.tedarik-detailed-note{ font-size: 12px; color:#8a8a8e; line-height: 1.5; padding-left: 8px; }
@media (max-width: 960px){
    .tedarik-detailed-grid{ grid-template-columns: 1fr; }
    .tedarik-detailed-actions{ grid-template-columns: 1fr; }
    .tedarik-detailed-note{ padding-left:0; }
}
</style>
<!-- UNSCOPED: override pickletable global defaults for tedarik card-rows -->
<style>
.tedarik-card .pickletable {
    border: none !important;
    height: auto !important;
}
.tedarik-card .pickletable .divTable {
    height: auto !important;
    overflow: visible !important;
    border-bottom: none !important;
}
.tedarik-card .pickletable table {
    border-collapse: separate !important;
    border-spacing: 0 7px !important;
    table-layout: auto !important;
    border: none !important;
    width: 100% !important;
}
.tedarik-card .pickletable th {
    background: transparent !important;
    color: #b0b0b5 !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    border: none !important;
    box-shadow: none !important;
    position: relative !important;
    top: auto !important;
    padding: 0 14px 10px !important;
}
.tedarik-card .pickletable td {
    background: #fff !important;
    border: 1px solid #e8e8ea !important;
    font-size: 13.5px !important;
    padding: 13px 14px !important;
    text-overflow: clip !important;
    overflow: visible !important;
}
.tedarik-card .pickletable td:first-child {
    border-left: 1px solid #e8e8ea !important;
    border-top-left-radius: 8px !important;
    border-bottom-left-radius: 8px !important;
    font-weight: 600 !important;
}
.tedarik-card .pickletable td:last-child {
    border-right: 1px solid #e8e8ea !important;
    border-top-right-radius: 8px !important;
    border-bottom-right-radius: 8px !important;
}
.tedarik-card .pickletable tr {
    background: transparent !important;
}
.tedarik-card .pickletable tr:hover td {
    background: #fcfcfc !important;
}
.tedarik-card .pickletable .divPagination {
    border-top: none !important;
}
/* Card-row style for tedarik */
.tedarik-card .pickletable .pt-auto-height {
    border-collapse: separate !important;
    border-spacing: 0 7px !important;
    width: 100% !important;
    table-layout: auto !important;
    border: none !important;
}
.tedarik-card .pickletable .pt-auto-height tbody tr {
    background: white !important;
    border-radius: 14px !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06) !important;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    border: none !important;
}
.tedarik-card .pickletable .pt-auto-height tbody tr:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important;
}
.tedarik-card .pickletable .pt-auto-height tbody td {
    background: transparent !important;
    border: none !important;
    padding: 14px 12px !important;
    font-size: 13px !important;
    color: #333 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    vertical-align: middle !important;
}
.tedarik-card .pickletable .pt-auto-height tbody td:first-child {
    border-radius: 14px 0 0 14px !important;
}
.tedarik-card .pickletable .pt-auto-height tbody td:last-child {
    border-radius: 0 14px 14px 0 !important;
}
/* Make table fill full page height */
.tedarik-card .order-list-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.tedarik-card .pickletable {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.tedarik-card .pickletable .divTable {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}
</style>
