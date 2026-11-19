
<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';

    export default {
        breadcrumbs: {
            list: [ { title: 'Güç & Kapsam Tanımları', path: '/coalpanel/kva' } ],
            title: 'Güç & Kapsam Tanımları'
        },
        setup() {
            return {
                useNavigationStore,
                useAuthStore,
                PickleTable,
                Plib,
                wTrans,
                Swal
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildKvaTable();
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
                navigationStore : useNavigationStore(),
                authStore    : useAuthStore(),
                activeTab    : 'kva',
                scopeBuilt   : false,
                _tableResizeHandler : null,
            }
        },
        computed:{
            isKva(){ return this.activeTab === 'kva'; },
            searchPlaceholder(){ return this.isKva ? 'kVA ara...' : 'Kapsam tipi ara...'; },
        },
        methods: {
            switchTab(tab){
                if(this.activeTab === tab) return;
                this.activeTab = tab;
                if(tab === 'scope' && !this.scopeBuilt){
                    this.buildScopeTable();
                    this.scopeBuilt = true;
                }
                requestAnimationFrame(() => this.handleResponsiveTable());
            },
            activeTable(){
                return this.isKva ? this.table : this.scopeTable;
            },
            activeContainerId(){
                return this.isKva ? 'div_table' : 'div_scope_table';
            },
            handleResponsiveTable(){
                const container = document.getElementById(this.activeContainerId());
                if(!container) return;

                const table = container.querySelector('.pickletable table');
                if(!table) return;

                if(this._tableResizeHandler){
                    window.removeEventListener('resize', this._tableResizeHandler);
                }

                this._tableResizeHandler = () => {
                    const isMobile = window.innerWidth < 768;

                    if(isMobile){
                        container.style.overflowX = 'auto';
                        table.style.minWidth = '600px';
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
                this.activeTable()?.setFilter(
                    [{
                        key   : 'all',
                        type  : '=',
                        value : document.getElementById('mainSearch').value.trim()
                    }]
                );
            },
            exportTable(){
                const model = this.isKva ? 'kvas' : 'scopevalues';
                this.plib.openTab('POST', '/api/v1/export/'+model, this.activeTable().currentFilter,'_blank');
            },
            resetSearch(){
                document.getElementById('mainSearch').value = '';
                this.activeTable()?.setFilter([]);
            },
            createNew(){
                this.$router.push({ name: this.isKva ? 'KvForm' : 'SForm' });
            },
            fmtDate(value){
                const s = String(value ?? '');
                const m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
                return m ? `${m[3]}.${m[2]}.${m[1]} ${m[4]}:${m[5]}` : s;
            },
            buildKvaTable(){
                const headers = [
                    {
                        title : 'Başlık',
                        key   : 'title',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('kv-cell-title');
                            span.textContent = columnData ?? '';
                            return span;
                        }
                    },{
                        title : 'Oluşturulma',
                        key   : 'created_at',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('kv-date');
                            span.textContent = this.fmtDate(columnData);
                            return span;
                        }
                    },{
                        title : '',
                        key   : 'id',
                        order : false,
                        colAlign : 'right',
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('d-flex','align-items-center','justify-content-end','gap-1');

                            const editBtn = document.createElement('button');
                            editBtn.classList.add('btn','btn-secondary','action-icon-btn');
                            editBtn.title = 'Düzenle';
                            editBtn.innerHTML = '<i class="ki-outline ki-pencil fs-2"></i>';
                            editBtn.onclick = () => this.$router.push({ name: 'KvForm', params: { id: columnData } });
                            span.appendChild(editBtn);

                            if (this.authStore.permissions?.includes('per-09-02')) {
                                const delBtn = document.createElement('button');
                                delBtn.classList.add('btn','btn-secondary','action-icon-btn','action-icon-btn--danger');
                                delBtn.title = 'Sil';
                                delBtn.innerHTML = '<i class="ki-outline ki-trash fs-2"></i>';
                                delBtn.onclick = async () => {
                                    const confirm = await Swal.fire({
                                        icon: 'warning',
                                        title: 'kVA Tanımını Sil',
                                        text: 'Bu işlem tanımı tamamen silecektir. Emin misiniz?',
                                        showCancelButton: true,
                                        confirmButtonText: 'Evet, sil',
                                        cancelButtonText: 'Vazgeç',
                                        reverseButtons: true,
                                    });
                                    if (!confirm.isConfirmed) return;
                                    this.navigationStore.toggle(true);
                                    const rsp = await this.plib.request({ url: '/api/v1/document/'+columnData, method: 'DELETE' }, null);
                                    if (rsp.success) { this.table.deleteRow(columnData); }
                                    else { this.plib.toast(this.Swal,'error',rsp.msg); }
                                    setTimeout(() => this.navigationStore.toggle(false), 300);
                                };
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
                    ajax:{
                        url:'/api/v1/table/documents',
                        data:{}
                    },
                    initialFilter : [
                        {
                            key   : 'form-type',
                            type  : '=',
                            value : 'op-doc-kva-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-kva'
                        }
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

                requestAnimationFrame(() => this.handleResponsiveTable());
            },
            buildScopeTable(){
                const headers = [
                    {
                        title : 'Başlık',
                        key   : 'title',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('kv-cell-title');
                            span.textContent = columnData ?? '';
                            return span;
                        }
                    },{
                        title : 'Kod',
                        key   : 'code',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const pill = document.createElement('span');
                            pill.classList.add('kv-pill','kv-pill-code');
                            pill.textContent = columnData ?? '';
                            return pill;
                        }
                    },{
                        title : 'Oluşturulma',
                        key   : 'created_at',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('kv-date');
                            span.textContent = this.fmtDate(columnData);
                            return span;
                        }
                    },{
                        title : '',
                        key   : 'id',
                        order : false,
                        colAlign : 'right',
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('d-flex','align-items-center','justify-content-end','gap-1');

                            const editBtn = document.createElement('button');
                            editBtn.classList.add('btn','btn-secondary','action-icon-btn');
                            editBtn.title = 'Düzenle';
                            editBtn.innerHTML = '<i class="ki-outline ki-pencil fs-2"></i>';
                            editBtn.onclick = () => this.$router.push({ name: 'SForm', params: { id: columnData } });
                            span.appendChild(editBtn);

                            if (this.authStore.permissions?.includes('per-09-02')) {
                                const delBtn = document.createElement('button');
                                delBtn.classList.add('btn','btn-secondary','action-icon-btn','action-icon-btn--danger');
                                delBtn.title = 'Sil';
                                delBtn.innerHTML = '<i class="ki-outline ki-trash fs-2"></i>';
                                delBtn.onclick = async () => {
                                    const confirm = await Swal.fire({
                                        icon: 'warning',
                                        title: 'Kapsam Tipini Sil',
                                        text: 'Bu işlem tanımı tamamen silecektir. Emin misiniz?',
                                        showCancelButton: true,
                                        confirmButtonText: 'Evet, sil',
                                        cancelButtonText: 'Vazgeç',
                                        reverseButtons: true,
                                    });
                                    if (!confirm.isConfirmed) return;
                                    this.navigationStore.toggle(true);
                                    const rsp = await this.plib.request({ url: '/api/v1/document/'+columnData, method: 'DELETE' }, null);
                                    if (rsp.success) { this.scopeTable.deleteRow(columnData); }
                                    else { this.plib.toast(this.Swal,'error',rsp.msg); }
                                    setTimeout(() => this.navigationStore.toggle(false), 300);
                                };
                                span.appendChild(delBtn);
                            }

                            return span;
                        }
                    }
                ];

                this.scopeTable = new PickleTable({
                    container : '#div_scope_table',
                    headers   : headers,
                    pageLimit : 10,
                    height    : '70vh',
                    type      : 'ajax',
                    columnSearch : false,
                    paginationType : 'number',
                    ajax:{
                        url:'/api/v1/table/documents',
                        data:{}
                    },
                    initialFilter : [
                        {
                            key   : 'form-type',
                            type  : '=',
                            value : 'op-doc-scope-value-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-scope-value'
                        }
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

                requestAnimationFrame(() => this.handleResponsiveTable());
            },
        }
    }

</script>
<template>
    <div class="card rlist-card">
        <div class="card-header rlist-header">
            <div class="kv-tabs">
                <button type="button" class="kv-tab" :class="{ active: isKva }" @click="switchTab('kva')">
                    <i class="ki-outline ki-flash fs-5"></i> Güç (kVA)
                </button>
                <button type="button" class="kv-tab" :class="{ active: !isKva }" @click="switchTab('scope')">
                    <i class="ki-outline ki-category fs-5"></i> Kapsam Tipleri
                </button>
            </div>
            <div class="rlist-search-group">
                <div class="rlist-search-wrap">
                    <i class="ki-duotone ki-magnifier fs-4 rlist-search-icon">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text" id="mainSearch" class="rlist-search-input" :placeholder="searchPlaceholder">
                </div>
                <button type="button" class="rlist-btn rlist-btn-primary" @click="searchTable">
                    <i class="ki-outline ki-magnifier fs-5"></i> Ara
                </button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="resetSearch">Sıfırla</button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="exportTable">
                    <i class="ki-outline ki-exit-down fs-5"></i> Excel
                </button>
            </div>
            <div class="rlist-toolbar">
                <button
                    v-if="useAuthStore().permissions?.includes('per-09-02')"
                    type="button"
                    class="rlist-btn rlist-btn-create"
                    @click="createNew"
                >
                    <i class="ki-outline ki-plus fs-5"></i> {{ isKva ? 'kVA Tanımı Oluştur' : 'Kapsam Tipi Oluştur' }}
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="div_table" v-show="isKva"></div>
            <div id="div_scope_table" v-show="!isKva"></div>
        </div>
    </div>
</template>

<style scoped>
.rlist-card {
    border: 1px solid #e2e8f0 !important;
    border-radius: 16px !important;
    overflow: hidden;
    background: #fff !important;
    box-shadow: 0 8px 32px rgba(15,23,42,.06), 0 1px 3px rgba(15,23,42,.04) !important;
}
.rlist-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 14px 18px !important;
    border-bottom: 1px solid #f1f5f9 !important;
    background: #fff !important;
    min-height: unset !important;
}
.rlist-search-group {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.kv-tabs {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f1f5f9;
    border-radius: 12px;
    padding: 4px;
}
.kv-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: .01em;
    padding: 8px 14px;
    border-radius: 9px;
    cursor: pointer;
    transition: all .15s ease;
}
.kv-tab:hover { color: #0f172a; }
.kv-tab.active {
    background: #fff;
    color: #0e8ea4;
    box-shadow: 0 1px 4px rgba(15,23,42,.12);
}

/* ---- table row polish ---- */
.rlist-card :deep(.divTable){
    background: #fff;
}
.rlist-card :deep(table.dataTable){
    border-collapse: separate;
    border-spacing: 0;
}
.rlist-card :deep(table.dataTable thead th){
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .07em;
    text-transform: uppercase;
    padding: 14px 18px;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.rlist-card :deep(table.dataTable tbody td){
    padding: 15px 18px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
    color: #334155;
    background: transparent;
    transition: background .15s ease;
}
.rlist-card :deep(table.dataTable tbody tr){
    transition: background .15s ease;
}
.rlist-card :deep(table.dataTable tbody tr:hover td){
    background: #f0fbfc;
}
.rlist-card :deep(table.dataTable tbody tr:hover td:first-child){
    border-top-left-radius: 12px;
    border-bottom-left-radius: 12px;
}
.rlist-card :deep(table.dataTable tbody tr:hover td:last-child){
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
}
.rlist-card :deep(.kv-cell-title){
    font-weight: 700;
    color: #0f172a;
}
.rlist-card :deep(.kv-pill){
    display: inline-flex;
    align-items: center;
    padding: 4px 13px;
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 700;
    line-height: 1.5;
    white-space: nowrap;
}
.rlist-card :deep(.kv-pill-kva){
    background: #e6f7fa;
    color: #0e8ea4;
}
.rlist-card :deep(.kv-pill-code){
    background: #eef2ff;
    color: #4338ca;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
    letter-spacing: .01em;
}
.rlist-card :deep(.kv-chip){
    display: inline-flex;
    align-items: center;
    padding: 3px 11px;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: .02em;
    white-space: nowrap;
    background: #f1f5f9;
    color: #475569;
}
.rlist-card :deep(.kv-chip--hucre){ background: #ecfdf5; color: #047857; }
.rlist-card :deep(.kv-chip--sdk){ background: #fff7ed; color: #c2410c; }
.rlist-card :deep(.kv-chip--pano){ background: #eef2ff; color: #4338ca; }
.rlist-card :deep(.kv-date){
    color: #94a3b8;
    font-size: 12.5px;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.rlist-card :deep(.action-icon-btn){
    width: 34px !important;
    height: 34px !important;
    padding: 0 !important;
    border-radius: 10px !important;
    border: 1px solid transparent !important;
    background: #f1f5f9 !important;
    color: #475569 !important;
    box-shadow: none !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    transition: all .15s ease;
}
.rlist-card :deep(.action-icon-btn:hover){
    background: #e6f7fa !important;
    color: #0e8ea4 !important;
    transform: translateY(-1px);
}
.rlist-card :deep(.action-icon-btn--danger){
    background: #fef2f2 !important;
    color: #dc2626 !important;
}
.rlist-card :deep(.action-icon-btn--danger:hover){
    background: #fee2e2 !important;
    color: #b91c1c !important;
}
.rlist-card :deep(.action-icon-btn > i){
    font-size: 15px !important;
}
</style>
