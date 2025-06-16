
<script>
    import { useNavigationStore } from '@/stores/navigation'
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';


    export default {
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                PickleTable,
                Plib
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTestTable();
            
            this.navigationStore.setBread([
                {
                    title : 'Home',
                    url   : '/panel',
                },
                {
                    title : 'Documents',
                    url   : '/panel/documents',
                }
            ] , 'Documents List');
            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 1000);
        },  
        data() {
            const plib = new Plib();
            return {
                plib : plib,
                navigationStore : useNavigationStore(),
            }
        },
        methods: {
            buildTestTable(){
                
                //set headers
                const headers = [
                    {
                        title : 'ID',
                        key   : 'id',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        
                    },{
                        title : 'Title',
                        key   : 'title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Sys Code',
                        key   : 'sys_code',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Current Status',
                        key   : 'status',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Created Date',
                        key   : 'sys_date',
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
                            edit.href        = '/panel/documents/form/'+columnData;
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
                    //columnSearch : true, // true - false for opening and closig
                    paginationType : 'number',// scroll - number (number for default)
                    ajax:{
                        url:'/api/v1/table/documents',
                        data:{
                            //order:{},
                        }
                    },
                    
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

                        data.status = JSON.parse(data.status)?.OpTitle;
                        return data;
                    },
                });
            }
        }
    }

</script>
<template>
    <div class="row justify-content-end">
        <div class="align-items-center d-flex gap-1 ms-3" style="width: auto;"> 
            <a href="/panel/documents/form" class="icon icon-subtle ph ph-plus-circle"></a> 
            <!--<a href="" class="icon icon-subtle ph ph-download"></a> 
            <a href="" class="icon icon-subtle ph ph-gear"></a> -->
        </div>
    </div>
    <br>
    <div id="div_table"></div>
</template>
