
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
            list: [ { title: 'İhale Yönetimi', path: '/coalpanel/ihaleler' } ],
            title: 'İhale Düzenle / Oluştur'
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
            // delegated: readonly product field opens the picker even if the
            // Form engine (re)builds its inputs asynchronously (PForm pattern)
            this._prodClickHandler = (e) => {
                const t = e.target && e.target.closest ? e.target.closest('.area-target[data-tag="op-doc-ihale-form"] input[name^="product_title"]') : null;
                if (t) {
                    this._prodTarget = t.closest('.multiple-item-row') || t;
                    this.openProdModal();
                }
            };
            document.addEventListener('click', this._prodClickHandler);
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
                this.loadForm = true;
                setTimeout(() => {
                    this.navigationStore.toggle(false);
                }, 500);
            });
        },
        beforeUnmount(){
            if (this._prodClickHandler) {
                document.removeEventListener('click', this._prodClickHandler);
                this._prodClickHandler = null;
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
                showProdModal   : false,
                prodTable       : null,
                prodSearch      : '',
                _prodTarget     : null,
            };
        },
        methods: {
            // --- product picker (server-side paginated, safe for big data) ---
            openProdModal(){
                this.showProdModal = true;
                this.$nextTick(() => this.buildProdTable());
            },
            closeProdModal(){
                this.showProdModal = false;
                this._prodTarget = null;
                if (this.prodTable && this.prodTable.destroy) {
                    try { this.prodTable.destroy(); } catch(e) {}
                }
                this.prodTable = null;
                const el = document.getElementById('ih_prod_table');
                if (el) el.innerHTML = '';
            },
            searchProdTable(){
                if (!this.prodTable) return;
                const v = (this.prodSearch || '').trim();
                this.prodTable.setFilter(v ? [{ key : 'all', type : '=', value : v }] : []);
            },
            resetProdTable(){
                this.prodSearch = '';
                if (this.prodTable) this.prodTable.setFilter([]);
            },
            buildProdTable(){
                const headers = [
                    { title : 'Ürün Kodu', key : 'prod_code', order : true, type : 'string' },
                    { title : 'Kısa Ad', key : 'short_name', order : true, type : 'string' },
                    { title : 'Birim', key : 'unit', order : false, type : 'string' },
                    { title : 'Kategori', key : 'category_title', order : false, type : 'string' },
                    {
                        title : '', key : 'id', order : false, colAlign : 'center', type : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const btn = document.createElement('button');
                            btn.classList.add('btn','btn-sm','btn-primary');
                            btn.textContent = 'Seç';
                            btn.onclick = () => this.pickProduct(columnData, rowData);
                            return btn;
                        }
                    }
                ];

                this.prodTable = new PickleTable({
                    container : '#ih_prod_table',
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
                        { key : 'form-type', type : '=', value : 'op-doc-product-form' },
                        { key : 'type', type : '=', value : 'op-doc-product' }
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
            pickProduct(qnid, rowData){
                const row = this._prodTarget && document.contains(this._prodTarget) ? this._prodTarget : null;
                if (!row) {
                    this.closeProdModal();
                    return;
                }
                // block duplicates across rows
                try {
                    const dup = Array.from(document.querySelectorAll('.area-target[data-tag="op-doc-ihale-form"] input[name^="product_qnid"]'))
                        .some(inp => inp.value === qnid && !row.contains(inp));
                    if (dup) {
                        this.plib.toast(this.Swal, 'info', 'Bu ürün zaten ekli.', () => {});
                        return;
                    }
                } catch(e) {}
                const title = (rowData && ((rowData.prod_code ? rowData.prod_code + ' — ' : '') + (rowData.short_name || ''))) || qnid;
                const unit = (rowData && rowData.unit) || '';
                try {
                    const q = row.querySelector ? row.querySelector('input[name^="product_qnid"]') : null;
                    const t = row.querySelector ? row.querySelector('input[name^="product_title"]') : (row.matches && row.matches('input[name^="product_title"]') ? row : null);
                    const u = row.querySelector ? row.querySelector('input[name^="unit"]') : null;
                    if (q) {
                        q.value = qnid || '';
                        q.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (t) {
                        t.value = title || '';
                        t.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (u) {
                        u.value = unit || '';
                        u.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                } catch(e) {}
                this.closeProdModal();
            },
            syncProductsToForm(){
                // engine may not have seen programmatic writes on brand-new docs — inject defensively (PForm pattern)
                try {
                    const rows = document.querySelectorAll('.area-target[data-tag="op-doc-ihale-form"] .multiple-item-row');
                    const df = (this.formData && this.formData.dynamicF) || {};
                    rows.forEach((row) => {
                        const inputs = row.querySelectorAll('input[name*="**ihale_product_rows**"]');
                        inputs.forEach((inp) => {
                            if (!inp.name) return;
                            for (const key in df) {
                                const entry = df[key];
                                if (entry && entry.entities && Object.prototype.hasOwnProperty.call(entry.entities, inp.name)) {
                                    entry.entities[inp.name] = inp.value || '';
                                }
                            }
                        });
                    });
                } catch(e) {}
            },
            selectedProductCount(){
                try {
                    return Array.from(document.querySelectorAll('.area-target[data-tag="op-doc-ihale-form"] input[name^="product_qnid"]'))
                        .filter(inp => (inp.value || '').trim() !== '').length;
                } catch(e) { return 0; }
            },
            hasSpecFile(){
                // 1) already saved on the tender (edit mode)
                try {
                    const ff = this.formDataStore.rawData?.formFormat?.['op-doc-ihale-form'] || {};
                    for (const row of Object.values(ff)) {
                        const ents = row?.entities || {};
                        for (const k in ents) {
                            if (k.startsWith('ihale_spec_file')) return true;
                        }
                    }
                } catch(e) {}
                // 2) picked but not saved yet (temp reference / pending upload)
                for (const k in (this.formData?.files || {})) {
                    if (k.includes('ihale_spec_file')) return true;
                }
                return false;
            },
            async submitForm(formData){
                this.formData = formData;
                this.syncProductsToForm();
                if (this.selectedProductCount() === 0) {
                    this.plib.toast(this.Swal, 'info', 'Lütfen en az bir ürün seçin.', () => {});
                    return;
                }
                if (!this.hasSpecFile()) {
                    this.plib.toast(this.Swal, 'info', 'İlgili Teknik Şartname dosyası zorunludur.', () => {});
                    return;
                }
                this.navigationStore.toggle(true);
                this.formData.typeKey = 'op-doc-ihale';
                const check = this.plib.checkForm('.form-item');
                if(check.valid){
                    const envelope = new FormData();
                    envelope.append('data', JSON.stringify(this.formData));
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

                    const savedQnid = rsp?.data?.qnid;
                    setTimeout(() => {
                        this.navigationStore.toggle(false);

                        this.plib.toast(this.Swal,'success','İşlem Tamamlandı',() => {
                            if(this.id === undefined && savedQnid){
                                this.$router.push({ name : 'IhList' });
                            }
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
        <Form formtypes="op-doc-ihale-form" fabtype="saveBtn" v-if="loadForm" :savecallback="submitForm" />

        <div v-if="showProdModal" style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.45);padding:16px;" @click.self="closeProdModal">
            <div style="background:#fff;border-radius:16px;max-width:860px;width:100%;max-height:88vh;overflow:auto;padding:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <div style="font-weight:700;font-size:1rem;color:#0f172a;">Ürün Seç</div>
                    <button type="button" @click="closeProdModal" class="rlist-btn rlist-btn-ghost">Kapat</button>
                </div>
                <div style="display:flex;gap:8px;margin-bottom:12px;">
                    <input v-model="prodSearch" @keyup.enter="searchProdTable" type="text" placeholder="Kod veya ad ara..." style="flex:1;border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:.9rem;outline:none;" />
                    <button type="button" @click="searchProdTable" class="rlist-btn rlist-btn-primary">Ara</button>
                    <button type="button" @click="resetProdTable" class="rlist-btn rlist-btn-ghost">Sıfırla</button>
                </div>
                <div id="ih_prod_table"></div>
            </div>
        </div>
    </div>

</template>

<style>
/* readonly product display doubles as picker opener */
.area-target[data-tag="op-doc-ihale-form"] input[name^="product_title"] {
    cursor: pointer;
}
/* ---- product picker modal: card rows like the front panels ---- */
#ih_prod_table {
    height: auto !important;
    border: none !important;
    background: #eef2f7 !important;
    border-radius: 14px !important;
    padding: 6px 10px 12px !important;
}
#ih_prod_table .divTable {
    height: auto !important;
    max-height: 38vh !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    border-bottom: none !important;
    border-radius: 10px !important;
}
#ih_prod_table table {
    border-collapse: separate !important;
    border-spacing: 0 7px !important;
    table-layout: fixed !important;
    border: none !important;
    width: 100% !important;
}
#ih_prod_table thead th {
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
#ih_prod_table thead th:nth-child(3),
#ih_prod_table tbody td:nth-child(3),
#ih_prod_table thead th:nth-child(4),
#ih_prod_table tbody td:nth-child(4) {
    width: 90px !important;
}
#ih_prod_table thead th:nth-child(5),
#ih_prod_table tbody td:nth-child(5) {
    width: 110px !important;
    text-align: right !important;
}
#ih_prod_table tbody tr {
    background: #fff !important;
    border-radius: 12px !important;
    box-shadow: 0 2px 8px rgba(15,23,42,.07) !important;
    border: 1px solid #eef2f7 !important;
}
#ih_prod_table tbody tr:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 6px 18px rgba(15,23,42,.12) !important;
}
#ih_prod_table tbody td {
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
#ih_prod_table tbody td:first-child {
    font-weight: 600 !important;
    color: #0f172a !important;
}
#ih_prod_table tbody td .btn {
    border-radius: 999px !important;
    padding: 7px 18px !important;
    font-weight: 600 !important;
}
#ih_prod_table .divPagination {
    border-top: none !important;
    justify-content: center !important;
    padding-top: 8px !important;
}
/* ---- product picker modal: card rows like the front panels ---- */
#ih_prod_table {
    height: auto !important;
    border: none !important;
    background: #eef2f7 !important;
    border-radius: 14px !important;
    padding: 6px 10px 12px !important;
}
#ih_prod_table .divTable {
    height: auto !important;
    max-height: 38vh !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    border-bottom: none !important;
    border-radius: 10px !important;
}
#ih_prod_table table {
    border-collapse: separate !important;
    border-spacing: 0 7px !important;
    table-layout: fixed !important;
    border: none !important;
    width: 100% !important;
}
#ih_prod_table thead th {
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
#ih_prod_table tbody tr {
    background: #fff !important;
    border-radius: 12px !important;
    box-shadow: 0 2px 8px rgba(15,23,42,.07) !important;
    border: 1px solid #eef2f7 !important;
}
#ih_prod_table tbody tr:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 6px 18px rgba(15,23,42,.12) !important;
}
#ih_prod_table tbody td {
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
#ih_prod_table tbody td:first-child {
    font-weight: 600 !important;
    color: #0f172a !important;
}
#ih_prod_table tbody td .btn {
    border-radius: 999px !important;
    padding: 7px 18px !important;
    font-weight: 600 !important;
}
#ih_prod_table .divPagination {
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
