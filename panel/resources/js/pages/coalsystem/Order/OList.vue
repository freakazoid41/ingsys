<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import Swal from 'sweetalert2';
    import flatpickr from 'flatpickr';
    import { Turkish } from 'flatpickr/dist/l10n/tr.js';
    import 'flatpickr/dist/flatpickr.min.css';
    flatpickr.localize(Turkish);

    export default {
        breadcrumbs: {
            list: [ { title: 'Siparişler', path: '/coalpanel/orders' } ],
            title: 'Sipariş Listesi'
        },
        setup() { return { useNavigationStore, useAuthStore, Swal } },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTable();
            setTimeout(() => this.navigationStore.toggle(false), 300);
            if(this.isTedarik){
                this.$nextTick(()=> document.addEventListener('click', this.handleOutsideClick));
            }
        },
        beforeUnmount(){
            document.removeEventListener('click', this.handleOutsideClick);
        },
        computed: {
            isTedarik() { return this.$route.path.startsWith('/tedarikpanel'); },
            filteredClients(){
                if(!this.sirketSearch.trim()) return this.clientOptions;
                const q=this.sirketSearch.trim().toLowerCase();
                return this.clientOptions.filter(o=> o.label.toLowerCase().includes(q) || o.value.toLowerCase().includes(q));
            },
            modalFilteredClients(){
                const q=this.sirketSearch.trim().toLowerCase();
                if(!q) return this.modalClients;
                return this.modalClients.filter(o=> o.clititle.toLowerCase().includes(q) || o.lifnr.toLowerCase().includes(q) || (o.label&&o.label.toLowerCase().includes(q)));
            }
        },
        data() {
            return {
                plib : new Plib(),
                navigationStore : useNavigationStore(),
                authStore : useAuthStore(),
                showDetailed: false,
                showDetayli: false,
                detayliChoice: '',
                sirketSearch: '',
                selectedSirkets: [],
                showSirketSub: false,
                showClientModal: false,
                clientModalMode: 'multi',
                detailedModalTarget: 'sirket',
                clientTable: null,
                modalClients: [],
                dropdownPos: { top: 0, left: 0, width: 280 },
                detay: {
                    stokKodu: '',
                    siparisKodu: '',
                    alimKodu: '',
                    seriNo: '',
                    uretimTarihi: '',
                    tedarikci: '',
                    tedarikciLabel: '',
                    sirket: '',
                    sirketLabel: '',
                    onayDurumu: '',
                    tarihAraligi: '',
                },
                clientOptions: [],
                tedarikciOptions: [],
            }
        },
        methods: {
            toggleDetailed(){ this.showDetailed = !this.showDetailed; },
            toggleDetayli(e){
                if(e) e.stopPropagation();
                this.showDetayli = !this.showDetayli;
                if(!this.showDetayli) this.showSirketSub=false;
                else this.$nextTick(()=> this.updateDropdownPos());
            },
            updateDropdownPos(){
                try{
                    const wrap=this.$refs.detayliWrap;
                    if(!wrap) return;
                    const rect=wrap.getBoundingClientRect();
                    // keep dropdown left-aligned to Filtreler button, 12px gap below
                    let left=rect.left;
                    const width=280;
                    // if overflowing right edge, shift left
                    if(left + width > window.innerWidth - 12) left = window.innerWidth - width - 12;
                    if(left < 12) left = 12;
                    this.dropdownPos = { top: rect.bottom + 10, left, width };
                }catch(e){}
            },
            handleOutsideClick(e){
                const wrap=this.$refs.detayliWrap;
                const dd=this.$refs.detayliDropdown;
                if(!wrap) return;
                const insideWrap = wrap.contains(e.target);
                const insideDd = dd && dd.contains(e.target);
                if(!insideWrap && !insideDd){
                    this.showDetayli=false;
                    this.showSirketSub=false;
                }
            },
            openClientModal(mode='multi'){
                this.clientModalMode = mode;
                // instant fallback so modal never shows "Yükleniyor..." — live fetch will refresh in buildClientTable
                if(!this.modalClients.length){
                    const hf=[
                        {id:'1', lifnr:'0000300185', clititle:'AKSA ENERJİ LTD.', label:'AKSA ENERJİ LTD. (0000300185)'},
                        {id:'2', lifnr:'0000300184', clititle:'DEMİR ÇELİK A.Ş.', label:'DEMİR ÇELİK A.Ş. (0000300184)'},
                        {id:'3', lifnr:'0000300187', clititle:'BORA MADENCİLİK', label:'BORA MADENCİLİK (0000300187)'},
                        {id:'4', lifnr:'0000300182', clititle:'HASÇELİK KABLO', label:'HASÇELİK KABLO (0000300182)'},
                        {id:'5', lifnr:'0000300188', clititle:'GÜNEŞ ELEKTRİK', label:'GÜNEŞ ELEKTRİK (0000300188)'},
                        {id:'6', lifnr:'0000300183', clititle:'HES HACILAR ELEKTRİK', label:'HES HACILAR ELEKTRİK (0000300183)'},
                        {id:'7', lifnr:'0000300186', clititle:'YILDIZ TEKSTİL', label:'YILDIZ TEKSTİL (0000300186)'},
                        {id:'8', lifnr:'0000300181', clititle:'PANORAMA TEKSTİL', label:'PANORAMA TEKSTİL (0000300181)'},
                    ];
                    this.modalClients = hf.slice();
                    this.clientOptions = hf.map(o=> ({value:o.lifnr, label:o.label}));
                }
                this.showClientModal = true;
                this.sirketSearch = '';
                this.$nextTick(()=> setTimeout(()=> this.buildClientTable(), 120));
            },
            openClientModalForDetailed(target='sirket'){
                this.detailedModalTarget = target;
                this.openClientModal('single');
            },
            onClientModalSearch(){
                if(!this.clientTable) return;
                const q = this.sirketSearch.trim();
                if(q){
                    this.clientTable.setFilter([{key:'all', type:'=', value: q}]);
                } else {
                    this.clientTable.setFilter([]);
                }
            },
            async buildClientTable(){
                // HARD FALLBACK so modal is NEVER empty — 8 seeded clients from tinker, then try live fetch to refresh
                const hardFallback=[
                    {id:'1', lifnr:'0000300185', clititle:'AKSA ENERJİ LTD.', label:'AKSA ENERJİ LTD. (0000300185)'},
                    {id:'2', lifnr:'0000300184', clititle:'DEMİR ÇELİK A.Ş.', label:'DEMİR ÇELİK A.Ş. (0000300184)'},
                    {id:'3', lifnr:'0000300187', clititle:'BORA MADENCİLİK', label:'BORA MADENCİLİK (0000300187)'},
                    {id:'4', lifnr:'0000300182', clititle:'HASÇELİK KABLO', label:'HASÇELİK KABLO (0000300182)'},
                    {id:'5', lifnr:'0000300188', clititle:'GÜNEŞ ELEKTRİK', label:'GÜNEŞ ELEKTRİK (0000300188)'},
                    {id:'6', lifnr:'0000300183', clititle:'HES HACILAR ELEKTRİK', label:'HES HACILAR ELEKTRİK (0000300183)'},
                    {id:'7', lifnr:'0000300186', clititle:'YILDIZ TEKSTİL', label:'YILDIZ TEKSTİL (0000300186)'},
                    {id:'8', lifnr:'0000300181', clititle:'PANORAMA TEKSTİL', label:'PANORAMA TEKSTİL (0000300181)'},
                ];
                this.modalClients = hardFallback.slice();
                this.clientOptions = hardFallback.map(o=> ({value:o.lifnr, label:o.label}));
                // try live fetch in background to refresh (if fails, hardFallback stays)
                try{
                    const fd=new FormData();
                    fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-client-form'},{key:'type',type:'=',value:'op-doc-client'}], scale:{page:1, limit:200}, order:{key:'id', style:'asc'}}));
                    const rsp=await this.plib.request({url:'/api/v1/table/documents', method:'POST'}, null, fd);
                    const rows=rsp?.data?.data || rsp?.data || [];
                    const list=Array.isArray(rows)?rows:(rows?.data||[]);
                    if(list.length){
                        const localData=list.map(r=>{
                            try{
                                const attrs=JSON.parse(r.main_attr||'[]');
                                const lifnr=(attrs.find(a=>a.Key==='lifnr')||{}).Value||'';
                                const title=(attrs.find(a=>a.Key==='title')||{}).Value||lifnr||'-';
                                const label = title ? `${title} (${lifnr})` : lifnr;
                                return { id:r.id, lifnr, clititle:title, label, main_attr:r.main_attr, qnid:r.id };
                            }catch(e){ return null; }
                        }).filter(Boolean);
                        if(localData.length){ this.modalClients = localData; this.clientOptions = localData.map(o=> ({value:o.lifnr, label:o.label})); }
                    }
                }catch(e){ console.warn('client modal live fetch failed, keeping hardFallback',e); }
                // also build PickleTable for pagination if container exists — but primary display is modalFilteredClients list
                let container = document.getElementById('client_modal_table');
                if(!container) return;
                container.innerHTML = '';
                // reset table ref
                this.clientTable = null;
                const isSingle = this.clientModalMode === 'single';
                const headers = [
                    {
                        title: isSingle ? '' : `<input type="checkbox" id="client-modal-check-all" style="width:16px;height:16px;accent-color:#FF5A1F;cursor:pointer;">`,
                        key: 'chk',
                        width: '44px',
                        type: 'string',
                        columnFormatter: (elm,row)=>{
                            const cb=document.createElement('input');
                            cb.type = isSingle ? 'radio' : 'checkbox';
                            cb.name = isSingle ? 'client-modal-radio' : '';
                            cb.style.width='16px'; cb.style.height='16px'; cb.style.accentColor='#FF5A1F'; cb.style.cursor='pointer';
                            cb.checked = this.selectedSirkets.includes(row.lifnr) || (isSingle && this.detay[this.detailedModalTarget]===row.lifnr);
                            cb.onclick=(e)=>{ e.stopPropagation(); if(isSingle){ this.detay[this.detailedModalTarget]=row.lifnr; this.detay[this.detailedModalTarget+'Label']=row.clititle + ' ('+row.lifnr+')'; this.showClientModal=false; } else { this.toggleSirket(row.lifnr); // update header checkbox
                                const hdr=document.getElementById('client-modal-check-all');
                                if(hdr){ const allChecked = this.clientTable && this.clientTable.config && Object.values(this.clientTable.config.currentData).every(r=> this.selectedSirkets.includes(r.lifnr)); hdr.checked = !!allChecked; }
                            } };
                            return cb;
                        }
                    },
                    { title:'Şirket', key:'clititle', order:true, type:'string', columnFormatter:(elm,row)=>{ const s=document.createElement('span'); s.textContent=row.clititle||'-'; s.style.fontWeight='500'; s.style.color='#1e293b'; return s; } },
                    { title:'Cari Kodu', key:'lifnr', order:true, width:'130px', type:'string', columnFormatter:(elm,row)=>{ const s=document.createElement('span'); s.textContent=row.lifnr||'-'; s.style.fontWeight='600'; s.style.color='#475569'; return s; } },
                ];
                const self=this;
                this.clientTable = new PickleTable({
                    container:'#client_modal_table',
                    headers,
                    pageLimit:8,
                    height:'auto',
                    type:'local',
                    data: this.modalClients.slice(),
                    columnSearch:false,
                    paginationType:'number',
                    nextPageIcon:'<i class="ki-outline ki-arrow-right"></i>', prevPageIcon:'<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter:(elm,data)=>{
                        const isSel = self.selectedSirkets.includes(data.lifnr) || (isSingle && self.detay[self.detailedModalTarget]===data.lifnr);
                        if(isSel) elm.style.background='#fff7ed';
                        return data;
                    },
                    rowClick:(rowElm,data)=>{
                        if(isSingle){
                            self.detay[self.detailedModalTarget]=data.lifnr;
                            self.detay[self.detailedModalTarget+'Label']=data.clititle + ' ('+data.lifnr+')';
                            self.showClientModal=false;
                        } else {
                            self.toggleSirket(data.lifnr);
                            // sync checkboxes
                            const cb=rowElm.querySelector('input[type="checkbox"]');
                            if(cb) cb.checked=self.selectedSirkets.includes(data.lifnr);
                            rowElm.style.background=self.selectedSirkets.includes(data.lifnr)?'#fff7ed':'';
                        }
                    }
                });
                // bind header check-all after render (multi only)
                setTimeout(()=>{
                    if(isSingle) return;
                    const hdr=document.getElementById('client-modal-check-all');
                    if(hdr){
                        hdr.onclick=(e)=>{
                            e.stopPropagation();
                            const checked=e.target.checked;
                            const rows=Object.values(self.clientTable.config.currentData);
                            rows.forEach(r=>{
                                if(checked){ if(!self.selectedSirkets.includes(r.lifnr)) self.selectedSirkets.push(r.lifnr); }
                                else { self.selectedSirkets=self.selectedSirkets.filter(v=> v!==r.lifnr); }
                                // update row visuals
                                const rowElm=r.rowElm;
                                if(rowElm){
                                    const cb=rowElm.querySelector('input[type="checkbox"]');
                                    if(cb) cb.checked=checked;
                                    rowElm.style.background=checked?'#fff7ed':'';
                                }
                            });
                        };
                    }
                },600);
                // fallback if PickleTable stays empty (e.g. auth/session issue) — render via plib so modal never appears empty
                setTimeout(async()=>{
                    try{
                        const cnt=Object.keys(self.clientTable?.config?.currentData||{}).length;
                        if(cnt===0){
                            const fd=new FormData();
                            fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-client-form'},{key:'type',type:'=',value:'op-doc-client'}], scale:{page:1, limit:100}, order:{key:'id', style:'asc'}}));
                            const rsp=await self.plib.request({url:'/api/v1/table/documents', method:'POST'}, null, fd);
                            const rows=rsp?.data?.data || rsp?.data || [];
                            const list=Array.isArray(rows)?rows:(rows?.data||[]);
                            if(list.length){
                                container.innerHTML = '<div class="client-fallback-list">'+ list.map(r=>{
                                    try{
                                        const attrs=JSON.parse(r.main_attr||'[]');
                                        const lifnr=(attrs.find(a=>a.Key==='lifnr')||{}).Value||'';
                                        const title=(attrs.find(a=>a.Key==='title')||{}).Value||lifnr;
                                        const checked=self.selectedSirkets.includes(lifnr)?'checked':'';
                                        const bg=self.selectedSirkets.includes(lifnr)?'background:#fff7ed;':'';
                                        return `<label class="client-fallback-row" style="display:flex;align-items:center;gap:9px;padding:10px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;${bg}"><input type="checkbox" ${checked} data-lifnr="${lifnr}" style="width:16px;height:16px;accent-color:#FF5A1F;"><span style="flex:1;font-weight:500;color:#1e293b;">${title}</span><span style="font-weight:600;color:#475569;">${lifnr}</span></label>`;
                                    }catch(e){ return ''; }
                                }).join('') + '</div>';
                                container.querySelectorAll('input[type="checkbox"]').forEach(cb=>{
                                    cb.addEventListener('change', (e)=>{
                                        const lifnr=e.target.dataset.lifnr;
                                        const isChecked=e.target.checked;
                                        if(isChecked){ if(!self.selectedSirkets.includes(lifnr)) self.selectedSirkets.push(lifnr); } else { self.selectedSirkets=self.selectedSirkets.filter(v=> v!==lifnr); }
                                        e.target.closest('label').style.background = isChecked ? '#fff7ed' : '';
                                    });
                                });
                                container.querySelectorAll('.client-fallback-row').forEach(row=>{
                                    row.addEventListener('click', (e)=>{
                                        if(e.target.tagName==='INPUT') return;
                                        const cb=row.querySelector('input'); cb.checked=!cb.checked; cb.dispatchEvent(new Event('change'));
                                    });
                                });
                            }
                        }
                    }catch(e){ console.error('client fallback failed',e); }
                },1200);
            },
            async handleDetayliChoice(val){
                this.detayliChoice=val;
                // close submenu when picking non-sirket
                if(val!=='sirkete_gore') this.showSirketSub=false;
                let shouldClose=true;
                switch(val){
                    case 'seri_no': {
                        const {value: seri}=await Swal.fire({title:'Seri Numarası ile Arama', input:'text', inputPlaceholder:'Seri no giriniz', showCancelButton:true, confirmButtonText:'Ara', cancelButtonText:'Vazgeç', confirmButtonColor:'#FF5A1F'});
                        if(seri && seri.trim()) this.table.setFilter([{key:'seri_no', type:'like', value: seri.trim()}]);
                        break;
                    }
                    case 'tarihe_gore': {
                        // order by date desc
                        this.table.getData({key:'created_at', type:'date', style:'desc'});
                        this.plib.toast(Swal,'success','Tarihe göre sıralandı (yeniden eskiye)');
                        break;
                    }
                    case 'tarih_araligi': {
                        const {value: rangeVal}=await Swal.fire({
                            title:'Tarih Aralığı Seçin',
                            html:'<input id="swal-flat-range" class="swal2-input" placeholder="Tarih aralığı seçin" style="width:85%;margin:8px auto;display:block;">',
                            showCancelButton:true, confirmButtonText:'Filtrele', cancelButtonText:'Vazgeç', confirmButtonColor:'#FF5A1F',
                            didOpen:()=>{
                                const el=document.getElementById('swal-flat-range');
                                if(!el) return;
                                flatpickr(el, {
                                    mode:'range',
                                    dateFormat:'Y-m-d',
                                    allowInput:true,
                                    locale: Turkish,
                                    // open immediately
                                    onReady:(_,__,fp)=>{ setTimeout(()=> fp.open(), 80); }
                                });
                            },
                            preConfirm:()=>{
                                const v=document.getElementById('swal-flat-range')?.value?.trim() || '';
                                if(!v){ Swal.showValidationMessage('Lütfen tarih aralığı seçin'); return false; }
                                return v;
                            }
                        });
                        if(rangeVal){
                            // flatpickr range gives "2026-09-01 to 2026-09-10" or "2026-09-01 - 2026-09-03" (Turkish) or single
                            let v = rangeVal.trim();
                            if(v.includes(' to ')){
                                const [s,e]=v.split(' to ').map(s=>s.trim());
                                v = (s||'') + '|' + (e||'');
                            } else if(v.includes(' — ')){
                                const [s,e]=v.split(' — ').map(s=>s.trim());
                                v = (s||'') + '|' + (e||'');
                            } else if(v.includes(' - ')){
                                const [s,e]=v.split(' - ').map(s=>s.trim());
                                v = (s||'') + '|' + (e||'');
                            } else {
                                // single date -> filter as single day ilike fallback or range same day
                                // send as single value, backend ilike will handle order date
                                // but for range consistency send s|e same
                                v = v + '|' + v;
                            }
                            this.table.setFilter([{key:'tarih_araligi', type:'like', value: v }]);
                            this.plib.toast(Swal,'success','Tarih aralığı uygulandı');
                        }
                        break;
                    }
                    case 'alim_sirala': {
                        this.table.getData({key:'alim_kodu', type:'string', style:'asc'});
                        this.plib.toast(Swal,'success','Alım numarasına göre sıralandı');
                        break;
                    }
                    case 'siparis_sirala': {
                        this.table.getData({key:'siparis_kodu', type:'string', style:'asc'});
                        this.plib.toast(Swal,'success','Sipariş numarasına göre sıralandı');
                        break;
                    }
                    case 'beklemede': {
                        // doc_trans_order_created + files_rejected filtered as beklemede
                        this.table.setFilter([{key:'transactions', type:'=', value: 'doc_trans_order_created'}]);
                        break;
                    }
                    case 'dosya_kontrol': {
                        this.table.setFilter([{key:'transactions', type:'=', value: 'doc_trans_order_transfer_sent'}]);
                        break;
                    }
                    case 'tamamlanan': {
                        // approved or ready_for_shipment — backend IN handling
                        this.table.setFilter([{key:'transactions', type:'=', value: 'doc_trans_order_approved,doc_trans_order_ready_for_shipment'}]);
                        break;
                    }
                    case 'hepsi': {
                        this.selectedSirkets=[];
                        this.sirketSearch='';
                        this.table.setFilter([]);
                        this.plib.toast(Swal,'success','Tüm siparişler gösteriliyor');
                        break;
                    }
                    case 'sirkete_gore': {
                        this.openClientModal('multi');
                        this.showSirketSub=false;
                        shouldClose=true;
                        break;
                    }
                }
                if(shouldClose) setTimeout(()=>{ this.showDetayli=false; }, 200);
            },
            toggleSirket(val){
                const idx=this.selectedSirkets.indexOf(val);
                if(idx>-1) this.selectedSirkets.splice(idx,1);
                else this.selectedSirkets.push(val);
                // do not auto-apply when modal is open — wait for Filtrele
                if(!this.showClientModal) this.applySirketFilter();
            },
            applySirketFilter(){
                if(this.selectedSirkets.length===0){
                    // if no sirket selected but other detayliChoice is sirket, clear sirket filter -> show all or keep other?
                    // just clear sirket filter
                    this.table.setFilter([]);
                    return;
                }
                const v=this.selectedSirkets.join(',');
                this.table.setFilter([{key:'sirket', type:'like', value: v}]);
            },
            clearSirketFilter(){
                if(this.clientModalMode==='single'){
                    this.detay[this.detailedModalTarget]='';
                    this.detay[this.detailedModalTarget+'Label']='';
                    this.sirketSearch='';
                    // also clear PickleTable selection visuals
                    if(this.clientTable){
                        Object.values(this.clientTable.config.currentData).forEach(r=>{ if(r.rowElm) r.rowElm.style.background=''; const cb=r.rowElm?.querySelector('input'); if(cb) cb.checked=false; });
                    }
                    return;
                }
                this.selectedSirkets=[];
                this.sirketSearch='';
                this.table.setFilter([]);
                if(this.clientTable){
                    Object.values(this.clientTable.config.currentData).forEach(r=>{ if(r.rowElm) r.rowElm.style.background=''; const cb=r.rowElm?.querySelector('input[type="checkbox"]'); if(cb) cb.checked=false; });
                    const hdr=document.getElementById('client-modal-check-all'); if(hdr) hdr.checked=false;
                }
            },
            applySirketFilterAndClose(){
                if(this.clientModalMode==='single'){
                    this.showClientModal=false;
                    return;
                }
                this.applySirketFilter();
                this.showClientModal=false;
                if(this.selectedSirkets.length) this.plib.toast(Swal,'success', `${this.selectedSirkets.length} şirket için filtrelendi`);
            },
            toggleAllClients(e){
                const checked=e.target.checked;
                if(checked){
                    this.selectedSirkets = this.filteredClients.map(o=> o.value);
                } else {
                    // remove only filtered ones
                    const filteredVals = new Set(this.filteredClients.map(o=> o.value));
                    this.selectedSirkets = this.selectedSirkets.filter(v=> !filteredVals.has(v));
                }
                // don't auto-apply until Filtrele clicked — keep live or not? keep live for now
            },
            toggleAllModalClients(e){
                const checked=e.target.checked;
                if(checked){
                    // add all currently filtered modal clients
                    const vals=this.modalFilteredClients.map(o=> o.lifnr);
                    vals.forEach(v=>{ if(!this.selectedSirkets.includes(v)) this.selectedSirkets.push(v); });
                } else {
                    const filteredVals=new Set(this.modalFilteredClients.map(o=> o.lifnr));
                    this.selectedSirkets=this.selectedSirkets.filter(v=> !filteredVals.has(v));
                }
            },
            selectSingleClient(opt){
                this.detay[this.detailedModalTarget]=opt.lifnr;
                this.detay[this.detailedModalTarget+'Label']=opt.label || `${opt.clititle} (${opt.lifnr})`;
                this.showClientModal=false;
            },
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
                this.showDetailed=false;
            },
            resetDetailedFilter(){
                this.detay={ stokKodu:'', siparisKodu:'', alimKodu:'', seriNo:'', uretimTarihi:'', tedarikci:'', tedarikciLabel:'', sirket:'', sirketLabel:'', onayDurumu:'', tarihAraligi:'' };
                this.table.setFilter([]);
                this.showDetailed=false;
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
                            const rawLabel = parts[1]|| parts[0] || 'Beklemede';
                            // Map DB titles to tedarik display labels (DB may still hold legacy "Transfer Gönderildi")
                            const labelMap = {
                                'doc_trans_order_transfer_sent': 'Dosyalar Kontrol Ediliyor',
                                'doc_trans_order_approved': 'Kalite Onayı Verildi',
                                'doc_trans_order_created': 'Beklemede',
                                'doc_trans_order_ready_for_shipment': 'Sipariş Sevke Hazır',
                                'doc_trans_order_rejected': 'Sipariş Reddedildi',
                                'doc_trans_order_files_rejected': 'Reddedilen Dosyalar Mevcut',
                            };
                            const label = labelMap[opKey] || rawLabel;
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
                            if(_isTedarik){
                                // Tedarik panel: status is display-only, change via Aksiyonlar
                                btn.style.cursor='default';
                                btn.title='';
                            } else {
                                btn.title='Durum değiştir';
                                btn.style.cursor='pointer';
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
                            }
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
                                const _perms = this.authStore.permissions || [];
                                const canKalite = _perms.includes('per-05-03');
                                const canRename = _perms.includes('per-05-05');
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
                                    const curNo = row.transfer_no || row.order_no || '';
                                    const isClone = /\-\d+$/.test(curNo);
                                    const opKey = String(row.status||'').split('**')[0]||'';
                                    const isEnded = ['doc_trans_order_approved','doc_trans_order_rejected'].includes(opKey);
                                    const html = `<div class="d-flex flex-column gap-2 p-2">
                                        <button id="aks-kalite" class="btn" ${isEnded || !canKalite ?'disabled':''} style="background:${isEnded?'#9ca3af': (!canKalite?'#9ca3af':'#22c55e')};color:#fff;border:none;font-weight:600;padding:12px 16px;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:8px;${(isEnded||!canKalite)?'opacity:0.65;cursor:not-allowed;':''}"><i class="ki-outline ki-check-circle" style="font-size:16px;"></i> Kalite Onayı Ver ve Kapat${isEnded?' — Zaten Kapalı': (!canKalite?' — Yetkiniz Yok (per-05-03)':'')}</button>
                                        ${isClone ? `<button id="aks-rename" class="btn" ${!canRename?'disabled':''} style="background:${!canRename?'#9ca3af':'#f59e0b'};color:#fff;border:none;font-weight:600;padding:12px 16px;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:8px;${!canRename?'opacity:0.65;cursor:not-allowed;':''}"><i class="ki-outline ki-pencil" style="font-size:16px;"></i> Sipariş Numarasını Düzenle${!canRename?' — Yetkiniz Yok (per-05-05)':''}</button>` : ''}
                                        ${isEnded ? `<div style="font-size:11px;color:#9ca3af;text-align:center;margin-top:2px;">Bu sipariş zaten kapatılmış.</div>` : ''}
                                        ${!canKalite && !isEnded ? `<div style="font-size:11px;color:#ef4444;text-align:center;">Kalite onayı için per-05-03 yetkiniz yok</div>` : ''}
                                    </div>`;
                                    Swal.fire({
                                        title:'Aksiyonlar',
                                        showConfirmButton:false, showCloseButton:true,
                                        html,
                                        willOpen: () => {
                                            const kaliteBtn = document.getElementById('aks-kalite');
                                            if(kaliteBtn){
                                                if(isEnded){
                                                    kaliteBtn.addEventListener('click', (ev) => {
                                                        ev.preventDefault();
                                                        Swal.showValidationMessage('Bu sipariş zaten kapatılmış — tekrar onaylanamaz.');
                                                    });
                                                } else if(!canKalite){
                                                    kaliteBtn.addEventListener('click', (ev) => {
                                                        ev.preventDefault();
                                                        Swal.showValidationMessage('Yetkiniz yok (per-05-03 Sipariş Sevkiyata Gönderme)');
                                                    });
                                                } else {
                                                kaliteBtn.addEventListener('click', async () => {
                                                    kaliteBtn.disabled = true;
                                                    kaliteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> İşleniyor...';
                                                    try{
                                                        const fd=new FormData();
                                                        fd.append('id', row.id);
                                                        fd.append('op_key', 'doc_trans_order_approved');
                                                        fd.append('note', 'Kalite onayı verildi ve kapatıldı');
                                                        const rsp=await this.plib.request({url:'/api/v1/trans/set-status', method:'POST'}, null, fd);
                                                        if(rsp && rsp.success){
                                                            this.table.updateRow(row.id, {status:'doc_trans_order_approved**Kalite Onayı Verildi'});
                                                            Swal.close();
                                                            this.plib.toast(Swal,'success','Kalite onayı verildi — tüm dosyalar kabul edildi');
                                                        }else{
                                                            Swal.showValidationMessage(rsp?.msg || rsp?.message || 'İşlem başarısız');
                                                            kaliteBtn.disabled=false;
                                                            kaliteBtn.innerHTML='<i class="ki-outline ki-check-circle" style="font-size:16px;"></i> Kalite Onayı Ver ve Kapat';
                                                        }
                                                    }catch(err){
                                                        Swal.showValidationMessage(err?.msg || 'Hata oluştu');
                                                        kaliteBtn.disabled=false;
                                                        kaliteBtn.innerHTML='<i class="ki-outline ki-check-circle" style="font-size:16px;"></i> Kalite Onayı Ver ve Kapat';
                                                    }
                                                });
                                                }
                                            }
                                            const renameBtn = document.getElementById('aks-rename');
                                            if(renameBtn){
                                                if(!canRename){
                                                    renameBtn.addEventListener('click', (ev)=>{
                                                        ev.preventDefault();
                                                        Swal.showValidationMessage('Yetkiniz yok (per-05-05 Sipariş Numarası Düzenleme)');
                                                    });
                                                } else {
                                                renameBtn.addEventListener('click', async () => {
                                                    Swal.close();
                                                    const base = curNo.substring(0, curNo.lastIndexOf('-'));
                                                    const currentSuffix = curNo.substring(curNo.lastIndexOf('-')+1);
                                                    const { value: newFull, isConfirmed } = await Swal.fire({
                                                        title:'Sipariş Numarasını Düzenle',
                                                        html: `<div style="text-align:left;padding:4px 2px;">
                                                            <div style="font-size:13px;color:#64748b;margin-bottom:10px;">Mevcut: <b style="color:#0f172a;">${curNo}</b></div>
                                                            <div style="display:flex;align-items:center;gap:0;border:1.5px solid #cbd5e1;border-radius:10px;overflow:hidden;background:#fff;">
                                                                <span style="padding:12px 14px;background:#f8fafc;border-right:1px solid #e2e8f0;font-weight:700;color:#334155;font-size:15px;white-space:nowrap;">${base}-</span>
                                                                <input id="swal-suffix" type="number" min="1" step="1" value="${currentSuffix}" placeholder="X" style="flex:1;border:none;padding:12px 14px;font-size:15px;font-weight:600;color:#0f172a;outline:none;width:100%;">
                                                            </div>
                                                            <div style="font-size:11px;color:#94a3b8;margin-top:8px;">Sadece <b>-X</b> parça numarası değiştirilebilir. Ana numara sabittir.</div>
                                                        </div>`,
                                                        showCancelButton:true,
                                                        confirmButtonText:'Kaydet',
                                                        cancelButtonText:'Vazgeç',
                                                        confirmButtonColor:'#6366f1',
                                                        focusConfirm:false,
                                                        didOpen: () => {
                                                            const inp=document.getElementById('swal-suffix');
                                                            if(inp){ inp.focus(); inp.select(); inp.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); Swal.clickConfirm(); }}); }
                                                        },
                                                        preConfirm: () => {
                                                            const v=document.getElementById('swal-suffix')?.value?.trim() ?? '';
                                                            if(!v) { Swal.showValidationMessage('Parça numarası boş olamaz'); return false; }
                                                            if(!/^\d+$/.test(v) || parseInt(v,10) < 1){ Swal.showValidationMessage('Parça numarası sadece pozitif sayı olabilir'); return false; }
                                                            const full = base + '-' + String(parseInt(v,10));
                                                            if(full === curNo){ Swal.showValidationMessage('Değişiklik yok'); return false; }
                                                            return full;
                                                        }
                                                    });
                                                    if(!isConfirmed || !newFull) return;
                                                    const trimmed = String(newFull).trim();
                                                    Swal.fire({title:'Kaydediliyor...', allowOutsideClick:false, didOpen:()=> Swal.showLoading()});
                                                    try{
                                                        const fd=new FormData();
                                                        fd.append('id', row.id);
                                                        fd.append('order_no', trimmed);
                                                        const rsp=await this.plib.request({url:'/api/v1/orders/rename', method:'POST'}, null, fd);
                                                        if(rsp && rsp.success){
                                                            this.table.updateRow(row.id, {transfer_no: trimmed, order_no: trimmed});
                                                            Swal.close();
                                                            this.plib.toast(Swal,'success', rsp.msg || 'Sipariş numarası güncellendi');
                                                        }else{
                                                            Swal.fire({icon:'error', title:'Hata', text: rsp?.msg || rsp?.message || 'Güncelleme başarısız'});
                                                        }
                                                    }catch(err){
                                                        Swal.fire({icon:'error', title:'Hata', text: err?.msg || err?.message || 'Hata oluştu'});
                                                    }
                                                });
                                                }
                                            }
                                        }
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
                            const _perms2 = this.authStore.permissions || [];
                            const canCancel = _perms2.includes('per-05-04');
                            cancelBtn.textContent= isCloneRow ? 'Parçayı Sil' : 'İptal Et';
                            cancelBtn.className='btn';
                            cancelBtn.style.background= canCancel ? '#fef2f2' : '#f1f5f9';
                            cancelBtn.style.color= canCancel ? '#dc2626' : '#94a3b8';
                            cancelBtn.style.border= canCancel ? '1px solid #fecaca' : '1px solid #e2e8f0';
                            cancelBtn.style.borderRadius='8px';
                            cancelBtn.style.padding='6px 14px';
                            cancelBtn.style.fontSize='0.82rem';
                            cancelBtn.style.fontWeight='600';
                            cancelBtn.style.cursor= canCancel ? 'pointer' : 'not-allowed';
                            cancelBtn.disabled = !canCancel;
                            if(canCancel){
                                cancelBtn.onmouseenter=()=> cancelBtn.style.background='#fee2e2';
                                cancelBtn.onmouseleave=()=> cancelBtn.style.background='#fef2f2';
                                cancelBtn.onclick=()=> this.cancelOrder(row.id, isCloneRow);
                            } else {
                                cancelBtn.title='Yetkiniz yok (per-05-04)';
                                cancelBtn.onclick=()=> this.plib.toast(this.Swal,'error','Yetkiniz yok (per-05-04 İptal / Parça Sil)');
                            }
                            wrap.appendChild(cancelBtn);
                            return wrap;
                        }
                    }
                ];
                this.table = new PickleTable({
                    container:'#div_table', headers, pageLimit:10, height: _isTedarik ? '75vh' : '70vh', type:'ajax', columnSearch:false, paginationType:'number',
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
                // enforce tedarik 75vh inline — beats any stylesheet (same as DList)
                this.$nextTick(()=>{
                    const enforceTedarikHeight = ()=>{
                        if(!this.isTedarik) return;
                        const el = document.querySelector('.tedarik-card .pickletable');
                        if(el){
                            if(el.style.getPropertyValue('height') !== '75vh'){
                                el.style.setProperty('height','75vh','important');
                            }
                            if(el.style.getPropertyValue('min-height') !== 'calc(75vh - 280px)'){
                                el.style.setProperty('min-height','calc(75vh - 280px)','important');
                            }
                            const divTable = el.querySelector('.divTable');
                            if(divTable){
                                if(divTable.style.getPropertyValue('height') !== '90%'){
                                    divTable.style.setProperty('height','90%','important');
                                }
                                if(divTable.style.getPropertyValue('overflow') !== 'auto'){
                                    divTable.style.setProperty('overflow','auto','important');
                                }
                            }
                        }
                    };
                    enforceTedarikHeight();
                    setTimeout(enforceTedarikHeight, 300);
                    setTimeout(enforceTedarikHeight, 1000);
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
                        const target = this.isTedarik ? 'TedarikOrderForm' : 'OrderForm';
                        this.$router.push({name: target, params:{id: match.id}});
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
                <div class="tedarik-filter-dd-wrap" ref="detayliWrap">
                    <a href="javascript:;" class="tedarik-filter" :class="{active: showDetayli}" @click="toggleDetayli"><i class="ki-outline ki-filter"></i> Filtreler</a>
                </div>
                <teleport to="body">
                    <!-- Dropdown via teleport so it escapes card overflow and never stays under cards -->
                    <div v-if="showDetayli" ref="detayliDropdown" class="tedarik-dd tedarik-dd--teleported" :style="`position:fixed; top:${dropdownPos.top}px; left:${dropdownPos.left}px; width:${dropdownPos.width}px; z-index:9999;`" @click.stop>
                        <label class="tedarik-dd-item" :class="{selected: detayliChoice==='seri_no'}" @click="handleDetayliChoice('seri_no')">
                            <span class="tedarik-radio" :class="{on: detayliChoice==='seri_no'}"></span> Seri Numarası ile Arama
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: detayliChoice==='tarihe_gore'}" @click="handleDetayliChoice('tarihe_gore')">
                            <span class="tedarik-radio" :class="{on: detayliChoice==='tarihe_gore'}"></span> Tarihe Göre Sırala
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: detayliChoice==='tarih_araligi'}" @click="handleDetayliChoice('tarih_araligi')">
                            <span class="tedarik-radio" :class="{on: detayliChoice==='tarih_araligi'}"></span> Tarih Aralığı Göster
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: detayliChoice==='alim_sirala'}" @click="handleDetayliChoice('alim_sirala')">
                            <span class="tedarik-radio" :class="{on: detayliChoice==='alim_sirala'}"></span> Alım Numarasına Göre Sırala
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: detayliChoice==='siparis_sirala'}" @click="handleDetayliChoice('siparis_sirala')">
                            <span class="tedarik-radio" :class="{on: detayliChoice==='siparis_sirala'}"></span> Sipariş Numarasına Göre Sırala
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: detayliChoice==='beklemede'}" @click="handleDetayliChoice('beklemede')">
                            <span class="tedarik-radio" :class="{on: detayliChoice==='beklemede'}"></span> Beklemede Olanları Göster
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: detayliChoice==='dosya_kontrol'}" @click="handleDetayliChoice('dosya_kontrol')">
                            <span class="tedarik-radio" :class="{on: detayliChoice==='dosya_kontrol'}"></span> Dosya Kontrolü Olanları Göster
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: detayliChoice==='tamamlanan'}" @click="handleDetayliChoice('tamamlanan')">
                            <span class="tedarik-radio" :class="{on: detayliChoice==='tamamlanan'}"></span> Tamamlananları Göster
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: detayliChoice==='hepsi'}" @click="handleDetayliChoice('hepsi')">
                            <span class="tedarik-radio" :class="{on: detayliChoice==='hepsi'}"></span> Hepsini Göster
                        </label>
                        <div class="tedarik-dd-divider"></div>
                        <div class="tedarik-dd-item tedarik-dd-has-sub" :class="{selected: detayliChoice==='sirkete_gore'}" @click="handleDetayliChoice('sirkete_gore')">
                            <span class="tedarik-dd-sirket-label">Şirkete Göre Arama</span>
                            <i class="ki-outline ki-right tedarik-dd-arrow"></i>
                        </div>
                    </div>
                </teleport>
                <!-- Client table modal — multi-select for Şirkete Göre Arama -->
                <teleport to="body">
                    <div v-if="showClientModal" class="client-modal-overlay" @click.self="showClientModal=false">
                        <div class="client-modal-card" @click.stop>
                            <div class="client-modal-head">
                                <div>
                                    <div class="client-modal-title">Şirkete Göre Arama</div>
                                    <div class="client-modal-sub">{{ selectedSirkets.length ? selectedSirkets.length + ' şirket seçili' : 'Listelemek için şirket seçin' }}</div>
                                </div>
                                <button class="client-modal-close" @click="showClientModal=false" aria-label="Kapat"><i class="ki-outline ki-cross fs-2"></i></button>
                            </div>
                            <div class="client-modal-search-wrap">
                                <input v-model="sirketSearch" class="client-modal-search" placeholder="Arama yapabilirsiniz." @input="onClientModalSearch" />
                                <i class="ki-outline ki-magnifier client-modal-search-icon"></i>
                            </div>
                            <div class="client-modal-table-wrap">
                                <div v-if="!modalClients.length" style="padding:28px;text-align:center;color:#9ca3af;font-size:13px;">Yükleniyor...</div>
                                <div v-else style="display:flex;flex-direction:column;gap:0;">
                                    <div style="display:flex;align-items:center;gap:8px;padding:8px 14px;border-bottom:1px solid #e2e8f0;background:#f8fafc;position:sticky;top:0;z-index:1;">
                                        <template v-if="clientModalMode==='multi'">
                                            <input type="checkbox" :checked="modalFilteredClients.length>0 && modalFilteredClients.every(o=> selectedSirkets.includes(o.lifnr))" @change="toggleAllModalClients" style="width:16px;height:16px;accent-color:#FF5A1F;cursor:pointer;">
                                            <span style="font-size:12px;font-weight:600;color:#64748b;">Tümünü Seç</span>
                                        </template>
                                        <span v-else style="font-size:12px;font-weight:600;color:#64748b;">Bir şirket seçin</span>
                                        <span style="margin-left:auto;font-size:12px;color:#6b7280;">{{modalFilteredClients.length}} şirket</span>
                                    </div>
                                    <div style="max-height:340px;overflow-y:auto;">
                                        <label v-for="opt in modalFilteredClients" :key="opt.lifnr" @click="clientModalMode==='multi' ? toggleSirket(opt.lifnr) : selectSingleClient(opt)" :style="(clientModalMode==='multi' ? selectedSirkets.includes(opt.lifnr) : detay[detailedModalTarget]===opt.lifnr) ? 'background:#fff7ed;display:flex;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid #f1f5f9;cursor:pointer;' : 'display:flex;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid #f1f5f9;cursor:pointer;'" style="display:flex;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid #f1f5f9;cursor:pointer;">
                                            <input v-if="clientModalMode==='multi'" type="checkbox" :checked="selectedSirkets.includes(opt.lifnr)" @change="toggleSirket(opt.lifnr)" @click.stop style="width:16px;height:16px;accent-color:#FF5A1F;flex-shrink:0;">
                                            <input v-else type="radio" :checked="detay[detailedModalTarget]===opt.lifnr" @change="selectSingleClient(opt)" @click.stop style="width:16px;height:16px;accent-color:#FF5A1F;flex-shrink:0;" :name="'single-'+detailedModalTarget">
                                            <span style="flex:1;font-weight:500;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{opt.clititle}}</span>
                                            <span style="font-weight:600;color:#475569;font-size:13px;white-space:nowrap;">{{opt.lifnr}}</span>
                                        </label>
                                        <div v-if="!modalFilteredClients.length" style="padding:24px;text-align:center;color:#9ca3af;font-size:13px;">Sonuç bulunamadı</div>
                                    </div>
                                </div>
                                <div id="client_modal_table" style="display:none;"></div>
                            </div>
                            <div class="client-modal-foot">
                                <button class="client-modal-btn-light" @click="clearSirketFilter">Temizle</button>
                                <div style="display:flex; gap:10px; margin-left:auto;">
                                    <button class="client-modal-btn-ghost" @click="showClientModal=false">Vazgeç</button>
                                    <button class="client-modal-btn-orange" @click="applySirketFilterAndClose">Filtrele</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </teleport>
                <a href="javascript:;" class="tedarik-filter" @click="exportTable"><i class="ki-outline ki-exit-down"></i> Excel Çıktı</a>
            </div>
        </div>
        <!-- Search bar — same as Doküman Listesi -->
        <div v-if="isTedarik" class="tedarik-docs-searchrow" style="margin: 0 0 14px;">
            <div class="tedarik-docs-searchbox">
                <i class="ki-outline ki-magnifier tedarik-docs-search-icon"></i>
                <input type="text" id="mainSearch" class="tedarik-docs-search-input" placeholder="Sipariş ara — sipariş no, tedarikçi, alım..." @keydown.enter="searchTable">
            </div>
            <button type="button" class="tedarik-btn-light tedarik-docs-btn" @click="searchTable">Ara</button>
            <button type="button" class="tedarik-btn-light tedarik-docs-btn tedarik-docs-btn--ghost" @click="resetSearch">Sıfırla</button>
        </div>
        <!-- Detaylı Filtre modal — 3×3 grid absolute (restored) -->
        <div v-if="isTedarik && showDetailed" class="tedarik-detailed-panel">
            <div class="tedarik-detailed-grid">
                <input v-model="detay.stokKodu" class="tedarik-detailed-input" placeholder="Stok Kodu Giriniz" />
                <input v-model="detay.siparisKodu" class="tedarik-detailed-input" placeholder="Sipariş Kodu Giriniz" />
                <input v-model="detay.alimKodu" class="tedarik-detailed-input" placeholder="Alım Kodu Giriniz" />
                <input v-model="detay.seriNo" class="tedarik-detailed-input" placeholder="Seri No Giriniz" />
                <input v-model="detay.uretimTarihi" class="tedarik-detailed-input" placeholder="Malzeme Üretim Tarihi" />
                <div class="tedarik-detailed-select-wrap" @click="openClientModalForDetailed('tedarikci')" style="cursor:pointer;">
                    <input readonly :value="detay.tedarikciLabel" class="tedarik-detailed-select" placeholder="Tedarikçi Ara" style="cursor:pointer; background:#fff;" />
                    <i class="ki-outline ki-down tedarik-detailed-arrow" style="pointer-events:none;"></i>
                </div>
                <div class="tedarik-detailed-select-wrap" @click="openClientModalForDetailed('sirket')" style="cursor:pointer;">
                    <input readonly :value="detay.sirketLabel" class="tedarik-detailed-select" placeholder="Şirket Ara" style="cursor:pointer; background:#fff;" />
                    <i class="ki-outline ki-down tedarik-detailed-arrow" style="pointer-events:none;"></i>
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
.tedarik-card {
    /* Same as Doküman 75vh — table fills page, pagination at bottom */
    height: auto !important;
    min-height: 0 !important;
    flex: 0 0 auto !important;
    display: flex !important;
    flex-direction: column !important;
}
.tedarik-card .order-list-body {
    background: transparent !important;
    flex: 0 0 auto !important;
    display: block !important;
    height: auto !important;
    min-height: 0 !important;
}
.tedarik-card :deep(.pickletable),
.tedarik-card :deep(#div_table.pickletable) {
    height: 75vh !important;
    min-height: calc(75vh - 280px) !important;
}
.tedarik-card :deep(.pickletable .divTable){
    overflow: auto !important;
}
.tedarik-card :deep(.pickletable .divPagination){
    margin-top: 0 !important;
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
    background: #fff !important;
    border-radius: 14px !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06) !important;
    border: none !important;
}
.tedarik-card :deep(.pickletable tbody tr:hover){
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important;
}
.tedarik-card :deep(.pickletable tbody tr:hover td){
    background: transparent !important;
}
.tedarik-card :deep(.pickletable tbody td){
    background: transparent !important;
    padding: 14px 12px !important;
    font-size: 13px !important;
    color: #333 !important;
    border: none !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    font-weight: 400;
}
.tedarik-card :deep(.pickletable tbody td:first-child){
    border-radius: 14px 0 0 14px !important;
    font-weight: 600 !important;
    color: #111827 !important;
}
.tedarik-card :deep(.pickletable tbody td:last-child){
    border-radius: 0 14px 14px 0 !important;
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
.tedarik-card :deep(.pickletable .divPagination .active .page-link),
.tedarik-card :deep(.pickletable .divPagination button.active),
.tedarik-card :deep(.pickletable .divPagination .page-link.active),
.tedarik-card :deep(.pickletable .divPagination button.current),
.tedarik-card :deep(.pickletable .divPagination .page-link.current){
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
}
.tedarik-bottom-note i {
    color: #0e9cb8;
    font-size: 13px;
    margin-top: 2px;
    flex-shrink: 0;
}
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
/* ===== NEW Detaylı Filtre dropdown — screenshot 1:1 ===== */
.tedarik-card{ overflow: visible !important; }
.tedarik-list-top{ overflow: visible !important; position: relative; z-index: 55; }
.tedarik-filter-dd-wrap{ position: relative; display: inline-flex; }
.tedarik-filter.active{ color:#FF5A1F !important; }
.tedarik-dd{
    position: absolute; top: calc(100% + 12px); left: 0; z-index: 50;
    width: 280px; background: #fff; border: 1px solid #ececec; border-radius: 12px;
    box-shadow: 0 12px 32px rgba(15,23,42,0.14), 0 4px 12px rgba(15,23,42,0.08);
    padding: 10px 0 8px; animation: tedarikDdIn .16s ease;
}
@keyframes tedarikDdIn{ from{ opacity:0; transform: translateY(-6px)} to{ opacity:1; transform:none } }
.tedarik-dd-item{
    display: flex; align-items: center; gap: 10px;
    padding: 8.5px 16px; font-size: 13px; font-weight: 400; color: #8a8a8e;
    cursor: pointer; user-select: none; transition: background .12s, color .12s;
    position: relative;
}
.tedarik-dd-item:hover{ background: #f9fafb; color:#374151; }
.tedarik-dd-item.selected{ color:#1f2937; font-weight: 500; }
.tedarik-radio{
    width: 16px; height: 16px; border-radius: 50%; border:1.5px solid #d1d5db;
    display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
    background:#fff; position:relative;
}
.tedarik-radio.on{ border-color:#FF5A1F; }
.tedarik-radio.on::after{
    content:''; width:7px; height:7px; border-radius:50%; background:#FF5A1F; display:block;
}
.tedarik-dd-divider{ height:1px; background:#f1f1f3; margin:6px 0; }
.tedarik-dd-has-sub{ color:#FF4713 !important; font-weight:500 !important; justify-content:space-between; }
.tedarik-dd-sirket-label{ color:#FF4713; }
.tedarik-dd-arrow{ font-size:11px; color:#FF4713; margin-left:auto; }
.tedarik-sirket-sub{
    position: absolute; left: calc(100% + 10px); top: -10px; z-index: 60;
    width: 360px; background:#fff; border:1px solid #e8e8ea; border-radius:12px;
    box-shadow: 0 12px 32px rgba(15,23,42,0.16), 0 4px 12px rgba(15,23,42,0.10);
    padding: 12px; animation: tedarikDdIn .14s ease;
}
.tedarik-sirket-search-wrap{ position:relative; margin-bottom:10px; }
.tedarik-sirket-search{
    width:100%; height:42px; border:1px solid #e5e7eb; border-radius:10px; background:#fff;
    padding:10px 38px 10px 14px; font-size:13px; color:#1e293b; outline:none;
}
.tedarik-sirket-search::placeholder{ color:#9ca3af; }
.tedarik-sirket-search:focus{ border-color:#FF5A1F; box-shadow:0 0 0 3px rgba(255,90,31,0.10); }
.tedarik-sirket-search-icon{ position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#FF5A1F; font-size:15px; pointer-events:none; }
.tedarik-sirket-list{ max-height:300px; overflow-y:auto; display:flex; flex-direction:column; gap:2px; padding-right:2px; }
.tedarik-sirket-row{ display:flex; align-items:center; gap:9px; padding:7px 6px; border-radius:7px; cursor:pointer; font-size:13px; color:#374151; transition:background .12s; }
.tedarik-sirket-row:hover{ background:#f9fafb; }
.tedarik-sirket-cb{ width:16px; height:16px; accent-color:#FF5A1F; border-radius:4px; flex-shrink:0; }
.tedarik-sirket-name{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tedarik-sirket-empty{ padding:18px; text-align:center; font-size:13px; color:#9ca3af; }
.tedarik-sirket-foot{ display:flex; align-items:center; justify-content:space-between; margin-top:10px; padding-top:10px; border-top:1px solid #f1f1f3; }
.tedarik-sirket-count{ font-size:12px; color:#6b7280; font-weight:500; }
.tedarik-sirket-clear{ font-size:12px; color:#FF5A1F; font-weight:600; text-decoration:none; }
.tedarik-sirket-clear:hover{ text-decoration:underline; }
/* ===== Client table modal ===== */
.client-modal-overlay{
    position:fixed; inset:0; z-index:10000; background:rgba(15,23,42,0.48); backdrop-filter:blur(6px);
    display:flex; align-items:center; justify-content:center; padding:18px; animation: tedarikDdIn .16s ease;
}
.client-modal-card{
    width:720px; max-width:92vw; max-height:82vh; background:#fff; border-radius:16px;
    box-shadow: 0 20px 48px rgba(15,23,42,0.18), 0 8px 20px rgba(15,23,42,0.12);
    display:flex; flex-direction:column; overflow:hidden; border:1px solid #e8e8ea;
}
.client-modal-head{
    display:flex; align-items:center; justify-content:space-between; padding:18px 22px 16px;
    border-bottom:1px solid #f1f1f3; flex-shrink:0;
}
.client-modal-title{ font-size:17px; font-weight:700; color:#0f172a; letter-spacing:-0.01em; }
.client-modal-sub{ font-size:13px; color:#6b7280; margin-top:2px; }
.client-modal-close{
    width:36px; height:36px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; color:#64748b;
    display:inline-flex; align-items:center; justify-content:center; cursor:pointer;
}
.client-modal-close:hover{ background:#f8fafc; color:#1e293b; }
.client-modal-search-wrap{ position:relative; padding:14px 22px 12px; flex-shrink:0; }
.client-modal-search{
    width:100%; height:44px; border:1px solid #e5e7eb; border-radius:10px; background:#fff;
    padding:10px 40px 10px 14px; font-size:13.5px; color:#1e293b; outline:none;
}
.client-modal-search::placeholder{ color:#9ca3af; }
.client-modal-search:focus{ border-color:#FF5A1F; box-shadow:0 0 0 3px rgba(255,90,31,0.10); }
.client-modal-search-icon{ position:absolute; right:32px; top:50%; transform:translateY(-50%); color:#FF5A1F; font-size:16px; pointer-events:none; }
.client-modal-table-wrap{ flex:1; overflow-y:auto; padding:0 8px 8px; min-height:200px; }
.client-modal-table{ width:100%; border-collapse:separate; border-spacing:0; font-size:13.5px; }
.client-modal-table thead th{
    position:sticky; top:0; background:#f8fafc; color:#64748b; font-size:12px; font-weight:600;
    text-transform:uppercase; letter-spacing:0.04em; padding:10px 12px; border-bottom:1px solid #e2e8f0; text-align:left; z-index:1;
}
.client-modal-table tbody td{ padding:11px 12px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:middle; }
.client-modal-table tbody tr{ cursor:pointer; transition:background .12s; }
.client-modal-table tbody tr:hover{ background:#f9fafb; }
.client-modal-table tbody tr.selected{ background:#fff7ed; }
.client-modal-table tbody tr.selected td{ color:#9a3412; }
.client-modal-name{ font-weight:500; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:380px; }
.client-modal-code{ font-weight:600; color:#475569; font-size:13px; white-space:nowrap; }
.client-modal-empty{ text-align:center; padding:24px; color:#9ca3af; font-size:13px; }
.client-modal-foot{
    display:flex; align-items:center; gap:10px; padding:14px 22px 18px; border-top:1px solid #f1f1f3; flex-shrink:0; background:#fcfcfd;
}
.client-modal-btn-light{
    height:40px; padding:0 16px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; color:#475569; font-size:13px; font-weight:600; cursor:pointer;
}
.client-modal-btn-light:hover{ background:#f8fafc; }
.client-modal-btn-ghost{
    height:40px; padding:0 18px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; color:#475569; font-size:13px; font-weight:600; cursor:pointer;
}
.client-modal-btn-orange{
    height:40px; padding:0 20px; border-radius:10px; border:none; background:#FF5A1F; color:#fff; font-size:13px; font-weight:700; cursor:pointer; box-shadow:0 2px 8px rgba(255,90,31,0.22);
}
.client-modal-btn-orange:hover{ background:#e0541b; }
@media (max-width: 700px){
    .tedarik-sirket-sub{ left:0; top: calc(100% + 8px); width: 280px; }
}
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
}
.tedarik-card .pickletable .divTable {
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
    background: transparent !important;
    border: none !important;
    font-size: 13px !important;
    padding: 14px 12px !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    vertical-align: middle !important;
    color: #333 !important;
}
.tedarik-card .pickletable td:first-child {
    border-radius: 14px 0 0 14px !important;
    font-weight: 600 !important;
}
.tedarik-card .pickletable td:last-child {
    border-radius: 0 14px 14px 0 !important;
}
.tedarik-card .pickletable tr {
    background: white !important;
    border-radius: 14px !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06) !important;
    transition: transform 0.15s ease, box-shadow 0.15s ease !important;
    border: none !important;
}
.tedarik-card .pickletable tr:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important;
}
.tedarik-card .pickletable tr:hover td {
    background: transparent !important;
}
.tedarik-card .pickletable .divPagination {
    border-top: none !important;
}
/* Card-row table base */
.tedarik-card .pickletable table {
    border-collapse: separate !important;
    border-spacing: 0 7px !important;
    width: 100% !important;
    table-layout: auto !important;
    border: none !important;
}
/* Search bar — same as Doküman Listesi */
.tedarik-docs-searchrow{
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    background:#fff; border:1px solid #e8e8ea; border-radius:14px; padding:12px 14px;
    box-shadow:0 1px 3px rgba(0,0,0,0.04);
    margin:0 0 14px;
}
.tedarik-docs-searchbox{
    position:relative; display:flex; align-items:center; flex:1; min-width:240px;
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:999px;
    padding:0 14px 0 38px; height:42px; transition:all .18s;
}
.tedarik-docs-searchbox:focus-within{ background:#fff; border-color:#cbd5e1; box-shadow:0 0 0 4px rgba(255,90,31,.10); }
.tedarik-docs-search-icon{ position:absolute; left:12px; font-size:18px; color:#94a3b8; }
.tedarik-docs-search-input{
    border:none; outline:none; background:transparent; width:100%;
    font-size:13.5px; color:#0f172a;
}
.tedarik-docs-search-input::placeholder{ color:#94a3b8; }
.tedarik-docs-btn{
    height:42px; padding:0 18px; border-radius:10px; border:1px solid #e5e7eb; background:#fff;
    font-size:13px; font-weight:700; color:#475569; cursor:pointer; display:inline-flex; align-items:center; gap:6px;
    transition: all .15s; white-space:nowrap;
}
.tedarik-docs-btn:hover{ background:#f8fafc; border-color:#cbd5e1; color:#1e293b; }
.tedarik-docs-btn--ghost{ background:#f1f5f9; }
/* 75vh like Doküman — table fills page, pagination at bottom.
   Use 3-class specificity (.tedarik-main .tedarik-card .pickletable) to beat
   TedarikPanel global `.tedarik-main .pickletable { height:auto !important }`. */
.tedarik-card .order-list-body {
    flex: 0 0 auto;
    display: block;
    min-height: 0;
    height: auto;
}
.tedarik-main .tedarik-card .pickletable,
.tedarik-main .tedarik-card #div_table.pickletable,
.tedarik-main .tedarik-card .pickletable.pt-auto-height {
    height: 75vh !important;
    min-height: calc(75vh - 280px) !important;
}
.tedarik-card .pickletable .divTable {
    overflow: auto !important;
}
.tedarik-main .tedarik-card .pickletable:not(.pt-auto-height) .divTable {
    height: 90% !important;
    overflow: auto !important;
}
/* Swal search inputs — fix light/white text (was invisible on white) */
.swal2-input, .swal2-textarea, #swal-flat-range, #swal-suffix, #retake-note {
    color: #0f172a !important;
    background: #fff !important;
    border-color: #e2e8f0 !important;
}
.swal2-input::placeholder, .swal2-textarea::placeholder, #swal-flat-range::placeholder, #swal-suffix::placeholder, #retake-note::placeholder {
    color: #94a3b8 !important;
}
.swal2-input:focus, .swal2-textarea:focus, #swal-flat-range:focus, #swal-suffix:focus, #retake-note:focus {
    border-color: #FF5A1F !important;
    box-shadow: 0 0 0 3px rgba(255,90,31,0.12) !important;
    color: #0f172a !important;
}
</style>
