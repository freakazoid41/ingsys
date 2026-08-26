
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
    import dayjs from 'dayjs';

    export default {
        breadcrumbs: {
            list: [ { title: 'Talepler', path: '/coalpanel/requests' } ],
            title: 'Talepler'
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
                this.handleResponsiveTable();
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
            handleResponsiveTable(){
                const container = document.getElementById('div_table');
                if(!container) return;
                
                const table = container.querySelector('.pickletable table');
                if(!table) return;
                
                const updateResponsive = () => {
                    const isMobile = window.innerWidth < 768;
                    const tableWrapper = container.closest('#div_table');
                    
                    if(isMobile){
                        tableWrapper.style.overflowX = 'auto';
                        table.style.minWidth = '100%';
                        table.style.width = 'auto';
                    } else {
                        tableWrapper.style.overflowX = 'visible';
                        table.style.minWidth = '100%';
                        table.style.width = '100%';
                    }
                };
                
                updateResponsive();
                window.addEventListener('resize', updateResponsive);
            },
            searchTable(){
                this.table.setFilter(
                    [{
                        key   : 'all', // column key
                        type  : '=', // filtering type ('like','<','>')
                        value : document.getElementById('mainSearch').value.trim()//wanted column value
                    }]
                );
            },
            resetSearch(){
                document.getElementById('mainSearch').value = '';
                this.table.setFilter([]);
            },
            exportTable(){
                this.plib.openTab('POST', '/api/v1/export/requests', this.table.currentFilter,'_blank');
            },
            toggleExpired(){
                
                this.showExpired = document.getElementById('showExpiredSwitch').checked;

                const filter = [];
                if(this.showExpired){
                    filter.push({
                        key   : 'showExpired', // column key
                        type  : '=', // filtering type ('like','<','>')
                        value : 'true'//wanted column value
                    })
                }else{
                    filter.push({
                        key   : 'showExpired', // column key
                        type  : '=', // filtering type ('like','<','>')
                        value : 'false'//wanted column value
                    })
                }
                this.table.setFilter(filter);

            },
            formatRequestCard(rowData,columnData){
                const isMobile = window.innerWidth < 768;
                if(!isMobile) return columnData ?? '-';

                const statusParts = String(rowData.status ?? '').split('**');
                const statusLabel = statusParts[1] || 'Bekleniyor..';
                const isAdmin = this.authStore.permissions?.includes('per-05-02');
                const isReseller = this.authStore.typeKey === 'op-pert-reseller';

                const card = document.createElement('div');
                card.classList.add('request-card');

                const header = document.createElement('div');
                header.classList.add('request-card__header');

                const headerLeft = document.createElement('div');
                const badge = document.createElement('div');
                badge.classList.add('request-card__badge');
                badge.textContent = rowData.target_type ?? '-';

                const title = document.createElement('h4');
                title.classList.add('request-card__title');
                title.textContent = rowData.title ?? '-';

                const subtitle = document.createElement('p');
                subtitle.classList.add('request-card__subtitle');
                subtitle.textContent = `${rowData.req_no ?? '-'} · ${rowData.order_radius ?? '-'}`;

                headerLeft.appendChild(badge);
                headerLeft.appendChild(title);
                headerLeft.appendChild(subtitle);

                const status = document.createElement('span');
                status.classList.add('request-card__status');
                status.textContent = statusLabel;
                if(isAdmin){
                    status.classList.add('request-card__status--clickable');
                    status.title = 'Durum değiştir';
                    status.onclick = () => {
                        Swal.fire({
                            showConfirmButton : false,
                            showCloseButton : true,
                            html : `<small class="mb-5 mt-5">Listeden İstediğiniz Durumu Seçip Güncelleyebilirsiniz</small>
                                    <div class="row m-5 justify-content-center">
                                        <button class="btn btn-warning mb-5 doc-status" data-key="doc_trans_request_start"    type="button">Başladı</button>
                                        <button class="btn btn-success mb-5 doc-status" data-key="doc_trans_request_end"      type="button">Tamamlandı</button>
                                        <button class="btn btn-danger mb-5 doc-status"  data-key="doc_trans_request_cancelled"  type="button">İptal</button>
                                    </div>`,
                            willOpen : async () => {
                                Swal.showValidationMessage(statusParts[2] || '');
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
                                                    const rsp = await this.plib.request({ url: '/api/v1/trans/set-status', method: 'POST' }, null, envelope);
                                                    if(rsp.success){
                                                        this.table.updateRow(rowData.id,{status : e.target.dataset.key+'**'+rsp.data+'**'+note});
                                                        this.plib.toast(this.Swal,'success','İşlem Tamamlandı');
                                                    } else {
                                                        Swal.showValidationMessage(rsp.msg);
                                                    }
                                                    return rsp.success;
                                                } catch (error) {
                                                    console.log(error);
                                                    Swal.showValidationMessage(`Request failed: ${error}`);
                                                }
                                            }
                                        });
                                    });
                                });
                            }
                        });
                    };
                }

                header.appendChild(headerLeft);
                header.appendChild(status);

                const body = document.createElement('div');
                body.classList.add('request-card__body');

                const bodyItems = [
                    { label: 'Talep Kodu', value: rowData.req_no ?? '-' },
                    { label: 'Santral', value: rowData.target_type ?? '-' },
                    { label: 'Sipariş Kapsamı', value: rowData.order_radius ?? '-' },
                    { label: 'İhale', value: rowData.contract_start_date && rowData.contract_end_date ? `${rowData.contract_start_date} - ${rowData.contract_end_date}` : '-' },
                    { label: 'Sevkiyat', value: rowData.transfer_start_date && rowData.transfer_end_date ? `${rowData.transfer_start_date} - ${rowData.transfer_end_date}` : '-' }
                ];

                bodyItems.forEach(item => {
                    const row = document.createElement('div');
                    row.classList.add('request-card__row');
                    const label = document.createElement('span');
                    label.classList.add('request-card__label');
                    label.textContent = item.label;
                    const value = document.createElement('span');
                    value.classList.add('request-card__value');
                    value.textContent = item.value;
                    row.appendChild(label);
                    row.appendChild(value);
                    body.appendChild(row);
                });

                const footer = document.createElement('div');
                footer.classList.add('request-card__footer');
                if(isAdmin){
                    const startBtn = document.createElement('button');
                    startBtn.classList.add('request-card__action-btn');
                    startBtn.type = 'button';
                    startBtn.innerHTML = '<i class="ki-outline ki-youtube"></i> Başlat';
                    startBtn.onclick = async () => {
                        Swal.fire({
                            showCancelButton : true,
                            cancelButtonText : 'İptal',
                            confirmButtonText : 'Kaydet..',
                            showCloseButton : true,
                            icon : 'info',
                            title : 'İhale Başlayacaktır.',
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
                                    envelope.append('op_key','doc_trans_request_start');
                                    envelope.append('note',note);
                                    const rsp = await this.plib.request({ url: '/api/v1/trans/set-status', method: 'POST' }, null, envelope);
                                    if(rsp.success){
                                        this.table.updateRow(rowData.id,{status : 'doc_trans_request_start**'+rsp.data+'**'+note});
                                        this.plib.toast(this.Swal,'success','İhale Başlatıldı.');
                                    } else {
                                        Swal.showValidationMessage(rsp.msg);
                                    }
                                    return rsp.success;
                                } catch (error) {
                                    console.log(error);
                                    Swal.showValidationMessage(`Request failed: ${error}`);
                                }
                            }
                        });
                    };
                    footer.appendChild(startBtn);

                    const editBtn = document.createElement('button');
                    editBtn.classList.add('request-card__action-btn');
                    editBtn.type = 'button';
                    editBtn.innerHTML = '<i class="ki-outline ki-pencil"></i> Düzenle';
                    editBtn.onclick = () => this.$router.push({ name: 'RequestForm', params: { id: rowData.id } });
                    footer.appendChild(editBtn);

                    const delBtn = document.createElement('button');
                    delBtn.classList.add('request-card__action-btn','request-card__action-btn--danger');
                    delBtn.type = 'button';
                    delBtn.innerHTML = '<i class="ki-outline ki-trash"></i> Sil';
                    delBtn.onclick = async () => {
                        const confirm = await Swal.fire({
                            icon: 'warning',
                            title: 'Talebi Sil',
                            text: 'Bu işlem oluşturulan talebi tamamen silecektir. İşlemi gerçekleştirmek istediğinize emin misiniz?',
                            showCancelButton: true,
                            confirmButtonText: 'Evet, sil',
                            cancelButtonText: 'Vazgeç',
                            reverseButtons: true,
                        });
                        if (!confirm.isConfirmed) return;
                        this.navigationStore.toggle(true);
                        const rsp = await this.plib.request({ url: '/api/v1/document/'+rowData.id, method: 'DELETE' }, null);
                        if (rsp.success) { this.table.deleteRow(rowData.id); } else { this.plib.toast(this.Swal,'error',rsp.msg); }
                        setTimeout(() => this.navigationStore.toggle(false), 300);
                    };
                    footer.appendChild(delBtn);
                } else if(isReseller && statusParts[0] === 'doc_trans_request_start'){
                    const viewBtn = document.createElement('button');
                    viewBtn.classList.add('request-card__action-btn');
                    viewBtn.type = 'button';
                    viewBtn.innerHTML = '<i class="ki-outline ki-eye"></i> Detay';
                    viewBtn.onclick = () => this.$router.push({ name: 'RequestForm', params: { id: rowData.id } });
                    footer.appendChild(viewBtn);
                }

                card.appendChild(header);
                card.appendChild(body);
                if(footer.children.length) card.appendChild(footer);
                return card;
            },
            buildTestTable(){
                
                //set headers
                const headers = [
                    {
                        title : ' ',
                        key   : 'id',
                        order : false,
                        type  : 'string',
                        columnFormatter : (elm,rowData) => this.formatRequestCard(rowData)
                    },{
                        title : 'Talep Kodu',
                        key   : 'req_no',
                        order : true,
                        width : '100px',
                        type  : 'string'
                    },{
                        title : 'Talep Başlık',
                        key   : 'title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Santral',
                        key   : 'target_type',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData) => {
                            return rowData.her_ikisi && rowData.her_ikisi == 1 ? 'Her İki Sistem' : (rowData.target_type ?? '-');
                        }
                    },{
                        title : 'Sipariş Kapsamı',
                        key   : 'order_radius',
                        order : true,
                        width : '150px',
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'İhale Başlangıç / Bitiş',
                        key   : 'contract_start_date',
                        order : true,
                        width : '200px',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData) => {
                            return rowData.contract_start_date && rowData.contract_end_date ? 
                                `<div>${rowData.contract_start_date} - ${rowData.contract_end_date}</div>` : '-';
                        }
                    },{
                        title : 'Sevkiyat Başlangıç / Bitiş',
                        key   : 'transfer_start_date',
                        order : true,
                        width : '200px',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData) => {
                            return rowData.transfer_start_date && rowData.transfer_end_date ? 
                                `<div>${rowData.transfer_start_date} - ${rowData.transfer_end_date}</div>` : '-';
                        }
                    },{
                        title : 'Güncel Durum',
                        key   : 'status',
                        order : true,
                        width : '200px',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const  btn    = document.createElement('button');
                            btn.classList.add('btn','d-flex','align-items-center');

                            const key = rowData.status?.split('**');
                            
                            let icon  = '<i class="ph ph-timer fs-2 me-3"></i>';
                            switch(key?.[0]){
                                case 'doc_trans_created':
                                default:
                                    if(key?.[1]) key[1] = 'Taslak';
                                    icon  = '<i class="ki-outline ki-directbox-default fs-2 me-3"></i>';
                                    btn.classList.add('btn-default');
                                    break;
                                case 'doc_trans_request_end':
                                    icon  = '<i class="ki-outline ki-check fs-2 me-3"></i>';
                                    btn.classList.add('btn-success');
                                    break;
                                case 'doc_trans_request_start':
                                    icon  = '<i class="ki-outline ki-timer fs-2 me-3"></i>';
                                    btn.classList.add('btn-warning');
                                    break;
                                case 'doc_trans_request_cancelled':
                                    icon  = '<i class="ki-outline ki-cross-circle fs-2 me-3"></i>';
                                    btn.classList.add('btn-danger');
                                    break;
                            }
                            btn.innerHTML = icon+' '+(key?.[1] ?? 'Bekleniyor..') ;
                            btn.type      = 'button';
                            btn.onclick   = this.useAuthStore().permissions?.includes('per-05-02') ? (e) => {
                                Swal.fire({
                                    showConfirmButton : false,
                                    showCloseButton : true,
                                    html : `<small class="mb-5 mt-5">Listeden İstediğiniz Durumu Seçip Güncelleyebilirsiniz</small>
                                            <div class="row m-5 justify-content-center">
                                                <button class="btn btn-warning mb-5 doc-status" data-key="doc_trans_request_start"    type="button">Başladı</button>
                                                <button class="btn btn-success mb-5 doc-status" data-key="doc_trans_request_end"      type="button">Tamamlandı</button>
                                                <button class="btn btn-danger mb-5 doc-status"  data-key="doc_trans_request_cancelled"  type="button">İptal</button>
                                            </div>`,
                                    willOpen : async () => {
                                        Swal.showValidationMessage(key?.[2]);
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
                            } : () => {};
                          
                            return btn;
                        }
                    },{
                        title : '#',
                        key   : 'id',
                        order : false,
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const statusKey = rowData.status?.split('**')?.[0];
                            const isAdmin   = this.authStore.permissions?.includes('per-05-02');
                            const isReseller = this.authStore.typeKey == 'op-pert-reseller';

                            const span = document.createElement('span');
                            span.classList.add('d-flex','align-items-center','gap-1');

                            if (isAdmin) {
                                const startBtn = document.createElement('button');
                                startBtn.classList.add('btn','btn-secondary','action-icon-btn');
                                startBtn.title = 'Başlat';
                                startBtn.innerHTML = '<i class="ki-outline ki-youtube fs-2"></i>';
                                startBtn.onclick = () => {
                                    Swal.fire({
                                        showCancelButton : true,
                                        cancelButtonText : 'İptal',
                                        confirmButtonText : 'Kaydet..',
                                        showCloseButton : true,
                                        icon : 'info',
                                        title : 'İhale Başlayacaktır.',
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
                                                envelope.append('op_key','doc_trans_request_start');
                                                envelope.append('note',note);
                                                const rsp = await this.plib.request({
                                                    url      : '/api/v1/trans/set-status',
                                                    method   : 'POST',
                                                },null,envelope);
                                                if(rsp.success){
                                                    this.table.updateRow(rowData.id,{status : 'doc_trans_request_start'+'**'+rsp.data+'**'+note});
                                                    this.plib.toast(this.Swal,'success','İhale Başlatıldı.');
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

                                span.appendChild(startBtn);
                                // Düzenle
                                const editBtn = document.createElement('button');
                                editBtn.classList.add('btn','btn-secondary','action-icon-btn');
                                editBtn.title = 'Düzenle';
                                editBtn.innerHTML = '<i class="ki-outline ki-pencil fs-2"></i>';
                                editBtn.onclick = () => this.$router.push({ name: 'RequestForm', params: { id: columnData } });
                                span.appendChild(editBtn);

                                // Sil
                                const delBtn = document.createElement('button');
                                delBtn.classList.add('btn','btn-secondary','action-icon-btn','action-icon-btn--danger');
                                delBtn.title = 'Sil';
                                delBtn.innerHTML = '<i class="ki-outline ki-trash fs-2"></i>';
                                delBtn.onclick = async () => {
                                    const confirm = await Swal.fire({
                                        icon: 'warning',
                                        title: 'Talebi Sil',
                                        text: 'Bu işlem oluşturulan talebi tamamen silecektir. İşlemi gerçekleştirmek istediğinize emin misiniz?',
                                        showCancelButton: true,
                                        confirmButtonText: 'Evet, sil',
                                        cancelButtonText: 'Vazgeç',
                                        reverseButtons: true,
                                    });
                                    if (!confirm.isConfirmed) return;
                                    this.navigationStore.toggle(true);
                                    const rsp = await this.plib.request({ url: '/api/v1/document/'+columnData, method: 'DELETE' }, null);
                                    if (rsp.success) { this.table.deleteRow(columnData); }
                                    else { this.plib.toast(this.Swal,'error',rsp.msg); }
                                    setTimeout(() => this.navigationStore.toggle(false), 300);
                                };
                                span.appendChild(delBtn);

                            } else if (isReseller && statusKey == 'doc_trans_request_start') {
                                // Göz — detay
                                const viewBtn = document.createElement('button');
                                viewBtn.classList.add('btn','btn-secondary','action-icon-btn');
                                viewBtn.title = 'Detayı Gör';
                                viewBtn.innerHTML = '<i class="ki-outline ki-eye fs-2"></i>';
                                viewBtn.onclick = () => this.$router.push({ name: 'RequestForm', params: { id: columnData } });
                                span.appendChild(viewBtn);
                            }

                            return span.children.length ? span : '';
                        }
                    }
                ];
                
                //initiate table with responsive settings
                const isMobile = window.innerWidth < 768;
                const tableHeight = isMobile ? '50vh' : '70vh';
                const pageLimit = isMobile ? 5 : 10;
                
                this.table = new PickleTable({
                    container : '#div_table', //table target div
                    headers   : headers,
                    pageLimit : pageLimit, // -1 for closing pagination
                    height    : tableHeight,
                    type      : 'ajax',
                    columnSearch : false, // true - false for opening and closig
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
                            value : 'op-doc-request-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-request'
                        },{
                            key   : 'showExpired', // column key
                            type  : '=', // filtering type ('like','<','>')
                            value : document.getElementById('showExpiredSwitch').checked ? 'true' : 'false'//wanted column value
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

                        /*if(new Date(data['contract_end_date'].split('/').reverse().join('-')) <= new Date()){
                            elm.classList.add('past-due');
                            if(!this.showExpired) {
                                elm.style.display = 'none';
                            }
                        }*/


                        return data;
                    },
                });
            },
        }
    }

