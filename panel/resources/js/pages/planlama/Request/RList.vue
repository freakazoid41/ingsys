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
            isPlanlama() { return this.$route.path.startsWith('/planlamapanel'); },
        };
    },
    computed: {
        formRoute() { return this.$route.path.startsWith('/planlamapanel') ? 'PlanlamaRForm' : 'PlanlamaRFormAdmin'; },
        fFormRoute() { return this.$route.path.startsWith('/planlamapanel') ? 'PlanlamaFForm' : 'PlanlamaFFormAdmin'; },
        canCreate() { return (this.authStore.permissions || []).includes('per-041-05-02'); },
    },
    mounted() {
        this.navigationStore.toggle(true);
        this.buildTable();
        setTimeout(() => this.navigationStore.toggle(false), 300);
    },
    methods: {
        searchTable() {
            this.table.setFilter([{ key: 'all', type: '=', value: document.getElementById('mainSearch').value.trim() }]);
        },
        resetSearch() {
            document.getElementById('mainSearch').value = '';
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
            this.table = new PickleTable({
                container: '#div_table',
                headers: [
                    { title: 'Başlık', key: 'title', order: true, type: 'string' },
                    { title: 'Dönem', key: 'donem_bas', order: true, type: 'string',
                      columnFormatter: (elm, rowData, col) => {
                          const b = col || rowData.donem_bas || '';
                          const e = rowData.donem_bit || '';
                          const span = document.createElement('span');
                          span.textContent = b && e ? (b === e ? b : b + ' - ' + e) : (b || e || '-');
                          return span;
                      } },
                    { title: 'Oluşturulma', key: 'created_at', order: true, type: 'string' },
                    { title: '', key: 'id', order: false, type: 'string',
                      columnFormatter: (elm, rowData, columnData) => {
                          const span = document.createElement('span');
                          span.classList.add('d-flex','align-items-center','justify-content-center','gap-1');
                          const btn = document.createElement('button');
                          btn.classList.add('btn','btn-secondary');
                          btn.textContent = 'Detay';
                          btn.onclick = () => this.$router.push({ name: this.formRoute, params: { id: columnData } });
                          span.appendChild(btn);
                          if (this.authStore.permissions?.includes('per-041-05-03')) {
                              const fbtn = document.createElement('button');
                              fbtn.classList.add('btn','btn-secondary');
                              fbtn.textContent = 'Talep Oluştur';
                              fbtn.title = 'Bu ihtiyaçtan bağlı Talep Dökümanı oluştur';
                              fbtn.onclick = () => this.$router.push({ name: this.fFormRoute, query: { from: columnData } });
                              span.appendChild(fbtn);
                          }
                          const xbtn = document.createElement('button');
                          xbtn.classList.add('btn','btn-secondary');
                          xbtn.textContent = 'Excel';
                          xbtn.title = 'Kalemleri Excel olarak indir';
                          xbtn.onclick = () => this.downloadRowExcel(columnData);
                          span.appendChild(xbtn);
                          return span;
                      } },
                ],
                pageLimit: 10,
                height: '70vh',
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
    <div class="card rlist-card">
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
</style>
