
<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { reset, wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import dayjs from 'dayjs';
    import flatpickr from 'flatpickr';
    import { Turkish } from 'flatpickr/dist/l10n/tr.js';
    import 'flatpickr/dist/flatpickr.min.css';
    flatpickr.localize(Turkish);

    export default {
        breadcrumbs: {
            list: [ { title: 'Belgeler', path: '/coalpanel/documents' } ],
            title: 'Belgeler'
        },
        setup() {
            return {
                useNavigationStore,
                useAuthStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTestTable();
            setTimeout(() => {
                this.navigationStore.toggle(false);
                this.handleResponsiveTable();
            }, 400);
            if(this.isTedarik){
                this.$nextTick(()=> document.addEventListener('click', this.handleOutsideClick));
            }
        },
        beforeUnmount(){
            try{
                if(this._heightObs) this._heightObs.disconnect();
                if(this._enforceTedarikHeight) window.removeEventListener('resize', this._enforceTedarikHeight);
            }catch(e){}
            document.removeEventListener('click', this.handleOutsideClick);
        },  
        computed: {
            isTedarik() { return this.$route.path.startsWith('/tedarikpanel'); },
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
                useAuthStore    : useAuthStore(),
                showFiltre: false,
                filtreChoice: '',
                sirketSearch: '',
                selectedSirkets: [],
                showClientModal: false,
                clientModalMode: 'multi',
                clientTable: null,
                modalClients: [],
                dropdownPos: { top: 0, left: 0, width: 280 },
                clientOptions: [],
            }
        },
        methods: {
            handleResponsiveTable(){
                const container = document.getElementById('div_table');
                if(!container) return;
                const table = container.querySelector('.pickletable table');
                if(!table) return;
                const updateResponsive = () => {
                    const isMobile = window.innerWidth < 768;
                    if(isMobile){
                        table.style.minWidth = '100%';
                        table.style.width = 'auto';
                    } else {
                        table.style.minWidth = '760px';
                        table.style.width = '100%';
                    }
                };
                updateResponsive();
                window.addEventListener('resize', updateResponsive);
            },
            toggleFiltre(e){
                if(e) e.stopPropagation();
                this.showFiltre = !this.showFiltre;
                if(this.showFiltre) this.$nextTick(()=> this.updateDropdownPos());
            },
            updateDropdownPos(){
                try{
                    const wrap=this.$refs.filtreWrap;
                    if(!wrap) return;
                    const rect=wrap.getBoundingClientRect();
                    let left=rect.left;
                    const width=280;
                    if(left + width > window.innerWidth - 12) left = window.innerWidth - width - 12;
                    if(left < 12) left = 12;
                    this.dropdownPos = { top: rect.bottom + 10, left, width };
                }catch(e){}
            },
            handleOutsideClick(e){
                const wrap=this.$refs.filtreWrap;
                const dd=this.$refs.filtreDropdown;
                if(!wrap) return;
                const insideWrap = wrap.contains(e.target);
                const insideDd = dd && dd.contains(e.target);
                if(!insideWrap && !insideDd){
                    this.showFiltre=false;
                }
            },
            openClientModal(mode='multi'){
                this.clientModalMode = mode;
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
            onClientModalSearch(){
                // filtering is via computed modalFilteredClients on sirketSearch — no PickleTable needed
            },
            async buildClientTable(){
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
            },
            toggleSirket(val){
                const idx=this.selectedSirkets.indexOf(val);
                if(idx>-1) this.selectedSirkets.splice(idx,1);
                else this.selectedSirkets.push(val);
                if(!this.showClientModal) this.applySirketFilter();
            },
            applySirketFilter(){
                if(this.selectedSirkets.length===0){
                    this.table.setFilter([]);
                    return;
                }
                const v=this.selectedSirkets.join(',');
                this.table.setFilter([{key:'sirket', type:'like', value: v}]);
            },
            clearSirketFilter(){
                this.selectedSirkets=[];
                this.sirketSearch='';
                this.table.setFilter([]);
            },
            applySirketFilterAndClose(){
                this.applySirketFilter();
                this.showClientModal=false;
                if(this.selectedSirkets.length) this.plib.toast(Swal,'success', `${this.selectedSirkets.length} şirket için filtrelendi`);
            },
            toggleAllModalClients(e){
                const checked=e.target.checked;
                if(checked){
                    const vals=this.modalFilteredClients.map(o=> o.lifnr);
                    vals.forEach(v=>{ if(!this.selectedSirkets.includes(v)) this.selectedSirkets.push(v); });
                } else {
                    const filteredVals=new Set(this.modalFilteredClients.map(o=> o.lifnr));
                    this.selectedSirkets=this.selectedSirkets.filter(v=> !filteredVals.has(v));
                }
            },
            async handleFiltreChoice(val){
                this.filtreChoice=val;
                let shouldClose=true;
                switch(val){
                    case 'seri_no': {
                        const {value: seri}=await Swal.fire({title:'Seri Numarası ile Arama', input:'text', inputPlaceholder:'Seri no giriniz', showCancelButton:true, confirmButtonText:'Ara', cancelButtonText:'Vazgeç', confirmButtonColor:'#FF5A1F'});
                        if(seri && seri.trim()) this.table.setFilter([{key:'seri_no', type:'like', value: seri.trim()}]);
                        break;
                    }
                    case 'tarihe_gore': {
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
                                flatpickr(el, { mode:'range', dateFormat:'Y-m-d', allowInput:true, locale: Turkish, onReady:(_,__,fp)=>{ setTimeout(()=> fp.open(), 80); } });
                            },
                            preConfirm:()=>{
                                const v=document.getElementById('swal-flat-range')?.value?.trim() || '';
                                if(!v){ Swal.showValidationMessage('Lütfen tarih aralığı seçin'); return false; }
                                return v;
                            }
                        });
                        if(rangeVal){
                            let v = rangeVal.trim();
                            if(v.includes(' to ')){ const [s,e]=v.split(' to ').map(s=>s.trim()); v = (s||'') + '|' + (e||''); }
                            else if(v.includes(' — ')){ const [s,e]=v.split(' — ').map(s=>s.trim()); v = (s||'') + '|' + (e||''); }
                            else if(v.includes(' - ')){ const [s,e]=v.split(' - ').map(s=>s.trim()); v = (s||'') + '|' + (e||''); }
                            else { v = v + '|' + v; }
                            this.table.setFilter([{key:'tarih_araligi', type:'like', value: v }]);
                            this.plib.toast(Swal,'success','Tarih aralığı uygulandı');
                        }
                        break;
                    }
                    case 'siparis_sirala': {
                        this.table.getData({key:'group_key', type:'string', style:'asc'});
                        this.plib.toast(Swal,'success','Sipariş numarasına göre sıralandı');
                        break;
                    }
                    case 'beklemede': {
                        this.table.setFilter([{key:'file_status', type:'=', value: 'doc_file_waiting'}]);
                        break;
                    }
                    case 'onaylandi': {
                        this.table.setFilter([{key:'file_status', type:'=', value: 'doc_file_accepted'}]);
                        break;
                    }
                    case 'reddedildi': {
                        this.table.setFilter([{key:'file_status', type:'=', value: 'doc_file_rejected'}]);
                        break;
                    }
                    case 'hepsi': {
                        this.selectedSirkets=[];
                        this.sirketSearch='';
                        this.table.setFilter([]);
                        this.plib.toast(Swal,'success','Tüm belgeler gösteriliyor');
                        break;
                    }
                    case 'sirkete_gore': {
                        this.openClientModal('multi');
                        shouldClose=true;
                        break;
                    }
                }
                if(shouldClose) setTimeout(()=>{ this.showFiltre=false; }, 200);
            },
            parseRowStatus(lastStatus){
                try {
                    return JSON.parse(lastStatus || '{}');
                } catch (e) {
                    return {};
                }
            },
            exportTable(){
                this.plib.openTab('POST', '/api/v1/export/documents', this.table.currentFilter,'_blank');
            },
            createDetailModalContent(rowData){
                const makeLine = (labelText, valueNodeOrText) => {
                    const line = document.createElement('div');
                    line.classList.add('d-flex','justify-content-between','w-100','mb-2');
                    const key = document.createElement('span');
                    key.classList.add('text-dark','fw-semibold');
                    key.textContent = labelText;
                    const value = document.createElement('span');
                    value.classList.add('text-end','text-muted');
                    if (valueNodeOrText instanceof Node) {
                        value.appendChild(valueNodeOrText);
                    } else {
                        value.textContent = valueNodeOrText;
                    }
                    line.appendChild(key);
                    line.appendChild(value);
                    return line;
                };
                const container = document.createElement('div');
                container.classList.add('bg-white','border','border-1','rounded','p-3','shadow-sm');
                // İlişki: item files → product_name/title, order files → order_no (title is empty for orders)
                const iliskiVal = rowData.product_name || rowData.title || rowData.order_no || rowData.buying_no || '—';
                container.appendChild(makeLine('İlişki:', iliskiVal));
                // Eklenme Tarihi: use pre-formatted _created_at_fmt (file's real date), not overwritten EAV created_at (DD/MM/YYYY)
                const tarihVal = rowData._created_at_fmt && rowData._created_at_fmt !== '—' ? rowData._created_at_fmt : (()=>{ const d=dayjs(rowData._file_created_at || rowData.created_at); return d.isValid()?d.format('DD/MM/YYYY HH:mm'):'—'; })();
                container.appendChild(makeLine('Eklenme Tarihi:', tarihVal));
                const statusData = this.parseRowStatus(rowData.last_status);
                const statusTitle = statusData?.title ?? 'Bekleniyor..';
                const statusBadge = document.createElement('span');
                statusBadge.classList.add('badge','text-dark');
                statusBadge.textContent = statusTitle;
                container.appendChild(makeLine('Güncel Durum:', statusBadge));
                container.appendChild(makeLine('Kontrol Eden:', statusData?.name ?? '—'));
                let noteText = '';
                try { noteText = JSON.parse(statusData?.note ?? '{}')?.note ?? ''; } catch (e) { noteText = ''; }
                const noteEl = document.createElement('span');
                noteEl.classList.add('text-muted','small');
                noteEl.textContent = noteText || 'Yok';
                container.appendChild(makeLine('Durum Notu:', noteEl));
                const versionsWrap = document.createElement('div');
                versionsWrap.classList.add('w-100','mt-3');
                const label = document.createElement('h6');
                label.classList.add('mb-2','pt-2','border-top','text-secondary','small','fw-semibold');
                label.textContent = 'Geçmiş Versiyonlar';
                versionsWrap.appendChild(label);
                const ul = document.createElement('ul');
                ul.classList.add('list-group','w-100','mt-2');
                let oldVersions = [];
                try { oldVersions = JSON.parse(rowData?.old_versions ?? '[]') || []; } catch(e){ oldVersions = []; }
                if(oldVersions.length){
                    oldVersions.sort((a,b) => new Date(b.created_at) - new Date(a.created_at));
                    oldVersions.forEach((version, i) => {
                        const li = document.createElement('li');
                        li.classList.add('list-group-item','p-1');
                        const a = document.createElement('a');
                        a.classList.add('text-decoration-none','d-flex','align-items-center','justify-content-center');
                        a.href = '/order-file/'+(version.qnid ?? version.description);
                        a.target = '_blank';
                        a.innerHTML = (i == 0 ? '<i class="ki-outline fs-2 ki-arrow-right" style="color:tomato"></i>' : '') + dayjs(version.created_at).format('DD/MM/YYYY HH:mm');
                        li.appendChild(a);
                        ul.appendChild(li);
                    });
                } else {
                    const li = document.createElement('li');
                    li.classList.add('list-group-item','p-1','text-muted');
                    li.textContent = 'Yok';
                    ul.appendChild(li);
                }
                versionsWrap.appendChild(ul);
                container.appendChild(versionsWrap);
                return container;
            },
            showDetailModal(rowData){
                const container = this.createDetailModalContent(rowData);
                Swal.fire({
                    showConfirmButton: false,
                    showCloseButton: true,
                    didOpen: () => {
                        const htmlContainer = Swal.getHtmlContainer();
                        if(htmlContainer){
                            htmlContainer.innerHTML = '';
                            htmlContainer.appendChild(container);
                        }
                    }
                });
            },
            async handleRetake(rowData){
                const { value: note, isConfirmed } = await Swal.fire({
                    title: '',
                    html: `<div style="display:flex; flex-direction:column; align-items:center; gap:14px; padding:6px 4px 2px; text-align:center;">
                        <div style="width:64px; height:64px; border-radius:50%; background: linear-gradient(135deg, #fff7ed, #ffedd5); border:1.5px solid #fed7aa; display:flex; align-items:center; justify-content:center; color:#ea580c; font-size:28px; box-shadow:0 4px 16px rgba(234,88,12,0.12);"><i class="ki-outline ki-arrows-loop"></i></div>
                        <div style="font-size:18px; font-weight:800; color:#0f172a; letter-spacing:-0.01em;">Yeniden Talep Et</div>
                        <div style="font-size:13.5px; color:#475569; line-height:1.6; max-width:360px;">Bu belge <b style="color:#9a3412; background:#fff7ed; padding:2px 6px; border-radius:6px; border:1px solid #fed7aa;">Reddedildi</b> olarak işaretlenecek ve tedarikçiden yeniden yüklemesi istenecek.</div>
                        <div style="width:100%; text-align:left; margin-top:4px;">
                            <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:6px; display:flex; align-items:center; gap:6px;"><i class="ki-outline ki-notepad" style="color:#94a3b8;"></i> Not <span style="font-weight:400; color:#94a3b8;">(opsiyonel)</span></label>
                            <textarea id="retake-note" placeholder="Reddetme nedenini yazın... Örn: Görsel bulanık, imza eksik" style="width:100%; min-height:110px; border:1.5px solid #e2e8f0; border-radius:12px; padding:12px 14px; font-size:13.5px; outline:none; resize:vertical; background:#f8fafc; transition:border-color .15s; font-family:inherit;"></textarea>
                            <div style="font-size:11px; color:#94a3b8; margin-top:6px; display:flex; align-items:center; gap:4px;"><i class="ki-outline ki-information-2" style="font-size:12px;"></i> Tedarikçi bu notu detayda görecek.</div>
                        </div>
                    </div>`,
                    showCancelButton: true,
                    confirmButtonText: 'Reddet ve Talep Et',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#e2e8f0',
                    customClass: { popup:'swal-retake', confirmButton:'swal-retake-confirm', cancelButton:'swal-retake-cancel' },
                    focusConfirm: false,
                    preConfirm: () => document.getElementById('retake-note')?.value?.trim() ?? ''
                });
                if(!isConfirmed) return;
                const n = (note || '').trim();
                const fd=new FormData();
                fd.append('id', rowData.id);
                fd.append('op_key', 'doc_file_rejected');
                fd.append('note', n);
                Swal.fire({ title:'İşleniyor...', allowOutsideClick:false, didOpen:()=> Swal.showLoading() });
                try{
                    const rsp=await this.plib.request({ url:'/api/v1/trans/set-file-status', method:'POST' }, null, fd);
                    if(rsp && rsp.success){
                        const upd={ op_key:'doc_file_rejected', title: rsp.data || 'Reddedildi', note: n };
                        try{ this.table.updateRow(rowData.id, { last_status: JSON.stringify(upd) }); }catch(e){}
                        Swal.close();
                        this.plib.toast(Swal,'success','Yeniden talep edildi — belge reddedildi');
                    } else {
                        Swal.fire({ icon:'error', title:'Hata', text: rsp?.msg || rsp?.message || 'İşlem başarısız' });
                    }
                }catch(e){
                    Swal.fire({ icon:'error', title:'Hata', text: e?.msg || e?.message || String(e) });
                }
            },
            searchTable(){
                this.table.setFilter(
                    [{
                        key   : 'all',
                        type  : '=',
                        value : document.getElementById('mainSearch').value.trim()
                    }]
                );
            },
            resetSearch(){
                document.getElementById('mainSearch').value = '';
                this.table.setFilter([]);
            },
            formatDocumentCard(rowData){
                const fileType = rowData.file_type || '-';
                const relationLabel = rowData.relation_type === 'op-doc-client' ? 'Müşteri' : rowData.relation_type === 'op-doc-offer' ? 'Teklif' : 'Belge';
                const relationValue = rowData.relation_type === 'op-doc-offer' ? rowData.relation_qnid : (rowData.product_name || rowData.title || rowData.order_no || '-');
                const statusData = this.parseRowStatus(rowData.last_status);
                const statusLabel = statusData?.title || 'Bekleniyor..';
                const card = document.createElement('div');
                card.classList.add('document-card');
                const header = document.createElement('div');
                header.classList.add('document-card__header');
                const headerLeft = document.createElement('div');
                const badge = document.createElement('div');
                badge.classList.add('document-card__badge');
                badge.textContent = fileType;
                const title = document.createElement('h4');
                title.classList.add('document-card__title');
                title.textContent = relationValue || '-';
                const subtitle = document.createElement('p');
                subtitle.classList.add('document-card__subtitle');
                subtitle.textContent = relationLabel;
                headerLeft.appendChild(badge);
                headerLeft.appendChild(title);
                headerLeft.appendChild(subtitle);
                const statusBadge = document.createElement('span');
                statusBadge.classList.add('document-card__status');
                statusBadge.textContent = statusLabel;
                if (!this.isTedarik && this.useAuthStore().permissions?.includes('per-07-02')) {
                    statusBadge.classList.add('document-card__status--clickable');
                    statusBadge.title = 'Detayları Gör';
                    statusBadge.onclick = () => this.showDetailModal(rowData);
                }
                header.appendChild(headerLeft);
                header.appendChild(statusBadge);
                card.appendChild(header);
                const body = document.createElement('div');
                body.classList.add('document-card__body');
                const rows = [
                    { label: 'İlişki', value: relationValue },
                    { label: 'Eklenme Tarihi', value: rowData._created_at_fmt || (()=>{ const d=dayjs(rowData._file_created_at || rowData.created_at); return d.isValid()?d.format('DD/MM/YYYY HH:mm'):'-'; })() },
                    { label: 'Durum', value: statusLabel }
                ];
                rows.forEach(item => {
                    const row = document.createElement('div');
                    row.classList.add('document-card__row');
                    const label = document.createElement('span');
                    label.classList.add('document-card__label');
                    label.textContent = item.label;
                    const value = document.createElement('span');
                    value.classList.add('document-card__value');
                    value.textContent = item.value;
                    row.appendChild(label);
                    row.appendChild(value);
                    body.appendChild(row);
                });
                card.appendChild(body);
                const footer = document.createElement('div');
                footer.classList.add('document-card__footer');
                const detailBtn = document.createElement('button');
                detailBtn.classList.add('document-card__action-btn');
                detailBtn.type = 'button';
                detailBtn.innerHTML = '<i class="ki-outline ki-magnifier"></i> Detay';
                detailBtn.onclick = () => {
                    if(this.isTedarik){
                        this.$router.push({ name:'TedarikDForm', params:{ id: rowData.id }});
                    } else {
                        this.showDetailModal(rowData);
                    }
                };
                footer.appendChild(detailBtn);
                const openBtn = document.createElement('button');
                openBtn.classList.add('document-card__action-btn');
                openBtn.type = 'button';
                openBtn.innerHTML = '<i class="ki-outline ki-eye"></i> Aç';
                openBtn.onclick = () => window.open('/order-file/'+(rowData?.id ?? rowData?.file), '_blank');
                footer.appendChild(openBtn);
                // Yeniden Talep Et — reject file (admin)
                if(this.useAuthStore().permissions?.includes('per-07-02')){
                    const retakeBtn=document.createElement('button');
                    retakeBtn.classList.add('document-card__action-btn');
                    retakeBtn.type='button';
                    retakeBtn.innerHTML='<i class="ki-outline ki-arrows-loop"></i> Yeniden Talep Et';
                    retakeBtn.style.background='#fff7ed'; retakeBtn.style.borderColor='#fed7aa'; retakeBtn.style.color='#9a3412';
                    retakeBtn.onclick=()=> this.handleRetake(rowData);
                    footer.appendChild(retakeBtn);
                }
                // İlişki button — panel-aware + order support
                const showIliski = this.isTedarik || this.useAuthStore().permissions?.includes('per-07');
                if (showIliski) {
                    const viewFormBtn = document.createElement('button');
                    viewFormBtn.classList.add('document-card__action-btn');
                    viewFormBtn.type = 'button';
                    viewFormBtn.innerHTML = '<i class="ki-outline ki-arrow-right"></i> İlişki';
                    viewFormBtn.onclick = () => {
                        const isTed = this.isTedarik;
                        switch(rowData.relation_type){
                            case 'op-doc-client':
                                this.$router.push({ name: 'CForm', params: { id: rowData.relation_qnid } });
                                break;
                            case 'op-doc-offer':
                                this.$router.push({ name: isTed ? 'TedarikOrderForm' : 'OForm', params: { id: rowData.relation_qnid } });
                                break;
                            case 'op-doc-order':
                            case 'op-doc-order-item':
                                this.$router.push({ name: isTed ? 'TedarikOrderForm' : 'OrderForm', params: { id: rowData.relation_qnid } });
                                break;
                            default:
                                // fallback: try order if product_name exists
                                if(rowData.relation_qnid){
                                    const rName = isTed ? 'TedarikOrderForm' : 'OrderForm';
                                    this.$router.push({ name: rName, params: { id: rowData.relation_qnid } });
                                }
                                break;
                        }
                    };
                    footer.appendChild(viewFormBtn);
                }
                // Devre Dışı — admin only, never in tedarik
                if (!this.isTedarik && this.useAuthStore().permissions?.includes('per-07')) {
                    const disableBtn = document.createElement('button');
                    disableBtn.classList.add('document-card__action-btn');
                    disableBtn.type = 'button';
                    disableBtn.innerHTML = '<i class="ki-outline ki-trash"></i> Devre Dışı';
                    disableBtn.onclick = () => {
                        Swal.fire({
                            title: 'Emin misiniz?',
                            text: 'Dosya sistemde kalmaya devam edecek ama deaktif edilecek bu sayede listede görünmeyecek!',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Evet, devre dışı bırak',
                            cancelButtonText: 'İptal'
                        }).then(async (result) => {
                            if(result.isConfirmed){
                                const envelope = new FormData();
                                envelope.append('id', rowData.id);
                                try {
                                    const rsp = await this.plib.request({
                                        url      : '/api/v1/trans/disable-document',
                                        method   : 'POST',
                                    },null,envelope);
                                    if(rsp.success){
                                        this.table.removeRow(rowData.id);
                                        this.plib.toast(this.Swal,'success','Dosya devre dışı bırakıldı');
                                    }else{
                                        Swal.fire('Hata', rsp.message || 'İşlem başarısız', 'error');
                                    }
                                } catch (error) {
                                    Swal.fire('Hata', 'Bir hata oluştu', 'error');
                                }
                            }
                        });
                    };
                    footer.appendChild(disableBtn);
                }
                card.appendChild(footer);
                return card;
            },
            buildTestTable(){
                const headers = [
                    {
                        title : ' ',
                        key   : 'document_card',
                        order : false,
                        type  : 'string',
                        columnFormatter : (elm,rowData) => this.formatDocumentCard(rowData)
                    },{
                        title : 'Belge Başlık',
                        key   : 'file_type',
                        order : true,
                        width : this.isTedarik ? '180px' : '18%',
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            if(this.isTedarik){
                                const wrap=document.createElement('div');
                                wrap.style.display='flex';
                                wrap.style.alignItems='center';
                                wrap.style.gap='10px';
                                const iconBox=document.createElement('div');
                                iconBox.style.width='36px'; iconBox.style.height='36px'; iconBox.style.borderRadius='10px'; iconBox.style.display='inline-flex'; iconBox.style.alignItems='center'; iconBox.style.justifyContent='center'; iconBox.style.flexShrink='0'; iconBox.style.border='1px solid #e2e8f0';
                                const ft=(columnData||'').toLowerCase();
                                let icon='ki-document'; let bg='#f1f5f9'; let col='#475569'; let bd='#e2e8f0';
                                if(ft.includes('test')){ icon='ki-flask'; bg='#fef3ff'; col='#a21caf'; bd='#f5d0fe'; }
                                else if(ft.includes('cins')){ icon='ki-chart-simple'; bg='#fff7ed'; col='#c2410c'; bd='#fed7aa'; }
                                else if(ft.includes('kabul')){ icon='ki-clipboard'; bg='#ecfdf5'; col='#047857'; bd='#a7f3d0'; }
                                iconBox.style.background=bg; iconBox.style.color=col; iconBox.style.borderColor=bd;
                                iconBox.innerHTML=`<i class="ki-outline ${icon}" style="font-size:18px;"></i>`;
                                const span=document.createElement('span');
                                span.textContent=columnData || '—';
                                span.title=columnData || '—';
                                span.style.fontWeight='700';
                                span.style.color='#0f172a';
                                span.style.fontSize='13px';
                                span.style.display='inline-block';
                                span.style.maxWidth='140px';
                                span.style.overflow='hidden';
                                span.style.textOverflow='ellipsis';
                                span.style.whiteSpace='nowrap';
                                span.style.verticalAlign='middle';
                                wrap.appendChild(iconBox);
                                wrap.appendChild(span);
                                return wrap;
                            }
                            const wrap = document.createElement('div');
                            wrap.classList.add('dlist-filecell');
                            const iconBox = document.createElement('div');
                            iconBox.classList.add('dlist-filecell__icon');
                            const ft = (columnData||'').toLowerCase();
                            let icon = 'ki-document';
                            if(ft.includes('test')) icon = 'ki-flask';
                            else if(ft.includes('cins')) icon = 'ki-chart-simple';
                            else if(ft.includes('kabul')) icon = 'ki-clipboard';
                            iconBox.innerHTML = `<i class="ki-outline ${icon} fs-2"></i>`;
                            if(ft.includes('test')) iconBox.classList.add('is-test');
                            else if(ft.includes('cins')) iconBox.classList.add('is-cins');
                            else if(ft.includes('kabul')) iconBox.classList.add('is-kabul');
                            const textBox = document.createElement('div');
                            textBox.classList.add('dlist-filecell__text');
                            const title = document.createElement('div');
                            title.classList.add('dlist-filecell__title');
                            title.textContent = columnData || '—';
                            textBox.appendChild(title);
                            const sub = document.createElement('div');
                            sub.classList.add('dlist-filecell__sub');
                            sub.textContent = rowData.file_qnid ? rowData.file_qnid.slice(0,8)+'…' : 'Belge';
                            wrap.appendChild(iconBox);
                            wrap.appendChild(textBox);
                            return wrap;
                        }
                    },{
                        title : 'Sipariş / İlişki',
                        key   : 'group_key',
                        order : true,
                        width : this.isTedarik ? '170px' : '27%',
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const orderCode = rowData.group_key || rowData.order_no || columnData || '';
                            let iliski = '';
                            let iliskiCls = 'is-product';
                            switch(rowData.relation_type){
                                case 'op-doc-client':
                                    iliski = rowData.title || columnData || '';
                                    iliskiCls='is-client';
                                    break;
                                case 'op-doc-offer':
                                    iliski = rowData.relation_qnid || '';
                                    iliskiCls='is-offer';
                                    break;
                                default:
                                    if(rowData.product_name) { iliski=rowData.product_name; iliskiCls='is-product'; }
                                    else if(rowData.order_no) { iliski=rowData.order_no; iliskiCls='is-product'; }
                                    else if(columnData && columnData !== '') { iliski=columnData; iliskiCls='is-product'; }
                                    else { iliski=''; iliskiCls='is-empty'; }
                            }
                            if(this.isTedarik){
                                const display = (orderCode||'').trim() || (iliski||'').trim() || '—';
                                const span=document.createElement('span');
                                span.textContent=display;
                                span.title=display;
                                span.style.color='#334155';
                                span.style.display='inline-block';
                                span.style.maxWidth='160px';
                                span.style.overflow='hidden';
                                span.style.textOverflow='ellipsis';
                                span.style.whiteSpace='nowrap';
                                span.style.verticalAlign='middle';
                                return span;
                            }
                            const wrap=document.createElement('div');
                            wrap.classList.add('dlist-merged');
                            wrap.style.display='flex'; wrap.style.alignItems='center'; wrap.style.gap='6px'; wrap.style.flexWrap='nowrap'; wrap.style.overflow='hidden';
                            const oc=orderCode?.trim() || '';
                            const ic=iliski?.trim() || '';
                            if(!ic || ic==='—' || oc===ic){
                                const b=document.createElement('span');
                                b.classList.add('dlist-ordercode__badge');
                                b.textContent= oc || ic || '—';
                                b.title=b.textContent;
                                wrap.appendChild(b);
                                return wrap;
                            }
                            const b1=document.createElement('span');
                            b1.classList.add('dlist-ordercode__badge');
                            b1.textContent=oc; b1.title=oc;
                            const b2=document.createElement('span');
                            b2.classList.add('dlist-relation__badge', iliskiCls);
                            b2.textContent=ic; b2.title=ic;
                            wrap.appendChild(b1); wrap.appendChild(b2);
                            return wrap;
                        }
                    },{
                        title : 'Eklenme Tarihi',
                        key   : '_created_at_fmt',
                        order : false,
                        type  : 'string',
                        width : this.isTedarik ? '120px' : '13%',
                        columnFormatter : (elm,rowData) => {
                            if(this.isTedarik){
                                const s=document.createElement('span');
                                s.textContent=rowData._created_at_fmt || '—';
                                s.style.color='#334155';
                                s.style.whiteSpace='nowrap';
                                return s;
                            }
                            const wrap = document.createElement('div');
                            wrap.classList.add('dlist-date');
                            const d = document.createElement('span');
                            d.classList.add('dlist-date__text');
                            d.textContent = rowData._created_at_fmt || '—';
                            const icon = document.createElement('i');
                            icon.className = 'ki-outline ki-calendar-8 fs-5 me-1';
                            icon.style.opacity = '.55';
                            wrap.appendChild(icon);
                            wrap.appendChild(d);
                            return wrap;
                        }
                    },{
                        title : 'Güncel Durum',
                        key   : 'last_status',
                        order : false,
                        width : this.isTedarik ? '175px' : '18%',
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            let statusData = {};
                            try { statusData = JSON.parse(columnData || '{}'); } catch(e){ statusData={}; }
                            const key = statusData?.op_key;
                            let label = statusData?.title || 'Bekleniyor';
                            if(!statusData?.title) label = 'Kontrol Bekleniyor';
                            // tedarik: solid pills exactly like order list
                            if(this.isTedarik){
                                const sKey = key || '';
                                let iconCls='ki-outline ki-magnifier';
                                if(sKey==='doc_file_accepted') iconCls='ki-outline ki-check-circle';
                                else if(sKey==='doc_file_rejected') iconCls='ki-outline ki-cross-circle';
                                else if(sKey==='doc_file_refreshed') iconCls='ki-outline ki-arrows-loop';
                                else iconCls='ki-outline ki-magnifier';
                                const pill=document.createElement('span');
                                pill.style.display='inline-flex';
                                pill.style.alignItems='center';
                                pill.style.justifyContent='center';
                                pill.style.padding='5px 10px';
                                pill.style.borderRadius='6px';
                                pill.style.fontSize='11.5px';
                                pill.style.fontWeight='600';
                                pill.style.whiteSpace='nowrap';
                                pill.style.border='1px solid transparent';
                                pill.style.gap='6px';
                                const icon=document.createElement('i');
                                icon.className=iconCls;
                                icon.style.fontSize='14px';
                                pill.appendChild(icon);
                                const txt=document.createElement('span');
                                txt.textContent=statusData?.title || label;
                                // map to order-like labels
                                if(sKey==='doc_file_waiting' || !sKey) txt.textContent='Kontrol Bekleniyor';
                                else if(sKey==='doc_file_accepted') txt.textContent=statusData.title || 'Doküman Onaylandı';
                                else if(sKey==='doc_file_rejected') txt.textContent=statusData.title || 'Doküman Reddedildi';
                                else if(sKey==='doc_file_refreshed') txt.textContent=statusData.title || 'Doküman Onay Yenilendi';
                                pill.appendChild(txt);
                                if(sKey==='doc_file_accepted'){
                                    pill.style.background='#22c55e'; pill.style.color='#fff'; pill.style.borderColor='#22c55e';
                                } else if(sKey==='doc_file_rejected'){
                                    pill.style.background='#ef4444'; pill.style.color='#fff'; pill.style.borderColor='#ef4444';
                                } else if(sKey==='doc_file_refreshed'){
                                    pill.style.background='#facc15'; pill.style.color='#713f12'; pill.style.borderColor='#fcd34d';
                                } else {
                                    pill.style.background='#FF5A1F'; pill.style.color='#fff'; pill.style.borderColor='#FF5A1F';
                                }
                                pill.style.cursor='default';
                                return pill;
                            }
                            const pill = document.createElement('button');
                            pill.classList.add('dlist-pill');
                            let icon = '<i class="ki-outline ki-time fs-5"></i>';
                            switch(key){
                                case 'doc_file_waiting':
                                default:
                                    label = statusData?.title ? (statusData.title==='Kontrol Bekleniyor'? statusData.title : 'Kontrol Bekleniyor') : 'Kontrol Bekleniyor';
                                    icon  = '<i class="ki-outline ki-directbox-default fs-5"></i>';
                                    pill.classList.add('is-waiting');
                                    break;
                                case 'doc_file_accepted':
                                    icon  = '<i class="ki-outline ki-check-circle fs-5"></i>';
                                    pill.classList.add('is-accepted');
                                    label = statusData.title || 'Onaylandı';
                                    break;
                                case 'doc_file_refreshed':
                                    icon  = '<i class="ki-outline ki-arrows-loop fs-5"></i>';
                                    pill.classList.add('is-refreshed');
                                    label = statusData.title || 'Yenilendi';
                                    break;
                                case 'doc_file_rejected':
                                    icon  = '<i class="ki-outline ki-cross-circle fs-5"></i>';
                                    pill.classList.add('is-rejected');
                                    label = statusData.title || 'Reddedildi';
                                    break;
                            }
                            pill.innerHTML = icon+'<span>'+label+'</span>';
                            pill.type = 'button';
                            // tedarik list is read-only — status changes only on detail page (per Master 2026-09-03)
                            if(!this.isTedarik && this.useAuthStore().permissions?.includes('per-07-02')){
                                pill.onclick = (e) => {
                                    Swal.fire({
                                        showConfirmButton : false,
                                        showCloseButton : true,
                                        html : `<small class="mb-5 mt-5">Listeden İstediğiniz Durumu Seçip Güncelleyebilirsiniz</small>
                                                <div class="row m-5 justify-content-center">
                                                    <button class="btn btn-success mb-5 doc-status" data-key="doc_file_accepted"      type="button">Kabul Edildi</button>
                                                    <button class="btn btn-danger mb-5 doc-status"  data-key="doc_file_rejected"  type="button">Reddet</button>
                                                </div>`,
                                        willOpen : async () => {
                                            let noteText = (() => {
                                                try {
                                                    return JSON.parse(statusData?.note ?? '{}')?.note ?? statusData?.note ?? '';
                                                } catch (e) {
                                                    return statusData?.note ?? '';
                                                }
                                            })();
                                            Swal.showValidationMessage(noteText);
                                            document.querySelectorAll('.doc-status').forEach(btn => {
                                                btn.addEventListener('click', e => {
                                                    Swal.fire({
                                                        confirmButtonText : 'Kaydet',
                                                        showCloseButton : true,
                                                        html : `<div style="display:flex; flex-direction:column; align-items:center; gap:14px; padding:6px 4px 2px; text-align:center;">
                                                            <div style="width:56px; height:56px; border-radius:50%; background:#f8fafc; border:1.5px solid #e2e8f0; display:flex; align-items:center; justify-content:center; color:#64748b; font-size:26px;"><i class="ki-outline ki-notepad"></i></div>
                                                            <div style="font-size:17px; font-weight:800; color:#0f172a;">Not Ekle</div>
                                                            <div style="font-size:13px; color:#64748b; line-height:1.5;">İstersen bir not ekle <span style="color:#94a3b8;">(opsiyonel)</span></div>
                                                            <div style="width:100%; text-align:left; margin-top:4px;">
                                                                <textarea id="exampleFormControlTextarea1" placeholder="Notunuz..." style="width:100%; min-height:110px; border:1.5px solid #e2e8f0; border-radius:12px; padding:12px 14px; font-size:13.5px; outline:none; resize:vertical; background:#f8fafc; font-family:inherit;"></textarea>
                                                            </div>
                                                        </div>`,
                                                        allowOutsideClick: () => !Swal.isLoading(),
                                                        preConfirm : async () => {
                                                            try {
                                                                const note     = document.getElementById('exampleFormControlTextarea1').value.trim();
                                                                const envelope = new FormData();
                                                                envelope.append('id',rowData.id);
                                                                envelope.append('op_key',e.target.dataset.key);
                                                                envelope.append('note',note);
                                                                const rsp = await this.plib.request({
                                                                    url      : '/api/v1/trans/set-file-status',
                                                                    method   : 'POST',
                                                                },null,envelope);
                                                                if(rsp.success){
                                                                    const lastStatus = {
                                                                        "op_key" : e.target.dataset.key,
                                                                        "title"  : rsp.data,
                                                                        "note"   : note
                                                                    };
                                                                    this.table.updateRow(rowData.id,{last_status : JSON.stringify(lastStatus)});
                                                                    this.plib.toast(this.Swal,'success','İşlem Tamamlandı');
                                                                }else{
                                                                    Swal.showValidationMessage(rsp.msg);
                                                                }
                                                                return rsp.success;
                                                            } catch (error) {
                                                                Swal.showValidationMessage(`Request failed: ${error}`);
                                                            }
                                                        }
                                                    });
                                                });
                                            });
                                        }
                                    });
                                };
                            } else {
                                pill.style.cursor = 'default';
                            }
                            return pill;
                        }
                    },{
                        title : 'Detaylar',
                        key   : 'id',
                        order : false,
                        colAlign : 'center',
                        headAlign : 'center',
                        width : this.isTedarik ? '210px' : '24%',
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            if(this.isTedarik){
                                const wrap=document.createElement('div');
                                wrap.style.display='flex';
                                wrap.style.justifyContent='flex-end';
                                wrap.style.gap='6px';
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
                                    const canRetake = this.useAuthStore().permissions?.includes('per-07-02');
                                    const html = `<div class="d-flex flex-column gap-2 p-2">
                                        <button id="aks-preview" class="btn" style="background:#f8fafc;color:#334155;border:1px solid #e2e8f0;font-weight:600;padding:12px 16px;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:8px;"><i class="ki-outline ki-eye" style="font-size:16px;"></i> Önizle</button>
                                        ${canRetake ? `<button id="aks-retake" class="btn" style="background:#FF5A1F;color:#fff;border:none;font-weight:600;padding:12px 16px;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:8px;"><i class="ki-outline ki-arrows-loop" style="font-size:16px;"></i> Yeniden Talep Et</button>` : ''}
                                        <button id="aks-detail" class="btn" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;font-weight:600;padding:12px 16px;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:8px;"><i class="ki-outline ki-notepad-edit" style="font-size:16px;"></i> Detay</button>
                                        <button id="aks-link" class="btn" style="background:#f1f5f9;color:#334155;border:1px solid #e2e8f0;font-weight:600;padding:12px 16px;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:8px;"><i class="ki-outline ki-magnifier" style="font-size:16px;"></i> İlişkiye Git</button>
                                    </div>`;
                                    Swal.fire({title:'Aksiyonlar', showConfirmButton:false, showCloseButton:true, html, willOpen:()=>{
                                        document.getElementById('aks-preview')?.addEventListener('click', ()=>{ Swal.close(); window.open('/order-file/'+(rowData?.id ?? rowData?.file),'_blank'); });
                                        document.getElementById('aks-retake')?.addEventListener('click', ()=>{ Swal.close(); this.handleRetake(rowData); });
                                        document.getElementById('aks-detail')?.addEventListener('click', ()=>{ Swal.close(); this.$router.push({ name:'TedarikDForm', params:{ id: rowData.id }}); });
                                        document.getElementById('aks-link')?.addEventListener('click', ()=>{
                                            Swal.close();
                                            const isTed=this.isTedarik;
                                            switch(rowData.relation_type){
                                                case 'op-doc-client': this.$router.push({ name: 'CForm' , params: { id: rowData.relation_qnid }}); break;
                                                case 'op-doc-offer': this.$router.push({ name: isTed ? 'TedarikOrderForm' : 'OForm' , params: { id: rowData.relation_qnid }}); break;
                                                case 'op-doc-order':
                                                case 'op-doc-order-item': this.$router.push({ name: isTed ? 'TedarikOrderForm' : 'OrderForm', params: { id: rowData.relation_qnid }}); break;
                                                default: if(rowData.relation_qnid){ this.$router.push({ name: isTed ? 'TedarikOrderForm' : 'OrderForm', params: { id: rowData.relation_qnid }}); } break;
                                            }
                                        });
                                    }});
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
                                det.onclick=()=>{ this.$router.push({ name:'TedarikDForm', params:{ id: rowData.id }}); };
                                wrap.appendChild(det);
                                return wrap;
                            }
                            const wrap = document.createElement('div');
                            wrap.classList.add('dlist-actions');
                            const mkBtn = (iconClass, title, variant, onClick) => {
                                const b = document.createElement('button');
                                b.type = 'button';
                                b.className = 'dlist-actions__btn '+variant;
                                b.title = title;
                                b.innerHTML = `<i class="ki-outline ${iconClass} fs-3"></i>`;
                                b.onclick = onClick;
                                return b;
                            };
                            wrap.appendChild(mkBtn('ki-notepad-edit','Detay','is-detail', () => {
                                if(this.isTedarik){
                                    this.$router.push({ name:'TedarikDForm', params:{ id: rowData.id }});
                                } else {
                                    this.showDetailModal(rowData);
                                }
                            }));
                            wrap.appendChild(mkBtn('ki-eye','Önizle','is-view', () => window.open('/order-file/'+(rowData?.id ?? rowData?.file),'_blank')));
                            if(this.useAuthStore().permissions?.includes('per-07-02')){
                                const rb=document.createElement('button');
                                rb.type='button';
                                rb.className='dlist-actions__btn is-retake';
                                rb.title='Yeniden Talep Et';
                                rb.innerHTML='<i class="ki-outline ki-arrows-loop fs-5"></i><span style="margin-left:4px; font-size:12px; font-weight:700; white-space:nowrap;">Yeniden Talep Et</span>';
                                rb.style.width='auto'; rb.style.padding='0 14px'; rb.style.gap='6px';
                                rb.onclick=()=> this.handleRetake(rowData);
                                wrap.appendChild(rb);
                            }
                            const canSeeLink = this.isTedarik || this.useAuthStore().permissions?.includes('per-07');
                            if(canSeeLink){
                                wrap.appendChild(mkBtn('ki-magnifier','İlişkiye Git','is-link', () => {
                                    const isTed = this.isTedarik;
                                    switch(rowData.relation_type){
                                        case 'op-doc-client':
                                            this.$router.push({ name: 'CForm' , params: { id: rowData.relation_qnid }});
                                            break;
                                        case 'op-doc-offer':
                                            this.$router.push({ name: isTed ? 'TedarikOrderForm' : 'OForm' , params: { id: rowData.relation_qnid }});
                                            break;
                                        case 'op-doc-order':
                                        case 'op-doc-order-item':
                                            this.$router.push({ name: isTed ? 'TedarikOrderForm' : 'OrderForm', params: { id: rowData.relation_qnid }});
                                            break;
                                        default:
                                            if(rowData.relation_qnid){
                                                this.$router.push({ name: isTed ? 'TedarikOrderForm' : 'OrderForm', params: { id: rowData.relation_qnid }});
                                            }
                                            break;
                                    }
                                }));
                            }
                            return wrap;
                        }
                    }
                ];
                
                const isMobile = window.innerWidth < 768;
                const tableHeight = this.isTedarik ? '75vh' : '70vh';
                const pageLimit = isMobile ? 5 : 10;

                this.table = new PickleTable({
                    container : '#div_table',
                    headers   : headers,
                    pageLimit : pageLimit,
                    height    : tableHeight,
                    type      : 'ajax',
                    columnSearch : false,
                    paginationType : 'number',
                    ajax:{
                        url:'/api/v1/table/document_files',
                        data:{}
                    },
                    initialFilter : [],
                    nextPageIcon : '<i class="ki-outline ki-arrow-right "></i>',
                    prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter:(elm,data)=>{
                        // Preserve file's real created_at before EAV overwrite (EAV has DD/MM/YYYY which breaks dayjs)
                        const fileCreatedAt = data.created_at || '';
                        data._file_created_at = fileCreatedAt;
                        if(fileCreatedAt){
                            const d = dayjs(fileCreatedAt);
                            data._created_at_fmt = d.isValid() ? d.format('DD/MM/YYYY HH:mm') : '—';
                        } else {
                            data._created_at_fmt = '—';
                        }
                        if(data.relation_detail && data.relation_detail !== 'null'){
                            try {
                                JSON.parse(data.relation_detail).forEach(element => {
                                    if(element && element.Key){
                                        const rawKey = element.Key.split('**')[0] || element.Key;
                                        // Don't overwrite core file columns (file CreatedAt, id, file, qnid)
                                        if(['created_at','id','file','qnid','_created_at_fmt','_file_created_at'].includes(rawKey)){
                                            data['eav_'+rawKey] = element.Value;
                                        } else {
                                            data[rawKey] = element.Value;
                                        }
                                        data[element['Key']] = element.Value;
                                    }
                                });
                            } catch(e){}
                        }
                        return data;
                    },
                });
                // enforce tedarik 75vh inline — beats any stylesheet
                this.$nextTick(()=>{
                    const enforceTedarikHeight = ()=>{
                        if(!this.isTedarik) return;
                        const el = document.querySelector('.tedarik-docs-page .pickletable');
                        if(el){
                            if(el.style.getPropertyValue('height') !== '75vh'){
                                el.style.setProperty('height','75vh','important');
                            }
                            if(el.style.getPropertyValue('min-height') !== 'calc(75vh - 280px)'){
                                el.style.setProperty('min-height','calc(75vh - 280px)','important');
                            }
                        }
                    };
                    enforceTedarikHeight();
                    setTimeout(enforceTedarikHeight, 300);
                    setTimeout(enforceTedarikHeight, 1000);
                });
            },
        }
    }

