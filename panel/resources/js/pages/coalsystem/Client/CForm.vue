
<script>
    import { wTrans } from 'laravel-vue-i18n';
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router';
    import { useAuthStore } from '@/stores/auth';

    import { useNavigationStore } from '@/stores/navigation';
    import { useFormDataStore } from '@/stores/formdata';
    import Swal from 'sweetalert2';
    
    import Form from '@/components/coalparts/Form.vue';


    export default {
        breadcrumbs: {
            list: [ { title: 'Müşteriler', path: '/coalpanel/client' } ,{ title: 'Müşteri Ekle / Düzenle', path: '#' }],
            title: 'Müşteri Düzenle / Oluştur'
        },
        components: {
            Form
        },
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                useFormDataStore,useAuthStore,
                Plib,
                Swal,
                useRoute,
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
            async submitStatus(type){
                //this.navigationStore.toggle(true);
                //here update status for all files of document
                if(this.id === undefined){
                    this.plib.toast(this.Swal,'error','Belge henüz oluşturulmadı..',() => {});
                    return;
                }

                Swal.fire({
                    confirmButtonText : 'Kaydet..',
                    showCloseButton : true,
                    showCancelButton: true,
                    cancelButtonText : 'Vazgeç',
                    html : `<h6 style="color:tomato">Bütün Dosyalar ${type == 'reject' ? 'Reddedilecektir' : 'Kabul edilecektir'}</h6> <br> <small class="mb-5">Durum Notu Giriniz (Boş Olabilir)</small>
                            <div class="row m-5 justify-content-center">
                                <div class="col-12">
                                    <textarea class="form-control" id="exampleFormControlTextarea1" rows="3" placeholder="..."></textarea>
                                </div>
                            </div>`,
                    allowOutsideClick: () => !Swal.isLoading(),
                    preConfirm : async () => {
                        try {
                            Swal.showLoading();
                            this.navigationStore.toggle(true);
                            const note     = document.getElementById('exampleFormControlTextarea1').value.trim();
                            const envelope = new FormData();
                            envelope.append('id',this.id);
                            envelope.append('op_key',type =='reject' ? 'doc_file_rejected' : 'doc_file_accepted');
                            envelope.append('note',note);
                            const rsp = await this.plib.request({
                                url      : '/api/v1/trans/set-file-status-all',
                                method   : 'POST',
                            },null,envelope);
                            if(rsp.success){
                                this.plib.toast(this.Swal,'success','İşlem Tamamlandı');
                            }else{
                                Swal.showValidationMessage(rsp.msg);
                            }
                            Swal.hideLoading();
                            setTimeout(() => {
                                 window.location.reload();
                            }, 500);
                            return rsp.success;
                        } catch (error) {
                            console.log(error)
                            Swal.showValidationMessage(`
                                Request failed: ${error}
                            `);
                        }

                        
                    }
                });
            },
            async submitForm(formData){
                this.formData = formData;
                this.navigationStore.toggle(true);
                this.formData.typeKey = 'op-doc-client';
                const rsp = this.plib.checkForm('.form-item');
                if(rsp.valid){
                    const   envelope  = new FormData();
                        envelope.append('data',JSON.stringify(this.formData));
                    //register files
                    for(let key in this.formData.files){
                        envelope.append(key,this.formData.files[key]);
                    }
                    await this.plib.request({
                        url      : '/api/v1/document'+(this.id !== undefined ? '/'+this.id : ''),
                        method   : this.id !== undefined ? 'PUT' : 'POST',
                    },null,envelope);
                    
                    //trigger auth store because some clients needs to refresh some permissions
                    if(this.authStore.typeKey === 'op-pert-reseller' ){
                        await this.authStore.getPermissions();
                    }

                    setTimeout(() => {
                        this.navigationStore.toggle(false);
                        
                        this.plib.toast(this.Swal,'success','İşlem Tamamlandı',() => {
                            this.$router.push({ name: 'CList' });
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
    <div v-if="!authStore.currentStatus.canProceed && authStore.typeKey == 'op-pert-reseller'" class="alert alert-warning" role="alert">
        Firma bilgileriniz henüz doldurulmamıştır. İşlem yapabilmek için lütfen aşağıdaki formu doldurunuz.
    </div>
   
    <Form formtypes="op-doc-client-form" :fabtype="/*authStore.typeKey !== 'op-pert-reseller' ? 'options' : 'saveBtn'*/'saveBtn'" v-if="loadForm" :savecallback="submitForm" :rejectcallback="submitStatus" :acceptcallback="submitStatus" />
    
</template>
