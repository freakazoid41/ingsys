
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
                //console.log(formData);
                this.navigationStore.toggle(true);
                this.formData.typeKey = this.formKey;
                const rsp = this.plib.checkForm('.form-item');
                if(rsp.valid){

                    //check pass
                    const fields = Object.values(this.formData.dynamicF)[0]?.entities;
                    //here check if username is entered but password is empty
                    if(fields?.user_username != undefined && (fields?.user_password == undefined || fields?.user_password == '')){
                        this.navigationStore.toggle(false);
                        this.plib.toast(this.Swal,'error','Parola alanı boş bırakılamaz..',);
                        document.querySelector('input[name="user_password"]').classList.add('is-invalid');
                        return false;
                    }

                    //here check passwords
                    
                    if(fields?.user_password){
                        
                        if(fields?.user_password_check == undefined || fields?.user_password != fields?.user_password_check){
                            this.navigationStore.toggle(false);
                            this.plib.toast(this.Swal,'error','Parola alanları uyuşmamaktadır..',);
                            document.querySelector('input[name="user_password"]').classList.add('is-invalid');
                            document.querySelector('input[name="user_password_check"]').classList.add('is-invalid');
                            return false;
                        }
                    }
                    
                    const   envelope  = new FormData();
                        envelope.append('data',JSON.stringify(fields));
                        envelope.append('alldata',JSON.stringify(this.formData));
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
                            this.$router.push({ name: 'UserList' });
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
