
<style>
    
</style>

<script>
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router'
    import { useNavigationStore } from '@/stores/navigation'
    import { useFormDataStore } from '@/stores/formdata'
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    
    import Form from '@/components/Form.vue';
    import Transactions from '@/components/Transactions.vue';
    

    export default {
        components: {
            Form,
            Transactions 
        },
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                useFormDataStore,
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
                    
                }, 1000);
                
            });

            this.navigationStore.setBread([
                {
                    title : this.wTrans('menu.home'),
                    url   : '/panel',
                },
                {
                    title : this.wTrans('menu.targets'),
                    url   : '/panel/targets',
                }
            ] ,this.wTrans('form.targets'));
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
                this.formData.typeKey = 'op-doc-target';
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

                    setTimeout(() => {
                        this.navigationStore.toggle(false);
                        this.plib.toast(this.Swal,'success','İşlem Tamamlandı',() => {
                            window.location.href = '/panel/targets'
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
    <Form formtypes="op-doc-target-form" v-if="loadForm" :savecallback="submitForm" />
    <Transactions  :id="id"/>
</template>
