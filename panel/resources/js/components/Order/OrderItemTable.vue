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
        return { plib: new Plib(), table: null, resolvedParentId: null, selected: {} }
    },
    computed:{
        containerId(){ return 'order-item-table-'+this.orderId+this.containerSuffix; }
    },
    emits: ['select'],
    methods:{
        getSelected(){
            return Object.keys(this.selected).filter(k => this.selected[k]);
        },
        notifySelect(){
            this.$emit('select', this.getSelected());
        },
        async resolveAndBuild(){
            // Prefer the numeric id passed by the parent (rawData.document.id).
            let numericId = this.orderNumericId;
            // Fallback: resolve the numeric id from the qnid via the document endpoint.
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
                    const v=row.prod_code||'-';
                    const s=document.createElement('span'); s.textContent=v; s.style.fontWeight='600'; s.style.color='#0f172a'; s.title=v; return s;
                }},
                { title:'Ürün Adı', key:'title', order:true, type:'string' },
                { title:'Miktar', key:'quantity', order:true, width:'110px', type:'string', columnFormatter:(elm,row)=>{
                    const s=document.createElement('span'); s.textContent=(row.quantity||'-')+' '+(row.unit||''); s.style.color='#334155'; return s;
                }},
                { title:'', key:'id', order:false, width:'80px', type:'string', columnFormatter:(elm,row)=>{
                    const wrap=document.createElement('div'); wrap.style.display='flex'; wrap.style.justifyContent='flex-end';
                    const btn=document.createElement('button'); btn.className='btn btn-sm btn-light'; btn.innerHTML='<i class="ki-outline ki-eye"></i>'; btn.title='Kalem detayı'; btn.onclick=()=> Swal.fire({title:row.title||'Kalem', html:`<div style="text-align:left"><div><b>Ürün Kodu:</b> ${row.prod_code||'-'}</div><div><b>Miktar:</b> ${row.quantity||'-'} ${row.unit||''}</div></div>`, showCloseButton:true, showConfirmButton:false});
                    wrap.appendChild(btn);
                    return wrap;
                }}
            ];
            if(this.selectable){
                headers.unshift({
                    title:'', key:'select', order:false, width:'40px', type:'string',
                    columnFormatter:(elm,row)=>{
                        const cb=document.createElement('input');
                        cb.type='checkbox';
                        cb.style.width='18px'; cb.style.height='18px'; cb.style.cursor='pointer'; cb.style.accentColor='#154b91';
                        cb.checked=!!this.selected[row.id];
                        cb.onchange=()=>{ this.selected[row.id] = cb.checked; this.notifySelect(); };
                        return cb;
                    }
                });
            }
            // PickleTable requires ajax.data with scale, and initialFilter with parent_id
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
                    return data;
                }
            });
        }
    }
}
</script>
<template>
    <div class="card" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
        <div class="card-header" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
            <h4 style="margin:0;font-size:0.95rem;font-weight:700;color:#0f172a;">Sipariş Kalemleri</h4>
            <span class="badge bg-light text-muted" style="border:1px solid #e2e8f0;">parent_id = {{ resolvedParentId || '?' }}</span>
        </div>
        <div class="card-body p-0" style="background:#fff;">
            <div :id="containerId"></div>
        </div>
    </div>
</template>
<style scoped>
:deep(.pickletable thead th){ background:#f8fafc !important; color:#64748b !important; font-size:0.78rem !important; }
:deep(.pickletable tbody td){ padding:10px 12px !important; font-size:0.85rem !important; }
:deep(.pickletable table){ border-collapse:collapse !important; }
</style>
