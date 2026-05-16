
<script>
    import { wTrans } from 'laravel-vue-i18n';
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router'
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import { useFormDataStore } from '@/stores/formdata';
    import Swal from 'sweetalert2';
    import dayjs from 'dayjs';
    import Form from '@/components/coalparts/Form.vue';
    import OfferSummary from '@/components/Offer/OfferSummary.vue';
    import OfferLogTimeline from '@/components/Offer/OfferLogTimeline.vue';


    export default {
        breadcrumbs: {
            list: [  { title: 'Teklifler', path: '/coalpanel/offer' },{ title: 'Teklif Ekle / Düzenle', path: '#' } ],
            title: 'Teklif Ver'
        },
        components: {
            Form,
            OfferSummary,
            OfferLogTimeline,
        },
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                useFormDataStore,
                useAuthStore,

                Plib,
                Swal,
                useRoute,
                wTrans,
                dayjs
            }
        },
        mounted(){
            if(this.authStore.currentStatus.canResponse == false && !this.isViewMode){
                Swal.fire({
                    icon: 'error',
                    title: 'Erişim Reddedildi',
                    text: 'Firma bilgileriniz henüz sistem tarafından onaylanmamıştır. Bu durumda her hangi bir teklif veremezsiniz. Anlayışınız için teşekkür ederiz.',
                    confirmButtonText: 'Kapat.',
                    willClose : () => {
                        this.$router.push({ name: 'OList' });
                    }
                });
            }

            
            
            

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

            checkData().then(async response => {
                this.navigationStore.toggle(true);
                
                //some data may be come from requests page 
                
                if(this.navigationStore.routeParams?.request_id == undefined && this.id == undefined) this.$router.push({ name: 'RequestList' });
                //this are is for refreshing page when refresh navigation store is resetting 
                if(response?.data?.formFormat && Object.values(response?.data?.formFormat?.['op-doc-offer-form']).at(-1)){
                    const data = Object.values(response?.data.formFormat?.['op-doc-offer-form']).at(-1).entities;
                    this.navigationStore.setRouteParams({ 
                        offer_type : data.offer_type.split('**')[0],
                    });
                }
                this.formDataStore.rawData = response?.data || {};

                let requestData = this.navigationStore.routeParams?.request ?? null;
                if(!requestData && this.navigationStore.routeParams?.request_id != undefined && this.navigationStore.routeParams?.request_id != 'Bağımsız Teklif'){
                    const reqRsp = await this.plib.request({
                        url      : '/api/v1/document/'+this.navigationStore.routeParams.request_id,
                        method   : 'GET',
                    },null);
                    requestData = Object.values(reqRsp.data?.formFormat?.['op-doc-request-form'] ?? {})[0]?.entities ?? null;
                    this.requestDetails = requestData || null;

                    if(requestData){
                        this.navigationStore.setRouteParams({
                            ...this.navigationStore.routeParams,
                            request: requestData
                        });
                    }
                }

                //set ready data do store for update transactions (form component will catch that)
                this.formDataStore.setData(response?.data?.formFormat,(this.id == undefined) ? {
                    request_id      : this.navigationStore.routeParams?.request_id,
                    offer_type      : this.navigationStore.routeParams?.offer_type,
                    target_type     : requestData?.target_type ?? this.navigationStore.routeParams?.request?.target_type,
                    cliid           : this.authStore.currentStatus.clientQnid,
                    clititle        : this.authStore.currentStatus.clientTitle,
                    payment_desc    : requestData?.payment_desc ?? this.navigationStore.routeParams?.request?.payment_desc,
                    payment_periods : requestData?.payment_periods ?? this.navigationStore.routeParams?.request?.payment_periods
                } : {});
                
                if(response?.data?.document?.status != undefined){
                    this.statusList = this.parseStatusItems(response.data.document.status);
                }

                /*this.navigationStore.setRouteParams({ 
                    offer_type : rowData.offer_type,
                });*/


                const editableStatuses = ['doc_trans_offer_revision','doc_trans_created','doc_trans_offer_draft'];
                this.editable = this.id !== undefined && editableStatuses.includes(this.currentOfferStatusKey()) && this.authStore.permissions?.includes('per-08-02') && this.authStore.typeKey === 'op-pert-reseller';

                if(this.id === undefined || this.id === ''){
                    this.editable = true;
                }

                //here if is admin user set offer status to inspecting
                if(
                    this.authStore.permissions?.includes('per-08-02') && 
                    this.authStore.typeKey !== 'op-pert-reseller' && 
                    ['doc_trans_created','doc_trans_offer_draft','doc_trans_offer_revised'].includes(this.currentOfferStatusKey())
                ){
                    const note     = 'Yönetici İncelemeye Başladı';
                    const envelope = new FormData();
                    envelope.append('id',this.id);
                    envelope.append('op_key','doc_trans_offer_review');
                    envelope.append('note',note);
                    const rsp = await this.plib.request({
                        url      : '/api/v1/trans/set-status',
                        method   : 'POST',
                    },null,envelope);
                }
               
                this.loadForm = true;
                await this.loadOfferVersionHistory();

                setTimeout(() => {
                    this.navigationStore.toggle(false);
                }, 500);
                
            });

          
        },  
        data() {
            const route = useRoute();
            return {
                editable        : true,
                loadForm        : false,
                plib            : new Plib(),
                authStore       : useAuthStore(),
                navigationStore : useNavigationStore(),
                formDataStore   : useFormDataStore(),
                route           : route,
                id              : route?.params?.id !== '' ? route?.params?.id : undefined,
                offerType       : route?.params?.offer_type ?? undefined,
                offerStatus     : route?.params?.offer_status ?? undefined,
                isViewMode      : route?.query?.view === '1',
                requestId       : useNavigationStore().routeParams?.request_id ?? false,
                requestDetails  : null,
                statusList      : [],
                offerVersionOptions: [],
                selectedVersionId : '',
                previewEntities   : null,
            };
        },
        methods: {
            parseStatusItems(statusRaw){
                let rawList = [];
                try {
                    rawList = JSON.parse(statusRaw || '[]');
                } catch (e) {
                    rawList = [];
                }

                if (!Array.isArray(rawList)) {
                    if (rawList && typeof rawList === 'object' && Array.isArray(rawList.history)) {
                        rawList = rawList.history;
                    } else if (rawList && typeof rawList === 'object') {
                        rawList = [rawList];
                    } else {
                        rawList = [];
                    }
                }

                return rawList.map(item => {
                    const note = this.parseStatusNote(item.note ?? item.description ?? item.detail ?? '');
                    const title = item.op_title ?? 'Bekleniyor';
                    return {
                        title,
                        note,
                        op_key: item.op_key ?? '',
                        name: item.name ?? item.user ?? item.owner ?? '—',
                        time: item.created_at ?? item.date ?? item.updated_at ?? item.time ?? '',
                        badge: item.badge ?? item.type ?? item.status ?? '',
                    };
                });
            },
            parseStatusNote(note){
                if (!note) return '';
                if (typeof note === 'string') {
                    try {
                        const parsed = JSON.parse(note);
                        return parsed?.note ?? parsed?.description ?? parsed?.text ?? note;
                    } catch (e) {
                        return note;
                    }
                }
                if (typeof note === 'object') {
                    return note.note ?? note.description ?? note.text ?? JSON.stringify(note);
                }
                return String(note);
            },
            currentOfferStatusKey(){
                if(this.navigationStore.routeParams?.offer_status) return this.navigationStore.routeParams.offer_status;
                
                const lastStatus = this.statusList.length ? this.statusList[0]?.op_key : null;
                return lastStatus ?? 'doc_trans_created';
            },
            formatRequestDetail(value){
                if (value === undefined || value === null || value === '') return '—';
                return String(value);
            },
            async downloadPdf(){
                if(this.id === undefined){
                    this.plib.toast(this.Swal,'info','Teklif kaydedildikten sonra PDF indirebilirsiniz.',() => {});
                    return;
                }

                this.plib.openTab('POST', '/export/offer', {"id": this.id},'_blank');
                
            },

            async statusAction(opKey,title){
                if(this.id === undefined){
                    this.plib.toast(this.Swal,'info','Teklif kaydedildikten sonra işlem yapabilirsiniz.',() => {});
                    return;
                }

                Swal.fire({
                    confirmButtonText : 'Kaydet..',
                    showCloseButton : true,
                    html : `<small class="mb-5">Durum Notu Giriniz (Boş Olabilir)</small>
                            <div class="row m-5 justify-content-center">
                                <div class="col-12">
                                    <textarea class="form-control" id="exampleFormControlTextarea1" rows="3" placeholder="..."></textarea>
                                </div>
                            </div>`,
                    allowOutsideClick: () => !Swal.isLoading(),
                    preConfirm : async () => {
                        try {
                            const note     = document.getElementById('exampleFormControlTextarea1').value.trim();
                            const envelope = new FormData();
                            envelope.append('id',this.id);
                            envelope.append('op_key',opKey);
                            envelope.append('note',note);
                            const rsp = await this.plib.request({
                                url      : '/api/v1/trans/set-status',
                                method   : 'POST',
                            },null,envelope);
                            if(rsp.success){
                                this.plib.toast(this.Swal,'success','İşlem Tamamlandı',() => {
                                    this.$router.push({ name: 'OList' });
                                });
                            }else{
                                Swal.showValidationMessage(rsp.msg);
                            }
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

            async requestRevision(){
                return this.statusAction('doc_trans_offer_revision','Revize Talep Et');
            },

            async approveOffer(){
                return this.statusAction('doc_trans_offer_approved','Onayla');
            },

            async rejectOffer(){
                return this.statusAction('doc_trans_offer_rejected','Reddet');
            },

            async loadOfferVersionHistory(){
                if (!this.id) return;

                const tableReq = JSON.stringify({
                    scale  : { page: 1, limit: 100 },
                    filter : [{ key: 'doc_qnid', type: '=', value: this.id }],
                });
                const envelope = new FormData();
                envelope.append('tableReq', tableReq);
                envelope.append('model', 'userlogs');
                const rsp = await this.plib.request({ url: '/api/v1/table/userlog', method: 'POST' }, null, envelope);
                if (!rsp?.data) return;

                const currentEntities = Object.values(this.formDataStore.rawData?.formFormat?.['op-doc-offer-form'] ?? {})[0]?.entities ?? null;
                const currentJson = currentEntities ? JSON.stringify(currentEntities) : null;

                const options = [];
                for (let index = 0; index < rsp.data.length; index++) {
                    const row = rsp.data[index];
                    let desc = null;
                    if (typeof row.description === 'string') {
                        try { desc = JSON.parse(row.description); } catch (e) { desc = null; }
                    } else if (typeof row.description === 'object' && row.description !== null) {
                        desc = row.description;
                    }

                    const beforeEntities = Object.values(desc?.before?.formFormat?.['op-doc-offer-form'] ?? {})[0]?.entities ?? null;
                    const afterEntities = Object.values(desc?.after?.formFormat?.['op-doc-offer-form'] ?? {})[0]?.entities ?? null;

                    if (!beforeEntities && !afterEntities) continue;

                    const snapshot = afterEntities || beforeEntities;
                    const snapshotJson = snapshot ? JSON.stringify(snapshot) : null;
                    if (snapshotJson && currentJson && snapshotJson === currentJson) {
                        continue;
                    }

                    const label = row.title || desc?.desc || `Versiyon ${index + 1}`;
                    const formattedLabel = `${label} — ${dayjs(row.created_at).format('DD.MM.YYYY HH:mm')}`;

                    options.push({
                        id       : String(row.qnid ?? index),
                        label    : formattedLabel,
                        entities : snapshot,
                    });
                }

                this.offerVersionOptions = options;
            },

            versionSelect(versionId){
                this.selectedVersionId = versionId;
                const selected = this.offerVersionOptions.find(item => item.id === versionId);
                this.previewEntities = selected ? selected.entities : null;
            },

            async submitForm(formData){
                this.formData = formData;
                
                // Existing offers may only be edited in draft/created/revision states.
                if(!this.editable){
                    Swal.fire({
                        text : 'Sadece Revizyon Talebi Durumundaki Teklifler Düzenlenebilir.',
                        icon : 'warning',
                        showCloseButton : true,
                        showConfirmButton : false,
                    }) ;
                    return;
                }


                this.navigationStore.toggle(true);
                this.formData.typeKey = 'op-doc-offer';
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
                            this.$router.push({ name: 'OList' });
                        });
                    }, 300);

                }else{
                    console.log(rsp);
                    this.navigationStore.toggle(false);
                    this.plib.toast(this.Swal,'info','Eksik Alanları Doldurmalısınız..',() => {});
                }
            },

        }
    }

