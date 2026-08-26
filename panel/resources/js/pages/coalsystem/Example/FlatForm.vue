
<script>
    import { wTrans } from 'laravel-vue-i18n';
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router'
    import { useNavigationStore } from '@/stores/navigation'
    import { useFormDataStore } from '@/stores/formdata'
    import Swal from 'sweetalert2';
    
<<<<<<<< HEAD:panel/resources/js/pages/talk/visit/VForm.vue
    import TalkForm from '@/components/talk/TalkForm.vue';
========
    import Form from '@/components/coalparts/Form.vue';
>>>>>>>> coalSYS:panel/resources/js/pages/coalsystem/Example/FlatForm.vue


    export default {
        breadcrumbs: {
            list: [ { title: 'Panel', path: '/' }, { title: 'Daire', path: '/flats/form' } ],
            title: 'Daire'
        },
        components: {
<<<<<<<< HEAD:panel/resources/js/pages/talk/visit/VForm.vue
            TalkForm,
========
            Form
            
>>>>>>>> coalSYS:panel/resources/js/pages/coalsystem/Example/FlatForm.vue
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
        async mounted(){
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
<<<<<<<< HEAD:panel/resources/js/pages/talk/visit/VForm.vue
            
            this.navigationStore.setBread([
                {
                    title : this.wTrans('menu.home'),
                    url   : '/panel',
                },{
                    title : this.wTrans('menu.visit'),
                    url   : '/panel/visit',
                },{
                    title : this.wTrans('form.visit.list'),
                    url   : '/panel/visit/form',
                }
            ] ,this.wTrans('form.visit'));

          
========

           
>>>>>>>> coalSYS:panel/resources/js/pages/coalsystem/Example/FlatForm.vue
        },  
        data() {
            const route = useRoute();
            return {
                formKey         : 'op-doc-visit',
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
                this.formData.typeKey = this.formKey;
                const rsp = this.plib.checkForm('.form-item');
                if(rsp.valid){
                    const   envelope  = new FormData();
                        envelope.append('data',JSON.stringify(this.formData));
                    //register files
                    for(let key in this.formData.files){
                        envelope.append(key,this.formData.files[key]);
                    }
                    const rsp = await this.plib.request({
                        url      : '/api/v1/document'+(this.id !== undefined ? '/'+this.id : ''),
                        method   : this.id !== undefined ? 'PUT' : 'POST',
                    },null,envelope);
                    
                    setTimeout(() => {
                        this.navigationStore.toggle(false);
<<<<<<<< HEAD:panel/resources/js/pages/talk/visit/VForm.vue
                        if(rsp.success == true){
                            this.plib.toast(this.Swal,'success','İşlem Tamamlandı',() => {
                                this.$router.push({ name: 'VList' })
                            });
                        }else{
                            this.plib.toast(this.Swal,'error','Arıza Oluştu..',() => {});
                        }
                        
========
                        this.plib.toast(this.Swal,'success','İşlem Tamamlandı',() => {
                            this.$router.push({ name: 'FlatList' });
                        });
>>>>>>>> coalSYS:panel/resources/js/pages/coalsystem/Example/FlatForm.vue
                    }, 300);
                    return true;
                }else{
                    this.navigationStore.toggle(false);
                    this.plib.toast(this.Swal,'info','Eksik Alanları Doldurmalısınız..',() => {});

                    return false;
                }
            },
        }
    }

</script>

<template>
    <div class="table-tab">
        <div class="table-tab-head">
            <router-link :to="{ name: 'VList' }"><button class="table-toggle" body="1">{{ $t('form.visit.list') }}</button></router-link>
            <button class="table-toggle active" body="2">{{ $t('form.visit') }}</button>
        </div>
        <div class="table-tab-body">
            <div class="body-1 table-main"  >
                <TalkForm :formtypes="formKey+'-form'" v-if="loadForm" :savecallback="submitForm" />
                <!--<Transactions  :id="id"/>-->
            </div>
        </div>
    </div>
    
</template>
