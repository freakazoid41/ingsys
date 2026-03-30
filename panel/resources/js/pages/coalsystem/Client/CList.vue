
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
            
            this.navigationStore.setBread([
                {
                    title : this.wTrans('menu.home'),
                    url   : '/coalpanel',
                },
                {
                    title : this.wTrans('menu.requests'),
                    url   : '/coalpanel/request',
                }
            ] ,this.wTrans('form.requests.list'));

            this.navigationStore.setButtons([
              {
                icon : 'ph ph-download',
                onclick   : () => window.open('/export/documents/requests'),
              },{
                icon : 'ph ph-plus-circle',
                onclick   : () => this.$router.push({ name: 'RequestForm' }),
              }
            ]);


            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },  
        data() {
            return {
                plib : new Plib(),
                navigationStore : useNavigationStore(),
                useAuthStore    : useAuthStore(),
            }
        },
        methods: {
            buildTestTable(){
                
                //set headers
                const headers = [
                    {
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
                        title : '',
                        key   : 'id',
                        order : false,
                        //colAlign : 'center',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('d-flex','justify-content-center','align-items-center','flex-row');
                            let btn = document.createElement('button');
                            btn.classList.add('btn','btn-secondary','me-1','d-flex','justify-content-center','align-items-center','flex-row');
                            btn.innerHTML = '<i class="ki-duotone ki-pencil text-gray-900 fs-2 text-body-emphasis" role="img"><span class="path1"></span><span class="path2"></span></i>';
                            btn.onclick   = () => this.$router.push({ name: 'CForm' , params: { id: columnData }});
                            span.appendChild(btn);

                          

                            btn = document.createElement('button');
                            btn.classList.add('btn','btn-secondary','me-1','d-flex','justify-content-center','align-items-center','flex-row');
                            btn.innerHTML = '<i class="ki-duotone ki-cross-square text-gray-900 fs-2 text-body-emphasis" role="img"><span class="path1"></span><span class="path2"></span></i>';
                            btn.onclick   =  async () => {
                                this.navigationStore.toggle(true);
                                const rsp = await this.plib.request({
                                    url      : '/api/v1/document/'+columnData,
                                    method   : 'DELETE',
                                },null);

                                if(rsp.success) {
                                    this.table.deleteRow(columnData);
                                } else {
                                    this.plib.toast(this.Swal,'error',rsp.msg);
                                }
                                setTimeout(() => {
                                    this.navigationStore.toggle(false);
                                }, 300);
                                
                            };
                            span.appendChild(btn);
                            return this.useAuthStore().permissions?.includes('per-06-02') ? span : '';
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
                    nextPageIcon : '<i class="ph ph-arrow-right  text-body-emphasis"></i>',
                    prevPageIcon : '<i class="ph ph-arrow-left text-body-emphasis"></i>',
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
            },
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
                             <input type="text" class="search form-control form-control-solid w-250px ps-12" id="mainSearch" placeholder="Firma Ara">
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
                        <router-link :to="{ name: 'CForm' }" :class="['btn','btn-primary']" >Firma Oluştur</router-link>
                   </div>
              </div>
        <div class="card-body">
            <div id="div_table"></div>
        </div>
    </div>
</template>
