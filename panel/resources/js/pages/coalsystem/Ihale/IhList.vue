<script>
    import { useRouter } from 'vue-router';
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import Swal from 'sweetalert2';
    import { fmtDateTime } from '@/lib/dateUtils';

    export default {
        breadcrumbs: {
            list: [ { title: 'İhale Yönetimi', path: '/coalpanel/ihaleler' } ],
            title: 'İhaleler'
        },
        setup() {
            return {
                useNavigationStore,
                useAuthStore,
                PickleTable,
                Plib,
                Swal,
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTable();
            setTimeout(() => {
                this.navigationStore.toggle(false);
                this.handleResponsiveTable();
            }, 300);
        },
        beforeUnmount(){
            if(this._tableResizeHandler){
                window.removeEventListener('resize', this._tableResizeHandler);
            }
        },
        data() {
            return {
                plib : new Plib(),
                router : useRouter(),
                navigationStore : useNavigationStore(),
                authStore    : useAuthStore(),
                _tableResizeHandler : null,
            }
        },
        computed: {
            isReseller(){ return this.authStore.typeKey === 'op-pert-reseller'; },
            canManage(){ return this.authStore.permissions?.includes('per-041-03') && !this.isReseller; },
        },
        methods: {
            handleResponsiveTable(){
                const container = document.getElementById('div_table');
                if(!container) return;

                const table = container.querySelector('.pickletable table');
                if(!table) return;

                if(this._tableResizeHandler){
                    window.removeEventListener('resize', this._tableResizeHandler);
                }

                this._tableResizeHandler = () => {
                    if(window.innerWidth < 768){
                        container.style.overflowX = 'auto';
                        table.style.minWidth = '760px';
                        table.style.width = 'max-content';
                    } else {
                        container.style.overflowX = 'visible';
                        table.style.minWidth = '100%';
                        table.style.width = '100%';
                    }
                };

                this._tableResizeHandler();
                window.addEventListener('resize', this._tableResizeHandler);
            },
            searchTable(){
                this.table.setFilter([{
                    key   : 'all',
                    type  : '=',
                    value : document.getElementById('mainSearch').value.trim()
                }]);
            },
            resetSearch(){
                document.getElementById('mainSearch').value = '';
                this.table.setFilter([]);
            },
            openEdit(qnid){
                this.router.push({ name : 'IhForm', params : { id : qnid } });
            },
            openOffers(qnid){
                this.router.push({ name : 'IhOfferList', query : { ihale : qnid } });
            },
            esc(s){
                return String(s ?? '').replace(/[&<>"']/g, c => ({ '&' : '&amp;', '<' : '&lt;', '>' : '&gt;', '"' : '&quot;', "'" : '&#39;' }[c]));
            },
            async activateTender(qnid, label){
                const confirm = await Swal.fire({
                    icon : 'question',
                    title : 'İhaleyi Aktif Et',
                    html : '<div style="text-align:left;font-size:13px;">' + (label ? '<b>' + this.esc(label) + '</b> ' : '') + 'aktif edilecek ve <b>teklif alımı açılacak</b>.<br><br>Tedarikçiler bu ihale için teklif hazırlayabilecek.</div>',
                    showCancelButton : true,
                    confirmButtonText : 'Evet, aktif et',
                    cancelButtonText : 'Vazgeç',
                    reverseButtons : true,
                });
                if(!confirm.isConfirmed) return;

                this.navigationStore.toggle(true);
                const fd = new FormData();
                fd.append('id', qnid);
                const rsp = await this.plib.request({ url : '/api/v1/tenders/activate', method : 'POST' }, null, fd);

                if(rsp?.success){
                    // Include the action column key (`id`) so PickleTable re-runs its
                    // columnFormatter and swaps 🚀/🗑 for Teklif Ver/✔.
                    this.table.updateRow(qnid, { status : 'tender-activated**İhale Aktif**-', id : qnid });
                    this.plib.toast(this.Swal, 'success', 'İhale aktif edildi, teklif alımı açıldı.');
                } else {
                    this.plib.toast(this.Swal, 'error', rsp?.msg || 'İhale aktif edilemedi.');
                }
                setTimeout(() => this.navigationStore.toggle(false), 300);
            },
            async openWinnerModal(qnid, label){
                this.navigationStore.toggle(true);
                let rows = [];
                try {
                    const fd = new FormData();
                    fd.append('tableReq', JSON.stringify({
                        filter : [
                            { key : 'form-type', type : '=', value : 'op-doc-ihale-offer-form' },
                            { key : 'type', type : '=', value : 'op-doc-ihale-offer' },
                            { key : 'attr', type : 'ihale_qnid', value : qnid },
                        ],
                        scale : { page : 1, limit : 100 },
                    }));
                    const rsp = await this.plib.request({ url : '/api/v1/table/documents', method : 'POST' }, null, fd);
                    rows = rsp?.data?.data || rsp?.data || [];
                } catch(e) {}
                this.navigationStore.toggle(false);

                const map = new Map();
                rows.forEach((r) => {
                    // Rejected offers cannot win; hide those suppliers from the picker.
                    if (String(r.status || '').split('**')[0] === 'offer-rejected') return;
                    let attrs = [];
                    try { attrs = JSON.parse(r.main_attr || '[]'); } catch(e) {}
                    const g = (k) => (attrs.find(x => x.Key === k) || {}).Value;
                    const s = g('supplier_qnid');
                    if (!s) return;
                    if (!map.has(s)) map.set(s, { qnid : s, title : g('supplier_title') || s, items : 0 });
                    map.get(s).items += Number(r.row_count || 0) || 1;
                });
                const list = [...map.values()];

                if (!list.length) {
                    this.plib.toast(this.Swal, 'info', 'Bu ihaleye henüz teklif verilmemiş.');
                    return;
                }

                const html = '<div style="text-align:left;max-height:52vh;overflow:auto;">'
                    + '<div style="font-size:12.5px;color:#64748b;margin-bottom:10px;">Tamamlanınca teklifler kilitlenir, seçilen tedarikçi kazanan olarak dondurulur.</div>'
                    + list.map((s, i) => '<label style="display:flex;align-items:center;gap:10px;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;margin-bottom:8px;cursor:pointer;">'
                        + '<input type="radio" name="ih_winner" value="' + i + '" style="margin:0;">'
                        + '<span style="flex:1;min-width:0;"><span style="display:block;font-size:13px;font-weight:700;color:#0f172a;">' + this.esc(s.title) + '</span>'
                        + '<span style="display:block;font-size:11.5px;color:#64748b;">' + s.items + ' kalem teklif</span></span>'
                        + '</label>').join('')
                    + '</div>';

                const pick = await Swal.fire({
                    title : 'Kazanan Tedarikçi',
                    html,
                    width : 560,
                    showCancelButton : true,
                    confirmButtonText : 'Tamamla ve Kilitle',
                    cancelButtonText : 'Vazgeç',
                    reverseButtons : true,
                    preConfirm : () => {
                        const el = document.querySelector('input[name="ih_winner"]:checked');
                        if (!el) {
                            Swal.showValidationMessage('Bir kazanan seçmelisiniz.');
                            return false;
                        }
                        return list[Number(el.value)];
                    },
                });
                if (!pick.isConfirmed || !pick.value) return;

                this.navigationStore.toggle(true);
                const fd2 = new FormData();
                fd2.append('id', qnid);
                fd2.append('winner_supplier_qnid', pick.value.qnid);
                fd2.append('winner_supplier_title', pick.value.title);
                const done = await this.plib.request({ url : '/api/v1/tenders/complete', method : 'POST' }, null, fd2);

                if (done?.success) {
                    this.table.updateRow(qnid, { status : 'tender-complated**İhale Tamamlandı**-', winner_supplier_title : pick.value.title, id : qnid });
                    this.plib.toast(this.Swal, 'success', 'İhale tamamlandı — kazanan: ' + pick.value.title);
                } else {
                    this.plib.toast(this.Swal, 'error', done?.msg || 'İhale tamamlanamadı.');
                }
                setTimeout(() => this.navigationStore.toggle(false), 300);
            },
            async removeIhale(qnid, label){
                const confirm = await Swal.fire({
                    icon : 'warning',
                    title : 'İhaleyi Sil',
                    text : (label || 'Bu ihale') + ' pasife alınacaktır. Emin misiniz?',
                    showCancelButton : true,
                    confirmButtonText : 'Evet, sil',
                    cancelButtonText : 'Vazgeç',
                    reverseButtons : true,
                });
                if(!confirm.isConfirmed) return;

                this.navigationStore.toggle(true);
                const rsp = await this.plib.request({ url : '/api/v1/document/' + qnid, method : 'DELETE' }, null);
                if(rsp?.success){
                    this.table.deleteRow(qnid);
                } else {
                    this.plib.toast(this.Swal, 'error', rsp?.msg || 'Silinemedi.');
                }
                setTimeout(() => this.navigationStore.toggle(false), 300);
            },
            buildTable(){
                const headers = [
                    {
                        title : 'İhale Kodu',
                        key   : 'ihale_kodu',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,rowData) => {
                            const wrap = document.createElement('div');
                            wrap.className = 'ih-row-code';
                            const code = document.createElement('span');
                            code.className = 'ih-code';
                            code.textContent = rowData.ihale_kodu || '—';
                            wrap.appendChild(code);
                            return wrap;
                        }
                    },{
                        title : 'Ürün',
                        key   : 'product_count',
                        order : false,
                        colAlign : 'center',
                        type  : 'string',
                        columnFormatter : (elm,rowData) => {
                            const b = document.createElement('span');
                            b.className = 'ih-count';
                            b.textContent = (rowData.product_count ?? 0) + ' kalem';
                            return b;
                        }
                    },{
                        title : 'Şartname',
                        key   : 'spec_count',
                        order : false,
                        colAlign : 'center',
                        type  : 'string',
                        columnFormatter : (elm,rowData) => {
                            const s = document.createElement('span');
                            s.className = 'ih-pill ' + ((rowData.spec_count ?? 0) > 0 ? 'ih-pill--ok' : 'ih-pill--muted');
                            s.textContent = (rowData.spec_count ?? 0) > 0 ? 'Yüklü' : 'Yok';
                            return s;
                        }
                    },{
                        title : 'Durum',
                        key   : 'status',
                        order : false,
                        colAlign : 'center',
                        type  : 'string',
                        columnFormatter : (elm,rowData) => {
                            const raw = (rowData.status || '').split('**');
                            const opKey = raw[0] || '';
                            const label = raw[1] || '';
                            const wrap = document.createElement('div');
                            wrap.className = 'ih-status-cell';
                            const b = document.createElement('span');
                            if (opKey === 'tender-complated') {
                                b.className = 'ih-pill ih-pill--done';
                                b.textContent = label || 'İhale Tamamlandı';
                            } else if (opKey === 'tender-activated') {
                                b.className = 'ih-pill ih-pill--live';
                                b.textContent = label || 'İhale Aktif';
                            } else {
                                b.className = 'ih-pill ih-pill--muted';
                                b.textContent = label || 'İhale Oluşturuldu';
                            }
                            wrap.appendChild(b);
                            if (opKey === 'tender-complated' && rowData.winner_supplier_title) {
                                const w = document.createElement('div');
                                w.className = 'ih-winner';
                                w.textContent = '🏆 ' + rowData.winner_supplier_title;
                                wrap.appendChild(w);
                            }
                            return wrap;
                        }
                    },{
                        title : 'Son Teklif',
                        key   : 'teklif_son_tarihi',
                        order : false,
                        colAlign : 'center',
                        type  : 'string',
                        columnFormatter : (elm, rowData) => {
                            const b = document.createElement('span');
                            b.className = 'ih-pill ih-pill--muted';
                            b.textContent = rowData.teklif_son_tarihi || '—';
                            return b;
                        }
                    },{
                        title : 'Oluşturulma',
                        key   : 'created_at_fmt',
                        order : false,
                        type  : 'string',
                    },{
                        title : 'Aksiyonlar',
                        key   : 'id',
                        order : false,
                        colAlign : 'center',
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('d-flex','align-items-center','justify-content-center','gap-1');

                            const opKey = String(rowData.status || '').split('**')[0] || 'tender-created';
                            const isCreated = opKey === 'tender-created';
                            const isActive = opKey === 'tender-activated';
                            const isDone = opKey === 'tender-complated';

                            if(this.canManage && !isDone){
                                const editBtn = document.createElement('button');
                                editBtn.classList.add('btn','btn-secondary','action-icon-btn');
                                editBtn.title = 'Düzenle';
                                editBtn.innerHTML = '<i class="ki-outline ki-pencil fs-2"></i>';
                                editBtn.onclick = () => this.openEdit(columnData);
                                span.appendChild(editBtn);
                            }

                            if(this.canManage){
                                const offersBtn = document.createElement('button');
                                offersBtn.classList.add('btn','btn-secondary','action-icon-btn');
                                offersBtn.title = 'Teklifleri Gör';
                                offersBtn.innerHTML = '<i class="ki-outline ki-document fs-2"></i>';
                                offersBtn.onclick = () => this.openOffers(columnData);
                                span.appendChild(offersBtn);
                            }

                            if(isActive && this.authStore.permissions?.includes('per-041-03-01')){
                                const offerBtn = document.createElement('button');
                                offerBtn.classList.add('btn','btn-secondary','action-icon-btn');
                                offerBtn.title = 'Teklif Ver';
                                offerBtn.innerHTML = '<i class="ki-outline ki-entrance-right fs-2"></i>';
                                offerBtn.onclick = () => this.router.push({ name : 'IhOffer', query : { ihale : columnData } });
                                span.appendChild(offerBtn);
                            }

                            if(this.canManage && isCreated){
                                const activateBtn = document.createElement('button');
                                activateBtn.classList.add('btn','btn-secondary','action-icon-btn');
                                activateBtn.title = 'İhaleyi Aktif Et (teklif alımını aç)';
                                activateBtn.innerHTML = '<i class="ki-outline ki-rocket fs-2"></i>';
                                activateBtn.onclick = () => this.activateTender(columnData, rowData.ihale_kodu);
                                span.appendChild(activateBtn);
                            }

                            if(this.canManage && isActive){
                                const doneBtn = document.createElement('button');
                                doneBtn.classList.add('btn','btn-secondary','action-icon-btn');
                                doneBtn.title = 'İhaleyi Tamamla (kazananı seç ve kilitle)';
                                doneBtn.innerHTML = '<i class="ki-outline ki-check-circle fs-2"></i>';
                                doneBtn.onclick = () => this.openWinnerModal(columnData, rowData.ihale_kodu);
                                span.appendChild(doneBtn);
                            }

                            if(this.canManage && !isDone){
                                const delBtn = document.createElement('button');
                                delBtn.classList.add('btn','btn-secondary','action-icon-btn','action-icon-btn--danger');
                                delBtn.title = 'Sil';
                                delBtn.innerHTML = '<i class="ki-outline ki-trash fs-2"></i>';
                                delBtn.onclick = () => this.removeIhale(columnData, rowData.ihale_kodu);
                                span.appendChild(delBtn);
                            }

                            return span;
                        }
                    }
                ];

                this.table = new PickleTable({
                    container : '#div_table',
                    headers   : headers,
                    pageLimit : 10,
                    height    : '70vh',
                    type      : 'ajax',
                    columnSearch : false,
                    paginationType : 'number',
                    ajax : {
                        url : '/api/v1/table/documents',
                        data : {}
                    },
                    initialFilter : [
                        { key : 'form-type', type : '=', value : 'op-doc-ihale-form' },
                        { key : 'type', type : '=', value : 'op-doc-ihale' }
                    ],
                    rowFormatter : (elm, data) => {
                        let attrs = [];
                        try { attrs = JSON.parse(data.main_attr || '[]'); } catch(e) {}
                        attrs.forEach(a => { data[a.Key] = a.Value; });

                        const prodRows = new Set();
                        let spec = 0;
                        attrs.forEach(a => {
                            const tag = a.Key || '';
                            // Count full row keys. Form-engine ids are `new-<timestamp>`,
                            // so splitting on '-' collapses every new row into "new".
                            if(tag.startsWith('product_qnid**ihale_product_rows')) prodRows.add(tag.split('**')[2] || '');
                            if(tag.startsWith('ihale_spec_file**ihale_spec')) spec++;
                        });
                        // Server-side row_count is authoritative; fall back to parsed rows.
                        data.product_count = Number(data.row_count) > 0 ? Number(data.row_count) : prodRows.size;
                        data.spec_count = spec;
                        data.created_at_fmt = fmtDateTime(data.created_at);
                        return data;
                    },
                });

                requestAnimationFrame(() => this.handleResponsiveTable());
            },
        }
    }
</script>

<template>
    <div class="card rlist-card">
        <div class="card-header rlist-header">
            <div class="rlist-search-group">
                <div class="rlist-search-wrap">
                    <i class="ki-duotone ki-magnifier fs-4 rlist-search-icon">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text" id="mainSearch" class="rlist-search-input" placeholder="İhale kodu ara...">
                </div>
                <button type="button" class="rlist-btn rlist-btn-primary" @click="searchTable">
                    <i class="ki-outline ki-magnifier fs-5"></i> Ara
                </button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="resetSearch">Sıfırla</button>
            </div>
            <div class="rlist-toolbar">
                <router-link
                    v-if="canManage"
                    :to="{ name: 'IhForm' }"
                    class="rlist-btn rlist-btn-create"
                >
                    <i class="ki-outline ki-plus fs-5"></i> Yeni İhale
                </router-link>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="div_table"></div>
        </div>
    </div>
</template>

<style scoped>
.ih-row-code { display: flex; align-items: center; }
.ih-code {
    font-weight: 700;
    color: #0f172a;
    font-size: 13px;
    letter-spacing: .01em;
}
.ih-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 10px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-size: 12px;
    font-weight: 600;
}
.ih-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}
.ih-pill--ok { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.ih-pill--muted { background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; }
.ih-pill--live { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.ih-pill--done { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.ih-status-cell { display: flex; flex-direction: column; align-items: center; gap: 3px; }
.ih-winner { font-size: 11px; font-weight: 700; color: #475569; white-space: nowrap; }
</style>
