<script>
    import { useRouter } from 'vue-router';
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import Swal from 'sweetalert2';
    import { fmtDateTime } from '@/lib/dateUtils';
    import { tenderMeta, parseStatus } from '@/lib/tenderStatus';

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
            }, 300);
            document.addEventListener('click', this._outsideFilter);
        },
        beforeUnmount(){
            document.removeEventListener('click', this._outsideFilter);
        },
        data() {
            return {
                plib : new Plib(),
                router : useRouter(),
                navigationStore : useNavigationStore(),
                authStore    : useAuthStore(),
                showFilter : false,
                statusChoice : '',
            }
        },
        computed: {
            isReseller(){ return this.authStore.typeKey === 'op-pert-reseller'; },
            canManage(){ return this.authStore.permissions?.includes('per-041-03') && !this.isReseller; },
            canOffer(){ return this.authStore.permissions?.includes('per-041-03-01'); },
            isIhalePanel(){ return this.$route.path.startsWith('/ihalepanel'); },
            formRouteName(){ return this.isIhalePanel ? 'IhalePanelIhForm' : 'IhForm'; },
            offersRouteName(){ return this.isIhalePanel ? 'IhalePanelIhOfferList' : 'IhOfferList'; },
            offerRouteName(){ return this.isIhalePanel ? 'IhalePanelIhOffer' : 'IhOffer'; },
        },
        methods: {
            _outsideFilter(e){
                if(this.showFilter && !e.target.closest?.('.ihale-filter-dd-wrap')){
                    this.showFilter = false;
                }
            },
            searchTable(){
                const inp = this.$refs.tedarikSearch || document.getElementById('mainSearch');
                this.table.setFilter([{
                    key   : 'all',
                    type  : '=',
                    value : (inp?.value || '').trim()
                }]);
            },
            resetSearch(){
                const inp = this.$refs.tedarikSearch || document.getElementById('mainSearch');
                if(inp) inp.value = '';
                this.statusChoice = '';
                this.table.setFilter([]);
            },
            pickStatus(v){
                this.statusChoice = v;
                this.showFilter = false;
                if(!v){
                    this.table.setFilter([]);
                    return;
                }
                this.table.setFilter([{ key : 'transactions', type : '=', value : v }]);
            },
            exportTable(){
                // Server-side Excel export honours the active filter and covers
                // the whole list — not just the current page.
                this.plib.openTab('POST', '/api/v1/export/ihale', this.table?.currentFilter || [], '_blank');
            },
            formatDate(val){
                if(!val || val === '—') return '—';
                if(String(val).includes('.')) return String(val).slice(0, 10);
                if(String(val).includes('-')){
                    const p = String(val).split(' ')[0].split('-');
                    if(p.length === 3) return `${p[2]}.${p[1]}.${p[0]}`;
                }
                try { const d = new Date(val); if(!isNaN(d)) return d.toLocaleDateString('tr-TR'); } catch(e) {}
                return val;
            },
            isPastDate(val){
                if(!val) return false;
                const s = String(val).trim();
                let d = null;
                if(/^\d{4}-\d{2}-\d{2}/.test(s)) d = new Date(s.slice(0, 10) + 'T00:00:00');
                else {
                    const m = s.match(/^(\d{2})\.(\d{2})\.(\d{4})/);
                    if(m) d = new Date(`${m[3]}-${m[2]}-${m[1]}T00:00:00`);
                }
                if(!d || isNaN(d)) return false;
                return d.getTime() < Date.now();
            },
            openEdit(qnid){
                this.router.push({ name : this.formRouteName, params : { id : qnid } });
            },
            openOffers(qnid){
                this.router.push({ name : this.offersRouteName, query : { ihale : qnid } });
            },
            openOfferForTender(qnid){
                this.router.push({ name : this.offerRouteName, query : { ihale : qnid } });
            },
            esc(s){
                return String(s ?? '').replace(/[&<>"']/g, c => ({ '&' : '&amp;', '<' : '&lt;', '>' : '&gt;', '"' : '&quot;', "'" : '&#39;' }[c]));
            },
            openActionsModal(rowData, columnData){
                const opKey = String(rowData.status || '').split('**')[0] || 'tender-created';
                const isCreated = opKey === 'tender-created';
                const isActive = opKey === 'tender-activated';
                const isDone = opKey === 'tender-complated';
                const kod = rowData.ihale_kodu || 'İhale';

                const btn = (id, label, icon, bg) => `<button id="${id}" class="btn" style="background:${bg};color:#fff;border:none;font-weight:600;padding:12px 16px;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:8px;"><i class="${icon}" style="font-size:16px;"></i> ${label}</button>`;

                const items = [];
                if(this.canManage && !isDone) items.push({ id : 'aks-edit', html : btn('aks-edit', 'Düzenle', 'ki-outline ki-pencil', '#6366f1') });
                if(this.canManage) items.push({ id : 'aks-offers', html : btn('aks-offers', 'Teklifleri Gör', 'ki-outline ki-document', '#0e7490') });
                if(isActive && this.canOffer) items.push({ id : 'aks-offer', html : btn('aks-offer', 'Teklif Ver', 'ki-outline ki-entrance-right', '#059669') });
                if(this.canManage && isCreated) items.push({ id : 'aks-activate', html : btn('aks-activate', 'İhaleyi Aktif Et', 'ki-outline ki-rocket', '#f59e0b') });
                if(this.canManage && isActive) items.push({ id : 'aks-complete', html : btn('aks-complete', 'İhaleyi Tamamla', 'ki-outline ki-check-circle', '#22c55e') });
                if(this.canManage && !isDone) items.push({ id : 'aks-delete', html : btn('aks-delete', 'Sil', 'ki-outline ki-trash', '#ef4444') });

                if(!items.length) return;

                Swal.fire({
                    title : 'Aksiyonlar',
                    html : `<div style="text-align:left;font-size:12.5px;color:#64748b;margin-bottom:10px;"><b style="color:#0f172a;">${this.esc(kod)}</b></div><div class="d-flex flex-column gap-2 p-2">${items.map(i => i.html).join('')}</div>`,
                    showConfirmButton : false,
                    showCloseButton : true,
                });

                const on = (id, fn) => {
                    const el = document.getElementById(id);
                    if(el) el.addEventListener('click', () => { Swal.close(); fn(); });
                };
                on('aks-edit', () => this.openEdit(columnData));
                on('aks-offers', () => this.openOffers(columnData));
                on('aks-offer', () => this.openOfferForTender(columnData));
                on('aks-activate', () => this.activateTender(columnData, rowData.ihale_kodu));
                on('aks-complete', () => this.openWinnerModal(columnData, rowData.ihale_kodu));
                on('aks-delete', () => this.removeIhale(columnData, rowData.ihale_kodu));
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
                        width : '150px',
                        columnFormatter : (elm,row) => {
                            const v = row.ihale_kodu || '—';
                            const wrap = document.createElement('div');
                            wrap.style.display = 'flex';
                            wrap.style.flexDirection = 'column';
                            wrap.style.gap = '2px';
                            const span = document.createElement('span');
                            span.textContent = v;
                            span.style.display = 'inline-flex';
                            span.style.alignItems = 'center';
                            span.style.padding = '3px 10px';
                            span.style.borderRadius = '7px';
                            span.style.background = '#eef2ff';
                            span.style.color = '#4338ca';
                            span.style.fontFamily = 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace';
                            span.style.fontSize = '11.5px';
                            span.style.fontWeight = '700';
                            span.style.letterSpacing = '.02em';
                            span.style.width = 'fit-content';
                            span.title = v;
                            wrap.appendChild(span);
                            const opKey = String(row.status || '').split('**')[0] || '';
                            if (opKey === 'tender-complated' && row.winner_supplier_title) {
                                const link = document.createElement('span');
                                link.textContent = '🏆 ' + row.winner_supplier_title;
                                link.style.display = 'inline-flex';
                                link.style.alignItems = 'center';
                                link.style.padding = '2px 8px';
                                link.style.borderRadius = '7px';
                                link.style.background = '#fffbeb';
                                link.style.border = '1px solid #fde68a';
                                link.style.color = '#b45309';
                                link.style.fontSize = '10.5px';
                                link.style.fontWeight = '700';
                                link.style.width = 'fit-content';
                                wrap.appendChild(link);
                            }
                            return wrap;
                        }
                    },{
                        title : 'Ürün',
                        key   : 'product_count',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,row) => {
                            const v = (row.product_count ?? 0) + ' kalem';
                            const span = document.createElement('span');
                            span.textContent = v;
                            span.title = v;
                            span.style.display = 'inline-flex';
                            span.style.alignItems = 'center';
                            span.style.padding = '3px 10px';
                            span.style.borderRadius = '7px';
                            span.style.background = '#f1f5f9';
                            span.style.color = '#475569';
                            span.style.fontSize = '11.5px';
                            span.style.fontWeight = '700';
                            span.style.whiteSpace = 'nowrap';
                            return span;
                        }
                    },{
                        title : 'Şartname',
                        key   : 'spec_count',
                        order : true,
                        type  : 'string',
                        width : '130px',
                        columnFormatter : (elm,row) => {
                            const has = (row.spec_count ?? 0) > 0;
                            const s = document.createElement('span');
                            s.textContent = has ? 'Yüklü' : 'Yok';
                            s.style.display = 'inline-flex';
                            s.style.alignItems = 'center';
                            s.style.padding = '3px 10px';
                            s.style.borderRadius = '7px';
                            s.style.background = has ? '#ecfdf5' : '#f1f5f9';
                            s.style.color = has ? '#047857' : '#64748b';
                            s.style.fontSize = '11.5px';
                            s.style.fontWeight = '700';
                            return s;
                        }
                    },{
                        title : 'Son Teklif',
                        key   : 'teklif_son_tarihi',
                        order : true,
                        type  : 'string',
                        width : '120px',
                        columnFormatter : (elm,row) => {
                            const past = String(row.status || '').split('**')[0] !== 'tender-complated'
                                && this.isPastDate(row.teklif_son_tarihi);
                            const s = document.createElement('span');
                            s.textContent = this.formatDate(row.teklif_son_tarihi || '—');
                            s.style.color = past ? '#dc2626' : '#94a3b8';
                            s.style.fontSize = '12.5px';
                            s.style.fontVariantNumeric = 'tabular-nums';
                            s.style.whiteSpace = 'nowrap';
                            if (past) s.title = 'Teklif süresi geçti';
                            return s;
                        }
                    },{
                        title : 'Oluşturulma',
                        key   : 'created_at_fmt',
                        order : true,
                        type  : 'string',
                        width : '120px',
                        columnFormatter : (elm,row) => {
                            const s = document.createElement('span');
                            s.textContent = this.formatDate(row.created_at_fmt || row.created_at || '—');
                            s.style.color = '#94a3b8';
                            s.style.fontSize = '12.5px';
                            s.style.fontVariantNumeric = 'tabular-nums';
                            s.style.whiteSpace = 'nowrap';
                            return s;
                        }
                    },{
                        title : 'Durum',
                        key   : 'status',
                        order : true,
                        type  : 'string',
                        width : '225px',
                        columnFormatter : (elm,row) => {
                            const meta = tenderMeta(parseStatus(row.status).opKey);
                            const btn = document.createElement('span');
                            btn.textContent = meta.label;
                            btn.style.display = 'inline-flex';
                            btn.style.alignItems = 'center';
                            btn.style.justifyContent = 'center';
                            btn.style.padding = '4px 10px';
                            btn.style.borderRadius = '8px';
                            btn.style.fontSize = '11.5px';
                            btn.style.fontWeight = '700';
                            btn.style.whiteSpace = 'normal';
                            btn.style.lineHeight = '1.25';
                            btn.style.textAlign = 'center';
                            btn.style.maxWidth = '100%';
                            btn.style.cursor = 'default';
                            btn.style.border = '1px solid ' + meta.color + '33';
                            btn.style.gap = '6px';
                            btn.style.background = meta.color + '14';
                            btn.style.color = meta.color;
                            const icon = document.createElement('i');
                            icon.className = meta.icon;
                            icon.style.fontSize = '13px';
                            btn.prepend(icon);
                            return btn;
                        }
                    },{
                        title : '',
                        key   : 'id',
                        order : false,
                        type  : 'string',
                        width : '195px',
                        columnFormatter : (elm,rowData,columnData) => {
                            const wrap = document.createElement('div');
                            wrap.style.display = 'flex';
                            wrap.style.justifyContent = 'flex-end';
                            wrap.style.alignItems = 'center';
                            wrap.style.gap = '6px';
                            wrap.style.flexWrap = 'wrap';

                            const opKey = String(rowData.status || '').split('**')[0] || 'tender-created';
                            const isActive = opKey === 'tender-activated';
                            const hasAny = this.canManage;

                            // Admin shell: icon buttons like every other admin list
                            if (!this.isIhalePanel) {
                                const mk = (cls, title, icon, handler) => {
                                    const b = document.createElement('button');
                                    b.type = 'button';
                                    b.className = 'ihl-act ' + cls;
                                    b.title = title;
                                    b.innerHTML = '<i class="ki-outline ' + icon + '"></i>';
                                    b.onclick = (e) => { e.stopPropagation(); handler(); };
                                    return b;
                                };
                                if (hasAny) wrap.appendChild(mk('ihl-act--more', 'Aksiyonlar', 'ki-dots-horizontal', () => this.openActionsModal(rowData, columnData)));
                                if (isActive && this.canOffer) {
                                    wrap.appendChild(mk('ihl-act--go', 'Teklif Ver', 'ki-send', () => this.openOfferForTender(columnData)));
                                } else {
                                    wrap.appendChild(mk('ihl-act--edit', 'Düzenle / Detaylar', 'ki-pencil', () => {
                                        if (this.canManage) this.openEdit(columnData);
                                        else this.openOfferForTender(columnData);
                                    }));
                                }
                                return wrap;
                            }

                            let aks = null;
                            if (hasAny) {
                                aks = document.createElement('button');
                                aks.textContent = 'Aksiyonlar';
                                aks.className = 'btn';
                                aks.style.background = '#f1f5f9';
                                aks.style.color = '#475569';
                                aks.style.border = '1px solid transparent';
                                aks.style.borderRadius = '8px';
                                aks.style.padding = '6px 12px';
                                aks.style.fontSize = '11.5px';
                                aks.style.fontWeight = '700';
                                aks.style.cursor = 'pointer';
                                aks.style.flex = '0 0 auto';
                                aks.style.whiteSpace = 'nowrap';
                                aks.style.minHeight = '30px';
                                aks.onmouseenter = () => aks.style.background = '#e2e8f0';
                                aks.onmouseleave = () => aks.style.background = '#f1f5f9';
                                aks.onclick = (e) => { e.stopPropagation(); this.openActionsModal(rowData, columnData); };
                                wrap.appendChild(aks);
                            }

                            const primary = document.createElement('button');
                            primary.className = 'btn';
                            primary.style.borderRadius = '8px';
                            primary.style.padding = '6px 12px';
                            primary.style.fontSize = '11.5px';
                            primary.style.fontWeight = '700';
                            primary.style.cursor = 'pointer';
                            primary.style.flex = '0 0 auto';
                            primary.style.whiteSpace = 'nowrap';
                            primary.style.minHeight = '30px';

                            if (isActive && this.canOffer) {
                                primary.textContent = 'Teklif Ver';
                                primary.style.background = '#0e8ea4';
                                primary.style.color = '#ffffff';
                                primary.style.border = '1px solid #0e8ea4';
                                primary.onmouseenter = () => primary.style.background = '#0c7e90';
                                primary.onmouseleave = () => primary.style.background = '#0e8ea4';
                                primary.onclick = () => this.openOfferForTender(columnData);
                            } else {
                                primary.textContent = 'Detaylar';
                                primary.style.background = '#e6f7fa';
                                primary.style.color = '#0e8ea4';
                                primary.style.border = '1px solid transparent';
                                primary.onmouseenter = () => primary.style.background = '#cff0f5';
                                primary.onmouseleave = () => primary.style.background = '#e6f7fa';
                                primary.onclick = () => {
                                    if (this.canManage) this.openEdit(columnData);
                                    else this.openOfferForTender(columnData);
                                };
                            }
                            wrap.appendChild(primary);
                            return wrap;
                        }
                    }
                ];

                this.table = new PickleTable({
                    container : '#div_table',
                    headers   : headers,
                    pageLimit : 10,
                    height    : this.isIhalePanel ? '75vh' : '70vh',
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
                    nextPageIcon : '<i class="ki-outline ki-arrow-right"></i>',
                    prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter : (elm, data) => {
                        let attrs = [];
                        try { attrs = JSON.parse(data.main_attr || '[]'); } catch(e) { console.warn('[IhList] main_attr parse failed', e); }
                        attrs.forEach(a => { data[a.Key] = a.Value; });

                        const prodRows = new Set();
                        let spec = 0;
                        attrs.forEach(a => {
                            const tag = a.Key || '';
                            if(tag.startsWith('product_qnid**ihale_product_rows')) prodRows.add(tag.split('**')[2] || '');
                            if(tag.startsWith('ihale_spec_file**ihale_spec')) spec++;
                        });
                        data.product_count = Number(data.row_count) > 0 ? Number(data.row_count) : prodRows.size;
                        data.spec_count = spec;
                        data.created_at_fmt = fmtDateTime(data.created_at);
                        return data;
                    },
                });
            },
        }
    }
