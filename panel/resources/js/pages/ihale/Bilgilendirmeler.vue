<template>
  <div class="ihb-page">
    <section class="ihb-head">
      <div>
        <div class="ihb-eyebrow"><i class="ki-outline ki-notification-status"></i> BİLGİLENDİRMELER</div>
        <h1>İhale Hareketleri</h1>
        <p>İhale ve teklif sürecindeki son gelişmeler.</p>
      </div>
      <div class="ihb-head__actions">
        <span class="ihb-count">{{ rows.length }} kayıt</span>
        <button v-if="rows.length" class="ihb-btn" @click="markAllRead">Tümünü Okundu İşaretle</button>
      </div>
    </section>

    <div class="ihb-filters">
      <button v-for="f in filters" :key="f.key" class="ihb-filter" :class="{ active: activeFilter === f.key }" @click="activeFilter = f.key">
        {{ f.label }}
        <span>{{ f.count }}</span>
      </button>
    </div>

    <section class="ihb-card">
      <div v-if="!filteredRows.length" class="ihb-empty">
        <i class="ki-outline ki-notification-status"></i>
        <b>Bildirim yok</b>
        <span>Yeni ihale veya teklif hareketleri burada görünecek.</span>
      </div>
      <button v-for="row in filteredRows" :key="row.id" class="ihb-row" @click="openRow(row)">
        <span class="ihb-icon" :style="{ background: meta(row.cat).bg, color: meta(row.cat).color }">
          <i :class="meta(row.cat).icon"></i>
        </span>
        <span class="ihb-main">
          <span class="ihb-title">{{ meta(row.cat).label }} <b v-if="row.group_key">· {{ row.group_key }}</b></span>
          <span class="ihb-sub">
            <template v-if="row.ctitle">{{ row.ctitle }} · </template>{{ row._created_at_fmt || '—' }}
          </span>
        </span>
        <span class="ihb-pill" :style="{ background: meta(row.cat).pillBg, color: meta(row.cat).pillColor }">{{ row.catLabel }}</span>
        <i class="ki-outline ki-right ihb-arrow"></i>
      </button>
    </section>
  </div>
</template>

<script>
import { useNavigationStore } from '@/stores/navigation';
import { useAuthStore } from '@/stores/auth';
import { buildNotificationRows, TENDER_CATS } from '@/lib/notificationHelpers';
import { getCatMeta } from '@/lib/notificationMaps';

export default {
  name: 'IhaleBilgilendirmeler',
  setup() {
    return { useNavigationStore, useAuthStore };
  },
  data() {
    return {
      navigationStore: useNavigationStore(),
      authStore: useAuthStore(),
      activeFilter: 'all',
      tick: 0,
    };
  },
  computed: {
    canManage() { return (this.authStore.permissions || []).includes('per-041-03'); },
    rows() {
      // tick makes this recompute when notifications are refreshed
      void this.tick;
      const all = buildNotificationRows(this.navigationStore?.notifications || {}, []);
      return all.filter(r => TENDER_CATS.includes(r.cat));
    },
    filters() {
      const count = (cat) => cat === 'all' ? this.rows.length : this.rows.filter(r => r.cat === cat).length;
      return [
        { key: 'all', label: 'Tümü', count: count('all') },
        { key: 'ihale', label: 'İhaleler', count: this.rows.filter(r => ['ihale-01', 'ihale-02'].includes(r.cat)).length },
        { key: 'offer', label: 'Teklifler', count: this.rows.filter(r => ['offer-01', 'offer-02', 'offer-03'].includes(r.cat)).length },
      ];
    },
    filteredRows() {
      if (this.activeFilter === 'all') return this.rows;
      if (this.activeFilter === 'ihale') return this.rows.filter(r => ['ihale-01', 'ihale-02'].includes(r.cat));
      return this.rows.filter(r => ['offer-01', 'offer-02', 'offer-03'].includes(r.cat));
    },
  },
  mounted() {
    this.navigationStore.getNotifications();
    this._timer = setInterval(() => { this.tick++; }, 4000);
  },
  beforeUnmount() {
    clearInterval(this._timer);
  },
  watch: {
    'navigationStore.notifications': { handler() { this.tick++; }, deep: true },
  },
  methods: {
    meta(cat) { return getCatMeta(cat); },
    openRow(row) {
      if (row.cat !== 'rejected' && row.qnid) {
        this.navigationStore.markNotificationRead(row.cat, row.qnid).catch(() => {});
      }
      if (['ihale-01', 'ihale-02'].includes(row.cat)) {
        this.$router.push({ name: 'IhalePanelIhList' });
        return;
      }
      if (row.qnid) this.$router.push({ name: 'IhalePanelIhOffer', params: { id: row.qnid } });
    },
    async markAllRead() {
      try { await this.navigationStore.markAllNotificationsRead(['ihale-01','ihale-02','offer-01','offer-02','offer-03']); } catch (e) {}
      this.tick++;
    },
  },
};
</script>

