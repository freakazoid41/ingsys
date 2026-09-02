
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
                // İlişki: item files → product_name/title, order files → order_no (title is empty for orders)
                const iliskiVal = rowData.product_name || rowData.title || rowData.order_no || rowData.buying_no || '—';
                container.appendChild(makeLine('İlişki:', iliskiVal));
                // Eklenme Tarihi: use pre-formatted _created_at_fmt (file's real date), not overwritten EAV created_at (DD/MM/YYYY)
                const tarihVal = rowData._created_at_fmt && rowData._created_at_fmt !== '—' ? rowData._created_at_fmt : (()=>{ const d=dayjs(rowData._file_created_at || rowData.created_at); return d.isValid()?d.format('DD/MM/YYYY HH:mm'):'—'; })();
                container.appendChild(makeLine('Eklenme Tarihi:', tarihVal));
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
            formatDocumentCard(rowData){
                const fileType = rowData.file_type || '-';
                const relationLabel = rowData.relation_type === 'op-doc-client' ? 'Müşteri' : rowData.relation_type === 'op-doc-offer' ? 'Teklif' : 'Belge';
                const relationValue = rowData.relation_type === 'op-doc-offer' ? rowData.relation_qnid : (rowData.product_name || rowData.title || rowData.order_no || '-');
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
                    { label: 'Eklenme Tarihi', value: rowData._created_at_fmt || (()=>{ const d=dayjs(rowData._file_created_at || rowData.created_at); return d.isValid()?d.format('DD/MM/YYYY HH:mm'):'-'; })() },
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
                        width : '30%',
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const wrap = document.createElement('div');
                            wrap.classList.add('dlist-filecell');
                            const iconBox = document.createElement('div');
                            iconBox.classList.add('dlist-filecell__icon');
                            // pick icon by file_type
                            const ft = (columnData||'').toLowerCase();
                            let icon = 'ki-document';
                            if(ft.includes('test')) icon = 'ki-flask';
                            else if(ft.includes('cins')) icon = 'ki-chart-simple';
                            else if(ft.includes('kabul')) icon = 'ki-clipboard';
                            iconBox.innerHTML = `<i class="ki-outline ${icon} fs-2"></i>`;
                            if(ft.includes('test')) iconBox.classList.add('is-test');
                            else if(ft.includes('cins')) iconBox.classList.add('is-cins');
                            else if(ft.includes('kabul')) iconBox.classList.add('is-kabul');
                            const textBox = document.createElement('div');
                            textBox.classList.add('dlist-filecell__text');
                            const title = document.createElement('div');
                            title.classList.add('dlist-filecell__title');
                            title.textContent = columnData || '—';
                            textBox.appendChild(title);
                            const sub = document.createElement('div');
                            sub.classList.add('dlist-filecell__sub');
                            sub.textContent = rowData.file_qnid ? rowData.file_qnid.slice(0,8)+'…' : 'Belge';
                            // textBox.appendChild(sub);
                            wrap.appendChild(iconBox);
                            wrap.appendChild(textBox);
                            return wrap;
                        }
                    },{
                        title : 'İlişki',
                        key   : 'title',
                        order : true,
                        width : '15%',
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const wrap = document.createElement('div');
                            wrap.classList.add('dlist-relation');
                            const badge = document.createElement('span');
                            badge.classList.add('dlist-relation__badge');
                            switch(rowData.relation_type){
                                case 'op-doc-client':
                                    badge.textContent = columnData ?? '—';
                                    badge.classList.add('is-client');
                                    break;
                                case  'op-doc-offer':
                                    badge.textContent = rowData.relation_qnid;
                                    badge.classList.add('is-offer');
                                    break;
                                default:
                                    if(rowData.product_name){
                                        badge.textContent = rowData.product_name;
                                        badge.classList.add('is-product');
                                    } else if(rowData.order_no){
                                        badge.textContent = rowData.order_no;
                                        badge.classList.add('is-product');
                                    } else if(columnData && columnData !== ''){
                                        badge.textContent = columnData;
                                        badge.classList.add('is-product');
                                    } else {
                                        badge.textContent = '—';
                                        badge.classList.add('is-empty');
                                    }
                            }
                            wrap.appendChild(badge);
                            return wrap;
                        }
                    },{
                        title : 'Eklenme Tarihi',
                        key   : '_created_at_fmt',
                        order : false,
                        type  : 'string',
                        width : '14%',
                        columnFormatter : (elm,rowData) => {
                            const wrap = document.createElement('div');
                            wrap.classList.add('dlist-date');
                            const d = document.createElement('span');
                            d.classList.add('dlist-date__text');
                            d.textContent = rowData._created_at_fmt || '—';
                            const icon = document.createElement('i');
                            icon.className = 'ki-outline ki-calendar-8 fs-5 me-1';
                            icon.style.opacity = '.55';
                            wrap.appendChild(icon);
                            wrap.appendChild(d);
                            return wrap;
                        }
                    },{
                        title : 'Güncel Durum',
                        key   : 'last_status',
                        order : false,
                        width : '26%',
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const pill = document.createElement('button');
                            pill.classList.add('dlist-pill');
                            let statusData = {};
                            try { statusData = JSON.parse(columnData || '{}'); } catch(e){ statusData={}; }
                            const key = statusData?.op_key;
                            let icon = '<i class="ki-outline ki-time fs-5"></i>';
                            let label = statusData?.title || 'Bekleniyor';
                            if(!statusData?.title) label = 'Kontrol Bekleniyor';
                            switch(key){
                                case 'doc_file_waiting':
                                default:
                                    label = statusData?.title ? (statusData.title==='Kontrol Bekleniyor'? statusData.title : 'Kontrol Bekleniyor') : 'Kontrol Bekleniyor';
                                    icon  = '<i class="ki-outline ki-directbox-default fs-5"></i>';
                                    pill.classList.add('is-waiting');
                                    break;
                                case 'doc_file_accepted':
                                    icon  = '<i class="ki-outline ki-check-circle fs-5"></i>';
                                    pill.classList.add('is-accepted');
                                    label = statusData.title || 'Onaylandı';
                                    break;
                                case 'doc_file_refreshed':
                                    icon  = '<i class="ki-outline ki-arrows-loop fs-5"></i>';
                                    pill.classList.add('is-refreshed');
                                    label = statusData.title || 'Yenilendi';
                                    break;
                                case 'doc_file_rejected':
                                    icon  = '<i class="ki-outline ki-cross-circle fs-5"></i>';
                                    pill.classList.add('is-rejected');
                                    label = statusData.title || 'Reddedildi';
                                    break;
                            }
                            pill.innerHTML = icon+'<span>'+label+'</span>';
                            pill.type = 'button';
                            if(this.useAuthStore().permissions?.includes('per-07-02')){
                                pill.onclick = (e) => {
                                    Swal.fire({
                                        showConfirmButton : false,
                                        showCloseButton : true,
                                        html : `<small class="mb-5 mt-5">Listeden İstediğiniz Durumu Seçip Güncelleyebilirsiniz</small>
                                                <div class="row m-5 justify-content-center">
                                                    <button class="btn btn-success mb-5 doc-status" data-key="doc_file_accepted"      type="button">Kabul Edildi</button>
                                                    <button class="btn btn-danger mb-5 doc-status"  data-key="doc_file_rejected"  type="button">Reddet</button>
                                                </div>`,
                                        willOpen : async () => {
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
                                                                Swal.showValidationMessage(`Request failed: ${error}`);
                                                            }
                                                        }
                                                    });
                                                });
                                            });
                                        }
                                    });
                                };
                            } else {
                                pill.style.cursor = 'default';
                            }
                            return pill;
                        }
                    },{
                        title : 'Detaylar',
                        key   : 'id',
                        order : false,
                        colAlign : 'center',
                        headAlign : 'center',
                        width : '15%',
                        type  : 'string',
                        columnFormatter : (elm,rowData,columnData) => {
                            const wrap = document.createElement('div');
                            wrap.classList.add('dlist-actions');
                            const mkBtn = (iconClass, title, variant, onClick) => {
                                const b = document.createElement('button');
                                b.type = 'button';
                                b.className = 'dlist-actions__btn '+variant;
                                b.title = title;
                                b.innerHTML = `<i class="ki-outline ${iconClass} fs-3"></i>`;
                                b.onclick = onClick;
                                return b;
                            };
                            wrap.appendChild(mkBtn('ki-notepad-edit','Detay','is-detail', () => this.showDetailModal(rowData)));
                            wrap.appendChild(mkBtn('ki-eye','Önizle','is-view', () => window.open('/order-file/'+(rowData?.id ?? rowData?.file))));
                            wrap.appendChild(mkBtn('ki-magnifier','İlişkiye Git','is-link', () => {
                                switch(rowData.relation_type){
                                    case 'op-doc-client':
                                        this.$router.push({ name: 'CForm' , params: { id: rowData.relation_qnid }});
                                        break;
                                    case  'op-doc-offer':
                                        this.$router.push({ name: 'OForm' , params: { id: rowData.relation_qnid }});
                                        break;
                                }
                            }));
                            return this.useAuthStore().permissions?.includes('per-07') ? wrap : wrap;
                        }
                    }
                ];
                
                const isMobile = window.innerWidth < 768;
                const tableHeight = isMobile ? '50vh' : '70vh';
                const pageLimit = isMobile ? 5 : 10;

                this.table = new PickleTable({
                    container : '#div_table',
                    headers   : headers,
                    pageLimit : pageLimit,
                    height    : tableHeight,
                    type      : 'ajax',
                    columnSearch : true,
                    paginationType : 'scroll',
                    groupBy : 'group_key',
                    groupFormatter : (groupValue, rowCount) => {
                        if(!groupValue || groupValue === 'null' || groupValue === '') return `Belge — ${rowCount} belge`;
                        // rich html: order_no + count pill - pickletable renders as text, so we return plain; styled via CSS + JS after?
                        // We'll return string and style via CSS; count part will be wrapped via JS after render
                        return `${groupValue} — ${rowCount} belge`;
                    },
                    groupToggleCallback : null,
                    ajax:{
                        url:'/api/v1/table/document_files',
                        data:{}
                    },
                    initialFilter : [],
                    nextPageIcon : '<i class="ki-outline ki-arrow-right "></i>',
                    prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter:(elm,data)=>{
                        // Preserve file's real created_at before EAV overwrite (EAV has DD/MM/YYYY which breaks dayjs)
                        const fileCreatedAt = data.created_at || '';
                        data._file_created_at = fileCreatedAt;
                        if(fileCreatedAt){
                            const d = dayjs(fileCreatedAt);
                            data._created_at_fmt = d.isValid() ? d.format('DD/MM/YYYY HH:mm') : '—';
                        } else {
                            data._created_at_fmt = '—';
                        }
                        if(data.relation_detail && data.relation_detail !== 'null'){
                            try {
                                JSON.parse(data.relation_detail).forEach(element => {
                                    if(element && element.Key){
                                        const rawKey = element.Key.split('**')[0] || element.Key;
                                        // Don't overwrite core file columns (file CreatedAt, id, file, qnid)
                                        if(['created_at','id','file','qnid','_created_at_fmt','_file_created_at'].includes(rawKey)){
                                            data['eav_'+rawKey] = element.Value;
                                        } else {
                                            data[rawKey] = element.Value;
                                        }
                                        data[element['Key']] = element.Value;
                                    }
                                });
                            } catch(e){}
                        }
                        return data;
                    },
                });
                // enhance group headers after render (inner card, full-length, head-safe)
                const enhanceGroupHeader = (td) => {
                    if (td.querySelector('.dlist-group__inner')) return;
                    const tr = td.closest('tr');
                    const collapsed = tr?.dataset.collapsed === 'true';
                    const raw = td.textContent.trim().replace(/^›\s*/, '');
                    const m = raw.match(/^(.+)\s—\s(\d+ belge)$/);
                    if(!m) return;
                    const cleanNo = (tr?.dataset.groupValue || m[1]).trim().replace(/^›\s*/, '');
                    const cnt = m[2].trim();
                    td.innerHTML = '';
                    td.style.padding = '6px 0';
                    td.style.background = 'transparent';
                    td.style.border = 'none';
                    const inner = document.createElement('div');
                    inner.className = 'dlist-group__inner';
                    inner.innerHTML = `<span class="group-toggle-icon" style="transform:${collapsed ? 'rotate(-90deg)' : 'rotate(0deg)'}">›</span><span class="dlist-group__icon"><i class="ki-outline ki-folder fs-5"></i></span><span class="dlist-group__no">${cleanNo}</span><span class="dlist-group__count">${cnt}</span>`;
                    td.appendChild(inner);
                };
                setTimeout(()=> {
                    document.querySelectorAll('.table-group-header td').forEach(enhanceGroupHeader);
                }, 400);
                // re-enhance on pagination scroll / new pages
                const obs = new MutationObserver(()=> {
                    document.querySelectorAll('.table-group-header td:not(:has(.dlist-group__inner))').forEach(enhanceGroupHeader);
                });
                const container = document.getElementById('div_table');
                if(container) obs.observe(container, { childList:true, subtree:true });
            },
        }
    }

