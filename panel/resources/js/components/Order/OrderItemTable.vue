<script>
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';
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
        return { plib: new Plib(), table: null, resolvedParentId: null, selected: {}, itemCount: 0 }
    },
    computed:{
        containerId(){ return 'order-item-table-'+this.orderId+this.containerSuffix; }
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
            }
            this.buildTable(numericId);
        },
        buildTable(parentId){
            const headers = [
                { title:'Ürün Kodu', key:'prod_code', order:true, type:'string', columnFormatter:(elm,row)=>{
                    const v = row.prod_code || '-';
                    const wrap = document.createElement('div');
                    wrap.style.display = 'flex';
                    wrap.style.alignItems = 'center';
                    wrap.style.gap = '8px';
                    const icon = document.createElement('div');
                    icon.style.cssText = 'width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#eff6ff,#dbeafe);display:flex;align-items:center;justify-content:center;flex-shrink:0;';
                    icon.innerHTML = '<i class="ki-outline ki-box" style="font-size:14px;color:#3b82f6;"></i>';
                    const textWrap = document.createElement('div');
                    const code = document.createElement('span');
                    code.textContent = v;
                    code.style.cssText = 'font-weight:700;color:#0f172a;font-size:0.88rem;letter-spacing:-0.01em;';
                    code.title = v;
                    textWrap.appendChild(code);
                    wrap.appendChild(icon);
                    wrap.appendChild(textWrap);
                    return wrap;
                }},
                { title:'Ürün Adı', key:'title', order:true, type:'string', columnFormatter:(elm,row)=>{
                    const v = row.title || '-';
                    const s = document.createElement('span');
                    s.textContent = v.length > 32 ? v.substring(0,32)+'…' : v;
                    s.title = v;
                    s.style.cssText = 'color:#334155;font-size:0.86rem;line-height:1.3;';
                    return s;
                }},
                { title:'Miktar', key:'quantity', order:true, width:'130px', type:'string', columnFormatter:(elm,row)=>{
                    const wrap = document.createElement('div');
                    wrap.style.cssText = 'display:flex;align-items:center;gap:6px;justify-content:flex-end;';
                    const qty = document.createElement('span');
                    qty.textContent = row.quantity || '-';
                    qty.style.cssText = 'font-weight:700;color:#0f172a;font-size:0.9rem;font-variant-numeric:tabular-nums;';
                    wrap.appendChild(qty);
                    if(row.unit){
                        const badge = document.createElement('span');
                        badge.textContent = row.unit;
                        badge.style.cssText = 'background:#f1f5f9;color:#64748b;font-size:0.7rem;font-weight:600;padding:2px 7px;border-radius:6px;border:1px solid #e2e8f0;';
                        wrap.appendChild(badge);
                    }
                    return wrap;
                }},
                { title:'', key:'id', order:false, width:'50px', type:'string', columnFormatter:(elm,row)=>{
                    const btn = document.createElement('button');
                    btn.innerHTML = '<i class="ki-outline ki-eye" style="font-size:15px;"></i>';
                    btn.title = 'Kalem detayı';
                    btn.style.cssText = 'background:none;border:1px solid #e2e8f0;width:32px;height:32px;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;color:#94a3b8;transition:all 0.15s;';
                    btn.onmouseenter = () => { btn.style.background='#f1f5f9'; btn.style.color='#3b82f6'; btn.style.borderColor='#bfdbfe'; };
                    btn.onmouseleave = () => { btn.style.background='none'; btn.style.color='#94a3b8'; btn.style.borderColor='#e2e8f0'; };
                    btn.onclick = () => Swal.fire({
                        title: row.title || 'Kalem',
                        html: `<div style="text-align:left;display:flex;flex-direction:column;gap:12px;padding:4px 0;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#eff6ff,#dbeafe);display:flex;align-items:center;justify-content:center;"><i class="ki-outline ki-box" style="color:#3b82f6;"></i></div>
                                <div><div style="font-size:0.72rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Ürün Kodu</div><div style="font-weight:700;color:#0f172a;font-size:0.95rem;">${row.prod_code||'-'}</div></div>
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
                    return btn;
                }}
            ];
            if(this.selectable){
                headers.unshift({
                    title:'', key:'select', order:false, width:'40px', type:'string',
                    columnFormatter:(elm,row)=>{
                        const wrap = document.createElement('div');
                        wrap.style.cssText = 'display:flex;align-items:center;justify-content:center;';
                        const cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.style.cssText = 'width:17px;height:17px;cursor:pointer;accent-color:#154b91;border-radius:4px;';
                        cb.checked = !!this.selected[row.id];
                        cb.onchange = () => { this.selected[row.id] = cb.checked; this.notifySelect(); };
                        wrap.appendChild(cb);
                        return wrap;
                    }
                });
            }
            const filter = [
                { key:'form-type', type:'=', value:'op-doc-order-item-form' },
                { key:'type', type:'=', value:'op-doc-order-item' },
            ];
            if(parentId){
                filter.push({ key:'parent_id', type:'=', value: String(parentId) });
            }
            this.table = new PickleTable({
                container: '#'+this.containerId,
                headers, pageLimit:10, height:'auto', type:'ajax', columnSearch:false, paginationType:'number',
                ajax:{ url:'/api/v1/table/documents', data:{} },
                initialFilter: filter,
                nextPageIcon:'<i class="ki-outline ki-arrow-right"></i>', prevPageIcon:'<i class="ki-outline ki-arrow-left"></i>',
                rowFormatter:(elm,data)=>{
                    try{ JSON.parse(data.main_attr||'[]').forEach(el=> data[el['Key']]=el['Value']); }catch(e){}
                    data.prod_code = data['prod_code']||'-';
                    data.title = data['title']||'-';
                    data.quantity = data['quantity']||'-';
                    data.unit = data['unit']||'';
                    this.itemCount++;
                    return data;
                }
            });
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
                    <span class="order-item-subtitle" v-if="itemCount">{{ itemCount }} kalem listeleniyor</span>
                </div>
            </div>
            <span class="order-item-badge" v-if="resolvedParentId">#{{ resolvedParentId }}</span>
        </div>
        <div class="order-item-body">
            <div :id="containerId"></div>
            <div class="order-item-empty" v-if="!itemCount">
                <i class="ki-outline ki-question-circle" style="font-size:28px;color:#cbd5e1;"></i>
                <span style="color:#94a3b8;font-size:0.85rem;">Henüz kalem eklenmemiş</span>
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
    min-height: 48px;
}
.order-item-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 28px 16px;
}
:deep(.pickletable table){
    border-collapse: separate !important;
    border-spacing: 0 !important;
    width: 100%;
}
:deep(.pickletable thead th){
    background: #f8fafc !important;
    color: #64748b !important;
    font-size: 0.72rem !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.04em !important;
    padding: 11px 16px !important;
    border-bottom: 1px solid #e2e8f0 !important;
    border-top: none !important;
}
:deep(.pickletable tbody tr){
    background: #fff !important;
    transition: all 0.15s ease !important;
}
:deep(.pickletable tbody tr:hover){
    background: #f8fafc !important;
}
:deep(.pickletable tbody td){
    padding: 12px 16px !important;
    font-size: 0.86rem !important;
    border-bottom: 1px solid #f1f5f9 !important;
    border-top: none !important;
    border-left: none !important;
    border-right: none !important;
    vertical-align: middle !important;
}
:deep(.pickletable tbody tr:last-child td){
    border-bottom: none !important;
}
:deep(.pickletable .divPagination){
    background: #fff !important;
    border-top: 1px solid #f1f5f9 !important;
    padding: 10px 16px !important;
}
</style>
