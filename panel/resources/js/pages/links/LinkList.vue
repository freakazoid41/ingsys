
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
                    title : 'Anasayfa',
                    url   : '/panel',
                },
                {
                    title : 'Kısa Linkler',
                    url   : '/panel/links',
                }
            ] ,'Kısa Linkler');

            this.navigationStore.setButtons([
              {
                icon : 'ph ph-download',
                onclick   : () => window.open('/export/documents/links'),
              },{
                icon : 'ph ph-plus-circle',
                onclick   : () => {window.location.href = '/panel/links/form'},
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
                        title : 'Başlık',
                        key   : 'title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Kısa Link',
                        key   : 'short_link',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Uzun Link',
                        key   : 'long_link',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Tıklama Sayısı',
                        key   : 'click_count',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Oluşturma Tarihi',
                        key   : 'created_at',
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
                            edit.href        = '/panel/links/form/'+columnData;
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
                    columnSearch : true, // true - false for opening and closig
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
                            value : 'op-doc-link-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-link'
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
                            if(data['per_name'] == undefined) data['per_name'] = []
                            if(element['Key'].includes('per_name')) data['per_name'].push(element['Value']);
                        });
                        data['per_name'] = data['per_name'].join(' , ');
                        //data.status = JSON.parse(data.status).OpTitle;
                        return data;
                    },
                });
            }
        }
    }

</script>
<style>
.card{
    /*background: linear-gradient(45deg,#e45d0b 26%,#ffde59 128%) !important*/
    margin:15px !important;
}
table span{
    color: black !important;  
}
table td{
    color: black !important;  
}
</style>
<template>
    <div class="card">
        <div class="card-body">
            <div id="div_table"></div>
        </div>
    </div>
</template>