</script>
<template>
    <div class="card rlist-card">
        <div class="card-header rlist-header">
            <!-- Search group -->
            <div class="rlist-search-group">
                <div class="rlist-search-wrap">
                    <i class="ki-duotone ki-magnifier fs-4 rlist-search-icon">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text" id="mainSearch" class="rlist-search-input" placeholder="Talep ara...">
                </div>
                <button type="button" class="rlist-btn rlist-btn-primary" @click="searchTable">
                    <i class="ki-outline ki-magnifier fs-5"></i> Ara
                </button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="resetSearch">
                    Sıfırla
                </button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="exportTable">
                    <i class="ki-outline ki-exit-down fs-5"></i> Excel
                </button>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="showExpiredSwitch" @change="toggleExpired">
                    <label class="form-check-label" for="showExpiredSwitch">
                        Vakti Geçen İhaleleri Göster
                    </label>
                </div>
            </div>
            <!-- Toolbar -->
            <div class="rlist-toolbar">
                <router-link
                    v-if="authStore.permissions?.includes('per-05-02')"
                    :to="{ name: 'RequestForm' }"
                    class="rlist-btn rlist-btn-create"
                >
                    <i class="ki-outline ki-plus fs-5"></i> Talep Oluştur
                </router-link>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="div_table"></div>
        </div>
    </div>
