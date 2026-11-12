<script>
    import { useRouter, useRoute } from 'vue-router';
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import Swal from 'sweetalert2';
    import { fmtDateTime } from '@/lib/dateUtils';
    import { offerMeta, parseStatus } from '@/lib/tenderStatus';

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
                route : useRoute(),
                navigationStore : useNavigationStore(),
                authStore    : useAuthStore(),
                filteredIhale : useRoute().query.ihale || '',
                showFilter : false,
                statusChoice : '',
            }
        },
        computed: {
            isReseller(){ return this.authStore.typeKey === 'op-pert-reseller'; },
            canReview(){
                if (this.isReseller) return false;
                const p = this.authStore.permissions || [];
                return p.includes('per-041-03');
            },
            isIhalePanel(){ return this.$route.path.startsWith('/ihalepanel'); },
            listRouteName(){ return this.isIhalePanel ? 'IhalePanelIhOfferList' : 'IhOfferList'; },
            offerRouteName(){ return this.isIhalePanel ? 'IhalePanelIhOffer' : 'IhOffer'; },
        },
        watch: {
            '$route.query.ihale'(v){
                this.filteredIhale = v || '';
                this.refreshTable();
            },
        },
        methods: {
            _outsideFilter(e){
                if(this.showFilter && !e.target.closest?.('.ihale-filter-dd-wrap')){
                    this.showFilter = false;
                }
            },
            refreshTable(){
                const c = document.getElementById('div_table');
                if(c) c.innerHTML = '';
                this.table = null;
                this.buildTable();
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
                this.clearIhaleFilter();
                this.statusChoice = '';
                this.table.setFilter([]);
            },
            pickStatus(v){
                this.statusChoice = v;
                this.showFilter = false;
                const base = [];
                if(this.filteredIhale){
                    base.push({ key : 'attr', type : 'ihale_qnid', value : this.filteredIhale });
                }
                if(!v){
                    this.table.setFilter(base);
                    return;
                }
                this.table.setFilter([...base, { key : 'transactions', type : '=', value : v }]);
            },
            exportTable(){
                // Server-side Excel export honours the active filter and covers
                // the whole list — not just the current page.
                this.plib.openTab('POST', '/api/v1/export/ihale-offer', this.table?.currentFilter || [], '_blank');
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
            clearIhaleFilter(){
                this.filteredIhale = '';
                if(this.route.query.ihale){
                    this.router.replace({ name : this.listRouteName });
                }
            },
            openOffer(qnid){
                this.router.push({ name : this.offerRouteName, params : { id : qnid } });
            },
            openActionsModal(rowData, columnData){
                const st = this.offerStatus(rowData);
                const canAct = this.canReview && (st.key === 'offer-sended' || !st.key);
                if(!canAct) return;
                const label = rowData.supplier_title || 'Teklif';
                const btn = (id, text, icon, bg) => `<button id="${id}" class="btn" style="background:${bg};color:#fff;border:none;font-weight:600;padding:12px 16px;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:8px;"><i class="${icon}" style="font-size:16px;"></i> ${text}</button>`;
                Swal.fire({
                    title : 'Aksiyonlar',
                    html : `<div style="text-align:left;font-size:12.5px;color:#64748b;margin-bottom:10px;"><b style="color:#0f172a;">${this.esc(label)}</b></div><div class="d-flex flex-column gap-2 p-2">`
                        + btn('aks-open', 'Teklifi Aç', 'ki-outline ki-eye', '#0e8ea4')
                        + btn('aks-accept', 'Teklifi Kabul Et', 'ki-outline ki-check-circle', '#22c55e')
                        + btn('aks-reject', 'Teklifi Reddet', 'ki-outline ki-cross-circle', '#ef4444')
                        + `</div>`,
                    showConfirmButton : false,
                    showCloseButton : true,
                });
                const on = (id, fn) => {
                    const el = document.getElementById(id);
                    if(el) el.addEventListener('click', () => { Swal.close(); fn(); });
                };
                on('aks-open', () => this.openOffer(columnData));
                on('aks-accept', () => this.acceptOffer(columnData, rowData.supplier_title));
                on('aks-reject', () => this.rejectOffer(columnData, rowData.supplier_title));
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
                    this.table.updateRow(qnid, { status : 'offer-accepted**Teklif Kabul Edildi**-', id : qnid });
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
                    this.table.updateRow(qnid, { status : 'offer-rejected**Teklif Reddedildi**' + note, id : qnid });
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
                        width : '150px',
                        columnFormatter : (elm,row) => {
                            const v = row.ihale_kodu || '—';
                            const wrap = document.createElement('div');
                            wrap.style.display = 'flex';
                            wrap.style.flexDirection = 'column';
                            wrap.style.gap = '2px';
                            const span = document.createElement('span');
                            span.textContent = v;
                            span.style.fontWeight = '700';
                            span.style.color = '#0f172a';
                            span.style.fontSize = '13px';
                            span.title = v;
                            wrap.appendChild(span);
                            return wrap;
                        }
                    },{
                        title : 'Tedarikçi',
                        key   : 'supplier_title',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,row) => {
                            const v = row.supplier_title || '—';
                            const span = document.createElement('span');
                            span.textContent = v;
                            span.title = v;
                            span.style.color = '#334155';
                            span.style.display = 'inline-block';
                            span.style.maxWidth = '180px';
                            span.style.overflow = 'hidden';
                            span.style.textOverflow = 'ellipsis';
                            span.style.whiteSpace = 'nowrap';
                            span.style.verticalAlign = 'middle';
                            return span;
                        }
                    },{
                        title : 'Ürün',
                        key   : 'row_count',
                        order : true,
                        type  : 'string',
                        width : '130px',
                        columnFormatter : (elm,row) => {
                            const v = (row.row_count ?? 0) + ' kalem';
                            const s = document.createElement('span');
                            s.textContent = v;
                            s.style.color = '#334155';
                            return s;
                        }
                    },{
                        title : 'Durum',
                        key   : 'status',
                        order : true,
                        type  : 'string',
                        width : '225px',
                        columnFormatter : (elm,rowData) => {
                            const st = this.offerStatus(rowData);
                            const meta = offerMeta(st.key);
                            const btn = document.createElement('span');
                            btn.textContent = st.title || meta.label;
                            btn.style.display = 'inline-flex';
                            btn.style.alignItems = 'center';
                            btn.style.justifyContent = 'center';
                            btn.style.padding = '5px 10px';
                            btn.style.borderRadius = '6px';
                            btn.style.fontSize = '11.5px';
                            btn.style.fontWeight = '600';
                            btn.style.whiteSpace = 'normal';
                            btn.style.lineHeight = '1.25';
                            btn.style.textAlign = 'center';
                            btn.style.maxWidth = '100%';
                            btn.style.cursor = 'default';
                            btn.style.border = '1px solid transparent';
                            btn.style.gap = '6px';
                            btn.style.background = meta.color;
                            btn.style.color = '#ffffff';
                            btn.style.borderColor = meta.color;
                            const icon = document.createElement('i');
                            icon.className = meta.icon;
                            icon.style.fontSize = '14px';
                            btn.prepend(icon);
                            if (st.key === 'offer-rejected' && st.note) {
                                btn.title = st.note;
                            }
                            return btn;
                        }
                    },{
                        title : 'Son Kayıt',
                        key   : 'created_at_fmt',
                        order : true,
                        type  : 'string',
                        width : '120px',
                        columnFormatter : (elm,row) => {
                            const s = document.createElement('span');
                            s.textContent = this.formatDate(row.created_at_fmt || row.created_at || '—');
                            s.style.color = '#334155';
                            return s;
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

                            const st = this.offerStatus(rowData);
                            const canAct = this.canReview && (st.key === 'offer-sended' || !st.key);

                            if (canAct) {
                                const aks = document.createElement('button');
                                aks.textContent = 'Aksiyonlar';
                                aks.className = 'btn';
                                aks.style.background = '#8e8e93';
                                aks.style.color = '#ffffff';
                                aks.style.border = '1px solid #8e8e93';
                                aks.style.borderRadius = '8px';
                                aks.style.padding = '6px 14px';
                                aks.style.fontSize = '0.78rem';
                                aks.style.fontWeight = '600';
                                aks.style.cursor = 'pointer';
                                aks.style.flex = '0 0 auto';
                                aks.style.whiteSpace = 'nowrap';
                                aks.style.minHeight = '32px';
                                aks.onmouseenter = () => aks.style.background = '#7f7f84';
                                aks.onmouseleave = () => aks.style.background = '#8e8e93';
                                aks.onclick = (e) => { e.stopPropagation(); this.openActionsModal(rowData, columnData); };
                                wrap.appendChild(aks);
                            }

                            const det = document.createElement('button');
                            det.textContent = 'Detaylar';
                            det.className = 'btn';
                            det.style.background = '#0e8ea4';
                            det.style.color = '#ffffff';
                            det.style.border = '1px solid #0e8ea4';
                            det.style.borderRadius = '8px';
                            det.style.padding = '6px 14px';
                            det.style.fontSize = '0.78rem';
                            det.style.fontWeight = '600';
                            det.style.cursor = 'pointer';
                            det.style.flex = '0 0 auto';
                            det.style.whiteSpace = 'nowrap';
                            det.style.minHeight = '32px';
                            det.onmouseenter = () => det.style.background = '#0c7e90';
                            det.onmouseleave = () => det.style.background = '#0e8ea4';
                            det.onclick = () => this.openOffer(columnData);
                            wrap.appendChild(det);
                            return wrap;
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
                    height    : this.isIhalePanel ? '75vh' : '70vh',
                    type      : 'ajax',
                    columnSearch : false,
                    paginationType : 'number',
                    ajax : {
                        url : '/api/v1/table/documents',
                        data : {}
                    },
                    initialFilter : filters,
                    nextPageIcon : '<i class="ki-outline ki-arrow-right"></i>',
                    prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter : (elm, data) => {
                        let attrs = [];
                        try { attrs = JSON.parse(data.main_attr || '[]'); } catch(e) { console.warn('[IhOfferList] main_attr parse failed', e); }
                        attrs.forEach(a => { data[a.Key] = a.Value; });
                        data.created_at_fmt = fmtDateTime(data.created_at);
                        return data;
                    },
                });
            },
        }
    }
