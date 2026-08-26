
<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import VMasker  from 'vanilla-masker';
    import { Datepicker } from 'vanillajs-datepicker';
    import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';


    export default {
        breadcrumbs: {
            list: [ { title: 'Müşteriler', path: '/coalpanel/client' } ],
            title: 'Müşteriler'
        },
        setup() {
            Object.assign(Datepicker.locales, tr);
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                useAuthStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                Datepicker
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
                        key   : 'all', // column key
                        type  : '=', // filtering type ('like','<','>')
                        value : document.getElementById('mainSearch').value.trim()//wanted column value
                    }]
                );
            },
            exportTable(){
                this.plib.openTab('POST', '/api/v1/export/clients', this.table.currentFilter,'_blank');
            },
            resetSearch(){
                document.getElementById('mainSearch').value = '';
                this.table.setFilter([]);
            },
            formatClientCard(rowData){
                const title = rowData.title || '-';
                const code = rowData.clicode || '-';

                const card = document.createElement('div');
                card.classList.add('client-card');

                const header = document.createElement('div');
                header.classList.add('client-card__header');

                const headerLeft = document.createElement('div');
                const badge = document.createElement('div');
                badge.classList.add('client-card__badge');
                badge.textContent = code;

                const cardTitle = document.createElement('h4');
                cardTitle.classList.add('client-card__title');
                cardTitle.textContent = title;

                const lifnrEl = document.createElement('div');
                lifnrEl.classList.add('text-muted','fs-7');
                lifnrEl.textContent = 'Cari Kodu: ' + (rowData.lifnr || '-');
                lifnrEl.style.marginTop = '4px';
                
                headerLeft.appendChild(badge);
                headerLeft.appendChild(cardTitle);
                headerLeft.appendChild(lifnrEl);
                
                header.appendChild(headerLeft);

                const body = document.createElement('div');
                body.classList.add('client-card__body');

                

                const footer = document.createElement('div');
                footer.classList.add('client-card__footer');

                const editBtn = document.createElement('button');
                editBtn.classList.add('client-card__action-btn');
                editBtn.type = 'button';
                editBtn.innerHTML = '<i class="ki-outline ki-pencil"></i> Düzenle';
                editBtn.onclick = () => this.$router.push({ name: 'CForm', params: { id: rowData.id } });
                footer.appendChild(editBtn);

                if (this.authStore.permissions?.includes('per-06-02')) {
                    const delBtn = document.createElement('button');
                    delBtn.classList.add('client-card__action-btn', 'client-card__action-btn--danger');
                    delBtn.type = 'button';
                    delBtn.innerHTML = '<i class="ki-outline ki-trash"></i> Sil';
                    delBtn.onclick = async () => {
                        const confirm = await Swal.fire({
                            icon: 'warning',
                            title: 'Firmayı Sil',
                            text: 'Bu işlem firmayı tamamen silecektir. Emin misiniz?',
                            showCancelButton: true,
                            confirmButtonText: 'Evet, sil',
                            cancelButtonText: 'Vazgeç',
                            reverseButtons: true,
                        });
                        if (!confirm.isConfirmed) return;
                        this.navigationStore.toggle(true);
                        const rsp = await this.plib.request({ url: '/api/v1/document/'+rowData.id, method: 'DELETE' }, null);
                        if (rsp.success) { this.table.deleteRow(rowData.id); }
                        else { this.plib.toast(this.Swal,'error',rsp.msg); }
                        setTimeout(() => this.navigationStore.toggle(false), 300);
                    };
                    footer.appendChild(delBtn);
                }

                card.appendChild(header);
                card.appendChild(body);
                if (footer.children.length) card.appendChild(footer);
                return card;
            },
            buildTestTable(){
                
                //set headers
                const headers = [
                    {
                        title : ' ',
                        key   : 'client_card',
                        order : false,
                        type  : 'string',
                        columnFormatter : (elm,rowData) => this.formatClientCard(rowData)
                    },{
                        title : 'Firma Ünvan',
                        key   : 'title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Firma Kodu',
                        key   : 'clicode',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Cari Kodu',
                        key   : 'lifnr',
                        order : true,
                        type  : 'string',
                    },{
                        title : '',
                        key   : 'id',
                        order : false,
                        colAlign : 'center',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('d-flex','align-items-center','justify-content-center','gap-1');

                            const editBtn = document.createElement('button');
                            editBtn.classList.add('btn','btn-secondary','action-icon-btn');
                            editBtn.title = 'Düzenle';
                            editBtn.innerHTML = '<i class="ki-outline ki-pencil fs-2"></i>';
                            editBtn.onclick = () => this.$router.push({ name: 'CForm', params: { id: columnData } });
                            span.appendChild(editBtn);

                            if (this.authStore.permissions?.includes('per-06-02')) {
                                const delBtn = document.createElement('button');
                                delBtn.classList.add('btn','btn-secondary','action-icon-btn','action-icon-btn--danger');
                                delBtn.title = 'Sil';
                                delBtn.innerHTML = '<i class="ki-outline ki-trash fs-2"></i>';
                                delBtn.onclick = async () => {
                                    const confirm = await Swal.fire({
                                        icon: 'warning',
                                        title: 'Firmayı Sil',
                                        text: 'Bu işlem firmayı tamamen silecektir. Emin misiniz?',
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
                
                //initiate table
                this.table = new PickleTable({
                    container : '#div_table', //table target div
                    headers   : headers,
                    pageLimit : 10, // -1 for closing pagination
                    height    : '70vh',
                    type      : 'ajax',
                    columnSearch : false, // true - false for opening and closig
                    paginationType : 'number',// scroll - number (number for default)
                    ajax:{
                        url:'/api/v1/table/documents',
                        data:{
                            //order:{},
                        }
                    },
                    initialFilter : [
                        {
                            key   : 'form-type',
                            type  : '=',
                            value : 'op-doc-client-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-client'
                        }
                    ],
                    nextPageIcon : '<i class="ki-outline ki-arrow-right"></i>',
                    prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter:(elm,data)=>{
                        //console.log(elm,data);
                        //modify row element
                        //elm.style.backgroundColor = 'yellow';
                        //modify data
                        JSON.parse(data.main_attr).forEach(element => {
                            data[element['Key']] = element['Value'];
                            //if(data['cont_name'] == undefined) data['cont_name'] = []
                            //if(element['Key'].includes('cont_name')) data['cont_name'].push(element['Value']);
                        });
                        //data['cont_name'] = (data['cont_name'] ?? []).join(' , ');
                        //data.status = JSON.parse(data.status).OpTitle;
                        return data;
                    },
                });

                // wait one frame for PickleTable DOM render, then apply responsive behavior
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
                    <input type="text" id="mainSearch" class="rlist-search-input" placeholder="Müşteri ara...">
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
                    v-if="useAuthStore().permissions?.includes('per-06-02')"
                    :to="{ name: 'CForm' }"
                    class="rlist-btn rlist-btn-create"
                >
                    <i class="ki-outline ki-plus fs-5"></i> Firma Oluştur
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
    border: 1px solid #dde3ee !important;
    box-shadow: 0 2px 8px rgba(15,40,90,.07) !important;
    border-radius: 12px !important;
    overflow: hidden;
}
.rlist-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 16px 20px !important;
    border-bottom: 1px solid #eef0f4;
    background: #fff;
    min-height: unset !important;
}
.rlist-search-group {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.rlist-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.rlist-search-icon {
    position: absolute;
    left: 12px;
    color: #99a1b7;
    pointer-events: none;
}
.rlist-search-input {
    height: 40px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0 14px 0 38px;
    font-size: .9rem;
    color: #1e2a3b;
    background: #f8fafc;
    outline: none;
    width: 240px;
    transition: border-color .15s, background .15s, box-shadow .15s;
}
.rlist-search-input:focus {
    border-color: #154b91;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(21,75,145,.08);
}
.rlist-search-input::placeholder { color: #b0bac9; }
.rlist-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 40px;
    padding: 0 16px;
    border-radius: 8px;
    font-size: .875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s, color .15s, box-shadow .15s;
    text-decoration: none;
}
.rlist-btn-primary { background: #154b91; color: #fff; }
.rlist-btn-primary:hover { background: #0f3a72; color: #fff; }
.rlist-btn-ghost { background: #f4f6fa; color: #4b5675; border: 1px solid #e2e8f0; }
.rlist-btn-ghost:hover { background: #e8edf5; color: #1e2a3b; }
.rlist-btn-create { background: #154b91; color: #fff !important; }
.rlist-btn-create:hover { background: #0f3a72; color: #fff !important; box-shadow: 0 4px 12px rgba(21,75,145,.25); }

#div_table { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }

:deep(.pickletable table) { width: 100%; min-width: 100%; border-collapse: collapse; table-layout: auto; }
:deep(.pickletable th), :deep(.pickletable td) {
    white-space: nowrap;
    max-width: 320px;
    overflow: hidden;
    text-overflow: ellipsis;
}
:deep(.pickletable thead tr) { background: #154b91 !important; }
:deep(.pickletable thead) { --bs-emphasis-color: rgba(255,255,255,.6); }
:deep(.pickletable thead th) {
    background: #154b91 !important;
    color: rgba(255,255,255,.85) !important;
    font-size: .82rem !important;
    font-weight: 600 !important;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding: 13px 16px !important;
    border: none !important;
    border-right: 1px solid rgba(255,255,255,.1) !important;
    white-space: nowrap;
}
:deep(.pickletable thead th:last-child) { border-right: none !important; }
:deep(.pickletable thead th svg),
:deep(.pickletable thead th i) { color: rgba(255,255,255,.6) !important; background: transparent !important; }
:deep(.pickletable tbody tr) { border-bottom: 1px solid #eef0f4 !important; background: #fff !important; transition: background .12s; }
:deep(.pickletable tbody tr:hover) { background: #f7f9fd !important; }
:deep(.pickletable tbody td) {
    padding: 14px 16px !important;
    font-size: 1rem !important;
    color: #2d3748 !important;
    background: transparent !important;
    border: none !important;
    border-right: 1px solid #f0f2f7 !important;
    vertical-align: middle !important;
    white-space: normal !important;
    word-break: break-word;
    max-width: 300px;
}
:deep(.pickletable tbody td:last-child) { border-right: none !important; }

:deep(.pickletable .action-icon-btn > i) { padding-right: 0 !important; }
:deep(.pickletable .btn-secondary) {
    background: #f4f6fa !important;
    color: #4b5675 !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 7px !important;
    width: 34px !important;
    height: 34px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}
:deep(.pickletable .btn-secondary:hover) { background: #154b91 !important; color: #fff !important; border-color: #154b91 !important; }
:deep(.pickletable .action-icon-btn--danger:hover) { background: #dc2626 !important; color: #fff !important; border-color: #dc2626 !important; }

:deep(.pickletable .divPagination) { padding: 12px 16px !important; border-top: 1px solid #eef0f4; justify-content: flex-end !important; }
:deep(.pickletable .divPagination button) { height: 32px !important; min-width: 32px !important; border-radius: 6px !important; font-size: .82rem !important; font-weight: 600 !important; border: 1px solid #e2e8f0 !important; background: #fff !important; color: #4b5675 !important; }
:deep(.pickletable .divPagination button.current) { background: #154b91 !important; color: #fff !important; border-color: #154b91 !important; }
:deep(.pickletable .divPagination button:hover:not(.current)) { background: #f4f6fa !important; color: #154b91 !important; }

:deep(.pickletable td:first-child),
:deep(.pickletable th:first-child){
    display: none;
}

:deep(.client-card) {
    width: 100%;
    border: 1px solid rgba(21, 75, 145, 0.12);
    border-radius: 18px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 10px 24px rgba(15, 40, 90, 0.05);
}

:deep(.client-card__header) {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 16px 16px 12px;
    background: linear-gradient(135deg, rgba(21, 75, 145, 0.08), rgba(21, 75, 145, 0.02));
}

:deep(.client-card__badge) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 12px;
    border-radius: 999px;
    background: rgba(21, 75, 145, 0.12);
    color: #154b91;
    font-size: 0.78rem;
    font-weight: 700;
    margin-bottom: 8px;
}

:deep(.client-card__title) {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #102a43;
}

:deep(.client-card__subtitle) {
    margin: 4px 0 0;
    color: #5e6e82;
    font-size: 0.86rem;
}

:deep(.client-card__body) {
    display: grid;
    gap: 10px;
    padding: 0 16px 14px;
}

:deep(.client-card__row) {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 10px;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid rgba(228, 232, 240, 0.9);
}

:deep(.client-card__row:last-child) {
    border-bottom: none;
}

:deep(.client-card__label) {
    color: #6b7280;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

:deep(.client-card__value) {
    color: #102a43;
    font-size: 0.95rem;
    font-weight: 600;
    text-align: right;
}

:deep(.client-card__footer) {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px 16px 16px;
    border-top: 1px solid rgba(228, 232, 240, 0.9);
}

:deep(.client-card__action-btn) {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 10px;
    border: 1px solid rgba(21, 75, 145, 0.18);
    background: #ffffff;
    color: #154b91;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
}

:deep(.client-card__action-btn:hover) {
    background: rgba(21, 75, 145, 0.08);
}

:deep(.client-card__action-btn--danger) {
    border-color: rgba(220, 38, 38, 0.18);
    color: #b91c1c;
}

:deep(.client-card__action-btn--danger:hover) {
    background: rgba(220, 38, 38, 0.08);
}

@media (max-width: 767px) {
    .rlist-header { padding: 12px 14px !important; }
    .rlist-search-input { width: 160px; }

    :deep(.pickletable thead) {
        display: none !important;
    }

    :deep(.pickletable td:first-child),
    :deep(.pickletable th:first-child){
        display: unset !important;
    }

    :deep(.pickletable td:not(:first-child)),
    :deep(.pickletable th:not(:first-child)){
        display: none !important;
    }

    :deep(.pickletable tbody tr) {
        border: unset !important;
        border-bottom: unset !important;
    }

    :deep(.pickletable td:first-child){
        width: 100% !important;
        padding: 10px 10px !important;
    }
}
</style>