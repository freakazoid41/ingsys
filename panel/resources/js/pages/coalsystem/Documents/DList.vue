
<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import dayjs from 'dayjs';

    export default {
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                useAuthStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTestTable();
            
            /*this.navigationStore.setBread([
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
            ]);*/


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
                        title : 'Belge Başlık',
                        key   : 'entity_tag',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                           return this.navigationStore.fileEntities[rowData.entity_tag.split('**')[1]] ?? 'Diğer';
                        }
                    },{
                        title : 'İlişki',
                        key   : 'title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        
                    },{
                        title : 'Eklenme Tarihi',
                        key   : 'created_at',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                           return dayjs(columnData).format('DD/MM/YYYY HH:mm');
                        }
                    },{
                        title : 'Güncel Durum',
                        key   : 'last_status',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const  btn    = document.createElement('button');
                            btn.classList.add('btn','d-flex','align-items-center');
                            console.log(columnData);
                            const key = columnData?.split('**');
                            let icon  = '<i class="ph ph-timer fs-2 me-3 text-body-emphasis"></i>';
                            switch(key?.[0]){
                                case 'doc_file_waiting':
                                default:
                                    if(key?.[1]) key[1] = 'Kontrol Bekleniyor';
                                    icon  = '<i class="ki-outline ki-directbox-default fs-2 me-3 text-body-emphasis"></i>';
                                    btn.classList.add('btn-default');
                                    break;
                                case 'doc_file_accepted':
                                    icon  = '<i class="ki-outline ki-check fs-2 me-3 text-body-emphasis"></i>';
                                    btn.classList.add('btn-success');
                                    break;
                                case 'doc_file_refreshed':
                                    icon  = '<i class="ki-outline ki-timer fs-2 me-3 text-body-emphasis"></i>';
                                    btn.classList.add('btn-warning');
                                    break;
                                case 'doc_file_rejected':
                                    icon  = '<i class="ki-outline ki-cross-circle fs-2 me-3 text-body-emphasis"></i>';
                                    btn.classList.add('btn-danger');
                                    break;
                            }
                            btn.innerHTML = icon+' '+(key?.[1] ?? 'Bekleniyor..') ;
                            btn.type      = 'button';
                            btn.onclick   = this.useAuthStore().permissions?.includes('per-07-02') ? (e) => {
                                Swal.fire({
                                    showConfirmButton : false,
                                    showCloseButton : true,
                                    html : `<small class="mb-5 mt-5">Listeden İstediğiniz Durumu Seçip Güncelleyebilirsiniz</small>
                                            <div class="row m-5 justify-content-center">
                                                <button class="btn btn-warning mb-5 doc-status" data-key="doc_file_refreshed"    type="button">Yeniden Talep Et</button>
                                                <button class="btn btn-success mb-5 doc-status" data-key="doc_file_accepted"      type="button">Kabul Edildi</button>
                                                <button class="btn btn-danger mb-5 doc-status"  data-key="doc_file_rejected"  type="button">Reddet</button>
                                            </div>`,
                                    willOpen : async () => {
                                        Swal.showValidationMessage(key?.[2]);
                                        document.querySelectorAll('.doc-status').forEach(btn => {
                                            btn.addEventListener('click', e => {
                                                Swal.fire({
                                                    confirmButtonText : 'Kaydet..',
                                                    showCloseButton : true,
                                                    html : `<small class="mb-5">Durum Notu Giriniz (Boş Olabilir)</small>
                                                            <div class="row m-5 justify-content-center">
                                                                <div class="col-12">
                                                                    <textarea class="form-control" id="exampleFormControlTextarea1" rows="3" placeholder="..."></textarea>
                                                                </div>
                                                            </div>`,
                                                    allowOutsideClick: () => !Swal.isLoading(),
                                                    preConfirm : async () => {
                                                        try {
                                                            const note     = document.getElementById('exampleFormControlTextarea1').value.trim();
                                                            const envelope = new FormData();
                                                            envelope.append('id',rowData.id);
                                                            envelope.append('op_key',e.target.dataset.key);
                                                            envelope.append('note',note);
                                                            const rsp = await this.plib.request({
                                                                url      : '/api/v1/trans/set-file-status',
                                                                method   : 'POST',
                                                            },null,envelope);
                                                            if(rsp.success){
                                                                this.table.updateRow(rowData.id,{last_status : e.target.dataset.key+'**'+rsp.data+'**'+note});
                                                                this.plib.toast(this.Swal,'success','İşlem Tamamlandı');
                                                                this.taskDataStore.setTaskData();
                                                            }else{
                                                                Swal.showValidationMessage(rsp.msg);
                                                            }
                                                            return rsp.success;
                                                        } catch (error) {
                                                            console.log(error)
                                                            Swal.showValidationMessage(`
                                                                Request failed: ${error}
                                                            `);
                                                        }
                                                    }
                                                });
                                            });
                                        });
                                    }
                                });
                            } : () => {};
                            return btn;
                        }
                    },{
                        title : 'Detaylar',
                        key   : 'id',
                        order : false,
                        colAlign : 'center',
                        headAlign : 'center',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('d-flex','justify-content-center','align-items-center','flex-row');

                            let btn = document.createElement('button');
                            btn.classList.add('btn','btn-secondary','me-1','d-flex','justify-content-center','align-items-center','flex-row');
                            btn.innerHTML = '<i class="ki-duotone ki-pencil text-gray-900 fs-2 text-body-emphasis" role="img"><span class="path1"></span><span class="path2"></span></i>';
                            btn.onclick   = () => {
                                //here open detail modal
                            }
                            span.appendChild(btn);

                            btn = document.createElement('button');
                            btn.classList.add('btn','btn-secondary','me-1','d-flex','justify-content-center','align-items-center','flex-row');
                            btn.innerHTML = '<i class="ki-outline ki-eye text-gray-900 fs-2 text-body-emphasis" role="img"></i>';
                            btn.onclick   = () => {
                                window.open('/order-file/'+rowData?.file);
                            }
                            span.appendChild(btn);

                          

                           
                            return this.useAuthStore().permissions?.includes('per-07') ? span : '';
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
                        url:'/api/v1/table/document_files',
                        data:{
                            //order:{},
                        }
                    },
                    initialFilter : [],
                    nextPageIcon : '<i class="ph ph-arrow-right  text-body-emphasis"></i>',
                    prevPageIcon : '<i class="ph ph-arrow-left text-body-emphasis"></i>',
                    rowFormatter:(elm,data)=>{
                        //console.log(elm,data);
                        //modify row element
                        //elm.style.backgroundColor = 'yellow';
                        //modify data
                        JSON.parse(data.relation_detail).forEach(element => {
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
                             <input type="text" class="search form-control form-control-solid w-250px ps-12" id="mainSearch" placeholder="Dosya Ara">
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
                   </div>
              </div>
        <div class="card-body">
            <div id="div_table"></div>
        </div>
    </div>
</template>
