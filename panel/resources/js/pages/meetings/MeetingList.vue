
<script>
    import { useAuthStore } from '@/stores/auth';
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
        components: {
           
        },
        setup() {
            Object.assign(Datepicker.locales, tr);
            // expose to template and other options API hooks
            return {
                useAuthStore,
                useNavigationStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                VMasker,
                Datepicker
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
                    title : this.wTrans('menu.meetings'),
                    url   : '/panel/meetings',
                }
            ] ,this.wTrans('form.meetings.list'));
            this.navigationStore.setButtons([
              {
                icon : 'ph ph-download',
                onclick   : () => window.open('/export/documents/meetings'),
              },
              ...(this.authStore.data.type == 'admin' ? [{
                icon : 'ph ph-plus-circle',
                onclick   : () => this.$router.push({ name: 'MeetingForm' }),
              }] : [{}])
            ]);
            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 500);
        },  
        data() {
            const plib = new Plib();
            return {
                plib : plib,
                navigationStore : useNavigationStore(),
                authStore       : useAuthStore(),
            }
        },
        methods: {
            buildTestTable(){
                
                //set headers
                const headers = [
                    {
                        title : 'Tarih',
                        key   : 'meet_date',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Güncel Yönetici',
                        key   : 'meet_active_supervisor',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Güncel Aidat',
                        key   : 'meet_amount',
                        order : true,
                        colAlign : 'right',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => this.plib.formatMoney(columnData,2,',','.') + ' ' + rowData.cur
                    },{
                        title : '',
                        key   : 'id',
                        order : false,
                        type  : 'string', // if column is string then make type string
                        colAlign : 'center',
                        columnFormatter : (elm,rowData,columnData) => {
                            
                            const span = document.createElement('span');
                            span.classList.add('d-flex','justify-content-center','align-items-center','flex-row');
                            let btn = document.createElement('button');
                            btn.classList.add('btn','btn-secondary','me-1','d-flex','justify-content-center','align-items-center','flex-row');
                            btn.innerHTML = '<i class="fs-4 ph ph-note-pencil text-body-emphasis" role="img"></i>';
                            btn.onclick   = () => this.$router.push({ name: 'MeetingForm' , params: { id: columnData }});
                            span.appendChild(btn);

                          

                            btn = document.createElement('button');
                            btn.classList.add('btn','btn-secondary','me-1','d-flex','justify-content-center','align-items-center','flex-row');
                            btn.innerHTML = '<i class="fc-icon fc-icon- fs-4 ph ph-x-circle"  role="img"></i>';
                            btn.onclick   =  async () => {
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
                            span.appendChild(btn);
                            return span;
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
                            value : 'op-doc-meeting-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-meeting'
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
