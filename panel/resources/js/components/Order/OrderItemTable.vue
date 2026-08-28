<script>
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';

export default {
    props: {
        orderId: { type: String, required: true },
        orderNumericId: { type: Number, required: false },
        selectable: { type: Boolean, default: false },
        containerSuffix: { type: String, default: '' }
    },
    data(){
        return { plib: new Plib(), resolvedParentId: null, selected: {}, items: [], loading: true, error: null }
    },
    computed:{
        hasItems(){ return this.items.length > 0; },
        selectedCount(){ return Object.keys(this.selected).filter(k => this.selected[k]).length; }
    },
    emits: ['select'],
    mounted(){
        this.resolveAndBuild();
    },
    methods:{
        getSelected(){
            return Object.keys(this.selected).filter(k => this.selected[k]);
        },
        notifySelect(){
            this.$emit('select', this.getSelected());
        },
        toggleCard(row){
            if(!this.selectable) {
                this.showDetail(row);
                return;
            }
            const id = String(row.id_qnid || row.id);
            // keep both id forms for compatibility with partial clone flow (expects qnid)
            const qnid = row.id; // Documents tableList returns qnid as 'id'
            const key = qnid;
            this.selected[key] = !this.selected[key];
            this.notifySelect();
        },
        isSelected(row){
            const key = row.id;
            return !!this.selected[key];
        },
        showDetail(row){
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
                        <div><div style="font-size:0.72rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Miktar</div><div style="font-weight:700;color:#0f172a;font-size:0.95rem;">${row.quantity||'-'} <span style="color:#64748b;font-weight:500;font-size:0.82rem;">${row.unit||''}</span></div></div>
                    </div>
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
                }catch(e){
                    console.error(e);
                    numericId = this.orderNumericId;
                }
            }
            this.resolvedParentId = numericId || null;
            if(!numericId){
                console.warn('OrderItemTable: could not resolve parent numeric id for', this.orderId);
                this.loading = false;
                return;
            }
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
                // tableList returns {data:[], totalCount...} — handle both wrapped forms
                const rows = Array.isArray(raw) ? raw : (rsp?.data || []);
                // If rsp is the direct tableList object, it has .data
                const list = rsp?.data ? rsp.data : rows;
                const actualRows = Array.isArray(list) ? list : (list?.data || []);
                // Normalize
                this.items = (Array.isArray(actualRows) ? actualRows : []).map(r=>{
                    try{ JSON.parse(r.main_attr||'[]').forEach(el=> r[el['Key']]=el['Value']); }catch(e){}
                    return {
                        id: r.id, // qnid
                        id_qnid: r.id,
                        raw_id: r.main_id || r.id,
                        prod_code: r['prod_code']||'-',
                        title: r['title']||'-',
                        quantity: r['quantity']||'-',
                        unit: r['unit']||'',
                        _raw: r
                    };
                });
            }catch(e){
                console.error('fetchItems failed', e);
                this.error = 'Kalemler yüklenemedi';
            }finally{
                this.loading = false;
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

            <!-- Error -->
            <div v-else-if="error" class="order-item-empty">
                <i class="ki-outline ki-cross-circle" style="font-size:28px;color:#f87171;"></i>
                <span style="color:#94a3b8;font-size:0.85rem;">{{ error }}</span>
            </div>

            <!-- Empty -->
            <div v-else-if="!items.length" class="order-item-empty">
                <i class="ki-outline ki-question-circle" style="font-size:28px;color:#cbd5e1;"></i>
                <span style="color:#94a3b8;font-size:0.85rem;">Henüz kalem eklenmemiş</span>
            </div>

            <!-- Rows -->
            <div v-else class="oic-list-wrap">
                <div class="oic-list">
                    <div
                        v-for="(row, idx) in items"
                        :key="row.id"
                        class="oic-row"
                        :class="{ 'oic-selected': isSelected(row), 'oic-selectable': selectable }"
                        @click="toggleCard(row)"
                    >
                        <div class="oic-idx">{{ idx + 1 }}</div>

                        <div v-if="selectable" class="oic-check" :class="{ checked: isSelected(row) }" @click.stop="toggleCard(row)">
                            <i class="ki-outline ki-check" v-if="isSelected(row)"></i>
                        </div>

                        <div class="oic-code">
                            <div class="oic-code-icon">
                                <i class="ki-outline ki-box"></i>
                            </div>
                            <span class="oic-code-text" :title="row.prod_code">{{ row.prod_code }}</span>
                        </div>

                        <div class="oic-title" :title="row.title">{{ row.title }}</div>

                        <span class="oic-qty">
                            <strong>{{ row.quantity }}</strong>
                            <em v-if="row.unit">{{ row.unit }}</em>
                        </span>

                        <button class="oic-eye" @click.stop="showDetail(row)" title="Detay">
                            <i class="ki-outline ki-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<style scoped>
.order-item-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02);
}
.order-item-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.order-item-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.order-item-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3b82f6;
    font-size: 16px;
}
.order-item-title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.01em;
}
.order-item-subtitle {
    font-size: 0.74rem;
    color: #94a3b8;
    font-weight: 500;
}
.order-item-badge {
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    letter-spacing: 0.02em;
}
.order-item-body {
    background: #fff;
    padding: 0;
}
.order-item-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 28px 16px;
}

