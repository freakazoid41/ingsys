<script>
    import { useNavigationStore } from '@/stores/navigation'
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';

    export default {
        props: {
            id : {
                type: String
            },
        },
        setup() {
            // expose to template and other options API hooks
            return {
                PickleTable,
                Plib,
                wTrans,
                Swal,
                useNavigationStore
            }
        },
        mounted(){
            this.buildTestTable();
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
                        title : this.wTrans('transactions.period').value,
                        key   : 'period',
                        width : '10%',
                        colAlign : 'center',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : this.wTrans('transactions.date').value,
                        key   : 'created_at',
                        width : '10%',
                        colAlign : 'center',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : this.wTrans('transactions.source').value,
                        key   : 'ref_title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : this.wTrans('transactions.target').value,
                        key   : 'main_title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : this.wTrans('transactions.type').value,
                        key   : 'type',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : '',
                        key   : 'sign',
                        width : '5%',
                        colAlign : 'center',
                        order : false,
                        columnSearch : false,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            return columnData == 1 ? '<i class="ph ph-arrow-fat-lines-right fs-1 text-body-emphasis" style="color:green !important"></i>' : '<i class="ph ph-arrow-fat-lines-left fs-1 text-body-emphasis" style="color : tomato !important"></i>';
                        }
                    },{
                        title : this.wTrans('transactions.amount').value,
                        key   : 'amount',
                        colAlign : 'right',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            return this.plib.formatMoney(columnData);
                        }
                    },{
                        title : this.wTrans('transactions.currency').value,
                        key   : 'cur',
                        width : '5%',
                        order : true,
                        type  : 'string', // if column is string then make type string
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
                            edit.href        = 'javascript:;';
                            edit.style.width = 'auto';
                            edit.innerHTML   = '<i class="fc-icon fc-icon- fs-4 ph ph-note-pencil" role="img"></i>';
                            edit.onclick     = () => {
                                Swal.fire({
                                    heightAuto : false,
                                    html : `<style>
                                                .swal2-popup {
                                                    background-color:#000000f7 !important;
                                                }
                                            </style>
                                            <div class="row">
                                                <div class="col-12 text-start mb-5">
                                                    Açıklama : ${rowData.note}
                                                </div>
                                                ${rowData.transFile !== undefined ? ` 
                                                <div class="col-12 text-start">
                                                    Dosya : <a target="_BLANK" href="/order-file/${rowData.transFile}">Tıklayınız..</a>
                                                </div>` : ''}
                                            </div>`,
                                    showConfirmButton : false,
                                })
                            };
                            div.appendChild(edit);

                            const del       = document.createElement('a');
                            del.href        = 'javascript:;';
                            del.style.width = 'auto';
                            del.innerHTML   = '<i class="fc-icon fc-icon- fs-4 ph ph-x-circle"  role="img"></i>';
                            del.onclick     = async () => {

                                Swal.fire({
                                    customClass: {
                                        confirmButton: "btn btn-secondary me-3",
                                        cancelButton: "btn btn-secondary me-3"
                                    },
                                    heightAuto : false,
                                    title: "Eminmisiniz ?",
                                    html : `<style>
                                                .swal2-popup {
                                                    background-color:#000000f7 !important;
                                                }
                                            </style>
                                            Hareket sistemden tamamen silinecektir!`,
                                    icon: "warning",
                                    showCancelButton: true,
                                    confirmButtonColor: "#3085d6",
                                    cancelButtonColor: "#d33",
                                    confirmButtonText: "Evet , Sil!",
                                    cancelButtonText : 'İptal',
                                }).then(async (result) => {
                                    if (result.isConfirmed) {
                                        this.navigationStore.toggle(true);
                                        await this.plib.request({
                                            url      : '/api/v1/transaction/'+columnData,
                                            method   : 'DELETE',
                                        },null);

                                        this.table.deleteRow(columnData);
                                        setTimeout(() => {
                                            this.navigationStore.toggle(false);
                                            this.plib.toast(this.Swal,'success','Hareket Silindi...',() => {window.location.reload()});
                                        }, 200);
                                    }
                                });
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
                    columnSearch : true,
                    //columnSearch : true, // true - false for opening and closig
                    paginationType : 'number',// scroll - number (number for default)
                    ajax:{
                        url:'/api/v1/table/transactions',
                        data:{
                            //order:{},
                        }
                    },
                    ...(this.id !== undefined ? {initialFilter : [ {
                        key   : 'target_id',
                        type  : '=',
                        value : this.id
                    }]} : {}),
                    nextPageIcon : '<i class="ph ph-arrow-right  text-body-emphasis"></i>',
                    prevPageIcon : '<i class="ph ph-arrow-left text-body-emphasis"></i>',
                    rowFormatter:(elm,data)=>{
                        //modify data
                        JSON.parse(data.conn_info).forEach(element => {
                            data[element['Key']] = element['Value'];
                        });

                        if(data.ref_title === undefined) data.ref_title = data.title ?? '-';

                        JSON.parse(data.main_info).forEach(element => {
                            data[element['Key']] = element['Value'];
                        });

                        if(data.main_title === undefined) data.main_title = data.title ?? '-';

                        JSON.parse(data.trans_files).forEach(element => {
                            console.log(element)
                            data.transFile = element['file'];
                        });
                        return data;
                    },
                    rowClick : (elm,data) => { 
                        /*Swal.fire({
                            html : `<style>
                                        .swal2-popup {
                                            background-color:#000000f7 !important;
                                        }
                                    </style>
                                    <div class="row">
                                        <div class="col-12 text-start mb-5">
                                            Açıklama : ${data.note}
                                        </div>
                                        ${data.transFile !== undefined ? ` 
                                        <div class="col-12 text-start">
                                            Dosya : <a target="_BLANK" href="/order-file/${data.transFile}">Tıklayınız..</a>
                                        </div>` : ''}
                                    </div>`,
                            showConfirmButton : false,
                        })*/
                    },
                    afterRender:(currentData,currentPage)=>{
                        let jusrRemove = document.querySelector('input[name="sign"]');
                        if(jusrRemove != null ) jusrRemove.remove();

                        jusrRemove = document.querySelector('input[name="id"]');
                        if(jusrRemove != null ) jusrRemove.remove();
                    },
                });
            }
        }
    }

</script>

<template>
    <div class="card">
        <div class="card-header">
            {{ $t('transactions.list') }}
        </div>
        <div class="card-body">
            <div id="div_table"></div>
        </div>
    </div>
</template>