</script>
<template>
    <div class="dlist-page">
        <div class="dlist-card card">
            <div class="dlist-toolbar">
                <div class="dlist-toolbar__left">
                    <div class="dlist-search">
                        <i class="ki-outline ki-magnifier dlist-search__icon"></i>
                        <input type="text" class="dlist-search__input" id="mainSearch" placeholder="Dosya Ara — belge başlığı, sipariş no, ürün..." @keydown.enter="searchTable">
                    </div>
                    <button type="button" class="dlist-btn dlist-btn--primary" @click="searchTable"><i class="ki-outline ki-magnifier fs-5"></i> Ara</button>
                    <button type="button" class="dlist-btn dlist-btn--ghost" @click="resetSearch">Sıfırla</button>
                </div>
                <div class="dlist-toolbar__right">
                    <button type="button" class="dlist-btn dlist-btn--export" @click="exportTable"><i class="ki-outline ki-file-down fs-5"></i> Excel Çıktı</button>
                </div>
            </div>
            <div class="dlist-tablewrap">
                <div id="div_table"></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.dlist-page { padding-top: 12px; }
.dlist-card {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: visible;
    background: #fff;
    box-shadow: 0 8px 32px rgba(15,23,42,.06), 0 1px 3px rgba(15,23,42,.04);
}
.dlist-tablewrap {
    border-radius: 0 0 16px 16px;
    overflow: hidden;
}
.dlist-toolbar {
    display:flex; align-items:center; justify-content:space-between;
    gap:16px; padding:14px 18px;
    background:#fff;
    border-bottom:1px solid #f1f5f9;
    flex-wrap:wrap;
}
.dlist-toolbar__left { display:flex; align-items:center; gap:10px; flex:1 1 auto; flex-wrap:wrap; }
.dlist-toolbar__right { display:flex; align-items:center; gap:10px; }
.dlist-search {
    position:relative; display:flex; align-items:center;
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:999px;
    padding:0 14px 0 38px; height:40px; min-width:320px; flex:1; max-width:520px;
    transition: all .18s;
}
.dlist-search:focus-within { background:#fff; border-color:#cbd5e1; box-shadow:0 0 0 4px rgba(59,130,246,.08); }
.dlist-search__icon { position:absolute; left:12px; font-size:18px; color:#94a3b8; }
.dlist-search__input {
    border:none; outline:none; background:transparent; width:100%;
    font-size:13.5px; color:#0f172a;
}
.dlist-search__input::placeholder { color:#94a3b8; }
.dlist-btn {
    height:40px; padding:0 18px; border-radius:999px; border:1px solid transparent;
    font-size:13px; font-weight:700; letter-spacing:.01em;
    display:inline-flex; align-items:center; gap:7px; cursor:pointer;
    transition: all .16s; white-space:nowrap;
}
.dlist-btn--primary { background:#0f172a; color:#fff; border-color:#0f172a; }
.dlist-btn--primary:hover { background:#1e293b; transform:translateY(-1px); box-shadow:0 8px 18px rgba(15,23,42,.18); }
.dlist-btn--ghost { background:#f1f5f9; color:#334155; border-color:#e2e8f0; }
.dlist-btn--ghost:hover { background:#e2e8f0; }
.dlist-btn--export { background:#fff; color:#0f172a; border-color:#e2e8f0; }
.dlist-btn--export:hover { background:#f8fafc; border-color:#cbd5e1; }

.dlist-tablewrap { padding:0; }

#div_table {
    width: 100%;
    overflow: visible;
}
:deep(.pickletable){ overflow: visible !important; display:block !important; }
:deep(.pickletable .divTable){ overflow: visible !important; border:none !important; }
:deep(.pickletable table){ table-layout: fixed !important; width:100% !important; min-width:0 !important; border:none !important; margin:0 !important; border-collapse: collapse !important; background: #fff !important; }
:deep(.pickletable .table-responsive){ overflow:visible !important; }

/* hide first hidden pickletable column */
:deep(.pickletable tr:not(.table-group-header) td:first-child),
:deep(.pickletable th:first-child){ display:none; }

/* ——— table head ——— */
:deep(.pickletable thead th){
    background:#f8fafc !important;
    color:#475569 !important;
    font-size:11.5px !important;
    font-weight:700 !important;
    letter-spacing:.06em !important;
    text-transform:uppercase !important;
    border-bottom:1px solid #e2e8f0 !important;
    border-top:none !important;
    padding:10px 14px !important;
    white-space:nowrap;
}
:deep(.pickletable thead th i){ opacity:.45; }

/* column filter inputs - second header row */
:deep(.pickletable thead tr:nth-child(2) th){
    background:#fff !important;
    padding:8px 10px !important;
    border-bottom:1px solid #eef2f7 !important;
}
:deep(.pickletable thead input){
    height:34px !important; border-radius:10px !important;
    border:1px solid #e2e8f0 !important; background:#f8fafc !important;
    font-size:12.5px !important; padding:0 10px !important;
    transition:.15s !important;
}
:deep(.pickletable thead input:focus){
    background:#fff !important; border-color:#93c5fd !important;
    box-shadow:0 0 0 3px rgba(59,130,246,.12) !important; outline:none !important;
}
:deep(.pickletable thead input::placeholder){ color:#94a3b8 !important; }

/* ——— body rows ——— */
:deep(.pickletable tbody tr:not(.table-group-header)){
    border-bottom:1px solid #f1f5f9 !important;
    transition: background .12s;
}
:deep(.pickletable tbody tr:not(.table-group-header):hover){
    background:#f8fafc !important;
}
:deep(.pickletable tbody td){
    padding:12px 12px !important;
    vertical-align:middle !important;
    font-size:13px !important;
    color:#0f172a !important;
    border:none !important;
    overflow:hidden;
}
:deep(.pickletable tbody tr:last-child){ border-bottom:none !important; }

/* group header - premium (inner flex card, full-length, head safe) */
:deep(.table-group-header){
    display:table-row !important;
}
:deep(.table-group-header td){
    display:table-cell !important; /* keep table layout for colspan */
    padding:6px 0 !important; /* gap between groups */
    background: transparent !important; border:none !important;
    box-shadow:none !important;
}
:deep(.dlist-group__inner){
    display:flex !important; align-items:center !important; gap:10px !important;
    width:100% !important; box-sizing:border-box !important;
    background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%) !important;
    border:1px solid #e0e7ff !important; border-left:3px solid #6366f1 !important;
    border-radius:12px !important;
    padding:12px 16px !important;
    box-shadow: 0 2px 8px rgba(99,102,241,.06), 0 1px 2px rgba(15,23,42,.04) !important;
    cursor:pointer !important; user-select:none !important;
    white-space:nowrap !important; overflow:hidden !important;
    transition: all .15s ease !important;
}
:deep(.table-group-header:hover .dlist-group__inner){
    background: linear-gradient(135deg, #e0e7ff 0%, #eef2ff 70%, #fff 100%) !important;
    border-color:#c7d2fe !important;
    box-shadow: 0 6px 16px rgba(99,102,241,.12) !important;
}
:deep(.group-toggle-icon){
    display:inline-flex !important; align-items:center; justify-content:center;
    width:28px; height:28px; border-radius:9px;
    background: linear-gradient(135deg,#6366f1,#4f46e5) !important; border:none !important;
    color:#fff !important; font-size:14px !important; font-weight:800 !important;
    flex-shrink:0 !important; line-height:1 !important;
    box-shadow: 0 2px 8px rgba(99,102,241,.30) !important;
    transition: transform .2s ease !important;
}
:deep(tbody tr[data-group][data-collapsed="true"]){ display:none !important; }
:deep(.dlist-group__icon){
    display:inline-flex; align-items:center; justify-content:center;
    width:30px; height:30px; border-radius:10px;
    background:#eef2ff; border:1px solid #e0e7ff; color:#6366f1;
    flex-shrink:0; font-size:16px;
}
:deep(.dlist-group__no){ color:#1e1b4b; font-weight:800; font-size:14px; letter-spacing:.01em; }
:deep(.dlist-group__count){
    display:inline-flex; align-items:center; gap:6px; margin-left:auto;
    background:#eef2ff; border:1px solid #c7d2fe; color:#4338ca;
    font-size:12px; font-weight:800; padding:5px 12px; border-radius:999px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.6);
}
:deep(.dlist-group__count::before){
    content:""; display:inline-block; width:6px; height:6px; border-radius:999px; background:#6366f1; opacity:.9;
}

/* file cell */
:deep(.dlist-filecell){ display:flex; align-items:center; gap:11px; min-width:0; }
:deep(.dlist-filecell__icon){
    width:38px; height:38px; border-radius:11px; display:inline-flex; align-items:center; justify-content:center;
    background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; flex-shrink:0;
}
:deep(.dlist-filecell__icon.is-test){ background:#fef3ff; color:#a21caf; border-color:#f5d0fe; }
:deep(.dlist-filecell__icon.is-cins){ background:#fff7ed; color:#c2410c; border-color:#fed7aa; }
:deep(.dlist-filecell__icon.is-kabul){ background:#ecfdf5; color:#047857; border-color:#a7f3d0; }
:deep(.dlist-filecell__title){ font-weight:700; font-size:13px; color:#0f172a; line-height:1.25; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
:deep(.dlist-filecell__text){ min-width:0; overflow:hidden; }
:deep(.dlist-filecell__sub){ font-size:11px; color:#94a3b8; margin-top:2px; }

/* relation badge */
:deep(.dlist-relation__badge){
    display:inline-flex; align-items:center; padding:5px 11px; border-radius:999px;
    font-size:12px; font-weight:700; letter-spacing:.01em; border:1px solid transparent;
    max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
:deep(.dlist-relation__badge.is-product){ background:#eef2ff; color:#4338ca; border-color:#c7d2fe; }
:deep(.dlist-relation__badge.is-client){ background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }
:deep(.dlist-relation__badge.is-offer){ background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
:deep(.dlist-relation__badge.is-empty){ background:#f8fafc; color:#94a3b8; border-color:#e2e8f0; }

/* date */
:deep(.dlist-date){ display:inline-flex; align-items:center; gap:2px; color:#334155; font-weight:600; font-size:12.5px; white-space:nowrap; }
:deep(.dlist-date__text){ color:#0f172a; }

/* status pill */
:deep(.dlist-pill){
    display:inline-flex; align-items:center; gap:8px;
    height:34px; padding:0 14px; border-radius:999px; border:1px solid transparent;
    font-size:12px; font-weight:800; letter-spacing:.01em; white-space:nowrap;
    cursor:pointer; transition: all .15s; width:100%; max-width:240px; min-width:0; justify-content:flex-start;
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
    overflow:hidden; text-overflow:ellipsis;
}
:deep(.dlist-pill span){ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0; }
:deep(.dlist-pill:hover){ transform:translateY(-1px); box-shadow:0 6px 16px rgba(15,23,42,.08); }
:deep(.dlist-pill.is-waiting){ background:#f8fafc; color:#475569; border-color:#e2e8f0; }
:deep(.dlist-pill.is-refreshed){ background:#fffbeb; color:#92400e; border-color:#fde68a; }
:deep(.dlist-pill.is-accepted){ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
:deep(.dlist-pill.is-rejected){ background:#fff1f2; color:#9f1239; border-color:#fecdd3; }
:deep(.dlist-pill.is-waiting i){ color:#64748b; }
:deep(.dlist-pill.is-refreshed i){ color:#d97706; }
:deep(.dlist-pill.is-accepted i){ color:#059669; }
:deep(.dlist-pill.is-rejected i){ color:#e11d48; }

/* actions */
:deep(.dlist-actions){ display:flex; align-items:center; justify-content:center; gap:6px; }
:deep(.dlist-actions__btn){
    width:36px; height:36px; border-radius:12px; border:1px solid #e2e8f0;
    background:#fff; color:#334155; display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer; transition: all .14s;
}
:deep(.dlist-actions__btn:hover){ transform:translateY(-1px); box-shadow:0 8px 18px rgba(15,23,42,.08); }
:deep(.dlist-actions__btn.is-detail){ background:#f8fafc; }
:deep(.dlist-actions__btn.is-detail:hover){ background:#eef2ff; color:#4338ca; border-color:#c7d2fe; }
:deep(.dlist-actions__btn.is-view){ background:#fff; }
:deep(.dlist-actions__btn.is-view:hover){ background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }
:deep(.dlist-actions__btn.is-link){ background:#fff; }
:deep(.dlist-actions__btn.is-link:hover){ background:#f8fafc; color:#0f172a; border-color:#cbd5e1; }

/* document-card mobile */
:deep(.document-card){ width:100%; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden; background:#fff; box-shadow:0 8px 24px rgba(15,23,42,.06); }
:deep(.document-card__header){ display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding:16px 16px 12px; background:linear-gradient(135deg, #eef2ff, #f8fafc); }
:deep(.document-card__badge){ display:inline-flex; align-items:center; justify-content:center; padding:4px 12px; border-radius:999px; background:#fff; border:1px solid #e0e7ff; color:#4338ca; font-size:.78rem; font-weight:700; margin-bottom:8px; }
:deep(.document-card__title){ margin:0; font-size:1rem; font-weight:800; color:#0f172a; }
:deep(.document-card__subtitle){ margin:4px 0 0; color:#64748b; font-size:.86rem; }
:deep(.document-card__status){ align-self:center; padding:6px 12px; border-radius:999px; background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; font-size:.78rem; font-weight:800; white-space:nowrap; }
:deep(.document-card__status--clickable){ cursor:pointer; transition:.2s; }
:deep(.document-card__status--clickable:hover){ background:#d1fae5; transform:translateY(-1px); }
:deep(.document-card__body){ display:grid; gap:10px; padding:0 16px 14px; }
:deep(.document-card__row){ display:grid; grid-template-columns:1fr auto; gap:10px; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9; }
:deep(.document-card__row:last-child){ border-bottom:none; }
:deep(.document-card__label){ color:#64748b; font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; }
:deep(.document-card__value){ color:#0f172a; font-size:.95rem; font-weight:700; text-align:right; }
:deep(.document-card__footer){ display:flex; flex-wrap:wrap; gap:8px; padding:10px 16px 16px; border-top:1px solid #f1f5f9; }
:deep(.document-card__action-btn){ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:10px; border:1px solid #e2e8f0; background:#fff; color:#334155; font-size:.82rem; font-weight:700; cursor:pointer; }
:deep(.document-card__action-btn:hover){ background:#f8fafc; }

@media (max-width: 767px) {
    .dlist-toolbar{ flex-direction:column; align-items:stretch; }
    .dlist-search{ min-width:0; max-width:none; }
    :deep(.pickletable thead){ display:none !important; }
    :deep(.pickletable tr:not(.table-group-header) td:first-child),
    :deep(.pickletable tr:not(.table-group-header) th:first-child){ display:table-cell !important; width:100% !important; max-width:100% !important; }
    :deep(.pickletable tr:not(.table-group-header) td:not(:first-child)),
    :deep(.pickletable tr:not(.table-group-header) th:not(:first-child)){ display:none !important; }
    :deep(.pickletable tbody tr){ border:unset !important; border-bottom:unset !important; }
    :deep(.pickletable table){ table-layout:auto !important; min-width:unset !important; width:100% !important; }
    :deep(.pickletable th), :deep(.pickletable td){ font-size:.75rem !important; }
}
</style>
