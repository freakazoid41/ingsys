
<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import { useRouter } from 'vue-router';
    import { Datepicker } from 'vanillajs-datepicker';
    import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';


    export default {
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
        async mounted(){
            this.navigationStore.toggle(true);
            this.buildTestTable();
            await useAuthStore().getPermissions();
            this.navigationStore.setBread([
                {
                    title : this.wTrans('menu.home'),
                    url   : '/secpanel',
                },
                {
                    title : this.wTrans('menu.users'),
                    url   : '/secpanel/users',
                }
            ] ,this.wTrans('menu.users.list'));

            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },  
        data() {
            return {
                plib : new Plib(),
                useAuthStore    : useAuthStore(),
                navigationStore : useNavigationStore(),
            }
        },
        methods: {
            async buildTestTable(){
                await useAuthStore().getPermissions();
                //set headers
                const headers = [
                    {
                        title : 'Durum',
                        key   : 'user_status',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            switch (String(columnData)) {
                                case '1':
                                    span.style.color = 'green';
                                    span.innerText   = 'Aktif';
                                    break;
                                case '0':
                                    span.style.color = 'red';
                                    span.innerText   ='Pasif';
                                    break;
                                 case '-1':
                                    span.style.color = 'orange';
                                    span.innerText   = 'Onay Bekliyor';
                                    break;
                                default:
                                    span.innerText = 'Bilinmiyor';
                                    break;
                            }
                            return span;
                        }
                    },{
                        title : 'İsim',
                        key   : 'name',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Tip',
                        key   : 'type_title',
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
                            edit.onclick   = () => this.$router.push({ name: 'UForm' , params: { id: columnData }});
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
                                    url      : '/api/v1/persons/'+columnData,
                                    method   : 'DELETE',
                                },null);

                                this.table.deleteRow(columnData);
                                setTimeout(() => {
                                    this.navigationStore.toggle(false);
                                }, 300);
                                
                            };
                            div.appendChild(del);

                            return this.useAuthStore().permissions?.includes('per-04-02') ? div : '';
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
                        url:'/api/v1/table/user',
                        data:{
                            //order:{},
                        }
                    },
                    initialFilter : [
                        
                    ],
                    nextPageIcon : '<i class="fa fa-solid fa-chevron-right"></i>',
                    prevPageIcon : '<i class="fa fa-solid fa-chevron-left"></i>',
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
    <div class="card">
        <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                   <div class="card-title">
                        <div class="d-flex align-items-center position-relative my-1">
                             <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-4">
                                  <span class="path1"></span>
                                  <span class="path2"></span>
                             </i>
                             <input type="text" class="search form-control form-control-solid w-250px ps-12" id="mainSearch" placeholder="Kullanıcı Ara">
                        </div>
                   </div>
                   <div class="card-toolbar flex-row-fluid justify-content-end gap-5">
                        <!--<div class="w-100  mw-200px d-flex align-items-center">
                             <label class="mx-2" for="">Durum: </label>
                             <select class="mw-150px form-select form-select-solid" data-control="select2" data-hide-search="true" data-placeholder="Durum" data-kt-ecommerce-product-filter="durum">
                                  <option></option>
                                  <option value="all">Tümü</option>
                                  <option value="Aktif">Aktif</option>
                                  <option value="Pasif">Pasif</option>
                             </select>
                        </div>
                        <div class="w-100 mw-200px d-flex align-items-center">
                             <label class="mx-2">Alt Yüklenici: </label>
                             <select class="mw-150px form-select form-select-solid" data-control="select2" data-hide-search="true" data-placeholder="Alt Yüklenici" data-kt-ecommerce-product-filter="alt_yuklenici">
                                  <option></option>
                                  <option value="all">Tümü</option>
                                  <option value="Evet">Evet</option>
                                  <option value="Hayır">Hayır</option>
                             </select>
                        </div>-->
                        <router-link :to="{ name: 'UForm' }" :class="['btn','btn-primary']">
                            Kullanıcı Oluştur
                        </router-link>
                   </div>
              </div>
        <div class="card-body">
            <div id="div_table"></div>
        </div>
    </div>
</template>
