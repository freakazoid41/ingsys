
<script>
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router';
    import { useAuthStore } from '@/stores/auth';

    import { useNavigationStore } from '@/stores/navigation';
    import { useFormDataStore } from '@/stores/formdata';
    import Swal from 'sweetalert2';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';

    import Form from '@/components/coalparts/Form.vue';


    export default {
        breadcrumbs: {
            list: [ { title: 'Ürünler', path: '/coalpanel/products' } ,{ title: 'Ürün Ekle / Düzenle', path: '#' }],
            title: 'Ürün Düzenle / Oluştur'
        },
        components: {
            Form
        },
        setup() {
            return {
                useNavigationStore,
                useFormDataStore,useAuthStore,
                Plib,
                Swal,
                useRoute
            }
        },
        mounted(){
            // delegated: readonly category field opens the picker even if the
            // Form engine (re)builds its inputs asynchronously
            this._catClickHandler = (e) => {
                const t = e.target && e.target.closest ? e.target.closest('.area-target input[name^="category_title"]') : null;
                if (t) {
                    t.style.cursor = 'pointer';
                    this.openCatModal();
                }
            };
            document.addEventListener('click', this._catClickHandler);
            this.navigationStore.toggle(true);
            const checkData = async () => {
                if(this.id !== undefined && this.id !== ''){
                    const rsp = await this.plib.request({
                        url      : '/api/v1/document/'+this.id,
                        method   : 'GET',
                    },null);

                    return rsp;
                }else{
                    return {
                        success : false
                    }
                }
            }

            checkData().then(response => {
                this.navigationStore.toggle(true);
                this.formDataStore.setData(response?.data?.formFormat);
                this.formDataStore.rawData = response?.data || null;
                try {
                    const ff = response?.data?.formFormat?.['op-doc-product-form'] || {};
                    const firstRow = Object.values(ff)[0];
                    const ents = (firstRow && firstRow.entities) || {};
                    if (ents.category_qnid) {
                        this.selectedCategoryQnid = ents.category_qnid;
                        this.selectedCategoryTitle = ents.category_title || ents.category_qnid;
                    }
                } catch(e) {}
                this.loadForm = true;
                setTimeout(() => {
                    this.navigationStore.toggle(false);
                }, 500);
            });
        },
        beforeUnmount(){
            if (this._catClickHandler) {
                document.removeEventListener('click', this._catClickHandler);
                this._catClickHandler = null;
            }
        },
        data() {
            const route = useRoute();

            return {
                loadForm        : false,
                plib            : new Plib(),
                authStore       : useAuthStore(),
                navigationStore : useNavigationStore(),
                formDataStore   : useFormDataStore(),
                id              : route?.params?.id !== '' ? route?.params?.id : undefined,
                selectedCategoryQnid  : '',
                selectedCategoryTitle : '',
                showCatModal    : false,
                catTable        : null,
                catSearch       : '',
            };
        },
        methods: {
            // --- category picker (server-side paginated, safe for big data) ---
            openCatModal(){
                this.showCatModal = true;
                this.$nextTick(() => this.buildCatTable());
            },
            closeCatModal(){
                this.showCatModal = false;
                if (this.catTable && this.catTable.destroy) {
                    try { this.catTable.destroy(); } catch(e) {}
                }
                this.catTable = null;
                const el = document.getElementById('pc_cat_table');
                if (el) el.innerHTML = '';
            },
            searchCatTable(){
                if (!this.catTable) return;
                const v = (this.catSearch || '').trim();
                this.catTable.setFilter(v ? [{ key : 'all', type : '=', value : v }] : []);
            },
            resetCatTable(){
                this.catSearch = '';
                if (this.catTable) this.catTable.setFilter([]);
            },
            buildCatTable(){
                const headers = [
                    { title : 'Başlık', key : 'title', order : true, type : 'string' },
                    { title : 'Kod', key : 'code', order : true, type  : 'string' },
                    {
                        title : '', key : 'id', order : false, colAlign : 'center', type : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const btn = document.createElement('button');
                            btn.classList.add('btn','btn-sm','btn-primary');
                            btn.textContent = 'Seç';
                            btn.onclick = () => this.pickCategory(columnData, rowData);
                            return btn;
                        }
                    }
                ];

                this.catTable = new PickleTable({
                    container : '#pc_cat_table',
                    headers   : headers,
                    pageLimit : 10,
                    height    : '46vh',
                    type      : 'ajax',
                    columnSearch : false,
                    paginationType : 'number',
                    ajax:{
                        url:'/api/v1/table/documents',
                        data:{}
                    },
                    initialFilter : [
                        { key : 'form-type', type : '=', value : 'op-doc-product-category-form' },
                        { key : 'type', type : '=', value : 'op-doc-product-category' }
                    ],
                    rowFormatter:(elm,data)=>{
                        try {
                            JSON.parse(data.main_attr).forEach(element => {
                                data[element['Key']] = element['Value'];
                            });
                        } catch(e) {}
                        return data;
                    },
                });
            },
            pickCategory(qnid, rowData){
                this.selectedCategoryQnid = qnid;
                this.selectedCategoryTitle = (rowData && (rowData.title || rowData.code)) || qnid;
                this.pushCategoryToForm();
                this.closeCatModal();
            },
            clearCategory(){
                this.selectedCategoryQnid = '';
                this.selectedCategoryTitle = '';
                this.pushCategoryToForm();
            },
            pushCategoryToForm(){
                // write back into the hidden Form engine inputs so the standard
                // submitDynamicChanges pipeline picks them up (never bypass it)
                try {
                    const q = document.querySelector('.area-target[data-tag="op-doc-product-form"] input[name^="category_qnid"]');
                    if (q) {
                        q.value = this.selectedCategoryQnid || '';
                        q.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    const t = document.querySelector('.area-target[data-tag="op-doc-product-form"] input[name^="category_title"]');
                    if (t) {
                        t.value = this.selectedCategoryTitle || '';
                        t.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                } catch(e) {}
            },
            async submitForm(formData){
                this.formData = formData;
                this.pushCategoryToForm();
                if (!this.selectedCategoryQnid) {
                    this.plib.toast(this.Swal, 'info', 'Lütfen bir kategori seçin.', () => {});
                    return;
                }
                this.navigationStore.toggle(true);
                this.formData.typeKey = 'op-doc-product';
                // engine may not have seen programmatic writes on brand-new docs — inject defensively
                try {
                    for (const key in (this.formData.dynamicF || {})) {
                        const entry = this.formData.dynamicF[key];
                        if (entry && entry.entities) {
                            entry.entities['category_qnid'] = this.selectedCategoryQnid;
                            entry.entities['category_title'] = this.selectedCategoryTitle;
                        }
                    }
                } catch(e) {}
                const rsp = this.plib.checkForm('.form-item');
                if(rsp.valid){
                    const   envelope  = new FormData();
                        envelope.append('data',JSON.stringify(this.formData));
                    for(let key in this.formData.files){
                        const fileItem = this.formData.files[key];
                        if(fileItem && !fileItem.uploading && fileItem.reference){
                            envelope.append(key, JSON.stringify(fileItem.reference));
                        } else if(fileItem && fileItem.file) {
                            envelope.append(key, fileItem.file);
                        }
                    }
                    const rsp = await this.plib.request({
                        url      : '/api/v1/document'+(this.id !== undefined ? '/'+this.id : ''),
                        method   : this.id !== undefined ? 'PUT' : 'POST',
                    },null,envelope);

                    if(!rsp?.success){
                        this.navigationStore.toggle(false);
                        this.plib.toast(
                            this.Swal,
                            'error',
                            rsp?.msg ?? rsp?.data?.message ?? 'Kayıt oluşturulamadı. Lütfen bilgileri kontrol edip tekrar deneyin.'
                        );
                        return;
                    }

                    setTimeout(() => {
                        this.navigationStore.toggle(false);

                        this.plib.toast(this.Swal,'success','İşlem Tamamlandı',() => {
                            this.$router.push({ name: 'PList' });
                        });
                    }, 300);

                }else{
                    this.navigationStore.toggle(false);
                    this.plib.toast(this.Swal,'info','Eksik Alanları Doldurmalısınız..',() => {});
                }
            },

        }
    }

</script>

<template>
    <div>
        <div class="card mb-4" v-if="loadForm" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;background:#fff;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;flex-wrap:wrap;">
                <div style="min-width:0;">
                    <div style="font-weight:700;font-size:.95rem;color:#0f172a;">Ürün Kategorisi</div>
                    <div v-if="selectedCategoryQnid" style="font-size:.85rem;color:#334155;margin-top:2px;">{{ selectedCategoryTitle }}</div>
                    <div v-else style="font-size:.85rem;color:#94a3b8;margin-top:2px;">Henüz kategori seçilmedi *</div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button v-if="selectedCategoryQnid" type="button" @click="clearCategory" class="rlist-btn rlist-btn-ghost">Temizle</button>
                    <button type="button" @click="openCatModal" class="rlist-btn rlist-btn-primary">
                        <i class="ki-outline ki-magnifier fs-5"></i> Kategori Seç
                    </button>
                </div>
            </div>
        </div>

        <Form formtypes="op-doc-product-form" fabtype="saveBtn" v-if="loadForm" :savecallback="submitForm" />

        <div v-if="showCatModal" style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.45);padding:16px;" @click.self="closeCatModal">
            <div style="background:#fff;border-radius:16px;max-width:860px;width:100%;max-height:88vh;overflow:auto;padding:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <div style="font-weight:700;font-size:1rem;color:#0f172a;">Kategori Seç</div>
                    <button type="button" @click="closeCatModal" class="rlist-btn rlist-btn-ghost">Kapat</button>
                </div>
                <div style="display:flex;gap:8px;margin-bottom:12px;">
                    <input v-model="catSearch" @keyup.enter="searchCatTable" type="text" placeholder="Başlık veya kod ara..." style="flex:1;border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:.9rem;outline:none;" />
                    <button type="button" @click="searchCatTable" class="rlist-btn rlist-btn-primary">Ara</button>
                    <button type="button" @click="resetCatTable" class="rlist-btn rlist-btn-ghost">Sıfırla</button>
                </div>
                <div id="pc_cat_table"></div>
            </div>
        </div>
    </div>

