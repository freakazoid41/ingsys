
<script>
    import { useNavigationStore } from '@/stores/navigation'
    import { useEventDataStore } from '@/stores/events'

    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import VMasker  from 'vanilla-masker';
    import { Datepicker } from 'vanillajs-datepicker';
    import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';



    export default {
        components: {
           
        },
        setup() {
            Object.assign(Datepicker.locales, tr);
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                VMasker,
                Datepicker,
                useEventDataStore
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
                    title : this.wTrans('menu.projects'),
                    url   : '/panel/projects',
                }
            ] ,this.wTrans('form.projects.list'));
            this.navigationStore.setButtons([
              {
                icon : 'ph ph-download',
                onclick   : () => window.open('/export/documents/projects'),
              },{
                icon : 'ph ph-plus-circle',
                onclick   : () => {window.location.href = '/panel/projects/form'},
              }
            ]);
            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 500);
        },  
        data() {
            const plib = new Plib();
            return {
                plib : plib,
                taskDataStore   : useEventDataStore(),
                navigationStore : useNavigationStore(),
            }
        },
        methods: {
            buildTestTable(){
                
                //set headers
                const headers = [
                    {
                        title : 'Başlangıç Tarih',
                        key   : 'start_date',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'T. Bitiş Tarih',
                        key   : 'end_date',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Başlık',
                        key   : 'title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Güncel Durum',
                        key   : 'status',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const  btn    = document.createElement('button');
                            btn.classList.add('btn','d-flex','align-items-center');

                            const key = columnData?.split('**');
                            let icon  = '<i class="ph ph-timer fs-2 me-3 text-body-emphasis"></i>';
                            switch(key?.[0]){
                                case 'doc_trans_created':
                                default:
                                    if(key?.[1]) key[1] = 'Bekleniyor..';
                                    btn.classList.add('btn-info');
                                    break;
                                case 'doc_trans_project_end':
                                    icon  = '<i class="ph ph-calendar-check fs-2 me-3 text-body-emphasis"></i>';
                                    btn.classList.add('btn-success');
                                    break;
                                case 'doc_trans_project_start':
                                    icon  = '<i class="ph ph-clock-countdown fs-2 me-3 text-body-emphasis"></i>';
                                    btn.classList.add('btn-warning');
                                    break;
                                case 'doc_trans_project_sikinti':
                                    icon  = '<i class="ph ph-seal-warning fs-2 me-3 text-body-emphasis"></i>';
                                    btn.classList.add('btn-danger');
                                    break;
                                case 'doc_trans_project_payment':
                                    icon  = '<i class="ph ph-money fs-2 me-3 text-body-emphasis"></i>';
                                    btn.classList.add('btn-primary');
                                    break;
                            }
                            btn.innerHTML = icon+' '+(key?.[1] ?? 'Bekleniyor..') ;
                            btn.type      = 'button';
                            btn.onclick   = (e) => {
                                Swal.fire({
                                    showConfirmButton : false,
                                    showCloseButton : true,
                                    html : `<small class="mb-5">Listeden İstediğiniz Durumu Seçip Güncelleyebilirsiniz</small>
                                            <div class="row mt-5 justify-content-center">
                                                <button class="btn btn-warning mb-5 doc-status" data-key="doc_trans_project_start"    type="button">Süreç Başladı</button>
                                                <button class="btn btn-success mb-5 doc-status" data-key="doc_trans_project_end"      type="button">Süreç Tamamlandı</button>
                                                <button class="btn btn-danger mb-5 doc-status"  data-key="doc_trans_project_sikinti"  type="button">Sıkıntı Oluştu</button>
                                                <button class="btn btn-primary mb-5 doc-status" data-key="doc_trans_project_payment"  type="button">Ödeme Bekliyor</button>
                                            </div>`,
                                    willOpen : async () => {
                                        Swal.showValidationMessage(key?.[2]);
                                        document.querySelectorAll('.doc-status').forEach(btn => {
                                            btn.addEventListener('click', e => {
                                                Swal.fire({
                                                    confirmButtonText : 'Kaydet..',
                                                    showCloseButton : true,
                                                    html : `<small class="mb-5">Durum Notu Giriniz (Boş Olabilir)</small>
                                                            <div class="row mt-5 justify-content-center">
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
                                                                url      : '/api/v1/trans/set-status',
                                                                method   : 'POST',
                                                            },null,envelope);
                                                            if(rsp.success){
                                                                this.table.updateRow(rowData.id,{status : e.target.dataset.key+'**'+rsp.data+'**'+note});
                                                                this.plib.toast(this.Swal,'success','İşlem Tamamlandı');
                                                                this.taskDataStore.setTaskData();
                                                            }else{
                                                                Swal.showValidationMessage(rsp.msg);
                                                            }
                                                            return rsp.success;
                                                        } catch (error) {
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
                            };
                            return btn;
                        }
                    },{
                        title : 'Belirlenen Ödeme',
                        key   : 'planed_amount',
                        order : true,
                        colAlign : 'right',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => this.plib.formatMoney(columnData,2,',','.') + ' ' + rowData.cur
                    },{
                        title : '',
                        key   : 'id',
                        order : false,
                        width : '5%',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const div = document.createElement('div');
                            div.classList.add('row','justify-content-center');

                            const edit       = document.createElement('a');
                            edit.href        = '/panel/projects/form/'+columnData;
                            edit.style.width = 'auto';
                            edit.innerHTML   = '<i class="fc-icon fc-icon- fs-4 ph ph-note-pencil" role="img"></i>';
                            div.appendChild(edit);

                            const del       = document.createElement('a');
                            del.href        = 'javascript:;';
                            del.style.width = 'auto';
                            del.innerHTML   = '<i class="fc-icon fc-icon- fs-4 ph ph-x-circle"  role="img"></i>';
                            del.onclick     = async () => {
                                this.navigationStore.toggle(true);
                                await this.plib.request({
                                    url      : '/api/v1/document/'+columnData,
                                    method   : 'DELETE',
                                },null);

                                this.table.deleteRow(columnData);
                                setTimeout(() => {
                                    this.navigationStore.toggle(false);
                                }, 200);
                                
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
                        url:'/api/v1/table/documents',
                        data:{
                            //order:{},
                        }
                    },
                    initialFilter : [
                        {
                            key   : 'form-type',
                            type  : '=',
                            value : 'op-doc-project-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-project'
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
                        });
                        return data;
                    },
                });
            },
        }
    }

</script>
<template>
    <div class="card">
        <div class="card-body">
            <div id="div_table"></div>
        </div>
    </div>
</template>
