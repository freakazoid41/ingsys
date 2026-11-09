<script>
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { useAuthStore } from '@/stores/auth';
import { expandMonths } from '@/lib/talepRows';
import { useNavigationStore } from '@/stores/navigation';

export default {
    breadcrumbs: {
        list: [{ title: 'Malzeme Planlama', path: '/planlamapanel' }, { title: 'İhtiyaç Listesi', path: '#' }],
        title: 'İhtiyaç Listesi'
    },
    setup() { return { Plib, useAuthStore, useNavigationStore }; },
    data() {
        return {
            plib: new Plib(),
            authStore: useAuthStore(),
            navigationStore: useNavigationStore(),
            table: null,
            _buildTimeouts: [],
            isPlanlama() { return this.$route.path.startsWith('/planlamapanel'); },
        };
    },
    computed: {
        isFront() { return this.$route.path.startsWith('/planlamapanel'); },
        formRoute() { return this.$route.path.startsWith('/planlamapanel') ? 'PlanlamaRForm' : 'PlanlamaRFormAdmin'; },
        fFormRoute() { return this.$route.path.startsWith('/planlamapanel') ? 'PlanlamaFForm' : 'PlanlamaFFormAdmin'; },
        canCreate() { return (this.authStore.permissions || []).includes('per-041-05-02'); },
    },
    mounted() {
        this.navigationStore.toggle(true);
        this.buildTable();
        setTimeout(() => this.navigationStore.toggle(false), 300);
        this._buildTimeouts.push(setTimeout(() => this.enforceFrontHeight(), 400));
    },
    beforeUnmount() {
        this._buildTimeouts.forEach(clearTimeout);
    },
    methods: {
        enforceFrontHeight() {
            if (!this.isFront) return;
            try {
                const el = document.querySelector('.tedarik-card .pickletable');
                if (!el) return;
                el.style.setProperty('height', '75vh', 'important');
                el.style.setProperty('min-height', 'calc(75vh - 280px)', 'important');
                const dt = el.querySelector('.divTable');
                if (dt) {
                    dt.style.setProperty('height', '90%', 'important');
                    dt.style.setProperty('overflow', 'auto', 'important');
                }
            } catch {}
        },
        searchTable() {
            const inp = this.$refs.frontSearch || document.getElementById('mainSearch');
            const v = (inp?.value || '').trim();
            this.table.setFilter(v ? [{ key: 'all', type: '=', value: v }] : []);
        },
        resetSearch() {
            const inp = this.$refs.frontSearch || document.getElementById('mainSearch');
            if (inp) inp.value = '';
            this.table.setFilter([]);
        },
        goForm(id) {
            this.$router.push({ name: this.formRoute, params: id ? { id } : {} });
        },
        async downloadRowExcel(qnid) {
            try {
                const rsp = await this.plib.request({ url: '/api/v1/document/' + qnid, method: 'GET' }, null);
                const ff = rsp?.data?.formFormat?.['op-doc-request-form'] || {};
                let head = { title: '' };
                for (const rid in ff) {
                    const ents = ff[rid]?.entities || {};
                    for (const k in ents) {
                        if (k.includes('**')) continue;
                        if (head[k] === undefined) head[k] = ents[k];
                    }
                }
                const months = expandMonths(head.donem_bas || '', head.donem_bit || '');
                const mlabels = months.map(m => { const p = m.split('/'); return p[1] + '-' + p[0]; });
                const aoa = [['Mal Grubu', 'İhale Grubu', 'Malzeme Kodu', 'Malzeme İsmi', 'Ölçü Birimi'].concat(mlabels)];
                const iff = rsp?.data?.formFormat?.['op-doc-request-item-form'] || {};
                const byRow = {};
                for (const rid in iff) {
                    const ents = iff[rid]?.entities || {};
                    for (const k in ents) {
                        const parts = k.split('**');
                        if (parts.length < 3) continue;
                        const rk = parts[2];
                        byRow[rk] = byRow[rk] || {};
                        byRow[rk][parts[0]] = ents[k];
                    }
                }
                Object.values(byRow).forEach(r => {
                    if (!r.prod_code && !r.title) return;
                    aoa.push([r.mal_grubu || '', r.ihale_grubu || '', r.prod_code || '', r.title || '', r.unit || ''].concat(mlabels.map(() => '')));
                });
                if (aoa.length < 2) { this.plib.toast(Swal, 'info', 'İndirilecek kalem yok'); return; }
                const ws = XLSX.utils.aoa_to_sheet(aoa);
                ws['!cols'] = [{ wch: 20 }, { wch: 20 }, { wch: 20 }, { wch: 40 }, { wch: 14 }].concat(mlabels.map(() => ({ wch: 12 })));
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Kalemler');
                const nm = String(head.title || 'ihtiyac').replace(/[\\/:*?"<>|]/g, '-').slice(0, 40);
                XLSX.writeFile(wb, 'ihtiyac-' + nm + '.xlsx');
            } catch (err) {
                console.error(err);
                this.plib.toast(Swal, 'error', 'Excel indirilemedi');
            }
        },
        buildTable() {
            const isFront = this.$route.path.startsWith('/planlamapanel');
            this.table = new PickleTable({
                container: '#div_table',
                headers: [
                    { title: 'Başlık', key: 'title', order: true, type: 'string', width: isFront ? '220px' : undefined,
                      columnFormatter: (elm, rowData, col) => {
                          const s = document.createElement('span');
                          s.textContent = col || rowData.title || '—';
                          s.title = s.textContent;
                          s.style.cssText = 'font-weight:700;color:#0f172a;font-size:' + (isFront ? '13px' : '14px') + ';display:inline-block;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle;cursor:pointer;';
                          s.onclick = () => this.$router.push({ name: this.formRoute, params: { id: rowData.id } });
                          return s;
                      } },
                    { title: 'Dönem', key: 'donem_bas', order: true, type: 'string', width: isFront ? '170px' : undefined,
                      columnFormatter: (elm, rowData, col) => {
                          const b = col || rowData.donem_bas || '';
                          const e = rowData.donem_bit || '';
                          const span = document.createElement('span');
                          span.textContent = b && e ? (b === e ? b : b + ' - ' + e) : (b || e || '-');
                          span.style.color = '#334155';
                          return span;
                      } },
                    { title: 'Oluşturulma', key: 'created_at', order: true, type: 'string', width: isFront ? '150px' : undefined,
                      columnFormatter: (elm, rowData, col) => {
                          const s = document.createElement('span'); s.textContent = col || rowData.created_at || '—'; s.style.color = '#334155'; return s;
                      } },
                    { title: '', key: 'id', order: false, type: 'string', width: isFront ? '260px' : undefined,
                      columnFormatter: (elm, rowData, columnData) => {
                          const wrap = document.createElement('div');
                          wrap.style.display = 'flex';
                          wrap.style.justifyContent = isFront ? 'flex-end' : 'center';
                          wrap.style.alignItems = 'center';
                          wrap.style.gap = '6px';
                          const mk = (label, bg, color, border) => {
                              const b = document.createElement('button');
                              b.textContent = label;
                              b.className = 'btn';
                              b.style.cssText = `background:${bg};color:${color};border:1px solid ${border};border-radius:8px;padding:6px 14px;font-size:0.78rem;font-weight:600;cursor:pointer;white-space:nowrap;`;
                              return b;
                          };
                          const btn = mk('Detay', '#0e8ea4', '#ffffff', '#0e8ea4');
                          btn.onclick = () => this.$router.push({ name: this.formRoute, params: { id: columnData } });
                          wrap.appendChild(btn);
                          if (this.authStore.permissions?.includes('per-041-05-03')) {
                              const fbtn = mk('Talep Oluştur', '#f1f5f9', '#334155', '#e2e8f0');
                              fbtn.title = 'Bu ihtiyaçtan bağlı Talep Dökümanı oluştur';
                              fbtn.onclick = () => this.$router.push({ name: this.fFormRoute, query: { from: columnData } });
                              wrap.appendChild(fbtn);
                          }
                          const xbtn = mk('Excel', '#f1f5f9', '#334155', '#e2e8f0');
                          xbtn.title = 'Kalemleri Excel olarak indir';
                          xbtn.onclick = () => this.downloadRowExcel(columnData);
                          wrap.appendChild(xbtn);
                          return wrap;
                      } },
                ],
                pageLimit: 10,
                height: isFront ? '75vh' : '70vh',
                type: 'ajax',
                columnSearch: false,
                paginationType: 'number',
                ajax: { url: '/api/v1/table/documents', data: {} },
                initialFilter: [
                    { key: 'form-type', type: '=', value: 'op-doc-request-form' },
                    { key: 'type', type: '=', value: 'op-doc-request' },
                ],
                nextPageIcon: '<i class="ki-outline ki-arrow-right"></i>',
                prevPageIcon: '<i class="ki-outline ki-arrow-left"></i>',
                rowFormatter: (elm, data) => {
                    try {
                        JSON.parse(data.main_attr).forEach(el => { data[el['Key']] = el['Value']; });
                    } catch(e) {}
                    return data;
                },
            });
        },
    }
};
</script>

<template>
    <div :class="['order-list-card', { 'tedarik-card': isFront }]">
        <!-- Admin header -->
        <div v-if="!isFront" class="card rlist-card">
            <div class="card-header rlist-header">
                <div class="rlist-search-group">
                    <div class="rlist-search-wrap">
                        <i class="ki-duotone ki-magnifier fs-4 rlist-search-icon">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                        <input type="text" id="mainSearch" class="rlist-search-input" placeholder="İhtiyaç ara...">
                    </div>
                    <button type="button" class="rlist-btn rlist-btn-primary" @click="searchTable">
                        <i class="ki-outline ki-magnifier fs-5"></i> Ara
                    </button>
                    <button type="button" class="rlist-btn rlist-btn-ghost" @click="resetSearch">Sıfırla</button>
                </div>
                <div v-if="canCreate" class="rlist-toolbar">
                    <button type="button" class="rlist-btn rlist-btn-create" @click="goForm()">
                        <i class="ki-outline ki-plus fs-5"></i> İhtiyaç Listesi Oluştur
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="div_table"></div>
            </div>
        </div>
        <!-- Front (planlamapanel) header 1:1 tedarik order table -->
        <template v-else>
            <div class="tedarik-list-top">
                <div class="tedarik-list-title"><span>İhtiyaç Listesi</span></div>
                <div class="tedarik-filters">
                    <a v-if="canCreate" href="javascript:;" class="tedarik-filter" @click="goForm()"><i class="ki-outline ki-plus"></i> İhtiyaç Oluştur</a>
                </div>
            </div>
            <div class="tedarik-docs-searchrow" style="margin: 0 0 14px;">
                <div class="tedarik-docs-searchbox">
                    <i class="ki-outline ki-magnifier tedarik-docs-search-icon"></i>
                    <input type="text" ref="frontSearch" class="tedarik-docs-search-input" placeholder="İhtiyaç ara — başlık, dönem..." @keydown.enter="searchTable">
                </div>
                <button type="button" class="tedarik-btn-light tedarik-docs-btn" @click="searchTable">Ara</button>
                <button type="button" class="tedarik-btn-light tedarik-docs-btn tedarik-docs-btn--ghost" @click="resetSearch">Sıfırla</button>
            </div>
            <div class="order-list-body">
                <div id="div_table"></div>
            </div>
            <div class="tedarik-bottom-note">
                <i class="ki-outline ki-information-5 me-5"></i>
                <span>Bir satıra tıklayarak detayına gidebilir, bağlı talep oluşturabilirsiniz.</span>
            </div>
        </template>
    </div>
</template>

<style scoped>
.rlist-card { border: 1px solid #e2e8f0 !important; border-radius: 16px !important; overflow: hidden; background: #fff !important; }
.rlist-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 14px 18px !important; border-bottom: 1px solid #f1f5f9 !important; background: #fff !important; }
.rlist-search-group { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.rlist-search-wrap { position: relative; display: flex; align-items: center; }
.rlist-search-icon { position: absolute; left: 12px; color: #99a1b7; pointer-events: none; }
.rlist-search-input { height: 40px; border: 1px solid #e2e8f0; border-radius: 999px; padding: 0 14px 0 38px; font-size: 13px; color: #0f172a; background: #f8fafc; outline: none; width: 280px; }
.rlist-search-input:focus { border-color: #0e7490; background: #fff; }
.rlist-btn { display: inline-flex; align-items: center; gap: 6px; height: 40px; padding: 0 18px; border-radius: 999px; font-size: 13px; font-weight: 700; border: 1px solid transparent; cursor: pointer; white-space: nowrap; text-decoration: none; }
.rlist-btn-primary { background: #0e7490 !important; color: white !important; border-color: #0e7490; }
.rlist-btn-ghost { background: #f1f5f9; color: #334155; border-color: #e2e8f0; }
.rlist-btn-create { background: #0e7490 !important; color: white !important; border-color: #0e7490; }
#div_table { width: 100%; overflow-x: auto; }
/* ===== TEDARIK 1:1 (front) — same as OList tedarik-card ===== */
.tedarik-card { border: none !important; border-radius: 0 !important; box-shadow: none !important; background: transparent !important; }
.tedarik-list-top { display: flex; align-items: center; justify-content: space-between; padding: 2px 4px 18px; gap: 12px; flex-wrap: wrap; border-bottom: none; }
.tedarik-list-title { display: flex; align-items: center; gap: 10px; font-size: 19px; font-weight: 700; color: #1e293b; letter-spacing: -0.02em; line-height: 1; }
.tedarik-filters { display: flex; align-items: center; gap: 22px; }
.tedarik-filter { display: inline-flex; align-items: center; gap: 6px; font-size: 13.5px; font-weight: 500; color: #8a8a8e; text-decoration: none; cursor: pointer; white-space: nowrap; }
.tedarik-filter i { font-size: 13px; color: #a1a1a6; }
.tedarik-filter:hover { color: #4b5563; }
.tedarik-docs-searchrow { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.tedarik-docs-searchbox { position: relative; display: flex; align-items: center; flex: 1; min-width: 220px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0 12px; height: 42px; }
.tedarik-docs-searchbox:focus-within { background: #fff; border-color: #cbd5e1; box-shadow: 0 0 0 4px rgba(255,90,31,.10); }
.tedarik-docs-search-icon { position: absolute; left: 12px; font-size: 18px; color: #94a3b8; }
.tedarik-docs-search-input { border: none; outline: none; background: transparent; height: 100%; width: 100%; padding-left: 30px; font-size: 13.5px; color: #0f172a; }
.tedarik-docs-search-input::placeholder { color: #94a3b8; }
.tedarik-btn-light { height: 42px; padding: 0 18px; border-radius: 10px; background: #fff; border: 1.5px solid #e2e8f0; color: #334155; font-size: 13px; font-weight: 700; cursor: pointer; }
.tedarik-btn-light:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
.tedarik-docs-btn--ghost { background: #f1f5f9; }
.tedarik-bottom-note { margin-top: 10px; display: flex; align-items: flex-start; gap: 6px; font-size: 11.5px; color: #8a8a8e; line-height: 1.6; }
.tedarik-bottom-note i { color: #0e9cb8; font-size: 13px; margin-top: 2px; flex-shrink: 0; }
.tedarik-card :deep(.pickletable) { height: auto !important; border: none !important; }
.tedarik-card :deep(.pickletable .divTable) { height: auto !important; overflow: visible !important; border-bottom: none !important; }
.tedarik-card :deep(.pickletable table) { border-collapse: separate !important; border-spacing: 0 7px !important; table-layout: fixed !important; border: none !important; width: 100% !important; }
.tedarik-card :deep(.pickletable thead) { position: sticky !important; top: 0 !important; z-index: 5 !important; background: #fff !important; }
.tedarik-card :deep(.pickletable thead th) { background: #fff !important; color: #b0b0b5 !important; font-size: 13px !important; font-weight: 500 !important; border: none !important; border-bottom: none !important; box-shadow: none !important; padding: 10px 14px !important; text-transform: none !important; letter-spacing: 0 !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; text-align: left !important; vertical-align: middle !important; line-height: 1.4 !important; position: sticky !important; top: 0 !important; z-index: 5 !important; }
.tedarik-card :deep(.pickletable thead th:last-child) { text-align: right !important; }
.tedarik-card :deep(.pickletable thead th i), .tedarik-card :deep(.pickletable thead th svg) { vertical-align: middle !important; }
.tedarik-card :deep(.pickletable thead th.orderable span) { display: inline-flex !important; align-items: center !important; line-height: 1 !important; vertical-align: middle !important; }
.tedarik-card :deep(.pickletable thead th.orderable span:after) { display: inline-flex !important; align-items: center !important; line-height: 1 !important; margin-left: 6px !important; }
.tedarik-card :deep(.pickletable tbody tr) { background: #fff !important; border-radius: 14px !important; box-shadow: 0 2px 8px rgba(0,0,0,0.06) !important; border: none !important; }
.tedarik-card :deep(.pickletable tbody tr:hover) { transform: translateY(-2px) !important; box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important; }
.tedarik-card :deep(.pickletable tbody tr:hover td) { background: transparent !important; }
.tedarik-card :deep(.pickletable tbody td) { background: transparent !important; padding: 14px 12px !important; font-size: 13px !important; color: #333 !important; border: none !important; vertical-align: middle !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; font-weight: 400; }
.tedarik-card :deep(.pickletable tbody td:first-child) { border-radius: 14px 0 0 14px !important; font-weight: 600 !important; color: #111827 !important; }
.tedarik-card :deep(.pickletable tbody td:last-child) { border-radius: 0 14px 14px 0 !important; }
.tedarik-card :deep(.pickletable .divPagination) { background: transparent !important; border-top: none !important; padding: 10px 0 0 !important; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; position: relative; }
.tedarik-card :deep(.pickletable .divPagination .pagination) { gap: 5px; margin-top: 14px; }
.tedarik-card :deep(.pickletable .divPagination button), .tedarik-card :deep(.pickletable .divPagination .page-link) { border: 1px solid #e5e7eb !important; background: #fff !important; color: #8a8a8e !important; border-radius: 6px !important; padding: 5px 10px !important; font-size: 12px !important; font-weight: 500 !important; min-width: 32px; height: 30px; }
.tedarik-card :deep(.pickletable .divPagination .active button), .tedarik-card :deep(.pickletable .divPagination .active .page-link) { background: #fff !important; color: #FF5A1F !important; border-color: #FF5A1F !important; font-weight: 700 !important; }
</style>
