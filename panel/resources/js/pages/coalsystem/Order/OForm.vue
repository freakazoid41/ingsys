<script>
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
            return { useNavigationStore, useFormDataStore, Plib, Swal, useRoute, useAuthStore }
        },
        async mounted(){
            this.navigationStore.toggle(true);
            // Orders come from SAP — form requires a document id. No id = redirect to list.
            if(this.id === undefined || this.id === ''){
                this.$router.replace({ name: 'OrderList' });
                return;
            }
            const response = await this.plib.request({ url:'/api/v1/document/'+this.id, method:'GET' },null);
            this.navigationStore.toggle(true);
            this.formDataStore.setData(response?.data?.formFormat);
            this.formDataStore.rawData = response?.data || {};
            this.rawData = response?.data || {};
            try {
                this.parsedStatus = JSON.parse(this.rawData?.document?.status || '[]');
            } catch(e) { this.parsedStatus = []; }
            this.loadForm = true;
            setTimeout(() => this.navigationStore.toggle(false), 400);
            // New rule: if order was partitioned before (has active EBELN-X clones), force partial
            this.checkHasPartitions();
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
                parsedStatus: [],
                transferMode: 'at_once',
                selectedItems: [],
                allItemSerials: [],
                highlightItemQnid: null,
                hasPartitions: false,
                checkingPartitions: false,
                itemFiles: {},
                itemRemovedFiles: [],
                printingKabul: false,
                printingCins: false,
            };
        },
        computed: {
            orderStatus(){
                if(Array.isArray(this.parsedStatus) && this.parsedStatus.length) return this.parsedStatus[this.parsedStatus.length-1].op_key || '';
                return '';
            },
            lockedStatuses(){ return ['doc_trans_order_transfer_sent','doc_trans_order_ready_for_shipment','doc_trans_order_approved','doc_trans_order_rejected','doc_trans_order_files_rejected','doc_trans_transfer_sent','doc_trans_transfer_approved','doc_trans_transfer_rejected']; },
            isLocked(){ return this.id && this.lockedStatuses.includes(this.orderStatus); },
            canSend(){ return this.id && this.orderStatus === 'doc_trans_order_created'; },
            canPrintKabul(){ return this.canSend || this.orderStatus === 'doc_trans_order_files_rejected'; },
            storedTransferMode(){
                const entities = this.orderFormEntities;
                if(entities.transfer_mode) return entities.transfer_mode;
                // Fallback: clone orders without stored transfer_mode are always partial
                if(this.isCloneOrder) return 'partial';
                return '';
            },
            isFilesLocked(){ return this.id && ['doc_trans_order_transfer_sent','doc_trans_order_ready_for_shipment','doc_trans_order_approved','doc_trans_order_rejected','doc_trans_transfer_sent','doc_trans_transfer_approved','doc_trans_transfer_rejected'].includes(this.orderStatus); },
            readonlyFields(){
                if(!this.isLocked) return [];
                const fields = ['order_desc','imalatci_firma_adi'];
                if(this.isFilesLocked) fields.push('transfer_kabul_file','transfer_cins_file');
                return fields;
            },
            isCloneOrder(){
                const orderNo = this.orderEntities?.order_no || '';
                return /\-\d+$/.test(orderNo);
            },
            parentOrderNo(){
                const orderNo = this.orderEntities?.order_no || '';
                return this.isCloneOrder ? orderNo.replace(/\-\d+$/, '') : null;
            },
            orderEntities(){
                return this.orderFormEntities;
            },
            orderFormEntities(){
                const form = this.rawData?.formFormat?.['op-doc-order-form'] || this.formDataStore?.rawData?.formFormat?.['op-doc-order-form'] || {};
                for (const connId in form) {
                    const entities = form[connId]?.entities;
                    if (entities && (entities.order_no || entities.transfer_mode)) return entities;
                }
                return {};
            },
            isAtOnceDisabled(){
                return this.hasPartitions;
            },
        },
        methods: {
            validatePartial(){
                if(!this.selectedItems.length){
                    this.plib.toast(this.Swal,'info','Parçalı transfer için en az bir kalem seçmelisiniz.',()=>{});
                    return false;
                }
                const invalid = this.selectedItems.find(i => !i.amount || i.amount <= 0);
                if(invalid){
                    this.plib.toast(this.Swal,'info','Her kalem için geçerli bir bölme miktarı girmelisiniz.',()=>{});
                    return false;
                }
                for(const item of this.selectedItems){
                    if(!item.serials || !item.serials.length) continue;
                    for(const s of item.serials){
                        // KG/M: production_date not required (auto-filled from order date)
                    }
                }
                return true;
            },
            validateAtOnceSerials(){
                for(const item of this.allItemSerials){
                    if(item.unit === 'ST') continue;
                    let sum = 0;
                    for(const s of item.serials){
                        // KG/M: production_date not required (auto-filled from order date)
                        const sq = parseFloat(s.quantity);
                        if(isNaN(sq) || sq <= 0){
                            this.highlightItemQnid = null;
                            this.$nextTick(()=>{ this.highlightItemQnid = item.qnid; });
                            this.plib.toast(this.Swal,'info','Her seri miktarı sıfırdan büyük olmalıdır.',()=>{});
                            return false;
                        }
                        sum += sq;
                    }
                    if(Math.abs(sum - item.quantity) > 0.01){
                        this.highlightItemQnid = null;
                        this.$nextTick(()=>{ this.highlightItemQnid = item.qnid; });
                        this.plib.toast(this.Swal,'info', item.unit + ' toplamı ' + item.quantity + ' olmalıdır. Şu an: ' + sum.toFixed(2),()=>{});
                        return false;
                    }
                }
                return true;
            },
            async checkHasPartitions(){
                try {
                    const orderNo = this.orderEntities?.order_no || '';
                    if(!orderNo) return;
                    const baseNo = orderNo.replace(/-\d+$/, '');
                    if(!baseNo) return;
                    // clones are op-doc-order with order_no = baseNo + '-X', active only (tableList filters status=1)
                    this.checkingPartitions = true;
                    const fd = new FormData();
                    fd.append('tableReq', JSON.stringify({
                        filter: [
                            { key:'form-type', type:'=', value:'op-doc-order-form' },
                            { key:'type', type:'=', value:'op-doc-order' },
                            { key:'all', type:'=', value: baseNo + '-' }
                        ],
                        scale: { page: 1, limit: 100 },
                        order: { key: 'id', style: 'asc' }
                    }));
                    const rsp = await this.plib.request({url:'/api/v1/table/documents', method:'POST'}, null, fd);
                    const rows = rsp?.data?.data || rsp?.data || [];
                    const list = Array.isArray(rows) ? rows : (rows?.data || []);
                    let found = false;
                    for(const r of list){
                        try {
                            const attrs = JSON.parse(r.main_attr || '[]');
                            const noAttr = attrs.find(a => a.Key === 'order_no');
                            const v = noAttr ? String(noAttr.Value) : '';
                            if(v && v !== orderNo && v.startsWith(baseNo + '-') && /-\d+$/.test(v)){
                                // tableList already filters active, so any hit = active partition
                                // also ensure it's not the current doc itself
                                if(String(r.id) !== String(this.id)) found = true;
                                // even if r.id is current clone, still counts as partition exists for base
                                // for base order, any clone means partitioned before
                                if(!this.isCloneOrder && v.startsWith(baseNo + '-')) found = true;
                            }
                        } catch(e){}
                    }
                    // If we are viewing the base order, any clone found means partitioned before
                    // If we are viewing a clone, don't apply rule to itself
                    if(!this.isCloneOrder) this.hasPartitions = found;
                    else this.hasPartitions = false;
                    if(this.hasPartitions && this.transferMode === 'at_once'){
                        this.transferMode = 'partial';
                    }
                } catch(e) {
                    console.error('checkHasPartitions failed', e);
                } finally {
                    this.checkingPartitions = false;
                }
            },
            buildTransferPayload(){
                if(!this.canSend || !this.transferMode) return;
                if(this.hasPartitions && this.transferMode === 'at_once'){
                    this.plib.toast(this.Swal,'info','Bu sipariş daha önce parçalı gönderildi. Artık sadece parçalı gönderim yapılabilir.',()=>{});
                    return false;
                }
                this.formData.transfer_mode = this.transferMode;
                if(this.transferMode === 'partial'){
                    if(!this.validatePartial()) return false;
                    this.formData.selected_items = this.selectedItems;
                }
                if(this.transferMode === 'at_once' && this.allItemSerials.length){
                    if(!this.validateAtOnceSerials()) return false;
                    this.formData.item_serials = this.allItemSerials;
                }
                return true;
            },
            async submitForm(formData){
                this.formData = formData;
                this.navigationStore.toggle(true);
                this.formData.typeKey = 'op-doc-order';

                if(this.buildTransferPayload() === false){
                    this.navigationStore.toggle(false);
                    return;
                }

                const rsp = this.plib.checkForm('.form-item');
                if(rsp.valid){
                    // Save item files BEFORE the order save. The partial-transfer flow
                    // (processOrderTransfer → moveOrderFilesToDocument) can only move item
                    // files that are already linked (finalized + entities) to the original
                    // items. Saving after the order PUT leaves them on the originals and the
                    // clone ships without test docs/images.
                    if(!(await this.saveItemFiles())) return;
                    const envelope = new FormData();
                    envelope.append('data', JSON.stringify(this.formData));
                    for(const [key, fileItem] of Object.entries(this.formData.files || {})){
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
                    setTimeout(async () => {
                        this.navigationStore.toggle(false);
                        const msg = response?.data?.transfer_msg || response.msg || 'İşlem Tamamlandı';
                        // Slutty helper: if order still in files_rejected, list which slots are still rejected
                        let still = [];
                        try {
                            const detail = response?.detail || response?.data?.detail || {};
                            const docStatus = detail?.document?.status ? JSON.parse(detail.document.status) : [];
                            const lastOp = Array.isArray(docStatus) && docStatus.length ? docStatus[docStatus.length - 1]?.op_key : '';
                            if (lastOp === 'doc_trans_order_files_rejected') {
                                const orderForm = detail?.formFormat?.['op-doc-order-form'] || {};
                                for (const cid in orderForm) {
                                    const ents = orderForm[cid].entities || {};
                                    for (const tag in ents) {
                                        if (!tag.includes('transfer_kabul_file') && !tag.includes('transfer_cins_file')) continue;
                                        let parsed = null;
                                        try { parsed = JSON.parse(ents[tag]); } catch(e) { continue; }
                                        if (parsed?.last_status?.op_key === 'doc_file_rejected') {
                                            still.push(tag.includes('transfer_kabul') ? 'Malzeme Kabul' : 'Malzeme Cins-Miktar');
                                        }
                                    }
                                }
                                const it = this.$refs.itemTable;
                                if (it) {
                                    for (const item of it.items || []) {
                                        const ex = it.existingTestFiles?.[item.id]?.[0];
                                        const replaced = it.itemTestFiles?.[item.id]?.reference;
                                        if (ex?.last_status?.op_key === 'doc_file_rejected' && !replaced) {
                                            still.push(`Test: ${item.prod_code || item.title || item.id}`);
                                        }
                                    }
                                }
                                still = [...new Set(still)];
                            }
                        } catch(e) {}
                        if (still.length) {
                            const html = `<div style="text-align:left"><div style="font-weight:700;color:#0f172a;margin-bottom:6px;">Kaydedildi — hala reddedilen dosyalar var</div><div style="font-size:0.88rem;color:#475569;margin-bottom:8px;">${still.join(', ')}</div><div style="font-size:0.82rem;color:#64748b;">Bu dosyaları yenileyip tekrar Kaydet yapın, aksi halde durum <b>Reddedilen Dosyalar Mevcut</b> olarak kalır.</div></div>`;
                            this.Swal.fire({ icon: 'info', html, confirmButtonText: 'Anladım', allowOutsideClick: false }).then(() => {
                                if (response.success) this.$router.push({ name: 'OrderList' });
                            });
                        } else {
                            this.plib.toast(this.Swal, response.success ? 'success' : 'error', msg,() => {
                                if(response.success) this.$router.push({ name: 'OrderList' });
                            });
                        }
                    }, 300);
                }else{
                    this.navigationStore.toggle(false);
                    this.plib.toast(this.Swal,'info','Eksik Alanları Doldurmalısınız..',()=>{});
                }
            },
            onItemsSelected(items){
                this.selectedItems = items;
            },
            onItemSerials(serials){
                this.allItemSerials = serials;
            },
            onItemFiles(files){
                this.itemFiles = files;
                // Track removed existing files — will be added to removedData on next save
                if(files.removedFiles && files.removedFiles.length){
                    if(!this.itemRemovedFiles) this.itemRemovedFiles = [];
                    for(const rf of files.removedFiles){
                        if(!this.itemRemovedFiles.find(r => r.id === rf.id)){
                            this.itemRemovedFiles.push(rf);
                        }
                    }
                }
            },
            async cancelOrder(){
                const isPartial = this.isCloneOrder;
                const conf = await this.Swal.fire({
                    title: isPartial ? 'Parçayı Sil' : 'Siparişi İptal Et',
                    text: isPartial ? 'Bu parça (EBELN-X) silinecek ve miktarları ana siparişe geri eklenecek. Emin misiniz?' : 'Sipariş tamamen reddedilecek ve iptal edilecek. Emin misiniz?',
                    icon:'warning', showCancelButton:true, confirmButtonText: isPartial ? 'Evet, Sil' : 'Evet, İptal Et', cancelButtonText:'Vazgeç',
                });
                if(!conf.isConfirmed) return;
                const fd=new FormData(); fd.append('id',this.id); fd.append('note', isPartial ? 'Parça silindi, miktarlar ana siparişe iade edildi' : 'Sipariş reddedildi ve iptal edildi');
                const rsp=await this.plib.request({url:'/api/v1/orders/cancel', method:'POST'}, null, fd);
                this.plib.toast(this.Swal, rsp.success?'success':'error', rsp.msg||'İşlem Tamamlandı',()=>{
                    if(rsp.success) this.$router.push({name:'OrderList'});
                });
            },
            async saveItemFiles(){
                const tf = this.itemFiles?.testFiles || {};
                const imgs = this.itemFiles?.images || {};
                const connIds = this.itemFiles?.connIds || {};
                const removedFiles = this.itemRemovedFiles || [];
                const hasAny = Object.keys(tf).some(k => tf[k]?.reference) || Object.keys(imgs).some(k => (imgs[k]||[]).length > 0) || removedFiles.length > 0;
                if(!hasAny) return true;
                const itemTable = this.$refs.itemTable;
                if(!itemTable || !itemTable.items) return true;
                // Process removed existing files first (test docs + product images)
                for(const rf of removedFiles){
                    const connId = rf.connId || rf.id;
                    const key = rf.key;
                    if(!connId || !key) continue;
                    const item = itemTable.items.find(i => i._fileConnId == connId);
                    if(!item) continue;
                    const itemQnid = item._raw?.qnid || item.id_qnid || item.id;
                    try {
                        const envelope = new FormData();
                        envelope.append('data', JSON.stringify({
                            typeKey: 'op-doc-order-item',
                            removedData: [{ id: connId, key: key }]
                        }));
                        await this.plib.request({
                            url: '/api/v1/document/' + itemQnid,
                            method: 'PUT'
                        }, null, envelope);
                    } catch(e) {
                        console.error('Remove file failed', e, rf);
                    }
                }
                this.itemRemovedFiles = [];
                if(itemTable) itemTable._removedExistingFiles = [];
                for(const item of itemTable.items){
                    const testFile = tf[item.id];
                    const images = imgs[item.id] || [];
                    const hasTest = testFile && testFile.reference;
                    const hasImages = images.length > 0;
                    if(!hasTest && !hasImages) continue;
                    // Use qnid (UUID) for API — registerContent expects qnid, not numeric id
                    const itemQnid = item._raw?.qnid || item.id_qnid || item.id;
                    // Use existing connId from SAP sync, or generate new rowId
                    const rowId = connIds[item.id] || ('new-' + Date.now());
                    try {
                        const envelope = new FormData();
                        const dynamicF = {};
                        const filesObj = {};
                        dynamicF['op-doc-order-item-form**' + rowId] = {
                            tag: 'op-doc-order-item-form',
                            entities: {}
                        };
                        if(hasTest){
                            // Include existing file ID in key so registerContent detects replacement
                            const existingId = (this.$refs.itemTable?.existingTestFiles?.[item.id]?.[0]?.id) || 0;
                            const fileKey = 'item_test_docs**dynamicFile**' + existingId + '**' + rowId + '*-*item_test_file**item_test_docs**' + rowId;
                            filesObj[fileKey] = testFile.reference;
                        }
                        if(hasImages){
                            for(let i = 0; i < images.length; i++){
                                const img = images[i];
                                if(!img.reference) continue;
                                // Unique entity tag per image so multiple images coexist instead of
                                // replacing each other (shared tag = one slot = last one wins).
                                // Suffix carries a unique id — never reuse the same tag for a new image.
                                const imgTag = 'item_images_file**item_images**' + rowId + '**img-' + (img.uploadId || (Date.now() + '-' + i));
                                const fileKey = 'item_images**dynamicFile**' + i + '**' + rowId + '*-*' + imgTag;
                                filesObj[fileKey] = img.reference;
                            }
                        }
                        envelope.append('data', JSON.stringify({
                            typeKey: 'op-doc-order-item',
                            dynamicF
                        }));
                        for(const [key, ref] of Object.entries(filesObj)){
                            envelope.append(key, JSON.stringify(ref));
                        }
                        const itemRsp = await this.plib.request({
                            url: '/api/v1/document/' + itemQnid,
                            method: 'PUT'
                        }, null, envelope);
                        if(!itemRsp || itemRsp.success === false){
                            this.plib.toast(this.Swal, 'error', itemRsp?.msg || itemRsp?.message || 'Kalem dosyaları kaydedilemedi.');
                            return false;
                        }
                    } catch(e) {
                        console.error('Item file save failed for item', item.id, e);
                        this.plib.toast(this.Swal, 'error', 'Kalem dosyaları kaydedilemedi: ' + (e?.msg || e?.message || 'bilinmeyen hata'));
                        return false;
                    }
                }
                // Clear state so a retry of the order save doesn't re-send used references
                this.itemFiles = {};
                return true;
            },
            async navigateToParentOrder(){
                if(!this.parentOrderNo) return;
                try{
                    const fd = new FormData();
                    fd.append('tableReq', JSON.stringify({
                        filter: [
                            { key:'form-type', type:'=', value:'op-doc-order-form' },
                            { key:'type', type:'=', value:'op-doc-order' },
                            { key:'all', type:'=', value: this.parentOrderNo }
                        ],
                        scale: { page: 1, limit: 5 },
                        order: { key: 'id', style: 'asc' }
                    }));
                    const rsp = await this.plib.request({url:'/api/v1/table/documents', method:'POST'}, null, fd);
                    const rows = rsp?.data?.data || rsp?.data || [];
                    const list = Array.isArray(rows) ? rows : (rows?.data || []);
                    const match = list.find(r => {
                        try {
                            const attrs = JSON.parse(r.main_attr || '[]');
                            const orderNo = attrs.find(a => a.Key === 'order_no');
                            return orderNo && orderNo.Value === this.parentOrderNo;
                        } catch(e) { return false; }
                    });
                    if(match){
                        this.$router.push({name:'OrderForm', params:{id: match.id}});
                    } else {
                        this.plib.toast(this.Swal, 'info', 'Orijinal sipariş bulunamadı: ' + this.parentOrderNo);
                    }
                } catch(e) {
                    console.error('navigateToParentOrder failed', e);
                }
            },
            async printMalzemeKabul(){
                const showWarn = (title, text) => {
                    this.Swal.fire({ icon:'warning', title, text, confirmButtonText:'Tamam', allowOutsideClick:true });
                };
                const itemTable = this.$refs.itemTable;
                if(!itemTable || !itemTable.items || !itemTable.items.length){
                    showWarn('Kalemler Yüklenmedi', 'Kalem bilgileri henüz yüklenemedi. Sayfayı yenileyip tekrar deneyin.');
                    return;
                }
                // Determine transfer mode: use form state if canSend, else use DB stored value
                const effectiveTransferMode = this.canSend ? this.transferMode : this.storedTransferMode;
                if(!effectiveTransferMode){
                    showWarn('Transfer Türü Yok', 'Transfer türü bilgisi bulunamadı. Sayfayı yenileyip tekrar deneyin.');
                    return;
                }
                if(this.hasPartitions && effectiveTransferMode === 'at_once'){
                    showWarn('Tek Seferde Kilitli', 'Bu sipariş daha önce parçalı gönderildiği için artık sadece parçalı gönderim yapılabilir. Tüm parçalar silinirse tek seferde tekrar mümkün.');
                    return;
                }
                // Get field value: from form DOM if canSend, else from DB entities
                const getFieldValue = (name) => {
                    if(!this.canSend){
                        // DB-only mode: use orderEntities directly
                        const val = this.orderEntities[name];
                        return (val !== undefined && val !== null && String(val).trim() !== '') ? String(val).trim() : '';
                    }
                    const formComp = this.$refs.formRef;
                    if(formComp && formComp.getCurrentFormData){
                        try {
                            const fd = formComp.getCurrentFormData();
                            const dynF = fd?.dynamicF || {};
                            for(const key in dynF){
                                const e = dynF[key]?.entities;
                                if(!e) continue;
                                for(const ek in e){
                                    const base = ek.split('**')[0].split('*-*').pop();
                                    if(base === name && e[ek] !== undefined && String(e[ek]).trim() !== ''){
                                        return String(e[ek]).trim();
                                    }
                                }
                                if(e[name] !== undefined && String(e[name]).trim() !== '') return String(e[name]).trim();
                            }
                        } catch(e) {}
                    }
                    const allInputs = document.querySelectorAll('input[name^="'+name+'"], textarea[name^="'+name+'"]');
                    for(const inp of allInputs){
                        if(inp.value && String(inp.value).trim()) return String(inp.value).trim();
                    }
                    const anyInputs = document.querySelectorAll('.form-item');
                    for(const inp of anyInputs){
                        const n = inp.getAttribute('name') || '';
                        if(n === name || n.startsWith(name + '**')){
                            if(inp.value && String(inp.value).trim()) return String(inp.value).trim();
                        }
                    }
                    return '';
                };
                const desc = getFieldValue('order_desc') || this.orderEntities.order_desc || '';
                const imalatci = getFieldValue('imalatci_firma_adi') || this.orderEntities.imalatci_firma_adi || '';
                if(!imalatci){
                    showWarn('İmalatçı Firma Boş', 'İmalatçı Firma adı boş. Formu yazdırmadan önce imalatçı firma bilgisi girmelisiniz.');
                    return;
                }
                // For partial mode in files_rejected: selectedItems may be empty (form locked), treat all items as selected
                if(effectiveTransferMode === 'partial' && !this.selectedItems.length && !this.canSend){
                    // DB mode: use all items as selected (order was already sent with all items or subset)
                    // Fall through to use all items
                } else if(effectiveTransferMode === 'partial' && !this.selectedItems.length){
                    showWarn('Kalem Seçilmedi', 'Parçalı transfer seçtiniz ama henüz kalem işaretlemediniz. Gönderilecek kalemleri tablodan işaretleyin.');
                    return;
                }
                if(effectiveTransferMode === 'partial' && this.selectedItems.length){
                    for(const item of this.selectedItems){
                        if(!item.amount || item.amount <= 0){
                            showWarn('Bölme Miktarı Eksik', 'İşaretlenen kalemlerden birinin bölme miktarı girilmemiş. Tüm seçili kalemler için gönderilecek miktarı girin.');
                            return;
                        }
                    }
                }
                this.printingKabul = true;
                this.Swal.fire({ title:'PDF oluşturuluyor...', html:'<div style="display:flex;justify-content:center;padding:10px"><i class="ki-outline ki-loading" style="font-size:26px;animation:spin 1s linear infinite;color:#059669"></i></div><div style="font-size:0.85rem;color:#64748b">Lütfen bekleyin, form hazırlanıyor</div>', allowOutsideClick:false, showConfirmButton:false, didOpen:()=>this.Swal.showLoading() });
                const items = [];
                if(effectiveTransferMode === 'partial' && this.selectedItems.length){
                    for(const sel of this.selectedItems){
                        const item = itemTable.items.find(i => i.id == sel.qnid);
                        if(!item) continue;
                        items.push({
                            prod_code: item.prod_code || '',
                            title: item.title || '',
                            unit: item.unit || '',
                            quantity: item.quantity,
                            accept_quantity: sel.amount || item.quantity
                        });
                    }
                } else {
                    for(const item of itemTable.items){
                        items.push({
                            prod_code: item.prod_code || '',
                            title: item.title || '',
                            unit: item.unit || '',
                            quantity: item.quantity,
                            accept_quantity: item.quantity
                        });
                    }
                }
                let orderNo = this.orderEntities.order_no || '';
                const buyingNo = this.orderEntities.buying_no || '';
                const createdAt = this.orderEntities.created_at || '';
                if(effectiveTransferMode === 'partial'){
                    // If current order already has a suffix (e.g. 3510002100-1), it's a reprint — use as-is
                    if(!/\-\d+$/.test(orderNo)){
                        // Base order — calculate next clone suffix
                        const baseNo = orderNo;
                        try {
                            const cloneFd = new FormData();
                            cloneFd.append('tableReq', JSON.stringify({
                                filter: [
                                    { key:'form-type', type:'=', value:'op-doc-order-form' },
                                    { key:'type', type:'=', value:'op-doc-order' },
                                    { key:'all', type:'=', value: baseNo + '-' }
                                ],
                                scale: { page: 1, limit: 100 },
                                order: { key: 'id', style: 'asc' }
                            }));
                            const cloneRsp = await this.plib.request({url:'/api/v1/table/documents', method:'POST'}, null, cloneFd);
                            const cloneRows = cloneRsp?.data?.data || cloneRsp?.data || [];
                            const cloneList = Array.isArray(cloneRows) ? cloneRows : (cloneRows?.data || []);
                            let maxX = 0;
                            for(const r of cloneList){
                                try {
                                    const attrs = JSON.parse(r.main_attr || '[]');
                                    const noAttr = attrs.find(a => a.Key === 'order_no');
                                    if(noAttr && noAttr.Value){
                                        const m = noAttr.Value.match(/-(\d+)$/);
                                        if(m) maxX = Math.max(maxX, parseInt(m[1]));
                                    }
                                } catch(e){}
                            }
                            orderNo = baseNo + '-' + (maxX + 1);
                        } catch(e){
                            orderNo = baseNo + '-1';
                        }
                    }
                    // else: clone order already has suffix, use as-is for reprint
                }
                const fd = new FormData();
                fd.append('qnid', this.id);
                fd.append('transfer_mode', effectiveTransferMode);
                fd.append('items', JSON.stringify(items));
                fd.append('order_no', orderNo);
                fd.append('buying_no', buyingNo);
                fd.append('created_at', createdAt);
                fd.append('order_desc', desc);
                fd.append('imalatci_firma_adi', imalatci);
                try{
                    const rsp = await fetch('/api/v1/export/malzeme-kabul', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'Authorization': 'Bearer ' + (localStorage.getItem('token') || ''),
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: fd
                    });
                    if(!rsp.ok){
                        const errData = await rsp.json().catch(()=>({}));
                        throw new Error(errData.msg || 'PDF oluşturulamadı (HTTP ' + rsp.status + ')');
                    }
                    const ct = rsp.headers.get('content-type') || '';
                    if(!ct.includes('pdf')){
                        const errData = await rsp.json().catch(()=>({}));
                        throw new Error(errData.msg || 'Sunbekten PDF yerine hata döndü. Lütfen tekrar deneyin.');
                    }
                    const blob = await rsp.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'malzeme-kabul-' + (this.orderEntities.order_no || this.id) + '.pdf';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    a.remove();
                    this.Swal.close();
                    this.printingKabul = false;
                } catch(e){
                    this.Swal.close();
                    this.printingKabul = false;
                    this.Swal.fire({ icon:'error', title:'PDF Oluşturulamadı', text:'PDF indirilemedi: ' + e.message, confirmButtonText:'Tamam' });
                }
            },
            async printMalzemeCinsMiktar(){
                const showWarn = (title, text) => {
                    this.Swal.fire({ icon:'warning', title, text, confirmButtonText:'Tamam', allowOutsideClick:true });
                };
                const itemTable = this.$refs.itemTable;
                if(!itemTable || !itemTable.items || !itemTable.items.length){
                    showWarn('Kalemler Yüklenmedi', 'Kalem bilgileri henüz yüklenemedi. Sayfayı yenileyip tekrar deneyin.');
                    return;
                }
                const effectiveTransferMode = this.canSend ? this.transferMode : this.storedTransferMode;
                if(!effectiveTransferMode){
                    showWarn('Transfer Türü Yok', 'Transfer türü bilgisi bulunamadı. Sayfayı yenileyip tekrar deneyin.');
                    return;
                }
                if(this.hasPartitions && effectiveTransferMode === 'at_once'){
                    showWarn('Tek Seferde Kilitli', 'Bu sipariş daha önce parçalı gönderildiği için artık sadece parçalı gönderim yapılabilir. Tüm parçalar silinirse tek seferde tekrar mümkün.');
                    return;
                }
                const getFieldValue = (name) => {
                    if(!this.canSend){
                        const val = this.orderEntities[name];
                        return (val !== undefined && val !== null && String(val).trim() !== '') ? String(val).trim() : '';
                    }
                    const formComp = this.$refs.formRef;
                    if(formComp && formComp.getCurrentFormData){
                        try {
                            const fd = formComp.getCurrentFormData();
                            const dynF = fd?.dynamicF || {};
                            for(const key in dynF){
                                const e = dynF[key]?.entities;
                                if(!e) continue;
                                for(const ek in e){
                                    const base = ek.split('**')[0].split('*-*').pop();
                                    if(base === name && e[ek] !== undefined && String(e[ek]).trim() !== ''){
                                        return String(e[ek]).trim();
                                    }
                                }
                                if(e[name] !== undefined && String(e[name]).trim() !== '') return String(e[name]).trim();
                            }
                        } catch(e) {}
                    }
                    const allInputs = document.querySelectorAll('input[name^="'+name+'"], textarea[name^="'+name+'"]');
                    for(const inp of allInputs){
                        if(inp.value && String(inp.value).trim()) return String(inp.value).trim();
                    }
                    const anyInputs = document.querySelectorAll('.form-item');
                    for(const inp of anyInputs){
                        const n = inp.getAttribute('name') || '';
                        if(n === name || n.startsWith(name + '**')){
                            if(inp.value && String(inp.value).trim()) return String(inp.value).trim();
                        }
                    }
                    return '';
                };
                const desc = getFieldValue('order_desc') || this.orderEntities.order_desc || '';
                const imalatci = getFieldValue('imalatci_firma_adi') || this.orderEntities.imalatci_firma_adi || '';
                if(!imalatci){
                    showWarn('İmalatçı Firma Boş', 'İmalatçı Firma adı boş. Formu yazdırmadan önce imalatçı firma bilgisi girmelisiniz.');
                    return;
                }
                if(effectiveTransferMode === 'partial' && !this.selectedItems.length && !this.canSend){
                } else if(effectiveTransferMode === 'partial' && !this.selectedItems.length){
                    showWarn('Kalem Seçilmedi', 'Parçalı transfer seçtiniz ama henüz kalem işaretlemediniz. Gönderilecek kalemleri tablodan işaretleyin.');
                    return;
                }
                if(effectiveTransferMode === 'partial' && this.selectedItems.length){
                    for(const item of this.selectedItems){
                        if(!item.amount || item.amount <= 0){
                            showWarn('Bölme Miktarı Eksik', 'İşaretlenen kalemlerden birinin bölme miktarı girilmemiş. Tüm seçili kalemler için gönderilecek miktarı girin.');
                            return;
                        }
                    }
                }
                this.printingCins = true;
                this.Swal.fire({ title:'PDF oluşturuluyor...', html:'<div style="display:flex;justify-content:center;padding:10px"><i class="ki-outline ki-loading" style="font-size:26px;animation:spin 1s linear infinite;color:#7c3aed"></i></div><div style="font-size:0.85rem;color:#64748b">Lütfen bekleyin, form hazırlanıyor</div>', allowOutsideClick:false, showConfirmButton:false, didOpen:()=>this.Swal.showLoading() });
                const items = [];
                if(effectiveTransferMode === 'partial' && this.selectedItems.length){
                    for(const sel of this.selectedItems){
                        const item = itemTable.items.find(i => i.id == sel.qnid);
                        if(!item) continue;
                        // Use frontend serial state (newly entered), fall back to DB serials
                        const frontendSerials = itemTable.serials?.[item.id] || [];
                        const serials = frontendSerials.length > 0 ? frontendSerials : (item.serials || []);
                        if(serials.length){
                            for(const s of serials){
                                items.push({
                                    prod_code: item.prod_code || '',
                                    title: item.title || '',
                                    unit: item.unit || '',
                                    quantity: s.quantity || '-',
                                    serial_no: s.serial_no || '-'
                                });
                            }
                        } else {
                            items.push({
                                prod_code: item.prod_code || '',
                                title: item.title || '',
                                unit: item.unit || '',
                                quantity: sel.amount || item.quantity,
                                serial_no: '-'
                            });
                        }
                    }
                } else {
                    for(const item of itemTable.items){
                        // Use frontend serial state (newly entered), fall back to DB serials
                        const frontendSerials = itemTable.serials?.[item.id] || [];
                        const serials = frontendSerials.length > 0 ? frontendSerials : (item.serials || []);
                        if(serials.length){
                            for(const s of serials){
                                items.push({
                                    prod_code: item.prod_code || '',
                                    title: item.title || '',
                                    unit: item.unit || '',
                                    quantity: s.quantity || '-',
                                    serial_no: s.serial_no || '-'
                                });
                            }
                        } else {
                            items.push({
                                prod_code: item.prod_code || '',
                                title: item.title || '',
                                unit: item.unit || '',
                                quantity: item.quantity,
                                serial_no: '-'
                            });
                        }
                    }
                }
                let orderNo = this.orderEntities.order_no || '';
                const buyingNo = this.orderEntities.buying_no || '';
                const createdAt = this.orderEntities.created_at || '';
                if(effectiveTransferMode === 'partial'){
                    if(!/\-\d+$/.test(orderNo)){
                        const baseNo = orderNo;
                        try {
                            const cloneFd = new FormData();
                            cloneFd.append('tableReq', JSON.stringify({
                                filter: [
                                    { key:'form-type', type:'=', value:'op-doc-order-form' },
                                    { key:'type', type:'=', value:'op-doc-order' },
                                    { key:'all', type:'=', value: baseNo + '-' }
                                ],
                                scale: { page: 1, limit: 100 },
                                order: { key: 'id', style: 'asc' }
                            }));
                            const cloneRsp = await this.plib.request({url:'/api/v1/table/documents', method:'POST'}, null, cloneFd);
                            const cloneRows = cloneRsp?.data?.data || cloneRsp?.data || [];
                            const cloneList = Array.isArray(cloneRows) ? cloneRows : (cloneRows?.data || []);
                            let maxX = 0;
                            for(const r of cloneList){
                                try {
                                    const attrs = JSON.parse(r.main_attr || '[]');
                                    const noAttr = attrs.find(a => a.Key === 'order_no');
                                    if(noAttr && noAttr.Value){
                                        const m = noAttr.Value.match(/-(\d+)$/);
                                        if(m) maxX = Math.max(maxX, parseInt(m[1]));
                                    }
                                } catch(e){}
                            }
                            orderNo = baseNo + '-' + (maxX + 1);
                        } catch(e){
                            orderNo = baseNo + '-1';
                        }
                    }
                }
                const fd = new FormData();
                fd.append('qnid', this.id);
                fd.append('transfer_mode', effectiveTransferMode);
                fd.append('items', JSON.stringify(items));
                fd.append('order_no', orderNo);
                fd.append('buying_no', buyingNo);
                fd.append('created_at', createdAt);
                fd.append('order_desc', desc);
                fd.append('imalatci_firma_adi', imalatci);
                try{
                    const rsp = await fetch('/api/v1/export/malzeme-cins-miktar-kabul', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'Authorization': 'Bearer ' + (localStorage.getItem('token') || ''),
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: fd
                    });
                    if(!rsp.ok){
                        const errData = await rsp.json().catch(()=>({}));
                        throw new Error(errData.msg || 'PDF oluşturulamadı (HTTP ' + rsp.status + ')');
                    }
                    const ct = rsp.headers.get('content-type') || '';
                    if(!ct.includes('pdf')){
                        const errData = await rsp.json().catch(()=>({}));
                        throw new Error(errData.msg || 'Sunbekten PDF yerine hata döndü. Lütfen tekrar deneyin.');
                    }
                    const blob = await rsp.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'malzeme-cins-miktar-' + (this.orderEntities.order_no || this.id) + '.pdf';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    a.remove();
                    this.Swal.close();
                    this.printingCins = false;
                } catch(e){
                    this.Swal.close();
                    this.printingCins = false;
                    this.Swal.fire({ icon:'error', title:'PDF Oluşturulamadı', text:'PDF indirilemedi: ' + e.message, confirmButtonText:'Tamam' });
                }
            }
        }
    }