/* scrollable list — ~10-15 rows visible */
.oic-list-wrap {
    padding: 10px 10px 10px 10px;
}
.oic-list {
    max-height: 420px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 6px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    scroll-behavior: smooth;
}
/* nice scrollbar */
.oic-list::-webkit-scrollbar { width: 6px; }
.oic-list::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
.oic-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.oic-list::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
.oic-list { scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9; }

.oic-list--loading {
    max-height: 420px;
    overflow: hidden;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* row */
.oic-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    transition: all 0.15s ease;
    min-height: 52px;
}
.oic-row:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}
.oic-row.oic-selectable { cursor: pointer; }
.oic-row.oic-selected {
    border-color: #3b82f6;
    background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
    box-shadow: 0 0 0 1px rgba(59,130,246,0.12);
}
.oic-idx {
    width: 26px;
    height: 26px;
    border-radius: 7px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    flex-shrink: 0;
}
.oic-row.oic-selected .oic-idx {
    background: #3b82f6;
    color: #fff;
    border-color: #3b82f6;
}
.oic-code {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    flex-shrink: 0;
    width: 170px;
}
.oic-code-icon {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3b82f6;
    font-size: 12px;
    flex-shrink: 0;
}
.oic-code-text {
    font-weight: 700;
    color: #0f172a;
    font-size: 0.80rem;
    letter-spacing: -0.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.oic-title {
    flex: 1;
    min-width: 0;
    font-size: 0.84rem;
    font-weight: 500;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.oic-qty {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 4px 8px;
    font-size: 0.76rem;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    white-space: nowrap;
}
.oic-qty strong { font-weight: 800; }
.oic-qty em { font-style: normal; color: #64748b; font-weight: 600; font-size: 0.70rem; }
.oic-eye {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    flex-shrink: 0;
}
.oic-eye:hover { background:#f1f5f9; color:#3b82f6; border-color:#bfdbfe; }

/* checkbox inline */
.oic-check {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    border: 1.5px solid #cbd5e1;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: transparent;
    transition: all 0.15s;
    flex-shrink: 0;
}
.oic-check.checked {
    background: #3b82f6;
    border-color: #3b82f6;
    color: #fff;
    box-shadow: 0 1px 4px rgba(59,130,246,0.3);
}

/* skeleton row */
.oic-skeleton-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
}
.oic-skeleton-line {
    height: 10px;
    border-radius: 6px;
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 37%, #f1f5f9 63%);
    background-size: 400% 100%;
    animation: oic-shimmer 1.4s ease infinite;
}
.oic-skeleton-line.w-20{ width:18%; }
.oic-skeleton-line.w-50{ width:48%; }
.oic-skeleton-line.w-60{ width:60%; }
.oic-skeleton-line.w-90{ width:90%; }
.oic-skeleton-line.w-40{ width:40%; }
@keyframes oic-shimmer { 0%{background-position:100% 0} 100%{background-position:-100% 0} }

@media (max-width: 640px){
    .oic-code { width: 120px; }
    .oic-title { font-size: 0.80rem; }
    .oic-list { max-height: 360px; }
}
</style>
