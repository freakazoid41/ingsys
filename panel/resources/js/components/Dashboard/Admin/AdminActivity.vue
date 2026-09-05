<template>
  <div class="adm-activity">
    <div class="adm-activity__head">
      <h5 class="adm-activity__title">Son Hareketler</h5>
      <router-link :to="{ name: 'LList' }" class="adm-activity__link">Loglar →</router-link>
    </div>
    <div v-if="loading" class="adm-activity__loading"><div class="adm-activity__spinner"></div></div>
    <div v-else-if="!items.length" class="adm-activity__empty">Henüz hareket yok</div>
    <div v-else class="adm-activity__timeline">
      <div v-for="a in items" :key="a.id" class="adm-activity__item">
        <div class="adm-activity__avatar" :style="{ background: avatarBg(a) }">{{ initials(a) }}</div>
        <div class="adm-activity__body">
          <div class="adm-activity__text">{{ a.desc_text || a.title }}</div>
          <div class="adm-activity__meta">
            <span class="adm-activity__actor">{{ a.actor_name || a.actor_email || 'Sistem' }}</span>
            <span class="adm-activity__dot">·</span>
            <span class="adm-activity__time">{{ fmtTime(a.created_at) }}</span>
          </div>
        </div>
        <span class="adm-activity__badge" :class="badgeCls(a)">{{ badgeLabel(a) }}</span>
      </div>
    </div>
  </div>
</template>
<script>
import Plib from '@/lib/pickle';
export default {
  name: 'AdminActivity',
  data(){ return { loading:true, items:[] }; },
  mounted(){ this.load(); },
  methods:{
    async load(){
      try{
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/admin-activity', method: 'GET' }, null);
        if(Array.isArray(rsp)) this.items = rsp;
      }catch(e){ console.error('admin-activity failed',e); }
      finally{ this.loading=false; }
    },
    initials(a){
      const n=(a.actor_name||a.actor_email||'S').trim();
      return n.split(/\s+/).slice(0,2).map(s=>s[0]).join('').toUpperCase().slice(0,2);
    },
    avatarBg(a){
      const h = (a.actor_email||a.actor_name||'').split('').reduce((s,c)=>s+c.charCodeAt(0),0);
      const hues=['#154b91','#0e7490','#6d28d9','#be185d','#047857','#9a3412'];
      return hues[h % hues.length];
    },
    fmtTime(v){
      if(!v) return '—';
      try{ const d=new Date(v); const now=new Date(); const diff=Math.floor((now-d)/60000); if(diff<1) return 'şimdi'; if(diff<60) return diff+' dk'; if(diff<1440) return Math.floor(diff/60)+' sa'; return d.toLocaleDateString('tr-TR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}); }catch{ return v.slice(0,16); }
    },
    badgeLabel(a){
      const k=(a.op_key||'');
      if(k.includes('login')) return 'Giriş';
      if(k.includes('tender')||k.includes('document')) return 'Sipariş';
      if(k.includes('file')) return 'Dosya';
      if(k.includes('logout')) return 'Çıkış';
      return 'İşlem';
    },
    badgeCls(a){
      const k=(a.op_key||'');
      if(k.includes('login')) return 'is-login';
      if(k.includes('file')) return 'is-file';
      if(k.includes('logout')) return 'is-logout';
      return 'is-order';
    }
  }
};
</script>
<style scoped>
.adm-activity{ background:#fff; border-radius:18px; padding:1.5rem; border:1px solid #eef2f7; box-shadow:0 1px 3px rgba(0,0,0,0.04); display:flex; flex-direction:column; }
.adm-activity__head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.adm-activity__title{ font-size:0.95rem; font-weight:800; color:#0f172a; margin:0; }
.adm-activity__link{ font-size:0.8rem; font-weight:700; color:#154b91; text-decoration:none; }
.adm-activity__link:hover{ color:#0f3a6b; }
.adm-activity__loading{ display:flex; justify-content:center; padding:2rem; }
.adm-activity__spinner{ width:28px; height:28px; border:3px solid #e5e7eb; border-top-color:#154b91; border-radius:50%; animation: adm-spin 0.6s linear infinite; }
@keyframes adm-spin{ to{ transform:rotate(360deg);} }
.adm-activity__empty{ text-align:center; padding:2rem; color:#9ca3af; font-size:0.9rem; }
.adm-activity__timeline{ display:flex; flex-direction:column; gap:0.7rem; max-height:420px; overflow-y:auto; padding-right:4px; }
.adm-activity__item{ display:flex; align-items:flex-start; gap:0.9rem; padding:0.7rem 0; border-bottom:1px solid #f8fafc; }
.adm-activity__item:last-child{ border-bottom:none; }
.adm-activity__avatar{ width:38px; height:38px; border-radius:11px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:0.72rem; font-weight:800; flex-shrink:0; letter-spacing:0.04em; }
.adm-activity__body{ flex:1; min-width:0; display:flex; flex-direction:column; gap:0.2rem; }
.adm-activity__text{ font-size:0.84rem; font-weight:700; color:#0f172a; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.adm-activity__meta{ display:flex; align-items:center; gap:0.35rem; font-size:0.73rem; color:#94a3b8; font-weight:600; }
.adm-activity__actor{ color:#475569; max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.adm-activity__dot{ color:#cbd5e1; }
.adm-activity__badge{ font-size:0.65rem; font-weight:800; padding:0.22rem 0.55rem; border-radius:999px; border:1px solid transparent; white-space:nowrap; flex-shrink:0; align-self:center; }
.adm-activity__badge.is-login{ background:#eff6ff; color:#1e40af; border-color:#bfdbfe; }
.adm-activity__badge.is-order{ background:#f0fdf4; color:#166534; border-color:#bbf7d0; }
.adm-activity__badge.is-file{ background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
.adm-activity__badge.is-logout{ background:#f1f5f9; color:#475569; border-color:#e2e8f0; }
</style>
