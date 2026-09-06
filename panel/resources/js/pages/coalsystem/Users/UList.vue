
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
                systemFilter    : '',
            }
        },
        methods: {
            buildFilters(){
                const filters = [];
                const searchVal = (document.getElementById('mainSearch')?.value || '').trim();
                if(searchVal) filters.push({ key:'all', type:'=', value: searchVal });
                if(this.systemFilter) filters.push({ key:'grp_code', type:'=', value: this.systemFilter });
                return filters;
            },
            onSystemFilterChange(){
                this.table.setFilter(this.buildFilters());
            },
            searchTable(){
                this.table.setFilter(this.buildFilters());
            },
            resetSearch(){
                document.getElementById('mainSearch').value = '';
                this.systemFilter = '';
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
                        title : 'Sistem',
                        key   : 'grp_code',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.style.padding = '4px 10px';
                            span.style.borderRadius = '999px';
                            span.style.fontWeight = '700';
                            span.style.fontSize = '11px';
                            span.style.border = '1px solid';
                            span.style.display = 'inline-block';
                            const v = (columnData || '').toString().toUpperCase();
                            if(v === 'GDZ'){
                                span.style.background = '#eff6ff';
                                span.style.color = '#1e40af';
                                span.style.borderColor = '#bfdbfe';
                                span.innerText = 'GDZ';
                            } else if(v === 'ADM'){
                                span.style.background = '#fff7ed';
                                span.style.color = '#9a3412';
                                span.style.borderColor = '#fed7aa';
                                span.innerText = 'ADM';
                            } else if(v === 'BOTH'){
                                span.style.background = 'linear-gradient(135deg,#eff6ff 0%,#fff7ed 100%)';
                                span.style.color = '#0f172a';
                                span.style.borderColor = '#cbd5e1';
                                span.innerText = 'İki Sistemde Mevcut';
                            } else {
                                span.style.background = '#f1f5f9';
                                span.style.color = '#475569';
                                span.style.borderColor = '#e2e8f0';
                                span.innerText = columnData || '-';
                            }
                            return span;
                        }
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
                <select v-model="systemFilter" @change="onSystemFilterChange" class="form-select rlist-system-select" style="min-width:190px; max-width:210px; border-radius:10px; border:1.5px solid #e2e8f0; font-weight:600; font-size:13px;">
                    <option value="">Tüm Sistemler</option>
                    <option value="GDZ">GDZ</option>
                    <option value="ADM">ADM</option>
                    <option value="BOTH">İki Sistemde Mevcut</option>
                </select>
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
