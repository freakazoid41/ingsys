
<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import Swal from 'sweetalert2';
    export default {
        props: {
            requestId: {
                type: String,
                required: true,
            },
            addOfferCallback: {
                type: Function,
                default: null,
            },
        },
        setup() {
            return {
                useNavigationStore,
                useAuthStore,
                PickleTable,
                Plib,
                Swal,
            };
        },
        mounted() {
            this.buildTable();
        },
        data() {
            const uid = Date.now();
            return {
                plib           : new Plib(),
                navigationStore: useNavigationStore(),
                authStore      : useAuthStore(),
                tableId        : `offer-req-tbl-${uid}`,
            };
        },
        methods: {
            buildTable() {
                const headers = [
                    {
                        title : 'Tedarikçi',
                        key   : 'clititle',
                        order : true,
                        type  : 'string',
                    },{
                        title : 'Teklif Tipi',
                        key   : 'offer_type',
                        order : true,
                        type  : 'string',
                        columnFormatter: (elm, rowData, columnData) => columnData?.split('**')?.[1] ?? columnData,
                    },{
                        title : 'Teklif Kodu',
                        key   : 'qnid',
                        order : true,
                        type  : 'string',
                    },{
                        title : 'Tarih',
                        key   : 'date',
                        order : true,
                        type  : 'string',
                    },{
                        title : 'Güncel Durum',
                        key   : 'status',
                        order : true,
                        type  : 'string',
                        columnFormatter: (elm, rowData, columnData) => {
                            const key = rowData.status?.split('**');
                            const btn = document.createElement('button');
                            btn.classList.add('btn', 'd-flex', 'align-items-center');
                            let icon = '<i class="ki-outline ki-timer fs-2 me-2"></i>';
                            switch (key?.[0]) {
                                case 'doc_trans_offer_approved':
                                    icon = '<i class="ki-outline ki-check fs-2 me-2"></i>';
                                    btn.classList.add('btn-success');
                                    break;
                                case 'doc_trans_offer_rejected':
                                    icon = '<i class="ki-outline ki-cross-circle fs-2 me-2"></i>';
                                    btn.classList.add('btn-danger');
                                    break;
                                case 'doc_trans_offer_revision':
                                case 'doc_trans_offer_revised':
                                case 'doc_trans_offer_review':
                                    btn.classList.add('btn-warning');
                                    break;
                                case 'doc_trans_offer_sended':
                                default:
                                    if (key?.[1]) key[1] = 'Teklif Gönderildi';
                                    btn.classList.add('btn-default');
                                    break;
                            }
                            btn.innerHTML = icon + ' ' + (key?.[1] ?? 'Teklif Gönderildi');
                            btn.type = 'button';
                            btn.onclick = this.authStore.permissions?.includes('per-05-02') ? () => {
                                Swal.fire({
                                    showConfirmButton: false,
                                    showCloseButton: true,
                                    html: `<small class="mb-5 mt-5">Listeden İstediğiniz Durumu Seçip Güncelleyebilirsiniz</small>
                                           <div class="row m-5 justify-content-center">
                                               <button class="btn btn-warning mb-5 doc-status" data-key="doc_trans_offer_review" type="button">İnceleniyor</button>
                                               <button class="btn btn-info mb-5 doc-status" data-key="doc_trans_offer_revision" type="button">Revizyon Talebi</button>
                                               <button class="btn btn-danger mb-5 doc-status" data-key="doc_trans_offer_rejected" type="button">Reddedildi</button>
                                               <button class="btn btn-success mb-5 doc-status" data-key="doc_trans_offer_accepted" type="button">Kabul Edildi</button>
                                           </div>`,
                                    willOpen: async () => {
                                        Swal.showValidationMessage(key?.[2]);
                                        document.querySelectorAll('.doc-status').forEach(statusBtn => {
                                            statusBtn.addEventListener('click', e => {
                                                Swal.fire({
                                                    confirmButtonText: 'Kaydet..',
                                                    showCloseButton: true,
                                                    html: `<small class="mb-5">Durum Notu Giriniz (Boş Olabilir)</small>
                                                           <div class="row m-5 justify-content-center">
                                                               <div class="col-12">
                                                                   <textarea class="form-control" id="statusNoteArea" rows="3" placeholder="..."></textarea>
                                                               </div>
                                                           </div>`,
                                                    allowOutsideClick: () => !Swal.isLoading(),
                                                    preConfirm: async () => {
                                                        try {
                                                            const note = document.getElementById('statusNoteArea').value.trim();
                                                            const envelope = new FormData();
                                                            envelope.append('id', rowData.id);
                                                            envelope.append('op_key', e.target.dataset.key);
                                                            envelope.append('note', note);
                                                            const rsp = await this.plib.request({ url: '/api/v1/trans/set-status', method: 'POST' }, null, envelope);
                                                            if (rsp.success) {
                                                                this.table.updateRow(rowData.id, { status: e.target.dataset.key + '**' + rsp.data + '**' + note });
                                                                this.plib.toast(this.Swal, 'success', 'İşlem Tamamlandı');
                                                            } else {
                                                                Swal.showValidationMessage(rsp.msg);
                                                            }
                                                            return rsp.success;
                                                        } catch (error) {
                                                            Swal.showValidationMessage(`Request failed: ${error}`);
                                                        }
                                                    },
                                                });
                                            });
                                        });
                                    },
                                });
                            } : () => {};
                            return btn;
                        },
                    },{
                        title : '#',
                        key   : 'id',
                        order : false,
                        type  : 'string',
                        columnFormatter: (elm, rowData, columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('d-flex', 'align-items-center', 'gap-1');
                            const editBtn = document.createElement('button');
                            editBtn.classList.add('btn', 'btn-secondary', 'action-icon-btn');
                            editBtn.title = 'Düzenle';
                            editBtn.innerHTML = '<i class="ki-outline ki-pencil fs-2"></i>';
                            editBtn.onclick = () => {
                                const key = rowData.status?.split('**');
                                const statusKey = key?.[0] ?? 'doc_trans_offer_sended';
                                const editableStatuses = ['doc_trans_offer_revision', 'doc_trans_created', 'doc_trans_offer_draft'];
                                if (!editableStatuses.includes(statusKey) && !this.authStore.permissions?.includes('per-05-02')) {
                                    Swal.fire({ text: 'Sadece Revizyon Talebi Durumundaki Teklifler Düzenlenebilir.', icon: 'warning', showCloseButton: true, showConfirmButton: false });
                                    return;
                                }
                                this.navigationStore.setRouteParams({ offer_type: rowData.offer_type });
                                this.$router.push({ name: 'OForm', params: { id: rowData.id } });
                            };
                            span.appendChild(editBtn);
                            return span;
                        },
                    },
                ];

                this.table = new PickleTable({
                    container     : `#${this.tableId}`,
                    headers       : headers,
                    pageLimit     : 10,
                    height        : 'auto',
                    type          : 'ajax',
                    columnSearch  : false,
                    paginationType: 'number',
                    ajax: {
                        url  : '/api/v1/table/documents',
                        data : {},
                    },
                    initialFilter: [
                        { key: 'form-type',   type: '=',    value: 'op-doc-offer-form' },
                        { key: 'type',        type: '=',    value: 'op-doc-offer'      },
                        { key: 'request_id',  type: 'like', value: this.requestId      },
                    ],
                    nextPageIcon: '<i class="ki-outline ki-arrow-right" style="color:inherit"></i>',
                    prevPageIcon: '<i class="ki-outline ki-arrow-left" style="color:inherit"></i>',
                    rowFormatter: (elm, data) => {
                        JSON.parse(data.main_attr).forEach(element => {
                            data[element['Key']] = element['Value'];
                        });
                        return data;
                    },
                });
            },
        },
    };
