
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
        breadcrumbs: {
            list: [  { title: 'Kullanıcılar', path: '/coalpanel/users' } ],
            title: 'Kullanıcılar'
        },
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
            searchTable(){
                this.table.setFilter(
                    [{
                        key   : 'all', // column key
                        type  : '=', // filtering type ('like','<','>')
                        value : document.getElementById('mainSearch').value.trim()//wanted column value
                    }]
                );
            },
            resetSearch(){
                document.getElementById('mainSearch').value = '';
                this.table.setFilter([]);
            },
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
                            if(rowData.needs_refresh == 1){
                                span.innerText += ' (Şifre Yenileme Bekliyor)';
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
                        title : 'Rol',
                        key   : 'role_title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Kullanıcı Adı',
                        key   : 'username',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const isActive = rowData.is_active;
                            const icon = document.createElement('i'); 
                            icon.classList.add('fs-5', 'fa', 'fa-user', 'selectable-icon');
                            icon.style.color = isActive ? '#3CB371' : 'tomato';   
                            
                            const span = document.createElement('span');
                            span.appendChild(icon);
                            span.appendChild(document.createTextNode(' '+columnData));
                            return span;
                        }
                    },{
                        title : '',
                        key   : 'id',
                        order : false,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const div = document.createElement('div');
                            div.classList.add('row','justify-content-center');
                            const reset       = document.createElement('a');
                            reset.onclick   = () => {
                                Swal.fire({
                                    title: 'Kullanıcı Şifresini Sıfırla',
                                    text: "Bu işlem kullanıcının şifresini geçersiz olacak ve kullanıcıya geçici bir şifre maili gönderilecektir. Devam etmek istediğinize emin misiniz?",
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#d33',
                                    confirmButtonText: 'Evet, sıfırla!',
                                    cancelButtonText : 'Hayır, iptal et!'
                                }).then(async (result) => {
                                    if (result.isConfirmed) {
                                        this.navigationStore.toggle(true);
                                        await this.plib.request({
                                            url      : '/api/v1/auth/resetusercradentals/'+columnData,
                                            method   : 'POST',
                                        },null);

                                        Swal.fire(
                                            'Sıfırlandı!',
                                            'Kullanıcının şifresi başarıyla sıfırlandı ve kullanıcıya geçici şifre bilgi maili gönderildi.',
                                            'success'
                                        );

                                        this.table.updateRow(rowData.id,{
                                            needs_refresh:1,
                                            user_status : rowData.user_status
                                        });
                                        setTimeout(() => {
                                            this.navigationStore.toggle(false);
                                        }, 300);
                                    }
                                });
                            };
                            reset.style.width = 'auto';
                            reset.innerHTML   = '<i class="fs-5 fa fa-refresh selectable-icon" style="color:#95818C"  role="img"></i>';
                            div.appendChild(reset);

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
                                this.table.updateRow(columnData,{
                                    status : 0,
                                    user_status : 0
                                });
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
            exportTable(){
                this.plib.openTab('POST', '/api/v1/export/users', this.table.currentFilter,'_blank');
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
    <div class="card rlist-card mt-10">
        <div class="card-header rlist-header">
            <div class="rlist-search-group">
                <div class="rlist-search-wrap">
                    <i class="ki-duotone ki-magnifier fs-4 rlist-search-icon">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text" id="mainSearch" class="rlist-search-input" placeholder="Kullanıcı ara...">
                </div>
                <button type="button" class="rlist-btn rlist-btn-primary" @click="searchTable">
                    <i class="ki-outline ki-magnifier fs-5"></i> Ara
                </button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="resetSearch">Sıfırla</button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="exportTable">
                    <i class="ki-outline ki-exit-down fs-5"></i> Excel
                </button>
            </div>
            <div class="rlist-toolbar">
                <router-link :to="{ name: 'UForm' }" class="rlist-btn rlist-btn-create">
                    <i class="ki-outline ki-plus fs-5"></i> Kullanıcı Oluştur
                </router-link>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="div_table"></div>
        </div>
    </div>
</template>