</template>

<style scoped>
/* ── Card ────────────────────────────────────────────────────────────── */
.rlist-card {
    border: 1px solid #dde3ee !important;
    box-shadow: 0 2px 8px rgba(15,40,90,.07) !important;
    border-radius: 12px !important;
    overflow: hidden;
}

/* ── Header ─────────────────────────────────────────────────────────── */
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

/* ── Search group ────────────────────────────────────────────────────── */
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

/* ── Buttons ─────────────────────────────────────────────────────────── */
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

.rlist-btn-primary {
    background: #154b91;
    color: #fff;
}
.rlist-btn-primary:hover {
    background: #0f3a72;
    color: #fff;
}

.rlist-btn-ghost {
    background: #f4f6fa;
    color: #4b5675;
    border: 1px solid #e2e8f0;
}
.rlist-btn-ghost:hover {
    background: #e8edf5;
    color: #1e2a3b;
}

.rlist-btn-create {
    background: #154b91;
    color: #fff !important;
}
.rlist-btn-create:hover {
    background: #0f3a72;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(21,75,145,.25);
}

/* ── Table container ────────────────────────────────────────────────── */
#div_table {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* ── PickleTable overrides ──────────────────────────────────────────── */
:deep(.pickletable table) {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
    table-layout: auto;
}

