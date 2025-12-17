
<script>
    import { useNavigationStore } from '@/stores/navigation'
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import flatpickr from "flatpickr";
    import { Turkish } from "flatpickr/dist/l10n/tr.js"
    import 'flatpickr/dist/flatpickr.css';

    export default {
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                flatpickr,
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTestTable();
            
            this.navigationStore.setBread([
                {
                    title : this.wTrans('menu.home'),
                    url   : '/secpanel',
                },
                {
                    title : this.wTrans('menu.visit'),
                    url   : '/secpanel/visit',
                }
            ] ,this.wTrans('form.visit.list'));

            document.querySelectorAll('.date-select').forEach(el => {
                flatpickr(el, {
                    "locale": Turkish,
                    dateFormat: 'd/m/Y'
                });
            });
            

            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },  
        data() {
            return {
                pageStatus       : 'list',
                plib             : new Plib(),
                navigationStore  : useNavigationStore(),
                currentFacillity : null,
                currentFilter    : null,
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
                            if(rowData['inventory-taken']){
                                columnData = columnData.split(' ');
                                return columnData[0].split('-').reverse().join('/')+' '+columnData[1];
                            }else{
                                return 'Bekleniyor..';
                            }
                            
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
                                return 'Bekleniyor';
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
                                return 'Bekleniyor';
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
                        title : '',
                        key   : 'id',
                        order : false,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const div = document.createElement('div');
                            div.classList.add('row','justify-content-center');
                            
                            if(!rowData.exited_at){
                                const exit       = document.createElement('a');
                                exit.href        = 'javascript:;';
                                exit.style.width = 'auto';
                                exit.innerHTML   = '<i class="fs-6 far fa-share-square selectable-icon" style="color:#95818C"  role="img"></i>';
                                exit.onclick     = () => {
                                    //pop remaining inventories
                                    this.popRemain(rowData);
                                };
                                div.appendChild(exit);
                            }


                            const edit       = document.createElement('a');
                            edit.href        = '/secpanel/visit/form/'+columnData;
                            edit.style.width = 'auto';
                            edit.innerHTML   = '<i class="fs-6 fa fa-pen selectable-icon" style="color:#95818C"  role="img"></i>';
                            div.appendChild(edit);

                            const del       = document.createElement('a');
                            del.href        = 'javascript:;';
                            del.style.width = 'auto';
                            del.innerHTML   = '<i class="fs-6 fa fa-trash selectable-icon" style="color:#95818C"  role="img"></i>';
                            del.onclick     = async () => {
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
                            div.appendChild(del);

                            return div;
                        }
                    }
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
                        if(data.main_attr.includes('"{')){
                            //mssql variation 
                            data.main_attr = data.main_attr.replace('"{','{').replace('}"','}');
                        }


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
                this.getFilters();
                this.table.setFilter(this.currentFilter);
            },
            resetFilter(){
                document.getElementById('mainSearch').value = '';
                document.querySelectorAll('.date-select').forEach(el => el.value = '');
                this.currentFacillity = null;
                this.currentFilter    = null;
                this.table.setFilter([]);
                
            },
            getFilters(){
                const filter = [];
                const text = document.getElementById('mainSearch').value;
                if(text != ''){
                    filter.push({
                        key   : 'all', // column key
                        type  : '=', // filtering type ('like','<','>')
                        value : text.trim()//wanted column value
                    }); 
                }    

                const startDate = document.querySelector('.date-select[name="start_at"]')
                const endDate   = document.querySelector('.date-select[name="end_at"]')
                if(startDate.value != '' && endDate.value != ''){
                    filter.push({
                        key   : 'date-range', // column key
                        type  : '=', // filtering type ('like','<','>')
                        value : (startDate.value ?? '-')+'**'+(endDate.value ?? '-')//wanted column value
                    }); 
                }

                this.currentFilter = filter;
                return filter;

            },
            filterDate(e){
                this.getFilters();
                const startDate = document.querySelector('.date-select[name="start_at"]')
                const endDate   = document.querySelector('.date-select[name="end_at"]')
                if(startDate.value != '' && endDate.value != ''){
                    this.table.setFilter(this.currentFilter);
                }
                
            },
            async popRemain(data){
                const makeExit = async () => {
                    this.navigationStore.toggle(true);
                    const envelope  = new FormData();
                    envelope.append('data',JSON.stringify({
                        "dynamicF" : {
                            ['op-doc-visit-form**'+data.entity_conn_id] : {
                                "entities" : {
                                    "exited_at" : this.plib.getConvertedDate(new Date())
                                },
                                "tag"      : "op-doc-visit-form"
                            }
                        }
                    }));

                    const response = await this.plib.request({
                        url      : '/api/v1/yeniziyaret/'+data.id,
                        method   : 'PUT',
                    },null,envelope);

                    this.table.setFilter([]);

                    this.navigationStore.toggle(false);
                }


                const takenInv = [];
                const recvInv  = [];
                const attr = JSON.parse(data.main_attr);
                attr.forEach(el => {
                    if(el['Key'].includes('inventory**givengroup'))    takenInv.push(el['Value']);
                    if(el['Key'].includes('inventory**revievedgroup')) recvInv.push(el['Value']);
                });
                if(recvInv.length != takenInv.length){
                    Swal.fire({
                        html: ` <style>.swal2-modal{width:500px !important;}</style>
                                <div class="row w-100">
                                    <div class="col-12">
                                        <span>Kişi aşağıdaki ekipmanları henüz teslim etmemiştir.</span>
                                        <br>
                                        <ul class="list-group list-group-flush mt-3">
                                            ${takenInv.map(inv => {
                                                return !recvInv.includes(inv) ? '<li class="list-group-item text-start">- '+inv+'</li>' : '';
                                            }).join('')}
                                        </ul>
                                    </div>
                                </div>`,
                        showConfirmButton : true,
                        showCancelButton  : true,
                        showCloseButton   : true,
                        confirmButtonText : 'Yine de Çıkış Yap !',
                        cancelButtonText  : 'İptal',
                        preConfirm: async () => {
                            await makeExit();
                        }
                    });
                }else{
                    await makeExit();
                }
                
            },
            facilityFilter(){
                Swal.fire({
                    title: 'Tesis Seç',
                    
                    html:`<div id="table-facility"></div>`,
                    showCloselButton: true,
                    willOpen:() => {
                        //set headers
                        const headers = [
                            {
                                title : 'Tesis İsmi',
                                key   : 'title',
                                order : true,
                                type  : 'string', // if column is string then make type string
                            },{
                                title : 'Adres',
                                key   : 'address',
                                order : true,
                                colAlign : 'right',
                                type  : 'string', // if column is string then make type string
                            },{
                                title : '',
                                key   : 'id',
                                order : false,
                                type  : 'string', // if column is string then make type string
                                columnFormatter : (elm,rowData,columnData) => {
                                    const div = document.createElement('div');
                                    div.classList.add('row','justify-content-center');

                                    const edit       = document.createElement('a');
                                    edit.style.width = 'auto';
                                    edit.innerHTML   = '<i class="fs-5 fa fa-arrow-right selectable-icon" style="color:#95818C"  role="img"></i>';
                                    edit.onclick     = () => {
                                        this.table.setFilter(
                                            [{
                                                key   : 'facility_id', // column key
                                                type  : 'like', // filtering type ('like','<','>')
                                                value : columnData//wanted column value
                                            }]
                                        );
                                            console.log(rowData);
                                        this.currentFacillity = rowData.title
                                        Swal.close();
                                    };
                                    div.appendChild(edit);

                                    return div;
                                }
                            }
                        ];
                        
                        //initiate table
                        this.facilityTable = new PickleTable({
                            container : '#table-facility', //table target div
                            headers   : headers,
                            pageLimit : 10, // -1 for closing pagination
                            columnSearch : true,
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
                                    value : 'op-doc-facility-form'
                                },{
                                    key   : 'type',
                                    type  : '=',
                                    value : 'op-doc-facility'
                                }
                            ],
                            nextPageIcon : '<i class="fa fa-solid fa-chevron-right"></i>',
                            prevPageIcon : '<i class="fa fa-solid fa-chevron-left"></i>',
                            rowFormatter:(elm,data)=>{
                                //console.log(elm,data);
                                //modify row element
                                //elm.style.backgroundColor = 'yellow';
                                //modify data
                                if(data.main_attr.includes('"{')){
                                    //mssql variation 
                                    data.main_attr = data.main_attr.replace('"{','{').replace('}"','}');
                                }
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
                    }
                });
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
                    <button class="reset-filters" @click="resetFilter">Filtre Sıfırla</button>
                    <button class="reset-filters" @click="facilityFilter">Tesis Seç</button>
                    <button class="export-excel"  @click="plib.openTab('GET','/export/documents/visit',currentFilter, '_blank')" id="exportExcel">Excel’e Aktar</button>
                </div>
                <div class="table-custom-filter-date">
                    <p>Ziyaret Tarih Aralığı</p>
                    <div class="date-form">
                        <label for="startDate">Başlangıç Tarihi:</label>
                        <input type="text" name="start_at" @input="filterDate($event)" id="startDate" placeholder="--/--/---" class="hasDatepicker date-select">
                        <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg></span>
                    </div>
                    <div class="date-form">
                        <label for="endDate">Bitiş Tarihi:</label>
                        <input type="text" name="end_at" @input="filterDate($event)" id="endDate" placeholder="--/--/----" class="hasDatepicker date-select">
                        <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg></span>
                    </div>
                </div>
            </div>
            <div class="body-1 table-main">
                <div v-if="currentFacillity" class="alert alert-secondary" role="alert">
                    {{currentFacillity ?? ''  }}
                </div>
                <div id="div_table"></div>
            </div>
        </div>
    </div>
</template>
