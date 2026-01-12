
<script>
    import { useNavigationStore } from '@/stores/navigation'
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import { Datepicker } from 'vanillajs-datepicker';
    import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';
    import Swal from 'sweetalert2';
    import dayjs from 'dayjs';

    export default {
        setup() {
            Object.assign(Datepicker.locales, tr);
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                Datepicker,
                dayjs
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTestTable();
            
            this.navigationStore.setBread([
                {
                    title : this.wTrans('menu.home'),
                    url   : '/kontent',
                },
                {
                    title : this.wTrans('menu.logs'),
                    url   : '/kontent/logs',
                }
            ] ,this.wTrans('form.logs.list'));

            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },  
        data() {
            return {
                pageStatus      : 'list',
                plib            : new Plib(),
                navigationStore : useNavigationStore(),
            }
        },
        methods: {
            buildTestTable(){
                document.getElementById('div_table').innerHTML = '';
                //set headers
                const headers = [
                    {
                        title : 'Web Sitesi İsmi',
                        key   : 'root_url',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Durum Kodu',
                        key   : 'status_code',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Kontrol Saati',
                        key   : 'created_at',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            return dayjs(columnData).format('DD.MM.YYYY HH:mm'); 
                        }
                    },{
                        title : 'Url',
                        key   : 'url',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnClick: (elm,rowData,columnData) => {
                            window.open(columnData, '_blank');
                        },
                        columnFormatter : (elm,rowData,columnData) => {
                            return '<div class="d-flex align-items-center"><i class="fa fa-arrow-right fs-5 me-5"></i>'+' '+columnData+'</div>';
                        }
                        
                    }/*,{
                        title : '',
                        key   : 'id',
                        order : false,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const div = document.createElement('div');
                            div.classList.add('row','justify-content-center');

                            let btn        = document.createElement('a');
                            btn.href        = 'javascript:;';
                            btn.style.width = 'auto';
                            btn.innerHTML   = '<i class="fs-5 fa fa-recycle selectable-icon" style="color:#95818C"  role="img"></i>';
                            btn.onclick     = async () => {
                                this.navigationStore.toggle(true);
                                const rsp = await this.plib.request({
                                    url      : '/api/v1/setprocess/'+columnData,
                                    method   : 'GET',
                                },null);
                                
                                if(rsp.success) this.plib.toast(this.Swal,'success','Kontrol Süreci Başlatıldı');
                                else this.plib.toast(this.Swal,'error','Hata Oluştu');

                                setTimeout(() => {
                                    this.navigationStore.toggle(false);
                                }, 300);
                                
                            };
                            div.appendChild(btn);

                            btn              = document.createElement('a');
                            btn.href        = '/kontent/flist/form/'+columnData;
                            btn.style.width = 'auto';
                            btn.innerHTML   = '<i class="fs-5 fa fa-pen selectable-icon" style="color:#95818C"  role="img"></i>';
                            div.appendChild(btn);

                            btn             = document.createElement('a');
                            btn.href        = 'javascript:;';
                            btn.style.width = 'auto';
                            btn.innerHTML   = '<i class="fs-5 fa fa-trash selectable-icon" style="color:#95818C"  role="img"></i>';
                            btn.onclick     = async () => {
                                this.navigationStore.toggle(true);
                                await this.plib.request({
                                    url      : '/api/v1/content/'+columnData,
                                    method   : 'DELETE',
                                },null);

                                this.table.deleteRow(columnData);
                                setTimeout(() => {
                                    this.navigationStore.toggle(false);
                                }, 300);
                                
                            };
                            div.appendChild(btn);

                            return div;
                        }
                    }*/
                ];
                
                //initiate table
                this.table = new PickleTable({
                    container : '#div_table', //table target div
                    headers   : headers,
                    pageLimit : 10, // -1 for closing pagination
                    height    : '50vh',
                    type      : 'ajax',
                    //columnSearch : true, // true - false for opening and closig
                    paginationType : 'number',// scroll - number (number for default)
                    ajax:{
                        url:'/api/v1/table/user_logs',
                        data:{
                            //order:{},
                        }
                    },
                    // do anything from returned data
                    ajaxReturnCallback : (data) => {
                        setTimeout(() => {
                            try{
                                const currentPage = document.querySelector('.btn_page.current').dataset.page;
                                const limit = currentPage * data.filteredCount;
                                const start = (currentPage - 1) * data.filteredCount;

                                let span = document.querySelector('.divPagination span');
                                if(span == null) {
                                    span = document.createElement('span');
                                    document.querySelector('.divPagination').appendChild(span);
                                }

                                span.innerHTML = data.totalCount+' Adet kayıttan '+start+' - '+limit+' arası gösteriliyor.';
                            }catch(e){

                            }
                        }, 200);
                    },
                    initialFilter : [
                        {
                            key   : 'type_key',
                            type  : '=',
                            value : 'log-url-error'
                        }
                    ],
                    nextPageIcon : '<i class="fa fa-solid fa-chevron-right"></i>',
                    prevPageIcon : '<i class="fa fa-solid fa-chevron-left"></i>',
                    rowFormatter:(elm,data)=>{
                        const desc = JSON.parse(data.description);

                        for(let key in desc){
                            data[key] = desc[key];
                        }
                        return data;
                    },
                });
            },
            searchTable(){
                this.table.setFilter(
                    [{
                        key   : 'all', // column key
                        type  : '=', // filtering type ('like','<','>')
                        value : document.getElementById('mainSearch').value.trim()//wanted column value
                    }]
                );
            }
        }
    }

</script>
<template>
    <!--<div class="card">
        <div class="card-body">
            <div id="div_table"></div>
        </div>
    </div>-->
    <div class="table-tab">
        <div class="table-tab-head">
            <button href="javascript:;" class="table-toggle active" body="1">{{ $t('form.logs.list') }}</button>
            
        </div>
        <div class="table-tab-body">
            <div class="table-custom-filter mb-5">
                <div class="table-custom-filter-search">
                    <span class="kontent-icon" name="SearchFilter"></span>
                    <input class="search-put" type="text" id="mainSearch">
                    <button type="button" @click="searchTable">Sorgula <span class="kontent-icon" name="Search"></span></button>
                </div>
                <div class="table-custom-filter-button-group">
                    <button class="export-excel"  @click="plib.openTab('GET','/export/documents/user_logs',null, '_blank')" id="exportExcel">Excel’e Aktar</button>
                </div>
                <!--<div class="table-custom-filter-date">
                    <p>Tarih Aralığı</p>
                    <div class="date-form">
                        <label for="startDate">Başlangıç Tarihi:</label>
                        <input type="text" id="startDate" placeholder="--.--.---">
                        <span class="icon"><span class="kontent-icon" name="DownFeth"></span></span>
                    </div>
                    <div class="date-form">
                        <label for="endDate">Bitiş Tarihi:</label>
                        <input type="text" id="endDate" placeholder="--.--.----">
                        <span class="icon"><span class="kontent-icon" name="DownFeth"></span></span>
                    </div>
                </div>-->
            </div>
            <div class="body-1 table-main">
                <div id="div_table"></div>
            </div>
        </div>
    </div>
</template>