:deep(.pickletable thead tr) {
    background: #154b91 !important;
}
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

:deep(.pickletable thead th:last-child) {
    border-right: none !important;
}

:deep(.pickletable thead th svg),
:deep(.pickletable thead th i) {
    color: rgba(255,255,255,.6) !important;
}

:deep(.pickletable tbody tr) {
    border-bottom: 1px solid #eef0f4 !important;
    background: #fff !important;
    transition: background .12s;
}

:deep(.pickletable tbody tr:hover) {
    background: #f7f9fd !important;
}

:deep(.pickletable tbody td) {
    padding: 14px 16px !important;
    font-size: 1rem !important;
    color: #2d3748 !important;
    background: transparent !important;
    border: none !important;
    border-right: 1px solid #f0f2f7 !important;
    vertical-align: middle !important;
    white-space: normal !important;
    word-break: break-word;
    max-width: 220px;
}

:deep(.pickletable tbody td:last-child) {
    border-right: none !important;
}

/* Status butonları */
:deep(.pickletable .btn-warning) {
    background: rgba(217,119,6,.1) !important;
    color: #d97706 !important;
    border: 1px solid rgba(217,119,6,.25) !important;
    border-radius: 20px !important;
    font-size: .9rem !important;
    padding: 5px 16px !important;
    font-weight: 600 !important;
}
:deep(.pickletable .btn-success) {
    background: rgba(5,150,105,.1) !important;
    color: #059669 !important;
    border: 1px solid rgba(5,150,105,.25) !important;
    border-radius: 20px !important;
    font-size: .9rem !important;
    padding: 5px 16px !important;
    font-weight: 600 !important;
}
:deep(.pickletable .btn-danger) {
    background: rgba(220,38,38,.08) !important;
    color: #dc2626 !important;
    border: 1px solid rgba(220,38,38,.2) !important;
    border-radius: 20px !important;
    font-size: .9rem !important;
    padding: 5px 16px !important;
    font-weight: 600 !important;
}
:deep(.pickletable .btn-default) {
    background: #f4f6fa !important;
    color: #6b7280 !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 20px !important;
    font-size: .9rem !important;
    padding: 5px 16px !important;
    font-weight: 600 !important;
}
:deep(.pickletable .btn-primary) {
    background: rgba(21,75,145,.1) !important;
    color: #154b91 !important;
    border: 1px solid rgba(21,75,145,.2) !important;
    border-radius: 8px !important;
    font-size: .9rem !important;
    padding: 6px 16px !important;
    font-weight: 600 !important;
}