</script>

<template>
    <div class="card otbl-card">
        <div class="card-header otbl-header">
            <span class="otbl-title">
                <i class="ki-outline ki-book-open fs-4 me-2"></i> Bu Talebe Ait Teklifler
            </span>
            <button
                v-if="addOfferCallback"
                type="button"
                class="otbl-btn otbl-btn-create"
                @click="addOfferCallback"
            >
                <i class="ki-outline ki-plus fs-5"></i> Teklif Ekle
            </button>
        </div>
        <div class="card-body p-0">
            <div :id="tableId"></div>
        </div>
    </div>
</template>

<style scoped>
.otbl-card {
    border: 1px solid #dde3ee !important;
    box-shadow: 0 2px 8px rgba(15,40,90,.07) !important;
    border-radius: 12px !important;
    overflow: hidden;
}
.otbl-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 14px 20px !important;
    border-bottom: 1px solid #eef0f4;
    background: #fff;
    min-height: unset !important;
}
.otbl-title {
    font-size: .95rem;
    font-weight: 700;
    color: #1e2a3b;
    display: flex;
    align-items: center;
}
.otbl-btn {
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
    white-space: nowrap;
    transition: background .15s, color .15s, box-shadow .15s;
}
.otbl-btn-create { background: #154b91; color: #fff !important; }
.otbl-btn-create:hover { background: #0f3a72; color: #fff !important; box-shadow: 0 4px 12px rgba(21,75,145,.25); }

:deep(.pickletable table) { width: 100%; border-collapse: collapse; min-width: 800px; table-layout: auto; }
:deep(.pickletable thead tr) { background: #154b91 !important; }
:deep(.pickletable thead) { --bs-emphasis-color: rgba(255,255,255,.6); }
:deep(.pickletable thead th) {
    background: #154b91 !important;
    color: rgba(255,255,255,.85) !important;
    font-size: .82rem !important;
    font-weight: 600 !important;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding: 13px 16px !important;
    border: none !important;
    border-right: 1px solid rgba(255,255,255,.1) !important;
    white-space: nowrap;
}
:deep(.pickletable thead th:last-child) { border-right: none !important; }
:deep(.pickletable thead th svg),
:deep(.pickletable thead th i),
:deep(.pickletable thead th span),
:deep(.pickletable thead th *) { color: rgba(255,255,255,.85) !important; background: transparent !important; }
:deep(.pickletable tbody tr) { border-bottom: 1px solid #eef0f4 !important; background: #fff !important; transition: background .12s; }
:deep(.pickletable tbody tr:hover) { background: #f7f9fd !important; }
:deep(.pickletable tbody td) {
    padding: 12px 16px !important;
    font-size: 1rem !important;
    color: #2d3748 !important;
    background: transparent !important;
    border: none !important;
    border-right: 1px solid #f0f2f7 !important;
    vertical-align: middle !important;
    white-space: normal !important;
    word-break: break-word;
    max-width: 200px;
}
:deep(.pickletable tbody td:last-child) { border-right: none !important; }

/* Status pills */
:deep(.pickletable .btn-warning) { background: rgba(217,119,6,.1) !important; color: #d97706 !important; border: 1px solid rgba(217,119,6,.25) !important; border-radius: 20px !important; font-size: .85rem !important; padding: 4px 14px !important; font-weight: 600 !important; }
:deep(.pickletable .btn-success) { background: rgba(5,150,105,.1) !important; color: #059669 !important; border: 1px solid rgba(5,150,105,.25) !important; border-radius: 20px !important; font-size: .85rem !important; padding: 4px 14px !important; font-weight: 600 !important; }
:deep(.pickletable .btn-danger)  { background: rgba(220,38,38,.08) !important; color: #dc2626 !important; border: 1px solid rgba(220,38,38,.2) !important; border-radius: 20px !important; font-size: .85rem !important; padding: 4px 14px !important; font-weight: 600 !important; }
:deep(.pickletable .btn-default) { background: #f4f6fa !important; color: #6b7280 !important; border: 1px solid #e2e8f0 !important; border-radius: 20px !important; font-size: .85rem !important; padding: 4px 14px !important; font-weight: 600 !important; }

/* Action icon buttons */
:deep(.pickletable .action-icon-btn > i) { padding-right: 0 !important; }
:deep(.pickletable .btn-secondary) { background: #f4f6fa !important; color: #4b5675 !important; border: 1px solid #e2e8f0 !important; border-radius: 7px !important; width: 34px !important; height: 34px !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; }
:deep(.pickletable .btn-secondary:hover) { background: #154b91 !important; color: #fff !important; border-color: #154b91 !important; }

/* Pagination */
:deep(.pickletable .divPagination) { padding: 12px 16px !important; border-top: 1px solid #eef0f4; justify-content: flex-end !important; }
:deep(.pickletable .divPagination button) { height: 32px !important; min-width: 32px !important; border-radius: 6px !important; font-size: .82rem !important; font-weight: 600 !important; border: 1px solid #e2e8f0 !important; background: #fff !important; color: #4b5675 !important; }
:deep(.pickletable .divPagination button.current) { background: #154b91 !important; color: #fff !important; border-color: #154b91 !important; }
:deep(.pickletable .divPagination button:hover:not(.current)) { background: #f4f6fa !important; color: #154b91 !important; }
</style>