</script>
<template>
    <div style="padding-bottom: 100px;">
        <div class="card mb-6" v-if="id && loadForm" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.04),0 1px 2px rgba(0,0,0,0.02);">
            <div v-if="canSend" style="background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);border-bottom:1px solid #bfdbfe;padding:20px 24px;">
                <div style="display:flex;align-items:flex-start;gap:16px;margin-bottom:16px;">
                    <div style="width:42px;height:42px;border-radius:12px;background:#3b82f6;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;flex-shrink:0;box-shadow:0 2px 6px rgba(59,130,246,0.3);">
                        <i class="ki-outline ki-send"></i>
                    </div>
                    <div>
                        <h4 style="margin:0 0 4px;font-size:1.08rem;font-weight:700;color:#0f172a;">Transfer Gönder</h4>
                        <p style="margin:0;font-size:0.88rem;color:#64748b;line-height:1.5;">Transfer türünü seçin ve kaydedin. Parçalı seçerseniz gönderilecek kalemleri işaretleyin.</p>
                    </div>
                </div>
                <div style="display:flex;gap:14px;">
                    <label class="transfer-mode-option" :class="{ active: transferMode === 'at_once', disabled: hasPartitions }" :style="hasPartitions ? 'flex:1;display:flex;align-items:center;gap:14px;padding:14px 18px;border:2px solid #e2e8f0;border-radius:12px;cursor:not-allowed;transition:all 0.2s;background:#f1f5f9 !important;opacity:0.55;pointer-events:none;' : 'flex:1;display:flex;align-items:center;gap:14px;padding:14px 18px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all 0.2s;background:#fff;'" @click="hasPartitions && $event.preventDefault()">
                        <input type="radio" value="at_once" v-model="transferMode" class="d-none" :disabled="hasPartitions">
                        <div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#eff6ff,#dbeafe);display:flex;align-items:center;justify-content:center;color:#3b82f6;font-size:17px;flex-shrink:0;">
                            <i class="ki-outline ki-direct-right"></i>
                        </div>
                        <div><div style="font-weight:700;font-size:0.95rem;color:#0f172a;">Tek Seferde</div><div style="font-size:0.82rem;color:#94a3b8;">Tüm sipariş gönderilir</div></div>
                    </label>
                    <label class="transfer-mode-option" :class="{ active: transferMode === 'partial' }" style="flex:1;display:flex;align-items:center;gap:14px;padding:14px 18px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all 0.2s;background:#fff;">
                        <input type="radio" value="partial" v-model="transferMode" class="d-none">
                        <div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#fef3c7,#fde68a);display:flex;align-items:center;justify-content:center;color:#d97706;font-size:17px;flex-shrink:0;">
                            <i class="ki-outline ki-slice"></i>
                        </div>
                        <div><div style="font-weight:700;font-size:0.95rem;color:#0f172a;">Parçalı</div><div style="font-size:0.82rem;color:#94a3b8;">Seçili kalemler gönderilir</div></div>
                    </label>
                </div>
                <div v-if="hasPartitions" style="margin-top:12px;padding:10px 14px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;color:#92400e;font-size:0.85rem;display:flex;align-items:center;gap:8px;">
                    <i class="ki-outline ki-information-2" style="font-size:16px;"></i>
                    <span>Bu sipariş daha önce parçalı gönderildiği için artık sadece <b>Parçalı</b> gönderim yapılabilir. Tüm parçalar silinirse tek seferde tekrar mümkün.</span>
                </div>
                <div style="margin-top:14px;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" @click="printMalzemeKabul" class="print-kabul-btn" :disabled="printingKabul">
                        <i :class="printingKabul ? 'ki-outline ki-loading' : 'ki-outline ki-printer'" :style="printingKabul ? 'font-size:16px;animation:spin 1s linear infinite' : ''"></i>
                        <span>{{ printingKabul ? 'Oluşturuluyor...' : 'Malzeme Kabul Formu Yazdır' }}</span>
                    </button>
                    <button type="button" @click="printMalzemeCinsMiktar" class="print-cins-btn" :disabled="printingCins">
                        <i :class="printingCins ? 'ki-outline ki-loading' : 'ki-outline ki-printer'" :style="printingCins ? 'font-size:16px;animation:spin 1s linear infinite' : ''"></i>
                        <span>{{ printingCins ? 'Oluşturuluyor...' : 'Malzeme Cins-Miktar Kabul Formu Yazdır' }}</span>
                    </button>
                </div>
            </div>
            <div style="background:#fff;padding:0;">
                <OrderItemTable ref="itemTable" :key="(canSend ? transferMode : 'ro')" :orderId="id" :orderNumericId="formDataStore.rawData?.document?.id" :orderDate="orderEntities.created_at || ''" :selectable="canSend && transferMode==='partial'" :atOnceMode="canSend && transferMode==='at_once'" :highlightQnid="highlightItemQnid" :containerSuffix="canSend ? '-sel' : ''" :readonly="isLocked" @select="onItemsSelected" @serials="onItemSerials" @item-files="onItemFiles" />
            </div>
        </div>

        <div class="clone-origin-card mb-6" v-if="id && loadForm && isCloneOrder">
            <div class="clone-origin-body">
                <div class="clone-origin-left">
                    <div class="clone-origin-icon">
                        <i class="ki-outline ki-arrow-top-right"></i>
                    </div>
                    <div>
                        <div class="clone-origin-label">Kaynak Sipariş</div>
                        <div class="clone-origin-value">{{ parentOrderNo }}</div>
                    </div>
                </div>
                <button class="clone-origin-btn" @click="navigateToParentOrder">
                    <i class="ki-outline ki-arrow-right"></i>
                    <span>Orijinal Siparişe Git</span>
                </button>
            </div>
        </div>

        <div class="transfer-info-card mb-6" v-if="id && !canSend && storedTransferMode">
            <div class="transfer-info-body">
                <div class="transfer-info-left">
                    <div class="transfer-info-icon" :class="storedTransferMode === 'at_once' ? 'icon-at-once' : 'icon-partial'">
                        <i :class="storedTransferMode === 'at_once' ? 'ki-outline ki-direct-right' : 'ki-outline ki-slice'"></i>
                    </div>
                    <div>
                        <div class="transfer-info-label">Transfer Türü</div>
                        <div class="transfer-info-value">{{ storedTransferMode === 'at_once' ? 'Tek Seferde' : 'Parçalı' }}</div>
                    </div>
                </div>
                <div class="transfer-info-badge" :class="storedTransferMode === 'at_once' ? 'badge-at-once' : 'badge-partial'">
                    {{ storedTransferMode === 'at_once' ? 'Tüm sipariş gönderildi' : 'Seçili kalemler gönderildi' }}
                </div>
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
                <div style="display:flex;gap:8px;align-items:center;">
                    <button v-if="orderStatus === 'doc_trans_order_files_rejected'" type="button" @click="printMalzemeKabul" class="print-kabul-btn" style="margin:0;" :disabled="printingKabul">
                        <i :class="printingKabul ? 'ki-outline ki-loading' : 'ki-outline ki-printer'" :style="printingKabul ? 'font-size:16px;animation:spin 1s linear infinite' : ''"></i>
                        <span>{{ printingKabul ? 'Oluşturuluyor...' : 'Malzeme Kabul Formu Yazdır' }}</span>
                    </button>
                    <button v-if="orderStatus === 'doc_trans_order_files_rejected'" type="button" @click="printMalzemeCinsMiktar" class="print-cins-btn" style="margin:0;" :disabled="printingCins">
                        <i :class="printingCins ? 'ki-outline ki-loading' : 'ki-outline ki-printer'" :style="printingCins ? 'font-size:16px;animation:spin 1s linear infinite' : ''"></i>
                        <span>{{ printingCins ? 'Oluşturuluyor...' : 'Malzeme Cins-Miktar Kabul Formu Yazdır' }}</span>
                    </button>
                    <button class="locked-cancel-btn" @click="cancelOrder" v-if="authStore.permissions?.includes('per-05-02')">
                        <i class="ki-outline ki-trash"></i>
                        <span>{{ isCloneOrder ? 'Parçayı Sil' : 'İptal Et' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <Form ref="formRef" formtypes="op-doc-order-form" v-if="loadForm" savebtntitle="Kaydet" :readonlyFields="readonlyFields" :savecallback="submitForm" />
        
    </div>
</template>
<style scoped>
.clone-origin-card {
    border: 1px solid #e0e7ff;
    border-radius: 14px;
    overflow: hidden;
    background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
}
.clone-origin-body {
    padding: 18px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}
.clone-origin-left {
    display: flex;
    align-items: center;
    gap: 14px;
}
.clone-origin-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #6366f1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
}
.clone-origin-label {
    font-size: 0.85rem;
    color: #6366f1;
    margin-bottom: 2px;
}
.clone-origin-value {
    font-weight: 700;
    font-size: 1.05rem;
    color: #312e81;
}
.clone-origin-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px;
    background: #fff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.90rem;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
    flex-shrink: 0;
}
.clone-origin-btn:hover {
    background: #eef2ff;
    border-color: #a5b4fc;
    box-shadow: 0 1px 4px rgba(79,70,229,0.15);
}
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
    padding: 18px 24px;
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
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #f59e0b;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 20px;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(245,158,11,0.3);
}
.locked-card-badge {
    font-weight: 700;
    font-size: 0.95rem;
    color: #92400e;
    margin-bottom: 2px;
}
.locked-card-text {
    margin: 0;
    font-size: 0.85rem;
    color: #a16207;
    line-height: 1.4;
}
.locked-cancel-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px;
    background: #fff;
    color: #dc2626;
    border: 1px solid #fecaca;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.90rem;
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
.transfer-info-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
}
.transfer-info-body {
    padding: 18px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}
