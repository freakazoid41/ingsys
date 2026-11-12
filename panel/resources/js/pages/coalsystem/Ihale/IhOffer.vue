<script>
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router';
    import { useAuthStore } from '@/stores/auth';

    import { useNavigationStore } from '@/stores/navigation';
    import Swal from 'sweetalert2';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import flatpickr from 'flatpickr';
    import { Turkish } from 'flatpickr/dist/l10n/tr.js';
    import 'flatpickr/dist/flatpickr.min.css';
    import IhFileCell from './IhFileCell.vue';
    flatpickr.localize(Turkish);


    // İhale Teklif Hazırlama — custom screen (matrix too complex for Form engine).
    // Same data mechanic: one POST/PUT envelope (data JSON + dynamicFile* refs)
    // through /v1/document → registerContent. Versioning, logs, perms unchanged.
    // Cell file tags: {cellKey}**offer_cells**{rowKey}, 2x ** = single-slot
    // versioning, first segment unique per cell so prefix search never collides.
    const ACCEPT_BY_PRESET = {
        pdf     : '.pdf',
        pdf_img : '.pdf,.jpg,.jpeg,.png',
        img     : '.jpg,.jpeg,.png',
        xls     : '.xls,.xlsx',
        all     : '.pdf,.jpg,.jpeg,.png,.xls,.xlsx',
    };
    const BACKEND_EXTS = ['jpg','png','jpeg','pdf','xls','xlsx'];
    const MAX_SIZE = 42000000;

    export default {
        components : { IhFileCell },
        breadcrumbs: {
            list: [ { title: 'İhale Yönetimi', path: '/coalpanel/ihaleler' } ],
            title: 'Teklif Hazırla'
        },
        setup() {
            return {
                useNavigationStore, useAuthStore,
                Plib,
                Swal,
                useRoute
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.init().finally(() => {
                this.navigationStore.toggle(false);
            });
        },
        beforeUnmount(){
            this.closeIhaleModal();
        },
        data() {
            const route = useRoute();
            return {
                loadForm        : false,
                plib            : new Plib(),
                authStore       : useAuthStore(),
                navigationStore : useNavigationStore(),
                id              : route?.params?.id !== '' ? route?.params?.id : undefined,
                gateOk          : false,
                gateMsg         : '',
                boundClients    : [],
                supplierQnid    : '',
                ihaleQnid       : route?.query?.ihale || '',
                ihale           : null,
                items           : [],
                kvaColsB        : [],
                kvaColsC        : [],
                headerConnId    : null,
                saving          : false,
                canWrite        : false,
                activeIdx       : 0,
                showIhaleModal  : false,
                ihaleTable      : null,
                ihaleSearch     : '',
                registrySlots   : [],
                snapshotSlots   : [],
                tenderLocked    : false,
                tenderInactive  : false,
                tenderStatus    : '',
                offerStatus     : '',
                offerRejectNote : '',
                _validFp        : null,
                viewMode        : 'list',
                prodTable       : null,
                prodSearch      : '',
                _tplCache       : {},
            };
        },
        computed: {
            isIhalePanel(){ return this.$route.path.startsWith('/ihalepanel'); },
            listRouteName(){ return this.isIhalePanel ? 'IhalePanelIhList' : 'IhList'; },
            offerRouteName(){ return this.isIhalePanel ? 'IhalePanelIhOffer' : 'IhOffer'; },
            itemsDoneCount(){
                try { return (this.items || []).filter(it => this.itemMissing(it) === 0).length; }
                catch(e) { return 0; }
            },
            productCards(){
                const rank = { attention : 0, partial : 1, done : 2 };
                return (this.items || [])
                    .map((it, i) => ({ it, i, s : this.itemStats(it) }))
                    .sort((a, b) => (rank[a.s.state] - rank[b.s.state]) || (a.i - b.i));
            },
            attentionCount(){
                return (this.items || []).filter(it => this.itemStats(it).state === 'attention').length;
            },
            doneCount(){
                return (this.items || []).filter(it => this.itemStats(it).state === 'done').length;
            },
            canEditGorus(){
                try { return this.authStore.typeKey !== 'op-pert-reseller' && this.canWrite; }
                catch(e) { return false; }
            },
            canReview(){
                try {
                    if (this.authStore.typeKey === 'op-pert-reseller') return false;
                    const p = this.authStore.permissions || [];
                    return p.includes('per-041-03');
                } catch(e) { return false; }
            },
            deadlinePassed(){
                if (!this.ihale?.deadline) return false;
                const t = new Date(this.ihale.deadline + 'T23:59:59').getTime();
                return Number.isFinite(t) && t < Date.now();
            },
        },
        methods: {
            async init(){
                const perms = this.authStore.permissions || [];
                this.canWrite = perms.includes('per-041-03-01');
                const canInspect = perms.includes('per-041-03');
                if (!this.canWrite && !canInspect) {
                    this.gateMsg = 'Teklifler için yetkiniz yok (Teklif Oluşturma / İnceleme).';
                    return;
                }
                const list = this.authStore.currentStatus?.clientQnidList || [];
                const officeInspect = !!this.id && canInspect && this.authStore.typeKey !== 'op-pert-reseller';
                if (!list.length && !officeInspect) {
                    this.gateMsg = 'Teklif yalnızca bağlı carisi olan hesap ile hazırlanabilir.';
                    return;
                }
                if (list.length) {
                    await this.loadBoundClients(list);
                    if (!this.boundClients.length) {
                        this.gateMsg = 'Bağlı cari bulunamadı.';
                        return;
                    }
                    this.supplierQnid = this.boundClients[0].qnid;
                    await this.loadRegistry();
                }
                if (this.id) {
                    await this.loadOffer(this.id);
                } else if (this.ihaleQnid) {
                    const existing = await this.lookupExistingOffer(this.ihaleQnid, this.supplierQnid);
                    if (existing) {
                        this.$router.push({ name : this.offerRouteName, params : { id : existing } }).catch(()=>{});
                        return;
                    }
                    await this.loadIhale(this.ihaleQnid, {});
                }
                this.gateOk = true;
                this.loadForm = true;
                this.$nextTick(() => {
                    if (this.viewMode === 'list' && this.ihale) this.buildProductTable();
                });
            },
            async loadBoundClients(list){
                const out = [];
                for (const qnid of list) {
                    try {
                        const rsp = await this.plib.request({ url: '/api/v1/document/' + qnid, method: 'GET' }, null);
                        const ff = rsp?.data?.formFormat?.['op-doc-client-form'] || {};
                        const ents = (Object.values(ff)[0] || {}).entities || {};
                        out.push({ qnid, title: ents.title || qnid });
                    } catch(e) {}
                }
                this.boundClients = out;
            },
            supplierTitle(){
                return (this.boundClients.find(c => c.qnid === this.supplierQnid) || {}).title || '';
            },
            async loadRegistry(){
                try {
                    const rsp = await this.plib.request({ url : '/api/v1/client-docs/' + this.supplierQnid, method : 'GET' }, null);
                    this.registrySlots = (rsp && rsp.success) ? (rsp.data?.slots || []) : [];
                } catch(e) {
                    this.registrySlots = [];
                }
            },
            async onSupplierChange(){
                await this.loadRegistry();
                if (this.ihale) {
                    await this.buildItems(this.ihale.products || [], {});
                    this.viewMode = 'list';
                    this.$nextTick(() => this.buildProductTable());
                }
            },
            // ---------- ihale picker ----------
            openIhaleModal(){
                this.showIhaleModal = true;
                this.$nextTick(() => this.buildIhaleTable());
            },
            closeIhaleModal(){
                this.showIhaleModal = false;
                if (this.ihaleTable && this.ihaleTable.destroy) {
                    try { this.ihaleTable.destroy(); } catch(e) {}
                }
                this.ihaleTable = null;
                const el = document.getElementById('iho_ihale_table');
                if (el) el.innerHTML = '';
            },
            searchIhaleTable(){
                if (!this.ihaleTable) return;
                const v = (this.ihaleSearch || '').trim();
                this.ihaleTable.setFilter(v ? [{ key : 'all', type : '=', value : v }] : []);
            },
            resetIhaleTable(){
                this.ihaleSearch = '';
                if (this.ihaleTable) this.ihaleTable.setFilter([]);
            },
            buildIhaleTable(){
                this.ihaleTable = new PickleTable({
                    container : '#iho_ihale_table',
                    headers   : [
                        { title : 'İhale Kodu', key : 'ihale_kodu', order : true, type : 'string' },
                        { title : 'Oluşturulma', key : 'created_at', order : true, type : 'string' },
                        {
                            title : '', key : 'id', order : false, colAlign : 'center', type : 'string',
                            columnFormatter : (elm,rowData,columnData) => {
                                const btn = document.createElement('button');
                                btn.classList.add('btn','btn-sm','btn-primary');
                                btn.textContent = 'Seç';
                                btn.onclick = () => this.pickIhale(columnData);
                                return btn;
                            }
                        }
                    ],
                    pageLimit : 10,
                    height : '46vh',
                    type : 'ajax',
                    columnSearch : false,
                    paginationType : 'number',
                    ajax : { url:'/api/v1/table/documents', data:{} },
                    initialFilter : [
                        { key : 'form-type', type : '=', value : 'op-doc-ihale-form' },
                        { key : 'type', type : '=', value : 'op-doc-ihale' }
                    ],
                    rowFormatter:(elm,data)=>{
                        try {
                            JSON.parse(data.main_attr).forEach(element => {
                                data[element['Key']] = element['Value'];
                            });
                        } catch(e) {}
                        return data;
                    },
                });
            },
            async pickIhale(qnid){
                this.closeIhaleModal();
                this.navigationStore.toggle(true);
                try {
                    const existing = await this.lookupExistingOffer(qnid, this.supplierQnid);
                    if (existing) {
                        this.$router.push({ name : this.offerRouteName, params : { id : existing } }).catch(()=>{});
                        return;
                    }
                    await this.loadIhale(qnid, {});
                } finally {
                    this.navigationStore.toggle(false);
                }
            },
            async lookupExistingOffer(ihaleQnid, supplierQnid){
                try {
                    const fd = new FormData();
                    fd.append('tableReq', JSON.stringify({ filter: [
                        { key : 'form-type', type : '=', value : 'op-doc-ihale-offer-form' },
                        { key : 'type', type : '=', value : 'op-doc-ihale-offer' },
                        { key : 'attr', type : 'supplier_qnid', value : supplierQnid },
                    ], scale : { page : 1, limit : 50 } }));
                    const rsp = await this.plib.request({ url : '/api/v1/table/documents', method : 'POST' }, null, fd);
                    const rows = rsp?.data?.data || rsp?.data || [];
                    for (const row of rows) {
                        try {
                            const attrs = JSON.parse(row.main_attr || '[]');
                            const ih = (attrs.find(a => a.Key === 'ihale_qnid') || {}).Value;
                            if (ih === ihaleQnid) return row.id;
                        } catch(e) {}
                    }
                } catch(e) {}
                return null;
            },
            // ---------- loaders ----------
            groupEntities(entities, group){
                // {field}**{group}**{rowKey} → rows keyed by rowKey
                const rows = {};
                for (const k in (entities || {})) {
                    const parts = k.split('**');
                    if (parts.length >= 3 && parts[1] === group) {
                        const rk = parts.slice(2).join('**');
                        (rows[rk] = rows[rk] || {})[parts[0]] = entities[k];
                    }
                }
                return rows;
            },
            parseDocStatus(raw){
                if (Array.isArray(raw)) return raw;
                if (typeof raw === 'string') {
                    try {
                        const parsed = JSON.parse(raw);
                        return Array.isArray(parsed) ? parsed : [];
                    } catch (e) {
                        return [];
                    }
                }
                return [];
            },
            async loadIhale(qnid, savedByProduct){                const rsp = await this.plib.request({ url: '/api/v1/document/' + qnid, method: 'GET' }, null);
                if (!rsp?.success) {
                    this.plib.toast(this.Swal, 'error', 'İhale yüklenemedi.');
                    return;
                }
                const ff = rsp?.data?.formFormat?.['op-doc-ihale-form'] || {};
                const first = Object.values(ff)[0] || {};
                const ents = first.entities || {};
                const statusArr = this.parseDocStatus(rsp?.data?.document?.status);
                const lastStatus = statusArr.length ? statusArr[statusArr.length - 1].op_key : '';
                this.tenderStatus = lastStatus || 'tender-created';
                this.tenderLocked = this.tenderStatus === 'tender-complated';
                this.tenderInactive = !this.tenderLocked && this.tenderStatus !== 'tender-activated';
                const products = Object.values(this.groupEntities(ents, 'ihale_product_rows'))
                    .filter(r => r.product_qnid)
                    .map(r => ({
                        product_qnid : r.product_qnid,
                        title : r.product_title || r.product_qnid,
                        quantity : r.quantity || '',
                        unit : r.unit || '',
                        category_qnid : '',
                        category_title : '',
                        prod_code : '',
                    }));
                for (const p of products) {
                    try {
                        const pr = await this.plib.request({ url: '/api/v1/document/' + p.product_qnid, method: 'GET' }, null);
                        const pff = pr?.data?.formFormat?.['op-doc-product-form'] || {};
                        const pe = (Object.values(pff)[0] || {}).entities || {};
                        p.category_qnid = pe.category_qnid || '';
                        p.category_title = pe.category_title || '';
                        p.prod_code = pe.prod_code || '';
                    } catch(e) {}
                }
                let specFileQnid = '';
                try {
                    const specKey = Object.keys(ents).find(k => k.startsWith('ihale_spec_file'));
                    if (specKey && ents[specKey]) specFileQnid = (JSON.parse(ents[specKey]).qnid) || '';
                } catch(e) {}
                let specTitle = '';
                try {
                    const specTitleKey = Object.keys(ents).find(k => k.startsWith('spec_title'));
                    if (specTitleKey) specTitle = ents[specTitleKey] || '';
                } catch(e) {}
                this.ihaleQnid = qnid;
                this.ihale = {
                    qnid,
                    kodu : ents.ihale_kodu || '',
                    spec_title : specTitle,
                    spec_file_qnid : specFileQnid,
                    deadline : ents.teklif_son_tarihi || '',
                    products,
                };
                await this.buildItems(products, savedByProduct || {});
            },
            async loadTemplates(categoryQnid){
                if (!categoryQnid) return [];
                if (this._tplCache[categoryQnid]) return this._tplCache[categoryQnid];
                try {
                    const rsp = await this.plib.request({ url: '/api/v1/document/' + categoryQnid, method: 'GET' }, null);
                    const ff = rsp?.data?.formFormat?.['op-doc-product-category-form'] || {};
                    const ents = (Object.values(ff)[0] || {}).entities || {};
                    const out = Object.entries(this.groupEntities(ents, 'category_files')).map(([reqId, r]) => {
                        let kva = [];
                        try {
                            const raw = r.kva;
                            kva = Array.isArray(raw) ? raw.map(String) : JSON.parse(raw || '[]').map(String);
                        } catch(e) { kva = []; }
                        return {
                            reqId,
                            evrak_no : r.evrak_no || '',
                            standard : r.standard || '',
                            file_title : r.file_title || '',
                            file_group : r.file_group || '',
                            file_types : r.file_types || 'all',
                            kva,
                        };
                    }).filter(t => t.file_title);
                    this._tplCache[categoryQnid] = out;
                    return out;
                } catch(e) { console.warn('[IhOffer] template load failed', categoryQnid, e); return []; }
            },
            async buildItems(products, savedByProduct){
                const items = [];
                const kvasB = new Set(), kvasC = new Set();
                for (const p of products) {
                    const templates = await this.loadTemplates(p.category_qnid);
                    const cells = {};
                    for (const t of templates) {
                        const isOther = (t.file_group || '').trim().toLocaleLowerCase('tr') === 'diğer belgeler';
                        const kvas = t.kva.length ? t.kva : ['all'];
                        for (const kv of kvas) {
                            (isOther ? kvasC : kvasB).add(kv);
                            const cellKey = 'oc_' + p.product_qnid.slice(0,8) + '_' + String(t.reqId).replace(/[^a-zA-Z0-9]/g,'').slice(-12) + '_' + kv;
                            cells[cellKey] = {
                                cellKey, reqId : t.reqId, kva : kv,
                                evrak_no : t.evrak_no, standard : t.standard,
                                file_title : t.file_title, file_group : t.file_group,
                                file_types : t.file_types, isOther,
                                state : 'empty', pendingFile : null, pendingRef : null,
                                savedFile : null, fileTag : '', removed : false,
                            };
                        }
                    }
                    const saved = (savedByProduct || {})[p.product_qnid] || null;
                    if (saved) {
                        for (const tag in (saved.files || {})) {
                            const ck = tag.split('**')[0];
                            if (cells[ck]) {
                                try {
                                    cells[ck].state = 'saved';
                                    cells[ck].savedFile = JSON.parse(saved.files[tag]);
                                    cells[ck].fileTag = tag;
                                    cells[ck].expired = this.isPast(cells[ck].savedFile?.valid_until);
                                    cells[ck].fileStatus = cells[ck].savedFile?.last_status?.op_key || '';
                                } catch(e) {}
                            }
                        }
                    }
                    // shared library (client + product code + requirement + kVA) wins over
                    // legacy per-offer files; completed tenders read the frozen snapshot
                    this.applyLibrary(cells, p.prod_code);
                    // group cells into template rows for rendering
                    const rowMap = {};
                    for (const ck in cells) {
                        const c = cells[ck];
                        (rowMap[c.reqId] = rowMap[c.reqId] || { tpl : c, perKv : {}, span : null });
                        if (c.kva === 'all') rowMap[c.reqId].span = c;
                        else rowMap[c.reqId].perKv[c.kva] = c;
                    }
                    const rowsB = [], rowsC = [];
                    for (const rk in rowMap) {
                        (rowMap[rk].tpl.isOther ? rowsC : rowsB).push(rowMap[rk]);
                    }
                    const tedasSaved = Object.entries((saved && saved.tedas) || {}).map(([tag, info]) => ({ tag, info }));
                    items.push({
                        product_qnid : p.product_qnid, title : p.title,
                        prod_code : p.prod_code || '',
                        quantity : p.quantity, unit : p.unit,
                        category_title : p.category_title,
                        connId : saved ? saved.connId : null,
                        imalatci : (saved && saved.entities.imalatci_firma_adi) || '',
                        marka : (saved && saved.entities.urun_markasi) || '',
                        gorusFile : (saved && saved.gorus) ? { tag : saved.gorus.tag, info : saved.gorus.info } : null,
                        gorusPending : null, gorusRemoved : false,
                        tedasSaved, tedasPending : [], tedasRemoved : [],
                        cells, rowsB, rowsC,
                    });
                }
                const num = (v) => parseInt(v, 10) || 0;
                this.kvaColsB = [...kvasB].filter(v => v !== 'all').sort((a,b) => num(a) - num(b));
                this.kvaColsC = [...kvasC].filter(v => v !== 'all').sort((a,b) => num(a) - num(b));
                this.items = items;
                this.viewMode = 'list';
                if (this.activeIdx >= items.length) this.activeIdx = 0;
                if (this.loadForm) this.$nextTick(() => this.buildProductTable());
            },
            itemMissing(it){
                let n = 0;
                if (!it.imalatci.trim()) n++;
                if (!it.marka.trim()) n++;
                for (const ck in it.cells) {
                    const c = it.cells[ck];
                    if (!(c.state === 'pending' || (c.state === 'saved' && !c.removed && !c.expired))) n++;
                }
                return n;
            },
            isPast(date){
                if (!date) return false;
                return String(date).slice(0, 10) < new Date().toISOString().slice(0, 10);
            },
            itemStats(it){
                let total = 0, ready = 0, expired = 0;
                for (const ck in (it.cells || {})) {
                    total++;
                    const c = it.cells[ck];
                    if (c.expired) expired++;
                    else if (c.state === 'saved' && !c.removed) ready++;
                }
                const missingFields = (it.imalatci?.trim() ? 0 : 1) + (it.marka?.trim() ? 0 : 1);
                const missing = (total - ready - expired) + missingFields;
                const state = missing === 0
                    ? 'done'
                    : (ready > 0 || it.imalatci?.trim() || it.marka?.trim()) ? 'partial' : 'attention';
                return {
                    total, ready, expired, missingFields, missing, state,
                    pct : total ? Math.round(ready / total * 100) : 0,
                };
            },
            openProduct(i){
                this.activeIdx = i;
                this.viewMode = 'form';
                try { window.scrollTo({ top : 0, behavior : 'smooth' }); } catch(e) {}
            },
            backToList(){
                this.viewMode = 'list';
                this.$nextTick(() => this.buildProductTable());
                try { window.scrollTo({ top : 0, behavior : 'smooth' }); } catch(e) {}
            },
            itemByProduct(qnid){
                return (this.items || []).find(it => it.product_qnid === qnid);
            },
            statFor(qnid){
                const it = this.itemByProduct(qnid);
                return it ? this.itemStats(it) : { total : 0, ready : 0, expired : 0, missing : 0, state : 'attention', pct : 0 };
            },
            openProductByQnid(qnid){
                const i = (this.items || []).findIndex(it => it.product_qnid === qnid);
                if (i >= 0) this.openProduct(i);
            },
            searchProducts(){
                if (this.prodTable) {
                    this.prodTable.setFilter([{ key : 'all', type : '=', value : (this.prodSearch || '').trim() }]);
                }
            },
            resetProducts(){
                this.prodSearch = '';
                if (this.prodTable) this.prodTable.setFilter([]);
            },
            buildProductTable(){
                const host = document.getElementById('iho_prod_table');
                if (!host) return;
                host.innerHTML = '';
                this.prodTable = null;

                const stateLabel = (s) => s === 'done' ? 'Tamamlandı' : s === 'attention' ? 'Dikkat Bekliyor' : 'Devam Ediyor';

                const headers = [
                    {
                        title : 'Ürün',
                        key   : 'product_title',
                        order : false,
                        type  : 'string',
                        width : '34%',
                        columnFormatter : (elm, rowData) => {
                            const wrap = document.createElement('div');
                            wrap.className = 'iho-pt-product';
                            const t = document.createElement('div');
                            t.className = 'iho-pt-title';
                            t.textContent = rowData.product_title || '—';
                            wrap.appendChild(t);
                            const meta = document.createElement('div');
                            meta.className = 'iho-pt-meta';
                            if (rowData.prod_code) {
                                const code = document.createElement('span');
                                code.className = 'iho-pt-code';
                                code.textContent = rowData.prod_code;
                                meta.appendChild(code);
                            }
                            if (rowData.quantity) {
                                const q = document.createElement('span');
                                q.textContent = rowData.quantity + ' ' + (rowData.unit || '');
                                meta.appendChild(q);
                            }
                            wrap.appendChild(meta);
                            return wrap;
                        }
                    },{
                        title : 'Kategori',
                        key   : 'category_title',
                        order : false,
                        type  : 'string',
                        width : '20%',
                        columnFormatter : (elm, rowData) => {
                            const s = document.createElement('span');
                            s.className = 'iho-pt-cat';
                            s.textContent = rowData.category_title || '—';
                            return s;
                        }
                    },{
                        title : 'Evrak',
                        key   : 'evrak',
                        order : false,
                        colAlign : 'center',
                        type  : 'string',
                        width : '14%',
                        columnFormatter : (elm, rowData) => {
                            const st = rowData.stat || this.statFor(rowData.product_qnid);
                            const wrap = document.createElement('div');
                            wrap.className = 'iho-pt-evrak';
                            const bar = document.createElement('div');
                            bar.className = 'iho-pt-bar';
                            const fill = document.createElement('span');
                            fill.style.width = (st.pct || 0) + '%';
                            bar.appendChild(fill);
                            const label = document.createElement('div');
                            label.className = 'iho-pt-evrak-label';
                            label.textContent = (st.ready || 0) + ' / ' + (st.total || 0);
                            wrap.appendChild(bar);
                            wrap.appendChild(label);
                            return wrap;
                        }
                    },{
                        title : 'Eksik',
                        key   : 'missing',
                        order : false,
                        colAlign : 'center',
                        type  : 'string',
                        width : '13%',
                        columnFormatter : (elm, rowData) => {
                            const st = rowData.stat || this.statFor(rowData.product_qnid);
                            const wrap = document.createElement('div');
                            wrap.className = 'iho-pt-missing';
                            if (st.expired) {
                                const b = document.createElement('div');
                                b.className = 'bad';
                                b.textContent = st.expired + ' süresi doldu';
                                wrap.appendChild(b);
                            }
                            const m = st.missing - st.expired;
                            const n = document.createElement('div');
                            n.className = m > 0 ? 'warn' : 'ok';
                            n.textContent = m > 0 ? (m + ' eksik') : 'eksik yok';
                            wrap.appendChild(n);
                            return wrap;
                        }
                    },{
                        title : 'Durum',
                        key   : 'state',
                        order : false,
                        colAlign : 'center',
                        type  : 'string',
                        width : '10%',
                        columnFormatter : (elm, rowData) => {
                            const st = rowData.stat || this.statFor(rowData.product_qnid);
                            const s = document.createElement('span');
                            s.className = 'iho-prod-state iho-prod-state--' + st.state;
                            s.textContent = stateLabel(st.state);
                            return s;
                        }
                    },{
                        title : '',
                        key   : 'id',
                        order : false,
                        colAlign : 'center',
                        type  : 'string',
                        width : '9%',
                        columnFormatter : (elm, rowData) => {
                            const st = rowData.stat || this.statFor(rowData.product_qnid);
                            const btn = document.createElement('button');
                            btn.className = 'iho-pt-btn';
                            btn.type = 'button';
                            btn.textContent = st.state === 'done' ? 'Aç' : 'Doldur';
                            btn.onclick = () => this.openProductByQnid(rowData.product_qnid);
                            return btn;
                        }
                    }
                ];

                this.prodTable = new PickleTable({
                    container : '#iho_prod_table',
                    headers : headers,
                    pageLimit : 10,
                    height : '56vh',
                    type : 'ajax',
                    columnSearch : false,
                    paginationType : 'number',
                    ajax : {
                        url : '/api/v1/tender-products',
                        data : { qnid : this.ihaleQnid },
                    },
                    rowFormatter : (elm, data) => {
                        data.stat = this.statFor(data.product_qnid);
                        if (elm?.classList) {
                            elm.classList.remove('is-attention', 'is-partial', 'is-done');
                            elm.classList.add('is-' + data.stat.state);
                        }
                        return data;
                    },
                });

                if ((this.prodSearch || '').trim() !== '') {
                    this.$nextTick(() => this.searchProducts());
                }
            },
            goProduct(i){
                if (i >= 0 && i < this.items.length) {
                    this.activeIdx = i;
                    this.viewMode = 'form';
                    try { window.scrollTo({ top : 0, behavior : 'smooth' }); } catch(e) {}
                }
            },
            async loadOffer(id){
                const rsp = await this.plib.request({ url: '/api/v1/document/' + id, method: 'GET' }, null);
                if (!rsp?.success) {
                    this.plib.toast(this.Swal, 'error', 'Teklif yüklenemedi.');
                    return;
                }
                const ff = rsp?.data?.formFormat?.['op-doc-ihale-offer-form'] || {};
                const hEntry = Object.entries(ff)[0] || [];
                const hConn = hEntry[0] || null;
                const hEnts = (hEntry[1] || {}).entities || {};
                const ownsOffer = (this.authStore.currentStatus?.clientQnidList || []).includes(hEnts.supplier_qnid);
                // Office reviewers (per-041-03) inspect read-only without a client binding.
                const officeInspect = this.canReview && this.authStore.typeKey !== 'op-pert-reseller';
                if (!hEnts.supplier_qnid || (!ownsOffer && !officeInspect)) {
                    this.plib.toast(this.Swal, 'error', 'Bu teklif sizin firmanıza ait değil.');
                    this.$router.push({ name : this.listRouteName }).catch(()=>{});
                    return;
                }
                this.headerConnId = hConn;
                this.supplierQnid = hEnts.supplier_qnid;
                if (!this.boundClients.find(c => c.qnid === hEnts.supplier_qnid)) {
                    this.boundClients = [{ qnid : hEnts.supplier_qnid, title : hEnts.supplier_title || hEnts.supplier_qnid }];
                }
                try {
                    const snap = JSON.parse(hEnts.snapshot || 'null');
                    if (snap && Array.isArray(snap.slots)) this.snapshotSlots = snap.slots;
                } catch(e) {}
                if (hEnts.snapshot === undefined) this.snapshotSlots = [];
                const offerStatusArr = this.parseDocStatus(rsp?.data?.document?.status);
                const lastOfferStatus = offerStatusArr.length ? offerStatusArr[offerStatusArr.length - 1] : null;
                this.offerStatus = lastOfferStatus?.op_key || 'offer-sended';
                this.offerRejectNote = lastOfferStatus?.note || '';
                await this.loadRegistry();
                const iff = rsp?.data?.formFormat?.['op-doc-ihale-offer-item-form'] || {};
                const savedByProduct = {};
                for (const [connId, row] of Object.entries(iff)) {
                    const e = row.entities || {};
                    if (!e.product_qnid) continue;
                    const ents = {}, files = {}, tedas = {};
                    let gorus = null;
                    for (const ek in e) {
                        if (ek.startsWith('tedas_file**')) {
                            try { tedas[ek] = JSON.parse(e[ek]); } catch(err) {}
                            continue;
                        }
                        if (ek.startsWith('gorus_')) {
                            try { gorus = { tag : ek, info : JSON.parse(e[ek]) }; } catch(err) {}
                            continue;
                        }
                        let isFile = false;
                        try {
                            const p = JSON.parse(e[ek]);
                            if (p && typeof p === 'object' && p.qnid) isFile = true;
                        } catch(err) {}
                        if (isFile) files[ek] = e[ek];
                        else ents[ek] = e[ek];
                    }
                    savedByProduct[e.product_qnid] = { connId, entities : ents, files, tedas, gorus };
                }
                await this.loadIhale(hEnts.ihale_qnid, savedByProduct);
            },
            // ---------- cells ----------
            acceptFor(preset){
                return ACCEPT_BY_PRESET[preset] || ACCEPT_BY_PRESET.all;
            },
            libKey(prodCode, standard, fileTitle, kva){
                return [prodCode || '', standard || '', fileTitle || '', kva || ''].join('||');
            },
            applyLibrary(cells, prodCode){
                // A closed tender must read ONLY its frozen snapshot, even when the
                // snapshot has zero slots. Falling back to registrySlots lets a
                // post-completion library upload appear inside a locked tender.
                const source = this.tenderLocked ? this.snapshotSlots : this.registrySlots;
                if (!source || !source.length) return;
                for (const ck in cells) {
                    const c = cells[ck];
                    const key = this.libKey(prodCode, c.standard, c.file_title, c.kva);
                    const slot = source.find(s => s.match_key === key);
                    if (!slot || !slot.file) continue;
                    c.state = 'saved';
                    c.shared = true;
                    c.removed = false;
                    c.savedFile = slot.file;
                    c.expired = !!slot.expired;
                    c.fileStatus = slot.file?.last_status?.op_key || '';
                    c.history = slot.versions || [];
                    c.fileTag = slot.row || '';
                }
            },
            async askValidity(cell){
                const current = cell.savedFile?.valid_until || '';
                const toDisplay = (v) => {
                    if (!v) return '';
                    const p = String(v).slice(0, 10).split('-');
                    return p.length === 3 ? `${p[2]}.${p[1]}.${p[0]}` : v;
                };
                const r = await this.Swal.fire({
                    title : 'Geçerlilik Tarihi',
                    html : `<div style="text-align:left;">
                            <div style="font-size:12.5px;color:#64748b;margin-bottom:8px;">Belgenin son geçerlilik tarihi (opsiyonel). Süresi geçen dosya teklif kaydını engeller.</div>
                            <input id="iho_valid_until" class="form-control" autocomplete="off" placeholder="gg.aa.yyyy" value="${toDisplay(current)}" style="width:100%;height:42px;">
                        </div>`,
                    showCancelButton : true,
                    confirmButtonText : 'Yükle',
                    cancelButtonText : 'Vazgeç',
                    reverseButtons : true,
                    didOpen : () => {
                        const el = document.getElementById('iho_valid_until');
                        if (!el) return;
                        this._validFp = flatpickr(el, {
                            locale : Turkish,
                            dateFormat : 'd/m/Y',
                            allowInput : true,
                            disableMobile : true,
                            defaultDate : current || null,
                        });
                    },
                    willClose : () => {
                        if (this._validFp) {
                            try { this._validFp.destroy(); } catch(e) {}
                            this._validFp = null;
                        }
                    },
                    preConfirm : () => {
                        const el = document.getElementById('iho_valid_until');
                        const raw = (el?.value || '').trim();
                        if (!raw) return '';
                        const parts = raw.split(/[.\/-]/);
                        if (parts.length !== 3) {
                            this.Swal.showValidationMessage('Tarih gg.aa.yyyy formatında olmalıdır.');
                            return false;
                        }
                        const [d, m, y] = parts.map(x => parseInt(x, 10));
                        if (!d || !m || !y || y < 2000) {
                            this.Swal.showValidationMessage('Geçerli bir tarih girin.');
                            return false;
                        }
                        return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                    },
                });
                if (!r.isConfirmed) return false;
                return r.value || '';
            },
            async onCellFile(cell, item, ev){
                const f = ev?.target?.files?.[0];
                if (ev?.target) ev.target.value = '';
                if (!f) return;
                if (this.tenderLocked || this.tenderInactive || this.deadlinePassed) {
                    this.plib.toast(this.Swal, 'info', this.tenderLocked ? 'İhale tamamlandı — dosyalar kilitli.' : (this.deadlinePassed ? 'Teklif süresi doldu — dosya yüklenemez.' : 'İhale henüz aktif değil — dosya yüklenemez.'));
                    return;
                }
                const ext = (f.name.split('.').pop() || '').toLowerCase();
                if (!BACKEND_EXTS.includes(ext)) {
                    this.plib.toast(this.Swal, 'info', 'İzin verilen: pdf, xls, xlsx, jpg, jpeg, png');
                    return;
                }
                if (f.size > MAX_SIZE) {
                    this.plib.toast(this.Swal, 'info', 'Dosya en fazla 42 MB olabilir.');
                    return;
                }
                const validUntil = await this.askValidity(cell);
                if (validUntil === false) return;

                cell.state = 'uploading';
                try {
                    const fd = new FormData();
                    fd.append('file', f);
                    if (validUntil) fd.append('valid_until', validUntil);
                    const up = await this.plib.request({ url : '/api/v1/temp-upload', method : 'POST' }, null, fd);
                    if (!up?.success || !up?.reference_id) {
                        throw new Error(up?.msg || 'Yükleme başarısız.');
                    }

                    const reference = {
                        success : true,
                        reference_id : up.reference_id,
                        encrypted_name : up.encrypted_name,
                        original_name : up.original_name || f.name,
                    };
                    const payload = new FormData();
                    payload.append('supplier_qnid', this.supplierQnid);
                    payload.append('supplier_title', this.supplierTitle());
                    payload.append('product_code', item.prod_code || '');
                    payload.append('standard', cell.standard || '');
                    payload.append('file_title', cell.file_title || '');
                    payload.append('kva', cell.kva || 'all');
                    payload.append('evrak_no', cell.evrak_no || '');
                    payload.append('reference', JSON.stringify(reference));

                    const rsp = await this.plib.request({ url : '/api/v1/client-docs/slot', method : 'POST' }, null, payload);
                    if (!rsp?.success) {
                        throw new Error(rsp?.msg || 'Kütüphaneye yazılamadı.');
                    }

                    cell.savedFile = rsp.file;
                    cell.state = 'saved';
                    cell.shared = true;
                    cell.removed = false;
                    cell.expired = !!rsp.slot?.expired;
                    cell.history = rsp.slot?.versions || [];
                    cell.fileTag = rsp.slot?.row || '';
                    cell.pendingFile = null;
                    cell.pendingRef = null;
                    this.mergeRegistrySlot(rsp.slot);
                    this.plib.toast(this.Swal, 'success', 'Dosya kaydedildi — aynı ürün için tüm ihalelerde görünecek.');
                } catch(e) {
                    cell.state = cell.savedFile ? 'saved' : 'empty';
                    this.plib.toast(this.Swal, 'error', e?.message || 'Yükleme başarısız.');
                }
            },
            mergeRegistrySlot(slot){
                if (!slot || !slot.match_key) return;
                const i = this.registrySlots.findIndex(s => s.match_key === slot.match_key);
                if (i >= 0) this.registrySlots.splice(i, 1, slot);
                else this.registrySlots.push(slot);
            },
            noteOf(cell){
                const n = cell?.savedFile?.last_status?.note;
                if (!n) return '';
                try {
                    const p = JSON.parse(n);
                    return (p && p.note) ? String(p.note) : String(n);
                } catch(e) {
                    return String(n);
                }
            },
            escHtml(s){
                return String(s ?? '').replace(/[&<>"']/g, c => ({ '&' : '&amp;', '<' : '&lt;', '>' : '&gt;', '"' : '&quot;', "'" : '&#39;' }[c]));
            },
            openCellModal(cell, item){
                const st = this.statusPill(cell);
                const opKey = cell?.savedFile?.last_status?.op_key || '';
                const note = this.noteOf(cell);
                const history = cell.history || [];
                const reqTitle = (cell.evrak_no ? cell.evrak_no + ' — ' : '') + (cell.file_title || 'Dosya');
                const kvaLabel = cell.kva === 'all' ? 'Tüm güçler' : (cell.kva + ' kVA');
                const created = cell.savedFile?.created_at ? String(cell.savedFile.created_at).slice(0, 19) : '—';
                const canRenew = !this.tenderLocked && !this.tenderInactive && !this.deadlinePassed;
                const canReviewFile = this.canReview && !this.tenderLocked && !!cell.savedFile?.id;
                const accept = this.acceptFor(cell.file_types);
                const noteBg = (opKey === 'doc_file_rejected' || opKey === 'offer-file-rejected') ? '#fef2f2' : (cell.expired ? '#fff7ed' : '#fffbeb');
                const noteBd = (opKey === 'doc_file_rejected' || opKey === 'offer-file-rejected') ? '#fecaca' : (cell.expired ? '#fed7aa' : '#fde68a');

                const html = `
                    <div style="text-align:left;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                            <div style="min-width:0;">
                                <div style="font-size:16px;font-weight:800;color:#0f172a;">Dosya İşlemleri</div>
                                <div style="font-size:13px;font-weight:700;color:#0f172a;margin-top:8px;line-height:1.35;">${this.escHtml(reqTitle)}</div>
                                <div style="font-size:11.5px;color:#64748b;margin-top:2px;">${this.escHtml(kvaLabel)}${cell.standard ? ' · ' + this.escHtml(cell.standard) : ''}</div>
                            </div>
                            <button type="button" id="iho_cm_close" aria-label="Kapat"
                                style="flex:0 0 30px;width:30px;height:30px;border:none;border-radius:9px;background:#f1f5f9;color:#64748b;font-size:15px;line-height:1;cursor:pointer;">✕</button>
                        </div>

                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin:14px 0 2px;">
                            <span style="display:inline-flex;align-items:center;padding:4px 12px;border-radius:999px;background:${st.c};color:#fff;font-size:12px;font-weight:700;">${this.escHtml(st.t)}</span>
                            ${cell.shared ? '<span style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;background:#e0f2fe;color:#075985;font-size:11.5px;font-weight:700;">Paylaşımlı</span>' : ''}
                            ${cell.expired ? '<span style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;background:#fff7ed;color:#c2410c;font-size:11.5px;font-weight:700;">Süresi doldu</span>' : ''}
                        </div>

                        <div style="margin-top:12px;border-top:1px solid #f1f5f9;">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #f8fafc;font-size:12.5px;">
                                <span style="color:#64748b;">Yüklenme</span><b style="color:#0f172a;font-weight:700;">${this.escHtml(created)}</b>
                            </div>
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #f8fafc;font-size:12.5px;">
                                <span style="color:#64748b;">Geçerlilik</span><b style="color:#0f172a;font-weight:700;">${cell.savedFile?.valid_until ? this.escHtml(cell.savedFile.valid_until) : '—'}</b>
                            </div>
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #f8fafc;font-size:12.5px;">
                                <span style="color:#64748b;">Kaynak</span><b style="color:#0f172a;font-weight:700;">${cell.shared ? 'Tedarikçi evrak kütüphanesi' : 'Teklife özel'}</b>
                            </div>
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 0;font-size:12.5px;">
                                <span style="color:#64748b;">Sürüm</span><b style="color:#0f172a;font-weight:700;">${history.length}</b>
                            </div>
                        </div>

                        ${note ? `<div style="background:${noteBg};border:1px solid ${noteBd};border-radius:10px;padding:10px 12px;font-size:12.5px;color:#7c2d12;margin-top:12px;"><b>Not / Ret nedeni:</b><div style="margin-top:4px;white-space:pre-wrap;">${this.escHtml(note)}</div></div>` : ''}

                        <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-top:18px;">
                            <button type="button" id="iho_cm_preview" style="border:1px solid #e2e8f0;background:#fff;color:#0f172a;border-radius:9px;padding:8px 14px;font-size:12.5px;font-weight:700;cursor:pointer;">Önizle</button>
                            <button type="button" id="iho_cm_hist" style="border:1px solid #e2e8f0;background:#fff;color:#0f172a;border-radius:9px;padding:8px 14px;font-size:12.5px;font-weight:700;cursor:pointer;">Geçmiş (${history.length})</button>
                            ${canRenew ? '<label for="iho_cm_file" style="border:1px solid #FF5A1F;background:#FF5A1F;color:#fff;border-radius:9px;padding:8px 14px;font-size:12.5px;font-weight:700;cursor:pointer;margin:0;">Yenile</label><input id="iho_cm_file" type="file" accept="' + accept + '" style="display:none;">' : ''}
                            ${canReviewFile && opKey !== 'offer-file-accepted' && opKey !== 'doc_file_accepted' ? '<button type="button" id="iho_cm_accept" style="border:1px solid #bbf7d0;background:#f0fdf4;color:#15803d;border-radius:9px;padding:8px 14px;font-size:12.5px;font-weight:700;cursor:pointer;">Dosyayı Kabul Et</button>' : ''}
                            ${canReviewFile ? '<button type="button" id="iho_cm_reject" style="border:1px solid #fecaca;background:#fff;color:#b91c1c;border-radius:9px;padding:8px 14px;font-size:12.5px;font-weight:700;cursor:pointer;">Dosyayı Reddet</button>' : ''}
                            <button type="button" id="iho_cm_done" style="border:1px solid #0f172a;background:#0f172a;color:#fff;border-radius:9px;padding:8px 16px;font-size:12.5px;font-weight:700;cursor:pointer;">Kapat</button>
                        </div>
                    </div>`;

                this.Swal.fire({
                    html,
                    width : 660,
                    showConfirmButton : false,
                    showCancelButton : false,
                    customClass : { popup : 'iho-cell-modal' },
                    didOpen : () => {
                        const q = (id) => document.getElementById(id);
                        q('iho_cm_close')?.addEventListener('click', () => this.Swal.close());
                        q('iho_cm_done')?.addEventListener('click', () => this.Swal.close());
                        q('iho_cm_preview')?.addEventListener('click', () => {
                            if (cell.savedFile?.qnid) window.open('/order-file/' + cell.savedFile.qnid, '_blank');
                        });
                        q('iho_cm_hist')?.addEventListener('click', () => {
                            this.Swal.close();
                            this.showHistory(cell);
                        });
                        q('iho_cm_file')?.addEventListener('change', (ev) => {
                            this.Swal.close();
                            this.onCellFile(cell, item, ev);
                        });
                        q('iho_cm_accept')?.addEventListener('click', () => {
                            this.Swal.close();
                            this.acceptCellFile(cell);
                        });
                        q('iho_cm_reject')?.addEventListener('click', () => {
                            this.Swal.close();
                            this.rejectCellFile(cell);
                        });
                    },
                });
            },
            async acceptCellFile(cell){
                if(!this.id){
                    this.plib.toast(this.Swal, 'info', 'Dosya kabulü için teklif kaydedilmiş olmalı.');
                    return;
                }
                this.navigationStore.toggle(true);
                const fd = new FormData();
                fd.append('id', this.id);
                fd.append('file_qnid', cell.savedFile?.qnid || '');
                fd.append('file_id', String(cell.savedFile?.id || 0));
                const rsp = await this.plib.request({ url : '/api/v1/offers/file-accept', method : 'POST' }, null, fd);
                this.navigationStore.toggle(false);

                if(rsp?.success){
                    cell.fileStatus = 'offer-file-accepted';
                    cell.savedFile = { ...(cell.savedFile || {}), last_status : { op_key : 'offer-file-accepted', title : 'Dosya Kabul Edildi' } };
                    this.plib.toast(this.Swal, 'success', 'Dosya kabul edildi.');
                } else {
                    this.plib.toast(this.Swal, 'error', rsp?.msg || 'Dosya kabul edilemedi.');
                }
            },
            async rejectCellFile(cell){
                if(!this.id){
                    this.plib.toast(this.Swal, 'info', 'Dosya reddi için teklif kaydedilmiş olmalı.');
                    return;
                }
                const res = await this.Swal.fire({
                    title : 'Dosyayı Reddet',
                    html : '<div style="text-align:left;font-size:12.5px;color:#64748b;margin-bottom:8px;">Bu dosya reddedilir ve <b>teklif otomatik olarak reddedilir</b>. Tedarikçi nedeni görüp düzeltip yeniden gönderebilir.</div>',
                    input : 'textarea',
                    inputLabel : 'Red nedeni (zorunlu)',
                    inputPlaceholder : 'örn. rapor geçersiz / süresi dolmuş',
                    showCancelButton : true,
                    confirmButtonText : 'Reddet',
                    cancelButtonText : 'Vazgeç',
                    reverseButtons : true,
                    inputValidator : (v) => (!v || v.trim().length < 3) ? 'Red nedeni zorunludur.' : undefined,
                });
                if(!res.isConfirmed) return;
                const note = (res.value || '').trim();

                this.navigationStore.toggle(true);
                const fd = new FormData();
                fd.append('id', this.id);
                fd.append('file_qnid', cell.savedFile?.qnid || '');
                fd.append('file_id', String(cell.savedFile?.id || 0));
                fd.append('note', note);
                const rsp = await this.plib.request({ url : '/api/v1/offers/file-reject', method : 'POST' }, null, fd);
                this.navigationStore.toggle(false);

                if(rsp?.success){
                    cell.fileStatus = 'offer-file-rejected';
                    cell.savedFile = { ...(cell.savedFile || {}), last_status : { op_key : 'offer-file-rejected', title : 'Dosya Reddedildi', note } };
                    this.offerStatus = 'offer-rejected';
                    this.offerRejectNote = 'Dosya reddedildi: ' + note;
                    this.plib.toast(this.Swal, 'success', 'Dosya reddedildi, teklif otomatik reddedildi.');
                } else {
                    this.plib.toast(this.Swal, 'error', rsp?.msg || 'Dosya reddedilemedi.');
                }
            },
            showHistory(cell){
                const versions = cell.history || [];
                if (!versions.length) {
                    this.plib.toast(this.Swal, 'info', 'Bu alan için geçmiş yok.');
                    return;
                }
                const rows = versions.map((v) => `<tr>
                        <td>${v.status === 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Eski</span>'}</td>
                        <td>${(v.created_at || '').toString().slice(0, 19)}</td>
                        <td>${v.valid_until || '—'}</td>
                        <td>${v.qnid ? '<a href="/order-file/' + v.qnid + '" target="_blank">Görüntüle</a>' : '—'}</td>
                    </tr>`).join('');
                this.Swal.fire({
                    title : 'Dosya Geçmişi',
                    width : 780,
                    html : `<div style="text-align:left;max-height:60vh;overflow:auto;">
                        <table class="table table-sm align-middle" style="font-size:12.5px;">
                            <thead><tr><th>Durum</th><th>Yüklenme</th><th>Geçerlilik</th><th>Dosya</th></tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>`,
                });
            },
            cancelPending(cell){
                cell.pendingFile = null;
                cell.pendingRef = null;
                cell.state = (cell.savedFile && !cell.removed) ? 'saved' : 'empty';
            },
            removeSaved(cell){
                cell.removed = true;
                cell.savedFile = null;
                cell.state = cell.pendingRef ? 'pending' : 'empty';
            },
            // ---------- tedaş multi files (append-only, 3x ** tags) ----------
            async onTedasFile(item, ev){
                const list = ev?.target?.files ? Array.from(ev.target.files) : [];
                if (ev?.target) ev.target.value = '';
                for (const f of list) {
                    const ext = (f.name.split('.').pop() || '').toLowerCase();
                    if (!BACKEND_EXTS.includes(ext)) {
                        this.plib.toast(this.Swal, 'info', f.name + ': izin verilen: pdf, xls, xlsx, jpg, jpeg, png');
                        continue;
                    }
                    if (f.size > MAX_SIZE) {
                        this.plib.toast(this.Swal, 'info', f.name + ': en fazla 42 MB olabilir.');
                        continue;
                    }
                    const pend = { file : f, ref : null, uploading : true };
                    item.tedasPending.push(pend);
                    try {
                        const fd = new FormData();
                        fd.append('file', f);
                        const rsp = await this.plib.request({ url : '/api/v1/temp-upload', method : 'POST' }, null, fd);
                        if (rsp?.success && rsp?.reference_id) {
                            pend.ref = { success : true, reference_id : rsp.reference_id, encrypted_name : rsp.encrypted_name, original_name : rsp.original_name || f.name };
                        } else {
                            const ix = item.tedasPending.indexOf(pend);
                            if (ix >= 0) item.tedasPending.splice(ix, 1);
                            this.plib.toast(this.Swal, 'error', (rsp?.msg || 'Yükleme başarısız.') + ' — ' + f.name);
                        }
                    } catch(e) {
                        const ix = item.tedasPending.indexOf(pend);
                        if (ix >= 0) item.tedasPending.splice(ix, 1);
                        this.plib.toast(this.Swal, 'error', 'Yükleme başarısız — ' + f.name);
                    } finally {
                        pend.uploading = false;
                    }
                }
            },
            cancelTedasPending(item, pi){
                item.tedasPending.splice(pi, 1);
            },
            removeTedasSaved(item, ti){
                const gone = item.tedasSaved.splice(ti, 1)[0];
                if (gone && gone.tag) item.tedasRemoved.push(gone.tag);
            },
            // ---------- görüş single file (admin-only) ----------
            async onGorusFile(item, ev){
                const f = ev?.target?.files?.[0];
                if (ev?.target) ev.target.value = '';
                if (!f) return;
                const ext = (f.name.split('.').pop() || '').toLowerCase();
                if (!BACKEND_EXTS.includes(ext)) {
                    this.plib.toast(this.Swal, 'info', 'İzin verilen: pdf, xls, xlsx, jpg, jpeg, png');
                    return;
                }
                if (f.size > MAX_SIZE) {
                    this.plib.toast(this.Swal, 'info', 'Dosya en fazla 42 MB olabilir.');
                    return;
                }
                item.gorusPending = { file : f, ref : null, uploading : true };
                try {
                    const fd = new FormData();
                    fd.append('file', f);
                    const rsp = await this.plib.request({ url : '/api/v1/temp-upload', method : 'POST' }, null, fd);
                    if (rsp?.success && rsp?.reference_id) {
                        item.gorusPending.ref = { success : true, reference_id : rsp.reference_id, encrypted_name : rsp.encrypted_name, original_name : rsp.original_name || f.name };
                    } else {
                        item.gorusPending = null;
                        this.plib.toast(this.Swal, 'error', rsp?.msg || 'Yükleme başarısız.');
                    }
                } catch(e) {
                    item.gorusPending = null;
                    this.plib.toast(this.Swal, 'error', 'Yükleme başarısız.');
                } finally {
                    if (item.gorusPending) item.gorusPending.uploading = false;
                }
            },
            cancelGorusPending(item){
                item.gorusPending = null;
            },
            removeGorusSaved(item){
                item.gorusRemoved = true;
                item.gorusFile = null;
            },
            statusPill(c){
                if (c?.expired || this.isPast(c?.savedFile?.valid_until)) return { t : 'Süresi doldu', c : '#dc2626' };
                const op = c?.savedFile?.last_status?.op_key || '';
                if (op === 'doc_file_accepted' || op === 'offer-file-accepted') return { t : 'Onaylandı', c : '#16a34a' };
                if (op === 'doc_file_rejected' || op === 'offer-file-rejected') return { t : 'Reddedildi', c : '#dc2626' };
                if (op === 'doc_file_refreshed') return { t : 'Yenilendi', c : '#2563eb' };
                if (c?.shared || c?.savedFile?.valid_until) return { t : 'Yüklendi', c : '#0ea5e9' };
                return { t : 'İncelemede', c : '#d97706' };
            },
            previewFile(qnid){
                if (qnid) window.open('/order-file/' + qnid, '_blank');
            },
            // ---------- save ----------
            missingList(){
                const missing = [];
                this.items.forEach((it, i) => {
                    if (!it.imalatci.trim()) missing.push((i+1) + '. ürün: İmalatçı Firma Adı');
                    if (!it.marka.trim()) missing.push((i+1) + '. ürün: Ürün Markası');
                    for (const ck in it.cells) {
                        const c = it.cells[ck];
                        const label = (i+1) + '. ürün: ' + ((c.evrak_no ? c.evrak_no + ' — ' : '') + c.file_title) + (c.kva !== 'all' ? ' (' + c.kva + ' kVA)' : '');
                        if (c.expired) {
                            missing.push(label + ' (süresi doldu)');
                        } else if (!(c.state === 'pending' || (c.state === 'saved' && !c.removed))) {
                            missing.push(label);
                        }
                    }
                });
                return missing;
            },
            buildEnvelope(){
                const dynamicF = {};
                const files = {};
                const removedData = [];
                const hKey = 'op-doc-ihale-offer-form**' + (this.headerConnId || 'new-offer-h');
                dynamicF[hKey] = { tag : 'op-doc-ihale-offer-form', entities : {
                    ihale_qnid : this.ihaleQnid,
                    ihale_kodu : this.ihale?.kodu || '',
                    supplier_qnid : this.supplierQnid,
                    supplier_title : this.supplierTitle(),
                }};
                this.items.forEach((it, i) => {
                    const rowKey = it.connId || ('new-offer-p' + i);
                    const key = 'op-doc-ihale-offer-item-form**' + rowKey;
                    dynamicF[key] = { tag : 'op-doc-ihale-offer-item-form', entities : {
                        ihale_qnid : this.ihaleQnid,
                        supplier_qnid : this.supplierQnid,
                        product_qnid : it.product_qnid,
                        product_title : it.title,
                        quantity : it.quantity || '',
                        unit : it.unit || '',
                        imalatci_firma_adi : it.imalatci,
                        urun_markasi : it.marka,
                    }};
                    // görüş single file (admin-only; suppliers never send it — backend strips it too)
                    if (this.canEditGorus && it.gorusPending && it.gorusPending.ref) {
                        const fileTag = 'gorus_' + it.product_qnid.slice(0,8) + '**gorus_docs**' + rowKey;
                        files['dynamicFile**gorus_docs**' + rowKey + '*-*' + fileTag] = JSON.stringify(it.gorusPending.ref);
                    } else if (this.canEditGorus && it.gorusRemoved && it.gorusFile && it.gorusFile.tag && it.connId) {
                        removedData.push({ id : it.connId, key : it.gorusFile.tag });
                    }
                    for (const ck in it.cells) {
                        const c = it.cells[ck];
                        if (c.pendingRef) {
                            const fileTag = c.cellKey + '**offer_cells**' + rowKey;
                            files['dynamicFile**offer_cells**' + rowKey + '*-*' + fileTag] = JSON.stringify(c.pendingRef);
                        } else if (c.removed && c.fileTag && it.connId) {
                            removedData.push({ id : it.connId, key : c.fileTag });
                        }
                    }
                    // tedaş multi files: each upload is its own slot (unique 4th segment → append, never replace)
                    (it.tedasPending || []).forEach((tp, pi) => {
                        if (!tp.ref) return;
                        const uniq = 'doc-' + Date.now() + '-' + pi + '-' + Math.random().toString(36).substr(2, 5);
                        const fileTag = 'tedas_file**tedas_docs**' + rowKey + '**' + uniq;
                        files['dynamicFile**tedas_docs**' + rowKey + '*-*' + fileTag] = JSON.stringify(tp.ref);
                    });
                    (it.tedasRemoved || []).forEach((tag) => {
                        if (it.connId) removedData.push({ id : it.connId, key : tag });
                    });
                });
                return { typeKey : 'op-doc-ihale-offer', dynamicF, removedData, files };
            },
            async save(){
                if (this.saving) return;
                if (this.tenderLocked || this.tenderInactive || this.deadlinePassed) {
                    this.plib.toast(this.Swal, 'info', this.tenderLocked ? 'İhale tamamlandı — teklif kilitli.' : (this.deadlinePassed ? 'Teklif süresi doldu — teklif kaydedilemez.' : 'İhale henüz aktif değil — teklif kaydedilemez.'));
                    return;
                }
                if (!this.ihaleQnid || !this.supplierQnid) {
                    this.plib.toast(this.Swal, 'info', 'Önce ihale ve tedarikçi seçin.');
                    return;
                }
                const missing = this.missingList();
                if (missing.length) {
                    this.plib.toast(this.Swal, 'info', 'Eksik alanlar: ' + missing.slice(0,5).join(' • ') + (missing.length > 5 ? ' (+' + (missing.length-5) + ' daha)' : ''));
                    return;
                }
                this.saving = true;
                this.navigationStore.toggle(true);
                try {
                    const env = this.buildEnvelope();
                    const fd = new FormData();
                    fd.append('data', JSON.stringify({ typeKey : env.typeKey, dynamicF : env.dynamicF, removedData : env.removedData }));
                    for (const k in env.files) fd.append(k, env.files[k]);
                    const rsp = await this.plib.request({
                        url : '/api/v1/document' + (this.id ? '/' + this.id : ''),
                        method : this.id ? 'PUT' : 'POST',
                    }, null, fd);
                    if (!rsp?.success) {
                        this.plib.toast(this.Swal, 'error', rsp?.msg || 'Kayıt başarısız.');
                        return;
                    }
                    const qnid = rsp?.data?.qnid || this.id;
                    this.plib.toast(this.Swal, 'success', 'Teklif kaydedildi.', () => {
                        if (!this.id && qnid) {
                            this.$router.push({ name : this.offerRouteName, params : { id : qnid } }).catch(()=>{});
                        } else {
                            window.location.reload();
                        }
                    });
                } finally {
                    this.saving = false;
                    this.navigationStore.toggle(false);
                }
            },
        }
    }

</script>

<template>
    <div>
        <div v-if="!gateOk && !loadForm" class="card">
            <div class="card-body text-center py-15">
                <h2 class="fw-bold mb-3">Teklif Hazırlama</h2>
                <p class="text-muted fs-6">{{ gateMsg || 'Yükleniyor...' }}</p>
            </div>
        </div>

        <div v-if="loadForm">
            <!-- header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-bold">İhale</label>
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control" :value="ihale ? ihale.kodu : ''" placeholder="Seçmek için tıklayın..." readonly @click="openIhaleModal" style="cursor:pointer;" />
                                <button v-if="ihale && !id && canWrite" type="button" class="btn btn-light" @click="openIhaleModal">Değiştir</button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tedarikçi Firma</label>
                            <select v-if="boundClients.length > 1" v-model="supplierQnid" :disabled="!canWrite || tenderLocked" class="form-select" @change="onSupplierChange">
                                <option v-for="c in boundClients" :key="c.qnid" :value="c.qnid">{{ c.title }}</option>
                            </select>
                            <input v-else type="text" class="form-control" :value="supplierTitle()" readonly />
                        </div>
                        <div class="col-md-3 text-end" v-if="canWrite && !tenderLocked && !tenderInactive && !deadlinePassed">
                            <button type="button" class="btn btn-success" :disabled="saving || !ihale" @click="save">
                                <span v-if="saving">Kaydediliyor...</span>
                                <span v-else>Teklifi Kaydet</span>
                            </button>
                        </div>
                    </div>
                    <div v-if="ihale && ihale.deadline" class="small mt-3" :class="deadlinePassed ? 'text-danger' : 'text-muted'">
                        <i class="ki-outline ki-time fs-6"></i>
                        Teklif son tarihi: <b>{{ ihale.deadline }}</b>
                    </div>
                    <div v-if="tenderInactive" class="alert alert-info d-flex align-items-center gap-2 mt-3 mb-0">
                        <i class="ki-outline ki-information-5 fs-3"></i>
                        <div>
                            <strong>İhale henüz aktif değil.</strong>
                            Teklif hazırlamak ve dosya yüklemek için ihale yöneticisinin ihaleyi aktif etmesi gerekir.
                        </div>
                    </div>
                    <div v-if="deadlinePassed && !tenderLocked" class="alert alert-danger d-flex align-items-center gap-2 mt-3 mb-0">
                        <i class="ki-outline ki-time fs-3"></i>
                        <div>
                            <strong>Teklif süresi doldu.</strong>
                            Bu ihaleye yeni teklif kaydedilemez veya dosya yüklenemez.
                        </div>
                    </div>
                    <div v-if="tenderLocked" class="alert alert-warning d-flex align-items-center gap-2 mt-3 mb-0">
                        <i class="ki-outline ki-lock-2 fs-3"></i>
                        <div>
                            <strong>İhale tamamlandı.</strong>
                            Bu teklifteki dosyalar kilitlendi — tamamlanma anındaki sürümler gösteriliyor. Evrak kütüphanesindeki yeni yüklemeler bu teklifi değiştirmez.
                        </div>
                    </div>
                    <div v-if="offerStatus === 'offer-rejected' && !tenderLocked" class="alert alert-danger d-flex align-items-start gap-2 mt-3 mb-0">
                        <i class="ki-outline ki-cross-circle fs-3"></i>
                        <div>
                            <strong>Teklif reddedildi.</strong>
                            <div v-if="offerRejectNote" style="margin-top:2px;">Neden: {{ offerRejectNote }}</div>
                            <div class="text-muted small" style="margin-top:4px;">Düzeltmeleri yapıp <b>Teklifi Kaydet</b> ile yeniden gönderin.</div>
                        </div>
                    </div>
                    <div v-else-if="offerStatus === 'offer-accepted'" class="alert alert-success d-flex align-items-center gap-2 mt-3 mb-0">
                        <i class="ki-outline ki-check-circle fs-3"></i>
                        <div><strong>Teklif kabul edildi.</strong> Bu tedarikçi ihalenin kazananı.</div>
                    </div>
                </div>
            </div>

            <div v-if="!ihale" class="card">
                <div class="card-body text-center py-15">
                    <p class="text-muted fs-6">Teklif hazırlamak için önce bir ihale seçin.</p>
                    <button type="button" class="btn btn-primary" @click="openIhaleModal">İhale Seç</button>
                </div>
            </div>

            <!-- product overview: the first thing the supplier sees -->
            <div v-if="viewMode === 'list' && ihale && items.length" class="card mb-4 iho-prodlist">
                <div class="card-header iho-prodlist-head">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ki-outline ki-parcel fs-2 text-primary"></i>
                        <span class="fw-bold fs-4">Ürün Listesi</span>
                        <span class="text-muted small">{{ ihale.kodu }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span v-if="attentionCount" class="iho-chip iho-chip--attention">{{ attentionCount }} ürün dikkat bekliyor</span>
                        <span v-if="doneCount" class="iho-chip iho-chip--done">{{ doneCount }} tamam</span>
                        <span class="iho-chip iho-chip--muted">{{ items.length }} ürün</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="iho-prod-searchrow">
                        <div class="iho-search-wrap">
                            <i class="ki-outline ki-magnifier fs-4 iho-search-icon"></i>
                            <input
                                id="iho_prod_search"
                                v-model="prodSearch"
                                type="text"
                                class="form-control"
                                placeholder="Ürün adı / kodu ara..."
                                @keyup.enter="searchProducts"
                            />
                        </div>
                        <button type="button" class="rlist-btn rlist-btn-primary" @click="searchProducts">
                            <i class="ki-outline ki-magnifier fs-5"></i> Ara
                        </button>
                        <button type="button" class="rlist-btn rlist-btn-ghost" @click="resetProducts">Sıfırla</button>
                        <span class="iho-prod-progress ms-auto">{{ itemsDoneCount }} / {{ items.length }} ürün tamam</span>
                    </div>
                    <div id="iho_prod_table"></div>
                </div>
            </div>

            <!-- product pager: one product form at a time (dropdown scales to 10+ products) -->
            <div v-if="viewMode === 'form' && ihale && items.length" class="card mb-3">
                <div class="card-body py-2">
                    <div class="iho-pager">
                        <button type="button" class="iho-pager-back" @click="backToList">
                            <i class="ki-outline ki-arrow-left fs-5"></i> Ürün Listesi
                        </button>
                        <template v-if="items.length > 1">
                            <span class="iho-pager-sep"></span>
                            <button type="button" class="iho-pager-btn" :disabled="activeIdx===0" @click="goProduct(activeIdx-1)">‹ Önceki ürün</button>
                            <select class="form-select form-select-sm iho-pager-select" :value="activeIdx" @change="goProduct(Number($event.target.value))">
                                <option v-for="(pt, idx) in items" :key="'tab'+idx" :value="idx">{{ idx+1 }}. {{ pt.title }}{{ itemMissing(pt) ? ' (' + itemMissing(pt) + ' eksik)' : ' ✓' }}</option>
                            </select>
                            <button type="button" class="iho-pager-btn" :disabled="activeIdx===items.length-1" @click="goProduct(activeIdx+1)">Sonraki ürün ›</button>
                        </template>
                    </div>
                </div>
                <div class="px-3 pb-2" v-if="items.length > 1">
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar bg-success" role="progressbar" :style="{ width : (items.length ? Math.round(itemsDoneCount / items.length * 100) : 0) + '%' }"></div>
                    </div>
                    <div class="text-center small mt-1" style="color:#64748b;">{{ itemsDoneCount }} / {{ items.length }} ürün tamam</div>
                </div>
            </div>

            <!-- per product (single visible form) -->
            <div v-for="(it, idx) in items" :key="it.product_qnid + '_' + idx" v-show="viewMode === 'form' && idx === activeIdx" class="card mb-4" :class="{ 'iho-ro': !canWrite }">
                <div class="card-header d-flex justify-content-between align-items-center" style="background:#f8fafc;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold fs-5">{{ idx + 1 }}. {{ it.title }}</span>
                        <span class="badge bg-light text-dark ms-2" v-if="it.quantity">{{ it.quantity }} {{ it.unit }}</span>
                    </div>
                    <span class="text-muted small">{{ it.category_title }}</span>
                </div>
                <div class="card-body">
                    <!-- A: header fields -->
                    <table class="table table-bordered align-middle" style="table-layout:fixed;">
                        <tbody>
                            <tr><th style="width:32%;background:#f1f5f9;">İHALE KODU</th><td>{{ ihale.kodu }}</td></tr>
                            <tr><th style="background:#f1f5f9;">İMALATÇI FİRMA ADI *</th><td><input v-model="it.imalatci" :disabled="!canWrite" type="text" class="form-control" placeholder="doldurunuz" /></td></tr>
                            <tr><th style="background:#f1f5f9;">TEDARİKÇİ FİRMA ADI</th><td>{{ supplierTitle() }}</td></tr>
                            <tr><th style="background:#f1f5f9;">ÜRÜN MARKASI *</th><td><input v-model="it.marka" :disabled="!canWrite" type="text" class="form-control" placeholder="doldurunuz" /></td></tr>
                            <tr><th style="background:#fffbeb;">MALZEME GRUBU</th><td>{{ it.category_title }}</td></tr>
                            <tr><th style="background:#f1f5f9;">İLGİLİ TEKNİK ŞARTNAME</th><td>{{ ihale.spec_title || '—' }}</td></tr>
                            <tr>
                                <th style="background:#f1f5f9;">TEDAŞ DEĞERLENDİRME RAPORU</th>
                                <td>
                                    <div class="iho-attach-list">
                                        <div v-for="(tf, ti) in it.tedasSaved" :key="'ts'+ti" class="iho-attach">
                                            <span class="iho-attach-ic"><i class="ki-outline ki-document fs-4"></i></span>
                                            <span class="iho-attach-name" :title="tf.info?.original_name || tf.tag">{{ tf.info?.original_name || 'TEDAŞ Raporu' }}</span>
                                            <span class="iho-attach-pill" :style="{ background : statusPill({ savedFile : tf.info }).c }">{{ statusPill({ savedFile : tf.info }).t }}</span>
                                            <span class="iho-attach-spacer"></span>
                                            <button type="button" class="iho-attach-btn" @click="previewFile(tf.info?.qnid)">Önizle</button>
                                            <button type="button" class="iho-attach-btn danger" @click="removeTedasSaved(it, ti)">Kaldır</button>
                                        </div>
                                        <div v-for="(tp, pi) in it.tedasPending" :key="'tp'+pi" class="iho-attach is-pending">
                                            <span class="iho-attach-ic"><i class="ki-outline ki-loading fs-4"></i></span>
                                            <span class="iho-attach-name">{{ tp.file ? tp.file.name : 'Dosya' }}</span>
                                            <span v-if="tp.uploading && !tp.ref" class="iho-attach-note">yükleniyor...</span>
                                            <span class="iho-attach-spacer"></span>
                                            <button type="button" class="iho-attach-btn" @click="cancelTedasPending(it, pi)">Vazgeç</button>
                                        </div>
                                        <label class="iho-attach-add">
                                            <i class="ki-outline ki-plus fs-5"></i> Dosya Ekle
                                            <input type="file" multiple :accept="acceptFor('all')" @change="(ev)=>onTedasFile(it, ev)" />
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th style="background:#f1f5f9;">GENEL DEĞERLENDİRME GÖRÜŞÜ</th>
                                <td>
                                    <div class="iho-attach-list">
                                        <template v-if="canEditGorus">
                                            <div v-if="it.gorusPending" class="iho-attach is-pending">
                                                <span class="iho-attach-ic"><i class="ki-outline ki-loading fs-4"></i></span>
                                                <span class="iho-attach-name">{{ it.gorusPending.file ? it.gorusPending.file.name : 'Dosya' }}</span>
                                                <span v-if="it.gorusPending.uploading" class="iho-attach-note">yükleniyor...</span>
                                                <span class="iho-attach-spacer"></span>
                                                <button type="button" class="iho-attach-btn" @click="cancelGorusPending(it)">Vazgeç</button>
                                            </div>
                                            <div v-else-if="it.gorusFile" class="iho-attach">
                                                <span class="iho-attach-ic"><i class="ki-outline ki-document fs-4"></i></span>
                                                <span class="iho-attach-name" :title="it.gorusFile.info?.original_name || it.gorusFile.tag">{{ it.gorusFile.info?.original_name || 'Genel Değerlendirme Görüşü' }}</span>
                                                <span class="iho-attach-pill" :style="{ background : statusPill({ savedFile : it.gorusFile.info }).c }">{{ statusPill({ savedFile : it.gorusFile.info }).t }}</span>
                                                <span class="iho-attach-spacer"></span>
                                                <button type="button" class="iho-attach-btn" @click="previewFile(it.gorusFile.info?.qnid)">Önizle</button>
                                                <label class="iho-attach-btn">
                                                    Değiştir
                                                    <input type="file" :accept="acceptFor('all')" @change="(ev)=>onGorusFile(it, ev)" />
                                                </label>
                                                <button type="button" class="iho-attach-btn danger" @click="removeGorusSaved(it)">Kaldır</button>
                                            </div>
                                            <label v-else class="iho-attach-add">
                                                <i class="ki-outline ki-plus fs-5"></i> Dosya Yükle
                                                <input type="file" :accept="acceptFor('all')" @change="(ev)=>onGorusFile(it, ev)" />
                                            </label>
                                        </template>
                                        <template v-else>
                                            <div v-if="it.gorusFile" class="iho-attach">
                                                <span class="iho-attach-ic"><i class="ki-outline ki-document fs-4"></i></span>
                                                <span class="iho-attach-name" :title="it.gorusFile.info?.original_name || it.gorusFile.tag">{{ it.gorusFile.info?.original_name || 'Genel Değerlendirme Görüşü' }}</span>
                                                <span class="iho-attach-pill" :style="{ background : statusPill({ savedFile : it.gorusFile.info }).c }">{{ statusPill({ savedFile : it.gorusFile.info }).t }}</span>
                                                <span class="iho-attach-spacer"></span>
                                                <button type="button" class="iho-attach-btn" @click="previewFile(it.gorusFile.info?.qnid)">Önizle</button>
                                            </div>
                                            <span v-else class="iho-attach-empty">İhale admini değerlendirme sırasında ekleyecek.</span>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- B: tip test matrix -->
                    <h6 class="fw-bold mt-4 mb-2">TİP TEST RAPORLARI</h6>
                    <div v-if="!it.rowsB.length" class="text-muted small mb-2">Bu kategori için tip test şablonu yok.</div>
                    <div v-else class="table-responsive">
                    <table class="table table-bordered align-middle text-center iho-matrix">
                        <colgroup>
                            <col style="width:5%;">
                            <col style="width:12%;">
                            <col style="width:24%;">
                            <col v-for="kv in kvaColsB" :key="'colb'+kv" style="width:6.5%;">
                        </colgroup>
                        <thead>
                            <tr style="background:#e2e8f0;">
                                <th>EVRAK NO</th><th>STANDART</th><th>TİP TEST RAPORU</th>
                                <th v-for="kv in kvaColsB" :key="'b'+kv">{{ kv }} kVA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in it.rowsB" :key="row.tpl.reqId">
                                <tr v-if="row.span">
                                    <td>{{ row.tpl.evrak_no }}</td>
                                    <td>{{ row.tpl.standard }}</td>
                                    <td class="text-start">{{ row.tpl.file_title }}</td>
                                    <td :colspan="kvaColsB.length || 1">
                                        <IhFileCell
                                            :cell="row.span"
                                            :locked="tenderLocked || tenderInactive || deadlinePassed"
                                            :accept="acceptFor(row.span.file_types)"
                                            @pick="(ev)=>onCellFile(row.span, it, ev)"
                                            @open="openCellModal(row.span, it)"
                                        />
                                    </td>
                                </tr>
                                <tr v-else>
                                    <td>{{ row.tpl.evrak_no }}</td>
                                    <td>{{ row.tpl.standard }}</td>
                                    <td class="text-start">{{ row.tpl.file_title }}</td>
                                    <td v-for="kv in kvaColsB" :key="'b'+kv+row.tpl.reqId">
                                        <span v-if="row.perKv[kv]">
                                            <IhFileCell
                                                :cell="row.perKv[kv]"
                                                :locked="tenderLocked || tenderInactive || deadlinePassed"
                                                :accept="acceptFor(row.perKv[kv].file_types)"
                                                @pick="(ev)=>onCellFile(row.perKv[kv], it, ev)"
                                                @open="openCellModal(row.perKv[kv], it)"
                                            />
                                        </span>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    </div>

                    <!-- C: diğer belgeler -->
                    <h6 class="fw-bold mt-4 mb-2">DİĞER BELGELER</h6>
                    <div v-if="!it.rowsC.length" class="text-muted small mb-2">Bu kategori için diğer belge şablonu yok.</div>
                    <div v-else class="table-responsive">
                    <table class="table table-bordered align-middle text-center iho-matrix">
                        <colgroup>
                            <col style="width:5%;">
                            <col style="width:12%;">
                            <col style="width:26%;">
                            <col v-for="kv in kvaColsC" :key="'colc'+kv" style="width:6.3%;">
                        </colgroup>
                        <thead>
                            <tr style="background:#e2e8f0;">
                                <th>EVRAK NO</th><th>STANDART</th><th>BELGE</th>
                                <th v-for="kv in kvaColsC" :key="'c'+kv">{{ kv }} kVA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in it.rowsC" :key="row.tpl.reqId">
                                <tr v-if="row.span">
                                    <td>{{ row.tpl.evrak_no }}</td>
                                    <td>{{ row.tpl.standard }}</td>
                                    <td class="text-start">{{ row.tpl.file_title }}</td>
                                    <td :colspan="kvaColsC.length || 1">
                                        <IhFileCell
                                            :cell="row.span"
                                            :locked="tenderLocked || tenderInactive || deadlinePassed"
                                            :accept="acceptFor(row.span.file_types)"
                                            @pick="(ev)=>onCellFile(row.span, it, ev)"
                                            @open="openCellModal(row.span, it)"
                                        />
                                    </td>
                                </tr>
                                <tr v-else>
                                    <td>{{ row.tpl.evrak_no }}</td>
                                    <td>{{ row.tpl.standard }}</td>
                                    <td class="text-start">{{ row.tpl.file_title }}</td>
                                    <td v-for="kv in kvaColsC" :key="'c'+kv+row.tpl.reqId">
                                        <span v-if="row.perKv[kv]">
                                            <IhFileCell
                                                :cell="row.perKv[kv]"
                                                :locked="tenderLocked || tenderInactive || deadlinePassed"
                                                :accept="acceptFor(row.perKv[kv].file_types)"
                                                @pick="(ev)=>onCellFile(row.perKv[kv], it, ev)"
                                                @open="openCellModal(row.perKv[kv], it)"
                                            />
                                        </span>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ihale picker modal -->
        <div v-if="showIhaleModal" style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.45);padding:16px;" @click.self="closeIhaleModal">
            <div style="background:#fff;border-radius:16px;max-width:860px;width:100%;max-height:88vh;overflow:auto;padding:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <div style="font-weight:700;font-size:1rem;color:#0f172a;">İhale Seç</div>
                    <button type="button" @click="closeIhaleModal" class="rlist-btn rlist-btn-ghost">Kapat</button>
                </div>
                <div style="display:flex;gap:8px;margin-bottom:12px;">
                    <input v-model="ihaleSearch" @keyup.enter="searchIhaleTable" type="text" placeholder="İhale kodu ara..." style="flex:1;border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:.9rem;outline:none;" />
                    <button type="button" @click="searchIhaleTable" class="rlist-btn rlist-btn-primary">Ara</button>
                    <button type="button" @click="resetIhaleTable" class="rlist-btn rlist-btn-ghost">Sıfırla</button>
                </div>
                <div id="iho_ihale_table"></div>
            </div>
        </div>
    </div>

</template>

<style>
.iho-matrix { table-layout: fixed; width: 100%; }
.iho-matrix th, .iho-matrix td { vertical-align: middle; font-size: .85rem; }
.iho-matrix th { white-space: nowrap; }
.iho-matrix tbody td:nth-child(2), .iho-matrix tbody td:nth-child(3) { text-align: left; }
.iho-matrix tbody td:nth-child(3) { white-space: normal; line-height: 1.35; }
.iho-matrix input[type=file] { display: none; }
.iho-matrix th, .iho-matrix td { padding: 8px 6px !important; }
.tedas-pick input[type=file] { display: none; }
.iho-matrix .btn { white-space: nowrap; }
/* readonly inspect mode: hide action buttons, keep preview */
.iho-ro .btn-outline-primary,
.iho-ro label.btn-light,
.iho-ro button.btn-light:not(.iho-preview) { display: none !important; }
.iho-ro input.form-control:disabled { background: #f8fafc; }
/* ---- ihale picker modal: card rows like the front panels ---- */
#iho_ihale_table {
    height: auto !important;
    border: none !important;
    background: #eef2f7 !important;
    border-radius: 14px !important;
    padding: 6px 10px 12px !important;
}
#iho_ihale_table .divTable {
    height: auto !important;
    max-height: 38vh !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    border-bottom: none !important;
    border-radius: 10px !important;
}
#iho_ihale_table table {
    border-collapse: separate !important;
    border-spacing: 0 7px !important;
    table-layout: fixed !important;
    border: none !important;
    width: 100% !important;
}
#iho_ihale_table thead th {
    background: transparent !important;
    color: #94a3b8 !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    border: none !important;
    padding: 6px 14px !important;
    text-align: left !important;
    text-transform: uppercase !important;
    letter-spacing: .04em !important;
}
#iho_ihale_table thead th:nth-child(2),
#iho_ihale_table tbody td:nth-child(2) {
    width: 180px !important;
}
#iho_ihale_table thead th:nth-child(3),
#iho_ihale_table tbody td:nth-child(3) {
    width: 110px !important;
    text-align: right !important;
}
#iho_ihale_table tbody tr {
    background: #fff !important;
    border-radius: 12px !important;
    box-shadow: 0 2px 8px rgba(15,23,42,.07) !important;
    border: 1px solid #eef2f7 !important;
}
#iho_ihale_table tbody tr:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 6px 18px rgba(15,23,42,.12) !important;
}
#iho_ihale_table tbody td {
    background: transparent !important;
    padding: 12px 14px !important;
    font-size: 13.5px !important;
    color: #334155 !important;
    border: none !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
