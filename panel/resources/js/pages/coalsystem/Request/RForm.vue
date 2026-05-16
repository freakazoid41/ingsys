
<script>
    import { wTrans } from 'laravel-vue-i18n';
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router'
    import { useNavigationStore } from '@/stores/navigation'
    import { useFormDataStore } from '@/stores/formdata';
    import { useAuthStore } from '@/stores/auth';

    import Swal from 'sweetalert2';
    import OfferRequestTable from "@/components/Offer/OfferRequestTable.vue";

    import Form from '@/components/coalparts/Form.vue';
    import RSummary from '@/components/coalparts/RSummary.vue';
    import RequestLogTimeline from '@/components/coalparts/RequestLogTimeline.vue';


    export default {
        breadcrumbs: {
            list: [ { title: 'Talepler', path: '/coalpanel/request' },{ title: 'Talep Ekle / Düzenle', path: '#' }  ],
            title: 'Talep Düzenle / Oluştur'
        },
        components: {
            Form,
            OfferRequestTable,
            RSummary,
            RequestLogTimeline,
        },
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                useFormDataStore,
                Plib,
                Swal,
                useRoute,
                useAuthStore,
                wTrans
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            const checkData = async () => {
                //if has id param get document data first
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
                //set ready data do store for update transactions (form component will catch that)
                this.formDataStore.setData(response?.data?.formFormat);
                this.formDataStore.rawData = response?.data || {};
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
                navigationStore : useNavigationStore(),
                formDataStore   : useFormDataStore(),
                id              : route?.params?.id !== '' ? route?.params?.id : undefined,
            };
        },
        methods: {
            async submitForm(formData){
                this.formData = formData;
                this.navigationStore.toggle(true);
                this.formData.typeKey = 'op-doc-request';
                const rsp = this.plib.checkForm('.form-item');
                if(rsp.valid){
                    const   envelope  = new FormData();
                        envelope.append('data',JSON.stringify(this.formData));
                    //register files
                    for(let key in this.formData.files){
                        envelope.append(key,this.formData.files[key]);
                    }
                    const response = await this.plib.request({
                        url      : '/api/v1/document'+(this.id !== undefined ? '/'+this.id : ''),
                        method   : this.id !== undefined ? 'PUT' : 'POST',
                    },null,envelope);

                    setTimeout(() => {
                        this.navigationStore.toggle(false);
                        this.plib.toast(this.Swal,response.success ? 'success' : 'error',response.msg || 'İşlem Tamamlandı',() => {
                            this.$router.push({ name: 'RequestList' });
                        });
                    }, 300);

                }else{
                    this.navigationStore.toggle(false);
                    this.plib.toast(this.Swal,'info','Eksik Alanları Doldurmalısınız..',() => {});
                }
            },
            async addOffer(){
                Swal.fire({
                    title : 'Teklif Türü Seçiniz',
                    showConfirmButton : false,
                    showCloseButton : true,
                    html : `<div class="row m-5 justify-content-center">
                                <button class="btn btn-primary mb-5 offer-type" data-key="op-doc-offer-form**Teklif Formu" type="button">Teklif Formu</button>
                                <button class="btn btn-secondary mb-5 offer-type" data-key="op-doc-offer-file**Dosya Yükleme" type="button">Dosya Yükleme</button>
                            </div>`,
                    willOpen : async () => {
                        document.querySelectorAll('.offer-type').forEach(btn => {
                            btn.addEventListener('click', e => {
                                this.navigationStore.setRouteParams({ 
                                    request_id : this.id , 
                                    offer_type : e.target.dataset.key,
                                    request    :  Object.values(this.formDataStore.rawData.formFormat['op-doc-request-form'])[0].entities
                                });
                                this.$router.push({ name: 'OForm'});
                                Swal.close();
                            });
                        });
                    }
                });
            }
        }
    }

</script>

<template>
    <!-- Supplier: read-only summary dashboard -->
    <RSummary
        v-if="loadForm && useAuthStore().typeKey === 'op-pert-reseller'"
        :entities="Object.values(formDataStore.rawData?.formFormat?.['op-doc-request-form'] ?? {})[0]?.entities ?? {}"
        :document="formDataStore.rawData?.document ?? {}"
        :addOfferCallback="addOffer"
    />

    <!-- Admin / client: edit form + offers table -->
    <div v-else style="padding-bottom: 100px;">
        <Form formtypes="op-doc-request-form" v-if="loadForm" savebtntitle="Kaydet" :savecallback="submitForm" />
        <div class="mt-10" v-if="id !== undefined">
            <OfferRequestTable :requestId="id" :addOfferCallback="addOffer" />
        </div>
        <div class="mt-10" v-if="id !== undefined">
            <RequestLogTimeline :requestQnid="id" />
        </div>
    </div>


</template>
