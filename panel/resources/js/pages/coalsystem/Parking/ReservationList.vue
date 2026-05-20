<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import { Datepicker } from 'vanillajs-datepicker';
    import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';

    export default {
        breadcrumbs: {
            list: [ { title: 'Reservations', path: '/coalpanel/reservations' } ],
            title: 'Reservations'
        },
        setup() {
            Object.assign(Datepicker.locales, tr);
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
                this.table.setFilter([
                    { key: 'all', type: '=', value: document.getElementById('mainSearch').value.trim() }
                ]);
            },
            resetSearch(){
                document.getElementById('mainSearch').value = '';
                this.table.setFilter([]);
            },
            exportTable(){
                this.plib.openTab('POST', '/api/v1/export/documents', this.table.currentFilter,'_blank');
            },
            buildTable(){
                const headers = [
                    { title : 'Reservation', key : 'title', order : true, type : 'string' },
                    { title : 'Plate', key : 'vehicle_plate', order : true, type : 'string' },
                    { title : 'Spot', key : 'parking_spot', order : true, type : 'string' },
                    { title : 'Date', key : 'reservation_date', order : true, type : 'string' },
                    { title : 'Status', key : 'status', order : false, type : 'string' },
                    { title : 'Actions', key : 'id', order : false, type : 'string', columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('d-flex','align-items-center','justify-content-center','gap-1');
                            const editBtn = document.createElement('button');
                            editBtn.classList.add('btn','btn-secondary','action-icon-btn');
                            editBtn.title = 'Edit';
                            editBtn.innerHTML = '<i class="ki-outline ki-pencil fs-2"></i>';
                            editBtn.onclick = () => this.$router.push({ name: 'ReservationForm', params: { id: columnData } });
                            span.appendChild(editBtn);
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
                    ajax:{ url:'/api/v1/table/documents', data:{} },
                    initialFilter : [
                        { key:'form-type', type:'=', value:'op-doc-reservation-form' },
                        { key:'type', type:'=', value:'op-doc-reservation' },
                    ],
                    nextPageIcon : '<i class="ki-outline ki-arrow-right"></i>',
                    prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter:(elm,data)=>{
                        JSON.parse(data.main_attr).forEach(element => { data[element['Key']] = element['Value']; });
                        return data;
                    },
                });
                requestAnimationFrame(() => this.handleResponsiveTable());
            }
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
                    <input type="text" id="mainSearch" class="rlist-search-input" placeholder="Search reservations...">
                </div>
                <button type="button" class="rlist-btn rlist-btn-primary" @click="searchTable">
                    <i class="ki-outline ki-magnifier fs-5"></i> Search
                </button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="resetSearch">Reset</button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="exportTable">
                    <i class="ki-outline ki-exit-down fs-5"></i> Excel
                </button>
            </div>
            <div class="rlist-toolbar">
                <router-link :to="{ name: 'ReservationForm' }" class="rlist-btn rlist-btn-create">
                    <i class="ki-outline ki-plus fs-5"></i> Add Reservation
                </router-link>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="div_table"></div>
        </div>
    </div>
</template>

<style scoped>
.rlist-card { border: 1px solid #dde3ee !important; box-shadow: 0 2px 8px rgba(15,40,90,.07) !important; border-radius: 12px !important; overflow: hidden; }
.rlist-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 16px 20px !important; }
.rlist-search-group { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.rlist-search-wrap { position: relative; display: flex; align-items: center; }
.rlist-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6c757d; }
.rlist-search-input { padding-left: 40px; width: 280px; }
.rlist-btn { border-radius: 8px; min-height: 40px; }
.rlist-btn-primary { background: #154b91; color: #fff; border: none; }
.rlist-btn-ghost { background: #f8fafd; color: #154b91; border: 1px solid #dce4f2; }
.rlist-btn-create { background: #0f5132; color: #fff; border: none; }
</style>