#iho_ihale_table tbody td:first-child {
    font-weight: 600 !important;
    color: #0f172a !important;
}
#iho_ihale_table tbody td .btn {
    border-radius: 999px !important;
    padding: 7px 18px !important;
    font-weight: 600 !important;
}
#iho_ihale_table .divPagination {
    border-top: none !important;
    justify-content: center !important;
    padding-top: 8px !important;
}
.rlist-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 10px;
    padding: 9px 14px;
    font-size: .85rem;
    font-weight: 600;
    border: 1px solid transparent;
    cursor: pointer;
}
.rlist-btn-primary { background: #FF5A1F; color: #fff; }
.rlist-btn-ghost { background: #f8fafc; color: #334155; border-color: #e2e8f0; }

/* ---- product overview (first screen) ---- */
.iho-prodlist-head {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px;
    background: #f8fafc !important;
    border-bottom: 1px solid #e2e8f0 !important;
}
.iho-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 999px;
    font-size: 12px; font-weight: 600;
}
.iho-chip--attention { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
.iho-chip--done { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.iho-chip--muted { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

.iho-prod-state { font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.iho-prod-state--attention { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
.iho-prod-state--partial { background: #fefce8; color: #a16207; border: 1px solid #fde68a; }
.iho-prod-state--done { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }

/* product table — server-side, card rows */
.iho-prod-searchrow {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    padding: 12px 16px; border-bottom: 1px solid #f1f5f9;
}
.iho-search-wrap { position: relative; flex: 1 1 280px; max-width: 460px; }
.iho-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
.iho-search-wrap input { padding-left: 36px; height: 42px; border-radius: 10px; }
.iho-prod-progress { font-size: 12.5px; font-weight: 600; color: #475569; }

#iho_prod_table { padding: 4px 14px 12px; }
#iho_prod_table table {
    border-collapse: separate !important;
    border-spacing: 0 8px !important;
    border: none !important;
    width: 100% !important;
    table-layout: fixed !important;
}
#iho_prod_table thead { display: none !important; }
#iho_prod_table tbody td:nth-child(1) { width: 34%; }
#iho_prod_table tbody td:nth-child(2) { width: 20%; }
#iho_prod_table tbody td:nth-child(3) { width: 14%; }
#iho_prod_table tbody td:nth-child(4) { width: 13%; }
#iho_prod_table tbody td:nth-child(5) { width: 10%; }
#iho_prod_table tbody td:nth-child(6) { width: 9%; }
#iho_prod_table thead th {
    background: transparent !important;
    color: #94a3b8 !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: .04em;
    border: none !important;
    padding: 6px 12px !important;
    text-align: left;
}
#iho_prod_table tbody tr {
    background: #fff !important;
    box-shadow: 0 1px 3px rgba(15,23,42,.06);
    transition: transform .15s ease, box-shadow .15s ease;
}
#iho_prod_table tbody tr:hover { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(15,23,42,.10); }
#iho_prod_table tbody td {
    border: none !important;
    padding: 13px 12px !important;
    vertical-align: middle;
    background: transparent !important;
}
#iho_prod_table tbody tr td:first-child { border-radius: 12px 0 0 12px; }
#iho_prod_table tbody td:last-child { border-radius: 0 12px 12px 0; }
#iho_prod_table tbody tr.is-attention td:first-child { box-shadow: inset 4px 0 0 #f97316; }
#iho_prod_table tbody tr.is-partial td:first-child { box-shadow: inset 4px 0 0 #eab308; }
#iho_prod_table tbody tr.is-done td:first-child { box-shadow: inset 4px 0 0 #16a34a; }
#iho_prod_table tbody td .iho-pt-btn {
    border: 1px solid #e2e8f0; background: #fff; color: #0f172a;
    border-radius: 9px; padding: 7px 12px; font-size: 12px; font-weight: 700; cursor: pointer;
}
#iho_prod_table tbody td .iho-pt-btn:hover { border-color: #FF5A1F; color: #FF5A1F; }

.iho-pt-product { display: flex; flex-direction: column; gap: 3px; text-align: left; }
.iho-pt-title { font-size: 13.5px; font-weight: 700; color: #0f172a; line-height: 1.3; }
.iho-pt-meta { display: flex; align-items: center; gap: 8px; font-size: 11.5px; color: #64748b; flex-wrap: wrap; }
.iho-pt-code { background: #f1f5f9; color: #334155; padding: 1px 7px; border-radius: 6px; font-weight: 700; }
.iho-pt-cat { font-size: 12px; color: #475569; text-align: left; }
.iho-pt-evrak { display: flex; flex-direction: column; gap: 5px; align-items: center; }
.iho-pt-bar { width: 100%; height: 6px; background: #f1f5f9; border-radius: 999px; overflow: hidden; }
.iho-pt-bar > span { display: block; height: 100%; background: linear-gradient(90deg, #fb923c, #16a34a); border-radius: 999px; }
.iho-pt-evrak-label { font-size: 11.5px; font-weight: 700; color: #334155; }
.iho-pt-missing { display: flex; flex-direction: column; gap: 2px; align-items: center; font-size: 11.5px; font-weight: 600; }
.iho-pt-missing .warn { color: #b45309; }
.iho-pt-missing .ok { color: #047857; }
.iho-pt-missing .bad { color: #b91c1c; }

/* product pager (centered) */
.iho-pager { display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap; }
.iho-pager-select { flex: 0 1 480px; max-width: 480px; min-width: 220px; }
.iho-pager-btn {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #0f172a;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s ease;
}
.iho-pager-btn:hover:not(:disabled) { background: #eef2f7; }
.iho-pager-btn:disabled { opacity: .45; cursor: default; }
.iho-pager-back {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: none;
    background: transparent;
    color: #475569;
    border-radius: 9px;
    padding: 8px 10px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s ease, color .15s ease;
}
.iho-pager-back:hover { background: #f1f5f9; color: #0f172a; }
.iho-pager-sep { width: 1px; height: 24px; background: #e2e8f0; flex: 0 0 1px; }

/* attachment rows — TEDAŞ / Genel Değerlendirme Görüşü */
.iho-attach-list { display: flex; flex-direction: column; gap: 6px; }
.iho-attach { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; min-height: 44px; }
.iho-attach.is-pending { border-style: dashed; background: #fffbeb; }
.iho-attach-ic { width: 28px; height: 28px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 28px; }
.iho-attach.is-pending .iho-attach-ic { background: #fef3c7; color: #b45309; }
.iho-attach-name { font-size: 12.5px; font-weight: 600; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 280px; }
.iho-attach-pill { font-size: 10.5px; font-weight: 700; color: #fff; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
.iho-attach-note { font-size: 11px; color: #94a3b8; }
.iho-attach-spacer { flex: 1 1 auto; }
.iho-attach-btn { display: inline-flex; align-items: center; gap: 4px; border: 1px solid #e2e8f0; background: #fff; border-radius: 8px; padding: 4px 10px; font-size: 11.5px; font-weight: 600; color: #0f172a; cursor: pointer; margin: 0; }
.iho-attach-btn:hover { border-color: #94a3b8; }
.iho-attach-btn.danger { color: #b91c1c; border-color: #fecaca; }
.iho-attach-btn.danger:hover { background: #fef2f2; }
.iho-attach-btn input { display: none; }
.iho-attach-add { display: inline-flex; align-items: center; gap: 6px; border: 1.5px dashed #cbd5e1; border-radius: 10px; padding: 7px 14px; font-size: 12px; font-weight: 700; color: #2f80ed; background: #f8fafc; cursor: pointer; width: max-content; margin: 0; }
.iho-attach-add:hover { border-color: #2f80ed; background: #eff6ff; }
.iho-attach-add input { display: none; }
.iho-attach-empty { font-size: 12px; color: #94a3b8; }
</style>
