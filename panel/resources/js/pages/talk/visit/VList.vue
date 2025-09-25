
<script>
    import { useNavigationStore } from '@/stores/navigation'
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';

    export default {
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTestTable();
            
            this.navigationStore.setBread([
                {
                    title : this.wTrans('menu.home'),
                    url   : '/panel',
                },
                {
                    title : this.wTrans('menu.visit'),
                    url   : '/panel/visit',
                }
            ] ,this.wTrans('form.visit.list'));

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
                   /*{
                        title : 'ID',
                        key   : 'id',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        
                    },*/{
                        title : 'Ad Soyad',
                        key   : 'name',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        //columnFormatter : (elm,rowData,columnData) => rowData.name+' '+rowData.surname
                    },{
                        title : 'Telefon',
                        key   : 'phone',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },/*{
                        title : 'E-Posta',
                        key   : 'email',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },*/{
                        title : 'Tesis',
                        key   : 'facility',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Giriş',
                        key   : 'entered_at',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            columnData = columnData.split(' ');
                            return columnData[0].split('-').reverse().join('/')+' '+columnData[1];
                        }
                    },{
                        title : 'Video Başlama',
                        key   : 'id',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            if(rowData['video_start']){
                                rowData['video_start'] = rowData['video_start'].split(' ');
                                return rowData['video_start'][1];
                            }else{
                                return '-'
                            }
                            
                        }
                    },{
                        title : 'Video Bitiş',
                        key   : 'id',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            if(rowData['video_end']){
                                rowData['video_end'] = rowData['video_end'].split(' ');
                                return rowData['video_end'][1];
                            }else{
                                return '-'
                            }
                            
                        }
                    },{
                        title : 'İzleme Süresi',
                        key   : 'id',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            if(rowData['video_second']){
                                return this.plib.fancyTimeFormat(rowData['video_second']);
                            }else{
                                return '0:00'
                            }
                            
                        }
                    },{
                        title : 'İzleme Durum',
                        key   : 'id',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            return rowData['video_status'] ?? 'İzlemedi';
                        }
                    },{
                        title : 'Test Durum',
                        key   : 'id',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            return rowData['test-result'] != undefined ? rowData['test-result'].split('/')[1]  : '-';
                        }
                    },{
                        title : 'Çıkış',
                        key   : 'exited_at',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            if(columnData != null){
                                columnData = columnData.split(' ');
                                return columnData[0].split('-').reverse().join('/')+' '+columnData[1];
                            }else{
                                return 'Bekleniyor..';
                            }
                            
                        }
                    },/*{
                        title : '',
                        key   : 'id',
                        order : false,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const div = document.createElement('div');
                            div.classList.add('row','justify-content-center');

                            const edit       = document.createElement('a');
                            edit.href        = '/talkpanel/visit/form/'+columnData;
                            edit.style.width = 'auto';
                            edit.innerHTML   = '<i class="fs-5 fa fa-pen selectable-icon" style="color:#95818C"  role="img"></i>';
                            div.appendChild(edit);

                            const del       = document.createElement('a');
                            del.href        = 'javascript:;';
                            del.style.width = 'auto';
                            del.innerHTML   = '<i class="fs-5 fa fa-trash selectable-icon" style="color:#95818C"  role="img"></i>';
                            del.onclick     = async () => {
                                this.navigationStore.toggle(true);
                                await this.plib.request({
                                    url      : '/api/v1/document/'+columnData,
                                    method   : 'DELETE',
                                },null);

                                this.table.deleteRow(columnData);
                                setTimeout(() => {
                                    this.navigationStore.toggle(false);
                                }, 300);
                                
                            };
                            div.appendChild(del);

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
                        url:'/api/v1/table/documents',
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
                            key   : 'form-type',
                            type  : '=',
                            value : 'op-doc-visit-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-visit'
                        }
                    ],
                    nextPageIcon : '<i class="fa fa-solid fa-chevron-right"></i>',
                    prevPageIcon : '<i class="fa fa-solid fa-chevron-left"></i>',
                    rowFormatter:(elm,data)=>{
                        //console.log(elm,data);
                        //modify row element
                        //elm.style.backgroundColor = 'yellow';
                        //modify data
                        JSON.parse(data.main_attr).forEach(element => {
                            data[element['Key']] = element['Value'];
                            if(data['cont_name'] == undefined) data['cont_name'] = []
                            if(element['Key'].includes('cont_name')) data['cont_name'].push(element['Value']);
                        });
                        data['cont_name'] = (data['cont_name'] ?? []).join(' , ');
                        //data.status = JSON.parse(data.status).OpTitle;
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
            <button href="javascript:;" class="table-toggle active" body="1">{{ $t('form.visit.list') }}</button>
            <router-link :to="{ name: 'VForm' }"><button class="table-toggle" body="2">{{ $t('form.visit') }}</button></router-link>
        </div>
        <div class="table-tab-body">
            <div class="table-custom-filter mb-5">
                <div class="table-custom-filter-search">
                    <span class="kontent-icon" name="SearchFilter"></span>
                    <input class="search-put" type="text" id="mainSearch">
                    <button type="button" @click="searchTable">Sorgula <span class="kontent-icon" name="Search"></span></button>
                </div>
                <div class="table-custom-filter-button-group">
                    <button class="export-excel" onclick="window.location.href='/export/documents/visit'" id="exportExcel">Excel’e Aktar</button>
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
            <div class="body-1 table-main"  >
                <div id="div_table"></div>
            </div>
        </div>
    </div>
</template>
