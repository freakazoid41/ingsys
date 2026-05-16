
<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import VMasker  from 'vanilla-masker';
    import { Datepicker } from 'vanillajs-datepicker';
    import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';


    export default {
        breadcrumbs: {
            list: [  { title: 'Teklifler', path: '/coalpanel/offer' } ],
            title: 'Teklifler'
        },
        setup() {
            Object.assign(Datepicker.locales, tr);
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                useAuthStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                Datepicker
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTestTable();
            
            


            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },  
        data() {
            return {
                plib : new Plib(),
                navigationStore : useNavigationStore(),
                authStore    : useAuthStore(),
            }
        },
        methods: {
            searchTable(){
                this.table.setFilter(
                    [{
                        key   : 'all',
                        type  : '=',
                        value : document.getElementById('mainSearch').value.trim()
                    }]
                );
            },
            resetSearch(){
                document.getElementById('mainSearch').value = '';
                this.table.setFilter([]);
            },
            exportTable(){
                this.plib.openTab('POST', '/api/v1/export/offers', this.table.currentFilter,'_blank');
            },
            openStatusChangeModal(rowData){
                const currentStatus = String(rowData.status ?? '').split('**');
                const currentNote = currentStatus[2] || '';

                Swal.fire({
                    showConfirmButton : false,
                    showCloseButton : true,
                    html : `<small class="mb-5 mt-5">Listeden İstediğiniz Durumu Seçip Güncelleyebilirsiniz</small>
                            <div class="row m-5 justify-content-center">
                                <button class="btn btn-warning mb-5 doc-status" data-key="doc_trans_offer_review"    type="button">İnceleniyor</button>
                                <button class="btn btn-info mb-5 doc-status" data-key="doc_trans_offer_revision"  type="button">Revizyon Talebi</button>
                                <button class="btn btn-danger mb-5 doc-status"  data-key="doc_trans_offer_rejected"  type="button">Reddedildi</button>
                                <button class="btn btn-success mb-5 doc-status"  data-key="doc_trans_offer_approved"  type="button">Kabul Edildi</button>
                            </div>`,
                    willOpen : async () => {
                        Swal.showValidationMessage(currentNote);
                        document.querySelectorAll('.doc-status').forEach(btn => {
                            btn.addEventListener('click', e => {
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
                                            envelope.append('id',rowData.id);
                                            envelope.append('op_key',e.target.dataset.key);
                                            envelope.append('note',note);
                                            const rsp = await this.plib.request({
                                                url      : '/api/v1/trans/set-status',
                                                method   : 'POST',
                                            },null,envelope);
                                            if(rsp.success){
                                                this.table.updateRow(rowData.id,{status : e.target.dataset.key+'**'+rsp.data+'**'+note});
                                                this.plib.toast(this.Swal,'success','İşlem Tamamlandı');
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
                            });
                        });
                    }
                });
            },
            formatOfferCard(rowData,columnData){
                const data = JSON.parse(rowData.addional ?? '{}');
                let request = '-';
                for (const key in data) {
                    if (data[key]?.Key === 'title'){
                        request = data[key]?.Value ?? '-';
                        break;
                    }
                }

                const offerTypeLabel = String(rowData.offer_type ?? '').split('**')[1] || rowData.offer_type || '-';
                const statusParts = String(rowData.status ?? '').split('**');
                const statusLabel = statusParts[1] || 'Teklif Gönderildi';

                const cardItems = [
                    { label: 'Belge Tarihi', value: rowData.date ?? '-' },
                    { label: 'Teklif Kodu', value: rowData.req_no ?? '-' },
                    { label: 'Talep', value: request },
                    { label: 'Cinsi', value: rowData.coal_type ?? '-' },
                    { label: 'Birim Fiyat', value: VMasker.toMoney(String(rowData.unit_price ?? ''), {precision: 2, separator: ',', delimiter: '.', unit: '', zeroCents: false}) }
                ];

                const card = document.createElement('div');
                card.classList.add('offer-card');

                const header = document.createElement('div');
                header.classList.add('offer-card__header');

                const headerLeft = document.createElement('div');
                const badge = document.createElement('div');
                badge.classList.add('offer-card__badge');
                badge.textContent = rowData.target_type ?? '-';

                const title = document.createElement('h4');
                title.classList.add('offer-card__title');
                title.textContent = rowData.clititle ?? '-';

                const subtitle = document.createElement('p');
                subtitle.classList.add('offer-card__subtitle');
                subtitle.textContent = offerTypeLabel;

                headerLeft.appendChild(badge);
                headerLeft.appendChild(title);
                headerLeft.appendChild(subtitle);

                const statusBadge = document.createElement('span');
                statusBadge.classList.add('offer-card__status');
                statusBadge.textContent = statusLabel;

                if (this.authStore.permissions?.includes('per-05-02')) {
                    statusBadge.classList.add('offer-card__status--clickable');
                    statusBadge.title = 'Durum değiştir';
                    statusBadge.onclick = () => this.openStatusChangeModal(rowData);
                }

                header.appendChild(headerLeft);
                header.appendChild(statusBadge);

                const body = document.createElement('div');
                body.classList.add('offer-card__body');

                cardItems.forEach(item => {
                    const row = document.createElement('div');
                    row.classList.add('offer-card__row');

                    const label = document.createElement('span');
                    label.classList.add('offer-card__label');
                    label.textContent = item.label;

                    const value = document.createElement('span');
                    value.classList.add('offer-card__value');
                    value.textContent = item.value;

                    row.appendChild(label);
                    row.appendChild(value);
                    body.appendChild(row);
                });

                const footer = document.createElement('div');
                footer.classList.add('offer-card__footer');

                if (this.authStore.typeKey === 'op-pert-reseller') {
                    const detailBtn = document.createElement('button');
                    detailBtn.classList.add('offer-card__action-btn');
                    detailBtn.type = 'button';
                    detailBtn.innerHTML = '<i class="ki-outline ki-eye"></i> Detay';
                    detailBtn.onclick = () => {
                        this.navigationStore.setRouteParams({ offer_type: rowData.offer_type });
                        this.$router.push({ name: 'OForm', params: { id: rowData.id }, query: { view: '1' } });
                    };
                    footer.appendChild(detailBtn);
                }

                const editBtn = document.createElement('button');
                editBtn.classList.add('offer-card__action-btn');
                editBtn.type = 'button';
                editBtn.innerHTML = '<i class="ki-outline ki-pencil"></i> Düzenle';
                editBtn.onclick = () => {
                    let key = rowData.status?.split('**');
                    console.log(key)
                    if (key == undefined) key = ['doc_trans_created'];
                    if (key?.[0] != 'doc_trans_offer_revision' && key?.[0] != 'doc_trans_created' && key?.[0] != 'doc_trans_offer_draft' && !this.authStore.permissions?.includes('per-05-02')) {
                        Swal.fire({
                            text : 'Sadece Revizyon Talebi Durumundaki Teklifler Düzenlenebilir.',
                            icon : 'warning',
                            showCloseButton : true,
                            showConfirmButton : false,
                        });
                        return;
                    }

                    this.navigationStore.setRouteParams({
                        offer_type   : rowData.offer_type,
                        offer_status : key?.[0],
                    });
                    this.$router.push({ name: 'OForm' , params: { id: rowData.id }});
                };
                footer.appendChild(editBtn);

                if (this.authStore.permissions?.includes('per-08-02')) {
                    const removeBtn = document.createElement('button');
                    removeBtn.classList.add('offer-card__action-btn','offer-card__action-btn--danger');
                    removeBtn.type = 'button';
                    removeBtn.innerHTML = '<i class="ki-outline ki-trash"></i> Sil';
                    removeBtn.onclick = async () => {
                        const confirm = await Swal.fire({
                            icon: 'warning',
                            title: 'Teklifi Sil',
                            text: 'Bu işlem oluşturulan teklifi tamamen silecek. İşlemi gerçekleştirmek istediğinize emin misiniz?',
                            showCancelButton: true,
                            confirmButtonText: 'Evet, sil',
                            cancelButtonText: 'Vazgeç',
                            reverseButtons: true,
                        });
                        if (!confirm.isConfirmed) return;

                        const deleteId = rowData.qnid ?? rowData.id;
                        this.navigationStore.toggle(true);
                        const rsp = await this.plib.request({
                            url      : '/api/v1/document/' + deleteId,
                            method   : 'DELETE',
                        }, null);

                        if (rsp?.success !== false) {
                            this.table.deleteRow(deleteId);
                        } else {
                            this.plib.toast(this.Swal,'error',rsp.msg);
                        }
                        setTimeout(() => {
                            this.navigationStore.toggle(false);
                        }, 300);
                    };
                    footer.appendChild(removeBtn);
                }

                card.appendChild(header);
                card.appendChild(body);
                card.appendChild(footer);
                return card;
            },
            buildTestTable(){
                
                //set headers
                const headers = [
                    {
                        title : ' ',
                        key   : 'clititle',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => this.formatOfferCard(rowData,columnData)
                    },
                    {
                        title : 'Cari',
                        key   : 'clititle',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Santral',
                        key   : 'target_type',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Teklif tipi',
                        key   : 'offer_type',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            return columnData.split('**')[1];
                        }
                    },{
                        title : 'Belge Tarihi',
                        key   : 'date',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{

                        title : 'Teklif Kodu',
                        key   : 'req_no',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{

                        title : 'Talep',
                        key   : 'addional',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const data = JSON.parse(columnData ?? '{}');
                            for (const key in data) {
                                if (data[key]?.Key === 'title'){
                                    const spn     = document.createElement('span');
                                    spn.innerHTML = data[key]?.Value ?? '-';
                                    const viewBtn = document.createElement('button');
                                    viewBtn.classList.add('btn','ms-2','btn-secondary','action-icon-btn','me-1');
                                    viewBtn.title = 'Detay';
                                    viewBtn.innerHTML = '<i class="ki-outline ki-eye fs-2"></i>';
                                    viewBtn.onclick = () => {
                                        this.$router.push({ name: 'RequestForm', params: { id: rowData.request_id } });
                                    };

                                    
                                    spn.appendChild(viewBtn);



                                    return spn;
                                } 
                            }
                            return '-';
                        }
                    },{

                        title : 'Cinsi',
                        key   : 'coal_type',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => columnData ?? ''
                    },{

                        title : 'Birim Fiyat',
                        key   : 'unit_price',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            return VMasker.toMoney(String(columnData), {precision: 2, separator: ',', delimiter: '.', unit: '', zeroCents: false});
                        }
                    },{
                        title : 'Güncel Durum',
                        key   : 'status',
                        order : true,
                        width : '250px',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const key = rowData.status?.split('**');
                            const btn = document.createElement('button');
                            btn.type  = 'button';
                            btn.classList.add('status-pill');

                            switch(key?.[0]){
                                case 'doc_trans_offer_approved':
                                    btn.classList.add('status-pill--success');
                                    break;
                                case 'doc_trans_offer_rejected':
                                    btn.classList.add('status-pill--danger');
                                    break;
                                case 'doc_trans_offer_revision':
                                case 'doc_trans_offer_revised':
                                case 'doc_trans_offer_review':
                                    btn.classList.add('status-pill--warning');
                                    break;
                                default:
                                    btn.classList.add('status-pill--secondary');
                                    break;
                            }
                            btn.textContent = key?.[1] ?? 'Teklif Gönderildi';
                            //here we are looking request form permissions
                            btn.onclick = () => this.authStore.permissions?.includes('per-05-02') ? this.openStatusChangeModal(rowData) : {};

                            
                            return btn;
                        }
                    },{
                        title : '',
                        key   : '#',
                        order : false,
                        //colAlign : 'center',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('d-flex','justify-content-center','align-items-center','flex-row');

                            // View button (eye) — always visible for suppliers
                            const viewBtn = document.createElement('button');
                            viewBtn.classList.add('btn','btn-secondary','action-icon-btn','me-1');
                            viewBtn.title = 'Detay';
                            viewBtn.innerHTML = '<i class="ki-outline ki-eye fs-2"></i>';
                            viewBtn.onclick = () => {
                                this.navigationStore.setRouteParams({ offer_type: rowData.offer_type });
                                this.$router.push({ name: 'OForm', params: { id: rowData.id }, query: { view: '1' } });
                            };
                            span.appendChild(viewBtn);

                            let btn = document.createElement('button');
                            btn.classList.add('btn','btn-secondary','action-icon-btn','me-1');
                            btn.title = 'Düzenle';
                            btn.innerHTML = '<i class="ki-outline ki-pencil fs-2"></i>';
                            btn.onclick   = () =>{
                                let key = rowData.status?.split('**');
                                if(key == undefined) key = ['doc_trans_created'];

                                //here if offer is draft or revision request then allow to edit
                                if(key?.[0] != 'doc_trans_offer_revision' && key?.[0] != 'doc_trans_created' && key?.[0] != 'doc_trans_offer_draft' && !this.authStore.permissions?.includes('per-05-02')) {
                                    Swal.fire({
                                        text : 'Sadece Revizyon Talebi Durumundaki Teklifler Düzenlenebilir.',
                                        icon : 'warning',
                                        showCloseButton : true,
                                        showConfirmButton : false,
                                    })
                                    return;
                                }

                                this.navigationStore.setRouteParams({
                                    offer_type   : rowData.offer_type,
                                    offer_status : key?.[0],
                                });
                                this.$router.push({ name: 'OForm' , params: { id: rowData.id }});
                            };
                            span.appendChild(btn);

                          
                            //send offer to stsyem
                            /*if(this.authStore.typeKey == 'op-pert-reseller'){

                            
                                btn = document.createElement('button');
                                btn.classList.add('btn','btn-secondary','me-1','d-flex','justify-content-center','align-items-center','flex-row');
                                btn.innerHTML = '<i class="ki-outline ki-send text-gray-900 fs-2"></i>';
                                btn.onclick   =  async () => {
                                    Swal.fire({
                                        confirmButtonText : 'Gönder..',
                                        showCloseButton : true,
                                        title : 'Teklif Gönderilecektir. Onaylıyor musunuz ?',
                                        allowOutsideClick: () => !Swal.isLoading(),
                                        preConfirm : async () => {
                                            try {
                                                const envelope = new FormData();
                                                envelope.append('id',rowData.id);
                                                envelope.append('op_key','doc_trans_offer_sended');
                                                envelope.append('note','Müşteri Teklif Gönderdi.');
                                                const rsp = await this.plib.request({
                                                    url      : '/api/v1/trans/set-status',
                                                    method   : 'POST',
                                                },null,envelope);
                                                if(rsp.success){
                                                    this.table.updateRow(rowData.id,{status : 'doc_trans_offer_sended**'+rsp.data+'**Müşteri Teklif Gönderdi.'});
                                                    this.plib.toast(this.Swal,'success','İşlem Tamamlandı');
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
                                    
                                };
                                span.appendChild(btn);
                            }*/
                            if (this.authStore.permissions?.includes('per-08-02')) {
                                const del = document.createElement('button');
                                del.classList.add('btn','btn-secondary','action-icon-btn','action-icon-btn--danger','me-1');
                                del.title = 'Sil';
                                del.innerHTML = '<i class="ki-outline ki-trash fs-2"></i>';
                                del.onclick = async () => {
                                    const confirm = await Swal.fire({
                                        icon: 'warning',
                                        title: 'Teklifi Sil',
                                        text: 'Bu işlem oluşturulan teklifi tamamen silecektir. İşlemi gerçekleştirmek istediğinize emin misiniz?',
                                        showCancelButton: true,
                                        confirmButtonText: 'Evet, sil',
                                        cancelButtonText: 'Vazgeç',
                                        reverseButtons: true,
                                    });
                                    if (!confirm.isConfirmed) return;

                                    const deleteId = rowData.qnid ?? rowData.id;
                                    this.navigationStore.toggle(true);
                                    const rsp = await this.plib.request({
                                        url      : '/api/v1/document/' + deleteId,
                                        method   : 'DELETE',
                                    }, null);

                                    if (rsp?.success !== false) {
                                        this.table.deleteRow(deleteId);
                                    } else {
                                        this.plib.toast(this.Swal,'error',rsp.msg);
                                    }
                                    setTimeout(() => {
                                        this.navigationStore.toggle(false);
                                    }, 300);
                                };
                                span.appendChild(del);
                            }

                            return span;
                        }
                    }
                ];
                
                //initiate table
                this.table = new PickleTable({
                    container : '#div_table', //table target div
                    headers   : headers,
                    pageLimit : 10, // -1 for closing pagination
                    height    : '70vh',
                    type      : 'ajax',
                    columnSearch : true, // true - false for opening and closig
                    paginationType : 'number',// scroll - number (number for default)
                    ajax:{
                        url:'/api/v1/table/documents',
                        data:{
                            //order:{},
                        }
                    },
                    initialFilter : [
                        {
                            key   : 'form-type',
                            type  : '=',
                            value : 'op-doc-offer-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-offer'
                        }
                    ],
                    nextPageIcon : '<i class="ki-outline ki-arrow-right"></i>',
                    prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter:(elm,data)=>{
                        //console.log(elm,data);
                        //modify row element
                        //elm.style.backgroundColor = 'yellow';
                        //modify data
                        JSON.parse(data.main_attr).forEach(element => {
                            data[element['Key']] = element['Value'];
                            //if(data['cont_name'] == undefined) data['cont_name'] = []
                            //if(element['Key'].includes('cont_name')) data['cont_name'].push(element['Value']);
                        });
                        //data['cont_name'] = (data['cont_name'] ?? []).join(' , ');
                        //data.status = JSON.parse(data.status).OpTitle;
                        return data;
                    },
                });
            },
            giveOffer(){
                //here select offer type
                Swal.fire({
                    title : 'Teklif Türü Seçiniz',
                    showConfirmButton : false,
                    showCloseButton : true,
                    html : `<div class="row m-5 justify-content-center">
                                <button class="btn btn-primary mb-5 offer-type" data-key="op-doc-offer-form**Teklif Formu" type="button">Teklif Formu</button>
                                <button class="btn btn-secondary mb-5 offer-type" data-key="op-doc-offer-file**Dosya Yükleme" type="button">Dosya Yükleme</button>
                            </div>`,
                    willOpen : async () => {
                        document.querySelectorAll('.offer-type').forEach(btn => {
                            btn.addEventListener('click', e => {
                                this.navigationStore.setRouteParams({ 
                                    request_id : 'Bağımsız Teklif' , 
                                    offer_type : e.target.dataset.key,
                                    request :  {
                                        target_type : document.querySelector('input[name="SYS_CODE"]').value
                                    }
                                });
                                this.$router.push({ name: 'OForm'});
                                Swal.close();
                            });
                        });
                    }
                });
            }
        }
    }