</script>

<template>
    <div :class="['order-list-card', 'tedarik-card', { 'ihale-card': isIhalePanel }]">
        <div class="tedarik-list-top">
            <div class="tedarik-list-title">
                <span>Teklif Listesi</span>
            </div>
            <div class="tedarik-filters">
                <div class="tedarik-filter-dd-wrap ihale-filter-dd-wrap">
                    <a href="javascript:;" class="tedarik-filter" :class="{ active: !!statusChoice }" @click.stop="showFilter = !showFilter">
                        <i class="ki-outline ki-filter"></i> Filtreler
                    </a>
                    <div v-if="showFilter" class="tedarik-dd" @click.stop>
                        <label class="tedarik-dd-item" :class="{ selected: statusChoice === '' }" @click="pickStatus('')">
                            <span class="tedarik-radio" :class="{ on: statusChoice === '' }"></span> Hepsini Göster
                        </label>
                        <label class="tedarik-dd-item" :class="{ selected: statusChoice === 'offer-sended' }" @click="pickStatus('offer-sended')">
                            <span class="tedarik-radio" :class="{ on: statusChoice === 'offer-sended' }"></span> Gönderilenler
                        </label>
                        <label class="tedarik-dd-item" :class="{ selected: statusChoice === 'offer-accepted' }" @click="pickStatus('offer-accepted')">
                            <span class="tedarik-radio" :class="{ on: statusChoice === 'offer-accepted' }"></span> Kabul Edilenler
                        </label>
                        <label class="tedarik-dd-item" :class="{ selected: statusChoice === 'offer-rejected' }" @click="pickStatus('offer-rejected')">
                            <span class="tedarik-radio" :class="{ on: statusChoice === 'offer-rejected' }"></span> Reddedilenler
                        </label>
                    </div>
                </div>
                <a href="javascript:;" class="tedarik-filter" @click="exportTable"><i class="ki-outline ki-exit-down"></i> Excel Çıktı</a>
            </div>
        </div>
        <div class="tedarik-docs-searchrow" style="margin: 0 0 14px;">
            <div class="tedarik-docs-searchbox">
                <i class="ki-outline ki-magnifier tedarik-docs-search-icon"></i>
                <input type="text" ref="tedarikSearch" id="mainSearch" class="tedarik-docs-search-input" placeholder="Teklif ara — ihale kodu, tedarikçi..." @keydown.enter="searchTable">
            </div>
            <button type="button" class="tedarik-btn-light tedarik-docs-btn" @click="searchTable">Ara</button>
            <button type="button" class="tedarik-btn-light tedarik-docs-btn tedarik-docs-btn--ghost" @click="resetSearch">Sıfırla</button>
            <button
                v-if="filteredIhale"
                type="button"
                class="tedarik-btn-light tedarik-docs-btn tedarik-docs-btn--ghost"
                @click="clearIhaleFilter(); table.setFilter([]);"
            >
                İhale Filtresini Kaldır
            </button>
        </div>
        <div class="order-list-body">
            <div id="div_table"></div>
        </div>
        <div class="tedarik-bottom-note">
            <i class="ki-outline ki-information-5"></i>
            <span>Teklifleri <b>"Detaylar"</b> butonuna tıklayarak incelemeyi unutmayınız.</span>
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
</style>