</script>

<template>
    <!-- Admin or supplier view mode: read-only summary -->
    <template v-if="loadForm && (authStore.typeKey !== 'op-pert-reseller' || isViewMode)">
        <OfferSummary
            :editable = "editable"
            :onEditable="() => { 
                isViewMode = false;
            }"
            :entities="Object.values(formDataStore.rawData?.formFormat?.['op-doc-offer-form'] ?? {})[0]?.entities ?? {}"
            :previewEntities="previewEntities"
            :versionOptions="offerVersionOptions"
            :selectedVersionId="selectedVersionId"
            :onVersionChange="versionSelect"
            :document="formDataStore.rawData?.document ?? {}"
            :statusList="statusList"
            :onRequestRevision="requestRevision"
            :onApprove="approveOffer"
            :onReject="rejectOffer"
            :onDownloadPdf="downloadPdf"
            :hideActions="isViewMode"
        />
        <div class="mt-6" v-if="id">
            <OfferLogTimeline :documentQnid="id" />
        </div>
    </template>

    <!-- Supplier: input form -->
    <div v-else style="padding-bottom: 100px;">
        <div class="row mb-6">
            <div class="col-12">
                <div class="card">
                    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
                        <div class="d-flex flex-wrap gap-3">
                            <button class="btn btn-secondary" :disabled="!id" @click="downloadPdf">PDF Olarak İndir</button>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>

        <div class="row w-100">
            <div :class="{
                'col-8' : statusList.length > 0,
                'col-12': statusList.length === 0
                }">
                <Form formtypes="op-doc-offer-form" v-if="loadForm" :savecallback="submitForm" />
            </div>
            <!-- Right: status history -->
            <div class="col-lg-4" v-if="statusList.length > 0">
                <div class="card os-card mt-10" style="position:sticky;top:80px;">
                    <div class="card-header os-card-header">
                        <span class="os-section-title">Teklif Durum Geçmişi</span>
                    </div>
                    <div class="card-body p-4">
                        <template v-for="(item, index) in [...statusList].reverse()" :key="index">
                            <div class="os-status-item">
                                <div class="os-status-dot" :class="index === 0 ? 'os-status-dot--active' : ''"></div>
                                <div class="os-status-body">
                                    <div class="os-status-title">{{ item.title }}</div>
                                    <div class="os-status-meta">
                                        <span><i class="ki-outline ki-user fs-7 me-1"></i>{{ item.name }}</span>
                                        <span><i class="ki-outline ki-time fs-7 me-1"></i>{{ item.time ? dayjs(item.time).format('DD.MM.YYYY') : '—' }}</span>
                                    </div>
                                    <div class="os-status-note" v-if="item.note">{{ item.note }}</div>
                                </div>
                            </div>
                            <div v-if="index < statusList.length - 1" class="os-status-connector"></div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-10" v-if="id">
            <OfferLogTimeline :documentQnid="id" />
        </div>
    </div>
