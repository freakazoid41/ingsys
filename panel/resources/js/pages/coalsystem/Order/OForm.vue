<script>
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router'
    import { useNavigationStore } from '@/stores/navigation'
    import { useFormDataStore } from '@/stores/formdata';
    import { useAuthStore } from '@/stores/auth';
    import Swal from 'sweetalert2';
    import Form from '@/components/coalparts/Form.vue';
    import OrderItemTable from '@/components/Order/OrderItemTable.vue';
    import { formatDate, fmtDateTime } from '@/lib/dateUtils';
    import { validatePrintForm, downloadPdf } from '@/lib/pdf.js';
    import { escapeHtml } from '@/lib/escape.js';
    import { noteOf } from '@/lib/statusUtils.js';

    const LOCKED_STATUSES = ['doc_trans_order_transfer_sent','doc_trans_order_ready_for_shipment','doc_trans_order_approved','doc_trans_order_rejected','doc_trans_order_files_rejected','doc_trans_order_sevk_started','doc_trans_order_sevk_kalite_ok','doc_trans_order_sevk_addressed','doc_trans_order_sevk_dated','doc_trans_order_sevk_proforma','doc_trans_order_sevk_shipped'];
    const FILES_LOCKED_STATUSES = ['doc_trans_order_transfer_sent','doc_trans_order_ready_for_shipment','doc_trans_order_approved','doc_trans_order_rejected','doc_trans_order_sevk_started','doc_trans_order_sevk_kalite_ok','doc_trans_order_sevk_addressed','doc_trans_order_sevk_dated','doc_trans_order_sevk_proforma','doc_trans_order_sevk_shipped'];

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
                const listName = this.$route.path.startsWith('/tedarikpanel') ? 'TedarikOrderList' : 'OrderList';
                this.$router.replace({ name: listName });
                return;
            }
            const response = await this.plib.request({ url:'/api/v1/document/'+this.id, method:'GET' },null);
            if(!response || response.success === false){
                this.navigationStore.toggle(false);
                this.plib.toast(this.Swal,'error', response?.msg || response?.message || 'Bu siparişi görüntüleme yetkiniz yok');
                const listName = this.$route.path.startsWith('/tedarikpanel') ? 'TedarikOrderList' : 'OrderList';
                try{ await this.$router.replace({ name: listName, query:{...this.$route.query} }); }catch(e){}
                return;
            }
            this.formDataStore.setData(response?.data?.formFormat);
            this.formDataStore.rawData = response?.data || {};
            this.rawData = response?.data || {};
            try {
                this.parsedStatus = JSON.parse(this.rawData?.document?.status || '[]');
            } catch(e) { this.parsedStatus = []; }
            this.loadForm = true;
            // populate tedarik fields from existing entities (for edit / reprint)
            try { this.tedarikDesc = this.orderFormEntities?.order_desc || ''; } catch(e){}
            try { this.tedarikImalatci = this.orderFormEntities?.imalatci_firma_adi || ''; } catch(e){}
            try { this.parseTedarikExistingFiles(); } catch(e){}
            try {
                this.sevkDepo = this.orderFormEntities?.depo_yeri || '';
                this.sevkAdres = this.orderFormEntities?.sevk_adresi || '';
                this.sevkAdresDiger = this.orderFormEntities?.sevk_adres_diger || '';
                this.sevkTarihi = this.orderFormEntities?.sevk_tarihi || '';
                this.parseSevkExistingFiles();
            } catch(e){}
            // sync transferMode to stored value when locked so dot and border agree (partial vs at_once)
            try {
                if(this.isLocked && this.storedTransferMode){
                    this.transferMode = this.storedTransferMode;
                } else if(this.hasPartitions){
                    // keep existing hasPartitions logic but after async check it will force partial again
                }
            } catch(e){}
            setTimeout(() => this.navigationStore.toggle(false), 400);
            // New rule: if order was partitioned before (has active EBELN-X clones), force partial
            this.checkHasPartitions();
            // after partitions check, re-sync if now locked/partitioned
            this.$nextTick(()=>{
                try{
                    if(this.isLocked && this.storedTransferMode) this.transferMode = this.storedTransferMode;
                    else if(this.hasPartitions && this.transferMode === 'at_once') this.transferMode = 'partial';
                }catch(e){}
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
                downloadingAll: false,
                isSubmitting: false,
                kaliteLoading: false,
                // tedarik detail (fresh order screenshot)
                tedarikDesc: '',
                tedarikImalatci: '',
                tedarikKabulFile: null,
                tedarikCinsFile: null,
                tedarikKabulRef: null,
                tedarikCinsRef: null,
                tedarikExistingKabul: null,
                tedarikExistingCins: null,
                // sevk onay (Way-1)
                sevkDepo: '',
                sevkAdres: '',
                sevkAdresDiger: '',
                sevkTarihi: '',
                sevkProformaFile: null,
                sevkIrsaliyeFile: null,
                sevkProformaRef: null,
                sevkIrsaliyeRef: null,
                sevkExistingProforma: null,
                sevkExistingIrsaliye: null,
                sevkLoading: false,
                sevkKontrolNote: '',
                // sevk focus mode (entering from Sevk tab)
                showAllDetails: false,
                sevkScrolled: false,
                sevkPartitions: null,
            };
        },
        computed: {
            orderStatus(){
                if(Array.isArray(this.parsedStatus) && this.parsedStatus.length) return this.parsedStatus[this.parsedStatus.length-1].op_key || '';
                return '';
            },
            isLocked(){ return this.id && LOCKED_STATUSES.includes(this.orderStatus); },
            canSend(){ return this.id && this.orderStatus === 'doc_trans_order_created'; },
            canPrintKabul(){ return this.canSend || this.orderStatus === 'doc_trans_order_files_rejected'; },
            storedTransferMode(){
                const entities = this.orderFormEntities;
                if(entities.transfer_mode) return entities.transfer_mode;
                // Fallback: clone orders without stored transfer_mode are always partial
                if(this.isCloneOrder) return 'partial';
                return '';
            },
            isFilesLocked(){ return this.id && FILES_LOCKED_STATUSES.includes(this.orderStatus); },
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
            hasAnyFile(){
                // admin bottom strip (Bütün Formları / history) visible only if order actually has files
                // base order 3510004400 now correctly has 0 (clone files no longer leak), so hide strip
                if(this.tedarikExistingKabul || this.tedarikExistingCins) return true;
                try{
                    const it = this.$refs.itemTable;
                    if(it){
                        // any existing file or currently selected file
                        if(Object.keys(it.itemFiles?.testFiles || {}).length) return true;
                        if(Object.keys(it.itemFiles?.images || {}).some(k=> (it.itemFiles.images[k]||[]).length)) return true;
                        if(it.existingTestFiles && Object.values(it.existingTestFiles).some(arr=> Array.isArray(arr) && arr.length)) return true;
                        if(it.existingImages && Object.values(it.existingImages).some(arr=> Array.isArray(arr) && arr.length)) return true;
                        // order-level files (transfer_kabul/cins) are on the order itself, not per-item
                        // for base 84428217 they are 0 after leak fix, for clone 3c1cf1f2 they are 3
                        if(it.existingOrderFiles && Object.values(it.existingOrderFiles).some(arr=> Array.isArray(arr) && arr.length)) return true;
                    }
                }catch(e){}
                // check order-level via tedarikExisting (already) and via rawData file entities fallback
                try{
                    const form = this.rawData?.formFormat?.['op-doc-order-form'] || {};
                    for(const cid in form){
                        const ents = form[cid]?.entities || {};
                        for(const k in ents){
                            if(k.includes('transfer_kabul_file') || k.includes('transfer_cins_file')){
                                const raw = ents[k];
                                let parsed = null;
                                if(typeof raw === 'object' && raw !== null && raw.id) parsed = raw;
                                else if(typeof raw === 'string'){ try{ parsed = JSON.parse(raw); }catch(e){} }
                                if(parsed && parsed.id) return true;
                            }
                        }
                    }
                }catch(e){}
                return false;
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
            tedarikDisplayMode(){
                if(this.canSend) return this.transferMode;
                return this.storedTransferMode || 'at_once';
            },
            isTedarik(){ return this.$route.path.startsWith('/tedarikpanel'); },
            targetList(){ return this.isTedarik ? 'TedarikOrderList' : 'OrderList'; },
            tedarikHeaderTitle(){
                return this.orderEntities?.ctitle || this.orderEntities?.spec_code || 'Sipariş Detayı';
            },
            tedarikHeaderSub(){
                return this.orderEntities?.order_no ? `Sipariş: ${this.orderEntities.order_no}` : '';
            },
            tedarikStatus(){
                const arr = Array.isArray(this.parsedStatus) ? this.parsedStatus : [];
                const last = arr.length ? arr[arr.length - 1] : null;
                const op = last?.op_key || this.orderStatus || 'doc_trans_order_created';
                const rawLabel = last?.title || last?.name || '';
                let label = rawLabel || 'Beklemede';
                let cls = 'tedarik-status--waiting';
                let icon = 'ki-outline ki-file-added';
                if(op === 'doc_trans_order_sevk_started'){
                    label = 'Kalite Onayında'; cls = 'tedarik-status--waiting'; icon = 'ki-outline ki-shield-tick';
                } else if(op === 'doc_trans_order_sevk_kalite_ok'){
                    label = 'Sevk Kalite OK'; cls = 'tedarik-status--ready'; icon = 'ki-outline ki-check-circle';
                } else if(op === 'doc_trans_order_sevk_addressed'){
                    label = 'Sevk Adresi Girildi'; cls = 'tedarik-status--ready'; icon = 'ki-outline ki-geolocation';
                } else if(op === 'doc_trans_order_sevk_dated'){
                    label = 'Sevk Tarihi Seçildi'; cls = 'tedarik-status--ready'; icon = 'ki-outline ki-calendar';
                } else if(op === 'doc_trans_order_sevk_proforma'){
                    label = 'Proforma Yüklendi'; cls = 'tedarik-status--ready'; icon = 'ki-outline ki-document';
                } else if(op === 'doc_trans_order_sevk_shipped'){
                    label = 'Sevkiyat Yapıldı'; cls = 'tedarik-status--ready'; icon = 'ki-outline ki-delivery';
                } else if(op === 'doc_trans_order_approved' || /Onay/i.test(label)){
                    label = 'Sevk Onayı Verildi'; cls = 'tedarik-status--approved'; icon = 'ki-outline ki-check-circle';
                } else if(op === 'doc_trans_order_ready_for_shipment' || /Sevke Hazır/i.test(label)){
                    label = 'Sipariş Sevke Hazır'; cls = 'tedarik-status--ready'; icon = 'ki-outline ki-truck';
                } else if(op === 'doc_trans_order_transfer_sent' || /Kontrol/i.test(label)){
                    label = 'Dosyalar Kontrol Ediliyor'; cls = 'tedarik-status--waiting'; icon = 'ki-outline ki-magnifier';
                } else if(op === 'doc_trans_order_files_rejected' || /Reddedilen/i.test(label)){
                    label = 'Reddedilen Dosyalar Mevcut'; cls = 'tedarik-status--rejected'; icon = 'ki-outline ki-cross-circle';
                } else if(op === 'doc_trans_order_rejected' || /Reddedildi/i.test(label)){
                    label = 'Reddedildi'; cls = 'tedarik-status--rejected'; icon = 'ki-outline ki-cross-circle';
                } else if(op === 'doc_trans_order_created' || /Beklemede/i.test(label)){
                    label = 'Beklemede'; cls = 'tedarik-status--waiting'; icon = 'ki-outline ki-time';
                }
                return { label, cls, icon, op };
            },
            canKalite(){
                if(!this.id || !this.loadForm) return false;
                if(!this.authStore.permissions?.includes('per-07-02')) return false;
                if(['doc_trans_order_approved','doc_trans_order_rejected'].includes(this.orderStatus)) return false;
                // allow from any sent state; block on pure draft without files? keep open - backend will validate transition
                return true;
            },
            sevkEnts(){
                return this.orderFormEntities || {};
            },
            isSevkOrder(){
                const s = this.orderStatus || '';
                if ((this.sevkEnts.approval_path || '') === 'sevk') return true;
                return s.startsWith('doc_trans_order_sevk_');
            },
            canSevkStart(){
                return this.orderStatus === 'doc_trans_order_ready_for_shipment' && this.authStore.permissions?.includes('per-05-03');
            },
            canSevkKalite(){
                return this.orderStatus === 'doc_trans_order_sevk_started' && this.authStore.permissions?.includes('per-07-02');
            },
            canSevkAdres(){
                return this.orderStatus === 'doc_trans_order_sevk_kalite_ok' && this.authStore.permissions?.includes('per-05-06');
            },
            canSevkDate(){
                return this.orderStatus === 'doc_trans_order_sevk_addressed' && (this.authStore.permissions?.includes('per-05-02') || this.authStore.permissions?.includes('per-05-06'));
            },
            canSevkProforma(){
                return ['doc_trans_order_sevk_dated','doc_trans_order_sevk_proforma'].includes(this.orderStatus) && this.authStore.permissions?.includes('per-05-02');
            },
            canSevkControl(){
                return this.orderStatus === 'doc_trans_order_sevk_proforma' && this.authStore.permissions?.includes('per-05-03');
            },
            canSevkShip(){
                return this.orderStatus === 'doc_trans_order_sevk_proforma' && (this.sevkEnts.sevk_kontrol_ok === '1') && this.authStore.permissions?.includes('per-05-02');
            },
            sevkFocus(){
                const active = this.canSevkStart || this.isSevkOrder;
                if(!active) return false;
                return (this.$route.query.f === 'sevk') || this.isSevkOrder;
            },
        },
        watch: {
            loadForm(v){ if(v) this.maybeScrollSevk(); },
            orderStatus(){ this.maybeScrollSevk(); },
        },
        methods: {
            maybeScrollSevk(){
                if(!this.sevkFocus || this.sevkScrolled || !this.loadForm) return;
                this.sevkScrolled = true;
                this.$nextTick(()=>{
                    setTimeout(()=>{
                        const el = document.getElementById('sevk-card');
                        if(el){ el.scrollIntoView({behavior:'smooth', block:'start'}); }
                    }, 350);
                });
            },
            toggleAllDetails(){ this.showAllDetails = !this.showAllDetails; },
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
                    if(!this.id) return;
                    this.checkingPartitions = true;
                    // Fast indexed endpoint — old tableList all-scan took 17s on 208k orders
                    const rsp = await this.plib.request({url:'/api/v1/order/'+this.id+'/partitions', method:'GET'}, null);
                    this.sevkPartitions = rsp?.success ? rsp : null;
                    if(rsp?.success && !this.isCloneOrder) this.hasPartitions = !!rsp.hasPartitions;
                    else if(this.isCloneOrder) this.hasPartitions = false;
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
            getTedarikRowId(){
                const form = this.rawData?.formFormat?.['op-doc-order-form'] || this.formDataStore?.rawData?.formFormat?.['op-doc-order-form'] || {};
                const keys = Object.keys(form);
                if(keys.length) return keys[0];
                // also try to find via formData dynamicF if exists
                if(this.formData && this.formData.dynamicF){
                    for(const k in this.formData.dynamicF){
                        if(k.startsWith('op-doc-order-form**')) return k.split('**')[1];
                    }
                }
                return 'new-' + Date.now();
            },
            onTedarikFileSelect(e, type){
                const file = e.target.files && e.target.files[0];
                if(type === 'kabul') this.tedarikKabulFile = file || null;
                else this.tedarikCinsFile = file || null;
                if(file) this.handleTedarikTempUpload(file, type);
            },
            async handleTedarikTempUpload(file, type){
                try{
                    const fd=new FormData();
                    fd.append('file', file);
                    const rsp=await this.plib.request({url:'/api/v1/temp-upload', method:'POST'}, null, fd);
                    // plib returns {success, reference_id, ...} at top-level; older check expected rsp.data
                    const ref = rsp?.data?.reference_id ? rsp.data : (rsp?.reference_id ? rsp : null);
                    if(rsp && rsp.success && ref && ref.reference_id){
                        if(type==='kabul') this.tedarikKabulRef = ref;
                        else this.tedarikCinsRef = ref;
                    } else if(rsp && !rsp.success){
                        console.warn('tedarik temp upload failed', rsp);
                        this.Swal.fire({ icon:'warning', title:'Dosya yüklenemedi', text: rsp?.msg || 'Geçici yükleme başarısız, normal yükleme denenecek.' });
                    }
                }catch(e){ console.warn('tedarik temp upload failed',e); }
            },
            parseTedarikExistingFiles(){
                try{
                    const form = this.rawData?.formFormat?.['op-doc-order-form'] || this.formDataStore?.rawData?.formFormat?.['op-doc-order-form'] || {};
                    for(const connId in form){
                        const conn = form[connId];
                        const entities = conn?.entities || {};
                        for(const tag in entities){
                            if(!tag.includes('transfer_kabul_file') && !tag.includes('transfer_cins_file')) continue;
                            const raw = entities[tag];
                            let parsed = null;
                            if(typeof raw === 'object' && raw !== null && raw.id) parsed = raw;
                            else if(typeof raw === 'string'){ try{ parsed = JSON.parse(raw); }catch(e){ continue; } }
                            if(!parsed || !parsed.id) continue;
                            if(tag.includes('transfer_kabul_file') && !this.tedarikExistingKabul) this.tedarikExistingKabul = { ...parsed, entity_tag: tag, connId };
                            if(tag.includes('transfer_cins_file') && !this.tedarikExistingCins) this.tedarikExistingCins = { ...parsed, entity_tag: tag, connId };
                        }
                        const files = conn?.files || {};
                        for(const tag in files){
                            const fd = files[tag];
                            if(!fd || !fd.id) continue;
                            if(tag.includes('transfer_kabul_file') && !this.tedarikExistingKabul) this.tedarikExistingKabul = { ...fd, entity_tag: tag, connId };
                            if(tag.includes('transfer_cins_file') && !this.tedarikExistingCins) this.tedarikExistingCins = { ...fd, entity_tag: tag, connId };
                        }
                    }
                }catch(e){}
            },
            getTedarikDisplayName(file, fallback){
                const raw = file?.name || file?.description || '';
                const looksDecrypted = raw && raw.includes('.') && !raw.includes(':') && raw.length < 80 && !raw.match(/^[A-Za-z0-9+\/=]{60,}$/);
                if(looksDecrypted) return raw;
                // encrypted salt:iv:ct is long base64 with : and no dot — show friendly fallback
                return fallback || 'Yüklü Dosya';
            },
            previewTedarikFile(file){
                if(!file?.qnid) return;
                const url = '/order-file/' + file.qnid;
                const name = this.escapeHtml(file.name || file.description || 'Dosya');
                Swal.fire({
                    html: `<div style="font-weight:700;margin-bottom:10px;color:#0f172a;">${name}</div><iframe src="${url}" style="width:100%;height:70vh;border:1px solid #e2e8f0;border-radius:8px;background:#fff"></iframe><div style="margin-top:10px;font-size:0.85rem;color:#64748b;"><a href="${url}" target="_blank" style="color:#3b82f6;text-decoration:underline;">Yeni pencerede aç / İndir</a></div>`,
                    showCloseButton: true, showConfirmButton: false, width: 900
                });
            },
            getTedarikNote(file){
                return this.noteOf(file);
            },
            hasTedarikNote(file){
                return !!this.getTedarikNote(file);
            },
            showTedarikFileNote(file){
                const st = file?.last_status || {};
                const isAccepted = st.op_key === 'doc_file_accepted';
                const title = isAccepted ? 'Dosya Onaylandı' : st.op_key === 'doc_file_rejected' ? 'Dosya Reddedildi' : 'Dosya Notu';
                const who = this.escapeHtml(st.name || '-');
                const note = this.escapeHtml(this.getTedarikNote(file) || '-');
                const when = st.created_at ? new Date(st.created_at).toLocaleString('tr-TR') : '';
                const fileName = this.escapeHtml(this.getTedarikDisplayName(file,'Dosya'));
                Swal.fire({
                    title,
                    html: `<div style="text-align:left; padding:8px 4px;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                            <span style="font-size:12px; font-weight:700; padding:4px 8px; border-radius:6px; ${isAccepted ? 'background:#dcfce7;color:#166534' : 'background:#fee2e2;color:#991b1b'}">${isAccepted ? 'Onaylandı' : 'Reddedildi'}</span>
                            <span style="font-size:13px; color:#0f172a; font-weight:600;">${fileName}</span>
                        </div>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; margin-bottom:8px;">
                            <div style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:4px;">İşlemi Yapan</div>
                            <div style="font-size:13px; font-weight:600; color:#0f172a;">${who}</div>
                            ${when ? `<div style="font-size:11px; color:#64748b; margin-top:2px;">${when}</div>` : ''}
                        </div>
                        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px;">
                            <div style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:6px;">Not / Gerekçe</div>
                            <div style="font-size:13px; color:#334155; line-height:1.6; white-space:pre-wrap; word-break:break-word;">${note}</div>
                        </div>
                    </div>`,
                    showCloseButton:true, showConfirmButton:true, confirmButtonText:'Kapat', confirmButtonColor:'#6366f1', width:520
                });
            },
            async submitForm(formData){
                this.formData = formData;
                this.navigationStore.toggle(true);
                this.isSubmitting = true;
                this.formData.typeKey = 'op-doc-order';

                // Tedarik panel manual wiring — inject order_desc / imalatci / files before validation
                if(this.isTedarik){
                    const rowId = this.getTedarikRowId();
                    const key = 'op-doc-order-form**' + rowId;
                    if(!this.formData.dynamicF) this.formData.dynamicF = {};
                    if(!this.formData.dynamicF[key]) this.formData.dynamicF[key] = { tag:'op-doc-order-form', entities:{} };
                    if(!this.formData.dynamicF[key].entities) this.formData.dynamicF[key].entities = {};
                    // After first save desc/imalatci are permanently locked — only files may be re-uploaded when rejected
                    if(!this.isLocked){
                        this.formData.dynamicF[key].entities['order_desc'] = this.tedarikDesc || '';
                        this.formData.dynamicF[key].entities['imalatci_firma_adi'] = this.tedarikImalatci || '';
                    }
                    if(!this.formData.files) this.formData.files = {};
                    // map tedarik file inputs to EAV file keys
                    const fileRowId = rowId;
                    if(this.tedarikKabulFile){
                        const fid='new-'+Date.now()+'-kabul';
                        // Include rowId before *-* so backend explode('**',fkey)[2] parses fid cleanly even on fallback raw upload
                        const fkey='dynamicFile**transfer_kabul**'+fid+'**'+fileRowId+'*-*transfer_kabul_file**transfer_kabul**'+fileRowId;
                        if(this.tedarikKabulRef && this.tedarikKabulRef.reference_id){
                            this.formData.files[fkey] = { reference: this.tedarikKabulRef };
                        } else {
                            this.formData.files[fkey] = { file: this.tedarikKabulFile };
                        }
                    }
                    if(this.tedarikCinsFile){
                        const fid='new-'+Date.now()+'-cins';
                        const fkey='dynamicFile**transfer_cins**'+fid+'**'+fileRowId+'*-*transfer_cins_file**transfer_cins**'+fileRowId;
                        if(this.tedarikCinsRef && this.tedarikCinsRef.reference_id){
                            this.formData.files[fkey] = { reference: this.tedarikCinsRef };
                        } else {
                            this.formData.files[fkey] = { file: this.tedarikCinsFile };
                        }
                    }
                }

                if(this.buildTransferPayload() === false){
                    this.navigationStore.toggle(false);
                    this.isSubmitting = false;
                    return;
                }

                // Tedarik manual validation bypasses DOM checkForm (Form hidden)
                let rsp = null;
                if(this.isTedarik){
                    if(!this.tedarikImalatci || !this.tedarikImalatci.trim()){
                        this.navigationStore.toggle(false);
                        this.isSubmitting = false;
                        this.Swal.fire({ icon:'warning', title:'İmalatçı Firma Boş', text:'Lütfen imalatçı firma adını giriniz.', confirmButtonText:'Tamam' });
                        return;
                    }
                    // Malzeme Kabul + Cins forms required on first send, and rejected files required on re-send (control mechanics)
                    if(this.canSend){
                        const hasKabulNew = !!(this.tedarikKabulFile || this.tedarikKabulRef);
                        const hasKabulExistingOk = !!(this.tedarikExistingKabul && this.tedarikExistingKabul.last_status?.op_key !== 'doc_file_rejected');
                        const hasCinsNew = !!(this.tedarikCinsFile || this.tedarikCinsRef);
                        const hasCinsExistingOk = !!(this.tedarikExistingCins && this.tedarikExistingCins.last_status?.op_key !== 'doc_file_rejected');
                        const kabulOk = hasKabulNew || hasKabulExistingOk;
                        const cinsOk = hasCinsNew || hasCinsExistingOk;
                        if(!kabulOk || !cinsOk){
                            this.navigationStore.toggle(false);
                            this.isSubmitting = false;
                            const missing = [];
                            if(!kabulOk) missing.push('Malzeme Kabul Formu');
                            if(!cinsOk) missing.push('Malzeme Cins-Miktar Kabul Formu');
                            this.Swal.fire({ icon:'warning', title:'Eksik Dosya', html:`<div style="text-align:left"><div>${missing.join(' ve ')} yüklenmelidir.</div><div style="margin-top:8px;font-size:13px;color:#64748b;">Lütfen her iki formu da doldurup <b>5. adım</b>da yükleyin. Sonra <b>Gönder</b> ile kontrole gönderin.</div></div>`, confirmButtonText:'Tamam', confirmButtonColor:'#FF5A1F' });
                            return;
                        }
                    } else if(this.orderStatus === 'doc_trans_order_files_rejected'){
                        const kabulRejected = this.tedarikExistingKabul?.last_status?.op_key === 'doc_file_rejected';
                        const cinsRejected = this.tedarikExistingCins?.last_status?.op_key === 'doc_file_rejected';
                        const hasKabulNew = !!(this.tedarikKabulFile || this.tedarikKabulRef);
                        const hasCinsNew = !!(this.tedarikCinsFile || this.tedarikCinsRef);
                        const missing = [];
                        if(kabulRejected && !hasKabulNew) missing.push('Malzeme Kabul Formu');
                        if(cinsRejected && !hasCinsNew) missing.push('Malzeme Cins-Miktar Kabul Formu');
                        if(!this.tedarikExistingKabul && !hasKabulNew) missing.push('Malzeme Kabul Formu');
                        if(!this.tedarikExistingCins && !hasCinsNew) missing.push('Malzeme Cins-Miktar Kabul Formu');
                        if(missing.length){
                            this.navigationStore.toggle(false);
                            this.isSubmitting = false;
                            this.Swal.fire({ icon:'warning', title:'Reddedilen Dosyalar', html:`<div style="text-align:left"><div>${missing.join(' ve ')} için yeni dosya yüklemeniz gerekiyor.</div><div style="margin-top:8px;font-size:13px;color:#64748b;">Reddedilen kartta <b>Yeni dosya seç</b> ile yenileyin.</div></div>`, confirmButtonText:'Tamam', confirmButtonColor:'#ef4444' });
                            return;
                        }
                    }
                    rsp = { valid: true };
                } else {
                    rsp = this.plib.checkForm('.form-item');
                }
                if(rsp.valid){
                    // Save item files BEFORE the order save. The partial-transfer flow
                    // (processOrderTransfer → moveOrderFilesToDocument) can only move item
                    // files that are already linked (finalized + entities) to the original
                    // items. Saving after the order PUT leaves them on the originals and the
                    // clone ships without test docs/images.
                    if(!(await this.saveItemFiles())){
                        this.navigationStore.toggle(false);
                        this.isSubmitting = false;
                        return;
                    }
                    const envelope = new FormData();
                    envelope.append('data', JSON.stringify(this.formData));
                    for(const [key, fileItem] of Object.entries(this.formData.files || {})){
                        if(fileItem && !fileItem.uploading && fileItem.reference){
                            envelope.append(key, JSON.stringify(fileItem.reference));
                        } else if(fileItem && fileItem.file) {
                            envelope.append(key, fileItem.file);
                        }
                    }
                    let response;
                    try{
                        response = await this.plib.request({
                            url: '/api/v1/document'+(this.id !== undefined ? '/'+this.id : ''),
                            method: this.id !== undefined ? 'PUT' : 'POST',
                        },null,envelope);
                    }catch(err){
                        this.navigationStore.toggle(false);
                        this.isSubmitting = false;
                        this.plib.toast(this.Swal,'error', err?.msg || 'Kaydetme sırasında hata oluştu');
                        return;
                    }
                    setTimeout(async () => {
                        this.navigationStore.toggle(false);
                        this.isSubmitting = false;
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
                            const targetList = this.targetList;
                            this.Swal.fire({ icon: 'info', html, confirmButtonText: 'Anladım', allowOutsideClick: false }).then(() => {
                                if (response.success) this.$router.push({ name: targetList });
                            });
                        } else {
                            const targetList2 = this.targetList;
                            this.plib.toast(this.Swal, response.success ? 'success' : 'error', msg,() => {
                                if(response.success) this.$router.push({ name: targetList2 });
                            });
                        }
                    }, 300);
                }else{
                    this.navigationStore.toggle(false);
                    this.isSubmitting = false;
                    this.plib.toast(this.Swal,'info','Eksik Alanları Doldurmalısınız..',()=>{});
                }
            },
            onItemsSelected(items){
                this.selectedItems = items;
            },
            async refreshSevkOrder(){
                try{
                    const r = await this.plib.request({url:'/api/v1/document/'+this.id, method:'GET'}, null);
                    this.rawData = r?.data || this.rawData;
                    this.formDataStore.rawData = r?.data || this.formDataStore.rawData;
                    try{ this.parsedStatus = JSON.parse(this.rawData?.document?.status || '[]'); }catch(e){ this.parsedStatus=[]; }
                    try{
                        this.sevkDepo = this.sevkEnts.depo_yeri || this.sevkDepo;
                        this.sevkAdres = this.sevkEnts.sevk_adresi || this.sevkAdres;
                        this.sevkTarihi = this.sevkEnts.sevk_tarihi || this.sevkTarihi;
                        this.parseSevkExistingFiles();
                    }catch(e){}
                }catch(e){}
            },
            parseSevkExistingFiles(){
                try{
                    const form = this.rawData?.formFormat?.['op-doc-order-form'] || {};
                    for(const connId in form){
                        const ents = form[connId]?.entities || {};
                        for(const tag in ents){
                            if(!tag.includes('proforma') && !tag.includes('irsaliye')) continue;
                            const raw = ents[tag];
                            let parsed = null;
                            if(typeof raw === 'object' && raw !== null && raw.id) parsed = raw;
                            else if(typeof raw === 'string'){ try{ parsed = JSON.parse(raw); }catch(e){ continue; } }
                            if(!parsed || !parsed.id) continue;
                            if(tag.includes('proforma') && !this.sevkExistingProforma) this.sevkExistingProforma = {...parsed, entity_tag: tag, connId};
                            if(tag.includes('irsaliye') && !this.sevkExistingIrsaliye) this.sevkExistingIrsaliye = {...parsed, entity_tag: tag, connId};
                        }
                    }
                }catch(e){}
            },
            getSevkRowId(){
                const form = this.rawData?.formFormat?.['op-doc-order-form'] || {};
                const keys = Object.keys(form);
                if(keys.length) return keys[0];
                return 'new-' + Date.now();
            },
            onSevkFileSelect(e, type){
                const f = e.target.files && e.target.files[0];
                if(type === 'proforma') this.sevkProformaFile = f || null;
                else this.sevkIrsaliyeFile = f || null;
                if(f) this.handleSevkTempUpload(f, type);
            },
            async handleSevkTempUpload(file, type){
                try{
                    const fd = new FormData(); fd.append('file', file);
                    const rsp = await this.plib.request({url:'/api/v1/temp-upload', method:'POST'}, null, fd);
                    const ref = rsp?.data?.reference_id ? rsp.data : (rsp?.reference_id ? rsp : null);
                    if(rsp && rsp.success && ref && ref.reference_id){
                        if(type === 'proforma') this.sevkProformaRef = ref;
                        else this.sevkIrsaliyeRef = ref;
                    }
                }catch(e){}
            },
            async uploadSevkFiles(){
                const rowId = this.getSevkRowId();
                const key = 'op-doc-order-form**' + rowId;
                const files = {};
                if(this.sevkProformaFile || this.sevkProformaRef){
                    const fid = 'new-' + Date.now() + '-pro';
                    const fkey = 'dynamicFile**sevk_docs**' + fid + '**' + rowId + '*-*proforma_file**sevk_docs**' + rowId;
                    files[fkey] = this.sevkProformaRef?.reference_id ? {reference: this.sevkProformaRef} : {file: this.sevkProformaFile};
                }
                if(this.sevkIrsaliyeFile || this.sevkIrsaliyeRef){
                    const fid = 'new-' + Date.now() + '-irs';
                    const fkey = 'dynamicFile**sevk_docs**' + fid + '**' + rowId + '*-*irsaliye_file**sevk_docs**' + rowId;
                    files[fkey] = this.sevkIrsaliyeRef?.reference_id ? {reference: this.sevkIrsaliyeRef} : {file: this.sevkIrsaliyeFile};
                }
                if(!Object.keys(files).length) return {success: true};
                const envelope = new FormData();
                envelope.append('data', JSON.stringify({typeKey:'op-doc-order', dynamicF:{[key]:{tag:'op-doc-order-form', entities:{}}}}));
                for(const k in files){
                    const it = files[k];
                    envelope.append(k, it.reference ? JSON.stringify(it.reference) : it.file);
                }
                const rsp = await this.plib.request({url:'/api/v1/document/'+this.id, method:'PUT'}, null, envelope);
                if(rsp?.success){
                    this.sevkProformaFile = null; this.sevkIrsaliyeFile = null;
                    this.sevkProformaRef = null; this.sevkIrsaliyeRef = null;
                    await this.refreshSevkOrder();
                }
                return rsp;
            },
            async callSevk(url, payload){
                if(this.sevkLoading) return;
                this.sevkLoading = true;
                try{
                    const fd = new FormData();
                    fd.append('id', this.id);
                    for(const k in (payload || {})){ if(payload[k] !== undefined && payload[k] !== '') fd.append(k, payload[k]); }
                    const rsp = await this.plib.request({url, method:'POST'}, null, fd);
                    this.plib.toast(this.Swal, rsp?.success ? 'success' : 'error', rsp?.msg || rsp?.message || 'İşlem tamam');
                    if(rsp?.success) await this.refreshSevkOrder();
                    return rsp;
                } finally { this.sevkLoading = false; }
            },
            sevkStart(){ return this.callSevk('/api/v1/orders/sevk/start', {note:'Sevk onayına alındı'}); },
            sevkKalite(){ if(!this.sevkDepo) return this.Swal.fire({icon:'warning', title:'Depo yeri zorunlu'}); return this.callSevk('/api/v1/orders/sevk/advance', {op_key:'doc_trans_order_sevk_kalite_ok', depo_yeri:this.sevkDepo, note:'Sevk kalite onayı'}); },
            sevkAdresKaydet(){ if(!this.sevkAdres) return this.Swal.fire({icon:'warning', title:'Sevk adresi zorunlu'}); return this.callSevk('/api/v1/orders/sevk/advance', {op_key:'doc_trans_order_sevk_addressed', sevk_adresi:this.sevkAdres, sevk_adres_diger:this.sevkAdresDiger, note:'Adres girildi'}); },
            sevkTarihKaydet(){ if(!this.sevkTarihi) return this.Swal.fire({icon:'warning', title:'Tarih seçin'}); return this.callSevk('/api/v1/orders/sevk/advance', {op_key:'doc_trans_order_sevk_dated', sevk_tarihi:this.sevkTarihi, note:'Tarih seçildi'}); },
            async sevkProformaKaydet(){
                const up = await this.uploadSevkFiles();
                if(up && up.success === false){ this.Swal.fire({icon:'error', title:'Dosya yüklenemedi', text: up.msg}); return; }
                return this.callSevk('/api/v1/orders/sevk/advance', {op_key:'doc_trans_order_sevk_proforma', note:'Proforma yüklendi'});
            },
            sevkUygun(){ return this.callSevk('/api/v1/orders/sevk/control', {uygun:1, note:this.sevkKontrolNote || 'Uygun'}); },
            sevkRed(){
                if(!this.sevkKontrolNote) return this.Swal.fire({icon:'warning', title:'Red gerekçesi yazın'});
                return this.callSevk('/api/v1/orders/sevk/control', {uygun:0, note:this.sevkKontrolNote});
            },
            sevkShip(){ return this.callSevk('/api/v1/orders/sevk/advance', {op_key:'doc_trans_order_sevk_shipped', sevk_kontrol_ok:'1', note:'Sevkiyat gerçekleşti'}); },
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
                const targetListCancel = this.targetList;
                this.plib.toast(this.Swal, rsp.success?'success':'error', rsp.msg||'İşlem Tamamlandı',()=>{
                    if(rsp.success) this.$router.push({name: targetListCancel});
                });
            },
            async kaliteOnayi(){
                if(this.kaliteLoading) return;
                if(!this.authStore.permissions?.includes('per-07-02')){
                    this.Swal.fire({icon:'warning', title:'Yetki yok', text:'Sevk onayı için yetkiniz yok (per-07-02)', confirmButtonText:'Tamam'});
                    return;
                }
                if(['doc_trans_order_approved','doc_trans_order_rejected'].includes(this.orderStatus)){
                    this.Swal.fire({icon:'info', title:'Zaten Kapalı', text:'Sipariş zaten kapatılmış.', confirmButtonText:'Tamam'});
                    return;
                }
                const conf = await this.Swal.fire({
                    title: 'Sevk Onayı Ver',
                    html: '<div style="text-align:left;font-size:13px;color:#334155;">Tüm bekleyen dosyalar <b>kabul</b> edilecek ve sipariş <b>Sevk Onayı Verildi</b> durumuna geçecek.<br><span style="color:#64748b;font-size:11.5px;">Onay sonrası sipariş kilitlenir.</span></div>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Evet, Onayla',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#22c55e',
                    cancelButtonColor: '#64748b'
                });
                if(!conf.isConfirmed) return;
                this.kaliteLoading = true;
                this.Swal.fire({
                    title: 'Onaylanıyor...',
                    html: '<div style="display:flex;justify-content:center;padding:12px"><i class="ki-outline ki-loading" style="font-size:24px;animation:spin 1s linear infinite;color:#22c55e"></i></div><div style="font-size:12px;color:#64748b;">Lütfen bekleyin</div>',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => this.Swal.showLoading()
                });
                try{
                    const fd = new FormData();
                    fd.append('id', this.id);
                    fd.append('op_key', 'doc_trans_order_approved');
                    fd.append('note', 'Sevk onayı verildi ve kapatıldı (detay)');
                    const rsp = await this.plib.request({url:'/api/v1/trans/set-status', method:'POST'}, null, fd);
                    this.Swal.close();
                    if(rsp?.success){
                        this.plib.toast(this.Swal, 'success', rsp.msg || 'Sevk onayı verildi — tüm dosyalar kabul edildi', async () => {
                            try{
                                const refreshed = await this.plib.request({url:'/api/v1/document/'+this.id, method:'GET'}, null);
                                this.rawData = refreshed?.data || this.rawData;
                                this.formDataStore.rawData = refreshed?.data || this.formDataStore.rawData;
                                try{ this.parsedStatus = JSON.parse(this.rawData?.document?.status || '[]'); }catch(e){ this.parsedStatus=[]; }
                            }catch(e){}
                        });
                    } else {
                        this.Swal.fire({icon:'error', title:'Olmadı', text: rsp?.msg || rsp?.message || 'Onay verilemedi', confirmButtonText:'Tamam'});
                    }
                }catch(e){
                    this.Swal.close();
                    this.Swal.fire({icon:'error', title:'Hata', text: e?.msg || e?.message || 'Beklenmeyen hata', confirmButtonText:'Tamam'});
                } finally {
                    this.kaliteLoading = false;
                }
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
                            { key:'siparis_kodu', type:'=', value: this.parentOrderNo }
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
                        const target = this.isTedarik ? 'TedarikOrderForm' : 'OrderForm';
                        this.$router.push({name: target, params:{id: match.id}, query:{...this.$route.query}});
                    } else {
                        this.plib.toast(this.Swal, 'info', 'Orijinal sipariş bulunamadı: ' + this.parentOrderNo);
                    }
                } catch(e) {
                    console.error('navigateToParentOrder failed', e);
                }
            },
            goBackToList(){
                const target = this.targetList;
                try{ this.$router.push({name: target, query:{...this.$route.query}}); }catch(e){ window.history.back(); }
            },
            async printMalzemeKabul(){
                const showWarn = (title, text) => {
                    this.Swal.fire({ icon:'warning', title, text, confirmButtonText:'Tamam', allowOutsideClick:true });
                };
                const itemTable = this.$refs.itemTable;
                const effectiveTransferMode = this.canSend ? this.transferMode : this.storedTransferMode;
                const desc = (this.tedarikDesc && String(this.tedarikDesc).trim()) || this.getFieldValue('order_desc') || this.orderEntities.order_desc || '';
                const imalatci = (this.tedarikImalatci && String(this.tedarikImalatci).trim()) || this.getFieldValue('imalatci_firma_adi') || this.orderEntities.imalatci_firma_adi || '';
                const err = validatePrintForm({ itemTable, effectiveTransferMode, hasPartitions: this.hasPartitions, selectedItems: this.selectedItems, canSend: this.canSend, imalatci });
                if(err){ showWarn(err.title, err.text); return; }
                this.printingKabul = true;
                this.Swal.fire({ title:'PDF oluşturuluyor...', html:'<div style="display:flex;justify-content:center;padding:10px"><i class="ki-outline ki-loading" style="font-size:26px;animation:spin 1s linear infinite;color:#059669"></i></div><div style="font-size:0.85rem;color:#64748b">Lütfen bekleyin, form hazırlanıyor</div>', allowOutsideClick:false, showConfirmButton:false, didOpen:()=>this.Swal.showLoading() });
                const items = [];
                if(effectiveTransferMode === 'partial' && this.selectedItems.length){
                    for(const sel of this.selectedItems){
                        const item = itemTable.items.find(i => i.id == sel.qnid);
                        if(!item) continue;
                        items.push({ prod_code: item.prod_code || '', title: item.title || '', unit: item.unit || '', quantity: item.quantity, accept_quantity: sel.amount || item.quantity });
                    }
                } else {
                    for(const item of itemTable.items){
                        items.push({ prod_code: item.prod_code || '', title: item.title || '', unit: item.unit || '', quantity: item.quantity, accept_quantity: item.quantity });
                    }
                }
                let orderNo = this.orderEntities.order_no || '';
                if(effectiveTransferMode === 'partial') orderNo = await this.calcCloneSuffix(orderNo);
                const fd = new FormData();
                fd.append('qnid', this.id);
                fd.append('transfer_mode', effectiveTransferMode);
                fd.append('items', JSON.stringify(items));
                fd.append('order_no', orderNo);
                fd.append('buying_no', this.orderEntities.buying_no || '');
                fd.append('created_at', this.orderEntities.created_at || '');
                fd.append('order_desc', desc);
                fd.append('imalatci_firma_adi', imalatci);
                try{
                    await downloadPdf('/api/v1/export/malzeme-kabul', 'malzeme-kabul-' + (this.orderEntities.order_no || this.id) + '.pdf', fd);
                    this.Swal.close();
                } catch(e){
                    this.Swal.close();
                    this.Swal.fire({ icon:'error', title:'PDF Oluşturulamadı', text:'PDF indirilemedi: ' + e.message, confirmButtonText:'Tamam' });
                } finally { this.printingKabul = false; }
            },
            async printMalzemeCinsMiktar(){
                const showWarn = (title, text) => {
                    this.Swal.fire({ icon:'warning', title, text, confirmButtonText:'Tamam', allowOutsideClick:true });
                };
                const itemTable = this.$refs.itemTable;
                const effectiveTransferMode = this.canSend ? this.transferMode : this.storedTransferMode;
                const desc = (this.tedarikDesc && String(this.tedarikDesc).trim()) || this.getFieldValue('order_desc') || this.orderEntities.order_desc || '';
                const imalatci = (this.tedarikImalatci && String(this.tedarikImalatci).trim()) || this.getFieldValue('imalatci_firma_adi') || this.orderEntities.imalatci_firma_adi || '';
                const err = validatePrintForm({ itemTable, effectiveTransferMode, hasPartitions: this.hasPartitions, selectedItems: this.selectedItems, canSend: this.canSend, imalatci });
                if(err){ showWarn(err.title, err.text); return; }
                this.printingCins = true;
                this.Swal.fire({ title:'PDF oluşturuluyor...', html:'<div style="display:flex;justify-content:center;padding:10px"><i class="ki-outline ki-loading" style="font-size:26px;animation:spin 1s linear infinite;color:#7c3aed"></i></div><div style="font-size:0.85rem;color:#64748b">Lütfen bekleyin, form hazırlanıyor</div>', allowOutsideClick:false, showConfirmButton:false, didOpen:()=>this.Swal.showLoading() });
                const items = [];
                if(effectiveTransferMode === 'partial' && this.selectedItems.length){
                    for(const sel of this.selectedItems){
                        const item = itemTable.items.find(i => i.id == sel.qnid);
                        if(!item) continue;
                        const frontendSerials = itemTable.serials?.[item.id] || [];
                        const serials = frontendSerials.length > 0 ? frontendSerials : (item.serials || []);
                        if(serials.length){
                            for(const s of serials){
                                items.push({ prod_code: item.prod_code || '', title: item.title || '', unit: item.unit || '', quantity: s.quantity || '-', serial_no: s.serial_no || '-' });
                            }
                        } else {
                            items.push({ prod_code: item.prod_code || '', title: item.title || '', unit: item.unit || '', quantity: sel.amount || item.quantity, serial_no: '-' });
                        }
                    }
                } else {
                    for(const item of itemTable.items){
                        const frontendSerials = itemTable.serials?.[item.id] || [];
                        const serials = frontendSerials.length > 0 ? frontendSerials : (item.serials || []);
                        if(serials.length){
                            for(const s of serials){
                                items.push({ prod_code: item.prod_code || '', title: item.title || '', unit: item.unit || '', quantity: s.quantity || '-', serial_no: s.serial_no || '-' });
                            }
                        } else {
                            items.push({ prod_code: item.prod_code || '', title: item.title || '', unit: item.unit || '', quantity: item.quantity, serial_no: '-' });
                        }
                    }
                }
                let orderNo = this.orderEntities.order_no || '';
                if(effectiveTransferMode === 'partial') orderNo = await this.calcCloneSuffix(orderNo);
                const fd = new FormData();
                fd.append('qnid', this.id);
                fd.append('transfer_mode', effectiveTransferMode);
                fd.append('items', JSON.stringify(items));
                fd.append('order_no', orderNo);
                fd.append('buying_no', this.orderEntities.buying_no || '');
                fd.append('created_at', this.orderEntities.created_at || '');
                fd.append('order_desc', desc);
                fd.append('imalatci_firma_adi', imalatci);
                try{
                    await downloadPdf('/api/v1/export/malzeme-cins-miktar-kabul', 'malzeme-cins-miktar-' + (this.orderEntities.order_no || this.id) + '.pdf', fd);
                    this.Swal.close();
                } catch(e){
                    this.Swal.close();
                    this.Swal.fire({ icon:'error', title:'PDF Oluşturulamadı', text:'PDF indirilemedi: ' + e.message, confirmButtonText:'Tamam' });
                } finally { this.printingCins = false; }
            },
            async downloadAllForms(){
                if(this.downloadingAll) return;
                if(!this.id){
                    this.Swal.fire({ icon:'warning', title:'Sipariş bulunamadı', text:'Qnid eksik', confirmButtonText:'Tamam' });
                    return;
                }
                this.downloadingAll = true;
                this.Swal.fire({ title:'İndiriliyor…', html:'<div style="display:flex;justify-content:center;padding:10px"><i class="ki-outline ki-loading" style="font-size:26px;animation:spin 1s linear infinite;color:#FF5A1F"></i></div><div style="font-size:0.85rem;color:#64748b">Formlar hazırlanıyor, lütfen bekleyin</div>', allowOutsideClick:false, showConfirmButton:false, didOpen:()=>this.Swal.showLoading() });
                try{
                    const token = localStorage.getItem('token') || '';
                    const res = await fetch(`/api/v1/order/${this.id}/download-all`, { method:'GET', headers: { 'Authorization': `Bearer ${token}`, 'Accept':'application/json' } });
                    if(!res.ok){
                        let msg = `HTTP ${res.status}`;
                        try{ const j=await res.json(); msg=j.msg||j.message||msg; }catch(e){}
                        throw new Error(msg);
                    }
                    const blob = await res.blob();
                    const cd = res.headers.get('Content-Disposition') || '';
                    let filename = `order-${this.orderEntities?.order_no || this.id}-tum-formlar.zip`;
                    const m = cd.match(/filename="?([^"]+)"?/);
                    if(m) filename = m[1];
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a'); a.href=url; a.download=filename; document.body.appendChild(a); a.click(); a.remove();
                    setTimeout(()=>URL.revokeObjectURL(url), 2000);
                    this.Swal.close();
                    this.plib.toast(this.Swal,'success','İndirildi');
                }catch(e){
                    this.Swal.close();
                    this.Swal.fire({ icon:'error', title:'İndirilemedi', text:e.message||'Bilinmeyen hata', confirmButtonText:'Tamam' });
                }finally{ this.downloadingAll = false; }
            },
            async showFileHistory(kind){
                const isKabul = kind === 'kabul';
                const title = isKabul ? 'Malzeme Kabul Formu — Geçmiş' : 'Malzeme Cinsi Kabul Formu — Geçmiş';
                const filterTag = isKabul ? 'transfer_kabul_file' : 'transfer_cins_file';
                this.Swal.fire({ title, html:'<div style="padding:20px;text-align:center;color:#64748b;"><i class="ki-outline ki-loading" style="font-size:22px;animation:spin 1s linear infinite;"></i><div style="margin-top:8px;">Yükleniyor…</div></div>', showConfirmButton:false, allowOutsideClick:false, didOpen:()=>this.Swal.showLoading() });
                try{
                    const token = localStorage.getItem('token') || '';
                    const res = await fetch(`/api/v1/order/${this.id}/files`, { headers:{ 'Authorization':`Bearer ${token}` } });
                    if(!res.ok) throw new Error(`HTTP ${res.status}`);
                    const j = await res.json();
                    const files = (j.data?.files || []).filter(f => (f.entity_tag||'').includes(filterTag));
                    if(!files.length){
                        this.Swal.fire({ icon:'info', title:'Kayıt yok', text:'Bu tipte henüz form yüklenmemiş.', confirmButtonText:'Tamam' });
                        return;
                    }
                    const rows = files.map(f=>{
                        const st = f.last_status || {};
                        const op = st.op_key || '';
                        const statusText = op==='doc_file_accepted' ? 'Onaylandı' : op==='doc_file_rejected' ? 'Reddedildi' : op==='doc_file_refreshed' ? 'Yenilendi' : 'Beklemede';
                        const statusColor = op==='doc_file_accepted' ? '#16a34a' : op==='doc_file_rejected' ? '#ef4444' : op==='doc_file_waiting' ? '#f59e0b' : '#64748b';
                        const bg = op==='doc_file_accepted' ? '#dcfce7' : op==='doc_file_rejected' ? '#fee2e2' : '#fef3c7';
                        const who = this.escapeHtml(st.name || '-');
                        const whenRaw = st.created_at || f.file_created_at;
                        const when = this.fmtDateTime(whenRaw);
                        // st.note is JSON string like {"actor":"Admin ...","from":"doc_file_...","to":"...","note":"SDASD"} or null
                        let noteText = '';
                        if(st.note){
                            try{
                                const parsed = JSON.parse(st.note);
                                if(parsed && typeof parsed === 'object'){
                                    noteText = parsed.note ?? parsed.Note ?? '';
                                }else{
                                    noteText = String(parsed);
                                }
                            }catch(e){
                                noteText = String(st.note);
                            }
                            if(noteText === 'null' || noteText === 'undefined') noteText = '';
                            noteText = String(noteText||'').trim();
                        }
                        const note = noteText ? `<div style="margin-top:4px;padding:6px 8px;background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;font-size:12px;color:#9a3412;"><b>Not:</b> ${this.escapeHtml(noteText)}</div>` : '';
                        const qnid = this.escapeHtml(f.file_qnid);
                        return `<div style="display:flex;flex-direction:column;gap:6px;padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;margin-bottom:8px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;background:${bg};color:${statusColor};border:1px solid ${statusColor}20;">${statusText}</span>
                                <span style="font-size:12px;color:#64748b;flex:1;">${when} • ${who} tarafından</span>
                                <button data-qnid="${qnid}" class="hist-preview" style="padding:6px 10px;border-radius:8px;border:1px solid #e0e7ff;background:#eef2ff;color:#4f46e5;font-size:12px;font-weight:600;cursor:pointer;"><i class="ki-outline ki-eye" style="font-size:12px;"></i> Gör</button>
                                <button data-qnid="${qnid}" class="hist-download" style="padding:6px 10px;border-radius:8px;border:1px solid #fed7aa;background:#fff7ed;color:#9a3412;font-size:12px;font-weight:600;cursor:pointer;"><i class="ki-outline ki-file-down" style="font-size:12px;"></i> İndir</button>
                            </div>
                            <div style="font-size:11px;color:#94a3b8;word-break:break-all;">${qnid}</div>
                            ${note}
                        </div>`;
                    }).join('');
                    const html = `<div style="max-height:55vh;overflow-y:auto;padding:4px;">${rows}<div style="margin-top:10px;font-size:11px;color:#94a3b8;text-align:center;">Toplam ${files.length} kayıt • en yeni üstte</div></div>`;
                    this.Swal.fire({ title, html, width:'640px', showCloseButton:true, showConfirmButton:false, didOpen:()=>{
                        document.querySelectorAll('.hist-preview').forEach(b=> b.addEventListener('click', ()=> window.open('/order-file/'+b.dataset.qnid, '_blank')));
                        document.querySelectorAll('.hist-download').forEach(b=> b.addEventListener('click', ()=> { const u='/order-file/'+b.dataset.qnid; const a=document.createElement('a'); a.href=u; a.download=''; document.body.appendChild(a); a.click(); a.remove(); }));
                    }});
                }catch(e){
                    this.Swal.fire({ icon:'error', title:'Yüklenemedi', text:e.message, confirmButtonText:'Tamam' });
                }
            },
            formatDate,
            fmtDateTime,
            escapeHtml,
            noteOf,
            getFieldValue(name){
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
            },
            async calcCloneSuffix(orderNo){
                if(/\-\d+$/.test(orderNo)) return orderNo;
                const baseNo = orderNo;
                try {
                    // Reuse the partitions payload from checkHasPartitions — same endpoint, no second round-trip
                    let clones = (this.sevkPartitions && this.sevkPartitions.base === baseNo) ? (this.sevkPartitions.clones || []) : null;
                    if(!clones){
                        const rsp = await this.plib.request({url:'/api/v1/order/'+this.id+'/partitions', method:'GET'}, null);
                        clones = rsp?.clones || [];
                    }
                    let maxX = 0;
                    for(const r of clones){
                        const m = String(r.order_no || '').match(/-(\d+)$/);
                        if(m) maxX = Math.max(maxX, parseInt(m[1]));
                    }
                    return baseNo + '-' + (maxX + 1);
                } catch(e){
                    return baseNo + '-1';
                }
            }
        }
    }
