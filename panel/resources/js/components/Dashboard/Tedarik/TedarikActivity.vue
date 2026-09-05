<template>
  <div class="tdk-activity">
    <div class="tdk-activity__head">
      <h5 class="tdk-activity__title">Bilgilendirmeler</h5>
      <span class="tdk-activity__badge">{{ items.length }}</span>
    </div>
    <div v-if="loading" class="tdk-activity__loading"><div class="tdk-activity__spinner"></div></div>
    <div v-else-if="!items.length" class="tdk-activity__empty">Henüz sipariş işlemi yok</div>
    <div v-else class="tdk-activity__timeline">
      <div v-for="a in items" :key="a.id" class="tdk-activity__item">
        <div class="tdk-activity__avatar" :style="{ background: avatarBg(a) }">{{ initials(a) }}</div>
        <div class="tdk-activity__body">
          <div class="tdk-activity__text">{{ a.desc_text || a.title }}</div>
          <div class="tdk-activity__meta">
            <span class="tdk-activity__actor">{{ a.actor_name || a.actor_email || 'Sistem' }}</span>
            <span class="tdk-activity__dot">·</span>
            <span class="tdk-activity__time">{{ fmtTime(a.created_at) }}</span>
          </div>
        </div>
        <span class="tdk-activity__pill" :class="pillCls(a)">{{ pillLabel(a) }}</span>
      </div>
    </div>
  </div>
</template>
<script>
import Plib from '@/lib/pickle';
export default {
  name: 'TedarikActivity',
  data(){ return { loading:true, items:[] }; },
  mounted(){ this.load(); },
  methods:{
    async load(){
      try{
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/tedarik-activity', method: 'GET' }, null);
        if(Array.isArray(rsp)) this.items = rsp;
      }catch(e){ console.error('tedarik-activity failed',e); }
      finally{ this.loading=false; }
    },
    initials(a){
      const n=(a.actor_name||a.actor_email||'S').trim();
      return n.split(/\s+/).slice(0,2).map(s=>s[0]).join('').toUpperCase().slice(0,2);
    },
    avatarBg(a){
      const h=(a.actor_email||a.actor_name||'').split('').reduce((s,c)=>s+c.charCodeAt(0),0);
      const hues=['#FF5A1F','#f59e0b','#7c3aed','#e11d48','#059669','#0ea5e9'];
      return hues[h % hues.length];
    },
    fmtTime(v){
      if(!v) return '—';
      try{ const d=new Date(v); const now=new Date(); const diff=Math.floor((now-d)/60000); if(diff<1) return 'şimdi'; if(diff<60) return diff+' dk'; if(diff<1440) return Math.floor(diff/60)+' sa'; return d.toLocaleDateString('tr-TR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}); }catch{ return v.slice(0,16); }
    },
    pillLabel(a){
      const k=(a.op_key||'');
      if(k.includes('file')) return 'Dosya';
      if(k.includes('order')||k.includes('tender')||k.includes('document')) return 'Sipariş';
      return 'İşlem';
    },
    pillCls(a){
      const k=(a.op_key||'');
      if(k.includes('file')) return 'is-file';
      return 'is-order';
    }
  }
};
</script>
<style scoped>
.tdk-activity{ background:#fff; border-radius:18px; padding:1.5rem; border:1px solid #ffe4cc; box-shadow:0 1px 3px rgba(255,90,31,.06); display:flex; flex-direction:column; }
.tdk-activity__head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.tdk-activity__title{ font-size:0.95rem; font-weight:800; color:#431407; margin:0; }
.tdk-activity__badge{ font-size:0.7rem; font-weight:800; background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; padding:0.2rem 0.6rem; border-radius:999px; }
.tdk-activity__loading{ display:flex; justify-content:center; padding:2rem; }
.tdk-activity__spinner{ width:28px; height:28px; border:3px solid #ffedd5; border-top-color:#FF5A1F; border-radius:50%; animation: tdk-spin 0.6s linear infinite; }
@keyframes tdk-spin{ to{ transform:rotate(360deg);} }
.tdk-activity__empty{ text-align:center; padding:2rem; color:#9ca3af; font-size:0.9rem; }
.tdk-activity__timeline{ display:flex; flex-direction:column; gap:0.7rem; max-height:380px; overflow-y:auto; padding-right:4px; }
.tdk-activity__item{ display:flex; align-items:flex-start; gap:0.9rem; padding:0.7rem 0; border-bottom:1px solid #fff7ed; }
.tdk-activity__item:last-child{ border-bottom:none; }
.tdk-activity__avatar{ width:36px; height:36px; border-radius:11px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:0.7rem; font-weight:800; flex-shrink:0; }
.tdk-activity__body{ flex:1; min-width:0; display:flex; flex-direction:column; gap:0.2rem; }
.tdk-activity__text{ font-size:0.84rem; font-weight:700; color:#431407; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.tdk-activity__meta{ display:flex; align-items:center; gap:0.35rem; font-size:0.72rem; color:#9a3412; opacity:.7; font-weight:600; }
.tdk-activity__actor{ color:#7c2d12; max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tdk-activity__dot{ color:#fed7aa; }
.tdk-activity__pill{ font-size:0.65rem; font-weight:800; padding:0.22rem 0.55rem; border-radius:999px; border:1px solid transparent; white-space:nowrap; flex-shrink:0; align-self:center; }
.tdk-activity__pill.is-file{ background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
.tdk-activity__pill.is-login{ background:#fef9c3; color:#854d0e; border-color:#fde68a; }
.tdk-activity__pill.is-order{ background:#ffedd5; color:#9a3412; border-color:#fed7aa; }
</style>
