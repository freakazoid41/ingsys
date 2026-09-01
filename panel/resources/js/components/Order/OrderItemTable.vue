<script>
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import flatpickr from 'flatpickr';
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect/index.js';
import { Turkish } from 'flatpickr/dist/l10n/tr.js';
import 'flatpickr/dist/plugins/monthSelect/style.css';
import * as XLSX from 'xlsx';

flatpickr.localize(Turkish);

export default {
    props: {
        orderId: { type: String, required: true },
        orderNumericId: { type: Number, required: false },
        selectable: { type: Boolean, default: false },
        atOnceMode: { type: Boolean, default: false },
        highlightQnid: { type: String, default: null },
        containerSuffix: { type: String, default: '' },
        orderDate: { type: String, default: '' },
        readonly: { type: Boolean, default: false }
    },
    data(){
        return {
            plib: new Plib(),
            resolvedParentId: null,
            selected: {},
            splitAmounts: {},
            serials: {},
            serialEnabled: {},
            serialCollapsed: {},
            serialViewCollapsed: {},
            excelFileInputs: {},
            items: [],
            loading: true,
            error: null,
            fpInstances: [],
            itemTestFiles: {},
            itemImages: {},
            existingTestFiles: {},
            existingImages: {},
            itemFilesCollapsed: {},
            testFileInputs: {},
            imageFileInputs: {},
            gallery: { visible:false, items:[], index:0 }
        }
    },
    computed:{
        currentGalleryItem(){ return this.gallery.items[this.gallery.index] || null; },
        hasItems(){ return this.items.length > 0; },
        selectedCount(){ return Object.keys(this.selected).filter(k => this.selected[k]).length; },
        itemsMap(){ return new Map(this.items.map(i => [i.id, i])); },
        parsedOrderDate(){
            const raw = this.orderDate || '';
            if(!raw) return '';
            // d/m/Y → YYYY-MM-01
            const dmY = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if(dmY) return dmY[3] + '-' + dmY[2].padStart(2,'0') + '-01';
            // Y-m-d → YYYY-MM-01
            const ymd = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if(ymd) return ymd[1] + '-' + ymd[2] + '-01';
            // YYYY-MM-DDTHH:MM:SS etc
            const iso = raw.match(/^(\d{4})-(\d{2})/);
            if(iso) return iso[1] + '-' + iso[2] + '-01';
            return '';
        },
        allValid(){
            // Validate partial mode (selected items)
            for(const key in this.selected){
                if(!this.selected[key]) continue;
                const item = this.itemsMap.get(key);
                if(!item) continue;
                const amt = parseFloat(this.splitAmounts[key]);
                if(isNaN(amt) || amt < 1) return false;
                const qty = parseFloat(item.quantity);
                if(amt > qty) return false;
                if(item.unit === 'ST' && !Number.isInteger(amt)) return false;
                // Validate serials for ST — only if enabled
                if(item.unit === 'ST' && this.serialEnabled[key]){
                    const sers = this.serials[key] || [];
                    if(sers.length !== Math.min(amt, 300)) return false;
                    for(const s of sers){
                        if(!s.production_date) return false;
                    }
                }
                // Validate serials for KG/M — required, sum must equal split amount
                if(item.unit !== 'ST'){
                    const sers = this.serials[key] || [];
                    if(sers.length === 0) return false;
                    let sum = 0;
                    for(const s of sers){
                        const sq = parseFloat(s.quantity);
                        if(isNaN(sq) || sq <= 0) return false;
                        sum += sq;
                    }
                    if(Math.abs(sum - amt) > 0.01) return false;
                }
            }
            // Validate at_once mode (all items with serials)
            if(this.atOnceMode){
                for(const item of this.items){
                    const unit = item.unit || 'ST';
                    if(unit === 'ST') continue; // ST validated by checkbox
                    const qty = parseFloat(item.quantity) || 0;
                    if(qty <= 0) continue;
                    const sers = this.serials[item.id] || [];
                    if(sers.length === 0) return false;
                    let sum = 0;
                    for(const s of sers){
                        const sq = parseFloat(s.quantity);
                        if(isNaN(sq) || sq <= 0) return false;
                        sum += sq;
                    }
                    if(Math.abs(sum - qty) > 0.01) return false;
                }
            }
            return true;
        }
    },
    emits: ['select', 'serials', 'item-files'],
    mounted(){
        this.resolveAndBuild();
    },
    updated(){
        this.initFlatpickr();
    },
    beforeUnmount(){
        this.destroyFlatpickr();
        // revoke object URLs for uploaded image previews
        for(const k in this.itemImages){
            (this.itemImages[k]||[]).forEach(f=>{ if(f.previewUrl) try{ URL.revokeObjectURL(f.previewUrl);}catch(e){} });
        }
    },
    watch: {
        atOnceMode(){
            if(this.atOnceMode){
                this.ensureAtOnceSerials();
            }
        },
        highlightQnid(newVal){
            // Remove old highlight from previous element
            if(this._lastHighlightEl){
                this._lastHighlightEl.style.borderColor = '';
                this._lastHighlightEl = null;
            }
            if(!newVal) return;
            this.$nextTick(()=>{
                const root = this.$refs?.rootEl || this.$el;
                if(!root || typeof root.querySelector !== 'function') return;
                const wrap = root.querySelector(`[data-item-qnid="${newVal}"]`);
                if(wrap){
                    const row = wrap.querySelector('.oic-row');
                    if(row){
                        row.style.borderColor = '#ef4444';
                        this._lastHighlightEl = row;
                        row.scrollIntoView({ behavior:'smooth', block:'center' });
                    }
                }
            });
        },
        selectable(){
            if(!this.selectable){
                this.selected = {};
                this.splitAmounts = {};
                this.serials = {};
                this.serialEnabled = {};
                this.notifySelect();
            }
        },
        splitAmounts: {
            deep: true,
            handler(){
                if(!this._syncingSplit) this.rebuildSerials();
                this.notifySelect();
            }
        },
        serials: {
            deep: true,
            handler(){
                clearTimeout(this._serialsDebounce);
                this._serialsDebounce = setTimeout(()=> {
                    this.syncSplitFromSerials();
                    this.notifySelect();
                }, 150);
            }
        }
    },
    methods:{
        needsSerials(item){
            if(!item) return false;
            const unit = item.unit || 'ST';
            if(unit !== 'ST') return true; // KG/M always need serials
            // ST: only if checkbox enabled
            return !!this.serialEnabled[item.id];
        },
        needsSerialsAtOnce(item){
            if(!item || !this.atOnceMode) return false;
            const unit = item.unit || 'ST';
            const qty = parseFloat(item.quantity) || 0;
            if(qty <= 0) return false;
            if(unit === 'ST' && qty >= 300) return false;
            return true;
        },
        ensureAtOnceSerials(){
            const defaultDate = this.parsedOrderDate || '';
            for(const item of this.items){
                if(!this.needsSerialsAtOnce(item)) continue;
                const key = item.id;
                const unit = item.unit || 'ST';
                if(unit === 'ST') continue; // ST handled by checkbox
                // KG/M: create first row if empty
                if(!this.serials[key] || this.serials[key].length === 0){
                    const qty = parseFloat(item.quantity) || 0;
                    this.serials[key] = [{ serial_no: '-', production_date: defaultDate, production_date_display: '', quantity: qty, unit }];
                }
            }
            this.serials = { ...this.serials };
        },
        toggleSerials(item){
            const key = item.id;
            this.serialEnabled[key] = !this.serialEnabled[key];
            if(this.serialEnabled[key]){
                // Generate rows when enabling
                this.rebuildSerialsForItem(key, item);
            } else {
                this.serials[key] = [];
            }
            this.serialEnabled = { ...this.serialEnabled };
            this.serials = { ...this.serials };
            this.notifySelect();
        },
        rebuildSerialsForItem(key, item){
            const unit = item.unit || 'ST';
            // In at_once mode, use full item quantity; in partial mode, use split amount
            const amt = this.atOnceMode
                ? (parseFloat(item.quantity) || 0)
                : (parseFloat(this.splitAmounts[key]) || 0);
            if(amt <= 0){ this.serials[key] = []; return; }
            if(unit === 'ST'){
                const existing = this.serials[key] || [];
                const newSerials = [];
                for(let i = 0; i < Math.min(amt, 300); i++){
                    newSerials.push({
                        serial_no: existing[i]?.serial_no || '',
                        production_date: existing[i]?.production_date || '',
                        production_date_display: existing[i]?.production_date_display || '',
                        quantity: 1,
                        unit: 'ST'
                    });
                }
                this.serials[key] = newSerials;
            } else {
                const existing = this.serials[key] || [];
                if(existing.length === 0){
                    const defaultDate = this.parsedOrderDate || '';
                    this.serials[key] = [{ serial_no: '-', production_date: defaultDate, production_date_display: '', quantity: amt, unit }];
                } else {
                    existing.forEach(s => s.unit = unit);
                    this.serials[key] = [...existing];
                }
            }
        },
        rebuildSerials(){
            const now = Date.now();
            for(const key in this.selected){
                if(!this.selected[key]) continue;
                const item = this.itemsMap.get(key);
                if(!item) continue;
                // Skip items that just had Excel uploaded (within 1000ms)
                if(this._excelUploadTime && this._excelUploadTime[key] && (now - this._excelUploadTime[key]) < 1000) continue;
                const unit = item.unit || 'ST';
                const amt = parseFloat(this.splitAmounts[key]) || 0;
                if(amt <= 0){ this.serials[key] = []; this.serialEnabled[key] = false; continue; }
                // ST >= 300: no serials allowed, clear everything
                if(unit === 'ST' && amt >= 300){
                    this.serials[key] = [];
                    this.serialEnabled[key] = false;
                    continue;
                }
                if(unit !== 'ST'){
                    // KG/M always need serials
                    this.rebuildSerialsForItem(key, item);
                } else if(this.serialEnabled[key]){
                    // ST < 300 with checkbox on
                    this.rebuildSerialsForItem(key, item);
                } else {
                    this.serials[key] = [];
                }
            }
            // Clean up in single pass
            for(const key in this.serials){
                if(!this.selected[key]) {
                    delete this.serials[key];
                    delete this.serialEnabled[key];
                    delete this.serialCollapsed[key];
                }
            }
        },
        addSerialRow(item){
            const key = item.id;
            const unit = item.unit || 'ST';
            if(!this.serials[key]) this.serials[key] = [];
            this.serials[key].push({
                serial_no: unit === 'ST' ? '' : '-',
                production_date: unit === 'ST' ? '' : (this.parsedOrderDate || ''),
                production_date_display: '',
                quantity: unit === 'ST' ? 1 : 0,
                unit: unit
            });
            this.serials = { ...this.serials };
            this.syncSplitFromSerials();
        },
        removeSerialRow(item, idx){
            const key = item.id;
            if(this.serials[key]) this.serials[key].splice(idx, 1);
            this.serials = { ...this.serials };
            this.syncSplitFromSerials();
        },
        syncSplitFromSerials(){
            if(!this.selectable) return;
            for(const key in this.selected){
                if(!this.selected[key]) continue;
                const item = this.itemsMap.get(key);
                if(!item) continue;
                if(item.unit === 'ST') continue;
                const sers = this.serials[key] || [];
                let sum = 0;
                for(const s of sers){
                    const q = parseFloat(s.quantity);
                    if(!isNaN(q) && q > 0) sum += q;
                }
                if(sum > 0){
                    this._syncingSplit = true;
                    this.splitAmounts[key] = Math.round(sum * 100) / 100;
                    this._syncingSplit = false;
                }
            }
        },
        // Initialize flatpickr month picker on new .oic-fp-month elements only
        initFlatpickr(){
            const root = this.$refs?.rootEl || this.$el;
            if(!root || typeof root.querySelectorAll !== 'function') return;
            this.$nextTick(()=>{
                const el = (this.$refs?.rootEl && typeof this.$refs.rootEl.querySelectorAll === 'function') ? this.$refs.rootEl : (this.$el && typeof this.$el.querySelectorAll === 'function' ? this.$el : root);
                const inputs = el.querySelectorAll('.oic-fp-month:not(.fp-initialized)');
                inputs.forEach(input => {
                    const serialKey = input.dataset.serialKey;
                    if(!serialKey) return;
                    const parts = serialKey.split('-');
                    const idx = parseInt(parts.pop());
                    const itemId = parts.join('-');
                    const ser = this.serials[itemId]?.[idx];
                    if(!ser) return;

                    const fp = flatpickr(input, {
                        plugins: [ new monthSelectPlugin({ shorthand: true, dateFormat: 'm/Y', altFormat: 'F Y' }) ],
                        defaultDate: ser.production_date || null,
                        onChange: (selectedDates, dateStr) => {
                            if(!dateStr){ ser.production_date = ''; ser.production_date_display = ''; return; }
                            const p = dateStr.split('/');
                            if(p.length === 2){
                                ser.production_date = p[1] + '-' + p[0] + '-01';
                                ser.production_date_display = p[0] + '.' + p[1];
                            }
                        }
                    });
                    this.fpInstances.push(fp);
                    input.classList.add('fp-initialized');
                });
            });
        },
        destroyFlatpickr(){
            this.fpInstances.forEach(fp => { try{ fp.destroy(); }catch(e){} });
            this.fpInstances = [];
        },
        getStep(unit){ return unit === 'ST' ? 1 : 0.01; },
        getRemaining(item){
            const qty = parseFloat(item.quantity) || 0;
            const amt = parseFloat(this.splitAmounts[item.id]) || 0;
            return Math.max(0, qty - amt);
        },
        quantityChanged(row){
            return row.original_quantity && parseFloat(row.original_quantity) !== parseFloat(row.quantity);
        },
        getSerialSum(item){
            const sers = this.serials[item.id] || [];
            return sers.reduce((sum, s) => sum + (parseFloat(s.quantity) || 0), 0);
        },
        hasSerialData(item){
            const sers = this.serials[item.id] || [];
            return sers.length > 0 && sers.some(s => s.production_date);
        },
        getSerialData(item){
            const sers = this.serials[item.id] || [];
            const total = sers.reduce((sum, s) => sum + (parseFloat(s.quantity) || 0), 0);
            return { count: sers.length, total: total % 1 === 0 ? total : total.toFixed(2) };
        },
        toggleCollapse(item){
            const key = item.id;
            this.serialCollapsed[key] = !this.serialCollapsed[key];
            this.serialCollapsed = { ...this.serialCollapsed };
        },
        isCollapsed(item){
            return !!this.serialCollapsed[item.id];
        },
        isViewCollapsed(item){
            // Default collapsed
            return this.serialViewCollapsed[item.id] !== false;
        },
        toggleViewCollapse(item){
            const key = item.id;
            this.serialViewCollapsed[key] = !this.isViewCollapsed(item);
            this.serialViewCollapsed = { ...this.serialViewCollapsed };
        },
        formatSerialDate(val){
            if(!val) return '-';
            const parts = val.split('-');
            if(parts.length >= 2) return parts[1] + '.' + parts[0];
            if(val.includes('.')) return val;
            return val;
        },
        triggerExcelUpload(item){
            this._excelUploadSplitAmt = parseFloat(this.splitAmounts[item.id]) || 0;
            const input = this.excelFileInputs[item.id];
            if(input){ input.value = ''; input.click(); }
        },
        downloadExcelTemplate(){
            const ws_data = [
                ['SRLCODE', 'SRLDATE', 'QUANTITY'],
                ['SRL01', '2024-01-15', 50],
                ['SRL02', '2024-01-15', 50],
                ['SRL03', '2024-02-01', 100],
            ];
            const ws = XLSX.utils.aoa_to_sheet(ws_data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Seri Listesi');
            XLSX.writeFile(wb, 'seri_numarasi_sablonu.xlsx');
        },
        onExcelFileSelected(item, event){
            const file = event.target.files[0];
            if(!file) return;
            this.parseSerialExcel(item, file);
        },
        normalizeExcelDate(val){
            if(val === null || val === undefined) return '';
            if(typeof val === 'number'){
                const d = new Date((val - 25569) * 86400000);
                return d.getUTCFullYear() + '-' + String(d.getUTCMonth() + 1).padStart(2, '0') + '-01';
            }
            const str = String(val).replace(/\//g, '-');
            const match = str.match(/^(\d{4})-(\d{2})/);
            if(match) return match[1] + '-' + match[2] + '-01';
            return '';
        },
        async parseSerialExcel(item, file){
            try{
                const data = await file.arrayBuffer();
                const wb = XLSX.read(data, { type: 'array' });
                const ws = wb.Sheets[wb.SheetNames[0]];
                const rows = XLSX.utils.sheet_to_json(ws);
                if(!rows.length){
                    this.plib.toast(Swal, 'info', 'Excel dosyası boş');
                    return;
                }
                const unit = item.unit || 'ST';
                const parsed = [];
                for(const r of rows){
                    const qty = unit === 'ST' ? 1 : (parseFloat(r['QUANTITY']) || 0);
                    if(qty <= 0 && unit !== 'ST') continue;
                    parsed.push({
                        serial_no: r['SRLCODE'] || '-',
                        production_date: this.normalizeExcelDate(r['SRLDATE']),
                        production_date_display: '',
                        quantity: qty,
                        unit: unit
                    });
                }
                if(!parsed.length){
                    this.plib.toast(Swal, 'info', 'Geçerli seri bulunamadı');
                    return;
                }
                // ST: warn if row count doesn't match split amount
                if(unit === 'ST'){
                    let amt = this._excelUploadSplitAmt || 0;
                    if(amt === 0 && this.atOnceMode){
                        amt = parseFloat(item.quantity) || 0;
                    }
                    console.log('[ExcelSerial] unit:', unit, 'amt:', amt, 'parsed:', parsed.length, '_excelUploadSplitAmt:', this._excelUploadSplitAmt, 'atOnceMode:', this.atOnceMode);
                    if(amt > 0 && parsed.length !== Math.min(amt, 300)){
                        const confirm = await Swal.fire({
                            title: 'Satır Uyuşmazlığı',
                            html: 'Excel <b>' + parsed.length + '</b> satır içeriyor, ancak girilen miktar <b>' + amt + ' ST</b>.<br>Bölme miktarı Excel satır sayısına eşitlenecek (<b>' + parsed.length + ' ST</b>).',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Evet, Yükle',
                            cancelButtonText: 'İptal',
                        });
                        if(!confirm.isConfirmed) return;
                        // Sync split amount with Excel row count
                        this.splitAmounts[item.id] = parsed.length;
                        this.splitAmounts = { ...this.splitAmounts };
                    }
                }
                // Mark upload time to prevent rebuildSerials from overwriting
                if(!this._excelUploadTime) this._excelUploadTime = {};
                this._excelUploadTime[item.id] = Date.now();
                // Ensure serialEnabled for ST
                if(unit === 'ST'){
                    this.serialEnabled[item.id] = true;
                    this.serialEnabled = { ...this.serialEnabled };
                }
                // Ensure serial area is expanded (not collapsed)
                this.serialCollapsed[item.id] = false;
                this.serialCollapsed = { ...this.serialCollapsed };
                // Set serials — triggers rebuildSerials via splitAmounts watcher,
                // but _excelUploadTime flag prevents overwrite for 1000ms
                this.serials[item.id] = parsed;
                this.serials = { ...this.serials };
                // For KG/M: sync split amount from Excel serial sum
                if(unit !== 'ST'){
                    this.syncSplitFromSerials();
                }
                this.plib.toast(Swal, 'success', parsed.length + ' seri yüklendi');
            }catch(e){
                this.plib.toast(Swal, 'error', 'Excel okunamadı');
            }
        },
        getSelected(){
            const result = [];
            // Partial mode: emit selected items with split amounts + serials
            for(const key in this.selected){
                if(!this.selected[key]) continue;
                const amt = parseFloat(this.splitAmounts[key]) || 0;
                const item = this.itemsMap.get(key);
                const itemSerials = (this.serials[key] || []).map(s => ({
                    serial_no: s.serial_no || '-',
                    production_date: s.production_date || '',
                    quantity: parseFloat(s.quantity) || 0,
                    unit: s.unit || (item?.unit || 'ST')
                }));
                result.push({ qnid: key, amount: amt, serials: itemSerials });
            }
            return result;
        },
        // Emit serials for ALL items (used in at_once mode)
        getAllItemSerials(){
            const result = [];
            for(const item of this.items){
                const sers = this.serials[item.id] || [];
                if(sers.length === 0) continue;
                const itemSerials = sers.map(s => ({
                    serial_no: s.serial_no || '-',
                    production_date: s.production_date || '',
                    quantity: parseFloat(s.quantity) || 0,
                    unit: s.unit || item.unit || 'ST'
                }));
                result.push({ qnid: item.id, quantity: parseFloat(item.quantity) || 0, unit: item.unit || 'ST', serials: itemSerials });
            }
            return result;
        },
        notifySelect(){
            this.$emit('select', this.getSelected());
            this.$emit('serials', this.getAllItemSerials());
        },
        onSplitInput(row, event){
            const val = event.target.value;
            const numVal = parseFloat(val);
            const qty = parseFloat(row.quantity) || 0;
            if(isNaN(numVal) || numVal < 1){
                this.splitAmounts[row.id] = '';
            } else if(numVal > qty){
                this.splitAmounts[row.id] = qty;
            } else {
                this.splitAmounts[row.id] = numVal;
            }
        },
        onSplitBlur(row, event){
            const unit = row.unit || 'ST';
            let val = parseFloat(this.splitAmounts[row.id]) || 0;
            if(unit === 'ST') val = Math.round(val);
            else val = Math.round(val * 100) / 100;
            const qty = parseFloat(row.quantity) || 0;
            if(val > qty) val = qty;
            if(val < 1) val = 1;
            this.splitAmounts[row.id] = val;
            event.target.value = val;
        },
        toggleCard(row){
            if(!this.selectable){ this.showDetail(row); return; }
            const qnid = row.id;
            const wasSelected = !!this.selected[qnid];
            this.selected[qnid] = !wasSelected;
            if(!wasSelected){
                this.splitAmounts[qnid] = parseFloat(row.quantity) || 0;
            } else {
                delete this.splitAmounts[qnid];
                delete this.serials[qnid];
            }
            this.notifySelect();
        },
        isSelected(row){ return !!this.selected[row.id]; },
        showDetail(row){
            const qty = parseFloat(row.quantity) || 0;
            const originalQty = row.original_quantity ? parseFloat(row.original_quantity) : null;
            const splitAmt = row.split_amount ? parseFloat(row.split_amount) : null;
            const splitFrom = row.split_from_qnid || null;

            let historyHtml = '';
            if(originalQty !== null && originalQty !== qty){
                historyHtml = `<div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#fef2f2,#fecaca);display:flex;align-items:center;justify-content:center;"><i class="ki-outline ki-arrow-right-left" style="color:#dc2626;"></i></div>
                    <div><div style="font-size:0.72rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Önceki Miktar</div><div style="font-weight:700;color:#94a3b8;font-size:0.95rem;text-decoration:line-through;">${originalQty} <span style="color:#64748b;font-weight:500;font-size:0.82rem;">${row.unit||''}</span></div></div>
                </div>`;
            }
            if(splitAmt !== null){
                historyHtml += `<div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#fef3c7,#fde68a);display:flex;align-items:center;justify-content:center;"><i class="ki-outline ki-slice" style="color:#d97706;"></i></div>
                    <div><div style="font-size:0.72rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Bölünmüş Miktar</div><div style="font-weight:700;color:#d97706;font-size:0.95rem;">${splitAmt} <span style="color:#64748b;font-weight:500;font-size:0.82rem;">${row.unit||''}</span></div></div>
                </div>`;
            }
            if(splitFrom){
                historyHtml += `<div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#f0f9ff,#dbeafe);display:flex;align-items:center;justify-content:center;"><i class="ki-outline ki-link" style="color:#3b82f6;"></i></div>
                    <div><div style="font-size:0.72rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Kaynak Kalem</div><div style="font-weight:600;color:#0f172a;font-size:0.95rem;word-break:break-all;">${splitFrom}</div></div>
                </div>`;
            }

            // Serial data - removed from modal, shown in collapsible row section

            Swal.fire({
                title: row.title || 'Kalem',
                html: `<div style="text-align:left;display:flex;flex-direction:column;gap:12px;padding:4px 0;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#eff6ff,#dbeafe);display:flex;align-items:center;justify-content:center;"><i class="ki-outline ki-box" style="color:#3b82f6;"></i></div>
                        <div><div style="font-size:0.72rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Ürün Kodu</div><div style="font-weight:700;color:#0f172a;font-size:0.95rem;word-break:break-all;">${row.prod_code||'-'}</div></div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);display:flex;align-items:center;justify-content:center;"><i class="ki-outline ki-text" style="color:#22c55e;"></i></div>
                        <div><div style="font-size:0.72rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Ürün Adı</div><div style="font-weight:600;color:#0f172a;font-size:0.95rem;">${row.title||'-'}</div></div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#fefce8,#fef9c3);display:flex;align-items:center;justify-content:center;"><i class="ki-outline ki-numbering" style="color:#eab308;"></i></div>
                        <div><div style="font-size:0.72rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Mevcut Miktar</div><div style="font-weight:700;color:#0f172a;font-size:0.95rem;">${row.quantity||'-'} <span style="color:#64748b;font-weight:500;font-size:0.82rem;">${row.unit||''}</span></div></div>
                    </div>
                    ${historyHtml}
                </div>`,
                showCloseButton: true,
                showConfirmButton: false,
                customClass: { popup: 'swal2-item-detail' }
            });
        },
        async resolveAndBuild(){
            let numericId = this.orderNumericId;
            if(!numericId && this.orderId){
                try{
                    const rsp = await this.plib.request({url:'/api/v1/document/'+this.orderId, method:'GET'}, null);
                    numericId = rsp?.data?.document?.id || this.orderNumericId;
                }catch(e){ numericId = this.orderNumericId; }
            }
            this.resolvedParentId = numericId || null;
            if(!numericId){ this.loading = false; return; }
            await this.fetchItems(numericId);
        },
        async fetchItems(parentId){
            this.loading = true;
            this.error = null;
            try{
                const payload = {
                    filter: [
                        { key:'form-type', type:'=', value:'op-doc-order-item-form' },
                        { key:'type', type:'=', value:'op-doc-order-item' },
                        { key:'parent_id', type:'=', value: String(parentId) },
                    ],
                    scale: { page: 1, limit: 100 },
                    order: { key: 'id', style: 'asc' }
                };
                const fd = new FormData();
                fd.append('tableReq', JSON.stringify(payload));
                const rsp = await this.plib.request({url:'/api/v1/table/documents', method:'POST'}, null, fd);
                const raw = rsp?.data || rsp?.data?.data || [];
                const rows = Array.isArray(raw) ? raw : (rsp?.data || []);
                const list = rsp?.data ? rsp.data : rows;
                const actualRows = Array.isArray(list) ? list : (list?.data || []);
                this.items = (Array.isArray(actualRows) ? actualRows : []).map(r=>{
                    try{ JSON.parse(r.main_attr||'[]').forEach(el=> r[el['Key']]=el['Value']); }catch(e){}
                    return {
                        id: r.id,
                        id_qnid: r.id,
                        raw_id: r.main_id || r.id,
                        prod_code: r['prod_code']||'-',
                        title: r['title']||'-',
                        quantity: r['quantity']||'-',
                        unit: r['unit']||'',
                        original_quantity: r['original_quantity']||null,
                        split_amount: r['split_amount']||null,
                        split_from_qnid: r['split_from_qnid']||null,
                        has_serials: r['has_serials']||'0',
                        serials: [],
                        _raw: r
                    };
                });
                // Fetch serials for items that have them
                await this.fetchSerialsForItems();
                // Fetch existing item files
                await this.fetchItemFiles();
            }catch(e){
                this.error = 'Kalemler yüklenemedi';
            }finally{
                this.loading = false;
                if(this.atOnceMode) this.ensureAtOnceSerials();
            }
        },
        async fetchSerialsForItems(){
            for(const item of this.items){
                if(item.has_serials !== '1') continue;
                try{
                    const payload = {
                        filter: [
                            { key:'form-type', type:'=', value:'op-doc-order-serial-form' },
                            { key:'type', type:'=', value:'op-doc-order-serial' },
                            { key:'parent_id', type:'=', value: String(item.raw_id) },
                        ],
                        scale: { page: 1, limit: 300 },
                        order: { key: 'id', style: 'asc' }
                    };
                    const fd = new FormData();
                    fd.append('tableReq', JSON.stringify(payload));
                    const rsp = await this.plib.request({url:'/api/v1/table/documents', method:'POST'}, null, fd);
                    const raw = rsp?.data || rsp?.data?.data || [];
                    const rows = Array.isArray(raw) ? raw : (rsp?.data || []);
                    const list = rsp?.data ? rsp.data : rows;
                    const actualRows = Array.isArray(list) ? list : (list?.data || []);
                    item.serials = (Array.isArray(actualRows) ? actualRows : []).map(r=>{
                        try{ JSON.parse(r.main_attr||'[]').forEach(el=> r[el['Key']]=el['Value']); }catch(e){}
                        return {
                            serial_no: r['serial_no']||'-',
                            production_date: r['production_date']||'',
                            quantity: r['quantity']||'-',
                            unit: r['unit']||'',
                        };
                    });
                }catch(e){
                    console.error('fetchSerials failed for item', item.id, e);
                }
            }
        },
        // --- Item file upload methods ---
        toggleItemFilesCollapse(item){
            const key = item.id;
            // Default collapsed = true; first toggle must flip to false (expanded)
            if(this.itemFilesCollapsed[key] === undefined) this.itemFilesCollapsed[key] = true;
            this.itemFilesCollapsed[key] = !this.itemFilesCollapsed[key];
            this.itemFilesCollapsed = { ...this.itemFilesCollapsed };
        },
        isItemFilesCollapsed(item){
            return this.itemFilesCollapsed[item.id] !== false;
        },
        isTestRejected(itemId){
            const f = this.existingTestFiles[itemId]?.[0];
            return !!(f && f.last_status && f.last_status.op_key === 'doc_file_rejected');
        },
        triggerTestUpload(item){
            if(this.readonly && !this.isTestRejected(item.id)) return;
            const input = this.testFileInputs[item.id];
            if(input){ input.value = ''; input.click(); }
        },
        triggerImageUpload(item){
            if(this.readonly) return;
            const input = this.imageFileInputs[item.id];
            if(input){ input.value = ''; input.click(); }
        },
        async onTestFileSelected(item, event){
            if(this.readonly && !this.isTestRejected(item.id)) return;
            const file = event.target.files[0];
            if(!file) return;
            const ext = file.name.split('.').pop().toLowerCase();
            if(!['pdf','xls','xlsx','jpg','jpeg','png'].includes(ext)){
                this.plib.toast(Swal, 'info', 'Sadece pdf, xls, xlsx, jpg, jpeg, png dosyaları yükleyebilirsiniz.');
                return;
            }
            if(file.size > 42 * 1024 * 1024){
                this.plib.toast(Swal, 'info', 'Dosya boyutu 42MB\'dan küçük olmalıdır.');
                return;
            }
            this.$set ? this.$set(this.itemTestFiles, item.id, { uploading: true, file, reference: null }) : (this.itemTestFiles[item.id] = { uploading: true, file, reference: null });
            this.itemTestFiles = { ...this.itemTestFiles };
            try {
                const ref = await this.uploadTempFile(file);
                this.itemTestFiles[item.id] = { uploading: false, file, reference: ref };
                this.itemTestFiles = { ...this.itemTestFiles };
                this.emitItemFiles();
            } catch(e) {
                this.itemTestFiles[item.id] = { uploading: false, file: null, reference: null };
                this.itemTestFiles = { ...this.itemTestFiles };
                this.plib.toast(Swal, 'error', 'Dosya yüklenemedi');
            }
        },
        isImageFile(file){
            if(!file) return false;
            const ext = (file.name||'').split('.').pop().toLowerCase();
            return ['jpg','jpeg','png','webp','gif'].includes(ext) || (file.type||'').startsWith('image/');
        },
        async onImageFileSelected(item, event){
            if(this.readonly) return;
            const file = event.target.files[0];
            if(!file) return;
            const ext = file.name.split('.').pop().toLowerCase();
            if(!['jpg','jpeg','png','pdf'].includes(ext)){
                this.plib.toast(Swal, 'info', 'Sadece jpg, jpeg, png, pdf dosyaları yükleyebilirsiniz.');
                return;
            }
            if(file.size > 42 * 1024 * 1024){
                this.plib.toast(Swal, 'info', 'Dosya boyutu 42MB\'dan küçük olmalıdır.');
                return;
            }
            if(!this.itemImages[item.id]) this.itemImages[item.id] = [];
            const uploadId = Date.now() + '-' + Math.random().toString(36).substr(2, 5);
            const previewUrl = URL.createObjectURL(file);
            this.itemImages[item.id].push({ uploadId, uploading: true, file, reference: null, previewUrl });
            this.itemImages = { ...this.itemImages };
            try {
                const ref = await this.uploadTempFile(file);
                const entry = this.itemImages[item.id].find(f => f.uploadId === uploadId);
                if(entry){
                    entry.uploading = false;
                    entry.reference = ref;
                }
                this.itemImages = { ...this.itemImages };
                this.emitItemFiles();
            } catch(e) {
                const failed = this.itemImages[item.id].find(f => f.uploadId === uploadId);
                if(failed?.previewUrl) try{ URL.revokeObjectURL(failed.previewUrl);}catch(e){}
                const idx = this.itemImages[item.id].findIndex(f => f.uploadId === uploadId);
                if(idx !== -1) this.itemImages[item.id].splice(idx, 1);
                this.itemImages = { ...this.itemImages };
                this.plib.toast(Swal, 'error', 'Dosya yüklenemedi');
            }
        },
        removeTestFile(itemId){
            if(this.readonly && !this.isTestRejected(itemId)) return;
            this.itemTestFiles[itemId] = { uploading: false, file: null, reference: null };
            this.itemTestFiles = { ...this.itemTestFiles };
            this.emitItemFiles();
        },
        removeImageFile(itemId, idx){
            if(this.readonly) return;
            const removed = this.itemImages[itemId]?.[idx];
            if(removed?.previewUrl) URL.revokeObjectURL(removed.previewUrl);
            if(this.itemImages[itemId]) this.itemImages[itemId].splice(idx, 1);
            this.itemImages = { ...this.itemImages };
            this.emitItemFiles();
        },
        onThumbError(e){
            e.target.style.display = 'none';
            const parent = e.target.parentElement;
            if(parent && !parent.querySelector('.oic-thumb-fallback')){
                const fb = document.createElement('div');
                fb.className = 'oic-thumb-fallback';
                fb.innerHTML = '<i class="ki-outline ki-document" style="font-size:24px;color:#94a3b8"></i><span style="font-size:0.7rem;color:#64748b;margin-top:4px">PDF</span>';
                parent.appendChild(fb);
            }
        },
        removeExistingTestFile(itemId, file){
            if(this.readonly) return;
            this.existingTestFiles[itemId] = [];
            this.existingTestFiles = { ...this.existingTestFiles };
            if(file && file.id){
                if(!this._removedExistingFiles) this._removedExistingFiles = [];
                const connId = file.connId || this.items.find(i => i.id === itemId)?._fileConnId;
                const key = file.entity_tag || ('item_test_file**item_test_docs**' + connId);
                this._removedExistingFiles.push({ id: connId, key: key, fileId: file.id, connId: connId });
            }
            this.emitItemFiles();
        },
        removeExistingImageFile(itemId, idx){
            if(this.readonly) return;
            const file = this.existingImages[itemId]?.[idx];
            if(file && file.id){
                if(!this._removedExistingFiles) this._removedExistingFiles = [];
                const connId = file.connId || this.items.find(i => i.id === itemId)?._fileConnId;
                const key = file.entity_tag;
                if(key) this._removedExistingFiles.push({ id: connId, key: key, fileId: file.id, connId: connId });
            }
            if(this.existingImages[itemId]) this.existingImages[itemId].splice(idx, 1);
            this.existingImages = { ...this.existingImages };
            this.emitItemFiles();
        },
        previewExistingFile(file){
            if(!file?.qnid) return;
            const url = '/order-file/' + file.qnid;
            const tag = (file.entity_tag || '').toLowerCase();
            const isTestDoc = tag.includes('item_test_file');
            // name is encrypted (salt:iv:ct) for existing files — don't show gibberish
            let label = 'Dosya';
            const rawName = file.name || '';
            const looksDecrypted = rawName.includes('.') && !rawName.includes(':') && rawName.length < 100;
            if(looksDecrypted) label = rawName;
            else label = isTestDoc ? 'Test Dökümanı' : 'Ürün Görseli';
            // Test docs are PDFs/Excel — use iframe (imageUrl can't render PDF). Product images are images — use imageUrl when possible, iframe fallback for PDFs.
            if(isTestDoc){
                Swal.fire({
                    html: `<div style="font-weight:700;margin-bottom:10px;color:#0f172a;font-size:0.95rem;">${label}</div><iframe src="${url}" style="width:100%;height:70vh;border:1px solid #e2e8f0;border-radius:8px;background:#fff"></iframe><div style="margin-top:10px;font-size:0.85rem;color:#64748b;"><a href="${url}" target="_blank" style="color:#3b82f6;text-decoration:underline;">Yeni pencerede aç / İndir</a></div>`,
                    showCloseButton: true,
                    showConfirmButton: false,
                    width: 900,
                    customClass: { popup: 'oic-preview-popup' }
                });
            } else {
                // product image — images and occasional pdf; iframe handles both reliably
                Swal.fire({
                    html: `<div style="font-weight:700;margin-bottom:10px;color:#0f172a;font-size:0.95rem;">${label}</div><iframe src="${url}" style="width:100%;height:70vh;border:1px solid #e2e8f0;border-radius:8px;background:#fff"></iframe><div style="margin-top:10px;font-size:0.85rem;color:#64748b;"><a href="${url}" target="_blank" style="color:#3b82f6;text-decoration:underline;">Yeni pencerede aç / İndir</a></div>`,
                    showCloseButton: true,
                    showConfirmButton: false,
                    width: 900,
                    customClass: { popup: 'oic-preview-popup' }
                });
            }
        },
        previewLocalImage(img){
            if(!img?.file) return;
            const name = (img.file.name || '').toLowerCase();
            const isPdf = /\.pdf$/i.test(name);
            const isExcel = /\.(xls|xlsx)$/i.test(name);
            const url = URL.createObjectURL(img.file);
            if(isPdf){
                Swal.fire({
                    html: `<div style="font-weight:700;margin-bottom:10px;color:#0f172a;">${img.file.name}</div><iframe src="${url}" style="width:100%;height:70vh;border:1px solid #e2e8f0;border-radius:8px;background:#fff"></iframe>`,
                    showCloseButton: true,
                    showConfirmButton: false,
                    width: 900,
                    didClose: () => URL.revokeObjectURL(url)
                });
                return;
            }
            if(isExcel){
                window.open(url, '_blank');
                setTimeout(()=>URL.revokeObjectURL(url), 1000);
                return;
            }
            Swal.fire({
                imageUrl: url,
                imageAlt: img.file.name,
                showCloseButton: true,
                showConfirmButton: false,
                width: 900,
                html: `<div style="margin-top:8px;font-size:0.85rem;color:#64748b;">${img.file.name}</div>`,
                didClose: () => URL.revokeObjectURL(url)
            });
        },
        getGalleryItems(itemId){
            const existing = this.existingImages[itemId] || [];
            const uploaded = (this.itemImages[itemId] || []).filter(x=> !x.uploading);
            const items = [];
            existing.forEach(f=>{
                items.push({ src:'/order-file/'+f.qnid, thumb:'/order-file/'+f.qnid, name:'Ürün Görseli '+(items.length+1), isImage:true, downloadUrl:'/order-file/'+f.qnid, qnid:f.qnid });
            });
            uploaded.forEach(u=>{
                const isImg = this.isImageFile(u.file);
                const src = u.previewUrl || '';
                items.push({ src: src, thumb: src, name: u.file?.name||'Görsel', isImage: isImg, downloadUrl: src, isLocal:true, file:u.file, previewUrl:u.previewUrl });
            });
            return items;
        },
        openGallery(itemId, globalIdx){
            const items = this.getGalleryItems(itemId);
            if(!items.length) return;
            // Clamp idx
            let idx = globalIdx;
            if(idx<0) idx=0;
            if(idx>=items.length) idx=items.length-1;
            this.gallery.items = items;
            this.gallery.index = idx;
            this.gallery.visible = true;
            document.body.style.overflow = 'hidden';
            // keyboard nav
            this._galleryKeyHandler = (e)=>{
                if(!this.gallery.visible) return;
                if(e.key==='Escape') this.closeGallery();
                if(e.key==='ArrowLeft') this.galleryPrev();
                if(e.key==='ArrowRight') this.galleryNext();
            };
            window.addEventListener('keydown', this._galleryKeyHandler);
        },
        closeGallery(){
            this.gallery.visible = false;
            document.body.style.overflow = '';
            if(this._galleryKeyHandler) window.removeEventListener('keydown', this._galleryKeyHandler);
        },
        galleryPrev(){ if(this.gallery.index>0) this.gallery.index--; },
        galleryNext(){ if(this.gallery.index < this.gallery.items.length-1) this.gallery.index++; },
        goToGallery(i){ this.gallery.index = i; },
        onGalleryImgError(e){
            e.target.style.display='none';
            const f = document.createElement('div');
            f.style.cssText='display:flex;align-items:center;justify-content:center;width:100%;height:100%;background:#f8fafc;color:#94a3b8;flex-direction:column;gap:6px';
            f.innerHTML='<i class="ki-outline ki-document" style="font-size:28px"></i><span style="font-size:0.75rem">Önizleme yok</span><a href="'+(this.currentGalleryItem?.downloadUrl||'#')+'" target="_blank" style="color:#6366f1;font-size:0.75rem;text-decoration:underline">Aç / İndir</a>';
            e.target.parentElement.appendChild(f);
        },
        uploadTempFile(file){
            const fd = new FormData();
            fd.append('file', file);
            return this.plib.request({ url:'/api/v1/temp-upload', method:'POST' }, null, fd)
                .then(r => {
                    if(r && r.success !== false) return r.reference || r.data?.reference || r;
                    throw new Error('Upload failed');
                });
        },
        emitItemFiles(){
            const connIds = {};
            for(const item of this.items){
                if(item._fileConnId) connIds[item.id] = item._fileConnId;
            }
            // Deep copy images arrays to avoid shared reference issues
            const imagesCopy = {};
            for(const k in this.itemImages){
                imagesCopy[k] = this.itemImages[k].map(f => ({...f}));
            }
            this.$emit('item-files', {
                testFiles: { ...this.itemTestFiles },
                images: imagesCopy,
                connIds,
                removedFiles: this._removedExistingFiles || []
            });
        },
        hasItemFiles(item){
            const tf = this.itemTestFiles[item.id];
            const hasNewTest = tf && (tf.reference || tf.file);
            const hasNewImages = (this.itemImages[item.id] || []).length > 0;
            const hasExistTest = (this.existingTestFiles[item.id] || []).length > 0;
            const hasExistImages = (this.existingImages[item.id] || []).length > 0;
            return hasNewTest || hasNewImages || hasExistTest || hasExistImages;
        },
        async fetchItemFiles(){
            for(const item of this.items){
                try{
                    const rsp = await this.plib.request({url:'/api/v1/document/'+item.id, method:'GET'}, null);
                    const formFormat = rsp?.data?.formFormat?.['op-doc-order-item-form'] || {};
                    for(const connId in formFormat){
                        const conn = formFormat[connId];
                        // Store the connId for saving files later (first conn is main item row)
                        if(!item._fileConnId) item._fileConnId = connId;
                        const seenIds = new Set();
                        // Gather file candidates from both conn.files (legacy) and conn.entities (current backend: JSON string in entities)
                        const candidates = {};
                        const files = conn?.files || {};
                        for(const tag in files){ candidates[tag] = files[tag]; }
                        const entities = conn?.entities || {};
                        for(const tag in entities){
                            if(!tag.includes('item_test_file') && !tag.includes('item_images_file')) continue;
                            if(candidates[tag]) continue; // already from files
                            const raw = entities[tag];
                            let parsed = null;
                            if(typeof raw === 'object' && raw !== null && raw.id){
                                parsed = raw;
                            } else if(typeof raw === 'string'){
                                try{ parsed = JSON.parse(raw); } catch(e){ continue; }
                            }
                            if(parsed && parsed.id) candidates[tag] = parsed;
                        }
                        for(const tag in candidates){
                            const fileData = candidates[tag];
                            if(!fileData || !fileData.id) continue;
                            if(seenIds.has(fileData.id)) continue;
                            seenIds.add(fileData.id);
                            const isTest = tag.includes('item_test_file');
                            const isImage = tag.includes('item_images_file');
                            if(isTest){
                                if(!this.existingTestFiles[item.id]) this.existingTestFiles[item.id] = [];
                                this.existingTestFiles[item.id].push({
                                    id: fileData.id,
                                    qnid: fileData.qnid,
                                    name: fileData.name || fileData.description || 'Test Dosyası',
                                    status: fileData.status,
                                    last_status: fileData.last_status,
                                    entity_tag: tag,
                                    connId: connId
                                });
                            } else if(isImage){
                                if(!this.existingImages[item.id]) this.existingImages[item.id] = [];
                                this.existingImages[item.id].push({
                                    id: fileData.id,
                                    qnid: fileData.qnid,
                                    name: fileData.name || fileData.description || 'Görsel',
                                    status: fileData.status,
                                    last_status: fileData.last_status,
                                    entity_tag: tag,
                                    connId: connId
                                });
                            }
                        }
                    }
                }catch(e){
                    // silent — items may not have files yet
                }
            }
            this.existingTestFiles = { ...this.existingTestFiles };
            this.existingImages = { ...this.existingImages };
        }
    }
}
</script>
<template>
    <div class="order-item-card" ref="rootEl">
        <div class="order-item-header">
            <div class="order-item-header-left">
                <div class="order-item-icon">
                    <i class="ki-outline ki-bullet-list"></i>
                </div>
                <div>
                    <h4 class="order-item-title">Sipariş Kalemleri</h4>
                    <span class="order-item-subtitle" v-if="!loading">{{ items.length }} kalem</span>
                    <span class="order-item-subtitle" v-else>Yükleniyor…</span>
                    <span class="order-item-subtitle" v-if="selectable && selectedCount" style="color:#3b82f6;font-weight:600;"> · {{ selectedCount }} seçili</span>
                </div>
            </div>
            <span class="order-item-badge" v-if="resolvedParentId">#{{ resolvedParentId }}</span>
        </div>
        <div class="order-item-body">
            <!-- Loading -->
            <div v-if="loading" class="oic-list oic-list--loading">
                <div v-for="n in 4" :key="n" class="oic-row oic-skeleton-row">
                    <div class="oic-skeleton-line w-20"></div>
                    <div class="oic-skeleton-line w-50"></div>
                    <div class="oic-skeleton-line w-20"></div>
                </div>
            </div>
            <div v-else-if="error" class="order-item-empty">
                <i class="ki-outline ki-cross-circle" style="font-size:28px;color:#f87171;"></i>
                <span style="color:#94a3b8;font-size:0.85rem;">{{ error }}</span>
            </div>
            <div v-else-if="!items.length" class="order-item-empty">
                <i class="ki-outline ki-question-circle" style="font-size:28px;color:#cbd5e1;"></i>
                <span style="color:#94a3b8;font-size:0.85rem;">Henüz kalem eklenmemiş</span>
            </div>
            <div v-else class="oic-list-wrap">
                <div class="oic-list">
                    <div v-for="(row, idx) in items" :key="row.id" class="oic-row-wrap" :data-item-qnid="row.id">
                        <!-- Main row -->
                        <div class="oic-row" :class="{ 'oic-selected': isSelected(row), 'oic-selectable': selectable }" @click="toggleCard(row)">
                            <div class="oic-idx">{{ idx + 1 }}</div>
                            <div v-if="selectable" class="oic-check" :class="{ checked: isSelected(row) }" @click.stop="toggleCard(row)">
                                <i class="ki-outline ki-check" v-if="isSelected(row)"></i>
                            </div>
                            <div class="oic-code">
                                <div class="oic-code-icon"><i class="ki-outline ki-box"></i></div>
                                <span class="oic-code-text" :title="row.prod_code">{{ row.prod_code }}</span>
                            </div>
                            <div class="oic-title" :title="row.title">{{ row.title }}</div>
                            <span class="oic-qty" v-if="quantityChanged(row)">
                                <strong style="text-decoration:line-through;color:#94a3b8;font-weight:500;">{{ row.original_quantity }}</strong>
                                <i class="ki-outline ki-arrow-right" style="font-size:11px;color:#94a3b8;margin:0 2px;"></i>
                                <strong>{{ row.quantity }}</strong>
                                <em v-if="row.unit">{{ row.unit }}</em>
                            </span>
                            <span class="oic-qty" v-else>
                                <strong>{{ row.quantity }}</strong>
                                <em v-if="row.unit">{{ row.unit }}</em>
                            </span>
                            <!-- Serial summary badge -->
                            <span v-if="(isSelected(row) || atOnceMode) && hasSerialData(row)" class="oic-serial-badge" @click.stop>
                                <i class="ki-outline ki-hash" style="font-size:11px;"></i>
                                {{ getSerialData(row).count }} seri, {{ getSerialData(row).total }} {{ row.unit }}
                            </span>
                            <button class="oic-eye" @click.stop="showDetail(row)" title="Detay">
                                <i class="ki-outline ki-eye"></i>
                            </button>
                        </div>

                        <!-- Item file uploads (Test Dökümanı + Ürün Görselleri) -->
                        <div class="oic-item-files" @click.stop>
                            <div class="oic-item-files-header" @click="toggleItemFilesCollapse(row)">
                                <i class="ki-outline ki-file" style="font-size:13px;color:#6366f1;"></i>
                                <span>Dosyalar (Test - Ürün Resmi)</span>
                                <span v-if="hasItemFiles(row)" class="oic-file-badge">var</span>
                                <span style="margin-left:auto;display:flex;align-items:center;gap:4px;font-size:0.78rem;color:#6366f1;">
                                    {{ isItemFilesCollapsed(row) ? 'Genişlet' : 'Daralt' }}
                                    <i :class="isItemFilesCollapsed(row) ? 'ki-outline ki-arrow-down' : 'ki-outline ki-arrow-up'" style="font-size:12px;"></i>
                                </span>
                            </div>
                            <div v-show="!isItemFilesCollapsed(row)" class="oic-item-files-body">
                                    <!-- Test Dökümanı (single slot — like Malzeme Kabul) -->
                                <div class="oic-item-file-section">
                                    <div class="oic-item-file-label">
                                        <i class="ki-outline ki-document" style="font-size:13px;color:#3b82f6;"></i>
                                        <span>Test Dökümanı</span>
                                        <span class="oic-item-file-hint">(Reddedilebilir / Onaylanabilir)</span>
                                    </div>
                                    <!-- Uploaded test file (new replacement — takes priority) -->
                                    <div v-if="itemTestFiles[row.id]?.reference" class="oic-item-file-list">
                                        <div class="oic-item-file-chip uploaded oic-previewable" @click.stop="previewLocalImage({file: itemTestFiles[row.id].file})" title="Önizlemek için tıklayın">
                                            <i class="ki-outline ki-document" style="font-size:13px;color:#059669;"></i>
                                            <span class="oic-item-file-name">{{ itemTestFiles[row.id]?.file?.name || 'Dosya' }}</span>
                                            <span class="oic-item-file-status status-uploaded">Yüklendi</span>
                                            <button class="oic-item-file-preview" @click.stop="previewLocalImage({file: itemTestFiles[row.id].file})" title="Önizle">
                                                <i class="ki-outline ki-eye" style="font-size:12px;"></i>
                                            </button>
                                            <button v-if="!readonly || isTestRejected(row.id)" class="oic-item-file-remove" @click.stop="removeTestFile(row.id)" title="Kaldır">
                                                <i class="ki-outline ki-cross" style="font-size:11px;"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Existing test file: accepted/pending — single slot, NOT removable (only insert on save, replace when rejected) -->
                                    <div v-else-if="existingTestFiles[row.id] && existingTestFiles[row.id].length > 0 && existingTestFiles[row.id][0].last_status?.op_key !== 'doc_file_rejected'" class="oic-item-file-list">
                                        <div class="oic-item-file-chip oic-previewable" @click.stop="previewExistingFile(existingTestFiles[row.id][0])" title="Önizlemek için tıklayın">
                                            <i class="ki-outline ki-document" style="font-size:13px;color:#3b82f6;"></i>
                                            <span class="oic-item-file-name" :title="existingTestFiles[row.id][0].name">{{ existingTestFiles[row.id][0].name }}</span>
                                            <span v-if="existingTestFiles[row.id][0].last_status" class="oic-item-file-status" :class="existingTestFiles[row.id][0].last_status.op_key === 'doc_file_accepted' ? 'status-accepted' : 'status-pending'">
                                                {{ existingTestFiles[row.id][0].last_status.op_key === 'doc_file_accepted' ? 'Onaylandı' : 'Beklemede' }}
                                            </span>
                                            <button class="oic-item-file-preview" @click.stop="previewExistingFile(existingTestFiles[row.id][0])" title="Önizle">
                                                <i class="ki-outline ki-eye" style="font-size:12px;"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Existing test file: rejected — show rejected status + upload button to replace -->
                                    <div v-else-if="existingTestFiles[row.id] && existingTestFiles[row.id].length > 0 && existingTestFiles[row.id][0].last_status?.op_key === 'doc_file_rejected'" class="oic-item-file-list" style="flex-direction:column;gap:8px;">
                                        <div class="oic-item-file-chip oic-previewable" @click.stop="previewExistingFile(existingTestFiles[row.id][0])" title="Önizlemek için tıklayın">
                                            <i class="ki-outline ki-document" style="font-size:13px;color:#3b82f6;"></i>
                                            <span class="oic-item-file-name" :title="existingTestFiles[row.id][0].name">{{ existingTestFiles[row.id][0].name }}</span>
                                            <span class="oic-item-file-status status-rejected">Reddedildi</span>
                                            <button class="oic-item-file-preview" @click.stop="previewExistingFile(existingTestFiles[row.id][0])" title="Önizle">
                                                <i class="ki-outline ki-eye" style="font-size:12px;"></i>
                                            </button>
                                        </div>
                                        <button class="oic-item-file-btn" @click.stop="triggerTestUpload(row)">
                                            <i class="ki-outline ki-file-up" style="font-size:14px;"></i>
                                            <span>Yeni Test Dökümanı Yükle</span>
                                        </button>
                                        <input type="file" :ref="el => { if(el) testFileInputs[row.id] = el }" accept=".pdf,.xls,.xlsx,.jpg,.jpeg,.png" style="display:none" @change="onTestFileSelected(row, $event)" />
                                    </div>
                                    <!-- No file at all — upload button -->
                                    <div v-else class="oic-item-file-upload">
                                        <button v-if="!readonly" class="oic-item-file-btn" @click.stop="triggerTestUpload(row)" :disabled="itemTestFiles[row.id]?.uploading">
                                            <i :class="itemTestFiles[row.id]?.uploading ? 'ki-outline ki-loading' : 'ki-outline ki-file-up'" style="font-size:14px;"></i>
                                            <span>{{ itemTestFiles[row.id]?.uploading ? 'Yükleniyor...' : 'Test Dökümanı Yükle' }}</span>
                                        </button>
                                        <span v-else style="font-size:0.78rem;color:#94a3b8;font-style:italic;padding:6px 0;display:inline-flex;align-items:center;gap:4px"><i class="ki-outline ki-lock-2" style="font-size:12px"></i> Sipariş kilitli — dosya eklenemez</span>
                                        <input type="file" :ref="el => { if(el) testFileInputs[row.id] = el }" accept=".pdf,.xls,.xlsx,.jpg,.jpeg,.png" style="display:none" @change="onTestFileSelected(row, $event)" />
                                    </div>
                                </div>

                                <!-- Ürün Görselleri -->
                                <div class="oic-item-file-section">
                                    <div class="oic-item-file-label">
                                        <i class="ki-outline ki-image" style="font-size:13px;color:#8b5cf6;"></i>
                                        <span>Ürün Görselleri</span>
                                        <span class="oic-item-file-hint">(Çoklu, onay/reddet yok)</span>
                                    </div>
                                    <!-- Existing images — thumbnail grid -->
                                    <div v-if="(existingImages[row.id] || []).length > 0" class="oic-image-grid">
                                        <div v-for="(ef, efi) in existingImages[row.id]" :key="'ei-'+ef.id" class="oic-image-thumb" @click.stop="openGallery(row.id, efi)" title="Önizlemek için tıklayın">
                                            <img :src="'/order-file/' + ef.qnid" :alt="ef.name" loading="lazy" @error="onThumbError" />
                                            <div class="oic-image-overlay oic-split">
                                                <button class="oic-split-half left" :class="{'full': readonly}" @click.stop="openGallery(row.id, efi)" :title="readonly ? 'Önizle (kilitli)' : 'Önizle'"><i class="ki-outline ki-eye" style="font-size:22px;"></i></button>
                                                <button v-if="!readonly" class="oic-split-half right" @click.stop="removeExistingImageFile(row.id, efi)" title="Sil"><i class="ki-outline ki-cross-circle" style="font-size:22px;"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Uploaded images — thumbnail grid -->
                                    <div v-if="(itemImages[row.id] || []).length > 0" class="oic-image-grid">
                                        <div v-for="(img, imgIdx) in itemImages[row.id]" :key="'img-'+imgIdx" class="oic-image-thumb uploaded" @click.stop="!img.uploading && openGallery(row.id, (existingImages[row.id]||[]).length + imgIdx)" :title="!img.uploading ? 'Önizlemek için tıklayın' : ''">
                                            <img v-if="!img.uploading && img.previewUrl" :src="img.previewUrl" :alt="img.file?.name" />
                                            <div v-else-if="img.uploading" class="oic-thumb-fallback" style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;background:#f8fafc">
                                                <i class="ki-outline ki-loading" style="font-size:20px;color:#8b5cf6;animation:spin 1s linear infinite"></i><span style="font-size:0.7rem;color:#64748b">Yükleniyor...</span>
                                            </div>
                                            <div v-else class="oic-thumb-fallback"><i class="ki-outline ki-document" style="font-size:22px;color:#94a3b8"></i><span style="font-size:0.68rem;color:#64748b;margin-top:2px;max-width:70px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ img.file?.name }}</span></div>
                                            <div class="oic-image-overlay oic-split" v-if="!img.uploading">
                                                <button class="oic-split-half left" :class="{'full': readonly}" @click.stop="openGallery(row.id, (existingImages[row.id]||[]).length + imgIdx)" :title="readonly ? 'Önizle (kilitli)' : 'Önizle'"><i class="ki-outline ki-eye" style="font-size:22px;"></i></button>
                                                <button v-if="!readonly" class="oic-split-half right" @click.stop="removeImageFile(row.id, imgIdx)" title="Sil"><i class="ki-outline ki-cross-circle" style="font-size:22px;"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Add image button -->
                                    <div class="oic-item-file-upload">
                                        <button v-if="!readonly" class="oic-item-file-btn oic-item-file-btn-image" @click.stop="triggerImageUpload(row)">
                                            <i class="ki-outline ki-plus" style="font-size:14px;"></i>
                                            <span>Görsel Ekle</span>
                                        </button>
                                        <span v-else style="font-size:0.78rem;color:#94a3b8;font-style:italic;padding:6px 0;display:inline-flex;align-items:center;gap:4px"><i class="ki-outline ki-lock-2" style="font-size:12px"></i> Sipariş kilitli — görsel eklenemez</span>
                                        <input type="file" :ref="el => { if(el) imageFileInputs[row.id] = el }" accept=".jpg,.jpeg,.png,.pdf" style="display:none" @change="onImageFileSelected(row, $event)" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Existing serial data (collapsible, read-only) -->
                        <div v-if="row.serials && row.serials.length > 0" class="oic-serial-view" @click.stop>
                            <div class="oic-serial-view-header" @click="toggleViewCollapse(row)">
                                <i class="ki-outline ki-hash" style="font-size:13px;color:#6366f1;"></i>
                                <span>Seri Numaraları ({{ row.serials.length }} adet)</span>
                                <span style="margin-left:auto;display:flex;align-items:center;gap:4px;font-size:0.78rem;color:#6366f1;">
                                    {{ isViewCollapsed(row) ? 'Genişlet' : 'Daralt' }}
                                    <i :class="isViewCollapsed(row) ? 'ki-outline ki-arrow-down' : 'ki-outline ki-arrow-up'" style="font-size:12px;"></i>
                                </span>
                            </div>
                            <div v-show="!isViewCollapsed(row)" class="oic-serial-scroll">
                                <div class="oic-serial-view-table">
                                    <div class="oic-serial-view-row oic-serial-view-header-row">
                                        <span style="flex:0 0 40px;text-align:center;">#</span>
                                        <span style="flex:1;">Seri No</span>
                                        <span style="flex:1;">Malzeme Üretim Tarihi</span>
                                        <span style="flex:0 0 100px;text-align:right;">Miktar</span>
                                    </div>
                                    <div v-for="(ser, si) in row.serials" :key="si" class="oic-serial-view-row">
                                        <span class="oic-serial-view-idx">{{ si + 1 }}</span>
                                        <input type="text" class="oic-serial-view-input" :value="ser.serial_no" disabled />
                                        <input type="text" class="oic-serial-view-input" :value="formatSerialDate(ser.production_date)" disabled />
                                        <span class="oic-serial-view-qty">{{ ser.quantity }} {{ ser.unit }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- At-once serial entry for each item -->
                        <div v-if="atOnceMode && needsSerialsAtOnce(row)" class="oic-atonce-bar" @click.stop>
                            <div class="oic-serial-section" style="border-top:none;padding-top:0;">
                                <!-- ST: serial checkbox -->
                                <div v-if="row.unit === 'ST'" class="oic-serial-toggle" style="padding:10px 0;">
                                    <label class="oic-toggle-label" @click.stop>
                                        <input type="checkbox" class="oic-toggle-cb" :checked="serialEnabled[row.id]" @change="toggleSerials(row)" />
                                        <span class="oic-toggle-switch"></span>
                                        <span class="oic-toggle-text">Seri Numarası Girilecek? <em>(Evet)</em></span>
                                    </label>
                                </div>

                                <!-- ST serial rows (when enabled) -->
                                <div v-if="row.unit === 'ST' && needsSerials(row)">
                                    <div class="oic-serial-header mt-2" @click="toggleCollapse(row)" style="cursor:pointer;">
                                        <i class="ki-outline ki-hash" style="font-size:13px;color:#6366f1;"></i>
                                        <span>Ürün Seri Numaralarını Giriniz. (Toplam: {{ row.quantity }} {{ row.unit }})</span>
                                        <button class="oic-serial-excel" @click.stop="triggerExcelUpload(row)">
                                            <i class="ki-outline ki-file-up" style="font-size:13px;"></i>
                                            <span>Excel'den Yükle</span>
                                        </button>
                                        <button class="oic-serial-excel oic-serial-dl" @click.stop="downloadExcelTemplate" title="Şablon İndir">
                                            <i class="ki-outline ki-file-down" style="font-size:13px;"></i>
                                            <span>Şablon</span>
                                        </button>
                                        <input type="file" :ref="el => { if(el) excelFileInputs[row.id] = el }" accept=".xls,.xlsx" style="display:none" @change="onExcelFileSelected(row, $event)" />
                                        <span style="margin-left:auto;display:flex;align-items:center;gap:4px;font-size:0.78rem;color:#6366f1;">
                                            {{ isCollapsed(row) ? 'Genişlet' : 'Daralt' }}
                                            <i :class="isCollapsed(row) ? 'ki-outline ki-arrow-down' : 'ki-outline ki-arrow-up'" style="font-size:12px;"></i>
                                        </span>
                                    </div>
                                    <div v-show="!isCollapsed(row)" class="oic-serial-scroll">
                                        <div class="oic-serial-grid">
                                            <div v-for="(ser, si) in (serials[row.id] || [])" :key="si" class="oic-serial-row">
                                                <div class="oic-serial-field">
                                                    <label>{{ si + 1 }}. Seri No</label>
                                                    <input type="text" class="oic-serial-input" v-model="ser.serial_no" placeholder="Seri No" />
                                                </div>
                                                <div class="oic-serial-field">
                                                    <label>{{ si + 1 }}. Malzeme Üretim Tarihi</label>
                                                    <input type="text" class="oic-fp-month" :data-serial-key="row.id + '-' + si" :data-value="ser.production_date_display" readonly placeholder="AA.YYYY" />
                                                </div>
                                                <div class="oic-serial-qty">1 ST</div>
                                            </div>
                                        </div>
                                        <div class="oic-serial-hint-inline">Seri numarası sayısının gönderilecek miktara eşit olmasına dikkat ediniz!</div>
                                    </div>
                                </div>

                                <!-- KG/M serial rows (always required) -->
                                <div v-if="row.unit !== 'ST'">
                                    <div class="oic-serial-header mt-2" @click="toggleCollapse(row)" style="cursor:pointer;">
                                        <i class="ki-outline ki-hash" style="font-size:13px;color:#64748b;"></i>
                                        <span>Ürün parti Numaralarını Giriniz. (Toplam: {{ row.quantity }} {{ row.unit }})</span>
                                        <button class="oic-serial-excel" @click.stop="triggerExcelUpload(row)">
                                            <i class="ki-outline ki-file-up" style="font-size:13px;"></i>
                                            <span>Excel'den Yükle</span>
                                        </button>
                                        <button class="oic-serial-excel oic-serial-dl" @click.stop="downloadExcelTemplate" title="Şablon İndir">
                                            <i class="ki-outline ki-file-down" style="font-size:13px;"></i>
                                            <span>Şablon</span>
                                        </button>
                                        <input type="file" :ref="el => { if(el) excelFileInputs[row.id] = el }" accept=".xls,.xlsx" style="display:none" @change="onExcelFileSelected(row, $event)" />
                                        <button class="oic-serial-add" @click.stop="addSerialRow(row)">
                                            <i class="ki-outline ki-plus" style="font-size:13px;"></i>
                                            <span>Satır Ekle</span>
                                        </button>
                                        <span style="margin-left:auto;display:flex;align-items:center;gap:4px;font-size:0.78rem;color:#6366f1;">
                                            {{ isCollapsed(row) ? 'Genişlet' : 'Daralt' }}
                                            <i :class="isCollapsed(row) ? 'ki-outline ki-arrow-down' : 'ki-outline ki-arrow-up'" style="font-size:12px;"></i>
                                        </span>
                                    </div>
                                    <div v-show="!isCollapsed(row)" class="oic-serial-scroll">
                                        <div class="oic-serial-table">
                                            <div class="oic-serial-table-header">
                                                <span style="flex:0 0 40px;">#</span>
                                                <span style="flex:1;">Parti No</span>
                                                <span style="flex:1;">Malzeme Üretim Tarihi</span>
                                                <span style="flex:0 0 100px;">Miktar</span>
                                                <span style="flex:0 0 36px;"></span>
                                            </div>
                                            <div v-for="(ser, si) in (serials[row.id] || [])" :key="si" class="oic-serial-table-row">
                                                <span class="oic-serial-table-idx">{{ si + 1 }}</span>
                                                <input type="text" class="oic-serial-table-input" v-model="ser.serial_no" placeholder="-" />
                                                <input type="text" class="oic-fp-month" :data-serial-key="row.id + '-' + si" :data-value="ser.production_date_display" readonly placeholder="AA.YYYY" />
                                                <input type="number" class="oic-serial-table-input oic-serial-table-qty" v-model.number="ser.quantity" :step="0.01" min="1" :placeholder="row.unit" />
                                                <button v-if="(serials[row.id] || []).length > 1" class="oic-serial-remove" @click="removeSerialRow(row, si)" title="Sil">
                                                    <i class="ki-outline ki-cross" style="font-size:12px;"></i>
                                                </button>
                                                <span v-else style="width:28px;flex-shrink:0;"></span>
                                            </div>
                                        </div>
                                        <div class="oic-serial-hint-inline">Seri numarası sayısının gönderilecek miktara eşit olmasına dikkat ediniz!</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Split bar when selected (partial mode) -->
                        <div v-if="selectable && isSelected(row)" class="oic-split-bar" @click.stop>
                            <div class="oic-split-row">
                                <div class="oic-split-label">
                                    <i class="ki-outline ki-slice" style="font-size:14px;color:#d97706;"></i>
                                    <span>Böl:</span>
                                </div>
                                <input type="number" class="oic-split-input" :value="splitAmounts[row.id]" :step="getStep(row.unit)" :min="0" :max="parseFloat(row.quantity)" @input="onSplitInput(row, $event)" @blur="onSplitBlur(row, $event)" placeholder="Miktar" />
                                <span class="oic-split-unit">{{ row.unit }}</span>
                                <span class="oic-split-remaining">Kalan: <strong>{{ getRemaining(row) }}</strong> {{ row.unit }}</span>
                            </div>

                            <!-- ST: serial checkbox -->
                            <div v-if="row.unit === 'ST' && parseFloat(splitAmounts[row.id]) > 0 && parseFloat(splitAmounts[row.id]) < 300" class="oic-serial-toggle">
                                <label class="oic-toggle-label" @click.stop>
                                    <input type="checkbox" class="oic-toggle-cb" :checked="serialEnabled[row.id]" @change="toggleSerials(row)" />
                                    <span class="oic-toggle-switch"></span>
                                    <span class="oic-toggle-text">Seri Numarası Girilecek? <em>(Evet)</em></span>
                                </label>
                            </div>

                            <!-- Serial entry: ST (when checkbox enabled) -->
                            <div v-if="row.unit === 'ST' && needsSerials(row)" class="oic-serial-section oic-serial-partial">
                                <div class="oic-serial-header" @click.stop="toggleCollapse(row)" style="cursor:pointer;">
                                    <i class="ki-outline ki-hash" style="font-size:13px;color:#6366f1;"></i>
                                    <span>Ürün Seri Numaralarını Giriniz. (Toplam: {{ parseFloat(splitAmounts[row.id]) }} {{ row.unit }})</span>
                                    <button class="oic-serial-excel" @click.stop="triggerExcelUpload(row)">
                                        <i class="ki-outline ki-file-up" style="font-size:13px;"></i>
                                        <span>Excel'den Yükle</span>
                                    </button>
                                    <button class="oic-serial-excel oic-serial-dl" @click.stop="downloadExcelTemplate" title="Şablon İndir">
                                        <i class="ki-outline ki-file-down" style="font-size:13px;"></i>
                                        <span>Şablon</span>
                                    </button>
                                    <input type="file" :ref="el => { if(el) excelFileInputs[row.id] = el }" accept=".xls,.xlsx" style="display:none" @change="onExcelFileSelected(row, $event)" />
                                    <span style="margin-left:auto;display:flex;align-items:center;gap:4px;font-size:0.78rem;color:#6366f1;">
                                        {{ isCollapsed(row) ? 'Genişlet' : 'Daralt' }}
                                        <i :class="isCollapsed(row) ? 'ki-outline ki-arrow-down' : 'ki-outline ki-arrow-up'" style="font-size:12px;"></i>
                                    </span>
                                </div>
                                <div v-show="!isCollapsed(row)" class="oic-serial-scroll">
                                    <div class="oic-serial-grid">
                                        <div v-for="(ser, si) in (serials[row.id] || [])" :key="si" class="oic-serial-row">
                                            <div class="oic-serial-field">
                                                <label>{{ si + 1 }}. Seri No</label>
                                                <input type="text" class="oic-serial-input" v-model="ser.serial_no" placeholder="Seri No" />
                                            </div>
                                            <div class="oic-serial-field">
                                                <label>{{ si + 1 }}. Malzeme Üretim Tarihi</label>
                                                <input type="text" class="oic-fp-month" :data-serial-key="row.id + '-' + si" :data-value="ser.production_date_display" readonly placeholder="AA.YYYY" />
                                            </div>
                                            <div class="oic-serial-qty">1 ST</div>
                                        </div>
                                    </div>
                                    <div class="oic-serial-hint-inline">Seri numarası sayısının gönderilecek miktara eşit olmasına dikkat ediniz!</div>
                                </div>
                            </div>

                            <!-- Serial entry: KG/M (always required) -->
                            <div v-if="row.unit !== 'ST' && needsSerials(row)" class="oic-serial-section oic-serial-partial">
                                <div class="oic-serial-header" @click.stop="toggleCollapse(row)" style="cursor:pointer;">
                                    <i class="ki-outline ki-hash" style="font-size:13px;color:#6366f1;"></i>
                                    <span>Ürün parti Numaralarını Giriniz. (Toplam: {{ parseFloat(splitAmounts[row.id]) }} {{ row.unit }})</span>
                                    <button class="oic-serial-excel" @click.stop="triggerExcelUpload(row)">
                                        <i class="ki-outline ki-file-up" style="font-size:13px;"></i>
                                        <span>Excel'den Yükle</span>
                                    </button>
                                    <button class="oic-serial-excel oic-serial-dl" @click.stop="downloadExcelTemplate" title="Şablon İndir">
                                        <i class="ki-outline ki-file-down" style="font-size:13px;"></i>
                                        <span>Şablon</span>
                                    </button>
                                    <input type="file" :ref="el => { if(el) excelFileInputs[row.id] = el }" accept=".xls,.xlsx" style="display:none" @change="onExcelFileSelected(row, $event)" />
                                    <button class="oic-serial-add" @click.stop="addSerialRow(row)">
                                        <i class="ki-outline ki-plus" style="font-size:13px;"></i>
                                        <span>Satır Ekle</span>
                                    </button>
                                    <span style="margin-left:auto;display:flex;align-items:center;gap:4px;font-size:0.78rem;color:#6366f1;">
                                        {{ isCollapsed(row) ? 'Genişlet' : 'Daralt' }}
                                        <i :class="isCollapsed(row) ? 'ki-outline ki-arrow-down' : 'ki-outline ki-arrow-up'" style="font-size:12px;"></i>
                                    </span>
                                </div>
                                <div v-show="!isCollapsed(row)" class="oic-serial-scroll">
                                    <div class="oic-serial-table">
                                        <div class="oic-serial-table-header">
                                            <span style="flex:0 0 40px;">#</span>
                                            <span style="flex:1;">Parti No</span>
                                            <span style="flex:1;">Malzeme Üretim Tarihi</span>
                                            <span style="flex:0 0 100px;">Miktar</span>
                                            <span style="flex:0 0 36px;"></span>
                                        </div>
                                        <div v-for="(ser, si) in (serials[row.id] || [])" :key="si" class="oic-serial-table-row">
                                            <span class="oic-serial-table-idx">{{ si + 1 }}</span>
                                            <input type="text" class="oic-serial-table-input" v-model="ser.serial_no" placeholder="-" />
                                            <input type="text" class="oic-fp-month" :data-serial-key="row.id + '-' + si" :data-value="ser.production_date_display" readonly placeholder="AA.YYYY" />
                                            <input type="number" class="oic-serial-table-input oic-serial-table-qty" v-model.number="ser.quantity" :step="0.01" min="1" :placeholder="row.unit" />
                                            <button v-if="(serials[row.id] || []).length > 1" class="oic-serial-remove" @click="removeSerialRow(row, si)" title="Sil">
                                                <i class="ki-outline ki-cross" style="font-size:12px;"></i>
                                            </button>
                                            <span v-else style="width:28px;flex-shrink:0;"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
        <!-- Gallery modal with next/prev -->
        <teleport to="body">
            <div v-if="gallery.visible" class="oic-gallery-overlay" @click.self="closeGallery">
                <button class="oic-gallery-close" @click="closeGallery" title="Kapat"><i class="ki-outline ki-cross" style="font-size:18px"></i></button>
                <div class="oic-gallery-counter">{{ gallery.index + 1 }} / {{ gallery.items.length }}<span v-if="currentGalleryItem" style="margin-left:8px;color:#cbd5e1;font-weight:400;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:bottom">{{ currentGalleryItem.name }}</span></div>
                <button v-if="gallery.items.length>1" class="oic-gallery-nav prev" @click.stop="galleryPrev" :disabled="gallery.index===0" title="Önceki (←)"><i class="ki-outline ki-arrow-left" style="font-size:22px"></i></button>
                <button v-if="gallery.items.length>1" class="oic-gallery-nav next" @click.stop="galleryNext" :disabled="gallery.index===gallery.items.length-1" title="Sonraki (→)"><i class="ki-outline ki-arrow-right" style="font-size:22px"></i></button>
                <div class="oic-gallery-stage" @click.stop>
                    <img v-if="currentGalleryItem?.isImage" :src="currentGalleryItem.src" :alt="currentGalleryItem.name" @error="onGalleryImgError" />
                    <iframe v-else :src="currentGalleryItem.src" title="preview"></iframe>
                    <a :href="currentGalleryItem?.downloadUrl||currentGalleryItem?.src" target="_blank" class="oic-gallery-open">Yeni pencerede aç</a>
                </div>
                <div v-if="gallery.items.length>1" class="oic-gallery-thumbs">
                    <div v-for="(g,i) in gallery.items" :key="i" class="oic-gallery-thumb" :class="{active:i===gallery.index}" @click="goToGallery(i)">
                        <img v-if="g.isImage" :src="g.thumb||g.src" :alt="g.name" @error="e=>e.target.style.display='none'" />
                        <div v-else class="oic-gallery-thumb-pdf"><i class="ki-outline ki-document" style="font-size:18px;color:#94a3b8"></i></div>
                    </div>
                </div>
            </div>
        </teleport>
</template>
<style scoped>
.order-item-card { border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.04),0 1px 2px rgba(0,0,0,0.02); }
.order-item-header { background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%); border-bottom:1px solid #e2e8f0; padding:18px 22px; display:flex; justify-content:space-between; align-items:center; }
.order-item-header-left { display:flex; align-items:center; gap:14px; }
.order-item-icon { width:42px; height:42px; border-radius:12px; background:linear-gradient(135deg,#eff6ff,#dbeafe); display:flex; align-items:center; justify-content:center; color:#3b82f6; font-size:20px; }
.order-item-title { margin:0; font-size:1.08rem; font-weight:700; color:#0f172a; }
.order-item-subtitle { font-size:0.85rem; color:#94a3b8; font-weight:500; }
.order-item-badge { background:#f1f5f9; color:#64748b; font-size:0.82rem; font-weight:700; padding:6px 14px; border-radius:8px; border:1px solid #e2e8f0; }
.order-item-body { background:#fff; padding:0; }
.order-item-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; padding:36px 20px; }
.oic-list-wrap { padding:14px; }
.oic-list { max-height:600px; overflow-y:auto; overflow-x:hidden; padding-right:6px; display:flex; flex-direction:column; gap:10px; }
.oic-list::-webkit-scrollbar { width:7px; }
.oic-list::-webkit-scrollbar-track { background:#f1f5f9; border-radius:10px; }
.oic-list::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:10px; }
.oic-list::-webkit-scrollbar-thumb:hover { background:#94a3b8; }
.oic-list--loading { max-height:480px; overflow:hidden; padding:14px; display:flex; flex-direction:column; gap:10px; }
.oic-row-wrap { display:flex; flex-direction:column; gap:0; }
.oic-row { display:flex; align-items:center; gap:12px; padding:14px 18px; margin-bottom: 2px !important; border:1px solid #e2e8f0; border-radius:12px; background:#fff; transition:all 0.15s ease; min-height:64px; }
.oic-row:hover { border-color:#cbd5e1; background:#f8fafc; }
.oic-row.oic-selectable { cursor:pointer; }
.oic-row.oic-selected { border-color:#3b82f6; border-bottom-left-radius:0; border-bottom-right-radius:0; background:linear-gradient(135deg,#eff6ff 0%,#f0f9ff 100%); }
.oic-idx { width:32px; height:32px; border-radius:8px; background:#f1f5f9; border:1px solid #e2e8f0; display:flex; align-items:center; justify-content:center; font-size:0.82rem; font-weight:700; color:#64748b; flex-shrink:0; }
.oic-row.oic-selected .oic-idx { background:#3b82f6; color:#fff; border-color:#3b82f6; }
.oic-code { display:flex; align-items:center; gap:10px; min-width:0; flex-shrink:0; width:210px; }
.oic-code-icon { width:34px; height:34px; border-radius:8px; background:linear-gradient(135deg,#eff6ff,#dbeafe); display:flex; align-items:center; justify-content:center; color:#3b82f6; font-size:15px; flex-shrink:0; }
.oic-code-text { font-weight:700; color:#0f172a; font-size:0.92rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.oic-title { flex:1; min-width:0; font-size:0.95rem; font-weight:500; color:#334155; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.oic-qty { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:6px 14px; font-size:0.88rem; color:#0f172a; display:inline-flex; align-items:center; gap:5px; flex-shrink:0; white-space:nowrap; }
.oic-qty strong { font-weight:800; }
.oic-qty em { font-style:normal; color:#64748b; font-weight:600; font-size:0.80rem; }
.oic-eye { width:34px; height:34px; border-radius:8px; border:1px solid #e2e8f0; background:#fff; color:#94a3b8; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.15s; flex-shrink:0; font-size:15px; }
.oic-eye:hover { background:#f1f5f9; color:#3b82f6; border-color:#bfdbfe; }
.oic-check { width:24px; height:24px; border-radius:7px; border:2px solid #cbd5e1; background:#fff; display:flex; align-items:center; justify-content:center; color:transparent; transition:all 0.15s; flex-shrink:0; }
.oic-check.checked { background:#3b82f6; border-color:#3b82f6; color:#fff; box-shadow:0 1px 4px rgba(59,130,246,0.3); }

/* Split bar */
.oic-split-bar { display:flex; flex-direction:column; gap:0; background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%); border:1px solid #fde68a; border-top:none; border-bottom-left-radius:12px; border-bottom-right-radius:12px; margin-top:-1px; }

/* At-once serial bar */
.oic-atonce-bar { display:flex; flex-direction:column; gap:0; background:#f8fafc; border:1px solid #e2e8f0; border-top:none; border-bottom-left-radius:12px; border-bottom-right-radius:12px; margin-top:-1px; }
.oic-split-row { display:flex; align-items:center; gap:12px; padding:12px 18px; }
.oic-split-label { display:flex; align-items:center; gap:5px; font-size:0.88rem; font-weight:600; color:#92400e; flex-shrink:0; }
.oic-split-input { width:100px; padding:7px 10px; border:1.5px solid #fbbf24; border-radius:8px; font-size:0.92rem; font-weight:700; color:#92400e; background:#fff; outline:none; -moz-appearance:textfield; }
.oic-split-input::-webkit-outer-spin-button, .oic-split-input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.oic-split-input:focus { border-color:#d97706; box-shadow:0 0 0 2px rgba(217,119,6,0.15); }
.oic-split-unit { font-size:0.85rem; font-weight:600; color:#92400e; flex-shrink:0; }
.oic-split-remaining { margin-left:auto; font-size:0.85rem; color:#92400e; font-weight:500; flex-shrink:0; }
.oic-split-remaining strong { font-weight:800; color:#78350f; }

/* Serial section */
.oic-serial-section { border-top:1px solid #e2e8f0; padding:14px 18px; }
.oic-serial-partial { background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%); border-color:#fde68a; }
.oic-serial-header { display:flex; align-items:center; gap:8px; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:12px; }
.oic-serial-grid { display:flex; flex-direction:column; gap:10px; }
.oic-serial-row { display:flex; align-items:flex-end; gap:12px; }
.oic-serial-field { display:flex; flex-direction:column; gap:4px; flex:1; }
.oic-serial-field label { font-size:0.76rem; font-weight:600; color:#64748b; }
.oic-serial-input { padding:8px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:0.88rem; color:#0f172a; background:#fff; outline:none; transition:border-color 0.15s; }
.oic-serial-input:focus { border-color:#3b82f6; box-shadow:0 0 0 2px rgba(59,130,246,0.1); }
.oic-serial-input::placeholder { color:#94a3b8; }
.oic-serial-qty { padding:8px 12px; font-size:0.82rem; font-weight:700; color:#475569; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; flex-shrink:0; text-align:center; min-width:65px; }

/* KG/M serial table */
.oic-serial-table { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; }
.oic-serial-table-header { display:flex; align-items:center; gap:8px; padding:10px 14px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.oic-serial-table-header span { font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; }
.oic-serial-table-row { display:flex; align-items:center; gap:10px; padding:10px 14px; border-bottom:1px solid #f1f5f9; }
.oic-serial-table-row:last-child { border-bottom:none; }
.oic-serial-table-row:hover { background:#f8fafc; }
.oic-serial-table-idx { width:30px; height:30px; border-radius:8px; background:#f1f5f9; border:1px solid #e2e8f0; display:flex; align-items:center; justify-content:center; font-size:0.76rem; font-weight:700; color:#64748b; flex-shrink:0; }
.oic-serial-table-input { flex:1; padding:8px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:0.88rem; color:#0f172a; background:#fff; outline:none; transition:border-color 0.15s; }
.oic-serial-table-input:focus { border-color:#3b82f6; box-shadow:0 0 0 2px rgba(59,130,246,0.1); }
.oic-serial-table-input::placeholder { color:#94a3b8; }
.oic-serial-table-qty { flex:0 0 110px; -moz-appearance:textfield; text-align:right; }
.oic-serial-table-qty::-webkit-outer-spin-button, .oic-serial-table-qty::-webkit-inner-spin-button { -webkit-appearance:none; }
.oic-serial-remove { width:30px; height:30px; border-radius:8px; border:1px solid #e2e8f0; background:#fff; color:#94a3b8; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition:all 0.15s; }
.oic-serial-remove:hover { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
.oic-serial-add { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border:1px dashed #cbd5e1; border-radius:8px; background:transparent; color:#64748b; font-size:0.84rem; font-weight:600; cursor:pointer; transition:all 0.15s; }
.oic-serial-add:hover { background:#f8fafc; border-color:#94a3b8; color:#334155; }
.oic-serial-excel { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border:1px dashed #6366f1; border-radius:8px; background:transparent; color:#6366f1; font-size:0.84rem; font-weight:600; cursor:pointer; transition:all 0.15s; }
.oic-serial-excel:hover { background:#eef2ff; border-color:#818cf8; color:#4f46e5; }
.oic-serial-dl { padding:8px 10px; min-width:36px; justify-content:center; }
.oic-serial-hint { border-top:1px solid #e2e8f0; padding:10px 18px; display:flex; align-items:center; gap:7px; font-size:0.82rem; color:#94a3b8; }
.oic-serial-hint-inline { margin-top:12px; padding:8px 12px; font-size:0.78rem; color:#94a3b8; font-style:italic; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; }

/* Per-item scrollable serial area */
.oic-serial-scroll { max-height:320px; overflow-y:auto; padding-right:4px; }
.oic-serial-scroll::-webkit-scrollbar { width:5px; }
.oic-serial-scroll::-webkit-scrollbar-track { background:#f1f5f9; border-radius:6px; }
.oic-serial-scroll::-webkit-scrollbar-thumb { background:#c7d2fe; border-radius:6px; }
.oic-serial-scroll::-webkit-scrollbar-thumb:hover { background:#818cf8; }

/* Serial summary badge on main row */
.oic-serial-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:6px; font-size:0.74rem; font-weight:600; color:#475569; flex-shrink:0; white-space:nowrap; }

/* Serial view (read-only, collapsible) */
.oic-serial-view { background:#f8fafc; border:1px solid #e2e8f0; border-top:none; border-bottom-left-radius:12px; border-bottom-right-radius:12px; }
.oic-serial-view-header { display:flex; align-items:center; gap:7px; padding:10px 18px; cursor:pointer; font-size:0.85rem; font-weight:600; color:#475569; }
.oic-serial-view-table { border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#fff; margin:0 14px 14px; }
.oic-serial-view-header-row { display:flex; align-items:center; gap:8px; padding:8px 12px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.oic-serial-view-header-row span { font-size:0.72rem; font-weight:600; color:#64748b; text-transform:uppercase; }
.oic-serial-view-row { display:flex; align-items:center; gap:8px; padding:7px 12px; border-bottom:1px solid #f1f5f9; }
.oic-serial-view-row:last-child { border-bottom:none; }
.oic-serial-view-idx { width:28px; height:28px; border-radius:7px; background:#f1f5f9; border:1px solid #e2e8f0; display:flex; align-items:center; justify-content:center; font-size:0.74rem; font-weight:700; color:#64748b; flex-shrink:0; }
.oic-serial-view-input { flex:1; padding:6px 8px; border:1px solid #e2e8f0; border-radius:6px; font-size:0.85rem; color:#64748b; background:#f8fafc; cursor:not-allowed; }
.oic-serial-view-qty { flex:0 0 100px; text-align:right; font-size:0.85rem; font-weight:600; color:#0f172a; }

/* Toggle checkbox */
.oic-serial-toggle { border-top:1px solid #fde68a; padding:10px 18px; }
.oic-toggle-label { display:inline-flex; align-items:center; gap:10px; cursor:pointer; user-select:none; }
.oic-toggle-cb { display:none; }
.oic-toggle-switch { width:40px; height:22px; border-radius:11px; background:#cbd5e1; position:relative; transition:all 0.2s; flex-shrink:0; }
.oic-toggle-switch::after { content:''; position:absolute; top:3px; left:3px; width:16px; height:16px; border-radius:50%; background:#fff; transition:all 0.2s; box-shadow:0 1px 3px rgba(0,0,0,0.15); }
.oic-toggle-cb:checked + .oic-toggle-switch { background:#3b82f6; }
.oic-toggle-cb:checked + .oic-toggle-switch::after { left:21px; }
.oic-toggle-text { font-size:0.88rem; font-weight:600; color:#0f172a; }
.oic-toggle-text em { color:#3b82f6; font-style:normal; }

/* Flatpickr month picker */
.oic-fp-month { width:100%; padding:6px 10px; border:1px solid #e0e7ff; border-radius:6px; font-size:0.88rem; font-weight:700; color:#1e1b4b; background:#fff; min-height:34px; cursor:pointer; }
.oic-fp-month:focus { border-color:#6366f1; box-shadow:0 0 0 2px rgba(99,102,241,0.12); outline:none; }
.flatpickr-monthInput { max-width:100%; }

/* Skeleton */
.oic-skeleton-row { display:flex; align-items:center; gap:14px; padding:16px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; }
.oic-skeleton-line { height:12px; border-radius:6px; background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 37%,#f1f5f9 63%); background-size:400% 100%; animation:oic-shimmer 1.4s ease infinite; }
.oic-skeleton-line.w-20{ width:18%; }
.oic-skeleton-line.w-50{ width:48%; }
@keyframes oic-shimmer { 0%{background-position:100% 0} 100%{background-position:-100% 0} }

@media (max-width:640px){
    .oic-code { width:150px; }
    .oic-serial-row { flex-direction:column; align-items:stretch; }
    .oic-serial-qty { text-align:left; }
}

/* Item file sections */
.oic-item-files { background:#f8fafc; border:1px solid #e2e8f0; border-top:none; border-bottom-left-radius:12px; border-bottom-right-radius:12px; }
.oic-item-files-header { display:flex; align-items:center; gap:7px; padding:10px 18px; cursor:pointer; font-size:0.85rem; font-weight:600; color:#475569; }
.oic-item-files-header:hover { background:#f1f5f9; }
.oic-file-badge { background:#e0e7ff; color:#4f46e5; font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:10px; }
.oic-item-files-body { padding:4px 18px 14px; display:flex; flex-direction:column; gap:14px; }
.oic-item-file-section { display:flex; flex-direction:column; gap:6px; }
.oic-item-file-label { display:flex; align-items:center; gap:6px; font-size:0.82rem; font-weight:600; color:#334155; }
.oic-item-file-hint { font-size:0.72rem; font-weight:400; color:#94a3b8; }
.oic-item-file-list { display:flex; flex-wrap:wrap; gap:6px; }
.oic-item-file-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; font-size:0.82rem; max-width:320px; }
.oic-item-file-chip.uploaded { border-color:#bbf7d0; background:#f0fdf4; }
.oic-item-file-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#0f172a; font-weight:500; flex:1; min-width:0; }
.oic-item-file-status { font-size:0.72rem; font-weight:700; padding:2px 8px; border-radius:6px; white-space:nowrap; flex-shrink:0; }
.oic-item-file-status.status-pending { background:#fef3c7; color:#92400e; }
.oic-item-file-status.status-accepted { background:#dcfce7; color:#166534; }
.oic-item-file-status.status-rejected { background:#fef2f2; color:#991b1b; }
.oic-item-file-status.status-uploaded { background:#dcfce7; color:#166534; }
.oic-item-file-status.status-uploading { background:#e0e7ff; color:#4338ca; }
.oic-item-file-remove { width:24px; height:24px; border-radius:6px; border:1px solid #e2e8f0; background:#fff; color:#94a3b8; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition:all 0.15s; }
.oic-item-file-remove:hover { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
.oic-item-file-preview { width:24px; height:24px; border-radius:6px; border:1px solid #e0e7ff; background:#eef2ff; color:#6366f1; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition:all 0.15s; }
.oic-item-file-preview:hover { background:#6366f1; border-color:#6366f1; color:#fff; }
.oic-previewable { cursor:pointer; transition:all 0.15s; }
.oic-previewable:hover { border-color:#c7d2fe; background:#f8fafc; box-shadow:0 1px 4px rgba(99,102,241,0.08); }
.oic-item-file-upload { display:flex; align-items:center; gap:8px; }
.oic-item-file-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border:1px dashed #cbd5e1; border-radius:8px; background:transparent; color:#64748b; font-size:0.82rem; font-weight:600; cursor:pointer; transition:all 0.15s; }
.oic-item-file-btn:hover { background:#f8fafc; border-color:#94a3b8; color:#334155; }
.oic-item-file-btn:disabled { opacity:0.5; cursor:not-allowed; }
.oic-item-file-btn-image { border-color:#c4b5fd; color:#7c3aed; }
.oic-item-file-btn-image:hover { background:#f5f3ff; border-color:#a78bfa; color:#6d28d9; }

/* Thumbnail grid for Ürün Görselleri */
.oic-image-grid { display:flex; flex-wrap:wrap; gap:10px; }
.oic-image-thumb { position:relative; width:84px; height:84px; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; background:#fff; cursor:pointer; flex-shrink:0; box-shadow:0 1px 3px rgba(0,0,0,0.06); transition:border-color 0.15s, box-shadow 0.15s, transform 0.15s; }
.oic-image-thumb:hover { border-color:#c7d2fe; box-shadow:0 4px 14px rgba(99,102,241,0.14); transform:translateY(-1px); }
.oic-image-thumb img { width:100%; height:100%; object-fit:cover; display:block; transition:transform 0.25s ease; }
.oic-image-thumb:hover img { transform:scale(1.04); }
.oic-image-thumb.uploaded { border-color:#c4b5fd; }
.oic-image-overlay.oic-split { position:absolute; inset:0; display:flex; flex-direction:row; padding:0; gap:0; opacity:0; transition:opacity 0.2s ease; background:transparent; }
.oic-image-thumb:hover .oic-image-overlay.oic-split { opacity:1; }
.oic-split-half { flex:1; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,0.48); backdrop-filter:blur(3px); border:none; cursor:pointer; color:#fff; transition:background 0.15s ease, transform 0.12s ease; }
.oic-split-half.left { border-right:1px solid rgba(255,255,255,0.14); border-radius:0; }
.oic-split-half.left.full { border-right:none; }
.oic-split-half.right { border-radius:0; }
.oic-split-half i { font-size:22px; filter:drop-shadow(0 1px 4px rgba(0,0,0,0.28)); transition:transform 0.14s ease; }
.oic-split-half:hover i { transform:scale(1.14); }
.oic-split-half.left:hover { background:rgba(79,70,229,0.82); }
.oic-split-half.right:hover { background:rgba(225,29,72,0.84); }
.oic-split-half:active i { transform:scale(0.96); }
.oic-thumb-fallback { width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:3px; background:#f8fafc; color:#94a3b8; }
@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }

/* Gallery overlay */
.oic-gallery-overlay { position:fixed; inset:0; background:rgba(15,23,42,0.88); backdrop-filter:blur(6px); z-index:9999; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:14px; padding:24px; }
.oic-gallery-close { position:absolute; top:18px; right:18px; width:40px; height:40px; border-radius:10px; border:1px solid rgba(255,255,255,0.18); background:rgba(255,255,255,0.08); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.15s; backdrop-filter:blur(4px); }
.oic-gallery-close:hover { background:#fff; color:#0f172a; }
.oic-gallery-counter { color:#e2e8f0; font-size:0.82rem; font-weight:600; letter-spacing:0.04em; display:flex; align-items:center; gap:4px; }
.oic-gallery-nav { position:absolute; top:50%; transform:translateY(-50%); width:48px; height:48px; border-radius:50%; border:1px solid rgba(255,255,255,0.18); background:rgba(255,255,255,0.10); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.15s; backdrop-filter:blur(4px); }
.oic-gallery-nav:hover:not(:disabled) { background:#fff; color:#0f172a; transform:translateY(-50%) scale(1.05); }
.oic-gallery-nav:disabled { opacity:0.28; cursor:not-allowed; }
.oic-gallery-nav.prev { left:22px; }
.oic-gallery-nav.next { right:22px; }
.oic-gallery-stage { max-width:min(1100px,92vw); max-height:76vh; width:92vw; display:flex; flex-direction:column; align-items:center; gap:10px; }
.oic-gallery-stage img { max-width:100%; max-height:70vh; object-fit:contain; border-radius:14px; background:#fff; box-shadow:0 12px 40px rgba(0,0,0,0.35); display:block; }
.oic-gallery-stage iframe { width:100%; height:70vh; border:none; border-radius:14px; background:#fff; box-shadow:0 12px 40px rgba(0,0,0,0.35); }
.oic-gallery-open { color:#93c5fd; font-size:0.82rem; text-decoration:underline; font-weight:500; }
.oic-gallery-thumbs { display:flex; gap:8px; max-width:92vw; overflow-x:auto; padding:4px 2px; scrollbar-width:thin; }
.oic-gallery-thumbs::-webkit-scrollbar { height:4px; }
.oic-gallery-thumb { width:64px; height:64px; border-radius:10px; overflow:hidden; border:2px solid transparent; cursor:pointer; flex-shrink:0; background:#fff; opacity:0.62; transition:all 0.15s; }
.oic-gallery-thumb.active { border-color:#818cf8; opacity:1; box-shadow:0 2px 10px rgba(99,102,241,0.35); }
.oic-gallery-thumb:hover { opacity:1; }
.oic-gallery-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.oic-gallery-thumb-pdf { width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f8fafc; }
</style>
