
<script>
    import { useNavigationStore } from '@/stores/navigation'
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import VMasker  from 'vanilla-masker';
    import { Datepicker } from 'vanillajs-datepicker';
    import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';


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
                Datepicker
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTestTable();
            
            this.navigationStore.setBread([
                {
                    title : this.wTrans('menu.home'),
                    url   : '/talkpanel',
                },
                {
                    title : this.wTrans('menu.users'),
                    url   : '/talkpanel/users',
                }
            ] ,this.wTrans('form.flats.list'));

            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },  
        data() {
            return {
                plib : new Plib(),
                navigationStore : useNavigationStore(),
            }
        },
        methods: {
            buildTestTable(){
                
                //set headers
                const headers = [
                   /*{
                        title : 'ID',
                        key   : 'id',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        
                    },*/{
                        title : 'İsim',
                        key   : 'name',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Kullanıcı Adı',
                        key   : 'username',
                        order : true,
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
                            edit.href        = '/talkpanel/users/form/'+columnData;
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
                    }
                ];
                
                //initiate table
                this.table = new PickleTable({
                    container : '#div_table', //table target div
                    headers   : headers,
                    pageLimit : 10, // -1 for closing pagination
                    height    : '70vh',
                    type      : 'ajax',
                    //columnSearch : true, // true - false for opening and closig
                    paginationType : 'number',// scroll - number (number for default)
                    ajax:{
                        url:'/api/v1/table/persons',
                        data:{
                            //order:{},
                        }
                    },
                    initialFilter : [
                        
                    ],
                    nextPageIcon : '<i class="ph ph-arrow-right  text-body-emphasis"></i>',
                    prevPageIcon : '<i class="ph ph-arrow-left text-body-emphasis"></i>',
                    rowFormatter:(elm,data)=>{
                        //console.log(elm,data);
                        //modify row element
                        //elm.style.backgroundColor = 'yellow';
                        //modify data
                        /*JSON.parse(data.main_attr).forEach(element => {
                            data[element['Key']] = element['Value'];
                        });*/

                        //data.status = JSON.parse(data.status).OpTitle;
                        return data;
                    },
                });
            },
            
        }
    }

</script>
<template>
    <div class="table-tab">
        <div class="table-tab-head">
            <button href="javascript:;" class="table-toggle active" body="1">{{ $t('menu.users.list') }}</button>
            <router-link :to="{ name: 'UForm' }"><button class="table-toggle" body="2">{{ $t('form.users') }}</button></router-link>
        </div>
        <div class="table-tab-body">
            <div class="table-custom-filter mb-5">
                <div class="table-custom-filter-search">
                    <span class="kontent-icon" name="SearchFilter"></span>
                    <input class="search-put" type="text" id="mainSearch">
                    <button type="button" @click="searchTable">Sorgula <span class="kontent-icon" name="Search"></span></button>
                </div>
                <div class="table-custom-filter-button-group" hidden>
                    <button class="export-excel" onclick="window.location.href='/export/documents/users'" id="exportExcel">Excel’e Aktar</button>
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
