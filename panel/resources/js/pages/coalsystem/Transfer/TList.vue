<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import Swal from 'sweetalert2';

    export default {
        breadcrumbs: { list:[{title:'Transferler', path:'/coalpanel/transfers'}], title:'Transferler' },
        setup(){ return { useNavigationStore, useAuthStore, PickleTable, Plib, Swal } },
        mounted(){ this.navigationStore.toggle(true); this.buildTable(); setTimeout(()=> this.navigationStore.toggle(false),300); },
        data(){ return { plib:new Plib(), navigationStore:useNavigationStore(), authStore:useAuthStore() } },
        methods:{
            searchTable(){ this.table.setFilter([{key:'all', type:'=', value: document.getElementById('mainSearch').value.trim() }]); },
            resetSearch(){ document.getElementById('mainSearch').value=''; this.table.setFilter([]); },
            buildTable(){
                const headers=[
                    {title:'Transfer No', key:'transfer_no', order:true, type:'string'},
                    {title:'Kaynak Sipariş', key:'order_no', order:true, type:'string'},
                    {title:'Tedarikçi', key:'spec_code', order:true, type:'string'},
                    {title:'İmalatçı', key:'imalatci_firma_adi', order:true, type:'string', columnFormatter:(elm,r)=>r.imalatci_firma_adi||'-'},
                    {title:'Durum', key:'status', order:true, width:'180px', type:'string', columnFormatter:(elm,row)=>{
                        const key=String(row.status||'').split('**'); const label=key[1]||key[0]||'-';
                        const btn=document.createElement('span'); btn.classList.add('badge','bg-secondary'); btn.style.padding='6px 12px'; btn.style.borderRadius='20px';
                        if(key[0]==='doc_trans_transfer_approved') btn.className='badge bg-success'; else if(key[0]==='doc_trans_transfer_rejected') btn.className='badge bg-danger'; else if(key[0]==='doc_trans_transfer_sent') btn.className='badge bg-warning text-dark';
                        btn.textContent=label; btn.style.cursor='pointer';
                        btn.onclick=()=>{
                            Swal.fire({ showConfirmButton:false, showCloseButton:true, html:`<div class="d-flex flex-column gap-2"><button class="btn btn-success doc-status" data-key="doc_trans_transfer_approved">Onaylandı</button><button class="btn btn-danger doc-status" data-key="doc_trans_transfer_rejected">Reddedildi</button></div>`, willOpen:()=>{
                                document.querySelectorAll('.doc-status').forEach(b=>b.addEventListener('click', async e=>{
                                    const fd=new FormData(); fd.append('id',row.id); fd.append('op_key', e.target.dataset.key); fd.append('note','Admin transfer durumu');
                                    const rsp=await this.plib.request({url:'/api/v1/trans/set-status', method:'POST'}, null, fd);
                                    if(rsp.success) this.table.updateRow(row.id,{status:e.target.dataset.key+'**'+rsp.data});
                                }))
                            }})
                        };
                        return btn;
                    }},
                    {title:'#', key:'id', order:false, type:'string', columnFormatter:(elm,row)=>{
                        const s=document.createElement('span'); const b=document.createElement('button'); b.className='btn btn-secondary btn-sm'; b.innerHTML='<i class="ki-outline ki-pencil"></i>'; b.onclick=()=> this.$router.push({name:'TransferForm', params:{id:row.id}}); s.appendChild(b);
                        const f=document.createElement('button'); f.className='btn btn-sm btn-light ms-1'; f.innerHTML='<i class="ki-outline ki-document"></i> Döküman'; f.title='Dosya onayla'; f.onclick=()=> this.$router.push({name:'DList'}); s.appendChild(f);
                        return s;
                    }}
                ];
                this.table=new PickleTable({
                    container:'#div_table', headers, pageLimit:10, height:'70vh', type:'ajax', columnSearch:false, paginationType:'number',
                    ajax:{url:'/api/v1/table/documents', data:{}},
                    initialFilter:[{key:'form-type', type:'=', value:'op-doc-transfer-form'},{key:'type', type:'=', value:'op-doc-transfer'}],
                    rowFormatter:(elm,data)=>{ try{ JSON.parse(data.main_attr).forEach(el=> data[el['Key']]=el['Value']); }catch(e){} return data; }
                });
            }
        }
    }
</script>
<template>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex gap-2">
                <input id="mainSearch" class="form-control" placeholder="Transfer ara..." style="width:260px">
                <button class="btn btn-primary" @click="searchTable">Ara</button>
                <button class="btn btn-light" @click="resetSearch">Sıfırla</button>
            </div>
            <router-link :to="{name:'TransferForm'}" class="btn btn-primary"><i class="ki-outline ki-plus"></i> Transfer Oluştur</router-link>
        </div>
        <div class="card-body p-0"><div id="div_table"></div></div>
    </div>
</template>
