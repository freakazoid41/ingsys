<template>
  <div class="tdk-activity">
    <div class="tdk-activity__head">
      <h5 class="tdk-activity__title">Bilgilendirmeler</h5>
      <span class="tdk-activity__badge">{{ items.length }}</span>
    </div>
    <div v-if="loading" class="tdk-activity__loading"><div class="tdk-activity__spinner"></div></div>
    <div v-else-if="!items.length" class="tdk-activity__empty">Henüz bildirim yok</div>
    <div v-else class="tdk-activity__timeline">
      <div v-for="a in items" :key="a.id" class="tdk-activity__item" :class="{ 'is-clickable': !!a.qnid }" @click="goDetail(a)">
        <div class="tdk-activity__avatar" :style="{ background: a.color || '#FF5A1F' }">{{ initials(a) }}</div>
        <div class="tdk-activity__body">
          <div class="tdk-activity__text">{{ a.title }}</div>
          <div v-if="a.orderNo && a.orderNo !== '-'" class="tdk-activity__detail"><i class="ki-outline ki-package" style="font-size:11px"></i> {{ a.orderNo }}<span v-if="a.ctitle" style="opacity:.6"> · {{ a.ctitle }}</span></div>
          <div class="tdk-activity__meta">
            <span class="tdk-activity__time">{{ a.createdFmt }}</span>
          </div>
        </div>
        <span class="tdk-activity__pill" :style="{ background: a.bg, color: a.pillColor, borderColor: a.pillBorder }">{{ a.label }}</span>
      </div>
    </div>
  </div>
</template>
<script>
import { useNavigationStore } from '@/stores/navigation';
import { buildNotificationRows } from '@/lib/notificationHelpers';
import { getCatMeta } from '@/lib/notificationMaps';
export default {
  name: 'PlanlamaActivity',
  data() {
    return {
      navigationStore: useNavigationStore(),
      loading: true,
      rawItems: [],
    };
  },
  computed: {
    items() {
      return this.rawItems.slice(0, 12);
    },
  },
  mounted() {
    this.load();
  },
  watch: {
    'navigationStore.notifications': {
      handler() { this.build(); },
      deep: true,
    },
  },
  methods: {
    async load() {
      try {
        await this.navigationStore.getNotifications();
      } catch (e) { console.error('[PlanlamaActivity] load', e); }
      finally {
        this.build();
        this.loading = false;
      }
    },
    build() {
      try {
        const rows = buildNotificationRows(this.navigationStore?.notifications, []).filter(r => (r.cat || '').startsWith('planlama-'));
        this.rawItems = rows.map(r => {
          const meta = getCatMeta(r.cat);
          return {
            id: r.id,
            title: (r.file_type && r.file_type !== meta.label ? (meta.label + ' \u2014 ' + r.file_type) : meta.label),
            orderNo: r.group_key || '',
            ctitle: r.ctitle || '',
            qnid: r.qnid,
            cat: r.cat,
            createdFmt: r._created_at_fmt || '',
            label: meta.label,
            color: meta.pillBg,
            bg: meta.bg,
            pillColor: meta.color,
            pillBorder: meta.pillBg,
          };
        });
      } catch (e) { console.error('[PlanlamaActivity] build', e); }
    },
    initials(a) {
      const n = (a.title || 'B').trim();
      return n.split(/\s+/).slice(0, 2).map(s => s[0]).join('').toUpperCase().slice(0, 2);
    },
    goDetail(a) {
      if (!a.qnid) return;
      this.navigationStore.markNotificationRead(a.cat, a.qnid).catch(() => {});
      this.$router.push({ name: 'PlanlamaFForm', params: { id: a.qnid } }).catch(() => {});
    },
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
.tdk-activity__item{ display:flex; align-items:flex-start; gap:0.9rem; padding:0.7rem 0; border-bottom:1px solid #fff7ed; transition: background .15s; border-radius:10px; padding-left:0.4rem; padding-right:0.4rem; }
.tdk-activity__item.is-clickable{ cursor:pointer; }
.tdk-activity__item.is-clickable:hover{ background:#fff7ed; }
.tdk-activity__detail{ font-size:0.72rem; color:#ea580c; font-weight:600; display:flex; align-items:center; gap:4px; margin-top:2px; }
.tdk-activity__item:last-child{ border-bottom:none; }
.tdk-activity__avatar{ width:36px; height:36px; border-radius:11px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:0.7rem; font-weight:800; flex-shrink:0; }
.tdk-activity__body{ flex:1; min-width:0; display:flex; flex-direction:column; gap:0.2rem; }
.tdk-activity__text{ font-size:0.84rem; font-weight:700; color:#431407; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.tdk-activity__meta{ display:flex; align-items:center; gap:0.35rem; font-size:0.72rem; color:#9a3412; opacity:.7; font-weight:600; }
.tdk-activity__pill{ font-size:0.65rem; font-weight:800; padding:0.22rem 0.55rem; border-radius:999px; border:1px solid transparent; white-space:nowrap; flex-shrink:0; align-self:center; }
</style>