<style scoped>
.ihb-page { padding: 1.5rem 1.25rem 2.5rem; display: flex; flex-direction: column; gap: 1.1rem; width: 100%; box-sizing: border-box; }
.ihb-page * { box-sizing: border-box; }
.ihb-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; }
.ihb-eyebrow { display: flex; align-items: center; gap: .45rem; font-size: .68rem; font-weight: 800; letter-spacing: .08em; color: #047857; }
.ihb-head h1 { margin: .3rem 0 .2rem; font-size: 1.5rem; font-weight: 800; color: #0f172a; }
.ihb-head p { margin: 0; font-size: .86rem; color: #64748b; }
.ihb-head__actions { display: flex; align-items: center; gap: .7rem; }
.ihb-count { font-size: .74rem; font-weight: 800; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 999px; padding: .3rem .7rem; }
.ihb-btn { border: 1px solid #e2e8f0; background: #fff; color: #334155; border-radius: 10px; padding: .55rem .85rem; font-size: .76rem; font-weight: 700; cursor: pointer; }
.ihb-btn:hover { border-color: #a7f3d0; }
.ihb-filters { display: flex; gap: .5rem; flex-wrap: wrap; }
.ihb-filter { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid #e2e8f0; background: #fff; color: #64748b; border-radius: 999px; padding: .42rem .8rem; font-size: .75rem; font-weight: 700; cursor: pointer; }
.ihb-filter span { background: #f1f5f9; color: #475569; border-radius: 999px; padding: .08rem .4rem; font-size: .68rem; }
.ihb-filter.active { background: #059669; border-color: #059669; color: #fff; }
.ihb-filter.active span { background: rgba(255,255,255,.2); color: #fff; }
.ihb-card { background: #fff; border: 1px solid #eef2f7; border-radius: 18px; overflow: hidden; }
.ihb-row { display: flex; align-items: center; gap: .85rem; width: 100%; border: 0; border-bottom: 1px solid #f1f5f9; background: #fff; padding: .85rem 1.1rem; cursor: pointer; text-align: left; transition: background .15s; }
.ihb-row:last-child { border-bottom: 0; }
.ihb-row:hover { background: #f8fafc; }
.ihb-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 19px; flex-shrink: 0; }
.ihb-main { display: flex; flex-direction: column; gap: .12rem; flex: 1; min-width: 0; }
.ihb-title { font-size: .82rem; font-weight: 800; color: #0f172a; }
.ihb-title b { color: #059669; }
.ihb-sub { font-size: .72rem; color: #94a3b8; }
.ihb-pill { font-size: .64rem; font-weight: 800; border-radius: 999px; padding: .22rem .55rem; white-space: nowrap; }
.ihb-arrow { color: #cbd5e1; font-size: 14px; }
.ihb-empty { padding: 3rem 1rem; text-align: center; display: flex; flex-direction: column; align-items: center; gap: .45rem; color: #94a3b8; }
.ihb-empty i { font-size: 30px; color: #cbd5e1; }
.ihb-empty b { color: #475569; font-size: .9rem; }
.ihb-empty span { font-size: .78rem; }
@media (max-width: 768px) { .ihb-page { padding: 1rem; } .ihb-head { flex-direction: column; align-items: flex-start; } }
</style>