</template>

<style>
/* readonly category display doubles as picker opener */
.area-target input[name^="category_title"] {
    cursor: pointer;
}
/* ---- category picker modal: card rows like the front panels ---- */
#pc_cat_table {
    height: auto !important;
    border: none !important;
    background: #eef2f7 !important;
    border-radius: 14px !important;
    padding: 6px 10px 12px !important;
}
#pc_cat_table .divTable {
    height: auto !important;
    max-height: 38vh !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    border-bottom: none !important;
    border-radius: 10px !important;
}
#pc_cat_table table {
    border-collapse: separate !important;
    border-spacing: 0 7px !important;
    table-layout: fixed !important;
    border: none !important;
    width: 100% !important;
}
#pc_cat_table thead th {
    background: transparent !important;
    color: #94a3b8 !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    border: none !important;
    padding: 6px 14px !important;
    text-align: left !important;
    text-transform: uppercase !important;
    letter-spacing: .04em !important;
}
#pc_cat_table thead th:nth-child(2),
#pc_cat_table tbody td:nth-child(2) {
    width: 110px !important;
}
#pc_cat_table thead th:nth-child(3),
#pc_cat_table tbody td:nth-child(3) {
    width: 110px !important;
    text-align: right !important;
}
#pc_cat_table tbody tr {
    background: #fff !important;
    border-radius: 12px !important;
    box-shadow: 0 2px 8px rgba(15,23,42,.07) !important;
    border: 1px solid #eef2f7 !important;
}
#pc_cat_table tbody tr:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 6px 18px rgba(15,23,42,.12) !important;
}
#pc_cat_table tbody td {
    background: transparent !important;
    padding: 12px 14px !important;
    font-size: 13.5px !important;
    color: #334155 !important;
    border: none !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
#pc_cat_table tbody td:first-child {
    font-weight: 600 !important;
    color: #0f172a !important;
}
#pc_cat_table tbody td .btn {
    border-radius: 999px !important;
    padding: 7px 18px !important;
    font-weight: 600 !important;
}
#pc_cat_table .divPagination {
    border-top: none !important;
    justify-content: center !important;
    padding-top: 8px !important;
}
</style>

<style scoped>
.rlist-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 10px;
    padding: 9px 14px;
    font-size: .85rem;
    font-weight: 600;
    border: 1px solid transparent;
    cursor: pointer;
}
.rlist-btn-primary {
    background: #FF5A1F;
    color: #fff;
}
.rlist-btn-ghost {
    background: #f8fafc;
    color: #334155;
    border-color: #e2e8f0;
}
</style>