.transfer-info-left {
    display: flex;
    align-items: center;
    gap: 14px;
}
.transfer-info-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
}
.transfer-info-icon.icon-at-once {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    color: #3b82f6;
}
.transfer-info-icon.icon-partial {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #d97706;
}
.transfer-info-label {
    font-size: 0.85rem;
    color: #94a3b8;
    margin-bottom: 2px;
}
.transfer-info-value {
    font-weight: 700;
    font-size: 1.0rem;
    color: #0f172a;
}
.transfer-info-badge {
    padding: 7px 16px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
}
.transfer-info-badge.badge-at-once {
    background: #eff6ff;
    color: #1d4ed8;
}
.transfer-info-badge.badge-partial {
    background: #fef3c7;
    color: #92400e;
}
.print-kabul-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.90rem;
    cursor: pointer;
    transition: all 0.15s ease;
    box-shadow: 0 2px 6px rgba(5,150,105,0.3);
}
.print-kabul-btn:hover {
    background: linear-gradient(135deg, #047857 0%, #065f46 100%);
    box-shadow: 0 4px 12px rgba(5,150,105,0.4);
    transform: translateY(-1px);
}
.print-kabul-btn i {
    font-size: 16px;
}
.print-cins-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.90rem;
    cursor: pointer;
    transition: all 0.15s ease;
    box-shadow: 0 2px 6px rgba(124,58,237,0.3);
}
.print-cins-btn:hover {
    background: linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
    box-shadow: 0 4px 12px rgba(124,58,237,0.4);
    transform: translateY(-1px);
}
.print-cins-btn i {
    font-size: 16px;
}
.print-kabul-btn:disabled, .print-cins-btn:disabled { opacity:0.72; cursor:not-allowed; transform:none !important; box-shadow:none !important; }
@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
</style>
