<script>
    import { wTrans } from 'laravel-vue-i18n';
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router';
    import { useNavigationStore } from '@/stores/navigation';
    import { useFormDataStore } from '@/stores/formdata';
    import { useAuthStore } from '@/stores/auth';
    import Swal from 'sweetalert2';
    import Form from '@/components/coalparts/Form.vue';

    export default {
        breadcrumbs: {
            list: [ { title: 'Parking Sessions', path: '/coalpanel/parking-sessions' }, { title: 'Session Add / Edit', path: '#' } ],
            title: 'Session Add / Edit'
        },
        components: { Form },
        setup() {
            return {
                useNavigationStore,
                useFormDataStore,
                useAuthStore,
                Plib,
                Swal,
                useRoute,
                wTrans
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            const checkData = async () => {
                if(this.id !== undefined && this.id !== ''){
                    const rsp = await this.plib.request({ url: '/api/v1/document/'+this.id, method: 'GET' }, null);
                    return rsp;
                } else {
                    return { success:false };
                }
            };
            checkData().then(response => {
                this.navigationStore.toggle(true);
                this.formDataStore.setData(response?.data?.formFormat);
                this.loadForm = true;
                setTimeout(() => { this.navigationStore.toggle(false); }, 500);
            });
        },
        data() {
            const route = useRoute();
            return {
                loadForm : false,
                plib : new Plib(),
                navigationStore : useNavigationStore(),
                formDataStore : useFormDataStore(),
                authStore : useAuthStore(),
                id : route?.params?.id !== '' ? route?.params?.id : undefined,
            };
        },
        methods: {
            async submitForm(formData){
                this.formData = formData;
                this.navigationStore.toggle(true);
                this.formData.typeKey = 'op-doc-parking-session';
                const rsp = this.plib.checkForm('.form-item');
                if(rsp.valid){
                    const firstDynamicKey = Object.keys(this.formData.dynamicF)[0];
                    if(firstDynamicKey){
                        const entities = this.formData.dynamicF[firstDynamicKey].entities || {};
                        Object.keys(entities).forEach(key => {
                            if(key.startsWith('main_')) this.formData[key] = entities[key];
                        });
                    }
                    const envelope = new FormData();
                    envelope.append('data', JSON.stringify(this.formData));
                    for(const key in this.formData.files){ envelope.append(key, this.formData.files[key]); }
                    const response = await this.plib.request({ url: '/api/v1/document'+(this.id !== undefined ? '/'+this.id : ''), method: this.id !== undefined ? 'PUT' : 'POST' }, null, envelope);
                    setTimeout(() => {
                        this.navigationStore.toggle(false);
                        this.plib.toast(this.Swal, response.success ? 'success' : 'error', response.msg || 'Operation completed', () => {
                            this.$router.push({ name: 'ParkingSessionList' });
                        });
                    }, 300);
                } else {
                    this.navigationStore.toggle(false);
                    this.plib.toast(this.Swal, 'info', 'Please fill required fields', () => {});
                }
            }
        }
    }
</script>

<template>
    <Form formtypes="op-doc-parking-session-form" v-if="loadForm" :savecallback="submitForm" savebtntitle="Save Session" />
</template>