</script>
<template>
    <div class="card rlist-card">
        <div class="card-header rlist-header">
            <div class="rlist-search-group">
                <div class="rlist-search-wrap">
                    <i class="ki-duotone ki-magnifier fs-4 rlist-search-icon">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text" id="mainSearch" class="rlist-search-input" placeholder="Teklif ara...">
                </div>
                <button type="button" class="rlist-btn rlist-btn-primary" @click="searchTable">
                    <i class="ki-outline ki-magnifier fs-5"></i> Ara
                </button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="resetSearch">Sıfırla</button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="exportTable">
                    <i class="ki-outline ki-exit-down fs-5"></i> Excel
                </button>
            </div>
            <div class="rlist-toolbar" v-if="authStore.typeKey !== 'op-pert-admin'">
                <button type="button" class="rlist-btn rlist-btn-create" @click="giveOffer">
                    <i class="ki-outline ki-plus fs-5"></i> Teklif Ver
                </button>
                <router-link :to="{ name: 'RequestList' }" class="rlist-btn rlist-btn-ghost">
                    Talep'e Teklif Ver
                </router-link>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="div_table"></div>
        </div>
    </div>
</template>

<style scoped>

.rlist-card {
    border: 1px solid #dde3ee !important;
    box-shadow: 0 2px 8px rgba(15,40,90,.07) !important;
    border-radius: 12px !important;
    overflow: hidden;
}
.rlist-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 16px 20px !important;
    border-bottom: 1px solid #eef0f4;
    background: #fff;
    min-height: unset !important;
}
.rlist-search-group {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.rlist-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.rlist-search-icon {
    position: absolute;
    left: 12px;
    color: #99a1b7;
    pointer-events: none;
}
.rlist-search-input {
    height: 40px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0 14px 0 38px;
    font-size: .9rem;
    color: #1e2a3b;
    background: #f8fafc;
    outline: none;
    width: 240px;
    transition: border-color .15s, background .15s, box-shadow .15s;
}
.rlist-search-input:focus {
    border-color: #154b91;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(21,75,145,.08);
}
.rlist-search-input::placeholder { color: #b0bac9; }
.rlist-toolbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rlist-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 40px;
    padding: 0 16px;
    border-radius: 8px;
    font-size: .875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s, color .15s, box-shadow .15s;
    text-decoration: none;
}
.rlist-btn-primary { background: #154b91; color: #fff; }
.rlist-btn-primary:hover { background: #0f3a72; color: #fff; }
.rlist-btn-ghost { background: #f4f6fa; color: #4b5675; border: 1px solid #e2e8f0; }
.rlist-btn-ghost:hover { background: #e8edf5; color: #1e2a3b; }
.rlist-btn-create { background: #154b91; color: #fff !important; }
.rlist-btn-create:hover { background: #0f3a72; color: #fff !important; box-shadow: 0 4px 12px rgba(21,75,145,.25); }

#div_table { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }

:deep(.pickletable table) { width: 100%; min-width: 100%; border-collapse: collapse; table-layout: auto; }
:deep(.pickletable th), :deep(.pickletable td) {
    white-space: nowrap;
    max-width: 320px;
    overflow: hidden;
    text-overflow: ellipsis;
}
:deep(.pickletable thead) { --bs-emphasis-color: rgba(255,255,255,.6); }
:deep(.pickletable thead tr) { background: #154b91 !important; }
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
:deep(.pickletable thead th i) { color: rgba(255,255,255,.6) !important; background: transparent !important; }
:deep(.pickletable thead th input) {
    background: rgba(255,255,255,.1) !important;
    border: 1px solid rgba(255,255,255,.2) !important;
    border-radius: 5px !important;
    color: #fff !important;
    font-size: .78rem !important;
    padding: 4px 8px !important;
    margin-top: 6px !important;
    width: 100% !important;
    outline: none !important;
}
:deep(.pickletable thead th input::placeholder) { color: rgba(255,255,255,.4) !important; }
:deep(.pickletable thead th input:focus) { background: rgba(255,255,255,.18) !important; border-color: rgba(255,255,255,.4) !important; }
:deep(.pickletable tbody tr) { border-bottom: 1px solid #eef0f4 !important; background: #fff !important; transition: background .12s; }
:deep(.pickletable tbody tr:hover) { background: #f7f9fd !important; }
:deep(.pickletable tbody td) {
    padding: 12px 16px !important;
    font-size: .9rem !important;
    color: #2d3748 !important;
    background: transparent !important;
    border: none !important;
    border-right: 1px solid #f0f2f7 !important;
    vertical-align: middle !important;
}
:deep(.pickletable tbody td:last-child) { border-right: none !important; }

/* Status pills */
:deep(.status-pill) {
    display: inline-flex;
    align-items: center;
    height: 26px;
    padding: 0 12px;
    border-radius: 20px;
    font-size: .78rem;
    font-weight: 700;
    border: 1px solid transparent;
    cursor: pointer;
    white-space: nowrap;
}
:deep(.status-pill--success)   { background: rgba(5,150,105,.1);  color: #059669; border-color: rgba(5,150,105,.25); }
:deep(.status-pill--danger)    { background: rgba(220,38,38,.08); color: #dc2626; border-color: rgba(220,38,38,.2); }
:deep(.status-pill--warning)   { background: rgba(217,119,6,.1);  color: #d97706; border-color: rgba(217,119,6,.25); }
:deep(.status-pill--secondary) { background: #f4f6fa;             color: #4b5675; border-color: #e2e8f0; }

:deep(.offer-card) {
    width: 100%;
    border: 1px solid rgba(21, 75, 145, 0.12);
    border-radius: 18px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 10px 24px rgba(15, 40, 90, 0.05);
}

:deep(.offer-card__header) {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 16px 16px 12px;
    background: linear-gradient(135deg, rgba(21, 75, 145, 0.08), rgba(21, 75, 145, 0.02));
}

:deep(.offer-card__badge) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 12px;
    border-radius: 999px;
    background: rgba(21, 75, 145, 0.12);
    color: #154b91;
    font-size: 0.78rem;
    font-weight: 700;
    margin-bottom: 8px;
}

:deep(.offer-card__title) {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #102a43;
}

:deep(.offer-card__subtitle) {
    margin: 4px 0 0;
    color: #5e6e82;
    font-size: 0.86rem;
}

:deep(.offer-card__status) {
    align-self: center;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(34, 197, 94, 0.12);
    color: #166534;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
}

:deep(.offer-card__status--clickable) {
    cursor: pointer;
    transition: background .2s, transform .2s;
}

:deep(.offer-card__status--clickable:hover) {
    background: rgba(21, 75, 145, 0.16);
    transform: translateY(-1px);
}

:deep(.offer-card__body) {
    display: grid;
    gap: 10px;
    padding: 0 16px 14px;
}

:deep(.offer-card__row) {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 10px;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid rgba(228, 232, 240, 0.9);
}

:deep(.offer-card__row:last-child) {
    border-bottom: none;
}

:deep(.offer-card__footer) {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px 16px 16px;
    border-top: 1px solid rgba(228, 232, 240, 0.9);
}

:deep(.offer-card__action-btn) {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 10px;
    border: 1px solid rgba(21, 75, 145, 0.18);
    background: #ffffff;
    color: #154b91;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
}

:deep(.offer-card__action-btn:hover) {
    background: rgba(21, 75, 145, 0.08);
}

:deep(.offer-card__action-btn--danger) {
    border-color: rgba(220, 38, 38, 0.18);
    color: #b91c1c;
}

:deep(.offer-card__action-btn--danger:hover) {
    background: rgba(220, 38, 38, 0.08);
}

:deep(.offer-card__label) {
    color: #6b7280;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

:deep(.offer-card__value) {
    color: #102a43;
    font-size: 0.95rem;
    font-weight: 600;
    text-align: right;
}

/* Action icon buttons */
:deep(.action-icon-btn) {
    background: #f4f6fa !important;
    color: #4b5675 !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 7px !important;
    width: 34px !important;
    height: 34px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}
:deep(.action-icon-btn:hover) { background: #154b91 !important; color: #fff !important; border-color: #154b91 !important; }
:deep(.action-icon-btn > i) { padding-right: 0 !important; }

:deep(.pickletable .divPagination) { padding: 12px 16px !important; border-top: 1px solid #eef0f4; justify-content: flex-end !important; }
:deep(.pickletable .divPagination button) { height: 32px !important; min-width: 32px !important; border-radius: 6px !important; font-size: .82rem !important; font-weight: 600 !important; border: 1px solid #e2e8f0 !important; background: #fff !important; color: #4b5675 !important; }
:deep(.pickletable .divPagination button.current) { background: #154b91 !important; color: #fff !important; border-color: #154b91 !important; }
:deep(.pickletable .divPagination button:hover:not(.current)) { background: #f4f6fa !important; color: #154b91 !important; }


:deep(.pickletable td:first-child),
:deep(.pickletable th:first-child){
    display: none;
}
@media (max-width: 767px) {
    
    .rlist-header { padding: 12px 14px !important; }
    .rlist-search-input { width: 160px; }

    :deep(.pickletable td:first-child),
    :deep(.pickletable th:first-child){
        display: unset;
    }
:deep(.pickletable thead) {
        display: none !important;
    }
    :deep(.pickletable td:not(:first-child)),
    :deep(.pickletable th:not(:first-child)){
      display: none;
    }

    :deep(.pickletable tbody tr) {
        border: unset !important;
        border-bottom: unset !important;
    }

    :deep(.pickletable td:first-child){
        padding: 50px !important;
        width: 100% !important;
    }
}

</style>