</template>

<style scoped>
.os-card {
    border: 1px solid #dde3ee !important;
    box-shadow: 0 2px 8px rgba(15,40,90,.07) !important;
    border-radius: 12px !important;
    overflow: hidden;
}
.os-card-header {
    background: #fff;
    padding: 12px 20px !important;
    border-bottom: 1px solid #eef0f4;
    min-height: unset !important;
}
.os-section-title {
    font-size: .82rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #154b91;
}

/* Fields */
.os-field-label {
    font-size: .78rem;
    color: #99a1b7;
    margin-bottom: 3px;
    font-weight: 500;
}
.os-field-value {
    font-size: .95rem;
    font-weight: 600;
    color: #1e2a3b;
    word-break: break-word;
}



/* Current status badge */
.os-current-badge {
    display: inline-flex;
    align-items: center;
    height: 28px;
    padding: 0 12px;
    border-radius: 20px;
    font-size: .78rem;
    font-weight: 700;
    border: 1px solid transparent;
}
.os-current-badge.badge-success  { background: rgba(5,150,105,.1);  color: #059669; border-color: rgba(5,150,105,.25); }
.os-current-badge.badge-danger   { background: rgba(220,38,38,.08); color: #dc2626; border-color: rgba(220,38,38,.2); }
.os-current-badge.badge-warning  { background: rgba(217,119,6,.1);  color: #d97706; border-color: rgba(217,119,6,.25); }
.os-current-badge.badge-secondary{ background: #f4f6fa;             color: #4b5675; border-color: #e2e8f0; }

/* Action buttons */
.os-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 38px;
    padding: 0 16px;
    border-radius: 8px;
    font-size: .875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: background .15s, color .15s;
}
.os-btn-ghost    { background: #f4f6fa; color: #4b5675; border: 1px solid #e2e8f0; }
.os-btn-ghost:hover { background: #e8edf5; }
.os-btn-warning  { background: rgba(217,119,6,.1); color: #d97706; border: 1px solid rgba(217,119,6,.25); }
.os-btn-warning:hover { background: rgba(217,119,6,.2); }
.os-btn-success  { background: rgba(5,150,105,.1); color: #059669; border: 1px solid rgba(5,150,105,.25); }
.os-btn-success:hover { background: rgba(5,150,105,.2); }
.os-btn-danger   { background: rgba(220,38,38,.08); color: #dc2626; border: 1px solid rgba(220,38,38,.2); }
.os-btn-danger:hover { background: rgba(220,38,38,.16); }

/* Status timeline */
.os-status-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.os-status-dot {
    flex-shrink: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #dde3ee;
    border: 2px solid #c5cdd8;
    margin-top: 5px;
}
.os-status-dot--active {
    background: #154b91;
    border-color: #154b91;
}
.os-status-body { flex: 1; padding-bottom: 4px; }
.os-status-title { font-size: .9rem; font-weight: 600; color: #1e2a3b; }
.os-status-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: .78rem;
    color: #99a1b7;
    margin-top: 3px;
}
.os-status-note {
    margin-top: 4px;
    font-size: .82rem;
    color: #4b5675;
    background: #f8fafc;
    border-radius: 6px;
    padding: 4px 8px;
}
.os-status-connector {
    width: 2px;
    height: 16px;
    background: #eef0f4;
    margin-left: 5px;
    margin-bottom: 2px;
}
.status-spinner-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.8rem;
  height: 1.8rem;
  padding: 0;
  border-radius: 999px;
  background-color: var(--bs-primary, #009ef7);
  color: #fff;
  box-sizing: border-box;
}

.loading-spinner {
  display: inline-block;
  width: 1rem;
  height: 1rem;
  border: 2px solid rgba(255,255,255,0.45);
  border-top-color: rgba(255,255,255,0.95);
  border-radius: 50%;
  animation: spinner-spin 0.8s linear infinite;
  box-sizing: border-box;
}

@keyframes spinner-spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.collapsible-card {
  position: relative;
  overflow: hidden;
}

.collapse-toggle {
  position: absolute;
  opacity: 0;
  pointer-events: none;
  width: 0;
  height: 0;
}

.collapsible-header {
  padding: 0;
  border-bottom: 1px solid rgba(0,0,0,.08);
}

.collapse-button {
  width: 100%;
  padding: 1rem 1.25rem;
  cursor: pointer;
  user-select: none;
}

.collapse-button:hover {
  background-color: rgba(0,0,0,.03);
}

.collapse-icon {
  transition: transform .35s ease;
  transform-origin: center;
}

.collapse-icon img {
  display: block;
  width: 24px;
  height: 24px;
}

.collapse-content {
  max-height: 0;
  overflow: hidden;
  transition: max-height .35s ease, padding .35s ease;
  padding: 0 1.25rem;
}

.collapse-toggle:checked + .collapsible-header + .collapse-content {
  max-height: 480px;
  padding: 1rem 1.25rem 1.25rem;
}

.collapse-toggle:checked + .collapsible-header .collapse-icon {
  transform: rotate(45deg);
}

.request-details-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

@media (max-width: 767px) {
  .request-details-grid {
    grid-template-columns: 1fr;
  }
}

.request-detail-row {
  background-color: #f8f9fa;
  border: 1px solid rgba(0,0,0,.08);
  border-radius: .85rem;
  padding: 1rem;
}

.detail-label {
  font-size: .82rem;
  color: #6c757d;
  font-weight: 600;
  margin-bottom: .35rem;
}

.detail-value {
  font-size: .95rem;
  color: #212529;
  word-break: break-word;
}
</style>
