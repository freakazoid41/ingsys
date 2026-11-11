
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
            list: [ { title: 'Ürünler', path: '/coalpanel/products' } ],
            title: 'Ürünler'
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
            this.buildTestTable();
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
                _tableResizeHandler : null,
            }
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
                this.table.setFilter(
                    [{
                        key   : 'all',
                        type  : '=',
                        value : document.getElementById('mainSearch').value.trim()
                    }]
                );
            },
            exportTable(){
                this.plib.openTab('POST', '/api/v1/export/products', this.table.currentFilter,'_blank');
            },
            resetSearch(){
                document.getElementById('mainSearch').value = '';
                this.table.setFilter([]);
            },
            buildTestTable(){
                const headers = [
                    {
                        title : 'Ürün Kodu',
                        key   : 'prod_code',
                        order : true,
                        type  : 'string',
                    },{
                        title : 'Kısa Ad',
                        key   : 'short_name',
                        order : true,
                        type  : 'string',
                    },{
                        title : 'Birim',
                        key   : 'unit',
                        order : true,
                        type  : 'string',
                    },{
                        title : 'Kategori',
                        key   : 'category_title',
                        order : true,
                        type  : 'string',
                    },{
                        title : 'Oluşturulma',
                        key   : 'created_at',
                        order : true,
                        type  : 'string',
                    },{
                        title : '',
                        key   : 'id',
                        order : false,
                        colAlign : 'center',
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('d-flex','align-items-center','justify-content-center','gap-1');

                            const editBtn = document.createElement('button');
                            editBtn.classList.add('btn','btn-secondary','action-icon-btn');
                            editBtn.title = 'Düzenle';
                            editBtn.innerHTML = '<i class="ki-outline ki-pencil fs-2"></i>';
                            editBtn.onclick = () => this.$router.push({ name: 'PForm', params: { id: columnData } });
                            span.appendChild(editBtn);

                            if (this.authStore.permissions?.includes('per-09-02')) {
                                const delBtn = document.createElement('button');
                                delBtn.classList.add('btn','btn-secondary','action-icon-btn','action-icon-btn--danger');
                                delBtn.title = 'Sil';
                                delBtn.innerHTML = '<i class="ki-outline ki-trash fs-2"></i>';
                                delBtn.onclick = async () => {
                                    const confirm = await Swal.fire({
                                        icon: 'warning',
                                        title: 'Ürünü Sil',
                                        text: 'Bu işlem ürünü tamamen silecektir. Emin misiniz?',
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
                            value : 'op-doc-product-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-product'
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
            <div class="rlist-search-group">
                <div class="rlist-search-wrap">
                    <i class="ki-duotone ki-magnifier fs-4 rlist-search-icon">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text" id="mainSearch" class="rlist-search-input" placeholder="Ürün ara...">
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
                <router-link
                    v-if="useAuthStore().permissions?.includes('per-09-02')"
                    :to="{ name: 'PForm' }"
                    class="rlist-btn rlist-btn-create"
                >
                    <i class="ki-outline ki-plus fs-5"></i> Ürün Oluştur
                </router-link>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="div_table"></div>
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
</style>
