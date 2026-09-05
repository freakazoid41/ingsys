<template>
  <div class="adm-files">
    <div class="adm-files__head">
      <h5 class="adm-files__title">Son Dosyalar</h5>
      <router-link :to="{ name: 'DList' }" class="adm-files__link">Tüm Dökümanlar →</router-link>
    </div>
    <div v-if="loading" class="adm-files__loading"><div class="adm-files__spinner"></div></div>
    <div v-else-if="!files.length" class="adm-files__empty">Henüz dosya yüklenmedi</div>
    <div v-else class="adm-files__list">
      <div v-for="f in files" :key="f.qnid || f.id" class="adm-file" @click="go(f.qnid || f.id)">
        <div class="adm-file__icon" :class="iconCls(f)"><i :class="iconFor(f)"></i></div>
        <div class="adm-file__body">
          <span class="adm-file__group">{{ f.group_key || '—' }}</span>
          <span class="adm-file__meta">{{ f.ctitle || 'Bilinmeyen Firma' }} · {{ fmtDate(f.created_at) }}</span>
        </div>
        <span class="adm-file__pill" :class="pillCls(f)">{{ pillLabel(f) }}</span>
      </div>
    </div>
  </div>
</template>
<script>
import Plib from '@/lib/pickle';
export default {
  name: 'AdminFiles',
  data(){ return { loading:true, files:[] }; },
  mounted(){ this.load(); },
  methods:{
    async load(){
      try{
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/admin-files', method: 'GET' }, null);
        if(Array.isArray(rsp)) this.files = rsp;
      }catch(e){ console.error('admin-files failed',e); }
      finally{ this.loading=false; }
    },
    parseStatus(f){
      try{
        const j = typeof f.last_status === 'string' ? JSON.parse(f.last_status) : f.last_status;
        return j || {};
      }catch{ return {}; }
    },
    pillLabel(f){
      const s = this.parseStatus(f);
      const m = { 'doc_file_waiting':'Bekliyor', 'doc_file_accepted':'Onaylandı', 'doc_file_rejected':'Reddedildi', 'doc_file_refreshed':'Yenilendi' };
      return m[s.op_key] || s.title || 'Bilinmiyor';
    },
    pillCls(f){
      const k = this.parseStatus(f).op_key;
      if(k==='doc_file_waiting') return 'adm-pill--amber';
      if(k==='doc_file_accepted') return 'adm-pill--green';
      if(k==='doc_file_rejected') return 'adm-pill--red';
      return 'adm-pill--gray';
    },
    iconFor(f){
      const tag=(f.entity_tag||'').split('**')[0];
      if(tag==='transfer_kabul_file') return 'ki-outline ki-clipboard';
      if(tag==='transfer_cins_file') return 'ki-outline ki-chart-simple';
      if(tag==='item_test_file') return 'ki-outline ki-flask';
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
    go(id){ this.$router.push({ name: 'DForm', params:{ id } }).catch(()=>{}); }
  }
};
</script>
<style scoped>
.adm-files{ background:#fff; border-radius:18px; padding:1.5rem; border:1px solid #eef2f7; box-shadow:0 1px 3px rgba(0,0,0,0.04); display:flex; flex-direction:column; }
.adm-files__head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.adm-files__title{ font-size:0.95rem; font-weight:800; color:#0f172a; margin:0; }
.adm-files__link{ font-size:0.8rem; font-weight:700; color:#154b91; text-decoration:none; }
.adm-files__link:hover{ color:#0f3a6b; }
.adm-files__loading{ display:flex; justify-content:center; padding:2rem; }
.adm-files__spinner{ width:28px; height:28px; border:3px solid #e5e7eb; border-top-color:#154b91; border-radius:50%; animation: adm-spin 0.6s linear infinite; }
@keyframes adm-spin{ to{ transform:rotate(360deg);} }
.adm-files__empty{ text-align:center; padding:2rem; color:#9ca3af; font-size:0.9rem; }
.adm-files__list{ display:flex; flex-direction:column; gap:0.6rem; max-height:420px; overflow-y:auto; padding-right:4px; }
.adm-file{ display:flex; align-items:center; gap:0.9rem; padding:0.85rem 1rem; border:1px solid #f1f5f9; border-radius:14px; cursor:pointer; transition: all 0.15s; background:#fff; }
.adm-file:hover{ background:#f8fafc; border-color:#e2e8f0; transform: translateY(-1px); }
.adm-file__icon{ width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; background:#f1f5f9; color:#64748b; }
.adm-file__icon.is-kabul{ background:#ecfdf5; color:#059669; }
.adm-file__icon.is-cins{ background:#fff7ed; color:#d97706; }
.adm-file__icon.is-test{ background:#fef3ff; color:#a855f7; }
.adm-file__body{ flex:1; min-width:0; display:flex; flex-direction:column; gap:0.15rem; }
.adm-file__group{ font-size:0.85rem; font-weight:800; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.adm-file__meta{ font-size:0.75rem; color:#94a3b8; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.adm-file__pill{ font-size:0.7rem; font-weight:800; padding:0.25rem 0.6rem; border-radius:999px; white-space:nowrap; border:1px solid transparent; flex-shrink:0; }
.adm-pill--amber{ background:#fef3c7; color:#92400e; border-color:#fde68a; }
.adm-pill--green{ background:#dcfce7; color:#166534; border-color:#bbf7d0; }
.adm-pill--red{ background:#fee2e2; color:#991b1b; border-color:#fecaca; }
.adm-pill--gray{ background:#f1f5f9; color:#475569; border-color:#e2e8f0; }
</style>