</script>
<template>
    <!-- Tedarik Doküman — same shared component, tedarik gets orange-card chrome like OList (height:auto, number pagination) -->
    <div v-if="isTedarik" :class="['order-list-card', 'tedarik-card', 'tedarik-docs-page']">
        <div class="tedarik-list-top">
            <div class="tedarik-list-title">
                <span>Doküman Listesi</span>
                <i class="ki-outline ki-document tedarik-title-icon"></i>
            </div>
            <div class="tedarik-filters">
                <div class="tedarik-filter-dd-wrap" ref="filtreWrap">
                    <a href="javascript:;" class="tedarik-filter" :class="{active: showFiltre}" @click="toggleFiltre"><i class="ki-outline ki-filter"></i> Filtreler</a>
                </div>
                <teleport to="body">
                    <div v-if="showFiltre" ref="filtreDropdown" class="tedarik-dd tedarik-dd--teleported" :style="`position:fixed; top:${dropdownPos.top}px; left:${dropdownPos.left}px; width:${dropdownPos.width}px; z-index:9999;`" @click.stop>
                        <label class="tedarik-dd-item" :class="{selected: filtreChoice==='seri_no'}" @click="handleFiltreChoice('seri_no')">
                            <span class="tedarik-radio" :class="{on: filtreChoice==='seri_no'}"></span> Seri Numarası ile Arama
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: filtreChoice==='tarihe_gore'}" @click="handleFiltreChoice('tarihe_gore')">
                            <span class="tedarik-radio" :class="{on: filtreChoice==='tarihe_gore'}"></span> Tarihe Göre Sırala
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: filtreChoice==='tarih_araligi'}" @click="handleFiltreChoice('tarih_araligi')">
                            <span class="tedarik-radio" :class="{on: filtreChoice==='tarih_araligi'}"></span> Tarih Aralığı Göster
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: filtreChoice==='siparis_sirala'}" @click="handleFiltreChoice('siparis_sirala')">
                            <span class="tedarik-radio" :class="{on: filtreChoice==='siparis_sirala'}"></span> Sipariş Numarasına Göre Sırala
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: filtreChoice==='beklemede'}" @click="handleFiltreChoice('beklemede')">
                            <span class="tedarik-radio" :class="{on: filtreChoice==='beklemede'}"></span> Beklemede Olanları Göster
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: filtreChoice==='onaylandi'}" @click="handleFiltreChoice('onaylandi')">
                            <span class="tedarik-radio" :class="{on: filtreChoice==='onaylandi'}"></span> Onaylananları Göster
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: filtreChoice==='reddedildi'}" @click="handleFiltreChoice('reddedildi')">
                            <span class="tedarik-radio" :class="{on: filtreChoice==='reddedildi'}"></span> Reddedilenleri Göster
                        </label>
                        <label class="tedarik-dd-item" :class="{selected: filtreChoice==='hepsi'}" @click="handleFiltreChoice('hepsi')">
                            <span class="tedarik-radio" :class="{on: filtreChoice==='hepsi'}"></span> Hepsini Göster
                        </label>
                        <div class="tedarik-dd-divider"></div>
                        <div class="tedarik-dd-item tedarik-dd-has-sub" :class="{selected: filtreChoice==='sirkete_gore'}" @click="handleFiltreChoice('sirkete_gore')">
                            <span class="tedarik-dd-sirket-label">Şirkete Göre Arama</span>
                            <i class="ki-outline ki-right tedarik-dd-arrow"></i>
                        </div>
                    </div>
                </teleport>
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
                                        <input type="checkbox" :checked="modalFilteredClients.length>0 && modalFilteredClients.every(o=> selectedSirkets.includes(o.lifnr))" @change="toggleAllModalClients" style="width:16px;height:16px;accent-color:#FF5A1F;cursor:pointer;">
                                        <span style="font-size:12px;font-weight:600;color:#64748b;">Tümünü Seç</span>
                                        <span style="margin-left:auto;font-size:12px;color:#6b7280;">{{modalFilteredClients.length}} şirket</span>
                                    </div>
                                    <div style="max-height:340px;overflow-y:auto;">
                                        <label v-for="opt in modalFilteredClients" :key="opt.lifnr" @click="toggleSirket(opt.lifnr)" :style="selectedSirkets.includes(opt.lifnr) ? 'background:#fff7ed;display:flex;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid #f1f5f9;cursor:pointer;' : 'display:flex;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid #f1f5f9;cursor:pointer;'">
                                            <input type="checkbox" :checked="selectedSirkets.includes(opt.lifnr)" @change="toggleSirket(opt.lifnr)" @click.stop style="width:16px;height:16px;accent-color:#FF5A1F;flex-shrink:0;">
                                            <span style="flex:1;font-weight:500;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{opt.clititle}}</span>
                                            <span style="font-weight:600;color:#475569;font-size:13px;white-space:nowrap;">{{opt.lifnr}}</span>
                                        </label>
                                        <div v-if="!modalFilteredClients.length" style="padding:24px;text-align:center;color:#9ca3af;font-size:13px;">Sonuç bulunamadı</div>
                                    </div>
                                </div>
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
        <!-- search row — matches OList detailed but simpler for docs -->
        <div class="tedarik-docs-searchrow">
            <div class="tedarik-docs-searchbox">
                <i class="ki-outline ki-magnifier tedarik-docs-search-icon"></i>
                <input type="text" id="mainSearch" class="tedarik-docs-search-input" placeholder="Dosya ara — belge, sipariş no, ürün, seri..." @keydown.enter="searchTable">
            </div>
            <button type="button" class="tedarik-btn-light tedarik-docs-btn" @click="searchTable">Ara</button>
            <button type="button" class="tedarik-btn-light tedarik-docs-btn tedarik-docs-btn--ghost" @click="resetSearch">Sıfırla</button>
        </div>
        <div class="order-list-body">
            <div id="div_table"></div>
        </div>
        <div class="tedarik-bottom-note mt-5">
            <i class="ki-outline ki-information-5 me-5"></i>
            <span>Belgeler tekil listelenir — <b>Sipariş Kodu</b> kolonundan bağlı siparişi görün. Önizlemek için <b>"Önizle"</b>, detay için <b>"Detay"</b>.</span>
        </div>
    </div>
    <div v-else class="dlist-page">
        <div class="dlist-card card">
            <div class="dlist-toolbar">
                <div class="dlist-toolbar__left">
                    <div class="dlist-search">
                        <i class="ki-outline ki-magnifier dlist-search__icon"></i>
                        <input type="text" class="dlist-search__input" id="mainSearch" placeholder="Dosya Ara — belge başlığı, sipariş no, ürün..." @keydown.enter="searchTable">
                    </div>
                    <button type="button" class="dlist-btn dlist-btn--primary" @click="searchTable"><i class="ki-outline ki-magnifier fs-5"></i> Ara</button>
                    <button type="button" class="dlist-btn dlist-btn--ghost" @click="resetSearch">Sıfırla</button>
                </div>
                <div class="dlist-toolbar__right">
                    <button type="button" class="dlist-btn dlist-btn--export" @click="exportTable"><i class="ki-outline ki-file-down fs-5"></i> Excel Çıktı</button>
                </div>
            </div>
            <div class="dlist-tablewrap">
                <div id="div_table"></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
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
.dlist-page { padding-top: 12px; }
.dlist-card {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 8px 32px rgba(15,23,42,.06), 0 1px 3px rgba(15,23,42,.04);
}
.dlist-tablewrap {
    border-radius: 0 0 16px 16px;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
}
.dlist-toolbar {
    display:flex; align-items:center; justify-content:space-between;
    gap:16px; padding:14px 18px;
    background:#fff;
    border-bottom:1px solid #f1f5f9;
    flex-wrap:wrap;
}
.dlist-toolbar__left { display:flex; align-items:center; gap:10px; flex:1 1 auto; flex-wrap:wrap; }
.dlist-toolbar__right { display:flex; align-items:center; gap:10px; }
.dlist-search {
    position:relative; display:flex; align-items:center;
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:999px;
    padding:0 14px 0 38px; height:40px; min-width:320px; flex:1; max-width:520px;
    transition: all .18s;
}
.dlist-search:focus-within { background:#fff; border-color:#cbd5e1; box-shadow:0 0 0 4px rgba(59,130,246,.08); }
.dlist-search__icon { position:absolute; left:12px; font-size:18px; color:#94a3b8; }
.dlist-search__input {
    border:none; outline:none; background:transparent; width:100%;
    font-size:13.5px; color:#0f172a;
}
.dlist-search__input::placeholder { color:#94a3b8; }
.dlist-btn {
    height:40px; padding:0 18px; border-radius:999px; border:1px solid transparent;
    font-size:13px; font-weight:700; letter-spacing:.01em;
    display:inline-flex; align-items:center; gap:7px; cursor:pointer;
    transition: all .16s; white-space:nowrap;
}
.dlist-btn--primary { background:#0f172a; color:#fff; border-color:#0f172a; }
.dlist-btn--primary:hover { background:#1e293b; transform:translateY(-1px); box-shadow:0 8px 18px rgba(15,23,42,.18); }
.dlist-btn--ghost { background:#f1f5f9; color:#334155; border-color:#e2e8f0; }
.dlist-btn--ghost:hover { background:#e2e8f0; }
.dlist-btn--export { background:#fff; color:#0f172a; border-color:#e2e8f0; }
.dlist-btn--export:hover { background:#f8fafc; border-color:#cbd5e1; }

.dlist-tablewrap { padding:0; border-radius:0 0 16px 16px; overflow:hidden; }
.dlist-page .dlist-tablewrap { overflow:hidden; }

#div_table {
    width: 100%;
    min-width: 0;
}
/* admin: 70vh container, internal scroll; tedarik: auto, page scroll */
:deep(.pickletable){ min-width: 0; }
:deep(.pickletable.pt-auto-height){ overflow: visible !important; display:block !important; height:auto !important; }
:deep(.pickletable:not(.pt-auto-height)){ display:flex !important; flex-direction:column !important; overflow:hidden !important; }
:deep(.pickletable .divTable){ min-width: 0; border:none !important; }
:deep(.pickletable.pt-auto-height .divTable){ overflow: visible !important; height:auto !important; }
:deep(.pickletable:not(.pt-auto-height) .divTable){ overflow:auto !important; height:90% !important; }
:deep(.pickletable table){ table-layout: auto !important; width:100% !important; min-width:680px !important; border:none !important; margin:0 !important; border-collapse: collapse !important; background: #fff !important; }
:deep(.pickletable .table-responsive){ overflow:visible !important; }
:deep(.pickletable .divPagination){
    display:flex !important; justify-content:flex-end !important; align-items:center !important;
    padding:14px 18px !important; border-top:1px solid #f1f5f9 !important; background:#fff !important;
    border-radius:0 0 16px 16px !important; gap:6px; flex-wrap:wrap;
}
:deep(.pickletable .divPagination button),
:deep(.pickletable .divPagination .page-link){
    border:1px solid #e2e8f0 !important; background:#fff !important; color:#475569 !important;
    border-radius:8px !important; padding:6px 12px !important; font-size:13px !important; font-weight:600 !important;
    min-width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center;
}
:deep(.pickletable .divPagination .active button),
:deep(.pickletable .divPagination .active .page-link),
:deep(.pickletable .divPagination button.active),
:deep(.pickletable .divPagination .page-link.active),
:deep(.pickletable .divPagination button.current),
:deep(.pickletable .divPagination .page-link.current){
    background:#0f172a !important; color:#fff !important; border-color:#0f172a !important;
}

/* hide first hidden pickletable column */
:deep(.pickletable tr:not(.table-group-header) td:first-child),
:deep(.pickletable th:first-child){ display:none; }

/* ——— table head ——— */
:deep(.pickletable thead th){
    background:#f8fafc !important;
    color:#475569 !important;
    font-size:11.5px !important;
    font-weight:700 !important;
    letter-spacing:.06em !important;
    text-transform:uppercase !important;
    border-bottom:1px solid #e2e8f0 !important;
    border-top:none !important;
    padding:10px 14px !important;
    white-space:nowrap;
}
:deep(.pickletable thead th i){ opacity:.45; }

/* column filter inputs - second header row */
:deep(.pickletable thead tr:nth-child(2) th){
    background:#fff !important;
    padding:8px 10px !important;
    border-bottom:1px solid #eef2f7 !important;
}
:deep(.pickletable thead input){
    height:34px !important; border-radius:10px !important;
    border:1px solid #e2e8f0 !important; background:#f8fafc !important;
    font-size:12.5px !important; padding:0 10px !important;
    transition:.15s !important;
}
:deep(.pickletable thead input:focus){
    background:#fff !important; border-color:#93c5fd !important;
    box-shadow:0 0 0 3px rgba(59,130,246,.12) !important; outline:none !important;
}
:deep(.pickletable thead input::placeholder){ color:#94a3b8 !important; }

/* ——— body rows ——— */
:deep(.pickletable tbody tr:not(.table-group-header)){
    border-bottom:1px solid #f1f5f9 !important;
    transition: background .12s;
}
:deep(.pickletable tbody tr:not(.table-group-header):hover){
    background:#f8fafc !important;
}
:deep(.pickletable tbody td){
    padding:12px 12px !important;
    vertical-align:middle !important;
    font-size:13px !important;
    color:#0f172a !important;
    border:none !important;
    overflow:hidden;
    white-space:nowrap;
}
:deep(.pickletable tbody td:last-child){
    overflow:visible !important;
    white-space:nowrap !important;
}
:deep(.pickletable tbody tr:last-child){ border-bottom:none !important; }

/* group header - premium (inner flex card, full-length, head safe) */
:deep(.table-group-header){
    display:table-row !important;
}
:deep(.table-group-header td){
    display:table-cell !important; /* keep table layout for colspan */
    padding:6px 0 !important; /* gap between groups */
    background: transparent !important; border:none !important;
    box-shadow:none !important;
}
:deep(.dlist-group__inner){
    display:flex !important; align-items:center !important; gap:10px !important;
    width:100% !important; box-sizing:border-box !important;
    background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%) !important;
    border:1px solid #e0e7ff !important; border-left:3px solid #6366f1 !important;
    border-radius:12px !important;
    padding:12px 16px !important;
    box-shadow: 0 2px 8px rgba(99,102,241,.06), 0 1px 2px rgba(15,23,42,.04) !important;
    cursor:pointer !important; user-select:none !important;
    white-space:nowrap !important; overflow:hidden !important;
    transition: all .15s ease !important;
}
:deep(.table-group-header:hover .dlist-group__inner){
    background: linear-gradient(135deg, #e0e7ff 0%, #eef2ff 70%, #fff 100%) !important;
    border-color:#c7d2fe !important;
    box-shadow: 0 6px 16px rgba(99,102,241,.12) !important;
}
:deep(.group-toggle-icon){
    display:inline-flex !important; align-items:center; justify-content:center;
    width:28px; height:28px; border-radius:9px;
    background: linear-gradient(135deg,#6366f1,#4f46e5) !important; border:none !important;
    color:#fff !important; font-size:14px !important; font-weight:800 !important;
    flex-shrink:0 !important; line-height:1 !important;
    box-shadow: 0 2px 8px rgba(99,102,241,.30) !important;
    transition: transform .2s ease !important;
}
:deep(tbody tr[data-group][data-collapsed="true"]){ display:none !important; }
:deep(.dlist-group__icon){
    display:inline-flex; align-items:center; justify-content:center;
    width:30px; height:30px; border-radius:10px;
    background:#eef2ff; border:1px solid #e0e7ff; color:#6366f1;
    flex-shrink:0; font-size:16px;
}
:deep(.dlist-group__no){ color:#1e1b4b; font-weight:800; font-size:14px; letter-spacing:.01em; }
:deep(.dlist-group__count){
    display:inline-flex; align-items:center; gap:6px; margin-left:auto;
    background:#eef2ff; border:1px solid #c7d2fe; color:#4338ca;
    font-size:12px; font-weight:800; padding:5px 12px; border-radius:999px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.6);
}
:deep(.dlist-group__count::before){
    content:""; display:inline-block; width:6px; height:6px; border-radius:999px; background:#6366f1; opacity:.9;
}

/* file cell */
:deep(.dlist-filecell){ display:flex; align-items:center; gap:11px; min-width:0; }
:deep(.dlist-filecell__icon){
    width:38px; height:38px; border-radius:11px; display:inline-flex; align-items:center; justify-content:center;
    background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; flex-shrink:0;
}
:deep(.dlist-filecell__icon.is-test){ background:#fef3ff; color:#a21caf; border-color:#f5d0fe; }
:deep(.dlist-filecell__icon.is-cins){ background:#fff7ed; color:#c2410c; border-color:#fed7aa; }
:deep(.dlist-filecell__icon.is-kabul){ background:#ecfdf5; color:#047857; border-color:#a7f3d0; }
:deep(.dlist-filecell__title){ font-weight:700; font-size:13px; color:#0f172a; line-height:1.25; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
:deep(.dlist-filecell__text){ min-width:0; overflow:hidden; }
:deep(.dlist-filecell__sub){ font-size:11px; color:#94a3b8; margin-top:2px; }

/* relation badge */
:deep(.dlist-relation__badge){
    display:inline-flex; align-items:center; padding:5px 11px; border-radius:999px;
    font-size:12px; font-weight:700; letter-spacing:.01em; border:1px solid transparent;
    max-width:145px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex-shrink:1; min-width:0;
}
:deep(.dlist-relation__badge.is-product){ background:#eef2ff; color:#4338ca; border-color:#c7d2fe; }
:deep(.dlist-relation__badge.is-client){ background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }
:deep(.dlist-relation__badge.is-offer){ background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
:deep(.dlist-relation__badge.is-empty){ background:#f8fafc; color:#94a3b8; border-color:#e2e8f0; }

/* order code */
:deep(.dlist-ordercode){ display:flex; align-items:center; }
:deep(.dlist-ordercode__badge){
    display:inline-flex; align-items:center; padding:5px 11px; border-radius:999px;
    font-size:12px; font-weight:800; letter-spacing:.01em; background:#f8fafc; color:#1e293b;
    border:1px solid #e2e8f0; white-space:nowrap; max-width:125px; overflow:hidden; text-overflow:ellipsis; flex-shrink:0;
}
:deep(.dlist-merged){ display:flex; align-items:center; gap:6px; flex-wrap:nowrap; overflow:hidden; max-width:100%; }
.tedarik-docs-page :deep(.dlist-ordercode__badge){
    background:#fff7ed; color:#7c2d12; border-color:#fed7aa;
}

/* date */
:deep(.dlist-date){ display:inline-flex; align-items:center; gap:2px; color:#334155; font-weight:600; font-size:12.5px; white-space:nowrap; }
:deep(.dlist-date__text){ color:#0f172a; }

/* status pill */
:deep(.dlist-pill){
    display:inline-flex; align-items:center; gap:8px;
    height:34px; padding:0 14px; border-radius:999px; border:1px solid transparent;
    font-size:12px; font-weight:800; letter-spacing:.01em; white-space:nowrap;
    cursor:pointer; transition: all .15s; width:100%; max-width:240px; min-width:0; justify-content:flex-start;
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
    overflow:hidden; text-overflow:ellipsis;
}
:deep(.dlist-pill span){ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0; }
:deep(.dlist-pill:hover){ transform:translateY(-1px); box-shadow:0 6px 16px rgba(15,23,42,.08); }
:deep(.dlist-pill.is-waiting){ background:#f8fafc; color:#475569; border-color:#e2e8f0; }
:deep(.dlist-pill.is-refreshed){ background:#fffbeb; color:#92400e; border-color:#fde68a; }
:deep(.dlist-pill.is-accepted){ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
:deep(.dlist-pill.is-rejected){ background:#fff1f2; color:#9f1239; border-color:#fecdd3; }
:deep(.dlist-pill.is-waiting i){ color:#64748b; }
:deep(.dlist-pill.is-refreshed i){ color:#d97706; }
:deep(.dlist-pill.is-accepted i){ color:#059669; }
:deep(.dlist-pill.is-rejected i){ color:#e11d48; }

/* actions */
:deep(.dlist-actions){ display:flex; align-items:center; justify-content:center; gap:6px; }
:deep(.dlist-actions__btn){
    width:36px; height:36px; border-radius:12px; border:1px solid #e2e8f0;
    background:#fff; color:#334155; display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer; transition: all .14s;
}
:deep(.dlist-actions__btn:hover){ transform:translateY(-1px); box-shadow:0 8px 18px rgba(15,23,42,.08); }
:deep(.dlist-actions__btn.is-detail){ background:#f8fafc; }
:deep(.dlist-actions__btn.is-detail:hover){ background:#eef2ff; color:#4338ca; border-color:#c7d2fe; }
:deep(.dlist-actions__btn.is-view){ background:#fff; }
:deep(.dlist-actions__btn.is-view:hover){ background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }
:deep(.dlist-actions__btn.is-link){ background:#fff; }
:deep(.dlist-actions__btn.is-link:hover){ background:#f8fafc; color:#0f172a; border-color:#cbd5e1; }
:deep(.dlist-actions__btn.is-retake){ background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
:deep(.dlist-actions__btn.is-retake:hover){ background:#ffedd5; color:#7c2d12; border-color:#fdba74; }

/* document-card mobile */
:deep(.document-card){ width:100%; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden; background:#fff; box-shadow:0 8px 24px rgba(15,23,42,.06); }
:deep(.document-card__header){ display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding:16px 16px 12px; background:linear-gradient(135deg, #eef2ff, #f8fafc); }
:deep(.document-card__badge){ display:inline-flex; align-items:center; justify-content:center; padding:4px 12px; border-radius:999px; background:#fff; border:1px solid #e0e7ff; color:#4338ca; font-size:.78rem; font-weight:700; margin-bottom:8px; }
:deep(.document-card__title){ margin:0; font-size:1rem; font-weight:800; color:#0f172a; }
:deep(.document-card__subtitle){ margin:4px 0 0; color:#64748b; font-size:.86rem; }
:deep(.document-card__status){ align-self:center; padding:6px 12px; border-radius:999px; background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; font-size:.78rem; font-weight:800; white-space:nowrap; }
:deep(.document-card__status--clickable){ cursor:pointer; transition:.2s; }
:deep(.document-card__status--clickable:hover){ background:#d1fae5; transform:translateY(-1px); }
:deep(.document-card__body){ display:grid; gap:10px; padding:0 16px 14px; }
:deep(.document-card__row){ display:grid; grid-template-columns:1fr auto; gap:10px; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9; }
:deep(.document-card__row:last-child){ border-bottom:none; }
:deep(.document-card__label){ color:#64748b; font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; }
:deep(.document-card__value){ color:#0f172a; font-size:.95rem; font-weight:700; text-align:right; }
:deep(.document-card__footer){ display:flex; flex-wrap:wrap; gap:8px; padding:10px 16px 16px; border-top:1px solid #f1f5f9; }
:deep(.document-card__action-btn){ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:10px; border:1px solid #e2e8f0; background:#fff; color:#334155; font-size:.82rem; font-weight:700; cursor:pointer; }
:deep(.document-card__action-btn:hover){ background:#f8fafc; }

@media (max-width: 767px) {
    .dlist-toolbar{ flex-direction:column; align-items:stretch; }
    .dlist-search{ min-width:0; max-width:none; }
    :deep(.pickletable thead){ display:none !important; }
    :deep(.pickletable tr:not(.table-group-header) td:first-child),
    :deep(.pickletable tr:not(.table-group-header) th:first-child){ display:table-cell !important; width:100% !important; max-width:100% !important; }
    :deep(.pickletable tr:not(.table-group-header) td:not(:first-child)),
    :deep(.pickletable tr:not(.table-group-header) th:not(:first-child)){ display:none !important; }
    :deep(.pickletable tbody tr){ border:unset !important; border-bottom:unset !important; }
    :deep(.pickletable table){ table-layout:auto !important; min-width:unset !important; width:100% !important; }
    :deep(.pickletable th), :deep(.pickletable td){ font-size:.75rem !important; }
}

/* ===== TEDARIK DOKÜMAN — 1:1 with OList tedarik chrome (card + paper-feed friendly) ===== */
.tedarik-docs-page{
    border:none !important; border-radius:0 !important; box-shadow:none !important;
    background:transparent !important; overflow:visible !important;
}
.tedarik-docs-page .tedarik-list-top{
    display:flex; align-items:center; justify-content:space-between;
    padding:2px 4px 16px; gap:12px; flex-wrap:wrap; border-bottom:none;
}
.tedarik-docs-page .tedarik-list-title{
    display:flex; align-items:center; gap:10px;
    font-size:19px; font-weight:700; color:#1e293b; letter-spacing:-0.02em; line-height:1;
}
.tedarik-docs-page .tedarik-title-icon{
    font-size:13px; color:#9ca3af; border:1px solid #e2e8f0; border-radius:5px;
    width:22px; height:18px; display:inline-flex; align-items:center; justify-content:center; background:#fff;
}
.tedarik-docs-page .tedarik-filters{ display:flex; align-items:center; gap:22px; }
.tedarik-docs-page .tedarik-filter{
    display:inline-flex; align-items:center; gap:6px; font-size:13.5px; font-weight:500;
    color:#8a8a8e; text-decoration:none; cursor:pointer; white-space:nowrap;
}
.tedarik-docs-page .tedarik-filter i{ font-size:13px; color:#a1a1a6; }
.tedarik-docs-page .tedarik-filter:hover{ color:#4b5563; }
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
.tedarik-docs-page .order-list-body{
    background:transparent !important; flex:0 0 auto; display:block; min-height:0; height:auto;
}
/* pickletable tedarik — divTable scroll for 75vh */
.tedarik-docs-page :deep(.pickletable .divTable){
    overflow:auto !important;
}
.tedarik-docs-page :deep(.pickletable table){
    border-collapse:separate !important; border-spacing:0 7px !important;
    width:100% !important; table-layout:auto !important; border:none !important;
}
.tedarik-docs-page :deep(.pickletable thead th){
    background:transparent !important; color:#b0b0b5 !important;
    font-size:13px !important; font-weight:500 !important; text-transform:none !important;
    letter-spacing:0 !important; padding:0 14px 10px !important; border:none !important; border-bottom:none !important;
    white-space:nowrap; text-align:left !important; box-shadow:none !important; position:relative !important; top:auto !important;
}
.tedarik-docs-page :deep(.pickletable thead th:last-child){ text-align:right !important; }
.tedarik-docs-page :deep(.pickletable tbody tr){ background:transparent !important; box-shadow:none !important; transition: transform 0.15s ease, box-shadow 0.15s ease !important; }
.tedarik-docs-page :deep(.pickletable tbody tr:hover){ transform: translateY(-1px) !important; }
.tedarik-docs-page :deep(.pickletable tbody tr:hover td){ background:#fcfcfc !important; box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important; }
.tedarik-docs-page :deep(.pickletable tbody td){
    background:#fff !important; padding:13px 14px !important; font-size:13.5px !important;
    color:#2b2b33 !important; border-top:1px solid #e8e8ea !important; border-bottom:1px solid #e8e8ea !important; border-left:none !important; border-right:none !important;
    vertical-align:middle !important; white-space:nowrap; font-weight:400;
    text-overflow:clip !important; overflow:visible !important;
    box-shadow: 0 1px 2px rgba(15,23,42,0.04) !important;
}
.tedarik-docs-page :deep(.pickletable tbody td:nth-child(2)){
    border-left:1px solid #e8e8ea !important; border-top-left-radius:12px !important; border-bottom-left-radius:12px !important; border-right:none !important;
    font-weight:600 !important; color:#111827 !important;
}
.tedarik-docs-page :deep(.pickletable tbody td:last-child){
    border-right:1px solid #e8e8ea !important; border-top-right-radius:12px !important; border-bottom-right-radius:12px !important; border-left:none !important;
}
.tedarik-docs-page :deep(.pickletable .divPagination){
    background:transparent !important; border-top:none !important; padding:10px 0 0 !important;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; position:relative;
}
.tedarik-docs-page :deep(.pickletable .divPagination button),
.tedarik-docs-page :deep(.pickletable .divPagination .page-link){
    border:1px solid #e5e7eb !important; background:#fff !important; color:#8a8a8e !important;
    border-radius:6px !important; padding:5px 10px !important; font-size:12px !important; font-weight:500 !important;
    min-width:32px; height:30px;
}
.tedarik-docs-page :deep(.pickletable .divPagination .active button),
.tedarik-docs-page :deep(.pickletable .divPagination .active .page-link),
.tedarik-docs-page :deep(.pickletable .divPagination button.active),
.tedarik-docs-page :deep(.pickletable .divPagination .page-link.active),
.tedarik-docs-page :deep(.pickletable .divPagination button.current),
.tedarik-docs-page :deep(.pickletable .divPagination .page-link.current){
    background:#fff !important; color:#FF5A1F !important; border-color:#FF5A1F !important; font-weight:700 !important;
}
.tedarik-docs-page :deep(.table-group-header td){
    padding:6px 0 !important; background:transparent !important; border:none !important;
}
.tedarik-docs-page :deep(.dlist-group__inner){
    background:linear-gradient(135deg, #fff7ed 0%, #fff 100%) !important;
    border:1px solid #fed7aa !important; border-left:3px solid #FF5A1F !important;
}
.tedarik-docs-page :deep(.group-toggle-icon){
    background:linear-gradient(135deg,#FF5A1F,#ea580c) !important;
}
.tedarik-docs-page :deep(.dlist-group__no){ color:#7c2d12 !important; }
.tedarik-docs-page :deep(.dlist-group__count){
    background:#fff7ed !important; border-color:#fed7aa !important; color:#9a3412 !important;
}
.tedarik-docs-page :deep(.dlist-group__count::before){ background:#FF5A1F !important; }

/* ===== Filtreler dropdown — cloned from OList normal filter ===== */
.tedarik-docs-page .tedarik-list-top{ overflow: visible !important; position: relative; z-index: 55; }
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
.tedarik-dd--teleported{ animation: tedarikDdIn .16s ease; }
@media (max-width: 700px){
    .tedarik-dd{ left:0; width: 280px; }
}

/* TEDARIK FILE LIST — user height rule, NEVER override */
.tedarik-docs-page :deep(.pickletable),
.tedarik-docs-page :deep(#div_table.pickletable) {
    height: 75vh !important;
    min-height: calc(75vh - 280px) !important;
}
</style>

<!-- UNSCOPED: override TedarikPanel global .tedarik-main .pickletable height:auto for docs page only -->
<style>
.tedarik-main .tedarik-docs-page .pickletable,
.tedarik-main .tedarik-docs-page #div_table.pickletable,
.tedarik-main .tedarik-docs-page .pickletable.pt-auto-height {
    height: 75vh !important;
    min-height: calc(75vh - 280px) !important;
}
/* Card-row style — replicate OList (shadow+radius on tr, transparent borderless td) */
.tedarik-main .tedarik-docs-page .pickletable { border:none !important; }
.tedarik-main .tedarik-docs-page .pickletable table {
    border:none !important; border-collapse:separate !important; border-spacing:0 7px !important;
}
.tedarik-main .tedarik-docs-page .pickletable tbody td {
    background:transparent !important;
    border:none !important;
    box-shadow:none !important;
    padding:13px 14px !important;
    font-size:13.5px !important;
    color:#2b2b33 !important;
    white-space:nowrap !important; overflow:hidden !important; text-overflow:ellipsis !important;
    vertical-align:middle !important; font-weight:400;
    border-radius:0 !important;
}
.tedarik-main .tedarik-docs-page .pickletable tbody tr {
    background:#fff !important;
    border:none !important;
    border-radius:14px !important;
    box-shadow:0 2px 8px rgba(0,0,0,0.06) !important;
    transition:transform 0.15s ease, box-shadow 0.15s ease !important;
}
.tedarik-main .tedarik-docs-page .pickletable tbody tr:hover {
    transform:translateY(-2px) !important;
    box-shadow:0 4px 16px rgba(0,0,0,0.12) !important;
}
.tedarik-main .tedarik-docs-page .pickletable tbody td:first-child { display:none !important; }
.tedarik-main .tedarik-docs-page .pickletable tbody td:nth-child(2) { border-radius:14px 0 0 14px !important; }
.tedarik-main .tedarik-docs-page .pickletable tbody td:last-child { border-radius:0 14px 14px 0 !important; }
.tedarik-main .tedarik-docs-page .pickletable tbody tr:hover td { background:transparent !important; box-shadow:none !important; }
/* Swal search inputs — fix light/white text (was invisible on white) */
.swal2-input, .swal2-textarea, #swal-flat-range, #retake-note, #exampleFormControlTextarea1 {
    color: #0f172a !important;
    background: #fff !important;
    border-color: #e2e8f0 !important;
}
.swal2-input::placeholder, .swal2-textarea::placeholder, #swal-flat-range::placeholder, #retake-note::placeholder, #exampleFormControlTextarea1::placeholder {
    color: #94a3b8 !important;
}
.swal2-input:focus, .swal2-textarea:focus, #swal-flat-range:focus, #retake-note:focus, #exampleFormControlTextarea1:focus {
    border-color: #FF5A1F !important;
    box-shadow: 0 0 0 3px rgba(255,90,31,0.12) !important;
    color: #0f172a !important;
}
</style>
