
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
            document.body.classList.add('ihf-form-open');
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
            document.body.classList.remove('ihf-form-open');
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
        computed: {
            listRouteName(){ return this.$route.path.startsWith('/ihalepanel') ? 'IhalePanelIhList' : 'IhList'; },
            isEdit(){ return this.id !== undefined && this.id !== ''; },
        },
        methods: {
            goList(){
                this.$router.push({ name : this.listRouteName });
            },
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
                            btn.classList.add('ihf-pick-btn');
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
                                this.$router.push({ name : this.listRouteName });
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
    <div class="ihf-page">
        <section class="ihf-head">
            <div>
                <div class="ihf-eyebrow"><i class="ki-outline ki-plus-square"></i> İHALE YÖNETİMİ</div>
                <h1>{{ isEdit ? 'İhale Düzenle' : 'Yeni İhale' }}</h1>
                <p>Ürün, şartname ve son teklif tarihini belirleyin. Kaydetmeden önce tüm alanları doldurun.</p>
            </div>
            <div class="ihf-head__actions">
                <span class="ihf-chip" :class="isEdit ? 'ihf-chip--edit' : 'ihf-chip--new'">
                    <i class="ki-outline" :class="isEdit ? 'ki-pencil' : 'ki-plus'"></i>
                    {{ isEdit ? 'Düzenleme' : 'Yeni Kayıt' }}
                </span>
                <button class="ihf-btn" @click="goList">
                    <i class="ki-outline ki-arrow-left fs-5"></i> İhaleler
                </button>
            </div>
        </section>

        <section class="ihf-card">
            <Form formtypes="op-doc-ihale-form" fabtype="saveBtn" v-if="loadForm" :savecallback="submitForm" />
        </section>

        <div class="ihf-note">
            <i class="ki-outline ki-information-5"></i>
            <span>Ürünü seçmek için ürün alanına tıklayın. Teknik şartname dosyası <b>zorunludur</b> (PDF/XLS/JPG, en fazla 40 MB).</span>
        </div>

        <div v-if="showProdModal" class="ihf-modal-overlay" @click.self="closeProdModal">
            <div class="ihf-modal-card">
                <div class="ihf-modal-head">
                    <div class="ihf-modal-title"><i class="ki-outline ki-parcel"></i> Ürün Seç</div>
                    <button type="button" @click="closeProdModal" class="ihf-btn ihf-btn--ghost ihf-btn--sm">Kapat</button>
                </div>
                <div class="ihf-modal-search">
                    <div class="ihf-searchbox">
                        <i class="ki-outline ki-magnifier ihf-searchbox__icon"></i>
                        <input v-model="prodSearch" @keyup.enter="searchProdTable" type="text" placeholder="Kod veya ad ara..." class="ihf-searchbox__input" />
                    </div>
                    <button type="button" @click="searchProdTable" class="ihf-btn ihf-btn--primary ihf-btn--sm">Ara</button>
                    <button type="button" @click="resetProdTable" class="ihf-btn ihf-btn--sm">Sıfırla</button>
                </div>
                <div id="ih_prod_table"></div>
            </div>
        </div>
    </div>

</template>

<style scoped>
.ihf-page {
    padding: 1.5rem 1.25rem 7rem;
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
.ihf-page * { box-sizing: border-box; }
.ihf-head {
    background: linear-gradient(135deg, #ffffff 0%, #f0fdf7 100%);
    border: 1px solid #d1fae5;
    border-radius: 20px;
    padding: 1.4rem 1.6rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.25rem;
    box-shadow: 0 8px 24px rgba(5, 150, 105, .06);
}
.ihf-eyebrow {
    display: flex; align-items: center; gap: .45rem;
    font-size: .68rem; font-weight: 800; letter-spacing: .08em; color: #047857;
}
.ihf-head h1 { margin: .3rem 0 .2rem; font-size: 1.5rem; font-weight: 800; color: #0f172a; }
.ihf-head p { margin: 0; font-size: .86rem; color: #64748b; }
.ihf-head__actions { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.ihf-chip {
    display: inline-flex; align-items: center; gap: .35rem;
    font-size: .72rem; font-weight: 800; border-radius: 999px; padding: .32rem .7rem;
    border: 1px solid transparent; white-space: nowrap;
}
.ihf-chip i { font-size: 13px; }
.ihf-chip--new { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.ihf-chip--edit { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.ihf-btn {
    display: inline-flex; align-items: center; gap: .45rem;
    border: 1px solid #e2e8f0; background: #fff; color: #334155;
    border-radius: 11px; padding: .65rem 1rem; font-size: .82rem; font-weight: 700; cursor: pointer;
    transition: all .18s ease; white-space: nowrap;
}
.ihf-btn:hover { border-color: #a7f3d0; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(15,23,42,.06); }
.ihf-btn--primary { background: #059669; color: #fff; border-color: #059669; }
.ihf-btn--primary:hover { background: #047857; border-color: #047857; box-shadow: 0 8px 20px rgba(5,150,105,.28); }
.ihf-btn--ghost { background: #f8fafc; }
.ihf-btn--sm { padding: .5rem .85rem; font-size: .78rem; border-radius: 10px; }
.ihf-card {
    background: #fff; border: 1px solid #eef2f7; border-radius: 18px;
    padding: 1.25rem 1.35rem; box-shadow: 0 2px 8px rgba(15,23,42,.04);
}
.ihf-note {
    display: flex; align-items: flex-start; gap: 6px;
    font-size: 11.5px; color: #8a8a8e; line-height: 1.6; padding: 0 4px;
}
.ihf-note i { color: #059669; font-size: 13px; margin-top: 2px; flex-shrink: 0; }
.ihf-note b { color: #0f172a; }

/* ---- Form engine: ihale green theme ---- */
.ihf-card :deep(.form-section-heading) {
    background: linear-gradient(135deg, #ffffff 0%, #f0fdf7 100%) !important;
    border: 1px solid #d1fae5 !important;
    border-top: 3px solid #059669 !important;
    color: #047857 !important;
    border-radius: 12px !important;
    padding: 10px 14px !important;
    font-size: 12.5px !important;
    letter-spacing: .06em !important;
}
.ihf-card :deep(.input-label) {
    font-size: 12px !important;
    font-weight: 800 !important;
    color: #334155 !important;
}
.ihf-card :deep(.form-control) {
    min-height: 44px;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    font-size: 13.5px;
    background: #fff;
}
.ihf-card :deep(.form-control:focus) {
    border-color: #059669;
    box-shadow: 0 0 0 4px rgba(5,150,105,.12);
    outline: none;
}
.ihf-card :deep(.form-control)::placeholder { color: #94a3b8; }
.ihf-card :deep(.multiple-item-card) {
    background: #fff !important;
    border: 1px solid #d1fae5 !important;
    border-top: 3px solid #059669 !important;
    border-radius: 16px !important;
    box-shadow: 0 6px 18px rgba(5,150,105,.08) !important;
    padding: 14px 16px 6px !important;
}
.ihf-card :deep(.multiple-item-row .input-label) {
    color: #047857 !important;
}
.ihf-card :deep(.input-group-text) {
    background: #ecfdf5 !important;
    border-color: #e2e8f0 !important;
    color: #047857 !important;
    border-radius: 0 12px 12px 0 !important;
}
.ihf-card :deep(.input-group .form-control) {
    border-radius: 12px 0 0 12px !important;
}
.ihf-card :deep(.input-group .form-control:only-child) {
    border-radius: 12px !important;
}
.ihf-card :deep(i[id*="subadd"]) {
    background: #059669 !important;
    color: #fff !important;
    border-radius: 999px !important;
    width: 30px; height: 30px;
    display: inline-flex !important; align-items: center !important; justify-content: center !important;
    font-size: 16px !important; cursor: pointer;
    box-shadow: 0 4px 12px rgba(5,150,105,.25);
    margin-left: 8px;
}
.ihf-card :deep(i[id*="subadd"]:hover) { background: #047857 !important; }
.ihf-card :deep(.rmv-btn-form) {
    background: #fef2f2 !important;
    color: #dc2626 !important;
    border: 1px solid #fecaca !important;
}
.ihf-card :deep(.rmv-btn-form:hover) { background: #fee2e2 !important; }

/* readonly product display doubles as picker opener */
.ihf-card :deep(.area-target[data-tag="op-doc-ihale-form"] input[name^="product_title"]) {
    cursor: pointer;
}

/* ---- product picker modal ---- */
.ihf-modal-overlay {
    position: fixed; inset: 0; z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    background: rgba(15,23,42,.45); padding: 16px;
}
.ihf-modal-card {
    background: #fff; border-radius: 18px;
    max-width: 860px; width: 100%; max-height: 88vh; overflow: auto; padding: 20px;
    border: 1px solid #eef2f7; box-shadow: 0 24px 64px rgba(15,23,42,.22);
}
.ihf-modal-head {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;
}
.ihf-modal-title {
    display: flex; align-items: center; gap: .5rem;
    font-weight: 800; font-size: 1rem; color: #0f172a;
}
.ihf-modal-title i { color: #059669; font-size: 20px; }
.ihf-modal-search { display: flex; gap: 8px; margin-bottom: 12px; }
.ihf-searchbox {
    position: relative; display: flex; align-items: center; flex: 1; min-width: 0;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0 12px; height: 42px;
}
.ihf-searchbox:focus-within { background: #fff; border-color: #059669; box-shadow: 0 0 0 4px rgba(5,150,105,.12); }
.ihf-searchbox__icon { position: absolute; left: 12px; font-size: 18px; color: #94a3b8; }
.ihf-searchbox__input {
    border: none; outline: none; background: transparent; height: 100%; width: 100%;
    padding-left: 30px; font-size: 13.5px; color: #0f172a;
}
.ihf-searchbox__input::placeholder { color: #94a3b8; }

/* product picker table: card rows */
#ih_prod_table {
    height: auto !important;
    border: none !important;
    background: #f6f8fb !important;
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
    box-shadow: 0 6px 18px rgba(5,150,105,.14) !important;
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
#ih_prod_table .divPagination {
    border-top: none !important;
    justify-content: center !important;
    padding-top: 8px !important;
}
#ih_prod_table .divPagination button,
#ih_prod_table .divPagination .page-link {
    min-width: 30px; height: 30px;
    border-radius: 8px !important;
    border: 1px solid #e5e7eb !important;
    background: #fff !important; color: #64748b !important;
    font-size: 12px !important; font-weight: 600 !important;
}
#ih_prod_table .divPagination .active button,
#ih_prod_table .divPagination button.active,
#ih_prod_table .divPagination button.current {
    background: #059669 !important; border-color: #059669 !important; color: #fff !important;
}
.ihf-pick-btn {
    border: none; border-radius: 999px; padding: 7px 20px;
    font-weight: 700; font-size: 12.5px; cursor: pointer;
    background: #059669; color: #fff;
    box-shadow: 0 4px 12px rgba(5,150,105,.25);
    transition: background .15s ease, transform .15s ease;
    white-space: nowrap;
}
.ihf-pick-btn:hover { background: #047857; transform: translateY(-1px); }

@media (max-width: 768px) {
    .ihf-page { padding: 1rem 1rem 7rem; }
    .ihf-head { flex-direction: column; align-items: flex-start; }
    .ihf-card { padding: 1rem; }
}
</style>

<style>
/* Save bar + calendar: teleported to body, so these must stay global.
   Scoped to the ihale form via body.ihf-form-open. */
body.ihf-form-open .fab-btns-wrapper {
    box-shadow: 0 -8px 24px rgba(5, 150, 105, .12) !important;
    background: rgba(255, 255, 255, .97) !important;
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    border-radius: 16px !important;
    border: 1px solid #eef2f7 !important;
    padding: .4rem .6rem !important;
    gap: 10px !important;
}
body.ihf-form-open .fab-btns-wrapper .btn-danger {
    background: #059669 !important;
    border-color: #059669 !important;
    color: #fff !important;
    border-radius: 11px !important;
    font-weight: 700 !important;
    box-shadow: 0 8px 20px rgba(5,150,105,.28) !important;
}
body.ihf-form-open .fab-btns-wrapper .btn-danger:hover,
body.ihf-form-open .fab-btns-wrapper .btn-danger.is-btn-hover {
    background: #047857 !important;
    border-color: #047857 !important;
}
body.ihf-form-open .fab-btns-wrapper .btn-cancel {
    background: #fff !important;
    color: #047857 !important;
    border: 1px solid #a7f3d0 !important;
    border-radius: 11px !important;
    font-weight: 700 !important;
}
body.ihf-form-open .fab-btns-wrapper .btn-cancel:hover,
body.ihf-form-open .fab-btns-wrapper .btn-cancel.is-btn-hover {
    background: #ecfdf5 !important;
    color: #047857 !important;
}
/* center the floating bar over the content column (210px sidebar) */
@media (min-width: 993px) {
    body.ihf-form-open .fab-btns-wrapper {
        left: calc(50% + 120px) !important;
    }
}
@media (max-width: 992px) {
    body.ihf-form-open .fab-btns-wrapper {
        left: 50% !important;
        width: calc(100vw - 32px) !important;
    }
}
body.ihf-form-open .flatpickr-day.selected,
body.ihf-form-open .flatpickr-day.selected:hover {
    background: #059669 !important;
    border-color: #059669 !important;
}
body.ihf-form-open .flatpickr-day.today { border-color: #a7f3d0 !important; }

/* Product picker "Seç" buttons are built by PickleTable at runtime (no scoped
   data attribute), so this must stay global. */
#ih_prod_table .ihf-pick-btn {
    border: none; border-radius: 999px; padding: 7px 20px;
    font-weight: 700; font-size: 12.5px; cursor: pointer;
    background: #059669; color: #fff;
    box-shadow: 0 4px 12px rgba(5,150,105,.25);
    transition: background .15s ease, transform .15s ease;
    white-space: nowrap;
}
#ih_prod_table .ihf-pick-btn:hover { background: #047857; transform: translateY(-1px); }
</style>