</script>
<template>
    <!-- TEDARIK PANEL — screenshot 1:1 fresh order -->
    <div v-if="isTedarik" style="padding-bottom: 100px;">
        <div v-if="id && loadForm" class="tedarik-detail">
            <!-- Header: company + meta -->
            <div class="tedarik-detail-header tedarik-header--beautified">
                <div class="tedarik-detail-header-top">
                    <div class="tedarik-detail-header-left">
                        <button @click="goBackToList" class="tedarik-back-link" title="Siparişler" aria-label="Geri">
                            <i class="ki-outline ki-arrow-left"></i>
                            <span>Tüm Siparişler</span>
                        </button>
                        <div class="tedarik-company-row">
                            <span class="tedarik-company-icon"><i class="ki-outline ki-office-bag"></i></span>
                            <div class="tedarik-detail-title">{{ orderEntities.ctitle || orderEntities.spec_code || 'Sipariş Detayı' }}</div>
                            <span v-if="orderEntities.spec_code" class="tedarik-tdno-badge">TDNO : {{ (orderEntities.spec_code || '').replace(/^0+/, '') || orderEntities.spec_code }}</span>
                        </div>
                    </div>
                    <div class="tedarik-detail-meta">
                        <div class="tedarik-meta-item"><span>Alım No :</span><b>{{ orderEntities.buying_no || '-' }}</b></div>
                        <div class="tedarik-meta-item"><span>Sipariş No :</span><b>{{ orderEntities.order_no || '-' }}</b></div>
                        <div class="tedarik-meta-item"><span>Tarih :</span><b>{{ formatDate(orderEntities.created_at) }}</b></div>
                    </div>
                </div>
                <!-- Drum — order current status -->
                <div class="tedarik-status-drum" :class="tedarikStatus.cls">
                    <span class="tedarik-status-dot"></span>
                    <i :class="tedarikStatus.icon"></i>
                    <span>{{ tedarikStatus.label }}</span>
                </div>
            </div>
            <!-- Yellow warning -->
            <div class="tedarik-warning">
                <i class="ki-outline ki-information-2"></i>
                <div>Siparişi tek seferde veya parçalı olarak gönderebilirsiniz. Sipariş onaylanıncaya kadar durumu sistem üzerinden takip edebilirsiniz.<br><span style="font-weight:600;">Not:</span> Tek seferde gönderimde tüm kalemler birlikte gönderilir. Parçalı gönderimde gönderilecek kalemleri işaretleyip miktar girmeniz zorunludur. Bir sipariş bir kez parçalı gönderildikten sonra sadece parçalı gönderim yapılabilir.</div>
            </div>

            <!-- Clone origin for tedarik -->
            <div class="tedarik-clone-banner" v-if="isCloneOrder" style="margin-bottom:14px; background:linear-gradient(135deg,#eef2ff 0%,#e0e7ff 100%); border:1px solid #c7d2fe; border-radius:12px; padding:14px 18px; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:12px;"><div style="width:36px; height:36px; border-radius:10px; background:#6366f1; color:#fff; display:flex; align-items:center; justify-content:center;"><i class="ki-outline ki-arrow-top-right"></i></div><div><div style="font-size:0.82rem; color:#6366f1;">Kaynak Sipariş</div><div style="font-weight:700; color:#312e81;">{{ parentOrderNo }}</div></div></div>
                <button @click="navigateToParentOrder" style="background:#fff; border:1px solid #c7d2fe; border-radius:10px; padding:8px 16px; color:#4f46e5; font-weight:600; cursor:pointer;">Orijinal Siparişe Git</button>
            </div>

            <!-- Sevk focus bar -->
            <div v-if="sevkFocus && loadForm" style="margin-bottom:12px; display:flex; align-items:center; gap:10px; padding:10px 14px; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:12px; font-size:12.5px; color:#5b21b6;">
                <i class="ki-outline ki-delivery" style="font-size:16px;"></i>
                <span><b>Sevk odağı</b> — {{ orderEntities.order_no || '' }} · {{ tedarikStatus.label }}</span>
                <button @click="toggleAllDetails" style="margin-left:auto; font-size:11.5px; font-weight:700; padding:6px 12px; border-radius:8px; border:1px solid #c4b5fd; background:#fff; color:#5b21b6; cursor:pointer;">{{ showAllDetails ? 'Özeti göster' : 'Tüm detayı göster' }}</button>
            </div>

            <!-- Step 1 -->
            <div class="tedarik-step-card" v-show="!sevkFocus || showAllDetails">
                <div class="tedarik-step-head"><span class="tedarik-step-num">1</span><span>Malzeme ve tedarik tipini seçiniz.</span></div>
                <div class="tedarik-step-body">
                    <div style="margin-bottom:10px; font-size:13px; color:#475569;">Sevkiyat Tipi <span style="color:#ef4444;">*</span></div>
                    <div style="display:flex; gap:12px; margin-bottom:14px;">
                        <label class="tedarik-radio-card" :class="{ active: tedarikDisplayMode==='at_once', disabled: hasPartitions || isLocked }" @click="!hasPartitions && !isLocked && (transferMode='at_once')">
                            <input type="radio" value="at_once" v-model="transferMode" :disabled="hasPartitions || isLocked" style="accent-color:#FF5A1F;">
                            <span>Tek Parça Sevkiyat</span>
                            <i v-if="isLocked" class="ki-outline ki-lock-2" style="margin-left:6px;font-size:13px;color:#94a3b8;"></i>
                        </label>
                        <label class="tedarik-radio-card" :class="{ active: tedarikDisplayMode==='partial', disabled: isLocked }" @click="!isLocked && (transferMode='partial')">
                            <input type="radio" value="partial" v-model="transferMode" :disabled="isLocked" style="accent-color:#FF5A1F;">
                            <span>Parçalı Sevkiyat</span>
                            <i v-if="isLocked" class="ki-outline ki-lock-2" style="margin-left:6px;font-size:13px;color:#94a3b8;"></i>
                        </label>
                    </div>
                    <div v-if="isLocked" style="margin-bottom:12px; padding:10px 14px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; color:#64748b; font-size:0.85rem; display:flex; align-items:center; gap:8px;"><i class="ki-outline ki-lock-2" style="font-size:14px;"></i> Sipariş kilitlendi — sevkiyat tipi değiştirilemez ({{ tedarikDisplayMode==='at_once' ? 'Tek Parça' : 'Parçalı' }} olarak gönderildi).</div>
                    <div v-if="hasPartitions" style="margin-bottom:12px; padding:10px 14px; background:#fef3c7; border:1px solid #fde68a; border-radius:8px; color:#92400e; font-size:0.85rem;">Bu sipariş daha önce parçalı gönderildiği için artık sadece <b>Parçalı</b> gönderim yapılabilir.</div>
                    <OrderItemTable ref="itemTable" :key="(canSend ? transferMode : 'ro')+'-tedarik'" :orderId="id" :orderNumericId="formDataStore.rawData?.document?.id" :orderDate="orderEntities.created_at || ''" :selectable="canSend && transferMode==='partial'" :atOnceMode="canSend && transferMode==='at_once'" :highlightQnid="highlightItemQnid" :containerSuffix="canSend ? '-sel' : ''" :readonly="isLocked" :hideHeader="true" :tedarikOrderStatus="tedarikStatus" @select="onItemsSelected" @serials="onItemSerials" @item-files="onItemFiles" />
                </div>
            </div>

            <!-- Step 2 -->
            <div class="tedarik-step-card" v-show="!sevkFocus || showAllDetails">
                <div class="tedarik-step-head"><span class="tedarik-step-num">2</span><span>İsterseniz açıklama girebilirsiniz.</span></div>
                <div class="tedarik-step-body">
                    <textarea v-model="tedarikDesc" rows="4" placeholder="Tercih ettiğiniz açıklamayı yazınız.." style="width:100%; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; font-size:13.5px; color:#1e293b; outline:none; resize:vertical;" :disabled="isLocked" :style="isLocked ? 'background:#f8fafc;opacity:0.72;cursor:not-allowed;' : ''"></textarea>
                    <div v-if="isLocked" style="margin-top:6px; font-size:11.5px; color:#94a3b8; display:flex; align-items:center; gap:4px;"><i class="ki-outline ki-lock-2" style="font-size:12px;"></i> Açıklama ilk kayıttan sonra değiştirilemez</div>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="tedarik-step-card" v-show="!sevkFocus || showAllDetails">
                <div class="tedarik-step-head"><span class="tedarik-step-num">3</span><span>İmalatçı firma adını giriniz.</span></div>
                <div class="tedarik-step-body">
                    <input v-model="tedarikImalatci" placeholder="İmalatçı Firma Adı Giriniz *" style="width:100%; max-width:520px; height:44px; border:1px solid #e2e8f0; border-radius:10px; padding:0 14px; font-size:13.5px; color:#1e293b; outline:none;" :disabled="isLocked" :style="isLocked ? 'background:#f8fafc;opacity:0.72;cursor:not-allowed;' : ''" />
                    <div v-if="isLocked" style="margin-top:6px; font-size:11.5px; color:#94a3b8; display:flex; align-items:center; gap:4px;"><i class="ki-outline ki-lock-2" style="font-size:12px;"></i> İmalatçı firma ilk kayıttan sonra değiştirilemez</div>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="tedarik-step-card" v-show="!sevkFocus || showAllDetails">
                <div class="tedarik-step-head"><span class="tedarik-step-num">4</span><span>Lütfen gerekli formları indirin.</span></div>
                <div class="tedarik-step-body">
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="button" @click="printMalzemeKabul" :disabled="printingKabul" class="tedarik-orange-btn"><i :class="printingKabul ? 'ki-outline ki-loading' : 'ki-outline ki-printer'" :style="printingKabul ? 'animation:spin 1s linear infinite' : ''"></i> {{ printingKabul ? 'Oluşturuluyor...' : 'Malzeme Kabul Formu' }}</button>
                        <button type="button" @click="printMalzemeCinsMiktar" :disabled="printingCins" class="tedarik-orange-btn"><i :class="printingCins ? 'ki-outline ki-loading' : 'ki-outline ki-printer'" :style="printingCins ? 'animation:spin 1s linear infinite' : ''"></i> {{ printingCins ? 'Oluşturuluyor...' : 'Malzeme Cins-Miktar Kabul Formu' }}</button>
                    </div>
                    <div style="margin-top:8px; font-size:12px; color:#64748b;">İndirdiğiniz formları ıslak imzalı olarak imzalayıp bir sonraki adımda yükleyeceksiniz.</div>
                </div>
            </div>

            <!-- Step 5 -->
            <div class="tedarik-step-card" v-show="!sevkFocus || showAllDetails">
                <div class="tedarik-step-head"><span class="tedarik-step-num">5</span><span>Lütfen doldurduğunuz formları yükleyiniz <span style="color:#ef4444;">*</span></span></div>
                <div class="tedarik-step-body">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                        <div>
                            <div style="font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;">Malzeme Kabul Formu <span v-if="canSend || tedarikExistingKabul?.last_status?.op_key==='doc_file_rejected'" style="color:#ef4444;">*</span></div>
                            <!-- Locked + existing: viewable like admin -->
                            <div v-if="isFilesLocked && tedarikExistingKabul" style="display:flex; align-items:center; gap:8px; padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; min-width:0; overflow:hidden;">
                                <i class="ki-outline ki-document" style="font-size:16px;color:#3b82f6;"></i>
                                <span style="flex:1; min-width:0; font-size:13px; font-weight:500; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" :title="getTedarikDisplayName(tedarikExistingKabul,'Malzeme Kabul Formu')">{{ getTedarikDisplayName(tedarikExistingKabul,'Malzeme Kabul Formu') }}</span>
                                <span v-if="tedarikExistingKabul.last_status" style="font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px;" :style="tedarikExistingKabul.last_status.op_key==='doc_file_accepted' ? 'background:#dcfce7;color:#166534' : tedarikExistingKabul.last_status.op_key==='doc_file_rejected' ? 'background:#fee2e2;color:#991b1b' : 'background:#fef3c7;color:#92400e'">{{ tedarikExistingKabul.last_status.op_key==='doc_file_accepted' ? 'Onaylandı' : tedarikExistingKabul.last_status.op_key==='doc_file_rejected' ? 'Reddedildi' : 'Beklemede' }}</span>
                                <button type="button" @click="previewTedarikFile(tedarikExistingKabul)" style="width:28px; height:28px; border-radius:6px; border:1px solid #e0e7ff; background:#eef2ff; color:#6366f1; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="ki-outline ki-eye" style="font-size:14px;"></i></button>
                            </div>
                            <div v-if="isFilesLocked && tedarikExistingKabul && tedarikExistingKabul.last_status" style="margin-top:8px; display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; background:#fff; border:1px solid #e2e8f0; border-radius:8px;">
                                <div style="font-size:12px; color:#475569; min-width:0; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    <span style="font-weight:700;">{{ tedarikExistingKabul.last_status.op_key==='doc_file_accepted' ? 'Onaylayan' : tedarikExistingKabul.last_status.op_key==='doc_file_rejected' ? 'Reddeden' : 'İşlem' }}:</span>
                                    {{ tedarikExistingKabul.last_status.name || '-' }}
                                </div>
                                <button v-if="hasTedarikNote(tedarikExistingKabul)" type="button" @click="showTedarikFileNote(tedarikExistingKabul)" style="font-size:11px; font-weight:700; padding:5px 10px; border-radius:6px; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; cursor:pointer; flex-shrink:0;">Notu Gör</button>
                            </div>
                            <div v-else-if="isFilesLocked && !tedarikExistingKabul" style="padding:12px; background:#f8fafc; border:1px dashed #e2e8f0; border-radius:8px; font-size:13px; color:#94a3b8; text-align:center;">Henüz yüklenmedi — sipariş kilitli</div>
                            <template v-else>
                                <!-- Existing rejected: show rejected + upload to replace -->
                                <div v-if="tedarikExistingKabul && tedarikExistingKabul.last_status?.op_key==='doc_file_rejected'" style="display:flex; flex-direction:column; gap:8px;">
                                    <div style="display:flex; align-items:center; gap:8px; padding:10px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; min-width:0; overflow:hidden;">
                                        <i class="ki-outline ki-document" style="font-size:16px;color:#ef4444;"></i>
                                        <span style="flex:1; min-width:0; font-size:13px; color:#991b1b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" :title="getTedarikDisplayName(tedarikExistingKabul,'Malzeme Kabul Formu')">{{ getTedarikDisplayName(tedarikExistingKabul,'Malzeme Kabul Formu') }}</span>
                                        <span style="font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px; background:#fee2e2;color:#991b1b;">Reddedildi</span>
                                        <button type="button" @click="previewTedarikFile(tedarikExistingKabul)" style="width:28px; height:28px; border-radius:6px; border:1px solid #e0e7ff; background:#eef2ff; color:#6366f1; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="ki-outline ki-eye" style="font-size:14px;"></i></button>
                                    </div>
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; background:#fff7ed; border:1px solid #fed7aa; border-radius:8px;">
                                        <div style="font-size:12px; color:#9a3412; min-width:0; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                            <span style="font-weight:700;">Reddeden:</span> {{ tedarikExistingKabul.last_status?.name || '-' }}
                                        </div>
                                        <button v-if="hasTedarikNote(tedarikExistingKabul)" type="button" @click="showTedarikFileNote(tedarikExistingKabul)" style="font-size:11px; font-weight:700; padding:5px 10px; border-radius:6px; border:1px solid #fed7aa; background:#ffedd5; color:#9a3412; cursor:pointer; flex-shrink:0;">Notu Gör</button>
                                    </div>
                                    <label class="tedarik-file-wrap">
                                        <input type="file" accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx" @change="onTedarikFileSelect($event, 'kabul')" style="display:none;">
                                        <span class="tedarik-file-input"><span style="color:#64748b; font-size:13px;">{{ tedarikKabulFile ? tedarikKabulFile.name : 'Yeni dosya seç' }}</span><span class="tedarik-file-btn">Dosya Seç</span></span>
                                    </label>
                                </div>
                                <div v-else-if="tedarikExistingKabul" style="display:flex; flex-direction:column; gap:8px;">
                                    <div style="display:flex; align-items:center; gap:8px; padding:10px 12px; background:#f0fdf4; border:1px solid #86efac; border-radius:8px; min-width:0; overflow:hidden;">
                                        <i class="ki-outline ki-document" style="font-size:16px;color:#16a34a;"></i>
                                        <span style="flex:1; min-width:0; font-size:13px; font-weight:500; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" :title="getTedarikDisplayName(tedarikExistingKabul,'Malzeme Kabul Formu')">{{ getTedarikDisplayName(tedarikExistingKabul,'Malzeme Kabul Formu') }}</span>
                                        <span style="font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px;" :style="tedarikExistingKabul.last_status?.op_key==='doc_file_accepted' ? 'background:#dcfce7;color:#166534' : 'background:#fef3c7;color:#92400e'">{{ tedarikExistingKabul.last_status?.op_key==='doc_file_accepted' ? 'Onaylandı' : 'Beklemede' }}</span>
                                        <button type="button" @click="previewTedarikFile(tedarikExistingKabul)" style="width:28px; height:28px; border-radius:6px; border:1px solid #bbf7d0; background:#dcfce7; color:#16a34a; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="ki-outline ki-eye" style="font-size:14px;"></i></button>
                                    </div>
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; background:#fff; border:1px solid #e2e8f0; border-radius:8px;">
                                        <div style="font-size:12px; color:#475569; min-width:0; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                            <span style="font-weight:700;">{{ tedarikExistingKabul.last_status?.op_key==='doc_file_accepted' ? 'Onaylayan' : 'İşlem' }}:</span>
                                            {{ tedarikExistingKabul.last_status?.name || '-' }}
                                        </div>
                                        <button v-if="hasTedarikNote(tedarikExistingKabul)" type="button" @click="showTedarikFileNote(tedarikExistingKabul)" style="font-size:11px; font-weight:700; padding:5px 10px; border-radius:6px; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; cursor:pointer; flex-shrink:0;">Notu Gör</button>
                                    </div>
                                </div>
                                <label v-else class="tedarik-file-wrap" :style="isFilesLocked ? 'opacity:0.55;pointer-events:none;' : ''">
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx" @change="onTedarikFileSelect($event, 'kabul')" :disabled="isFilesLocked" style="display:none;">
                                    <span class="tedarik-file-input"><span style="color:#64748b; font-size:13px;">{{ tedarikKabulFile ? tedarikKabulFile.name : 'Dosya seçilmedi' }}</span><span class="tedarik-file-btn">Dosya Seç</span></span>
                                </label>
                            </template>
                            <div style="margin-top:6px; font-size:11.5px; color:#64748b;">Dosya yüklerken dosyanızın 42MB'dan küçük, JPG, PNG veya PDF formatında olduğundan emin olunuz.</div>
                            <div v-if="isFilesLocked" style="margin-top:6px; font-size:11.5px; color:#94a3b8; display:flex; align-items:center; gap:4px;"><i class="ki-outline ki-lock-2" style="font-size:12px;"></i> Sipariş kilitli — dosya değiştirilemez</div>
                        </div>
                        <div>
                            <div style="font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;">Malzeme Cins-Miktar Kabul Formu <span v-if="canSend || tedarikExistingCins?.last_status?.op_key==='doc_file_rejected'" style="color:#ef4444;">*</span></div>
                            <div v-if="isFilesLocked && tedarikExistingCins" style="display:flex; align-items:center; gap:8px; padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; min-width:0; overflow:hidden;">
                                <i class="ki-outline ki-document" style="font-size:16px;color:#7c3aed;"></i>
                                <span style="flex:1; min-width:0; font-size:13px; font-weight:500; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" :title="getTedarikDisplayName(tedarikExistingCins,'Malzeme Cins-Miktar Kabul Formu')">{{ getTedarikDisplayName(tedarikExistingCins,'Malzeme Cins-Miktar Kabul Formu') }}</span>
                                <span v-if="tedarikExistingCins.last_status" style="font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px;" :style="tedarikExistingCins.last_status.op_key==='doc_file_accepted' ? 'background:#dcfce7;color:#166534' : tedarikExistingCins.last_status.op_key==='doc_file_rejected' ? 'background:#fee2e2;color:#991b1b' : 'background:#fef3c7;color:#92400e'">{{ tedarikExistingCins.last_status.op_key==='doc_file_accepted' ? 'Onaylandı' : tedarikExistingCins.last_status.op_key==='doc_file_rejected' ? 'Reddedildi' : 'Beklemede' }}</span>
                                <button type="button" @click="previewTedarikFile(tedarikExistingCins)" style="width:28px; height:28px; border-radius:6px; border:1px solid #e0e7ff; background:#f5f3ff; color:#7c3aed; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="ki-outline ki-eye" style="font-size:14px;"></i></button>
                            </div>
                            <div v-if="isFilesLocked && tedarikExistingCins && tedarikExistingCins.last_status" style="margin-top:8px; display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; background:#fff; border:1px solid #e2e8f0; border-radius:8px;">
                                <div style="font-size:12px; color:#475569; min-width:0; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    <span style="font-weight:700;">{{ tedarikExistingCins.last_status.op_key==='doc_file_accepted' ? 'Onaylayan' : tedarikExistingCins.last_status.op_key==='doc_file_rejected' ? 'Reddeden' : 'İşlem' }}:</span>
                                    {{ tedarikExistingCins.last_status.name || '-' }}
                                </div>
                                <button v-if="hasTedarikNote(tedarikExistingCins)" type="button" @click="showTedarikFileNote(tedarikExistingCins)" style="font-size:11px; font-weight:700; padding:5px 10px; border-radius:6px; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; cursor:pointer; flex-shrink:0;">Notu Gör</button>
                            </div>
                            <div v-else-if="isFilesLocked && !tedarikExistingCins" style="padding:12px; background:#f8fafc; border:1px dashed #e2e8f0; border-radius:8px; font-size:13px; color:#94a3b8; text-align:center;">Henüz yüklenmedi — sipariş kilitli</div>
                            <template v-else>
                                <div v-if="tedarikExistingCins && tedarikExistingCins.last_status?.op_key==='doc_file_rejected'" style="display:flex; flex-direction:column; gap:8px;">
                                    <div style="display:flex; align-items:center; gap:8px; padding:10px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; min-width:0; overflow:hidden;">
                                        <i class="ki-outline ki-document" style="font-size:16px;color:#ef4444;"></i>
                                        <span style="flex:1; min-width:0; font-size:13px; color:#991b1b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" :title="getTedarikDisplayName(tedarikExistingCins,'Malzeme Cins-Miktar Kabul Formu')">{{ getTedarikDisplayName(tedarikExistingCins,'Malzeme Cins-Miktar Kabul Formu') }}</span>
                                        <span style="font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px; background:#fee2e2;color:#991b1b;">Reddedildi</span>
                                        <button type="button" @click="previewTedarikFile(tedarikExistingCins)" style="width:28px; height:28px; border-radius:6px; border:1px solid #e0e7ff; background:#f5f3ff; color:#7c3aed; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="ki-outline ki-eye" style="font-size:14px;"></i></button>
                                    </div>
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; background:#fff7ed; border:1px solid #fed7aa; border-radius:8px;">
                                        <div style="font-size:12px; color:#9a3412; min-width:0; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                            <span style="font-weight:700;">Reddeden:</span> {{ tedarikExistingCins.last_status?.name || '-' }}
                                        </div>
                                        <button v-if="hasTedarikNote(tedarikExistingCins)" type="button" @click="showTedarikFileNote(tedarikExistingCins)" style="font-size:11px; font-weight:700; padding:5px 10px; border-radius:6px; border:1px solid #fed7aa; background:#ffedd5; color:#9a3412; cursor:pointer; flex-shrink:0;">Notu Gör</button>
                                    </div>
                                    <label class="tedarik-file-wrap">
                                        <input type="file" accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx" @change="onTedarikFileSelect($event, 'cins')" style="display:none;">
                                        <span class="tedarik-file-input"><span style="color:#64748b; font-size:13px;">{{ tedarikCinsFile ? tedarikCinsFile.name : 'Yeni dosya seç' }}</span><span class="tedarik-file-btn">Dosya Seç</span></span>
                                    </label>
                                </div>
                                <div v-else-if="tedarikExistingCins" style="display:flex; flex-direction:column; gap:8px;">
                                    <div style="display:flex; align-items:center; gap:8px; padding:10px 12px; background:#f0fdf4; border:1px solid #86efac; border-radius:8px; min-width:0; overflow:hidden;">
                                        <i class="ki-outline ki-document" style="font-size:16px;color:#7c3aed;"></i>
                                        <span style="flex:1; min-width:0; font-size:13px; font-weight:500; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" :title="getTedarikDisplayName(tedarikExistingCins,'Malzeme Cins-Miktar Kabul Formu')">{{ getTedarikDisplayName(tedarikExistingCins,'Malzeme Cins-Miktar Kabul Formu') }}</span>
                                        <span style="font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px;" :style="tedarikExistingCins.last_status?.op_key==='doc_file_accepted' ? 'background:#dcfce7;color:#166534' : 'background:#fef3c7;color:#92400e'">{{ tedarikExistingCins.last_status?.op_key==='doc_file_accepted' ? 'Onaylandı' : 'Beklemede' }}</span>
                                        <button type="button" @click="previewTedarikFile(tedarikExistingCins)" style="width:28px; height:28px; border-radius:6px; border:1px solid #bbf7d0; background:#f5f3ff; color:#7c3aed; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="ki-outline ki-eye" style="font-size:14px;"></i></button>
                                    </div>
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; background:#fff; border:1px solid #e2e8f0; border-radius:8px;">
                                        <div style="font-size:12px; color:#475569; min-width:0; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                            <span style="font-weight:700;">{{ tedarikExistingCins.last_status?.op_key==='doc_file_accepted' ? 'Onaylayan' : 'İşlem' }}:</span>
                                            {{ tedarikExistingCins.last_status?.name || '-' }}
                                        </div>
                                        <button v-if="hasTedarikNote(tedarikExistingCins)" type="button" @click="showTedarikFileNote(tedarikExistingCins)" style="font-size:11px; font-weight:700; padding:5px 10px; border-radius:6px; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; cursor:pointer; flex-shrink:0;">Notu Gör</button>
                                    </div>
                                </div>
                                <label v-else class="tedarik-file-wrap" :style="isFilesLocked ? 'opacity:0.55;pointer-events:none;' : ''">
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx" @change="onTedarikFileSelect($event, 'cins')" :disabled="isFilesLocked" style="display:none;">
                                    <span class="tedarik-file-input"><span style="color:#64748b; font-size:13px;">{{ tedarikCinsFile ? tedarikCinsFile.name : 'Dosya seçilmedi' }}</span><span class="tedarik-file-btn">Dosya Seç</span></span>
                                </label>
                            </template>
                            <div style="margin-top:6px; font-size:11.5px; color:#64748b;">Dosya yüklerken dosyanızın 42MB'dan küçük, JPG, PNG veya PDF formatında olduğundan emin olunuz.</div>
                            <div v-if="isFilesLocked" style="margin-top:6px; font-size:11.5px; color:#94a3b8; display:flex; align-items:center; gap:4px;"><i class="ki-outline ki-lock-2" style="font-size:12px;"></i> Sipariş kilitli — dosya değiştirilemez</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sevk Onay Way-1 (before approved) -->
            <div id="sevk-card" class="tedarik-step-card sevk-card" :class="{ 'sevk-ring': sevkFocus }" v-if="id && loadForm && (canSevkStart || isSevkOrder)" style="border:1px solid #c4b5fd; background:linear-gradient(180deg,#faf5ff 0%,#f5f3ff 100%);">
                <div style="padding:16px 18px;">
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <span style="width:30px; height:30px; border-radius:10px; background:linear-gradient(135deg,#8b5cf6,#7c3aed); color:#fff; display:inline-flex; align-items:center; justify-content:center;"><i class="ki-outline ki-delivery" style="font-size:16px;"></i></span>
                        <span style="font-weight:800; color:#4c1d95; font-size:14px;">Sevk Onay Süreci</span>
                    </div>
                    <div style="font-size:11.5px; color:#6d28d9; margin:6px 0 12px;">Depo → adres → tarih → proforma/irsaliye → kontrol → sevkiyat → stok. Final: Kalite onayı.</div>
                    <div v-if="canSevkStart"><button @click="sevkStart" :disabled="sevkLoading" class="tedarik-orange-btn" style="background:#8b5cf6;border-color:#8b5cf6;">Sevk Onayına Al</button></div>
                    <div v-if="isSevkOrder" class="sevk-steps" style="display:flex; flex-direction:column; gap:10px; margin-top:6px;">
                        <div class="sevk-step">
                            <span class="sevk-num">1</span>
                            <div class="sevk-body">
                                <label>Depo (SAS)</label>
                                <div class="sevk-row">
                                    <input v-if="canSevkKalite" v-model="sevkDepo" :placeholder="sevkEnts.depo_yeri || 'Depo yeri'" class="sevk-input" />
                                    <button v-if="canSevkKalite" @click="sevkKalite" :disabled="sevkLoading" class="tedarik-orange-btn" style="background:#7c3aed;border-color:#7c3aed;">Kalite Onayı Ver</button>
                                    <span v-else class="sevk-val" :class="{ 'sevk-empty': !sevkEnts.depo_yeri }">{{ sevkEnts.depo_yeri || 'Henüz girilmedi' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="sevk-step">
                            <span class="sevk-num">2</span>
                            <div class="sevk-body">
                                <label>Sevk Adresi</label>
                                <div v-if="canSevkAdres" class="sevk-addrs">
                                    <textarea v-model="sevkAdres" :placeholder="sevkEnts.sevk_adresi || 'Sevk adresini yazınız...'" class="sevk-input sevk-area" rows="3"></textarea>
                                    <textarea v-model="sevkAdresDiger" placeholder="Diğer (Köşk vb. manuel giriş)" class="sevk-input sevk-area" rows="3"></textarea>
                                </div>
                                <button v-if="canSevkAdres" @click="sevkAdresKaydet" :disabled="sevkLoading" class="tedarik-orange-btn" style="background:#0ea5e9;border-color:#0ea5e9; margin-top:8px;">Adresi Kaydet</button>
                                <div v-if="!canSevkAdres && (sevkEnts.sevk_adresi || sevkEnts.sevk_adres_diger)" class="sevk-val">{{ sevkEnts.sevk_adresi || '' }}<span v-if="sevkEnts.sevk_adres_diger"> · {{ sevkEnts.sevk_adres_diger }}</span></div>
                                <div v-if="!canSevkAdres && !(sevkEnts.sevk_adresi || sevkEnts.sevk_adres_diger)" class="sevk-val sevk-empty">Henüz adres girilmedi</div>
                            </div>
                        </div>
                        <div class="sevk-step">
                            <span class="sevk-num">3</span>
                            <div class="sevk-body">
                                <label>Sevk Tarihi</label>
                                <div class="sevk-row">
                                    <input v-if="canSevkDate" type="date" v-model="sevkTarihi" class="sevk-input" style="max-width:220px;" />
                                    <button v-if="canSevkDate" @click="sevkTarihKaydet" :disabled="sevkLoading" class="tedarik-orange-btn" style="background:#06b6d4;border-color:#06b6d4;">Tarihi Kaydet</button>
                                    <span v-else class="sevk-val">{{ sevkEnts.sevk_tarihi || '' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="sevk-step">
                            <span class="sevk-num">4</span>
                            <div class="sevk-body">
                                <label>Proforma + İrsaliye</label>
                                <div class="sevk-uploads">
                                    <label v-if="canSevkProforma" class="sevk-upload">
                                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="onSevkFileSelect($event,'proforma')" style="display:none;" />
                                        <span class="sevk-upload-icon"><i class="ki-outline ki-document"></i></span>
                                        <span class="sevk-upload-text"><b>Proforma Fatura</b><small>{{ sevkProformaFile?.name || (sevkExistingProforma ? 'Yüklü ✓ — değiştirmek için seç' : 'PDF / görsel seç') }}</small></span>
                                    </label>
                                    <label v-if="canSevkProforma" class="sevk-upload">
                                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="onSevkFileSelect($event,'irsaliye')" style="display:none;" />
                                        <span class="sevk-upload-icon"><i class="ki-outline ki-document"></i></span>
                                        <span class="sevk-upload-text"><b>İrsaliye</b><small>{{ sevkIrsaliyeFile?.name || (sevkExistingIrsaliye ? 'Yüklü ✓ — değiştirmek için seç' : 'PDF / görsel seç') }}</small></span>
                                    </label>
                                </div>
                                <div v-if="sevkExistingProforma || sevkExistingIrsaliye" class="sevk-files">
                                    <span v-if="sevkExistingProforma" class="sevk-chip"><i class="ki-outline ki-check-circle"></i> {{ getTedarikDisplayName(sevkExistingProforma,'Proforma') }} <button type="button" @click="previewTedarikFile(sevkExistingProforma)" title="Önizle"><i class="ki-outline ki-eye"></i></button></span>
                                    <span v-if="sevkExistingIrsaliye" class="sevk-chip"><i class="ki-outline ki-check-circle"></i> {{ getTedarikDisplayName(sevkExistingIrsaliye,'İrsaliye') }} <button type="button" @click="previewTedarikFile(sevkExistingIrsaliye)" title="Önizle"><i class="ki-outline ki-eye"></i></button></span>
                                </div>
                                <div class="sevk-row" style="margin-top:10px;">
                                    <button v-if="canSevkProforma" @click="sevkProformaKaydet" :disabled="sevkLoading" class="tedarik-orange-btn" style="background:#d97706;border-color:#d97706;">Yükle+Onaya Sun</button>
                                    <span v-else-if="!(sevkExistingProforma || sevkExistingIrsaliye)" class="sevk-val sevk-empty">Henüz dosya yüklenmedi</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="canSevkControl" class="sevk-step">
                            <span class="sevk-num">5</span>
                            <div class="sevk-body">
                                <label>Kontrol</label>
                                <textarea v-model="sevkKontrolNote" placeholder="Not / gerekçe (ret için zorunlu)" class="sevk-input sevk-area" rows="2"></textarea>
                                <div class="sevk-row" style="margin-top:8px;">
                                    <button @click="sevkUygun" :disabled="sevkLoading" class="tedarik-orange-btn" style="background:#059669;border-color:#059669;">Uygun</button>
                                    <button @click="sevkRed" :disabled="sevkLoading" class="tedarik-orange-btn" style="background:#ef4444;border-color:#ef4444;">Uygun Değil</button>
                                </div>
                            </div>
                        </div>
                        <div v-if="canSevkShip"><button @click="sevkShip" :disabled="sevkLoading" class="tedarik-orange-btn" style="background:#059669;border-color:#059669;">Sevkiyat Gerçekleşti</button></div>
                        <div v-if="sevkEnts.sevk_kontrol_ok === '1'" class="sevk-meta sevk-ok">Kontrol: Uygun — sevkiyat yapabilirsiniz</div>
                        <div v-else-if="sevkEnts.proforma_kontrol_note" class="sevk-meta sevk-no">Kontrol: Uygun değil — {{ sevkEnts.proforma_kontrol_note }}</div>
                    </div>
                </div>
            </div>

            <!-- Sevk Onayı — tedarik close (per-07-02) -->
            <div class="tedarik-step-card" v-if="canKalite" style="border:1px solid #bbf7d0; background:#f0fdf4;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; gap:12px; flex-wrap:wrap;">
                    <div style="min-width:0; flex:1;">
                        <div style="font-weight:800; color:#166534; font-size:13px; display:flex; align-items:center; gap:6px;"><i class="ki-outline ki-check-circle" style="font-size:15px;"></i> Sevk Onayı</div>
                        <div style="font-size:11.5px; color:#15803d; margin-top:3px; line-height:1.4;">Siparişi <b>Sevk Onayı Verildi</b> durumuna alınır , tüm bekleyen dosyalar kabul edilir ve sipariş kilitlenir.</div>
                    </div>
                    <button @click="kaliteOnayi" :disabled="kaliteLoading" :style="kaliteLoading ? 'background:#9ca3af; color:#fff; border:none; border-radius:10px; padding:10px 18px; font-weight:700; font-size:13px; opacity:0.72; cursor:not-allowed; display:inline-flex; align-items:center; gap:8px; min-width:170px; justify-content:center;' : 'background:#22c55e; color:#fff; border:none; border-radius:10px; padding:10px 18px; font-weight:700; font-size:13px; cursor:pointer; display:inline-flex; align-items:center; gap:8px; min-width:170px; justify-content:center; box-shadow:0 2px 8px rgba(34,197,94,0.18);'">
                        <i :class="kaliteLoading ? 'ki-outline ki-loading' : 'ki-outline ki-check-circle'" :style="kaliteLoading ? 'font-size:15px; animation:spin 1s linear infinite;' : 'font-size:15px;'"></i>
                        {{ kaliteLoading ? 'Onaylanıyor...' : 'Sevk Onayı Ver' }}
                    </button>
                </div>
            </div>

            <!-- File History — Bütün Formları + entered Malzeme forms history (lists ALL, incl. rejected old versions) — only after first send for review -->
            <div class="tedarik-step-card" v-show="!sevkFocus || showAllDetails" v-if="id && loadForm && orderStatus && orderStatus !== 'doc_trans_order_created'" style="border:1px solid #e2e8f0;">
                <div style="display:flex; gap:10px; flex-wrap:wrap; padding:14px;">
                    <button type="button" @click="downloadAllForms" :disabled="downloadingAll" class="tedarik-orange-btn"><i :class="downloadingAll ? 'ki-outline ki-loading' : 'ki-outline ki-file-down'" :style="downloadingAll ? 'animation:spin 1s linear infinite' : ''"></i> {{ downloadingAll ? 'İndiriliyor…' : 'Bütün Formları İndir' }}</button>
                    <button type="button" @click="showFileHistory('kabul')" class="tedarik-orange-btn"><i class="ki-outline ki-eye"></i> Malzeme Kabul Formu</button>
                    <button type="button" @click="showFileHistory('cins')" class="tedarik-orange-btn"><i class="ki-outline ki-eye"></i> Malzeme Cinsi Kabul Formu</button>
                </div>
                <div style="padding:0 14px 10px; font-size:11.5px; color:#64748b;">Onaylanan, reddedilen ve bekleyen <b>tüm</b> formlar — ZIP ile toplu veya göz ile tek tek, geçmiş dahil.</div>
            </div>

            <!-- Step 6 -->
            <div class="tedarik-step-card" v-if="canSend || orderStatus === 'doc_trans_order_files_rejected'">
                <div class="tedarik-step-head"><span class="tedarik-step-num">6</span><span>Lütfen “Gönder” butonuna tıklamadan önce verilerin doğruluğuna emin olunuz.</span></div>
                <div class="tedarik-step-body">
                    <button @click="() => { if(isSubmitting) return; const fd = ($refs.formRef && $refs.formRef.getCurrentFormData) ? $refs.formRef.getCurrentFormData() : { dynamicF:{}, files:{} }; submitForm(fd); }" class="tedarik-gonder-btn w-100" :disabled="isSubmitting" :class="{ 'is-loading': isSubmitting }" :style="isSubmitting ? 'opacity:0.82; cursor:not-allowed; background:linear-gradient(135deg,#ff7a3a,#FF5A1F); box-shadow:0 4px 14px rgba(255,90,31,.28); position:relative; overflow:hidden;' : ''">
                        <span v-if="!isSubmitting" style="display:inline-flex; align-items:center; gap:6px; justify-content:center;">Gönder</span>
                        <span v-else style="display:inline-flex; align-items:center; gap:8px; justify-content:center;"><i class="ki-outline ki-loading" style="font-size:15px; animation:spin 1s linear infinite;"></i> Gönderiliyor...</span>
                        <span v-if="isSubmitting" style="position:absolute; left:0; bottom:0; height:3px; width:100%; background:rgba(255,255,255,.45); animation:tedarikProgress 1.2s ease-in-out infinite;"></span>
                    </button>
                    <div v-if="isSubmitting" style="margin-top:10px; display:flex; align-items:center; gap:10px; padding:10px 12px; background:linear-gradient(135deg,#fff7ed,#fff); border:1px solid #fed7aa; border-radius:10px; color:#9a3412; font-size:12.5px; line-height:1.4;">
                        <span style="width:28px; height:28px; border-radius:8px; background:#FF5A1F; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i class="ki-outline ki-loading" style="font-size:14px; animation:spin 1s linear infinite;"></i></span>
                        <span><b>Kaydediliyor</b> — dosyalar işleniyor, lütfen bekleyin...</span>
                    </div>
                    <div hidden v-if="orderStatus === 'doc_trans_order_files_rejected'" style="margin-top:10px; display:flex; gap:8px; align-items:center;">
                        <button type="button" @click="printMalzemeKabul" class="tedarik-orange-btn small" :disabled="printingKabul"><i :class="printingKabul ? 'ki-outline ki-loading' : 'ki-outline ki-printer'" :style="printingKabul ? 'animation:spin 1s linear infinite' : ''"></i> {{ printingKabul ? 'Oluşturuluyor...' : 'Malzeme Kabul (yeniden yazdır)' }}</button>
                        <button type="button" @click="printMalzemeCinsMiktar" class="tedarik-orange-btn small" :disabled="printingCins"><i :class="printingCins ? 'ki-outline ki-loading' : 'ki-outline ki-printer'" :style="printingCins ? 'animation:spin 1s linear infinite' : ''"></i> {{ printingCins ? 'Oluşturuluyor...' : 'Cins-Miktar (yeniden yazdır)' }}</button>
                    </div>
                </div>
            </div>

            <!-- hidden Form for EAV scaffolding (own Gönder button above — suppress generic bar) -->
            <div style="display:none;">
                <Form ref="formRef" formtypes="op-doc-order-form" v-if="loadForm" :showsave="false" savebtntitle="Kaydet" :readonlyFields="readonlyFields" :savecallback="submitForm" />
            </div>
        </div>
        <div v-else-if="id && !loadForm" style="padding:40px; text-align:center; color:#94a3b8;">Yükleniyor...</div>
    </div>
    <!-- ADMIN PANEL (unchanged) -->
    <div v-else style="padding-bottom: 100px;">
        <div v-if="id && loadForm && (canSevkStart || isSevkOrder)" style="margin:0 14px 12px; display:flex; align-items:center; gap:10px; padding:10px 14px; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:12px; font-size:12.5px; color:#5b21b6;">
            <i class="ki-outline ki-delivery" style="font-size:16px;"></i>
            <span><b>Sevk odağı</b></span>
            <button @click="toggleAllDetails" type="button" style="margin-left:auto; font-size:11.5px; font-weight:700; padding:6px 12px; border-radius:8px; border:1px solid #c4b5fd; background:#fff; color:#5b21b6; cursor:pointer;">{{ showAllDetails ? 'Özeti göster' : 'Tüm detayı göster' }}</button>
        </div>
        <div class="card mb-6" v-show="!sevkFocus || showAllDetails" v-if="id && loadForm" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.04),0 1px 2px rgba(0,0,0,0.02);">
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
                <div style="margin-top:14px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;">
                    
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
            <!-- Bütün Formları İndir + entered Malzeme history — admin (lists ALL, incl. rejected old versions) — hidden when no files (base 84428217 now 0 after leak fix) -->
            <div v-if="hasAnyFile" style="margin:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;justify-content: center;">
                <button type="button" @click="downloadAllForms" :disabled="downloadingAll" class="tedarik-orange-btn" style="background:#3b82f6;border-color:#3b82f6; color:#fff;"><i :class="downloadingAll ? 'ki-outline ki-loading' : 'ki-outline ki-file-down'" :style="downloadingAll ? 'animation:spin 1s linear infinite' : ''"></i> {{ downloadingAll ? 'İndiriliyor…' : 'Bütün Formları İndir' }}</button>
                <button type="button" @click="showFileHistory('kabul')" class="tedarik-orange-btn" style="background:#FF5A1F;border-color:#FF5A1F;"><i class="ki-outline ki-eye"></i> Malzeme Kabul Formu</button>
                <button type="button" @click="showFileHistory('cins')" class="tedarik-orange-btn" style="background:#FF5A1F;border-color:#FF5A1F;"><i class="ki-outline ki-eye"></i> Malzeme Cinsi Kabul Formu</button>
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

        <div id="sevk-card" class="card mb-6 sevk-card" :class="{ 'sevk-ring': sevkFocus }" v-if="id && loadForm && (canSevkStart || isSevkOrder)" style="border:1px solid #c4b5fd;border-radius:14px;overflow:hidden;background:#f5f3ff;">
            <div style="padding:18px 20px;">
                <div style="font-weight:800; color:#5b21b6; font-size:14px;">Sevk Onay Süreci</div>
                <div style="font-size:12px; color:#6d28d9; margin:4px 0 12px;">Depo → adres → tarih → proforma/irsaliye → kontrol → sevkiyat → stok. Final Kalite.</div>
                <div v-if="canSevkStart"><button @click="sevkStart" :disabled="sevkLoading" class="btn btn-sm" style="background:#8b5cf6;color:#fff;font-weight:700;">Sevk Onayına Al</button></div>
                <div v-if="isSevkOrder" class="sevk-steps" style="display:flex; flex-direction:column; gap:10px; margin-top:8px;">
                    <div class="sevk-step">
                        <span class="sevk-num">1</span>
                        <div class="sevk-body">
                            <label>Depo (SAS)</label>
                            <div class="sevk-row">
                                <input v-if="canSevkKalite" v-model="sevkDepo" :placeholder="sevkEnts.depo_yeri || 'Depo'" class="form-control form-control-sm sevk-input" style="max-width:280px;" />
                                <button v-if="canSevkKalite" @click="sevkKalite" class="btn btn-sm" style="background:#7c3aed;color:#fff;">Kalite Onayı Ver</button>
                                <span v-else style="font-size:12px;" :class="{ 'sevk-empty': !sevkEnts.depo_yeri }">{{ sevkEnts.depo_yeri || 'Henüz girilmedi' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="sevk-step">
                        <span class="sevk-num">2</span>
                        <div class="sevk-body">
                            <label>Sevk Adresi</label>
                            <div v-if="canSevkAdres" class="sevk-addrs">
                                <textarea v-model="sevkAdres" :placeholder="sevkEnts.sevk_adresi || 'Sevk adresini yazınız...'" class="form-control sevk-input sevk-area" rows="3"></textarea>
                                <textarea v-model="sevkAdresDiger" placeholder="Diğer (Köşk vb. manuel giriş)" class="form-control sevk-input sevk-area" rows="3"></textarea>
                            </div>
                            <button v-if="canSevkAdres" @click="sevkAdresKaydet" class="btn btn-sm" style="background:#0ea5e9;color:#fff; margin-top:8px;">Kaydet</button>
                            <div v-if="!canSevkAdres && (sevkEnts.sevk_adresi || sevkEnts.sevk_adres_diger)" style="font-size:12px;">{{ sevkEnts.sevk_adresi || '' }}<span v-if="sevkEnts.sevk_adres_diger"> · {{ sevkEnts.sevk_adres_diger }}</span></div>
                            <div v-if="!canSevkAdres && !(sevkEnts.sevk_adresi || sevkEnts.sevk_adres_diger)" style="font-size:12px;" class="sevk-empty">Henüz adres girilmedi</div>
                        </div>
                    </div>
                    <div class="sevk-step">
                        <span class="sevk-num">3</span>
                        <div class="sevk-body">
                            <label>Sevk Tarihi</label>
                            <div class="sevk-row">
                                <input v-if="canSevkDate" type="date" v-model="sevkTarihi" class="form-control form-control-sm sevk-input" style="max-width:200px;" />
                                <button v-if="canSevkDate" @click="sevkTarihKaydet" class="btn btn-sm" style="background:#06b6d4;color:#fff;">Kaydet</button>
                                <span v-else style="font-size:12px;">{{ sevkEnts.sevk_tarihi || '' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="sevk-step">
                        <span class="sevk-num">4</span>
                        <div class="sevk-body">
                            <label>Proforma + İrsaliye</label>
                            <div class="sevk-uploads">
                                <label v-if="canSevkProforma" class="sevk-upload">
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="onSevkFileSelect($event,'proforma')" style="display:none;" />
                                    <span class="sevk-upload-icon"><i class="ki-outline ki-document"></i></span>
                                    <span class="sevk-upload-text"><b>Proforma Fatura</b><small>{{ sevkProformaFile?.name || (sevkExistingProforma ? 'Yüklü ✓ — değiştirmek için seç' : 'PDF / görsel seç') }}</small></span>
                                </label>
                                <label v-if="canSevkProforma" class="sevk-upload">
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="onSevkFileSelect($event,'irsaliye')" style="display:none;" />
                                    <span class="sevk-upload-icon"><i class="ki-outline ki-document"></i></span>
                                    <span class="sevk-upload-text"><b>İrsaliye</b><small>{{ sevkIrsaliyeFile?.name || (sevkExistingIrsaliye ? 'Yüklü ✓ — değiştirmek için seç' : 'PDF / görsel seç') }}</small></span>
                                </label>
                            </div>
                            <div v-if="sevkExistingProforma || sevkExistingIrsaliye" class="sevk-files">
                                <span v-if="sevkExistingProforma" class="sevk-chip"><i class="ki-outline ki-check-circle"></i> {{ getTedarikDisplayName(sevkExistingProforma,'Proforma') }} <button type="button" @click="previewTedarikFile(sevkExistingProforma)" title="Önizle"><i class="ki-outline ki-eye"></i></button></span>
                                <span v-if="sevkExistingIrsaliye" class="sevk-chip"><i class="ki-outline ki-check-circle"></i> {{ getTedarikDisplayName(sevkExistingIrsaliye,'İrsaliye') }} <button type="button" @click="previewTedarikFile(sevkExistingIrsaliye)" title="Önizle"><i class="ki-outline ki-eye"></i></button></span>
                            </div>
                            <div class="sevk-row" style="margin-top:10px;">
                                <button v-if="canSevkProforma" @click="sevkProformaKaydet" :disabled="sevkLoading" class="btn btn-sm" style="background:#d97706;color:#fff;">Yükle+Onaya Sun</button>
                                <span v-else-if="!(sevkExistingProforma || sevkExistingIrsaliye)" class="sevk-val sevk-empty">Henüz dosya yüklenmedi</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="canSevkControl" class="sevk-step">
                        <span class="sevk-num">5</span>
                        <div class="sevk-body">
                            <label>Kontrol</label>
                            <textarea v-model="sevkKontrolNote" placeholder="Not / gerekçe (ret için zorunlu)" class="form-control sevk-input sevk-area" rows="2"></textarea>
                            <div class="sevk-row" style="margin-top:8px;"><button @click="sevkUygun" class="btn btn-sm" style="background:#059669;color:#fff;">Uygun</button><button @click="sevkRed" class="btn btn-sm" style="background:#ef4444;color:#fff;">Değil</button></div>
                        </div>
                    </div>
                    <div v-if="canSevkShip"><button @click="sevkShip" class="btn btn-sm" style="background:#059669;color:#fff;">Sevkiyat Gerçekleşti</button></div>
                    <div v-if="sevkEnts.sevk_kontrol_ok === '1'" class="sevk-meta sevk-ok">Kontrol: Uygun — sevkiyat yapabilirsiniz</div>
                    <div v-else-if="sevkEnts.proforma_kontrol_note" class="sevk-meta sevk-no">Kontrol: Uygun değil — {{ sevkEnts.proforma_kontrol_note }}</div>
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

        <!-- Sevk Onayı — admin detail (per-07-02) -->
        <div class="card mb-6" v-if="id && loadForm && canKalite" style="border:1px solid #bbf7d0; border-radius:14px; overflow:hidden; background:#f0fdf4; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; gap:12px; flex-wrap:wrap;">
                <div style="display:flex; align-items:center; gap:12px; min-width:0; flex:1;">
                    <div style="width:42px; height:42px; border-radius:10px; background:#22c55e; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; flex-shrink:0; box-shadow:0 2px 6px rgba(34,197,94,0.2);"><i class="ki-outline ki-check-circle"></i></div>
                    <div style="min-width:0;">
                        <div style="font-weight:800; color:#166534; font-size:13px;">Sevk Onayı</div>
                        <div style="font-size:11.5px; color:#15803d; margin-top:2px; line-height:1.4;">Tüm bekleyen dosyalar kabul edilecek, sipariş <b>Sevk Onayı Verildi</b> durumuna geçecek.</div>
                    </div>
                </div>
                <button @click="kaliteOnayi" :disabled="kaliteLoading" :style="kaliteLoading ? 'background:#9ca3af; color:#fff; border:none; border-radius:10px; padding:10px 18px; font-weight:700; font-size:13px; opacity:0.72; cursor:not-allowed; display:inline-flex; align-items:center; gap:8px; min-width:170px; justify-content:center;' : 'background:#22c55e; color:#fff; border:none; border-radius:10px; padding:10px 18px; font-weight:700; font-size:13px; cursor:pointer; display:inline-flex; align-items:center; gap:8px; min-width:170px; justify-content:center; box-shadow:0 2px 8px rgba(34,197,94,0.18);'">
                    <i :class="kaliteLoading ? 'ki-outline ki-loading' : 'ki-outline ki-check-circle'" :style="kaliteLoading ? 'font-size:15px; animation:spin 1s linear infinite;' : 'font-size:15px;'"></i>
                    {{ kaliteLoading ? 'Onaylanıyor...' : 'Sevk Onayı Ver' }}
                </button>
            </div>
        </div>

        <Form ref="formRef" formtypes="op-doc-order-form" v-if="loadForm" :showsave="!sevkFocus" savebtntitle="Kaydet" :readonlyFields="readonlyFields" :savecallback="submitForm" />
        
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
/* ===== TEDARIK DETAIL (screenshot 1:1) ===== */
.tedarik-detail { display:flex; flex-direction:column; gap:14px; background:#ffffff; border-radius:12px; }
.tedarik-detail-meta { display:flex; flex-direction:column; gap:3px; align-items:flex-end; text-align:right; }
.tedarik-warning { background:#fff8db; border:1px solid #fde68a; border-radius:12px; padding:14px 16px; display:flex; gap:12px; align-items:flex-start; color:#92400e; font-size:12.8px; line-height:1.6; }
.tedarik-warning i { font-size:18px; color:#f59e0b; flex-shrink:0; margin-top:2px; }
.tedarik-step-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
.sevk-ring { box-shadow:0 0 0 3px #a78bfa, 0 8px 24px rgba(139,92,246,.25) !important; }
#sevk-card { scroll-margin-top:12px; }
.sevk-steps { display:flex; flex-direction:column; gap:10px; }
.sevk-step { display:flex; gap:12px; background:#fff; border:1px solid #e9e5fb; border-radius:12px; padding:12px 14px; box-shadow:0 1px 2px rgba(76,29,149,.06); }
.sevk-num { width:26px; height:26px; border-radius:999px; background:linear-gradient(135deg,#8b5cf6,#7c3aed); color:#fff; font-size:12.5px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px; }
.sevk-body { flex:1; min-width:0; }
.sevk-body > label { display:block; font-size:11px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:#5b21b6; margin-bottom:6px; }
.sevk-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.sevk-input { border:1px solid #c4b5fd; border-radius:10px; padding:8px 12px; font-size:13px; color:#1e293b; background:#fff; outline:none; transition:border-color .15s, box-shadow .15s; }
.sevk-input:focus { border-color:#8b5cf6; box-shadow:0 0 0 3px rgba(139,92,246,.18); }
.sevk-input:disabled { background:#f8fafc; color:#64748b; }
.sevk-area { width:100%; resize:vertical; min-height:64px; line-height:1.5; }
.sevk-addrs { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media (max-width:640px){ .sevk-addrs { grid-template-columns:1fr; } }
.sevk-file { cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.sevk-uploads { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media (max-width:640px){ .sevk-uploads { grid-template-columns:1fr; } }
.sevk-upload { display:flex; align-items:center; gap:10px; background:#fff; border:1.5px dashed #c4b5fd; border-radius:12px; padding:10px 14px; cursor:pointer; transition:border-color .15s, box-shadow .15s; min-width:0; }
.sevk-upload:hover { border-color:#8b5cf6; box-shadow:0 0 0 3px rgba(139,92,246,.12); }
.sevk-upload-icon { width:34px; height:34px; border-radius:10px; background:#ede9fe; color:#7c3aed; display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; }
.sevk-upload-text { display:flex; flex-direction:column; min-width:0; }
.sevk-upload-text b { font-size:12.5px; color:#4c1d95; }
.sevk-upload-text small { font-size:11px; color:#6d28d9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:230px; }
.sevk-files { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
.sevk-chip { display:inline-flex; align-items:center; gap:6px; font-size:11.5px; font-weight:600; color:#065f46; background:#ecfdf5; border:1px solid #6ee7b7; border-radius:999px; padding:5px 8px 5px 10px; max-width:100%; }
.sevk-chip button { border:none; background:#fff; border-radius:999px; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; color:#059669; cursor:pointer; font-size:12px; }
.sevk-val { font-size:12.5px; color:#334155; }
.sevk-empty { color:#94a3b8 !important; font-style:italic; }
.sevk-meta { font-size:11px; color:#6d28d9; background:#ede9fe; border:1px dashed #c4b5fd; border-radius:8px; padding:6px 10px; }
.sevk-ok { color:#065f46; background:#ecfdf5; border-color:#6ee7b7; }
.sevk-no { color:#991b1b; background:#fef2f2; border-color:#fca5a5; }
.tedarik-step-head { display:flex; align-items:center; gap:10px; padding:14px 18px; border-bottom:1px solid #f1f5f9; font-size:13.5px; font-weight:700; color:#0f172a; background:#fcfcfd; }
.tedarik-step-num { width:26px; height:26px; border-radius:999px; background:#FF5A1F; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:13px; font-weight:800; flex-shrink:0; }
.tedarik-step-body { padding:16px 18px; }
.tedarik-radio-card { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border:1.5px solid #e2e8f0; border-radius:10px; background:#fff; cursor:pointer; font-size:13.5px; font-weight:600; color:#334155; transition:all .15s; }
.tedarik-radio-card.active { border-color:#FF5A1F; background:#fff7ed; color:#9a3412; }
.tedarik-radio-card.disabled { opacity:0.55; cursor:not-allowed; background:#f1f5f9; }
.tedarik-orange-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 18px; background:#FF5A1F; color:#fff; border:none; border-radius:10px; font-weight:700; font-size:13.5px; cursor:pointer; box-shadow:0 2px 8px rgba(255,90,31,0.22); }
.tedarik-orange-btn.small { padding:8px 14px; font-size:13px; }
.tedarik-orange-btn:hover { background:#e0541b; }
.tedarik-orange-btn:disabled { opacity:0.65; cursor:not-allowed; }
.tedarik-file-wrap { display:block; }
.tedarik-file-input { display:flex; align-items:center; justify-content:space-between; gap:12px; border:1px solid #e2e8f0; border-radius:10px; padding:0 0 0 14px; height:44px; background:#f8fafc; cursor:pointer; }
.tedarik-file-btn { height:32px; padding:0 14px; background:#e2e8f0; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:12.5px; font-weight:600; color:#475569; margin-right:6px; }
.tedarik-gonder-btn { background:#FF5A1F; color:#fff; border:none; border-radius:10px; padding:12px 28px; font-weight:800; font-size:14px; cursor:pointer; box-shadow:0 2px 8px rgba(255,90,31,0.22); }
.tedarik-gonder-btn:hover { background:#e0541b; }
.tedarik-cancel-btn { display:inline-flex; align-items:center; gap:6px; padding:10px 16px; background:#fff; color:#dc2626; border:1px solid #fecaca; border-radius:10px; font-weight:600; font-size:13px; cursor:pointer; }
@media (max-width: 720px){ .tedarik-step-body > div[style*="grid-template-columns:1fr 1fr"]{ grid-template-columns:1fr !important; } .tedarik-detail-meta{ text-align:left; } }

/* Beautified header — overrides */
.tedarik-detail-header { background: linear-gradient(135deg, #ffffff 0%, #fcfdff 100%) !important; border:1px solid #e2e8f0 !important; border-radius:16px !important; padding:18px 22px 16px !important; box-shadow:0 8px 24px rgba(15,23,42,0.04), 0 2px 8px rgba(15,23,42,0.03) !important; position:relative; overflow:hidden; }
.tedarik-detail-header::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background: linear-gradient(90deg, #FF5A1F 0%, #fb923c 50%, #FF5A1F 100%); }
.tedarik-detail-header-top { display:flex; justify-content:space-between; align-items:flex-end; gap:20px; flex-wrap:wrap; padding-bottom:12px; border-bottom:1px solid #f1f5f9; margin-bottom:10px; }
.tedarik-back-link { display:inline-flex; align-items:center; gap:7px; background:linear-gradient(135deg, #fff7ed 0%, #fff 100%); border:1.5px solid #fed7aa; color:#ea580c; font-size:12px; font-weight:700; cursor:pointer; padding:6px 14px 6px 10px; border-radius:999px; line-height:1; transition:all 0.2s ease; margin-bottom:8px; box-shadow:0 1px 4px rgba(234,88,12,0.08); letter-spacing:0.02em; }
.tedarik-back-link i { font-size:14px; transition:transform 0.2s ease; }
.tedarik-back-link:hover { background:linear-gradient(135deg, #ffedd5 0%, #fff7ed 100%); border-color:#fb923c; color:#c2410c; box-shadow:0 2px 8px rgba(234,88,12,0.15); transform:translateX(-2px); }
.tedarik-back-link:hover i { transform:translateX(-2px); }
.tedarik-company-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.tedarik-company-icon { width:36px; height:36px; border-radius:10px; background: linear-gradient(135deg, #FF5A1F 0%, #fb923c 100%); display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:16px; flex-shrink:0; box-shadow:0 4px 12px rgba(255,90,31,0.22); }
.tedarik-detail-title { font-size:15.5px; font-weight:800; color:#0f172a; line-height:1.3; letter-spacing:-0.01em; }
.tedarik-tdno-badge { display:inline-flex; align-items:center; padding:4px 8px; border-radius:6px; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; font-size:11px; font-weight:700; letter-spacing:0.02em; white-space:nowrap; }
.tedarik-detail-meta { display:flex; flex-direction:column; gap:3px; align-items:flex-end; text-align:right; }
.tedarik-meta-item { display:flex; align-items:center; gap:8px; white-space:nowrap; }
.tedarik-meta-item span { font-size:11.5px; color:#94a3b8; font-weight:500; white-space:nowrap; }
.tedarik-meta-item b { font-size:13px; color:#0f172a; font-weight:700; white-space:nowrap; }
.tedarik-status-drum { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; border-radius:999px; font-size:12px; font-weight:700; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.tedarik-status-dot { width:7px; height:7px; border-radius:999px; background:currentColor; opacity:0.9; display:inline-block; }
.tedarik-status-drum.tedarik-status--waiting { background:#FF5A1F; color:#fff; border-color:#FF5A1F; box-shadow:0 2px 8px rgba(255,90,31,0.22); }
.tedarik-status-drum.tedarik-status--ready { background:#fef3c7; color:#92400e; border-color:#fde68a; }
.tedarik-status-drum.tedarik-status--approved { background:#dcfce7; color:#166534; border-color:#86efac; }
.tedarik-status-drum.tedarik-status--rejected { background:#fee2e2; color:#991b1b; border-color:#fecaca; }
@keyframes tedarikProgress{ 0%{ transform:translateX(-100%);} 100%{ transform:translateX(100%);} }
.tedarik-gonder-btn.is-loading{ position:relative; overflow:hidden; }
</style>