</script>

<template>
    <!-- ADMIN (CoalPanel) — admin list design -->
    <div v-if="!isIhalePanel" class="card rlist-card mt-10">
        <div class="card-header rlist-header">
            <div class="rlist-search-group">
                <div class="rlist-search-wrap">
                    <i class="ki-duotone ki-magnifier fs-4 rlist-search-icon">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text" id="mainSearch" class="rlist-search-input" placeholder="İhale ara — ihale kodu, ürün, şartname..." @keydown.enter="searchTable">
                </div>
                <select v-model="statusChoice" @change="pickStatus(statusChoice)" class="form-select rlist-system-select" style="min-width:190px; max-width:210px; border-radius:10px; border:1.5px solid #e2e8f0; font-weight:600; font-size:13px;">
                    <option value="">Tüm Durumlar</option>
                    <option value="tender-created">Oluşturulanlar</option>
                    <option value="tender-activated">Aktif İhaleler</option>
                    <option value="tender-complated">Tamamlananlar</option>
                </select>
                <button type="button" class="rlist-btn rlist-btn-primary" @click="searchTable">
                    <i class="ki-outline ki-magnifier fs-5"></i> Ara
                </button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="resetSearch">Sıfırla</button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="exportTable">
                    <i class="ki-outline ki-exit-down fs-5"></i> Excel
                </button>
            </div>
            <div class="rlist-toolbar">
                <router-link v-if="canManage" :to="{ name: formRouteName }" class="rlist-btn rlist-btn-create">
                    <i class="ki-outline ki-plus fs-5"></i> Yeni İhale
                </router-link>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="div_table"></div>
        </div>
    </div>

    <!-- İHALE PANEL (front shell) — tedarik design -->
    <div v-else class="order-list-card tedarik-card ihale-card">
        <div class="tedarik-list-top">
            <div class="tedarik-list-title">
                <span>İhale Listesi</span>
            </div>
            <div class="tedarik-filters">
                <router-link v-if="canManage" :to="{ name: formRouteName }" class="tedarik-filter">
                    <i class="ki-outline ki-plus"></i> Yeni İhale
                </router-link>
                <div class="tedarik-filter-dd-wrap">
                    <a href="javascript:;" class="tedarik-filter" :class="{ active: !!statusChoice }" @click.stop="showFilter = !showFilter">
                        <i class="ki-outline ki-filter"></i> Filtreler
                    </a>
                    <div v-if="showFilter" class="tedarik-dd" @click.stop>
                        <label class="tedarik-dd-item" :class="{ selected: statusChoice === '' }" @click="pickStatus('')">
                            <span class="tedarik-radio" :class="{ on: statusChoice === '' }"></span> Hepsini Göster
                        </label>
                        <label class="tedarik-dd-item" :class="{ selected: statusChoice === 'tender-created' }" @click="pickStatus('tender-created')">
                            <span class="tedarik-radio" :class="{ on: statusChoice === 'tender-created' }"></span> Oluşturulanlar
                        </label>
                        <label class="tedarik-dd-item" :class="{ selected: statusChoice === 'tender-activated' }" @click="pickStatus('tender-activated')">
                            <span class="tedarik-radio" :class="{ on: statusChoice === 'tender-activated' }"></span> Aktif İhaleler
                        </label>
                        <label class="tedarik-dd-item" :class="{ selected: statusChoice === 'tender-complated' }" @click="pickStatus('tender-complated')">
                            <span class="tedarik-radio" :class="{ on: statusChoice === 'tender-complated' }"></span> Tamamlananlar
                        </label>
                    </div>
                </div>
                <a href="javascript:;" class="tedarik-filter" @click="exportTable"><i class="ki-outline ki-exit-down"></i> Excel Çıktı</a>
            </div>
        </div>
        <div class="tedarik-docs-searchrow" style="margin: 0 0 14px;">
            <div class="tedarik-docs-searchbox">
                <i class="ki-outline ki-magnifier tedarik-docs-search-icon"></i>
                <input type="text" ref="tedarikSearch" id="mainSearch" class="tedarik-docs-search-input" placeholder="İhale ara — ihale kodu, ürün, şartname..." @keydown.enter="searchTable">
            </div>
            <button type="button" class="tedarik-btn-light tedarik-docs-btn" @click="searchTable">Ara</button>
            <button type="button" class="tedarik-btn-light tedarik-docs-btn tedarik-docs-btn--ghost" @click="resetSearch">Sıfırla</button>
        </div>
        <div class="order-list-body">
            <div id="div_table"></div>
        </div>
        <div class="tedarik-bottom-note">
            <i class="ki-outline ki-information-5"></i>
            <span>Aktif ihalelerde <b>"Teklif Ver"</b> butonuna tıklayarak tekliflerinizi oluşturmayı unutmayınız.</span>
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
.tedarik-filter.active { color: #059669 !important; }
.tedarik-filter.active i { color: #059669 !important; }
.tedarik-filter-dd-wrap { position: relative; display: inline-flex; }
.tedarik-dd {
    position: absolute; top: calc(100% + 12px); left: 0; z-index: 50;
    width: 260px; background: #fff; border: 1px solid #ececec; border-radius: 12px;
    box-shadow: 0 12px 32px rgba(15,23,42,0.14), 0 4px 12px rgba(15,23,42,0.08);
    padding: 10px 0 8px;
}
.tedarik-dd-item {
    display: flex; align-items: center; gap: 10px;
    padding: 8.5px 16px; font-size: 13px; font-weight: 400; color: #8a8a8e;
    cursor: pointer; user-select: none;
}
.tedarik-dd-item:hover { background: #f8fafc; color: #334155; }
.tedarik-dd-item.selected { color: #059669; font-weight: 600; }
.tedarik-radio {
    width: 15px; height: 15px; border-radius: 50%;
    border: 1.5px solid #d1d5db; display: inline-flex; flex-shrink: 0;
    position: relative;
}
.tedarik-radio.on { border-color: #059669; }
.tedarik-radio.on::after {
    content: ''; position: absolute; inset: 2.5px;
    border-radius: 50%; background: #059669;
}
.tedarik-card .order-list-body {
    background: transparent !important;
    flex: 0 0 auto !important;
    display: block !important;
    height: auto !important;
    min-height: 0 !important;
}
.tedarik-docs-searchrow { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.tedarik-docs-searchbox {
    position: relative; display: flex; align-items: center; flex: 1; min-width: 220px;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 0 12px; height: 42px;
}
.tedarik-docs-searchbox:focus-within { background: #fff; border-color: #cbd5e1; box-shadow: 0 0 0 4px rgba(5,150,105,0.10); }
.tedarik-docs-search-icon { position: absolute; left: 12px; font-size: 18px; color: #94a3b8; }
.tedarik-docs-search-input {
    border: none; outline: none; background: transparent; height: 100%; width: 100%;
    padding-left: 30px; font-size: 13.5px; color: #0f172a;
}
.tedarik-docs-search-input::placeholder { color: #94a3b8; }
.tedarik-btn-light {
    height: 42px; padding: 0 18px; border-radius: 10px; background: #fff;
    border: 1.5px solid #e2e8f0; color: #334155; font-size: 13px; font-weight: 700; cursor: pointer;
}
.tedarik-btn-light:hover { background: #f8fafc; border-color: #cbd5e1; }
.tedarik-docs-btn--ghost { background: #f1f5f9; }
.tedarik-bottom-note {
    margin-top: 10px; display: flex; align-items: flex-start; gap: 6px;
    font-size: 11.5px; color: #8a8a8e; line-height: 1.6;
}
.tedarik-bottom-note i { color: #059669; font-size: 13px; margin-top: 2px; flex-shrink: 0; }
.tedarik-bottom-note b { color: #0f172a; }
.tedarik-card :deep(.pickletable),
.tedarik-card :deep(#div_table.pickletable) {
    height: 75vh !important;
    min-height: calc(75vh - 280px) !important;
}
.tedarik-card :deep(.pickletable .divTable){ overflow: auto !important; }
.tedarik-card :deep(.pickletable .divPagination){ margin-top: 0 !important; }
.tedarik-card :deep(.pickletable table){
    border-collapse: separate !important;
    border-spacing: 0 7px !important;
    width: 100% !important;
    table-layout: fixed !important;
    border: none !important;
}
.tedarik-card :deep(.pickletable thead){
    position: sticky !important; top: 0 !important; z-index: 5 !important; background: #fff !important;
}
.tedarik-card :deep(.pickletable thead th){
    background: #fff !important; color: #b0b0b5 !important;
    font-size: 13px !important; font-weight: 500 !important;
    text-transform: none !important; letter-spacing: 0 !important;
    padding: 10px 14px !important; border: none !important; border-bottom: none !important;
    white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important;
    text-align: left !important; vertical-align: middle !important; line-height: 1.4 !important;
    box-shadow: none !important; position: sticky !important; top: 0 !important; z-index: 5 !important;
}
.tedarik-card :deep(.pickletable thead th:last-child){ text-align: right !important; }
.tedarik-card :deep(.pickletable tbody tr){
    background: #fff !important; border-radius: 14px !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06) !important; border: none !important;
}
.tedarik-card :deep(.pickletable tbody tr:hover){
    transform: translateY(-2px) !important; box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important;
}
.tedarik-card :deep(.pickletable tbody tr:hover td){ background: transparent !important; }
.tedarik-card :deep(.pickletable tbody td){
    background: transparent !important; padding: 14px 12px !important;
    font-size: 13px !important; color: #333 !important; border: none !important;
    vertical-align: middle !important; white-space: nowrap !important;
    overflow: hidden !important; text-overflow: ellipsis !important; font-weight: 400;
}
.tedarik-card :deep(.pickletable tbody td:first-child){
    border-radius: 14px 0 0 14px !important; font-weight: 600 !important; color: #111827 !important;
}
.tedarik-card :deep(.pickletable tbody td:last-child){ border-radius: 0 14px 14px 0 !important; }
.tedarik-card :deep(.pickletable .divPagination){
    background: transparent !important; border-top: none !important; padding: 10px 0 0 !important;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; position: relative;
}
.tedarik-card :deep(.pickletable .divPagination button),
.tedarik-card :deep(.pickletable .divPagination .page-link){
    border: 1px solid #e5e7eb !important; background: #fff !important; color: #8a8a8e !important;
    border-radius: 6px !important; padding: 5px 10px !important;
    font-size: 12px !important; font-weight: 500 !important; min-width: 32px; height: 30px;
}
.tedarik-card :deep(.pickletable .divPagination .active button),
.tedarik-card :deep(.pickletable .divPagination .active .page-link),
.tedarik-card :deep(.pickletable .divPagination button.active),
.tedarik-card :deep(.pickletable .divPagination button.current){
    background: #fff !important; color: #059669 !important; border-color: #059669 !important; font-weight: 700 !important;
}

/* Admin shell — action icon buttons match every other admin list */
.rlist-card :deep(.ihl-act){
    width: 34px;
    height: 34px;
    border-radius: 10px;
    border: 1px solid transparent;
    background: #f1f5f9;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .15s ease;
    font-size: 15px;
}
.rlist-card :deep(.ihl-act:hover){ transform: translateY(-1px); }
.rlist-card :deep(.ihl-act--more){ background: #f1f5f9; color: #475569; }
.rlist-card :deep(.ihl-act--more:hover){ background: #e2e8f0; color: #0f172a; }
.rlist-card :deep(.ihl-act--edit){ background: #e6f7fa; color: #0e8ea4; }
.rlist-card :deep(.ihl-act--edit:hover){ background: #cff0f5; color: #0b7285; }
.rlist-card :deep(.ihl-act--go){ background: #0e8ea4; color: #fff; }
.rlist-card :deep(.ihl-act--go:hover){ background: #0c7e90; color: #fff; }
</style>
