
<script>
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router';
    import { useAuthStore } from '@/stores/auth';

    import { useNavigationStore } from '@/stores/navigation';
    import { useFormDataStore } from '@/stores/formdata';
    import Swal from 'sweetalert2';

    import Form from '@/components/coalparts/Form.vue';


    export default {
        breadcrumbs: {
            list: [ { title: 'Ürün Kategorileri', path: '/coalpanel/product-categories' } ,{ title: 'Kategori Ekle / Düzenle', path: '#' }],
            title: 'Kategori Düzenle / Oluştur'
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
        data() {
            const route = useRoute();

            return {
                loadForm        : false,
                plib            : new Plib(),
                authStore       : useAuthStore(),
                navigationStore : useNavigationStore(),
                formDataStore   : useFormDataStore(),
                id              : route?.params?.id !== '' ? route?.params?.id : undefined,
            };
        },
        methods: {
            async submitForm(formData){
                this.formData = formData;
                this.navigationStore.toggle(true);
                this.formData.typeKey = 'op-doc-product-category';

                // Zero-requirement categories: the repeatable category_files
                // group always renders one blank template row. A completely
                // untouched row is not a requirement — relax its required flags
                // for validation only (restored right after) so the category can
                // be saved with an empty file list.
                const relaxed = [];
                const emptyRowIds = [];
                document.querySelectorAll('.area-target[data-tag="op-doc-product-category-form"] .multiple-item-row').forEach(row => {
                    const names = ['evrak_no','standard','file_title','file_group','file_types'];
                    const empty = names.every(n => {
                        const el = row.querySelector('[name^="'+n+'"]');
                        return !el || String(el.value || '').trim() === '';
                    });
                    if (!empty) return;
                    const anyName = row.querySelector('[name*="**category_files**"]')?.name || '';
                    const rowId = anyName.split('**')[2] || '';
                    if (rowId) emptyRowIds.push(rowId);
                    row.querySelectorAll('.form-item').forEach(el => {
                        if (el.required) { relaxed.push(el); el.required = false; }
                    });
                });
                const rsp = this.plib.checkForm('.form-item');
                relaxed.forEach(el => { el.required = true; });

                // Drop the untouched template row from the payload as well —
                // its hidden tagboxes would otherwise create a phantom blank
                // requirement row on the server.
                if (emptyRowIds.length) {
                    for (const key of Object.keys(this.formData.dynamicF || {})) {
                        const ents = this.formData.dynamicF[key]?.entities;
                        if (!ents) continue;
                        for (const tag of Object.keys(ents)) {
                            if (emptyRowIds.some(id => tag.includes('**category_files**' + id))) delete ents[tag];
                        }
                        if (Object.keys(ents).length === 0) delete this.formData.dynamicF[key];
                    }
                    for (const fk of Object.keys(this.formData.files || {})) {
                        if (emptyRowIds.some(id => fk.includes('**category_files**' + id))) delete this.formData.files[fk];
                    }
                }
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
                            this.$router.push({ name: 'PCList' });
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
    <Form formtypes="op-doc-product-category-form" fabtype="saveBtn" v-if="loadForm" :savecallback="submitForm" />

</template>
