<script>
    import { wTrans } from 'laravel-vue-i18n';
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router'
    import { useNavigationStore } from '@/stores/navigation'
    import { useFormDataStore } from '@/stores/formdata';
    import { useAuthStore } from '@/stores/auth';
    import Swal from 'sweetalert2';
    import Form from '@/components/coalparts/Form.vue';
    import OrderItemTable from '@/components/Order/OrderItemTable.vue';

    export default {
        breadcrumbs: {
            list: [ { title: 'Siparişler', path: '/coalpanel/orders' },{ title: 'Sipariş Detayı', path: '#' }  ],
            title: 'Sipariş Detayı'
        },
        components: { Form, OrderItemTable },
        setup() {
            return { useNavigationStore, useFormDataStore, Plib, Swal, useRoute, useAuthStore, wTrans }
        },
        mounted(){
            this.navigationStore.toggle(true);
            const checkData = async () => {
                if(this.id !== undefined && this.id !== ''){
                    const rsp = await this.plib.request({ url:'/api/v1/document/'+this.id, method:'GET' },null);
                    return rsp;
                }else{
                    return { success : false }
                }
            }
            checkData().then(response => {
                this.navigationStore.toggle(true);
                this.formDataStore.setData(response?.data?.formFormat);
                this.formDataStore.rawData = response?.data || {};
                this.rawData = response?.data || {};
                this.loadForm = true;
                setTimeout(() => this.navigationStore.toggle(false), 400);
            });
        },
        data() {
            const route = useRoute();
            return {
                loadForm: false,
                plib: new Plib(),
                navigationStore: useNavigationStore(),
                formDataStore: useFormDataStore(),
                authStore: useAuthStore(),
                id: route?.params?.id !== '' ? route?.params?.id : undefined,
                rawData: {},
                transferMode: 'at_once',
                selectedItems: [],
            };
        },
        computed: {
            orderStatus(){
                let st = this.rawData?.document?.status || this.formDataStore?.rawData?.document?.status || '[]';
                try {
                    const arr = JSON.parse(st);
                    if(Array.isArray(arr) && arr.length) return arr[arr.length-1].op_key || '';
                } catch(e){}
                return '';
            },
            lockedStatuses(){ return ['doc_trans_order_transfer_sent','doc_trans_order_approved','doc_trans_order_rejected','doc_trans_order_files_rejected','doc_trans_transfer_sent','doc_trans_transfer_approved','doc_trans_transfer_rejected']; },
            isLocked(){ return this.id && this.lockedStatuses.includes(this.orderStatus); },
            canSend(){ return this.id && ['doc_trans_order_created','doc_trans_order_files_rejected'].includes(this.orderStatus); },
            readonlyFields(){ return this.isLocked ? ['order_desc','imalatci_firma_adi'] : []; },
        },
        methods: {
            async submitForm(formData){
                this.formData = formData;
                this.navigationStore.toggle(true);
                this.formData.typeKey = 'op-doc-order';

                if(this.canSend && this.transferMode){
                    this.formData.transfer_mode = this.transferMode;
                    if(this.transferMode === 'partial'){
                        if(!this.selectedItems.length){
                            this.navigationStore.toggle(false);
                            this.plib.toast(this.Swal,'info','Parçalı transfer için en az bir kalem seçmelisiniz.',()=>{});
                            return;
                        }
                        this.formData.selected_items = this.selectedItems;
                    }
                }

                const rsp = this.plib.checkForm('.form-item');
                if(rsp.valid){
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
                    const response = await this.plib.request({
                        url: '/api/v1/document'+(this.id !== undefined ? '/'+this.id : ''),
                        method: this.id !== undefined ? 'PUT' : 'POST',
                    },null,envelope);
                    setTimeout(() => {
                        this.navigationStore.toggle(false);
                        const msg = response?.data?.transfer_msg || response.msg || 'İşlem Tamamlandı';
                        this.plib.toast(this.Swal, response.success ? 'success' : 'error', msg,() => {
                            if(response.success) this.$router.push({ name: 'OrderList' });
                        });
                    }, 300);
                }else{
                    this.navigationStore.toggle(false);
                    this.plib.toast(this.Swal,'info','Eksik Alanları Doldurmalısınız..',()=>{});
                }
            },
            onItemsSelected(items){
                this.selectedItems = items;
            },
            async cancelOrder(){
                const conf = await this.Swal.fire({
                    title:'Siparişi İptal Et',
                    text:'Sipariş tamamen reddedilecek ve iptal edilecek. Emin misiniz?',
                    icon:'warning', showCancelButton:true, confirmButtonText:'Evet, İptal Et', cancelButtonText:'Vazgeç',
                });
                if(!conf.isConfirmed) return;
                const fd=new FormData(); fd.append('id',this.id); fd.append('note','Sipariş reddedildi ve iptal edildi');
                const rsp=await this.plib.request({url:'/api/v1/orders/cancel', method:'POST'}, null, fd);
                this.plib.toast(this.Swal, rsp.success?'success':'error', rsp.msg||'İşlem Tamamlandı',()=>{
                    if(rsp.success) this.$router.push({name:'OrderList'});
                });
            }
        }
    }
</script>
<template>
    <div style="padding-bottom: 100px;">
        <div class="card mb-6" v-if="id && canSend">
            <div class="card-body">
                <h4 class="mb-3" style="font-weight:800;color:#0f172a;">Transfer Gönder</h4>
                <p class="text-muted fs-7 mb-3">Transfer türünü seçin ve kaydedin. Parçalı seçerseniz gönderilecek kalemleri işaretleyin.</p>
                <div class="d-flex gap-5 mb-3">
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                        <input type="radio" value="at_once" v-model="transferMode"> Tek Seferde (Tüm Sipariş)
                    </label>
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                        <input type="radio" value="partial" v-model="transferMode"> Parçalı (Seçili Kalemler)
                    </label>
                </div>
                <div v-if="transferMode === 'partial'" class="mt-3">
                    <OrderItemTable v-if="loadForm" :orderId="id" :orderNumericId="formDataStore.rawData?.document?.id" selectable containerSuffix="-sel" @select="onItemsSelected" />
                </div>
            </div>
        </div>

        <div class="card mb-6" v-if="id && isLocked">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-warning text-dark mb-2">Sipariş kilitli</span>
                    <p class="mb-0 text-muted fs-7">Sipariş transfer için gönderildi. Açıklama ve imalatçı bilgileri kilitlendi; dosyalar yine de güncellenebilir.</p>
                </div>
                <button class="btn btn-danger" @click="cancelOrder" v-if="authStore.permissions?.includes('per-05-02')"><i class="ki-outline ki-trash"></i> İptal Et</button>
            </div>
        </div>

        <Form formtypes="op-doc-order-form" v-if="loadForm" savebtntitle="Kaydet" :readonlyFields="readonlyFields" :savecallback="submitForm" />
        <div class="mt-8" v-if="id && loadForm && !(canSend && transferMode === 'partial')">
            <OrderItemTable :orderId="id" :orderNumericId="formDataStore.rawData?.document?.id" />
        </div>
    </div>
</template>
