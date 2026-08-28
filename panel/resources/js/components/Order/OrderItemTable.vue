<script>
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import flatpickr from 'flatpickr';
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect/index.js';
import { Turkish } from 'flatpickr/dist/l10n/tr.js';
import 'flatpickr/dist/plugins/monthSelect/style.css';

flatpickr.localize(Turkish);

export default {
    props: {
        orderId: { type: String, required: true },
        orderNumericId: { type: Number, required: false },
        selectable: { type: Boolean, default: false },
        atOnceMode: { type: Boolean, default: false },
        containerSuffix: { type: String, default: '' }
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
            items: [],
            loading: true,
            error: null,
            fpInstances: []
        }
    },
    computed:{
        hasItems(){ return this.items.length > 0; },
        selectedCount(){ return Object.keys(this.selected).filter(k => this.selected[k]).length; },
        allValid(){
            for(const key in this.selected){
                if(!this.selected[key]) continue;
                const item = this.items.find(i => i.id === key);
                if(!item) continue;
                const amt = parseFloat(this.splitAmounts[key]);
                if(isNaN(amt) || amt <= 0) return false;
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
                // Validate serials for KG/M — required, at least 1
                if(item.unit !== 'ST'){
                    const sers = this.serials[key] || [];
                    if(sers.length === 0) return false;
                    let sum = 0;
                    for(const s of sers){
                        if(!s.production_date || parseFloat(s.quantity) <= 0) return false;
                        sum += parseFloat(s.quantity) || 0;
                    }
                    if(Math.abs(sum - amt) > 0.01) return false;
                }
            }
            return true;
        }
    },
    emits: ['select', 'serials'],
    mounted(){
        this.resolveAndBuild();
    },
    updated(){
        this.initFlatpickr();
    },
    beforeUnmount(){
        this.destroyFlatpickr();
    },
    watch: {
        atOnceMode(){
            if(this.atOnceMode){
                this.ensureAtOnceSerials();
            }
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
                this.rebuildSerials();
                this.notifySelect();
            }
        },
        serials: {
            deep: true,
            handler(){
                this.notifySelect();
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
            for(const item of this.items){
                if(!this.needsSerialsAtOnce(item)) continue;
                const key = item.id;
                const unit = item.unit || 'ST';
                if(unit === 'ST') continue; // ST handled by checkbox
                // KG/M: create first row if empty
                if(!this.serials[key] || this.serials[key].length === 0){
                    const qty = parseFloat(item.quantity) || 0;
                    this.serials[key] = [{ serial_no: '-', production_date: '', production_date_display: '', quantity: qty, unit }];
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
                    this.serials[key] = [{ serial_no: '-', production_date: '', production_date_display: '', quantity: amt, unit }];
                } else {
                    existing.forEach(s => s.unit = unit);
                    this.serials[key] = [...existing];
                }
            }
        },
        rebuildSerials(){
            for(const key in this.selected){
                if(!this.selected[key]) continue;
                const item = this.items.find(i => i.id === key);
                if(!item) continue;
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
            // Clean up
            for(const key in this.serials){
                if(!this.selected[key]) delete this.serials[key];
            }
            for(const key in this.serialEnabled){
                if(!this.selected[key]) delete this.serialEnabled[key];
            }
            for(const key in this.serialCollapsed){
                if(!this.selected[key]) delete this.serialCollapsed[key];
            }
        },
        addSerialRow(item){
            const key = item.id;
            const unit = item.unit || 'ST';
            if(!this.serials[key]) this.serials[key] = [];
            this.serials[key].push({
                serial_no: unit === 'ST' ? '' : '-',
                production_date: '',
                production_date_display: '',
                quantity: unit === 'ST' ? 1 : 0,
                unit: unit
            });
            this.serials = { ...this.serials };
        },
        removeSerialRow(item, idx){
            const key = item.id;
            if(this.serials[key]) this.serials[key].splice(idx, 1);
            this.serials = { ...this.serials };
        },
        // Initialize flatpickr month picker on all .oic-fp-month elements
        initFlatpickr(){
            // Destroy old instances first
            this.destroyFlatpickr();
            this.$nextTick(()=>{
                const inputs = this.$el.querySelectorAll('.oic-fp-month:not(.flatpickr-input)');
                inputs.forEach(input => {
                    const serialKey = input.dataset.serialKey; // "itemId-index"
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
        getSelected(){
            const result = [];
            // Partial mode: emit selected items with split amounts + serials
            for(const key in this.selected){
                if(!this.selected[key]) continue;
                const amt = parseFloat(this.splitAmounts[key]) || 0;
                const item = this.items.find(i => i.id === key);
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
                result.push({ qnid: item.id, serials: itemSerials });
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
            if(isNaN(numVal) || numVal < 0){
                this.splitAmounts[row.id] = 0;
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
            if(val < 0) val = 0;
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
                        _raw: r
                    };
                });
            }catch(e){
                this.error = 'Kalemler yüklenemedi';
            }finally{
                this.loading = false;
                if(this.atOnceMode) this.ensureAtOnceSerials();
            }
        }
    }
}
</script>
<template>
    <div class="order-item-card">
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
                    <div v-for="(row, idx) in items" :key="row.id" class="oic-row-wrap">
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
                            <span class="oic-qty" v-if="row.original_quantity && parseFloat(row.original_quantity) !== parseFloat(row.quantity)">
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

                        <!-- At-once serial entry for each item -->
                        <div v-if="atOnceMode && needsSerialsAtOnce(row)" class="oic-split-bar" @click.stop style="border-color:#e0e7ff;background:linear-gradient(135deg,#eef2ff 0%,#e0e7ff 100%);">
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
                                    <div class="oic-serial-header">
                                        <i class="ki-outline ki-hash" style="font-size:13px;color:#6366f1;"></i>
                                        <span>Ürün Seri Numaralarını Giriniz. (Toplam: {{ row.quantity }} {{ row.unit }})</span>
                                    </div>
                                    <div class="oic-serial-scroll">
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
                                    <div class="oic-serial-header">
                                        <i class="ki-outline ki-hash" style="font-size:13px;color:#6366f1;"></i>
                                        <span>Ürün parti Numaralarını Giriniz. (Toplam: {{ row.quantity }} {{ row.unit }})</span>
                                    </div>
                                    <div class="oic-serial-scroll">
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
                                                <input type="number" class="oic-serial-table-input oic-serial-table-qty" v-model.number="ser.quantity" :step="0.01" min="0" :placeholder="row.unit" />
                                                <button v-if="(serials[row.id] || []).length > 1" class="oic-serial-remove" @click="removeSerialRow(row, si)" title="Sil">
                                                    <i class="ki-outline ki-cross" style="font-size:12px;"></i>
                                                </button>
                                                <span v-else style="width:28px;flex-shrink:0;"></span>
                                            </div>
                                        </div>
                                        <button class="oic-serial-add" @click="addSerialRow(row)">
                                            <i class="ki-outline ki-plus" style="font-size:13px;"></i>
                                            <span>Satır Ekle</span>
                                        </button>
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
                            <div v-if="row.unit === 'ST' && needsSerials(row)" class="oic-serial-section">
                                <div class="oic-serial-header" @click.stop="toggleCollapse(row)" style="cursor:pointer;">
                                    <i class="ki-outline ki-hash" style="font-size:13px;color:#6366f1;"></i>
                                    <span>Ürün Seri Numaralarını Giriniz.</span>
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
                            <div v-if="row.unit !== 'ST' && needsSerials(row)" class="oic-serial-section">
                                <div class="oic-serial-header" @click.stop="toggleCollapse(row)" style="cursor:pointer;">
                                    <i class="ki-outline ki-hash" style="font-size:13px;color:#6366f1;"></i>
                                    <span>Ürün parti Numaralarını Giriniz.</span>
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
                                            <input type="number" class="oic-serial-table-input oic-serial-table-qty" v-model.number="ser.quantity" :step="0.01" min="0" :placeholder="row.unit" />
                                            <button v-if="(serials[row.id] || []).length > 1" class="oic-serial-remove" @click="removeSerialRow(row, si)" title="Sil">
                                                <i class="ki-outline ki-cross" style="font-size:12px;"></i>
                                            </button>
                                            <span v-else style="width:28px;flex-shrink:0;"></span>
                                        </div>
                                    </div>
                                    <button class="oic-serial-add" @click="addSerialRow(row)">
                                        <i class="ki-outline ki-plus" style="font-size:13px;"></i>
                                        <span>Satır Ekle</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
.oic-row { display:flex; align-items:center; gap:12px; padding:14px 18px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; transition:all 0.15s ease; min-height:64px; }
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
.oic-split-row { display:flex; align-items:center; gap:12px; padding:12px 18px; }
.oic-split-label { display:flex; align-items:center; gap:5px; font-size:0.88rem; font-weight:600; color:#92400e; flex-shrink:0; }
.oic-split-input { width:100px; padding:7px 10px; border:1.5px solid #fbbf24; border-radius:8px; font-size:0.92rem; font-weight:700; color:#92400e; background:#fff; outline:none; -moz-appearance:textfield; }
.oic-split-input::-webkit-outer-spin-button, .oic-split-input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.oic-split-input:focus { border-color:#d97706; box-shadow:0 0 0 2px rgba(217,119,6,0.15); }
.oic-split-unit { font-size:0.85rem; font-weight:600; color:#92400e; flex-shrink:0; }
.oic-split-remaining { margin-left:auto; font-size:0.85rem; color:#92400e; font-weight:500; flex-shrink:0; }
.oic-split-remaining strong { font-weight:800; color:#78350f; }

/* Serial section */
.oic-serial-section { border-top:1px solid #fde68a; padding:14px 18px; }
.oic-serial-header { display:flex; align-items:center; gap:7px; font-size:0.85rem; font-weight:600; color:#4338ca; margin-bottom:12px; }
.oic-serial-grid { display:flex; flex-direction:column; gap:8px; }
.oic-serial-row { display:flex; align-items:flex-end; gap:10px; }
.oic-serial-field { display:flex; flex-direction:column; gap:3px; flex:1; }
.oic-serial-field label { font-size:0.74rem; font-weight:600; color:#6366f1; }
.oic-serial-input { padding:7px 10px; border:1.5px solid #c7d2fe; border-radius:8px; font-size:0.88rem; color:#1e1b4b; background:#fff; outline:none; }
.oic-serial-input:focus { border-color:#6366f1; box-shadow:0 0 0 2px rgba(99,102,241,0.12); }
.oic-serial-input::placeholder { color:#a5b4fc; }
.oic-serial-qty { padding:7px 10px; font-size:0.82rem; font-weight:700; color:#4338ca; background:#eef2ff; border:1px solid #c7d2fe; border-radius:8px; flex-shrink:0; text-align:center; min-width:60px; }

/* KG/M serial table */
.oic-serial-table { border:1px solid #e0e7ff; border-radius:10px; overflow:hidden; background:#fff; margin-bottom:8px; }
.oic-serial-table-header { display:flex; align-items:center; gap:8px; padding:8px 12px; background:#eef2ff; border-bottom:1px solid #e0e7ff; }
.oic-serial-table-header span { font-size:0.74rem; font-weight:600; color:#4338ca; text-transform:uppercase; }
.oic-serial-table-row { display:flex; align-items:center; gap:8px; padding:8px 12px; border-bottom:1px solid #f1f5f9; }
.oic-serial-table-row:last-child { border-bottom:none; }
.oic-serial-table-idx { width:28px; height:28px; border-radius:7px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-size:0.74rem; font-weight:700; color:#64748b; flex-shrink:0; }
.oic-serial-table-input { flex:1; padding:6px 8px; border:1px solid #e0e7ff; border-radius:6px; font-size:0.85rem; color:#1e1b4b; background:#fff; outline:none; }
.oic-serial-table-input:focus { border-color:#6366f1; }
.oic-serial-table-input::placeholder { color:#a5b4fc; }
.oic-serial-table-qty { flex:0 0 100px; -moz-appearance:textfield; }
.oic-serial-table-qty::-webkit-outer-spin-button, .oic-serial-table-qty::-webkit-inner-spin-button { -webkit-appearance:none; }
.oic-serial-remove { width:28px; height:28px; border-radius:6px; border:1px solid #fecaca; background:#fff; color:#dc2626; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition:all 0.15s; }
.oic-serial-remove:hover { background:#fef2f2; border-color:#f87171; }
.oic-serial-add { display:inline-flex; align-items:center; gap:5px; padding:7px 14px; border:1.5px dashed #6366f1; border-radius:8px; background:transparent; color:#4f46e5; font-size:0.82rem; font-weight:600; cursor:pointer; transition:all 0.15s; }
.oic-serial-add:hover { background:#eef2ff; border-color:#4f46e5; }
.oic-serial-hint { border-top:1px solid #fde68a; padding:10px 18px; display:flex; align-items:center; gap:7px; font-size:0.82rem; color:#94a3b8; }
.oic-serial-hint-inline { margin-top:8px; font-size:0.78rem; color:#94a3b8; font-style:italic; }

/* Per-item scrollable serial area */
.oic-serial-scroll { max-height:320px; overflow-y:auto; padding-right:4px; }
.oic-serial-scroll::-webkit-scrollbar { width:5px; }
.oic-serial-scroll::-webkit-scrollbar-track { background:#f1f5f9; border-radius:6px; }
.oic-serial-scroll::-webkit-scrollbar-thumb { background:#c7d2fe; border-radius:6px; }
.oic-serial-scroll::-webkit-scrollbar-thumb:hover { background:#818cf8; }

/* Serial summary badge on main row */
.oic-serial-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; background:#eef2ff; border:1px solid #c7d2fe; border-radius:6px; font-size:0.74rem; font-weight:600; color:#4338ca; flex-shrink:0; white-space:nowrap; }

/* Toggle checkbox */
.oic-serial-toggle { border-top:1px solid #fde68a; padding:10px 18px; }
.oic-toggle-label { display:inline-flex; align-items:center; gap:10px; cursor:pointer; user-select:none; }
.oic-toggle-cb { display:none; }
.oic-toggle-switch { width:40px; height:22px; border-radius:11px; background:#cbd5e1; position:relative; transition:all 0.2s; flex-shrink:0; }
.oic-toggle-switch::after { content:''; position:absolute; top:3px; left:3px; width:16px; height:16px; border-radius:50%; background:#fff; transition:all 0.2s; box-shadow:0 1px 3px rgba(0,0,0,0.15); }
.oic-toggle-cb:checked + .oic-toggle-switch { background:#6366f1; }
.oic-toggle-cb:checked + .oic-toggle-switch::after { left:21px; }
.oic-toggle-text { font-size:0.88rem; font-weight:600; color:#1e1b4b; }
.oic-toggle-text em { color:#6366f1; font-style:normal; }

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
</style>
