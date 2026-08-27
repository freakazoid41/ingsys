
<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { reset, wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import dayjs from 'dayjs';

    export default {
        breadcrumbs: {
            list: [ { title: 'Belgeler', path: '/coalpanel/documents' } ],
            title: 'Belgeler'
        },
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                useAuthStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                
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
                useAuthStore    : useAuthStore(),
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
            parseRowStatus(lastStatus){
                try {
                    return JSON.parse(lastStatus || '{}');
                } catch (e) {
                    return {};
                }
            },
            exportTable(){
                this.plib.openTab('POST', '/api/v1/export/documents', this.table.currentFilter,'_blank');
            },
            createDetailModalContent(rowData){
                const makeLine = (labelText, valueNodeOrText) => {
                    const line = document.createElement('div');
                    line.classList.add('d-flex','justify-content-between','w-100','mb-2');
                    const key = document.createElement('span');
                    key.classList.add('text-dark','fw-semibold');
                    key.textContent = labelText;
                    const value = document.createElement('span');
                    value.classList.add('text-end','text-muted');
                    if (valueNodeOrText instanceof Node) {
                        value.appendChild(valueNodeOrText);
                    } else {
                        value.textContent = valueNodeOrText;
                    }
                    line.appendChild(key);
                    line.appendChild(value);
                    return line;
                };

                const container = document.createElement('div');
                container.classList.add('bg-white','border','border-1','rounded','p-3','shadow-sm');

                container.appendChild(makeLine('İlişki:', rowData.title ?? '—'));
                container.appendChild(makeLine('Eklenme Tarihi:', dayjs(rowData.created_at).format('DD/MM/YYYY HH:mm') || '—'));
                
                const statusData = this.parseRowStatus(rowData.last_status);
                const statusTitle = statusData?.title ?? 'Bekleniyor..';
                const statusBadge = document.createElement('span');
                statusBadge.classList.add('badge','text-dark');
                statusBadge.textContent = statusTitle;
                container.appendChild(makeLine('Güncel Durum:', statusBadge));
                container.appendChild(makeLine('Kontrol Eden:', statusData?.name ?? '—'));

                let noteText = '';
                try { noteText = JSON.parse(statusData?.note ?? '{}')?.note ?? ''; } catch (e) { noteText = ''; }
                const noteEl = document.createElement('span');
                noteEl.classList.add('text-muted','small');
                noteEl.textContent = noteText || 'Yok';
                container.appendChild(makeLine('Durum Notu:', noteEl));

                const versionsWrap = document.createElement('div');
                versionsWrap.classList.add('w-100','mt-3');
                const label = document.createElement('h6');
                label.classList.add('mb-2','pt-2','border-top','text-secondary','small','fw-semibold');
                label.textContent = 'Geçmiş Versiyonlar';
                versionsWrap.appendChild(label);

                const ul = document.createElement('ul');
                ul.classList.add('list-group','w-100','mt-2');
                let oldVersions = [];
                try { oldVersions = JSON.parse(rowData?.old_versions ?? '[]') || []; } catch(e){ oldVersions = []; }
                if(oldVersions.length){
                    oldVersions.sort((a,b) => new Date(b.created_at) - new Date(a.created_at));

                    oldVersions.forEach((version, i) => {
                        const li = document.createElement('li');
                        li.classList.add('list-group-item','p-1');
                        const a = document.createElement('a');
                        a.classList.add('text-decoration-none','d-flex','align-items-center','justify-content-center');
                        a.href = '/order-file/'+(version.qnid ?? version.description);
                        a.target = '_blank';
                        a.innerHTML = (i == 0 ? '<i class="ki-outline fs-2 ki-arrow-right" style="color:tomato"></i>' : '') + dayjs(version.created_at).format('DD/MM/YYYY HH:mm');
                        li.appendChild(a);
                        ul.appendChild(li);
                    });
                } else {
                    const li = document.createElement('li');
                    li.classList.add('list-group-item','p-1','text-muted');
                    li.textContent = 'Yok';
                    ul.appendChild(li);
                }
                versionsWrap.appendChild(ul);
                container.appendChild(versionsWrap);

                return container;
            },
            showDetailModal(rowData){
                const container = this.createDetailModalContent(rowData);
                Swal.fire({
                    showConfirmButton: false,
                    showCloseButton: true,
                    didOpen: () => {
                        const htmlContainer = Swal.getHtmlContainer();
                        if(htmlContainer){
                            htmlContainer.innerHTML = '';
                            htmlContainer.appendChild(container);
                        }
                    }
                });
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
            formatDocumentCard(rowData){
                const fileType = rowData.file_type || '-';
                const relationLabel = rowData.relation_type === 'op-doc-client' ? 'Müşteri' : rowData.relation_type === 'op-doc-offer' ? 'Teklif' : 'Belge';
                const relationValue = rowData.relation_type === 'op-doc-offer' ? rowData.relation_qnid : (rowData.title ?? '-');
                const statusData = this.parseRowStatus(rowData.last_status);
                const statusLabel = statusData?.title || 'Bekleniyor..';

                const card = document.createElement('div');
                card.classList.add('document-card');

                const header = document.createElement('div');
                header.classList.add('document-card__header');

                const headerLeft = document.createElement('div');
                const badge = document.createElement('div');
                badge.classList.add('document-card__badge');
                badge.textContent = fileType;

                const title = document.createElement('h4');
                title.classList.add('document-card__title');
                title.textContent = relationValue || '-';

                const subtitle = document.createElement('p');
                subtitle.classList.add('document-card__subtitle');
                subtitle.textContent = relationLabel;

                headerLeft.appendChild(badge);
                headerLeft.appendChild(title);
                headerLeft.appendChild(subtitle);

                const statusBadge = document.createElement('span');
                statusBadge.classList.add('document-card__status');
                statusBadge.textContent = statusLabel;
                if (this.useAuthStore().permissions?.includes('per-07-02')) {
                    statusBadge.classList.add('document-card__status--clickable');
                    statusBadge.title = 'Detayları Gör';
                    statusBadge.onclick = () => this.showDetailModal(rowData);
                }

                header.appendChild(headerLeft);
                header.appendChild(statusBadge);
                card.appendChild(header);

                const body = document.createElement('div');
                body.classList.add('document-card__body');

                const rows = [
                    { label: 'İlişki', value: relationValue },
                    { label: 'Eklenme Tarihi', value: dayjs(rowData.created_at).format('DD/MM/YYYY HH:mm') || '-' },
                    { label: 'Durum', value: statusLabel }
                ];

                rows.forEach(item => {
                    const row = document.createElement('div');
                    row.classList.add('document-card__row');

                    const label = document.createElement('span');
                    label.classList.add('document-card__label');
                    label.textContent = item.label;

                    const value = document.createElement('span');
                    value.classList.add('document-card__value');
                    value.textContent = item.value;

                    row.appendChild(label);
                    row.appendChild(value);
                    body.appendChild(row);
                });

                card.appendChild(body);

                const footer = document.createElement('div');
                footer.classList.add('document-card__footer');

                const detailBtn = document.createElement('button');
                detailBtn.classList.add('document-card__action-btn');
                detailBtn.type = 'button';
                detailBtn.innerHTML = '<i class="ki-outline ki-magnifier"></i> Detay';
                detailBtn.onclick = () => this.showDetailModal(rowData);
                footer.appendChild(detailBtn);

                const openBtn = document.createElement('button');
                openBtn.classList.add('document-card__action-btn');
                openBtn.type = 'button';
                openBtn.innerHTML = '<i class="ki-outline ki-eye"></i> Aç';
                openBtn.onclick = () => window.open('/order-file/'+(rowData?.id ?? rowData?.file), '_blank');
                footer.appendChild(openBtn);

                if (this.useAuthStore().permissions?.includes('per-07')) {
                    const viewFormBtn = document.createElement('button');
                    viewFormBtn.classList.add('document-card__action-btn');
                    viewFormBtn.type = 'button';
                    viewFormBtn.innerHTML = '<i class="ki-outline ki-arrow-right"></i> İlişki';
                    viewFormBtn.onclick = () => {
                        switch(rowData.relation_type){
                            case 'op-doc-client':
                                this.$router.push({ name: 'CForm', params: { id: rowData.relation_qnid } });
                                break;
                            case 'op-doc-offer':
                                this.$router.push({ name: 'OForm', params: { id: rowData.relation_qnid } });
                                break;
                        }
                    };
                    footer.appendChild(viewFormBtn);

                    const disableBtn = document.createElement('button');
                    disableBtn.classList.add('document-card__action-btn');
                    disableBtn.type = 'button';
                    disableBtn.innerHTML = '<i class="ki-outline ki-trash"></i> Devre Dışı';
                    disableBtn.onclick = () => {
                        Swal.fire({
                            title: 'Emin misiniz?',
                            text: 'Dosya sistemde kalmaya devam edecek ama deaktif edilecek bu sayede listede görünmeyecek!',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Evet, devre dışı bırak',
                            cancelButtonText: 'İptal'
                        }).then(async (result) => {
                            if(result.isConfirmed){
                                const envelope = new FormData();
                                envelope.append('id', rowData.id);
                                try {
                                    const rsp = await this.plib.request({
                                        url      : '/api/v1/trans/disable-document',
                                        method   : 'POST',
                                    },null,envelope);
                                    if(rsp.success){
                                        this.table.removeRow(rowData.id);
                                        this.plib.toast(this.Swal,'success','Dosya devre dışı bırakıldı');
                                    }else{
                                        Swal.fire('Hata', rsp.message || 'İşlem başarısız', 'error');
                                    }
                                } catch (error) {
                                    Swal.fire('Hata', 'Bir hata oluştu', 'error');
                                }
                            }
                        });
                    };
                    footer.appendChild(disableBtn);
                }

                card.appendChild(footer);
                return card;
            },
            buildTestTable(){
                
                //set headers
                const headers = [
                    {
                        title : ' ',
                        key   : 'document_card',
                        order : false,
                        type  : 'string',
                        columnFormatter : (elm,rowData) => this.formatDocumentCard(rowData)
                    },{
                        title : 'Belge Başlık',
                        key   : 'file_type',
                        order : true,
                        width : '300px',
                        type  : 'string', // if column is string then make type string
                        
                    },{
                        title : 'İlişki',
                        key   : 'title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const badge = document.createElement('span');
                            badge.classList.add('badge','text-dark');
                            

                            switch(rowData.relation_type){
                                case 'op-doc-client':
                                    badge.textContent = columnData ?? '—';
                                    break;
                                case  'op-doc-offer':
                                    badge.textContent = rowData.relation_qnid;
                                    break;
                                default:
                                    badge.classList.add('bg-secondary');
                            }



                            return badge;
                        }
                        
                    },{
                        title : 'Eklenme Tarihi',
                        key   : 'created_at',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                           return dayjs(columnData).format('DD/MM/YYYY HH:mm');
                        }
                    },{
                        title : 'Güncel Durum',
                        key   : 'last_status',
                        order : false,
                        width : '250px',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const  btn    = document.createElement('button');
                            btn.classList.add('btn','d-flex','align-items-center');
                            const statusData = JSON.parse(columnData);
                            const key = statusData?.op_key;
                            let icon  = '<i class="ph ph-timer fs-2 me-3"></i>';
                            switch(key){
                                case 'doc_file_waiting':
                                default:
                                    if(statusData?.title) statusData.title = 'Kontrol Bekleniyor';
                                    icon  = '<i class="ki-outline ki-directbox-default fs-2 me-3"></i>';
                                    btn.classList.add('btn-default');
                                    break;
                                case 'doc_file_accepted':
                                    icon  = '<i class="ki-outline ki-check fs-2 me-3"></i>';
                                    btn.classList.add('btn-success');
                                    break;
                                case 'doc_file_refreshed':
                                    icon  = '<i class="ki-outline ki-timer fs-2 me-3"></i>';
                                    btn.classList.add('btn-warning');
                                    break;
                                case 'doc_file_rejected':
                                    icon  = '<i class="ki-outline ki-cross-circle fs-2 me-3"></i>';
                                    btn.classList.add('btn-danger');
                                    break;
                            }
                            btn.innerHTML = icon+' '+(statusData?.title ?? 'Bekleniyor..') ;
                            btn.type      = 'button';
                            btn.onclick   = this.useAuthStore().permissions?.includes('per-07-02') ? (e) => {
                                Swal.fire({
                                    showConfirmButton : false,
                                    showCloseButton : true,
                                    html : `<small class="mb-5 mt-5">Listeden İstediğiniz Durumu Seçip Güncelleyebilirsiniz</small>
                                            <div class="row m-5 justify-content-center">
                                                <!--<button class="btn btn-warning mb-5 doc-status" data-key="doc_file_refreshed"    type="button">Yeniden Talep Et</button>-->
                                                <button class="btn btn-success mb-5 doc-status" data-key="doc_file_accepted"      type="button">Kabul Edildi</button>
                                                <button class="btn btn-danger mb-5 doc-status"  data-key="doc_file_rejected"  type="button">Reddet</button>
                                            </div>`,
                                    willOpen : async () => {
                                       //console.log(statusData)
                                        let noteText = (() => {
                                            try {
                                                return JSON.parse(statusData?.note ?? '{}')?.note ?? statusData?.note ?? '';
                                            } catch (e) {
                                                return statusData?.note ?? '';
                                            }
                                        })();
                                        Swal.showValidationMessage(noteText);
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
                                                                url      : '/api/v1/trans/set-file-status',
                                                                method   : 'POST',
                                                            },null,envelope);
                                                            if(rsp.success){
                                                                const lastStatus = {
                                                                    "op_key" : e.target.dataset.key,
                                                                    "title"  : rsp.data,
                                                                    "note"   : note
                                                                };


                                                                this.table.updateRow(rowData.id,{last_status : JSON.stringify(lastStatus)});
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
                        title : 'Detaylar',
                        key   : 'id',
                        order : false,
                        colAlign : 'center',
                        headAlign : 'center',
                        width : '200px',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            span.classList.add('d-flex','justify-content-center','align-items-center','flex-row');

                            let btn = document.createElement('button');
                            btn.classList.add('btn','btn-secondary','me-1','d-flex','justify-content-center','align-items-center','flex-row');
                            btn.innerHTML = '<i class="ki-duotone ki-pencil text-gray-900 fs-2" role="img"><span class="path1"></span><span class="path2"></span></i>';
                            btn.onclick   = () => {
                                this.showDetailModal(rowData);
                            };

                            span.appendChild(btn);

                            btn = document.createElement('button');
                            btn.classList.add('btn','btn-secondary','me-1','d-flex','justify-content-center','align-items-center','flex-row');
                            btn.innerHTML = '<i class="ki-outline ki-eye text-gray-900 fs-2" role="img"></i>';
                            btn.onclick   = () => {
                                window.open('/order-file/'+(rowData?.id ?? rowData?.file));
                            }
                            span.appendChild(btn);

                            btn = document.createElement('button');
                            btn.classList.add('btn','btn-secondary','me-1','d-flex','justify-content-center','align-items-center','flex-row');
                            btn.innerHTML = '<i class="ki-outline ki-magnifier text-gray-900 fs-2" role="img"></i>';
                            btn.onclick   = () => {
                                
                                //here we must split forms actualy everything is an document but on pnael their route is diffrent
                                switch(rowData.relation_type){
                                    case 'op-doc-client':
                                        this.$router.push({ name: 'CForm' , params: { id: rowData.relation_qnid }});
                                        break;
                                    case  'op-doc-offer':
                                        this.$router.push({ name: 'OForm' , params: { id: rowData.relation_qnid }});
                                        break;
                                }
                            }
                            span.appendChild(btn);

                            btn = document.createElement('button');
                            btn.classList.add('btn','btn-danger','me-1','d-flex','justify-content-center','align-items-center','flex-row');
                            btn.innerHTML = '<i class="ki-outline ki-trash text-white fs-2" role="img"></i>';
                            btn.onclick   = () => {
                                Swal.fire({
                                    title: 'Emin misiniz?',
                                    text: 'Bu dosya devre dışı bırakılacak!',
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonColor: '#d33',
                                    cancelButtonColor: '#3085d6',
                                    confirmButtonText: 'Evet, devre dışı bırak',
                                    cancelButtonText: 'İptal'
                                }).then(async (result) => {
                                    if(result.isConfirmed){
                                        const envelope = new FormData();
                                        envelope.append('id', rowData.id);
                                        try {
                                            const rsp = await this.plib.request({
                                                url      : '/api/v1/trans/disable-document',
                                                method   : 'POST',
                                            },null,envelope);
                                            if(rsp.success){
                                                this.table.removeRow(rowData.id);
                                                this.plib.toast(this.Swal,'success','Dosya devre dışı bırakıldı');
                                            }else{
                                                Swal.fire('Hata', rsp.message || 'İşlem başarısız', 'error');
                                            }
                                        } catch (error) {
                                            Swal.fire('Hata', 'Bir hata oluştu', 'error');
                                        }
                                    }
                                });
                            }
                            //span.appendChild(btn);

                            return this.useAuthStore().permissions?.includes('per-07') ? span : '';
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
                    columnSearch : true, // true - false for opening and closig
                    paginationType : 'scroll',// scroll - number (number for default)
                    // grouping configuration
                    groupBy : 'relation_detail', // group by file type (PDF, DOCX, etc.)
                    groupFormatter : (groupValue, rowCount) => {
                        const values = {};
                        const data = JSON.parse(groupValue);
                        data.forEach(item => {
                            values[item.Key] = item.Value;
                        });



                        return `${values.title} — ${rowCount} belge`;
                    },
                    groupToggleCallback : null,
                    ajax:{
                        url:'/api/v1/table/document_files',
                        data:{
                            //order:{},
                        }
                    },
                    initialFilter : [],
                    nextPageIcon : '<i class="ki-outline ki-arrow-right "></i>',
                    prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter:(elm,data)=>{
                        //console.log(elm,data);
                        //modify row element
                        //elm.style.backgroundColor = 'yellow';
                        //modify data
                        JSON.parse(data.relation_detail).forEach(element => {
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
        }
    }

</script>
<template>
    <div class="card mt-10">
       <div class="card-header align-items-center py-5 gap-2 gap-md-5 responsive-header">
                   <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1  w-100 search-container">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-4 search-icon">
                                  <span class="path1"></span>
                                  <span class="path2"></span>
                             </i>
                        <input type="text" class="search form-control form-control-solid w-250px ps-12 search-input" id="mainSearch" placeholder="Dosya Ara">
                        <button type="button" class="btn btn-primary ms-2 search-btn-primary" @click="searchTable">Ara</button>
                        <button type="button" class="btn btn-secondary ms-2 search-btn-secondary" @click="resetSearch">Sıfırla</button> 
                        <button type="button" class="btn btn-secondary ms-2 search-btn-export" @click="exportTable">Excel Çıktı</button> 
                        </div>
                   </div>
                <div class="card-toolbar flex-row-fluid justify-content-end gap-5 toolbar-container">
                        <!--<div class="w-100  mw-200px d-flex align-items-center">
                             <label class="mx-2" for="">Durum: </label>
                             <select class="mw-150px form-select form-select-solid" data-control="select2" data-hide-search="true" data-placeholder="Durum" data-kt-ecommerce-product-filter="durum">
                                  <option></option>
                                  <option value="all">Tümü</option>
                                  <option value="Aktif">Aktif</option>
                                  <option value="Pasif">Pasif</option>
                             </select>
                        </div>
                        <div class="w-100 mw-200px d-flex align-items-center">
                             <label class="mx-2">Alt Yüklenici: </label>
                             <select class="mw-150px form-select form-select-solid" data-control="select2" data-hide-search="true" data-placeholder="Alt Yüklenici" data-kt-ecommerce-product-filter="alt_yuklenici">
                                  <option></option>
                                  <option value="all">Tümü</option>
                                  <option value="Evet">Evet</option>
                                  <option value="Hayır">Hayır</option>
                             </select>
                        </div>-->
                   </div>
              </div>
        <div class="card-body">
            <div id="div_table"></div>
        </div>
    </div>
</template>

<style scoped>
.responsive-header {
    flex-wrap: wrap;
    padding: 1rem;
}

.search-container {
    flex-wrap: wrap;
    gap: 0.5rem;
}

.search-input {
    max-width: 100%;
    flex: 1 1 auto;
    min-width: 150px;
}

.toolbar-container {
    flex-wrap: wrap;
}

#div_table {
    width: 100%;
    overflow-x: auto;
    border-radius: 0.375rem;
}

    :deep(.pickletable tr:not(.table-group-header) td:first-child),
    :deep(.pickletable th:first-child){
        display: none;
    }

    /* GROUP STYLING */
    :deep(.table-group-header td) {
        background: linear-gradient(90deg, #f0f5ff 0%, #f8faff 100%) !important;
        border-left: 4px solid #154b91 !important;
        border-top: 1px solid #d1deff !important;
        border-bottom: 1px solid #d1deff !important;
        padding: 10px 18px !important;
        cursor: pointer !important;
        user-select: none !important;
        color: #154b91 !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        letter-spacing: 0.02em !important;
        overflow: visible !important;
    }

    :deep(.table-group-header:hover td) {
        background: linear-gradient(90deg, #e4edff 0%, #eef4ff 100%) !important;
    }

    :deep(.group-toggle-icon) {
        display: inline-block !important;
        margin-right: 8px !important;
        font-size: 16px !important;
        font-style: normal !important;
        color: #154b91 !important;
        transition: transform 0.2s ease !important;
        vertical-align: middle !important;
    }

    :deep(.group-title-text) {
        vertical-align: middle !important;
    }

    :deep(tbody tr[data-group][data-collapsed="true"]) {
        display: none !important;
    }

    :deep(.document-card) {
        width: 100%;
        border: 1px solid rgba(21, 75, 145, 0.12);
        border-radius: 18px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 10px 24px rgba(15, 40, 90, 0.05);
    }

    :deep(.document-card__header) {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        padding: 16px 16px 12px;
        background: linear-gradient(135deg, rgba(21, 75, 145, 0.08), rgba(21, 75, 145, 0.02));
    }

    :deep(.document-card__badge) {
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

    :deep(.document-card__title) {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #102a43;
    }

    :deep(.document-card__subtitle) {
        margin: 4px 0 0;
        color: #5e6e82;
        font-size: 0.86rem;
    }

    :deep(.document-card__status) {
        align-self: center;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(34, 197, 94, 0.12);
        color: #166534;
        font-size: 0.78rem;
        font-weight: 700;
        white-space: nowrap;
    }

    :deep(.document-card__status--clickable) {
        cursor: pointer;
        transition: background .2s, transform .2s;
    }

    :deep(.document-card__status--clickable:hover) {
        background: rgba(21, 75, 145, 0.16);
        transform: translateY(-1px);
    }

    :deep(.document-card__body) {
        display: grid;
        gap: 10px;
        padding: 0 16px 14px;
    }

    :deep(.document-card__row) {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid rgba(228, 232, 240, 0.9);
    }

    :deep(.document-card__row:last-child) {
        border-bottom: none;
    }

    :deep(.document-card__label) {
        color: #6b7280;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    :deep(.document-card__value) {
        color: #102a43;
        font-size: 0.95rem;
        font-weight: 600;
        text-align: right;
    }

    :deep(.document-card__footer) {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 10px 16px 16px;
        border-top: 1px solid rgba(228, 232, 240, 0.9);
    }

    :deep(.document-card__action-btn) {
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

    :deep(.document-card__action-btn:hover) {
        background: rgba(21, 75, 145, 0.08);
    }

    @media (max-width: 767px) {
        .responsive-header {
            flex-direction: column;
            gap: 0.75rem !important;
            padding: 0.75rem !important;
        }

        .search-container {
            width: 100%;
            gap: 0.35rem;
        }

        .search-btn-primary,
        .search-btn-secondary,
        .search-btn-export {
            padding: 0.4rem 0.6rem !important;
            font-size: 0.75rem !important;
            white-space: nowrap;
        }

        :deep(.pickletable thead) {
            display: none !important;
        }

        :deep(.pickletable tr:not(.table-group-header) td:first-child),
        :deep(.pickletable tr:not(.table-group-header) th:first-child){
            display: table-cell !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        :deep(.pickletable tr:not(.table-group-header) td:not(:first-child)),
        :deep(.pickletable tr:not(.table-group-header) th:not(:first-child)){
            display: none !important;
        }

        :deep(.pickletable tbody tr) {
            border: unset !important;
            border-bottom: unset !important;
        }

        :deep(.pickletable table) {
            table-layout: auto !important;
            min-width: unset !important;
            width: 100% !important;
        }

        :deep(.pickletable th),
        :deep(.pickletable td) {
            font-size: 0.75rem !important;
        }
    }
</style>
