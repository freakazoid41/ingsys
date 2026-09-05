<template>
  <div class="tdk-files">
    <div class="tdk-files__head">
      <h5 class="tdk-files__title">Son Dosyalar</h5>
      <router-link to="/tedarikpanel/documents" class="tdk-files__link">Tümü →</router-link>
    </div>
    <div v-if="loading" class="tdk-files__loading"><div class="tdk-files__spinner"></div></div>
    <div v-else-if="!files.length" class="tdk-files__empty">Henüz dosya yok</div>
    <div v-else class="tdk-files__list">
      <div v-for="f in files" :key="f.qnid || f.id" class="tdk-file" @click="go(f.qnid || f.id)">
        <div class="tdk-file__icon" :class="iconCls(f)"><i :class="iconFor(f)"></i></div>
        <div class="tdk-file__body">
          <span class="tdk-file__group">{{ f.group_key || '—' }}</span>
          <span class="tdk-file__meta">{{ f.ctitle || 'Bilinmeyen' }} · {{ fmtDate(f.created_at) }}</span>
        </div>
        <span class="tdk-file__pill" :class="pillCls(f)">{{ pillLabel(f) }}</span>
      </div>
    </div>
  </div>
</template>
<script>
import Plib from '@/lib/pickle';
export default {
  name: 'TedarikFiles',
  data(){ return { loading:true, files:[] }; },
  mounted(){ this.load(); },
  methods:{
    async load(){
      try{
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/tedarik-files', method: 'GET' }, null);
        if(Array.isArray(rsp)) this.files = rsp;
      }catch(e){ console.error('tedarik-files failed',e); }
      finally{ this.loading=false; }
    },
    parseStatus(f){
      try{ const j=typeof f.last_status==='string'?JSON.parse(f.last_status):f.last_status; return j||{}; }catch{ return {}; }
    },
    pillLabel(f){
      const s=this.parseStatus(f); const m={ 'doc_file_waiting':'Bekliyor', 'doc_file_accepted':'Onaylandı', 'doc_file_rejected':'Reddedildi', 'doc_file_refreshed':'Yenilendi' };
      return m[s.op_key]||s.title||'—';
    },
    pillCls(f){
      const k=this.parseStatus(f).op_key;
      if(k==='doc_file_waiting') return 'tdk-pill--amber';
      if(k==='doc_file_accepted') return 'tdk-pill--green';
      if(k==='doc_file_rejected') return 'tdk-pill--red';
      return 'tdk-pill--gray';
    },
    iconFor(f){
      const tag=(f.entity_tag||'').split('**')[0];
      if(tag==='transfer_kabul_file') return 'ki-outline ki-clipboard';
      if(tag==='transfer_cins_file') return 'ki-outline ki-chart-simple';
      if(tag==='item_test_file') return 'ki-outline ki-shield-tick';
      return 'ki-outline ki-file-added';
    },
    iconCls(f){
      const tag=(f.entity_tag||'').split('**')[0];
      if(tag==='transfer_kabul_file') return 'is-kabul';
      if(tag==='transfer_cins_file') return 'is-cins';
      if(tag==='item_test_file') return 'is-test';
      return '';
    },
    fmtDate(v){ if(!v) return '—'; try{ return new Date(v).toLocaleDateString('tr-TR',{day:'2-digit',month:'2-digit',year:'numeric'});}catch{ return v.slice(0,10);} },
    go(id){ this.$router.push({ name: 'TedarikDForm', params:{ id }}).catch(()=>{}); }
  }
};
</script>
<style scoped>
.tdk-files{ background:#fff; border-radius:18px; padding:1.5rem; border:1px solid #ffe4cc; box-shadow:0 1px 3px rgba(255,90,31,.06); display:flex; flex-direction:column; }
.tdk-files__head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.tdk-files__title{ font-size:0.95rem; font-weight:800; color:#431407; margin:0; }
.tdk-files__link{ font-size:0.8rem; font-weight:700; color:#FF5A1F; text-decoration:none; }
.tdk-files__link:hover{ color:#c2410c; }
.tdk-files__loading{ display:flex; justify-content:center; padding:2rem; }
.tdk-files__spinner{ width:28px; height:28px; border:3px solid #ffedd5; border-top-color:#FF5A1F; border-radius:50%; animation: tdk-spin 0.6s linear infinite; }
@keyframes tdk-spin{ to{ transform:rotate(360deg);} }
.tdk-files__empty{ text-align:center; padding:2rem; color:#9ca3af; font-size:0.9rem; }
.tdk-files__list{ display:flex; flex-direction:column; gap:0.65rem; max-height:380px; overflow-y:auto; padding-right:4px; }
.tdk-file{ display:flex; align-items:center; gap:0.9rem; padding:0.85rem 1rem; border:1px solid #fff7ed; border-radius:14px; cursor:pointer; transition: all 0.15s; background:#fff; }
.tdk-file:hover{ background:#fff7ed; border-color:#fed7aa; transform: translateY(-1px); }
.tdk-file__icon{ width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; background:#fff7ed; color:#9a3412; }
.tdk-file__icon.is-kabul{ background:#ecfdf5; color:#059669; }
.tdk-file__icon.is-cins{ background:#fff7ed; color:#d97706; }
.tdk-file__icon.is-test{ background:#fef3ff; color:#a855f7; }
.tdk-file__body{ flex:1; min-width:0; display:flex; flex-direction:column; gap:0.15rem; }
.tdk-file__group{ font-size:0.85rem; font-weight:800; color:#431407; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tdk-file__meta{ font-size:0.75rem; color:#9a3412; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; opacity:.8; }
.tdk-file__pill{ font-size:0.7rem; font-weight:800; padding:0.25rem 0.6rem; border-radius:999px; white-space:nowrap; border:1px solid transparent; flex-shrink:0; }
.tdk-pill--amber{ background:#fef3c7; color:#92400e; border-color:#fde68a; }
.tdk-pill--green{ background:#dcfce7; color:#166534; border-color:#bbf7d0; }
.tdk-pill--red{ background:#fee2e2; color:#991b1b; border-color:#fecaca; }
.tdk-pill--gray{ background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
</style>
