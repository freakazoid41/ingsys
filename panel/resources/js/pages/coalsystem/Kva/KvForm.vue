
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
            list: [ { title: 'Güç Tanımları (kVA)', path: '/coalpanel/kva' } ,{ title: 'Kategori Ekle / Düzenle', path: '#' }],
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
                this.formData.typeKey = 'op-doc-kva';
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
                            this.$router.push({ name: 'KvList' });
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
    <Form formtypes="op-doc-kva-form" fabtype="saveBtn" v-if="loadForm" :savecallback="submitForm" />

</template>