/* Aksiyon butonları (edit/delete) */
:deep(.pickletable .action-icon-btn > i) {
    padding-right: 0 !important;
}
:deep(.pickletable .btn-secondary) {
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
:deep(.pickletable .btn-secondary:hover) {
    background: #154b91 !important;
    color: #fff !important;
    border-color: #154b91 !important;
}
:deep(.pickletable .action-icon-btn--danger:hover) {
    background: #dc2626 !important;
    color: #fff !important;
    border-color: #dc2626 !important;
}

/* Pagination */
:deep(.pickletable .divPagination) {
    padding: 12px 16px !important;
    border-top: 1px solid #eef0f4;
    justify-content: flex-end !important;
}

:deep(.pickletable .divPagination button) {
    height: 32px !important;
    min-width: 32px !important;
    border-radius: 6px !important;
    font-size: .82rem !important;
    font-weight: 600 !important;
    border: 1px solid #e2e8f0 !important;
    background: #fff !important;
    color: #4b5675 !important;
}

:deep(.pickletable .divPagination button.current) {
    background: #154b91 !important;
    color: #fff !important;
    border-color: #154b91 !important;
}

:deep(.pickletable .divPagination button:hover:not(.current)) {
    background: #f4f6fa !important;
    color: #154b91 !important;
}

:deep(.request-card) {
    width: 100%;
    border: 1px solid rgba(21, 75, 145, 0.12);
    border-radius: 18px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 10px 24px rgba(15, 40, 90, 0.05);
}

:deep(.request-card__header) {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 16px 12px;
    background: linear-gradient(135deg, rgba(21, 75, 145, 0.08), rgba(21, 75, 145, 0.02));
}

:deep(.request-card__badge) {
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

:deep(.request-card__title) {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #102a43;
}

:deep(.request-card__subtitle) {
    margin: 4px 0 0;
    color: #5e6e82;
    font-size: 0.86rem;
}

:deep(.request-card__status) {
    align-self: center;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(34, 197, 94, 0.12);
    color: #166534;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
}

:deep(.request-card__status--clickable) {
    cursor: pointer;
    transition: background .2s, transform .2s;
}

:deep(.request-card__status--clickable:hover) {
    background: rgba(21, 75, 145, 0.16);
    transform: translateY(-1px);
}

:deep(.request-card__body) {
    display: grid;
    gap: 10px;
    padding: 0 16px 14px;
}

:deep(.request-card__row) {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 10px;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid rgba(228, 232, 240, 0.9);
}

:deep(.request-card__row:last-child) {
    border-bottom: none;
}

:deep(.request-card__label) {
    color: #6b7280;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

:deep(.request-card__value) {
    color: #102a43;
    font-size: 0.9rem;
    font-weight: 600;
    text-align: right;
}

:deep(.request-card__footer) {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px 16px 16px;
    border-top: 1px solid rgba(228, 232, 240, 0.9);
}

:deep(.request-card__action-btn) {
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

:deep(.request-card__action-btn:hover) {
    background: rgba(21, 75, 145, 0.08);
}

:deep(.request-card__action-btn--danger) {
    border-color: rgba(220, 38, 38, 0.18);
    color: #b91c1c;
}

:deep(.request-card__action-btn--danger:hover) {
    background: rgba(220, 38, 38, 0.08);
}

:deep(.pickletable td:first-child),
:deep(.pickletable th:first-child){
    display: none;
}

/* ── Responsive ─────────────────────────────────────────────────────── */
@media (max-width: 767px) {
    .rlist-header { padding: 12px 14px !important; }
    .rlist-search-input { width: 160px; }

    :deep(.pickletable thead) {
        display: none !important;
    }

    :deep(.pickletable td:first-child),
    :deep(.pickletable th:first-child){
        display: unset !important;
    }

    :deep(.pickletable td:not(:first-child)),
    :deep(.pickletable th:not(:first-child)){
        display: none !important;
    }

    :deep(.pickletable tbody tr) {
        border: unset !important;
        border-bottom: unset !important;
    }

    :deep(.pickletable td:first-child){
        width: 100% !important;
        padding: 10px 10px !important;
    }
}

@media (max-width: 480px) {
    .rlist-search-group { flex-wrap: wrap; }
    .rlist-search-input { width: 100%; }
    .rlist-toolbar { width: 100%; }
    .rlist-btn-create { width: 100%; justify-content: center; }
}
</style>
