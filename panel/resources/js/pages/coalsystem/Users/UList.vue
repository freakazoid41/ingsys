
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
                            const wrap = document.createElement('span');
                            wrap.classList.add('d-inline-flex','align-items-center','gap-1','flex-wrap');

                            const chip = document.createElement('span');
                            chip.classList.add('kv-chip');
                            const v = String(columnData);
                            if (v === '1') {
                                chip.classList.add('kv-chip--active');
                                chip.textContent = 'Aktif';
                            } else if (v === '0') {
                                chip.classList.add('kv-chip--passive');
                                chip.textContent = 'Pasif';
                            } else if (v === '-1') {
                                chip.classList.add('kv-chip--pending');
                                chip.textContent = 'Onay Bekliyor';
                            } else {
                                chip.classList.add('kv-chip--neutral');
                                chip.textContent = 'Bilinmiyor';
                            }
                            wrap.appendChild(chip);

                            if(rowData.needs_refresh == 1){
                                const note = document.createElement('span');
                                note.classList.add('kv-chip','kv-chip--warn');
                                note.textContent = 'Şifre Yenileme';
                                wrap.appendChild(note);
                            }
                            return wrap;
                        }
                    },{
                        title : 'İsim',
                        key   : 'name',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('kv-cell-title');
                            span.textContent = columnData ?? '';
                            return span;
                        }
                    },{
                        title : 'Tip',
                        key   : 'type_title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const chip = document.createElement('span');
                            chip.classList.add('kv-chip','kv-chip--type');
                            chip.textContent = columnData ?? '';
                            return chip;
                        }
                    },{
                        title : 'Rol',
                        key   : 'role_title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const chip = document.createElement('span');
                            chip.classList.add('kv-chip','kv-chip--role');
                            chip.textContent = columnData ?? '';
                            return chip;
                        }
                    },{
                        title : 'Sistem',
                        key   : 'grp_code',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const chip = document.createElement('span');
                            chip.classList.add('kv-chip');
                            const v = (columnData || '').toString().toUpperCase();
                            if(v === 'GDZ'){
                                chip.classList.add('kv-chip--gdz');
                                chip.textContent = 'GDZ';
                            } else if(v === 'ADM'){
                                chip.classList.add('kv-chip--adm');
                                chip.textContent = 'ADM';
                            } else if(v === 'BOTH'){
                                chip.classList.add('kv-chip--both');
                                chip.textContent = 'İki Sistemde Mevcut';
                            } else {
                                chip.classList.add('kv-chip--neutral');
                                chip.textContent = columnData || '-';
                            }
                            return chip;
                        }
                    },{
                        title : 'Kullanıcı Adı',
                        key   : 'username',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const isActive = rowData.is_active;
                            const icon = document.createElement('i');
                            icon.classList.add('fs-6', 'fa', 'fa-user', 'selectable-icon');
                            icon.style.color = isActive ? '#3CB371' : 'tomato';

                            const span = document.createElement('span');
                            span.classList.add('kv-username');
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
                            div.classList.add('d-flex','justify-content-end','gap-1');

                            const actBtn = (cls, title, icon) => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.classList.add('kv-act', cls);
                                btn.title = title;
                                btn.innerHTML = '<i class="fa '+icon+'"></i>';
                                return btn;
                            };

                            const reset = actBtn('kv-act--reset', 'Şifre Sıfırla', 'fa-refresh');
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
                            div.appendChild(reset);

                            const edit = actBtn('kv-act--edit', 'Düzenle', 'fa-pen');
                            edit.onclick = () => this.$router.push({ name: 'UForm' , params: { id: columnData }});
                            div.appendChild(edit);

                            const del = actBtn('kv-act--del', 'Sil', 'fa-trash');
                            del.onclick = async () => {
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

<style scoped>
.rlist-card :deep(.kv-cell-title){
    font-weight: 700;
    color: #0f172a;
}
.rlist-card :deep(.kv-chip){
    display: inline-flex;
    align-items: center;
    padding: 3px 11px;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: .02em;
    white-space: nowrap;
    background: #f1f5f9;
    color: #475569;
}
.rlist-card :deep(.kv-chip--active){ background: #ecfdf5; color: #047857; }
.rlist-card :deep(.kv-chip--passive){ background: #fef2f2; color: #dc2626; }
.rlist-card :deep(.kv-chip--pending){ background: #fffbeb; color: #b45309; }
.rlist-card :deep(.kv-chip--warn){ background: #fff7ed; color: #c2410c; }
.rlist-card :deep(.kv-chip--neutral){ background: #f1f5f9; color: #475569; }
.rlist-card :deep(.kv-chip--type){ background: #e6f7fa; color: #0e8ea4; }
.rlist-card :deep(.kv-chip--role){ background: #eef2ff; color: #4338ca; }
.rlist-card :deep(.kv-chip--gdz){ background: #eff6ff; color: #1e40af; }
.rlist-card :deep(.kv-chip--adm){ background: #fff7ed; color: #9a3412; }
.rlist-card :deep(.kv-chip--both){ background: linear-gradient(135deg,#eff6ff,#fff7ed); color: #0f172a; }
.rlist-card :deep(.kv-username){
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12.5px;
    color: #475569;
    white-space: nowrap;
}
.rlist-card :deep(.kv-act){
    width: 34px;
    height: 34px;
    border-radius: 10px;
    border: 1px solid transparent;
    background: #f1f5f9;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .15s ease;
    font-size: 14px;
}
.rlist-card :deep(.kv-act:hover){ transform: translateY(-1px); }
.rlist-card :deep(.kv-act--reset){ background: #fffbeb; color: #b45309; }
.rlist-card :deep(.kv-act--reset:hover){ background: #fef3c7; color: #92400e; }
.rlist-card :deep(.kv-act--edit){ background: #e6f7fa; color: #0e8ea4; }
.rlist-card :deep(.kv-act--edit:hover){ background: #cff0f5; color: #0b7285; }
.rlist-card :deep(.kv-act--del){ background: #fef2f2; color: #dc2626; }
.rlist-card :deep(.kv-act--del:hover){ background: #fee2e2; color: #b91c1c; }
</style>
