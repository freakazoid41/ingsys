<script>
    import { useRouter, useRoute } from 'vue-router';
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
            title: 'Teklifler'
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
                route : useRoute(),
                navigationStore : useNavigationStore(),
                authStore    : useAuthStore(),
                filteredIhale : useRoute().query.ihale || '',
                _tableResizeHandler : null,
            }
        },
        computed: {
            isReseller(){ return this.authStore.typeKey === 'op-pert-reseller'; },
            canReview(){
                if (this.isReseller) return false;
                const p = this.authStore.permissions || [];
                return p.includes('per-041-03-02') || p.includes('per-041-03');
            },
        },
        watch: {
            '$route.query.ihale'(v){
                this.filteredIhale = v || '';
                this.refreshTable();
            },
        },
        methods: {
            refreshTable(){
                const c = document.getElementById('div_table');
                if(c) c.innerHTML = '';
                this.table = null;
                this.buildTable();
            },
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
                        table.style.minWidth = '720px';
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
                this.clearIhaleFilter();
                this.table.setFilter([]);
            },
            clearIhaleFilter(){
                this.filteredIhale = '';
                if(this.route.query.ihale){
                    this.router.replace({ name : 'IhOfferList' });
                }
            },
            openOffer(qnid){
                this.router.push({ name : 'IhOffer', params : { id : qnid } });
            },
            async acceptOffer(qnid, label){
                const confirm = await Swal.fire({
                    icon : 'warning',
                    title : 'Teklifi Kabul Et',
                    html : '<div style="text-align:left;font-size:13px;">' + (label ? '<b>' + label + '</b> ' : '') + 'kazanan seçilecek ve <b>ihale tamamlanacak</b>.<br><br>Tüm teklif dosyaları dondurulur, ihale kilitlenir.</div>',
                    showCancelButton : true,
                    confirmButtonText : 'Kabul Et ve İhaleyi Tamamla',
                    cancelButtonText : 'Vazgeç',
                    reverseButtons : true,
                });
                if(!confirm.isConfirmed) return;

                this.navigationStore.toggle(true);
                const fd = new FormData();
                fd.append('id', qnid);
                const rsp = await this.plib.request({ url : '/api/v1/offers/accept', method : 'POST' }, null, fd);

                if(rsp?.success){
                    this.table.updateRow(qnid, { status : 'offer-accepted**Teklif Kabul Edildi**-' });
                    this.plib.toast(this.Swal, 'success', 'Teklif kabul edildi, ihale tamamlandı.');
                } else {
                    this.plib.toast(this.Swal, 'error', rsp?.msg || 'Teklif kabul edilemedi.');
                }
                setTimeout(() => this.navigationStore.toggle(false), 300);
            },
            async rejectOffer(qnid, label){
                const res = await Swal.fire({
                    title : 'Teklifi Reddet',
                    html : '<div style="text-align:left;font-size:13px;color:#64748b;margin-bottom:8px;">' + (label ? '<b>' + this.esc(label) + '</b> ' : '') + 'reddedilecek. Tedarikçi nedeni görüp düzeltip yeniden gönderebilir.</div>',
                    input : 'textarea',
                    inputLabel : 'Red nedeni (zorunlu)',
                    inputPlaceholder : 'örn. Tip test raporu eksik / hatalı',
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
                fd.append('id', qnid);
                fd.append('note', note);
                const rsp = await this.plib.request({ url : '/api/v1/offers/reject', method : 'POST' }, null, fd);

                if(rsp?.success){
                    this.table.updateRow(qnid, { status : 'offer-rejected**Teklif Reddedildi**' + note });
                    this.plib.toast(this.Swal, 'success', 'Teklif reddedildi.');
                } else {
                    this.plib.toast(this.Swal, 'error', rsp?.msg || 'Teklif reddedilemedi.');
                }
                setTimeout(() => this.navigationStore.toggle(false), 300);
            },
            esc(s){
                return String(s ?? '').replace(/[&<>"']/g, c => ({ '&' : '&amp;', '<' : '&lt;', '>' : '&gt;', '"' : '&quot;', "'" : '&#39;' }[c]));
            },
            offerStatus(rowData){
                const parts = String(rowData.status || '').split('**');
                return { key : parts[0] || '', title : parts[1] || '', note : parts[2] || '' };
            },
            buildTable(){
                const headers = [
                    {
                        title : 'İhale Kodu',
                        key   : 'ihale_kodu',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,rowData) => {
                            const b = document.createElement('span');
                            b.className = 'of-code';
                            b.textContent = rowData.ihale_kodu || '—';
                            return b;
                        }
                    },{
                        title : 'Tedarikçi',
                        key   : 'supplier_title',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,rowData) => {
                            const b = document.createElement('span');
                            b.className = 'of-supplier';
                            b.textContent = rowData.supplier_title || '—';
                            return b;
                        }
                    },{
                        title : 'Ürün',
                        key   : 'row_count',
                        order : false,
                        colAlign : 'center',
                        type  : 'string',
                        columnFormatter : (elm,rowData) => {
                            const b = document.createElement('span');
                            b.className = 'of-count';
                            b.textContent = (rowData.row_count ?? 0) + ' kalem';
                            return b;
                        }
                    },{
                        title : 'Durum',
                        key   : 'status',
                        order : false,
                        colAlign : 'center',
                        type  : 'string',
                        columnFormatter : (elm,rowData) => {
                            const st = this.offerStatus(rowData);
                            const wrap = document.createElement('div');
                            wrap.className = 'of-status';
                            const b = document.createElement('span');
                            if (st.key === 'offer-accepted') {
                                b.className = 'of-pill of-pill--ok';
                                b.textContent = st.title || 'Kabul Edildi';
                            } else if (st.key === 'offer-rejected') {
                                b.className = 'of-pill of-pill--bad';
                                b.textContent = st.title || 'Reddedildi';
                            } else {
                                b.className = 'of-pill of-pill--live';
                                b.textContent = st.title || 'Gönderildi';
                            }
                            wrap.appendChild(b);
                            if (st.key === 'offer-rejected' && st.note) {
                                const n = document.createElement('div');
                                n.className = 'of-note';
                                n.textContent = st.note;
                                n.title = st.note;
                                wrap.appendChild(n);
                            }
                            return wrap;
                        }
                    },{
                        title : 'Son Kayıt',
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

                            const openBtn = document.createElement('button');
                            openBtn.classList.add('btn','btn-secondary','action-icon-btn');
                            openBtn.title = 'Teklifi Aç';
                            openBtn.innerHTML = '<i class="ki-outline ki-eye fs-2"></i>';
                            openBtn.onclick = () => this.openOffer(columnData);
                            span.appendChild(openBtn);

                            const st = this.offerStatus(rowData);
                            const canAct = this.canReview && (st.key === 'offer-sended' || !st.key);

                            if (canAct) {
                                const okBtn = document.createElement('button');
                                okBtn.classList.add('btn','btn-secondary','action-icon-btn');
                                okBtn.title = 'Teklifi Kabul Et (kazanan seçilir, ihale tamamlanır)';
                                okBtn.innerHTML = '<i class="ki-outline ki-check-circle fs-2"></i>';
                                okBtn.onclick = () => this.acceptOffer(columnData, rowData.supplier_title);
                                span.appendChild(okBtn);

                                const noBtn = document.createElement('button');
                                noBtn.classList.add('btn','btn-secondary','action-icon-btn','action-icon-btn--danger');
                                noBtn.title = 'Teklifi Reddet (neden gerekli)';
                                noBtn.innerHTML = '<i class="ki-outline ki-cross-circle fs-2"></i>';
                                noBtn.onclick = () => this.rejectOffer(columnData, rowData.supplier_title);
                                span.appendChild(noBtn);
                            }

                            return span;
                        }
                    }
                ];

                const filters = [
                    { key : 'form-type', type : '=', value : 'op-doc-ihale-offer-form' },
                    { key : 'type', type : '=', value : 'op-doc-ihale-offer' }
                ];
                if(this.filteredIhale){
                    filters.push({ key : 'attr', type : 'ihale_qnid', value : this.filteredIhale });
                }

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
                    initialFilter : filters,
                    rowFormatter : (elm, data) => {
                        let attrs = [];
                        try { attrs = JSON.parse(data.main_attr || '[]'); } catch(e) {}
                        attrs.forEach(a => { data[a.Key] = a.Value; });
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
                    <input type="text" id="mainSearch" class="rlist-search-input" placeholder="İhale kodu veya tedarikçi ara...">
                </div>
                <button type="button" class="rlist-btn rlist-btn-primary" @click="searchTable">
                    <i class="ki-outline ki-magnifier fs-5"></i> Ara
                </button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="resetSearch">Sıfırla</button>
                <button
                    v-if="filteredIhale"
                    type="button"
                    class="rlist-btn rlist-btn-ghost"
                    @click="clearIhaleFilter(); table.setFilter([]);"
                >
                    <i class="ki-outline ki-cross fs-5"></i> İhale Filtresini Kaldır
                </button>
            </div>
            <div class="rlist-toolbar">
                <router-link
                    v-if="isReseller && useAuthStore().permissions?.includes('per-041-03-01')"
                    :to="{ name: 'IhOffer', query: filteredIhale ? { ihale: filteredIhale } : {} }"
                    class="rlist-btn rlist-btn-create"
                >
                    <i class="ki-outline ki-plus fs-5"></i> Teklif Oluştur
                </router-link>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="div_table"></div>
        </div>
    </div>
</template>

<style scoped>
.of-code { font-weight: 700; color: #0f172a; font-size: 13px; }
.of-supplier { color: #334155; font-size: 13px; font-weight: 500; }
.of-count {
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

.of-status { display: flex; flex-direction: column; align-items: center; gap: 3px; }
.of-pill { display: inline-flex; align-items: center; justify-content: center; padding: 4px 12px; border-radius: 999px; font-size: 11.5px; font-weight: 700; }
.of-pill--live { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.of-pill--ok { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.of-pill--bad { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.of-note { font-size: 10.5px; color: #b91c1c; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
