
<style>
    
</style>

<script>
    import { wTrans } from 'laravel-vue-i18n';
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router'
    import { useNavigationStore } from '@/stores/navigation'
    import { useFormDataStore } from '@/stores/formdata'
    import Swal from 'sweetalert2';
    
    import Form from '@/components/Form.vue';


    export default {
        components: {
            Form,
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
                        url      : '/api/v1/persons/'+this.id,
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
                if(response?.data){
                    response.data.user_username = response?.data.user_name;
                    response.data.main_name     = response?.data.name;
                }   
                
                //set ready data do store for update transactions (form component will catch that)
                this.formDataStore.setData({
                    'op-doc-user-form' : {
                        [this.id] : {
                            'entities' : response?.data
                        }
                    }
                });
               
                this.loadForm = true;
                setTimeout(() => {
                    this.navigationStore.toggle(false);
                }, 500);
                
            });

            this.navigationStore.setBread([
                {
                    title : this.wTrans('menu.home'),
                    url   : '/panel',
                },
                {
                    title : this.wTrans('menu.users'),
                    url   : '/panel/users',
                }
            ] ,this.wTrans('form.users'));
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
                console.log(formData);
                this.navigationStore.toggle(true);
                this.formData.typeKey = 'op-doc-flat';
                const rsp = this.plib.checkForm('.form-item');
                if(rsp.valid){
                    const   envelope  = new FormData();
                        envelope.append('data',JSON.stringify(Object.values(this.formData.dynamicF)?.[0]?.entities));
                    //register files
                    for(let key in this.formData.files){
                        envelope.append(key,this.formData.files[key]);
                    }
                    await this.plib.request({
                        url      : '/api/v1/persons'+(this.id !== undefined ? '/'+this.id : ''),
                        method   : this.id !== undefined ? 'PUT' : 'POST',
                    },null,envelope);

                    setTimeout(() => {
                        this.navigationStore.toggle(false);
                        this.plib.toast(this.Swal,'success','İşlem Tamamlandı',() => {
                            window.location.href = '/panel/users'
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
    <Form formtypes="op-doc-user-form" v-if="loadForm" :savecallback="submitForm" />
</template>
