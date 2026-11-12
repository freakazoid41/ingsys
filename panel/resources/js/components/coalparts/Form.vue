<script>
    import Plib from '@/lib/pickle';
    import AppFab from '@/components/coalparts/AppFab.vue';
    import flatpickr from 'flatpickr';
    import { normalizeFormValue } from '@/lib/formSerialize';
    import { buildFormDefinitions } from '@/forms/formDefinitions';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect/index.js';
    import { Turkish } from 'flatpickr/dist/l10n/tr.js';
    import TreeModal from '@/lib/treeModal.js';
    import { generatePassword } from '@/lib/password';
    import VMasker  from 'vanilla-masker';
    import { wTrans } from 'laravel-vue-i18n';
     import { useRoute } from 'vue-router';
    import { useFormDataStore } from '@/stores/formdata';
    import { usePermissionDataStore } from '@/stores/permissiondata';
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import Swal from 'sweetalert2';
    import dayjs from 'dayjs';

    // Memoise async option loaders. A repeatable `multiple` group with N rows used to
    // call its `setOptions` N times, serially awaited — the category form's kVA tag
    // fields fired 14 identical table requests before the form painted. Keyed by the
    // loader function itself, so a fresh component mount (new closures) refetches.
    const __setOptionsMemo = new WeakMap();
    const loadSetOptions = (fn) => {
        let p = __setOptionsMemo.get(fn);
        if (!p) {
            p = Promise.resolve().then(() => fn());
            __setOptionsMemo.set(fn, p);
            p.catch(() => __setOptionsMemo.delete(fn));
        }
        return p;
    };


    export default {
        props: {
            formtypes : {
                type: String
            },
            fabtype : {
                type: String
            },
            savecallback: {
                type: Function
            },
            savebtntitle:{
                type: String
            },
            rejectcallback: {
                type: Function
            },
            acceptcallback: {
                type: Function
            },
            readonlyFields: {
                type: Array,
                default: () => []
            },
            showsave: {
                type: Boolean,
                default: true
            }
        },
        components: {
            AppFab,
            
        },
        setup() {
            // localize flatpickr to Turkish
            flatpickr.localize(Turkish);
            // expose to template and other options API hooks
            return {
                
                usePermissionDataStore,
                useFormDataStore,
                flatpickr,
                AppFab,
                Plib,
                VMasker,
                wTrans,
                useAuthStore,
                Swal,
                PickleTable,
                dayjs
            }
        },
        data() {
            const usersClientClick = (e,element) => {
                Swal.fire({
                    title: 'Cari / Müdürlük Seçiniz',
                    showCloseButton: true,
                    showConfirmButton: false,
                    width: '920px',
                    padding: '1.2rem 1.1rem 1rem',
                    customClass: { popup: 'swal-client-picker', title: 'swal-client-title', htmlContainer: 'swal-client-html' },
                    html: ` <style>
                                .swal-client-picker{ border-radius:16px !important; }
                                .swal-client-title{ font-size:17px !important; font-weight:800 !important; color:#0f172a !important; padding-bottom:10px !important; }
                                .swal-client-html{ margin:0 !important; }
                                #client-tree .pickletable{ border:1px solid #e2e8f0 !important; border-radius:12px !important; overflow:hidden !important; background:#fff !important; }
                                #client-tree .pickletable .divTable{ overflow-x:auto !important; }
                                #client-tree .pickletable table{ width:100% !important; min-width:720px !important; table-layout:fixed !important; }
                                #client-tree .pickletable th{ background:#f8fafc !important; color:#475569 !important; font-size:12px !important; font-weight:700 !important; letter-spacing:.04em !important; text-transform:uppercase !important; white-space:nowrap !important; overflow:visible !important; text-overflow:clip !important; padding:10px 12px !important; border-bottom:1px solid #e2e8f0 !important; }
                                #client-tree .pickletable th input{ width:100% !important; margin-top:6px !important; border-radius:8px !important; }
                                #client-tree .pickletable td{ font-size:13px !important; color:#0f172a !important; padding:11px 12px !important; white-space:nowrap !important; overflow:hidden !important; text-overflow:ellipsis !important; border-bottom:1px solid #f1f5f9 !important; }
                                #client-tree .pickletable td:first-child{ font-weight:600 !important; }
                                #client-tree .pickletable th:nth-child(1), #client-tree .pickletable td:nth-child(1){ width:34% !important; }
                                #client-tree .pickletable th:nth-child(2), #client-tree .pickletable td:nth-child(2){ width:22% !important; }
                                #client-tree .pickletable th:nth-child(3), #client-tree .pickletable td:nth-child(3){ width:22% !important; }
                                #client-tree .pickletable th:nth-child(4), #client-tree .pickletable td:nth-child(4){ width:14% !important; text-align:center !important; }
                                /* make the full row blue when any cell is hovered */
                                #client-tree .pickletable tbody tr:hover td{ background-color:#154b91 !important; color:white !important; }
                                #client-tree .pickletable tbody tr:hover{ border-radius:10px !important; }
                                #client-tree .pickletable tbody tr:hover td:first-child{ border-top-left-radius:10px !important; border-bottom-left-radius:10px !important; }
                                #client-tree .pickletable tbody tr:hover td:last-child{ border-top-right-radius:10px !important; border-bottom-right-radius:10px !important; }
                                #client-tree .pickletable .divPagination{ padding:12px 14px !important; border-top:1px solid #f1f5f9 !important; background:#fff !important; }
                                #client-tree .pickletable .divPagination button{ min-width:36px !important; height:36px !important; border-radius:8px !important; }
                            </style>
                            <div id="client-tree" class="text-start"></div>`,
                    willOpen : () => {
                        this.buildClientTable('#client-tree',(data) => {
                            //check if same client is already added
                            const existingClients = document.querySelectorAll('input[name^="cliid"]');
                            let isDuplicate = false;
                            
                            existingClients.forEach((input) => {
                                if(input.value === data.id.toString() && input.value !== '') {
                                    isDuplicate = true;
                                }
                            });
                            
                            if(isDuplicate) {
                                this.plib.toast(this.Swal, 'warning', 'Bu cari zaten eklenmiştir!');
                                return false;
                            }

                            //element.value = data.clicode;
                            const codeElm = element.parentNode.parentNode.parentNode.querySelector('input[name^="clicode"]');
                            const titleElm = element.parentNode.parentNode.parentNode.querySelector('input[name^="clititle"]');
                            const idElm   = element.parentNode.parentNode.parentNode.querySelector('input[name^="cliid"]');
                            const sysElm  = element.parentNode.parentNode.parentNode.querySelector('input[name^="client_system"]');
                            titleElm.value = data.title;
                            idElm.value    = data.id;
                            codeElm.value  = data.clicode; 
                            if(sysElm) sysElm.value = (data.client_system || data.sys_code || 'GDZ').toString().toUpperCase();
                           
                            codeElm.dispatchEvent(new Event('input'));
                            idElm.dispatchEvent(new Event('input'));
                            titleElm.dispatchEvent(new Event('input'));
                            if(sysElm) sysElm.dispatchEvent(new Event('input'));
                            Swal.close();
                        });
                        
                    }
                });
            };
          
            return {
                permList        : this.usePermissionDataStore().list,
                authStore       : useAuthStore(),
                formDataStore   : useFormDataStore(),
                route           : useRoute(),
                plib            : new Plib(),
                isLoadingRoles  : true,
                ftypes          : this.formtypes.split(','),
                forms           : null,
                // Registry of every built field so readonlyFields changes can
                // re-stamp live (build-time stamping alone raced TForm linkBusy).
                builtFields     : [],
                formData        : {
                    dynamicF : {},
                    files : {},
                    removedData : []
                },
                flatpickrInstances : []
            }
        },
        watch: {
            // readonlyFields is stamped at build time; parents (TForm canSave /
            // linkBusy, OForm isFilesLocked, ...) change it asynchronously after
            // the form is built. Re-stamp every registered field so locks release.
            readonlyFields() {
                this.applyReadonlyState();
            }
        },
        async mounted() {
            // Field dictionaries live in an external module (+ JSON for pure
            // data forms); they need the component instance for their hooks.
            this.forms = buildFormDefinitions(this);
            this.isLoadingRoles = true;
            await this.usePermissionDataStore().fetchRoleTemplates();
            this.isLoadingRoles = false;

            // Ensure template is re-rendered so .area-target nodes are present before we attach rows
            await this.$nextTick();
            //here wait all forms are building
            await Promise.all(this.ftypes.map(async key => {
                const formData = this.formDataStore.formData?.[key] ?? undefined;
                await this.buildDynamicFForm(
                    key,
                    formData !== undefined ? Object.keys(formData)[0] : 'new-'+(new Date).getTime(),
                    formData !== undefined ? Object.values(formData)[0] : {}
                );
            }));
            //here empty form data to prevent submit with old data because buildDynamicFForm set formData with default values
            this.formDataStore.setData({},{});
        },
        methods: {
            rememberField(el, name, opts = {}) {
                if (!el || !name) return;
                if (this.builtFields.some(f => f.el === el)) return;
                this.builtFields.push({
                    el,
                    name,
                    baseReadOnly : !!opts.baseReadOnly,
                    isFile       : !!opts.isFile,
                });
            },
            applyReadonlyState() {
                const rf = Array.isArray(this.readonlyFields) ? this.readonlyFields : [];
                const isRo = (name) => rf.includes(name) || rf.some(x => name === x || name.startsWith(x + '**'));
                for (const f of this.builtFields) {
                    if (!f.el) continue;
                    const ro = isRo(f.name);
                    f.el.disabled  = ro;
                    f.el.readOnly  = f.baseReadOnly || ro;
                    if (f.isFile) {
                        f.el.style.pointerEvents = ro ? 'none' : '';
                    }
                    f.el.style.opacity = ro ? '0.6' : '';
                    f.el.style.cursor  = ro ? 'not-allowed' : '';
                }
            },
            getCurrentFormData(){
                return this.formData || {};
            },
            tcKimlikDogrula(tc) {
                tc = tc.toString().trim();

                // 1. Uzunluk ve sadece rakam kontrolü
                if (tc.length !== 11 || !/^\d{11}$/.test(tc)) {
                    return false;
                }

                // 2. İlk rakam 0 olamaz
                if (tc[0] === '0') {
                    return false;
                }

                let digits = tc.split('').map(Number);

                // 3. 10. hane kontrolü
                let tekToplam = digits[0] + digits[2] + digits[4] + digits[6] + digits[8];
                let ciftToplam = digits[1] + digits[3] + digits[5] + digits[7];
                let onuncuHane = (tekToplam * 7 - ciftToplam) % 10;

                if (onuncuHane !== digits[9]) {
                    return false;
                }

                // 4. 11. hane kontrolü
                let ilkOnToplam = digits.slice(0, 10).reduce((a, b) => a + b, 0);
                let onbirinciHane = ilkOnToplam % 10;

                return onbirinciHane === digits[10];
            },
            /**
             * 10 Haneli Vergi Kimlik Numarası (VKN) doğrulama
             * @param {string} vkn - Doğrulanacak 10 haneli vergi kimlik numarası
             * @returns {boolean} Geçerli ise true, değilse false
             */
            vergiKimlikDogrula(vkn) {
                if (vkn.length !== 10 && vkn.length !== 11) {
                    return false;
                }else{
                    if(vkn.length === 11){
                        return this.tcKimlikDogrula(vkn);
                    }
                }
                return true;

                /*const digits = vkn.split('').map(Number);
                const controlDigit = digits[9];           // Son basamak (kontrol basamağı)
                
                let toplam = 0;

                for (let i = 0; i < 9; i++) {
                    let x = (digits[i] + (9 - i)) % 10;   // (rakam + ağırlık) mod 10
                    let weight = Math.pow(2, 9 - i);      // 512, 256, 128, 64, 32, 16, 8, 4, 2
                    
                    let val = (x * weight) % 9;
                    
                    // Eğer x != 0 ve sonuç 0 ise, 9 kabul edilir
                    if (x !== 0 && val === 0) {
                        val = 9;
                    }
                    
                    toplam += val;
                }

                // Hesaplanan kontrol basamağı
                const hesaplananKontrol = (10 - (toplam % 10)) % 10;

                return hesaplananKontrol === controlDigit;*/
            },
            async formCallback() {
                if(!this.savecallback) return false;

                // wait for any in-flight temp uploads before building the save payload
                const pending = Object.values(this.formData.files || {}).filter(f => f && f.uploading && f.promise);
                if(pending.length){
                    await Promise.all(pending.map(f => f.promise));
                }

                // retry failed temp uploads once before giving up
                const failed = Object.values(this.formData.files || {}).some(f => f && f.error);
                if(failed){
                    await this.retryFailedUploads();
                }

                if(Object.values(this.formData.files || {}).some(f => f && f.error)){
                    this.plib.toast(this.Swal, 'error', 'Bazı dosyalar yüklenemedi. Lütfen dosyaları yeniden seçin.');
                    return false;
                }

                return this.savecallback(this.formData);
            },
            async retryFailedUploads() {
                const failedKeys = Object.keys(this.formData.files || {}).filter(key => {
                    const item = this.formData.files[key];
                    return item && item.error && item.file;
                });

                await Promise.all(failedKeys.map(async key => {
                    const item = this.formData.files[key];
                    try {
                        const ref = await this.uploadTempFile(item.file);
                        this.formData.files[key] = { uploading: false, reference: ref };
                    } catch (err) {
                        console.error('Retry failed for', key, err);
                    }
                }));
            },
            submitDynamicChanges(el,isDatalist = false,datalistKey = null){

                if(isDatalist){
                    this.formData[datalistKey] = el.length == 0 ? ["empty"] : el;
                    return true;
                }

                const tag    = el.dataset.tag;
                const name   = el.name.split('*-*')[0];
                let value    = el.value;
                
                const rowId  = el.dataset.rowId;
                if(this.formData.dynamicF[tag+'**'+rowId] === undefined) this.formData.dynamicF[tag+'**'+rowId] = {
                    entities : {},
                    tag      : tag,
                };

                //because vanilla masker , input events coming after shaping input value. make sure set everytime
                if(el.dataset.mask == 'money'){
                    if(value.includes(',')) value = value.replace(/\./g,'').replace(',','.');
                }
        
                switch (el.type) {
                    case 'file':
                        if(el.files && el.files[0]){
                            const fileKey = tag+'**dynamicFile**'+el.dataset.fileId+'**'+rowId+'*-*'+name;
                            // Store uploading state
                            this.formData.files[fileKey] = { uploading: true, file: el.files[0], promise: null };
                            // Upload immediately to temp storage
                            this.formData.files[fileKey].promise = this.uploadTempFile(el.files[0]).then(ref => {
                                this.formData.files[fileKey] = { uploading: false, reference: ref };
                            }).catch(err => {
                                console.error('Temp upload failed:', err);
                                this.formData.files[fileKey] = { uploading: false, error: true };
                                this.plib.toast(this.Swal, 'error', 'Dosya yüklenemedi: ' + (err.msg || 'Bilinmeyen hata'));
                            });
                        }
                        break;
                    default:
                        this.formData.dynamicF[tag+'**'+rowId].entities[name] = value;
                        if(el.type === 'checkbox') this.formData.dynamicF[tag+'**'+rowId].entities[name] = el.checked ? 1 : 0;
                        this.formData.dynamicF[tag+'**'+rowId].entities[name] = normalizeFormValue(el);
                        break;
                }
            },
            async uploadTempFile(file) {
                const fd = new FormData();
                fd.append('file', file);
                const rsp = await this.plib.request({
                    url: '/api/v1/temp-upload',
                    method: 'POST'
                }, null, fd);
                if(rsp && rsp.success){
                    return rsp;
                }else{
                    throw new Error(rsp?.msg || 'Yükleme başarısız');
                }
            },
            async buildDynamicFForm(tag,dynamicId = 'new-'+(new Date).getTime(),data = {},selector = null){
                const form   = this.forms[tag];
                const rowId    = dynamicId;
                const row    = document.createElement('div');
                const rowSub = document.createElement('div');
                let target;
               
                row.dataset.id  = rowId;
                row.dataset.tag = tag;
                row.classList.add('dform-row');
                if(selector == null){
                    target = document.querySelector('.area-target[data-tag="'+tag+'"]');
                    row.classList.add('mb-10','mt-10','col-12','card','card-full');
                    rowSub.classList.add('card-body');
                }else{
                    row.classList.add('row');
                    target = document.querySelector(selector);
                }

                if (!target) {
                    console.error('buildDynamicFForm: target not found for', tag, 'selector', selector)
                    return;
                }
                
                for (let index = 0; index < form.fields.length; index++) {
                    const fitem = form.fields[index];
                    
                    const itemRow = document.createElement('div');
                    itemRow.classList.add('row','mt-3','mb-6','item-row');
                    if(fitem?.rowClass !== undefined) itemRow.classList.add(...fitem?.rowClass);
                    if(fitem?.disabled) itemRow.style.display = 'none';
                    
                    const inLabel   = document.createElement('label');
                    inLabel.classList.add('form-label');
                    if(fitem?.required) inLabel.classList.add('required');
                    inLabel.innerHTML = fitem.label.length == 0 ? '<span> </span>' : fitem.label;
                    
                    itemRow.appendChild(inLabel);

                    let input = null;

                    const createInput = (attr,iDiv = null) => {
                        if(iDiv == null){
                            iDiv = document.createElement('div');
                            iDiv.classList.add('col-lg-'+(attr.col ?? '12'),'d-flex');
                        }
                        

                        const input = document.createElement('input');
                        // try to prevent browser autofill
                        input.setAttribute('autocomplete', 'off');
                        input.autocomplete = 'off';
                        input.setAttribute('autocorrect', 'off');
                        input.autocapitalize = 'off';
                        input.setAttribute('spellcheck', 'false');
                        if (attr?.type === 'password') {
                            // stronger hint for password fields
                            input.setAttribute('autocomplete', 'new-password');
                            input.autocomplete = 'new-password';
                        }
                        
                        input.type           = attr.type;
                        input.name           = attr.name;
                        input.dataset.rowId  = rowId;
                        input.dataset.tag    = tag;
                        input.placeholder    = attr?.placeholder ?? '';
                        
                        attr.element         = input;

                        this.rememberField(input, attr.name, {
                            baseReadOnly : !!(attr?.readOnly || attr?.isDate || attr?.isMonth),
                            isFile       : attr.type === 'file',
                        });
                        
                        if(attr?.defaultValue)            input.value = attr.defaultValue;
                        if(attr?.readOnly)                input.readOnly = attr.readOnly;
                        // Order System lock: descriptive fields become read-only when locked;
                        // file slots become read-only when isFilesLocked (e.g. Sipariş Sevke Hazır).
                        // Multiple file inputs use compound names like "transfer_kabul_file**transfer_kabul**id"
                        if(this.readonlyFields.includes(attr.name) || this.readonlyFields.some(rf => attr.name === rf || attr.name.startsWith(rf + '**'))){
                            input.readOnly = true;
                            input.disabled = true;
                            // file inputs inside multiple keep the visual disabled state even with preview
                            if(attr.type === 'file'){
                                input.style.opacity = '0.6';
                                input.style.cursor = 'not-allowed';
                                // prevent the DataTransfer preview from re-enabling the slot
                                input.style.pointerEvents = 'none';
                            }
                        }
                        if(attr.class !== undefined)      input.classList.add(...attr?.class);
                        if(attr?.required !== undefined)  input.required = attr.required;
                        if(attr?.hidden){
                            //here check if showOnValue is setted
                            if(!(attr?.showOnValue === true && data?.entities?.[attr.name] !== undefined)) {
                                input.hidden = attr.hidden;
                                if(iDiv?.parentNode)iDiv.parentNode.style.display = 'none';
                                //if its hidden do not make required even if required is setted
                                input.required = false;
                            }
                        }                  

                        input.classList.add('form-item');
                        
                        input.onclick = (e) => attr?.onclick?.(e,input) ?? (() => {})();
                        input.oninput = (e) => attr?.oninput?.(e) ?? (() => {})();
                        iDiv.appendChild(input);

                        if(attr?.hasIcon === true){
                            iDiv.classList.add('input-group');
                            const icon = document.createElement('span');
                            icon.classList.add('input-group-text','password-toggle');
                            icon.style.cursor = 'pointer';
                            icon.innerHTML = attr?.icon;
                            iDiv.appendChild(icon);
                        }


                        // add show/hide toggle for password fields
                        if (attr?.type === 'password') {
                            // Use Keen theme icons for consistency with the project
                            const makeIcon = (visible) => visible
                                ? '<i class="ki-outline ki-eye-slash fs-5" role="img"></i>'
                                : '<i class="ki-outline ki-eye fs-5" role="img"></i>';

                            let toggle;
                            if (iDiv.classList && iDiv.classList.contains('input-group')) {
                                toggle = document.createElement('span');
                                toggle.classList.add('input-group-text','password-toggle');
                                toggle.style.cursor = 'pointer';
                                toggle.innerHTML = makeIcon(false);
                               
                            } else {
                                toggle = document.createElement('button');
                                toggle.type = 'button';
                                toggle.classList.add('btn','btn-outline-secondary','ms-2','password-toggle');
                                toggle.style.minWidth = '38px';
                                toggle.style.display = 'inline-flex';
                                toggle.style.alignItems = 'center';
                                toggle.style.justifyContent = 'center';
                                toggle.innerHTML = makeIcon(false);
                                
                            }

                            iDiv.appendChild(toggle);

                            toggle.onclick = (ev) => {
                                ev.preventDefault();
                                const visible = input.type !== 'password';
                                input.type = visible ? 'password' : 'text';
                                // update icon (use opposite since visible var was previous state)
                                toggle.innerHTML = makeIcon(!visible);
                            };
                        }
                        
                        if(attr.type === 'file'){
                            input.setAttribute('accept',attr?.accept);
                            input.dataset.fileId = 0;
                                
                            const sizeFeedback = document.createElement('div');
                            sizeFeedback.classList.add('m-1');
                            sizeFeedback.innerHTML = 'Max. Boyut : 40 MB ve Dosya Tipi : .pdf, .xls, .xlsx, .jpg, .jpeg, .png olmalıdır.';
                            sizeFeedback.style.display = 'block';
                            iDiv.parentNode.appendChild(sizeFeedback);


                            const fileData = JSON.parse(data?.entities?.[attr.name] ?? '{}');
                            if(fileData?.description){
                                
                                input.dataset.fileId = fileData.id;
                                input.dataset.file = 'Dosya Mevcut';

                                switch(fileData?.last_status?.op_key){
                                    case 'doc_file_accepted':
                                        input.classList.add('is-valid');
                                        const validFeedback = document.createElement('div');
                                        validFeedback.classList.add('valid-feedback');
                                        validFeedback.innerHTML = 'Dosya kabul edilmiş durumda.';
                                        validFeedback.style.display = 'block';
                                        iDiv.parentNode.appendChild(validFeedback);
                                        break;
                                    case 'doc_file_rejected':
                                        input.classList.add('is-invalid');
                                        const invalidFeedback = document.createElement('div');
                                        invalidFeedback.classList.add('invalid-feedback');
                                        invalidFeedback.innerHTML = 'Dosya reddedilmiş durumda. Lütfen yeni bir dosya yükleyiniz.';
                                        invalidFeedback.style.display = 'block';
                                        iDiv.parentNode.appendChild(invalidFeedback);
                                        break;
                                }

                                const showB     = document.createElement('span');
                                showB.classList.add('input-group-text','rmv-btn-form');
                                showB.innerHTML = '<i class="ki-outline ki-magnifier fs-4"></i>';
                                showB.onclick   = (e) => {
                                    window.open('/order-file/'+(fileData?.qnid ?? fileData?.description));
                                };
                                iDiv.prepend(showB);
                                /*const small = document.createElement('a');
                                small.href  = '#';
                                small.innerHTML = '<a href="/order-file/'+fileData?.description+'" target="_BLANK"><small>Mevcut Dosya İçin Tıklayınız..</small></a>';
                                small.classList.add('text-danger','mt-2','d-block');
                                
                                iDiv.parentNode.appendChild(small);*/

                                
                                // Simulate file inserted for native labels
                                const myFileContent = ['My File Content'];
                                const myFile = new File(myFileContent, fileData?.description);

                                // Create a data transfer object. Similar to what you get from a `drop` event as `event.dataTransfer`
                                const dataTransfer = new DataTransfer();

                                // Add your file to the file list of the object
                                dataTransfer.items.add(myFile);

                                // Save the file list to a new variable
                                const fileList = dataTransfer.files;
                                input.files = fileList;
                            }
                        }else {
                            input.value          = data?.entities?.[attr.name] ?? (attr.defaultValue ?? '');
                            
                            if(this.formDataStore.addional?.[attr.name] !== undefined){
                                input.value = this.formDataStore.addional[attr.name];
                                input.dispatchEvent(new Event('input') );
                            }
                        } 
                        if(attr?.defaultValue) input.dispatchEvent(new Event('input'));

                        if(attr?.isMasked){
                            input.dataset.mask    = attr.mask;
                            switch (attr.mask) {
                                case 'money':
                                    input.style.textAlign = 'right';
                                    //for non float values
                                    //if(!input.value.includes('.')) input.value += '.00';
                                    new VMasker(input).maskMoney({
                                        // Decimal precision -> "90"
                                        precision: 2,
                                        // Decimal separator -> ",90"
                                        separator: ',',
                                        // Number delimiter -> "12.345.678"
                                        delimiter: '.',
                                    });
                                    break;
                                case 'phone':
                                    new  VMasker(input).maskPattern("(999) 999-99-99");
                                    break;
                                case 'custom':
                                    new  VMasker(input).maskPattern(attr.format);
                                    break;
                                default:
                                    break;
                            }
                        }

                        //do this after is added to div because lightpick component is not seeing before
                        if(attr?.isDate == true){
                            // Sub-field definitions don't always carry the Bootstrap
                            // classes (tender deadline was one of them). Force them
                            // here so every date control looks like the rest of the
                            // form. data-fmt marks the serialization (see
                            // lib/formSerialize.js); `date-input` is kept for
                            // backwards compatibility with older CSS/scripts.
                            input.classList.add('form-control', 'form-item', 'date-input');
                            input.dataset.fmt = 'date';
                            input.readOnly    = true;
                            input.placeholder = 'Tarih Seçiniz';
                            if(data?.entities?.[attr.name] !== undefined){
                                if(attr?.hasTime){
                                    let dvalue        = data?.entities?.[attr.name].split(' ');
                                    input.value       = dvalue[0].split('-').reverse().join('/')+' '+dvalue[1];
                                }else{
                                    input.value       = data?.entities?.[attr.name] !== undefined ? data?.entities[attr.name].split('-').reverse().join('/') : '';
                                }
                            }
                            if(attr?.readOnly !== true){
                                // initialize flatpickr
                                const fpOptions = {
                                    dateFormat : attr?.hasTime ? 'd/m/Y H:i' : 'd/m/Y',
                                    enableTime : !!attr?.hasTime,
                                    time_24hr  : true,
                                    locale     : 'tr',
                                    defaultDate: data?.entities?.[attr.name] ?? null,
                                    onChange   : (selectedDates, dateStr, instance) => {
                                        if(selectedDates.length){
                                            const formatted = instance.formatDate(selectedDates[0], attr?.hasTime ? 'd/m/Y H:i' : 'd/m/Y');
                                            input.value = formatted;
                                            input.dispatchEvent(new Event('input', { bubbles: true }));
                                        }
                                    }
                                };
                                
                                const fp = flatpickr(input, fpOptions);
                                this.flatpickrInstances.push(fp);
                            }
                            
                            input.readOnly = true;
                        }

                        if(attr?.isMonth == true){
                            input.classList.add('form-control', 'form-item');
                            input.readOnly    = true;
                            input.placeholder = 'Tarih Seçiniz';
                            if(this.readonlyFields.includes(attr.name) || this.readonlyFields.some(rf => attr.name === rf || attr.name.startsWith(rf + '**'))){
                                input.disabled = true;
                                input.style.opacity = '0.6';
                                input.style.cursor = 'not-allowed';
                            }
                            input.value       = data?.entities?.[attr.name] !== undefined ? data?.entities[attr.name].split('-').reverse().join('/') : '';

                            // initialize flatpickr with month select plugin
                            const fpOptions = {
                                dateFormat : 'm/Y',
                                locale     : 'tr',
                                defaultDate: data?.entities?.[attr.name] ?? null,
                                onChange   : (selectedDates, dateStr, instance) => {
                                    if(selectedDates.length){
                                        const formatted = instance.formatDate(selectedDates[0], 'm/Y');
                                        input.value = formatted;
                                        input.dispatchEvent(new Event('input', { bubbles: true }));
                                    }
                                },
                                plugins    : [ new monthSelectPlugin({ shorthand: true, dateFormat: 'm/Y', altFormat: 'F Y' }) ]
                            };

                            const fp = flatpickr(input, fpOptions);
                            this.flatpickrInstances.push(fp);

                        }

                        return iDiv;
                    };

                    const createSelect = async (attr,iDiv = null) => {
                        if(iDiv == null){
                            iDiv = document.createElement('div');
                            iDiv.classList.add('col-lg-'+(attr.col ?? '12'),'d-flex');
                        }


                        const input = document.createElement('select');
                        input.classList.add('form-item','form-select');
                        //input.dataset.fileId = fileId;
                        input.dataset.rowId  = rowId;
                        input.dataset.tag    = tag;
                        input.name           = attr.name;

                        this.rememberField(input, attr.name, { baseReadOnly: !!attr?.readOnly, isFile: false });

                        if(attr?.class !== undefined)    input.classList.add(...attr?.class);
                        if(attr?.disabled !== undefined && attr.disabled) iDiv.style.display = 'none';
                        if(attr?.required !== undefined) input.required = attr.required;
                        if(this.readonlyFields.includes(attr.name) || this.readonlyFields.some(rf => attr.name === rf || attr.name.startsWith(rf + '**'))){
                            input.disabled = true;
                            input.style.opacity = '0.6';
                            input.style.cursor = 'not-allowed';
                        }
                        if(attr?.hidden){
                            input.hidden = attr.hidden;
                            iDiv.hidden = attr.hidden;
                            if(iDiv.parentNode) iDiv.parentNode.style.display = 'none';
                        }  
                        
                        let op      = document.createElement('option');
                        op.value    = '';
                        op.text     = attr?.placeholder ?? 'Seçiniz';
                        op.selected = true;

                        input.appendChild(op);

                        if(attr?.setOptions !== undefined){
                            attr.options = await loadSetOptions(attr.setOptions);
                        }
                        input.oninput = (e) => attr.oninput(e);
                        for (let index = 0; index < attr?.options?.length; index++) {
                            op = document.createElement('option');
                            op.text  = attr.options[index].text;
                            op.value = attr.options[index].value;
                            if(attr.options[index].key !== undefined) op.dataset.key = attr.options[index].key;
                            if(attr.options[index].limit !== undefined) op.dataset.limit = attr.options[index].limit;
                            if(attr.options[index].data !== undefined) op.dataset.info = JSON.stringify(attr.options[index].data);
                            if(data?.entities?.[attr.name] !== undefined && data?.entities?.[attr.name] == op.value) op.selected = true;
                            if(this.formDataStore.addional?.[attr.name] !== undefined && this.formDataStore.addional[attr.name] == op.value){
                                op.selected = true;
                                input.dispatchEvent(new Event('input') );
                            }
                            
                            input.appendChild(op);
                        }

                        if(this.formDataStore.addional?.[attr.name] !== undefined){
                            input.dispatchEvent(new Event('input') );
                        }
                        iDiv.appendChild(input);
                        if(data?.entities?.[attr.name] !== undefined){
                            if(attr?.triggerInputOnSetup === undefined || attr?.triggerInputOnSetup == true) input.dispatchEvent(new Event('input'));
                        } 

                        
                        //NiceSelect.bind(input, attr?.isSearchable ? {searchable : true} : {});
                        
                        return iDiv;
                    };

                    // Tag typeahead multi-select: textbox with dropdown, picked values
                    // become removable pills. Stored as JSON array string in a
                    // single entity. Empty selection means "applies to all".
                    const buildChecksField = async (attr,iDiv,rowId,tag,data) => {
                        iDiv.classList.add('col-lg-'+(attr.col ?? '12'));
                        const wrap = document.createElement('div');
                        wrap.classList.add('form-control','d-flex','flex-wrap','align-items-center','gap-1','tag-typeahead');
                        wrap.style.cssText = 'cursor:text;min-height:44px;height:auto;padding:6px 12px;position:relative;';
                        let opts = Array.isArray(attr.options) ? attr.options : [];
                        if (attr.setOptions !== undefined) {
                            try { const loaded = await loadSetOptions(attr.setOptions); if (Array.isArray(loaded)) opts = loaded; } catch(e) {}
                        }
                        const norm = opts.map(o => ({ value: String(o.value ?? o.text ?? ''), text: String(o.text ?? o.value ?? '') })).filter(o => o.value !== '');
                        let current = [];
                        try {
                            const raw = data?.entities?.[attr.name];
                            if (Array.isArray(raw)) current = raw.map(String);
                            else if (typeof raw === 'string' && raw.trim() !== '') {
                                try { const p = JSON.parse(raw); current = Array.isArray(p) ? p.map(String) : [String(raw)]; }
                                catch(e) { current = raw.split(',').map(s=>s.trim()).filter(Boolean); }
                            }
                        } catch(e) {}
                        const labelOf = (v) => { const f = norm.find(o => o.value === v); return f ? f.text : v; };
                        const pushCurrent = () => {
                            this.submitDynamicChanges({
                                name: attr.name,
                                value: JSON.stringify(current),
                                type: 'checks',
                                dataset: { tag: tag, rowId: rowId },
                                classList: { contains: () => false }
                            });
                        };
                        const pillBox = document.createElement('div');
                        pillBox.classList.add('d-flex','flex-wrap','gap-1');
                        const textInput = document.createElement('input');
                        textInput.type = 'text';
                        textInput.placeholder = attr.placeholder || 'Yazarak arayın...';
                        textInput.style.cssText = 'border:none;outline:none;flex:1 1 120px;min-width:120px;font-size:.86rem;background:transparent;';
                        textInput.setAttribute('autocomplete', 'off');
                        const drop = document.createElement('div');
                        drop.style.cssText = 'display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 12px 32px rgba(15,23,42,.14);z-index:60;max-height:190px;overflow-y:auto;padding:4px;';
                        const renderPills = () => {
                            pillBox.innerHTML = '';
                            current.forEach((v) => {
                                const pill = document.createElement('span');
                                pill.classList.add('d-inline-flex','align-items-center','gap-1');
                                pill.style.cssText = 'background:#154B91;color:#fff;border-radius:999px;padding:3px 6px 3px 10px;font-size:.8rem;font-weight:600;white-space:nowrap;';
                                const t = document.createElement('span');
                                t.textContent = labelOf(v);
                                const x = document.createElement('button');
                                x.type = 'button';
                                x.textContent = '×';
                                x.setAttribute('aria-label', 'Kaldır');
                                x.style.cssText = 'border:none;background:rgba(255,255,255,.22);color:#fff;border-radius:50%;width:18px;height:18px;line-height:1;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;padding:0;';
                                x.addEventListener('mousedown', (ev) => { ev.preventDefault(); current = current.filter(c => c !== v); renderPills(); renderDrop(); pushCurrent(); });
                                pill.appendChild(t);
                                pill.appendChild(x);
                                pillBox.appendChild(pill);
                            });
                        };
                        const renderDrop = () => {
                            const q = (textInput.value || '').toLocaleLowerCase('tr');
                            drop.innerHTML = '';
                            const left = norm.filter(o => !current.includes(o.value) && (q === '' || o.text.toLocaleLowerCase('tr').includes(q) || o.value.toLowerCase().includes(q)));
                            if (!left.length) { drop.style.display = 'none'; return; }
                            left.slice(0, 50).forEach((o) => {
                                const it = document.createElement('div');
                                it.textContent = o.text;
                                it.style.cssText = 'padding:8px 10px;border-radius:8px;font-size:.86rem;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
                                it.addEventListener('mouseenter', () => { it.style.background = '#f1f5f9'; });
                                it.addEventListener('mouseleave', () => { it.style.background = 'transparent'; });
                                it.addEventListener('mousedown', (ev) => { ev.preventDefault(); current.push(o.value); renderPills(); textInput.value = ''; renderDrop(); textInput.focus(); pushCurrent(); });
                                drop.appendChild(it);
                            });
                            drop.style.display = 'block';
                        };
                        textInput.addEventListener('input', renderDrop);
                        textInput.addEventListener('focus', renderDrop);
                        textInput.addEventListener('blur', () => { setTimeout(() => { drop.style.display = 'none'; }, 150); });
                        textInput.addEventListener('keydown', (ev) => {
                            if (ev.key === 'Enter') {
                                ev.preventDefault();
                                const first = drop.querySelector('div');
                                if (drop.style.display !== 'none' && first) { first.dispatchEvent(new Event('mousedown')); }
                            } else if (ev.key === 'Backspace' && textInput.value === '' && current.length) {
                                current.pop(); renderPills(); renderDrop(); pushCurrent();
                            } else if (ev.key === 'Escape') { drop.style.display = 'none'; }
                        });
                        wrap.addEventListener('mousedown', (ev) => { if (ev.target === wrap) { ev.preventDefault(); textInput.focus(); } });
                        wrap.appendChild(pillBox);
                        wrap.appendChild(textInput);
                        wrap.appendChild(drop);
                        if (!opts.length) {
                            const hint = document.createElement('div');
                            hint.classList.add('text-muted','small','w-100');
                            hint.textContent = attr.emptyText || 'Seçenek bulunamadı — önce tanımları girin.';
                            wrap.appendChild(hint);
                        }
                        iDiv.appendChild(wrap);
                        renderPills();
                        // seed dynamicF so untouched-on-edit rows still save
                        pushCurrent();
                        return iDiv;
                    };
                    
                    let inputDiv = document.createElement('div');
                    inputDiv.classList.add('col-lg-12');
                    inputDiv.classList.add('row');
                    
                    switch (fitem.type) {   
                        case 'sub':
                        case 'multiple':
                            this.keyLock = [];
                            //this method will add sub elements
                            const addElements = async (nameTag = null,withClick = false) => {
                                
                                //addional item row element
                                const row = document.createElement('div');
                                row.classList.add('row','mb-2','multiple-item-row');
                                if(fitem.type === 'multiple') row.classList.add('multiple-item-card');
                                
                                inputDiv.appendChild(row);

                                //row components
                                for(let i = 0;i < fitem.subs.length; i++){
                                    const element = fitem.subs[i];
                                    
                                    const el = {...element};
                                    //create unique nametag
                                    if(nameTag == null) nameTag = (new Date).getTime()+'-'+inputDiv.querySelectorAll("[name^="+el.name+"]").length;
                                    
                                    const rowElm = document.createElement('div');
                                    rowElm.classList.add('col-md-'+el.col);
                                    let lbl   = document.createElement('label');
                                    lbl.classList.add('form-label','mt-5','input-label');
                                    //item label
                                    if(el?.label !== undefined && el?.label != ''){
                                        lbl.innerHTML = el.label;
                                        rowElm.appendChild(lbl);
                                    }

                                    

                                    const inpGroup = document.createElement('div');
                                    inpGroup.classList.add('input-group');
                                    rowElm.appendChild(inpGroup);

                                    el.name = fitem.type == 'multiple' ? el.name+'**'+(fitem.group_key ?? 'unalign-group-key') + '**'+ nameTag : el.name;
                                    
                                    if(el.type == 'select'){
                                        await createSelect(el,inpGroup);
                                    }else if(el.type == 'checks'){
                                        await buildChecksField(el,inpGroup,rowId,tag,data);
                                    }else{
                                        createInput(el,inpGroup);
                                    }

                                    

                                    //if sub element has subs :D
                                    if(el?.multiple){
                                        lbl.classList.add('d-flex','justify-content-between','align-items-center');
                                        const   addSub    = async (name = null) => {
                                            const inpGroup2 = document.createElement('div');
                                            inpGroup2.classList.add('input-group','mt-2');
                                            rowElm.appendChild(inpGroup2);

                                            const cloneElm = {...el};

                                            const number   = rowElm.querySelectorAll("[name^='"+el.name+"']").length;
                                            cloneElm.name += '-'+number;
                                            if(name != null) cloneElm.name = name;
                                            cloneElm.type == 'select' ? await createSelect(cloneElm, inpGroup2) : createInput(cloneElm, inpGroup2);
                                            
                                            //sub item remove
                                            const rmvInp     = document.createElement('span');
                                            rmvInp.classList.add('input-group-text','rmv-btn-form');
                                            rmvInp.innerHTML = '<i class="fa fa-trash fs-5 selectable-icon"></i>';
                                            rmvInp.onclick   = (e) => {
                                                this.formData.removedData.push({
                                                    id    : rowId,
                                                    type  : 'entity',
                                                    key   : cloneElm.name
                                                });
                                                delete this.formData?.dynamicF?.[tag+'**'+rowId]?.entities?.[cloneElm.name];

                                                inpGroup2.remove();

                                                //custom event for talk panel
                                                cloneElm?.onremove(rowElm);
                                            };
                                            inpGroup2.appendChild(rmvInp);
                                        }
                                        const   inpPlus   = document.createElement('i');
                                        inpPlus.classList.add('fa','fa-plus','fs-4','selectable-icon');
                                        inpPlus.onclick   = async (e) => addSub();
                                        lbl.prepend(inpPlus);
                                        
                                        //search if data has this items
                                        for(let key in data.entities){
                                            if(key.includes(el.name) && key.split(el.name)[1] !== undefined && key.split(el.name)[1] !== ''){
                                                addSub(key);
                                            }
                                        }
                                        
                                    }

                                    if(el.isDate){
                                        const calInp     = document.createElement('span');
                                        calInp.classList.add('input-group-text');
                                        calInp.innerHTML = '<i class="fa fa-calendar fs-5"></i>';
                                        calInp.onclick = () => el.element.dispatchEvent(new Event('focus'));
                                        inpGroup.appendChild(calInp);
                                    }

                                    if(el.isMasked){
                                        const calInp     = document.createElement('span');
                                        switch (el.mask) {
                                            case 'phone':
                                                calInp.classList.add('input-group-text');
                                                calInp.innerHTML = '<i class="fa fa-phone fs-5"></i>';
                                                calInp.onclick = () => el.element.dispatchEvent(new Event('focus'));
                                                inpGroup.appendChild(calInp);
                                                break;
                                            case 'money':
                                                calInp.classList.add('input-group-text');
                                                calInp.innerHTML = el?.moneyIcon ?? '';
                                                calInp.onclick = () => el.element.dispatchEvent(new Event('focus'));
                                                if(el?.moneyIcon) inpGroup.appendChild(calInp);
                                                break;
                                            default:
                                                break;
                                        }
                                    }

                                    if(fitem.type == 'multiple' && fitem.subs[fitem.subs.length-1] === element){
                                        const rmvInp     = document.createElement('span');
                                        rmvInp.classList.add('input-group-text','rmv-btn-form');
                                        rmvInp.innerHTML = '<i class="selectable-icon ki-outline ki-trash fs-4"></i>';
                                        rmvInp.onclick   = (e) => {
                                            document.querySelectorAll('[name*="'+nameTag.split('-')[0]+'"]').forEach(rmElm => {
                                                this.formData.removedData.push({
                                                    id    : rowId,
                                                    type  : 'entity',
                                                    key   : rmElm.name
                                                });
                                                delete this.formData?.dynamicF?.[tag+'**'+rowId]?.entities?.[rmElm.name];
                                            });
                                            row.remove();
                                        };
                                        
                                        if(fitem?.removable != false ) inpGroup.appendChild(rmvInp);
                                        
                                    }

                                    
                                    row.appendChild(rowElm);
                                }

                                row.dataset.tag = (fitem.group_key ?? 'unalign-group-key')+'-'+nameTag.split('-')[0]+'-row';
                                this.keyLock.push(row.dataset.tag);
                            };

                            inLabel.classList.add('d-flex','justify-content-between','align-items-center');
                            if(fitem.label && fitem.label.trim().length > 0) inLabel.classList.add('form-section-heading');
                            else inLabel.remove();
                            
                            const iconDiv = document.createElement('div');
                            iconDiv.classList.add('align-items-center','bg-highlight','d-flex','flex-shrink-0','h-10','justify-content-center','me-5','rounded-circle','w-10');
                            
                            if(fitem.type === 'multiple'){
                                inputDiv.classList.add('border','rounded','p-2');
                                const icon = document.createElement('i');
                                icon.classList.add('ki-outline','selectable-icon','ki-plus','fs-1');
                                icon.id = tag+'-'+(fitem.group_key ?? 'unalign-group-key')+'-subadd-'+rowId;
                                iconDiv.appendChild(icon);

                                icon.onclick   = async () =>{
                                    await addElements(null,true);
                                }; 
                                
                                inLabel.appendChild(iconDiv);
                                
                                //await addElements(null,true);
                                let hasData = false;
                                //here create elements if data is exist on given data with object nametag
                                if(data?.entities){
                                    for(let key in data?.entities) {
                                        if(key.includes('**'+fitem.group_key) && !this.keyLock.includes(fitem.group_key+'-'+key.split('**')[2].split('-')[0]+'-row')){
                                            
                                            await addElements(key.split('**')[2]);
                                            hasData = true;
                                        } 
                                    };
                                }

                                if(!hasData){
                                    //append one empty row
                                    icon.click();
                                }

                                if(fitem?.hideAdd === true) icon.hidden = true;
                                
                            } 
                        
                            //here check multiple input values if exist
                            /*if(data?.entities !== undefined){
                                const keys = {};
                                Object.keys(data?.entities).filter(key => {
                                    if(key.includes(fitem.group_key)){
                                        keys[key.split('**')[1]+'**'+key.split('**')[2]] = true
                                    }
                                });

                                //foreach grouıp key item add row
                                for(let key in keys) addElements();
                            }else{
                                addElements();
                            }*/
                            
                            if(fitem.type == 'sub') await addElements();
                            itemRow.appendChild(inputDiv);
                            if(fitem.hidden == true) itemRow.style.display = 'none';
                            
                            if(fitem?.requiredIfFirst){
                                       
                                //here check row count
                                const rowCount = itemRow.querySelectorAll('.multiple-item-row').length;
                                if(rowCount > 1){
                                    //remove required attr from first row elements
                                    itemRow.querySelectorAll('.multiple-item-row .form-item').forEach(el => {
                                        el.required = false;
                                    });
                                }
                            }
                            break;
                        case 'section':
                            labelDiv.remove();
                            itemRow.appendChild(document.createElement('hr'));
                            break;
                        case 'yesno':
                            
                            const key = (new Date()).getTime();
                            inputDiv = document.createElement('div');
                            inputDiv.classList.add('col-lg-6','d-flex','flex-direction-row','justify-content-between');
                            
                            let checkDiv = document.createElement('div');
                            checkDiv.classList.add('form-check','form-check-custom','form-check-solid','form-check-lg');

                            
                            input = document.createElement('input');
                            input.type = 'radio';
                            input.oninput = (e) => fitem.oninput(e);
                            //input.dataset.fileId = fileId;
                            input.dataset.rowId  = rowId;
                            input.dataset.tag    = tag;
                            input.value          = 1;
                            input.name           = fitem.name+'*-*'+key;
                            input.classList.add('form-check-input','valid');


                            if( tag == 'op-doc-per-kanaat' && 
                                fitem.name == 'canWork' && 
                                document.querySelectorAll("[type='radio'][data-tag='"+tag+"'][name^='"+fitem.name+"']").length == 0 ){
                                input.checked = true;
                            }


                            if(data?.entities?.[fitem.name] !== undefined && data?.entities?.[fitem.name] == 1) input.checked = true;

                            checkDiv.appendChild(input);

                            let label  = document.createElement('label');
                            label.innerHTML = 'Evet';
                            label.classList.add('form-check-label');

                            checkDiv.appendChild(label);
                        
                            inputDiv.appendChild(checkDiv);
                            
                            checkDiv = document.createElement('div');
                            checkDiv.classList.add('form-check','form-check-custom','form-check-solid','form-check-lg');

                            input = document.createElement('input');
                            input.type = 'radio';
                            input.oninput = (e) => fitem.oninput(e);
                            //input.dataset.fileId = fileId;
                            input.dataset.rowId  = rowId;
                            input.dataset.tag    = tag;
                            input.name           = fitem.name+'*-*'+key;
                            input.value          = 0;
                            input.classList.add('form-check-input','valid');

                            
                            if(data?.entities[fitem.name] !== undefined && data.entities[fitem.name] == 0) input.checked = true;

                            checkDiv.appendChild(input);

                            label  = document.createElement('label');
                            label.innerHTML = 'Hayır';
                            label.classList.add('form-check-label');
                            
                            if( tag == 'op-doc-per-kanaat' && 
                                fitem.name == 'canWork' && 
                                document.querySelectorAll("[type='radio'][data-tag='"+tag+"'][name^='"+fitem.name+"']").length == 0 &&
                                (data?.entities[fitem.name] === undefined || data.entities[fitem.name] != 0)){
                                input.hidden = true;
                                label.hidden = true;
                            }


                            checkDiv.appendChild(label);
                        
                            inputDiv.appendChild(checkDiv);

                            itemRow.appendChild(inputDiv);


                            break;
                        case 'textarea':
                            input                = document.createElement('textarea');
                            input.name           = fitem.name;
                            //input.dataset.fileId = fileId;
                            input.dataset.rowId  = rowId;
                            input.dataset.tag    = tag;
                            if(fitem.rows) input.rows = fitem.rows;
                            else if(fitem.name === 'order_desc') input.rows = 3;
                            input.classList.add(...fitem.class);

                            this.rememberField(input, fitem.name, { baseReadOnly: false, isFile: false });
                            if(fitem?.required !== undefined) input.required = fitem.required;
                            if(this.readonlyFields.includes(fitem.name) || this.readonlyFields.some(rf => fitem.name === rf || fitem.name.startsWith(rf + '**'))){
                                input.readOnly = true;
                                input.disabled = true;
                                input.style.opacity = '0.6';
                                input.style.cursor = 'not-allowed';
                            }

                        
                            if(fitem?.hidden) input.hidden = fitem.hidden;

                            input.classList.add(...fitem.class);
                            input.oninput = (e) => fitem.oninput(e);

                            inputDiv.appendChild(input);
                            
                            if(data?.entities?.[fitem.name] !== undefined){
                                input.value = data.entities[fitem.name];
                                input.innerHTML = data.entities[fitem.name];
                            }

                            inLabel.classList.add('input-label');
                            itemRow.appendChild(inputDiv);

                            break;
                        case 'button':
                            input           = document.createElement('button');
                            input.onclick   = (e) => fitem.oninput(e);
                            input.innerHTML = fitem.value;
                            input.type      = 'button';
                            input.classList.add(...fitem.class);
                            inputDiv.appendChild(input);
                            itemRow.appendChild(inputDiv);
                            break;
                        case 'tree':
                            inputDiv = document.createElement('div');
                            inputDiv.classList.add('col-lg-'+fitem?.col,'d-flex','align-items-center');
                            itemRow.appendChild(inputDiv);
                            this.permissionTree = TreeModal.render({
                                target: inputDiv,
                                items: fitem?.list,
                                defaultChecked: data?.entities?.[fitem.name] ?? [],
                                onChange: (checkedItems) => fitem?.oncheck(checkedItems),
                            });

                            if (this.permissionTree) {
                                this._treeModalInstance = this.permissionTree;
                            }


                            break;
                        case 'select':
                            itemRow.appendChild(await createSelect(fitem));
                            inLabel.classList.add('input-label');
                            break;
                        case 'switch':
                            inLabel.classList.add('input-label');
                            inputDiv = document.createElement('div');
                            inputDiv.classList.add('form-check','m-5','form-check-custom','form-check-solid');
                            
                            input = document.createElement('input');
                            input.classList.add('form-check-input');
                            input.type = 'checkbox';
                            input.oninput = (e) => fitem.oninput(e);
                            //input.dataset.fileId = fileId;
                            input.dataset.rowId  = rowId;
                            input.dataset.tag    = tag;
                            input.name           = fitem.name;
                            if(fitem?.required !== undefined) input.required = fitem.required;

                            if(data?.entities?.[fitem.name] !== undefined && data?.entities?.[fitem.name] == 1) input.checked = true;

                            inputDiv.appendChild(input);

                            const lbl  = document.createElement('label');
                            lbl.classList.add('form-check-label','ps-2');
                            lbl.innerHTML = fitem?.desc ?? '-';
                            inputDiv.appendChild(lbl);
                            itemRow.appendChild(inputDiv);

                            if(fitem?.sub){
                                for (let index = 0; index < fitem.sub.length; index++) {
                                    const subItem = fitem.sub[index];

                                    inputDiv = document.createElement('div');
                                    inputDiv.classList.add('col-lg-4','fv-row');

                                    const subDiv = document.createElement('div');
                                    subDiv.classList.add('d-flex','align-items-center','gap-3');

                                    const sinput = document.createElement('input');
                                    sinput.type = subItem.type;
                                    sinput.name = subItem.name;
                                    sinput.placeholder = subItem.placeholder;
                                    //sinput.dataset.fileId = fileId
                                    sinput.dataset.rowId  = rowId;
                                    sinput.dataset.tag    = tag;
                                    sinput.oninput = (e) => subItem.oninput(e);
                                    sinput.classList.add(...subItem.class);
                                    if(fitem?.required !== undefined) sinput.required = fitem.required;

                                    if(data?.entities[subItem.name] !== undefined) sinput.value = data.entities[subItem.name];

                                    if(input.checked) sinput.style.visibility = 'visible';

                                    subDiv.appendChild(sinput);


                                    inputDiv.appendChild(subDiv);
                                    itemRow.appendChild(inputDiv);
                                }
                            }

                            if(fitem?.hidden){
                                itemRow.style.display = 'none';
                            }  
                            break;
                        default:
                            itemRow.appendChild(createInput(fitem,inputDiv));
                            break;
                    }
                    
                    rowSub.appendChild(itemRow);
                }

                row.appendChild(rowSub);


                const rmvBtn = document.createElement('a');
                rmvBtn.classList.add('btn','btn-sm','btn-outline-danger','w-100','btn-block');
                rmvBtn.href  = 'javascript:;';
                rmvBtn.onclick = () => {
                    /*if(!rowId.toString().includes('new')){
                        this.formData.removedData.push({
                            id    : rowId,
                            type  : 'connection'
                        });
                    }else{
                        delete this.formData.dynamicF[tag+'**'+rowId];
                    }*/
                    row.remove();
                };
                rmvBtn.innerHTML = `<i class="ki-duotone ki-trash fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span><span class="path3"></span>
                                    <span class="path4"></span><span class="path5"></span></i> Sil`;
                const footer = document.createElement('div');
                footer.classList.add('card-body');
                footer.appendChild(rmvBtn);

                row.appendChild(footer);

                if(form?.showRemoveButton == false) rmvBtn.remove();
            
                if(form?.isFoldable && data?.entities[form?.foldableTag] !== undefined ){
                    const collBtn = document.createElement('button');
                    collBtn.classList.add('btn','btn-outline-danger','btn-outline','row','w-100','m-0','fold-btn');
                    collBtn.innerHTML = data.entities[form?.foldableTag];
                    collBtn.type = 'button';
                    collBtn.onclick = () => {
                        target.querySelectorAll('.foldable-area').forEach(el => {if(el != row) el.hidden = true});
                        row.hidden = !row.hidden;

                        target.querySelectorAll('.fold-btn').forEach(el => {el.classList.remove('active')});

                        if(!row.hidden) collBtn.classList.add('active');
                    };
                    //for section seperation
                    target.appendChild(document.createElement('hr'));
                    target.appendChild(collBtn);

                    row.classList.add('foldable-area');
                    row.hidden = true;
                }

                target.appendChild(row);
                
                //for section seperation
                if(form?.isFoldable) target.appendChild(document.createElement('hr'));
                form.oncreated(rowId,row);
            },
            buildClientTable(target,clickEvent){
                    
                //set headers
                const headers = [
                    {
                        title : 'Firma Ünvan',
                        key   : 'title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Firma Kodu',
                        key   : 'clicode',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Cari Kodu',
                        key   : 'lifnr',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,rowData,col) => {
                            const v = String(col || rowData.lifnr || '').trim();
                            if (!v || v === '-') return document.createTextNode('-');
                            const isMudur = /^90\d+/.test(v);
                            const pill = document.createElement('span');
                            pill.textContent = v;
                            pill.style.cssText = 'display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:800;border:1px solid '+(isMudur?'#bbf7d0;background:#f0fdf4;color:#15803d':'#e2e8f0;background:#f8fafc;color:#64748b');
                            if (isMudur) pill.title = 'Müdürlük';
                            return pill;
                        }
                    },{
                        title : 'Sistem',
                        key   : 'client_system',
                        order : true,
                        type  : 'string',
                        columnFormatter : (elm,rowData,col) => {
                            const sys = (col || rowData.client_system || 'GDZ').toString().toUpperCase();
                            const pill = document.createElement('span');
                            pill.textContent = sys;
                            pill.style.cssText = 'display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:800;border:1px solid '+(sys==='ADM'?'#bfdbfe;background:#eff6ff;color:#1e40af':'#fed7aa;background:#fff7ed;color:#9a3412');
                            return pill;
                        }
                    }
                ];
                
                //initiate table
                this.table = new PickleTable({
                    container : target, //table target div
                    headers   : headers,
                    pageLimit : 10, // -1 for closing pagination
                    height    : '56vh',
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
                            value : 'op-doc-client-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-client'
                        },{
                            key   : 'system_only',
                            type  : '=',
                            value : 'all'
                        }
                    ],
                    nextPageIcon : '<i class="ki-outline ki-arrow-right "></i>',
                    prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                    rowClick     : (elm,data)=> clickEvent(data),
                    rowFormatter : (elm,data)=>{
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
        },
        beforeUnmount() {
            if (Array.isArray(this.flatpickrInstances)) {
                this.flatpickrInstances.forEach(inst => {
                    try {
                        if (inst && typeof inst.destroy === 'function') inst.destroy();
                    } catch (e) {}
                });
                this.flatpickrInstances = [];
            }

            if (this._treeModalInstance && typeof this._treeModalInstance.destroy === 'function') {
                try { this._treeModalInstance.destroy(); } catch (e) {}
            }
        },
        
    }
</script>
<template>
    <div v-if="isLoadingRoles" class="text-center p-4">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Yükleniyor...</span>
        </div>
        <div>Roller yükleniyor, lütfen bekleyin...</div>
    </div>
    <div v-else>
        <div class="area-target" v-for="(item, index) in ftypes" :data-tag="item"></div>
        <AppFab v-if="showsave && (authStore.data.type=='admin' || ['op-doc-request-form','op-doc-requestf-form','op-doc-order-form','op-doc-order-item-form'].some(k=>formtypes.includes(k)))" :savebtntitle="savebtntitle ?? 'Formu Kaydet'" :btntype="fabtype" :callback="formCallback" :rejectcallback="rejectcallback" :acceptcallback="acceptcallback" :cancelcallback="() => $router.go(-1)" />
    </div>
</template>

<style>
.custom-button{
    background-color: #154b91!important;
    color: #fff!important;
    border-color: #154b91!important;
    width: auto;
    margin-left: auto;
}
.custom-button:hover{
    background-color: #fff!important;
    color: #154b91!important;
    border-color: #0c3560!important;
}
.area-target .input-label{
    font-weight: 600 !important;
    color: slategrey !important;
}
/* nested sub/multiple .row also matches Bootstrap .row>* gutter padding,
   which insets it vs full-width siblings — neutralize outer edges.
   NOTE: do NOT zero first/last-child padding here — on wrapped grids (2+ rows)
   that un-aligns columns (first col sticks out left). Keep gutters uniform. */
.area-target .col-lg-12 > .row{
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}
</style>