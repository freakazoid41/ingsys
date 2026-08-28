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
                let st = this.rawData?.document?.status ?? this.formDataStore?.rawData?.document?.status ?? null;
                let arr = [];
                if(Array.isArray(st)) arr = st;
                else if(typeof st === 'string' && st.trim() && st.trim() !== '[]'){
                    try { arr = JSON.parse(st); } catch(e){ arr = []; }
                }
                if(Array.isArray(arr) && arr.length) return arr[arr.length-1].op_key || '';
                return this.id ? 'doc_trans_order_created' : '';
            },
            lockedStatuses(){ return ['doc_trans_order_transfer_sent','doc_trans_order_ready_for_shipment','doc_trans_order_approved','doc_trans_order_rejected','doc_trans_order_files_rejected','doc_trans_transfer_sent','doc_trans_transfer_approved','doc_trans_transfer_rejected']; },
            isLocked(){ return this.id && this.lockedStatuses.includes(this.orderStatus); },
            canSend(){ return this.id && ['doc_trans_order_created','doc_trans_order_files_rejected'].includes(this.orderStatus); },
            isFilesLocked(){ return this.id && ['doc_trans_order_transfer_sent','doc_trans_order_ready_for_shipment','doc_trans_order_approved','doc_trans_order_rejected','doc_trans_transfer_sent','doc_trans_transfer_approved','doc_trans_transfer_rejected'].includes(this.orderStatus); },
            readonlyFields(){
                if(!this.isLocked) return [];
                const fields = ['order_desc','imalatci_firma_adi'];
                if(this.isFilesLocked) fields.push('transfer_kabul_file','transfer_cins_file');
                return fields;
            },
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
        <div class="card mb-6" v-if="id && loadForm" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.04),0 1px 2px rgba(0,0,0,0.02);">
            <div v-if="canSend" style="background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);border-bottom:1px solid #bfdbfe;padding:16px 20px;">
                <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:14px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:#3b82f6;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;flex-shrink:0;box-shadow:0 2px 6px rgba(59,130,246,0.3);">
                        <i class="ki-outline ki-send"></i>
                    </div>
                    <div>
                        <h4 style="margin:0 0 2px;font-size:0.95rem;font-weight:700;color:#0f172a;">Transfer Gönder</h4>
                        <p style="margin:0;font-size:0.78rem;color:#64748b;line-height:1.4;">Transfer türünü seçin ve kaydedin. Parçalı seçerseniz gönderilecek kalemleri işaretleyin.</p>
                    </div>
                </div>
                <div style="display:flex;gap:12px;">
                    <label class="transfer-mode-option" :class="{ active: transferMode === 'at_once' }" style="flex:1;display:flex;align-items:center;gap:12px;padding:12px 14px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all 0.2s;background:#fff;">
                        <input type="radio" value="at_once" v-model="transferMode" class="d-none">
                        <div style="width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#eff6ff,#dbeafe);display:flex;align-items:center;justify-content:center;color:#3b82f6;font-size:14px;flex-shrink:0;">
                            <i class="ki-outline ki-direct-right"></i>
                        </div>
                        <div><div style="font-weight:700;font-size:0.85rem;color:#0f172a;">Tek Seferde</div><div style="font-size:0.72rem;color:#94a3b8;">Tüm sipariş gönderilir</div></div>
                    </label>
                    <label class="transfer-mode-option" :class="{ active: transferMode === 'partial' }" style="flex:1;display:flex;align-items:center;gap:12px;padding:12px 14px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all 0.2s;background:#fff;">
                        <input type="radio" value="partial" v-model="transferMode" class="d-none">
                        <div style="width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#fef3c7,#fde68a);display:flex;align-items:center;justify-content:center;color:#d97706;font-size:14px;flex-shrink:0;">
                            <i class="ki-outline ki-slice"></i>
                        </div>
                        <div><div style="font-weight:700;font-size:0.85rem;color:#0f172a;">Parçalı</div><div style="font-size:0.72rem;color:#94a3b8;">Seçili kalemler gönderilir</div></div>
                    </label>
                </div>
            </div>
            <div style="background:#fff;padding:0;">
                <OrderItemTable :key="(canSend ? transferMode : 'ro')" :orderId="id" :orderNumericId="formDataStore.rawData?.document?.id" :selectable="canSend && transferMode==='partial'" :containerSuffix="canSend ? '-sel' : ''" @select="onItemsSelected" />
            </div>
        </div>

        <div class="locked-card mb-6" v-if="id && isLocked">
            <div class="locked-card-body">
                <div class="locked-card-left">
                    <div class="locked-card-icon">
                        <i class="ki-outline ki-lock"></i>
                    </div>
                    <div>
                        <div class="locked-card-badge">Sipariş Kilitli</div>
                        <p class="locked-card-text">{{ isFilesLocked ? 'Sipariş kilitlendi; açıklama, imalatçı ve dosyalar değiştirilemez.' : 'Açıklama ve imalatçı bilgileri kilitlendi; dosyalar yine de güncellenebilir.' }}</p>
                    </div>
                </div>
                <button class="locked-cancel-btn" @click="cancelOrder" v-if="authStore.permissions?.includes('per-05-02')">
                    <i class="ki-outline ki-trash"></i>
                    <span>İptal Et</span>
                </button>
            </div>
        </div>

        <Form formtypes="op-doc-order-form" v-if="loadForm" savebtntitle="Kaydet" :readonlyFields="readonlyFields" :savecallback="submitForm" />
        
    </div>
</template>
<style scoped>
.transfer-mode-option:hover {
    border-color: #93c5fd !important;
    background: #f8fafc !important;
}
.transfer-mode-option.active {
    border-color: #3b82f6 !important;
    background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%) !important;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
.locked-card {
    border: 1px solid #fef3c7;
    border-radius: 14px;
    overflow: hidden;
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
}
.locked-card-body {
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}
.locked-card-left {
    display: flex;
    align-items: center;
    gap: 14px;
}
.locked-card-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #f59e0b;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 17px;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(245,158,11,0.3);
}
.locked-card-badge {
    font-weight: 700;
    font-size: 0.88rem;
    color: #92400e;
    margin-bottom: 2px;
}
.locked-card-text {
    margin: 0;
    font-size: 0.78rem;
    color: #a16207;
    line-height: 1.4;
}
.locked-cancel-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #fff;
    color: #dc2626;
    border: 1px solid #fecaca;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
    flex-shrink: 0;
}
.locked-cancel-btn:hover {
    background: #fef2f2;
    border-color: #f87171;
    box-shadow: 0 1px 4px rgba(220,38,38,0.15);
}
</style>
