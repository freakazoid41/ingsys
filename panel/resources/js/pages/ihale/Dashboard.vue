<template>
  <div class="ihp-dashboard">
    <!-- hero -->
    <section class="ihp-hero">
      <div class="ihp-hero__text">
        <div class="ihp-eyebrow"><i class="ki-outline ki-chart-simple"></i> İHALE YÖNETİM PANELİ</div>
        <h1 class="ihp-title">Hoş geldiniz, <span>{{ userName }}</span></h1>
        <p class="ihp-subtitle">İhaleleri oluşturun, teklifleri izleyin ve süreci kazanana kadar yönetin.</p>
      </div>
      <div class="ihp-hero__actions">
        <button v-if="canManage" class="ihp-btn ihp-btn--primary" @click="goNewTender">
          <i class="ki-outline ki-plus fs-4"></i> Yeni İhale
        </button>
        <button class="ihp-btn" @click="goTenders">
          <i class="ki-outline ki-chart-simple fs-4"></i> İhaleler
        </button>
        <button v-if="canManage" class="ihp-btn" @click="goOffers">
          <i class="ki-outline ki-document fs-4"></i> Teklifler
        </button>
      </div>
    </section>

    <!-- stats -->
    <section class="ihp-stats">
      <button v-for="s in statCards" :key="s.key" class="ihp-stat" :class="{ 'ihp-stat--accent': s.accent }" @click="s.go()">
        <span class="ihp-stat__icon" :style="{ background: s.bg, color: s.color }"><i :class="s.icon"></i></span>
        <span class="ihp-stat__body">
          <span class="ihp-stat__label">{{ s.label }}</span>
          <span class="ihp-stat__value" :style="{ color: s.color }">
            <span v-if="loading" class="ihp-spinner"></span>
            <template v-else>{{ s.value }}</template>
          </span>
          <span class="ihp-stat__sub">{{ s.sub }}</span>
        </span>
        <i class="ki-outline ki-arrow-right ihp-stat__arrow"></i>
      </button>
    </section>

    <div class="ihp-grid ihp-grid--2">
      <!-- tender status -->
      <section class="ihp-card">
        <header class="ihp-card__head">
          <div>
            <h2>İhale Durumu</h2>
            <p>Aktif süreçlerin dağılımı</p>
          </div>
          <span class="ihp-chip">{{ stats.tenders.total }} ihale</span>
        </header>
        <div class="ihp-status-list">
          <div v-for="row in tenderStatusRows" :key="row.key" class="ihp-status-row">
            <span class="ihp-status-dot" :style="{ background: row.color }"></span>
            <span class="ihp-status-name">{{ row.label }}</span>
            <span class="ihp-status-bar"><span :style="{ width: row.pct + '%', background: row.color }"></span></span>
            <span class="ihp-status-count">{{ row.value }}</span>
          </div>
        </div>
        <div class="ihp-mini">
          <span><b>{{ stats.tenders.total }}</b> toplam ihale</span>
          <span><b>{{ stats.offers.total }}</b> teklif</span>
        </div>
      </section>

      <!-- recent tenders -->
      <section class="ihp-card">
        <header class="ihp-card__head">
          <div>
            <h2>Son İhaleler</h2>
            <p>En yeni 5 ihale</p>
          </div>
          <button class="ihp-link" @click="goTenders">Tümü <i class="ki-outline ki-right fs-5"></i></button>
        </header>
        <div v-if="loading" class="ihp-empty">Yükleniyor...</div>
        <div v-else-if="!recentTenders.length" class="ihp-empty">Henüz ihale oluşturulmamış.</div>
        <div v-else class="ihp-list">
          <button v-for="t in recentTenders" :key="t.qnid" class="ihp-row" @click="openTender(t)">
            <span class="ihp-row__main">
              <span class="ihp-code">{{ t.kod }}</span>
              <span class="ihp-row__meta">{{ t.product_count }} kalem · {{ fmtDate(t.created_at) }}</span>
            </span>
            <span class="ihp-pill" :class="tenderPill(t.status).cls">{{ tenderPill(t.status).label }}</span>
            <i class="ki-outline ki-right ihp-row__arrow"></i>
          </button>
        </div>
      </section>
    </div>

    <div class="ihp-grid ihp-grid--2">
      <!-- recent offers -->
      <section class="ihp-card">
        <header class="ihp-card__head">
          <div>
            <h2>{{ canManage ? 'Son Teklifler' : 'Tekliflerim' }}</h2>
            <p>{{ canManage ? 'Tedarikçilerden gelen son teklifler' : 'Son teklif hareketleriniz' }}</p>
          </div>
          <button v-if="canManage" class="ihp-link" @click="goOffers">Tümü <i class="ki-outline ki-right fs-5"></i></button>
        </header>
        <div v-if="loading" class="ihp-empty">Yükleniyor...</div>
        <div v-else-if="!recentOffers.length" class="ihp-empty">Henüz teklif yok.</div>
        <div v-else class="ihp-list">
          <button v-for="o in recentOffers" :key="o.qnid" class="ihp-row" @click="openOffer(o)">
            <span class="ihp-row__main">
              <span class="ihp-code">{{ o.kod }}</span>
              <span class="ihp-row__meta">{{ o.supplier_title }} · {{ fmtDate(o.created_at) }}</span>
            </span>
            <span class="ihp-pill" :class="offerPill(o.status).cls">{{ offerPill(o.status).label }}</span>
            <i class="ki-outline ki-right ihp-row__arrow"></i>
          </button>
        </div>
      </section>

      <!-- monthly -->
      <section class="ihp-card">
        <header class="ihp-card__head">
          <div>
            <h2>Aylık Akış</h2>
            <p>Son 6 ay · ihale / teklif</p>
          </div>
          <span class="ihp-chip"><i class="ki-outline ki-chart fs-6"></i></span>
        </header>
        <div class="ihp-months">
          <div v-for="(label, i) in monthly.labels" :key="label" class="ihp-month">
            <div class="ihp-month__bars">
              <span class="ihp-month__bar ihp-month__bar--tender" :style="{ height: barHeight(monthly.tenders[i]) }" :title="'İhale: ' + monthly.tenders[i]"></span>
              <span class="ihp-month__bar ihp-month__bar--offer" :style="{ height: barHeight(monthly.offers[i]) }" :title="'Teklif: ' + monthly.offers[i]"></span>
            </div>
            <span class="ihp-month__label">{{ label }}</span>
          </div>
        </div>
        <div class="ihp-legend">
          <span><i class="ihp-dot ihp-dot--tender"></i> İhale</span>
          <span><i class="ihp-dot ihp-dot--offer"></i> Teklif</span>
        </div>
      </section>
    </div>

    <!-- quick -->
    <section class="ihp-quick">
      <button v-if="canManage" class="ihp-quick__card" @click="goNewTender">
        <i class="ki-outline ki-plus-square ihp-quick__icon"></i>
        <span><b>Yeni İhale Oluştur</b><small>Ürün, şartname ve son teklif tarihi</small></span>
      </button>
      <button class="ihp-quick__card" @click="goTenders">
        <i class="ki-outline ki-chart-simple ihp-quick__icon"></i>
        <span><b>İhaleleri Görüntüle</b><small>Durum, ürün ve şartname takibi</small></span>
      </button>
      <button v-if="canManage" class="ihp-quick__card" @click="goOffers">
        <i class="ki-outline ki-document ihp-quick__icon"></i>
        <span><b>Teklifleri Değerlendir</b><small>Kabul, red ve kazanan seçimi</small></span>
      </button>
      <button class="ihp-quick__card" @click="goNotifications">
        <i class="ki-outline ki-notification-status ihp-quick__icon"></i>
        <span><b>Bilgilendirmeler</b><small>İhale ve teklif hareketleri</small></span>
      </button>
    </section>
  </div>
</template>

<script>
import Plib from '@/lib/pickle';
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';

export default {
  name: 'IhaleDashboard',
  setup() {
    return { useAuthStore, useNavigationStore };
  },
  data() {
    return {
      authStore: useAuthStore(),
      navigationStore: useNavigationStore(),
      loading: true,
      stats: { tenders: { total: 0, created: 0, active: 0, completed: 0 }, offers: { total: 0, submitted: 0, rejected: 0, accepted: 0 } },
      recentTenders: [],
      recentOffers: [],
      monthly: { labels: [], tenders: [], offers: [] },
    };
  },
  computed: {
    userName() { return this.authStore.userName || this.authStore.currentStatus?.main_name || 'Kullanıcı'; },
    perms() { return this.authStore.permissions || []; },
    canManage() { return this.perms.includes('per-041-03'); },
    tenderStatusRows() {
      const max = Math.max(1, this.stats.tenders.created, this.stats.tenders.active, this.stats.tenders.completed);
      return [
        { key: 'created', label: 'Oluşturuldu', value: this.stats.tenders.created, color: '#94a3b8', pct: Math.round(this.stats.tenders.created / max * 100) },
        { key: 'active', label: 'Aktif', value: this.stats.tenders.active, color: '#059669', pct: Math.round(this.stats.tenders.active / max * 100) },
        { key: 'completed', label: 'Tamamlandı', value: this.stats.tenders.completed, color: '#2563eb', pct: Math.round(this.stats.tenders.completed / max * 100) },
      ];
    },
    statCards() {
      const t = this.stats.tenders;
      const o = this.stats.offers;
      return [
        { key: 'total', label: 'TOPLAM İHALE', value: t.total, sub: 'Tüm kayıtlar', icon: 'ki-outline ki-chart-simple', bg: '#ecfdf5', color: '#059669', accent: true, go: () => this.goTenders() },
        { key: 'active', label: 'AKTİF İHALE', value: t.active, sub: 'Teklif alımı açık', icon: 'ki-outline ki-rocket', bg: '#d1fae5', color: '#047857', go: () => this.goTenders() },
        { key: 'completed', label: 'TAMAMLANAN', value: t.completed, sub: 'Kazanan seçildi', icon: 'ki-outline ki-flag', bg: '#dbeafe', color: '#2563eb', go: () => this.goTenders() },
        { key: 'offers', label: 'TOPLAM TEKLİF', value: o.total, sub: 'Gelen teklifler', icon: 'ki-outline ki-document', bg: '#fef3c7', color: '#b45309', go: () => this.goOffers() },
        { key: 'pending', label: 'ONAY BEKLEYEN', value: o.submitted, sub: 'Değerlendirme', icon: 'ki-outline ki-time', bg: '#ffedd5', color: '#c2410c', go: () => this.goOffers() },
        { key: 'accepted', label: 'KABUL EDİLEN', value: o.accepted, sub: 'Kazanan teklif', icon: 'ki-outline ki-check-circle', bg: '#dcfce7', color: '#16a34a', go: () => this.goOffers() },
      ];
    },
    maxMonthly() {
      return Math.max(1, ...(this.monthly.tenders || []), ...(this.monthly.offers || []));
    },
  },
  async mounted() {
    await this.load();
    try { this.navigationStore.getNotifications(); } catch (e) {}
  },
  methods: {
    async load() {
      this.loading = true;
      try {
        const rsp = await (new Plib).request({ url: '/api/v1/dashboard/ihale-stats', method: 'GET' }, null);
        if (rsp && typeof rsp === 'object') {
          if (rsp.tenders) this.stats.tenders = { ...this.stats.tenders, ...rsp.tenders };
          if (rsp.offers) this.stats.offers = { ...this.stats.offers, ...rsp.offers };
          this.recentTenders = Array.isArray(rsp.recentTenders) ? rsp.recentTenders : [];
          this.recentOffers = Array.isArray(rsp.recentOffers) ? rsp.recentOffers : [];
          if (rsp.monthly) this.monthly = rsp.monthly;
        }
      } catch (e) {
        console.error('ihale-stats failed', e);
      } finally {
        this.loading = false;
      }
    },
    barHeight(v) {
      return Math.max(6, Math.round(((Number(v) || 0) / this.maxMonthly) * 100)) + '%';
    },
    fmtDate(v) {
      if (!v) return '—';
      const d = new Date(v);
      if (isNaN(d.getTime())) return String(v).slice(0, 10);
      return d.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    },
    tenderPill(status) {
      if (status === 'tender-activated') return { label: 'İhale Aktif', cls: 'is-active' };
      if (status === 'tender-complated') return { label: 'Tamamlandı', cls: 'is-done' };
      return { label: 'Oluşturuldu', cls: 'is-created' };
    },
    offerPill(status) {
      if (status === 'offer-accepted') return { label: 'Kabul', cls: 'is-done' };
      if (status === 'offer-rejected') return { label: 'Red', cls: 'is-rejected' };
      return { label: 'Yeni', cls: 'is-active' };
    },
    goNewTender() { this.$router.push({ name: 'IhalePanelIhForm' }); },
    goTenders() { this.$router.push({ name: 'IhalePanelIhList' }); },
    goOffers() { this.$router.push({ name: this.canManage ? 'IhalePanelIhOfferList' : 'IhalePanelIhList' }); },
    goNotifications() { this.$router.push({ name: 'IhalePanelBilgilendirmeler' }); },
    openTender(t) {
      if (this.canManage) this.$router.push({ name: 'IhalePanelIhForm', params: { id: t.qnid } });
      else this.$router.push({ name: 'IhalePanelIhOffer', query: { ihale: t.qnid } });
    },
    openOffer(o) {
      this.$router.push({ name: 'IhalePanelIhOffer', params: { id: o.qnid } });
    },
  },
};
</script>

<style scoped>
.ihp-dashboard {
  padding: 1.5rem 1.25rem 2.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}
.ihp-dashboard * { box-sizing: border-box; }
.ihp-hero {
  background: linear-gradient(135deg, #ffffff 0%, #f0fdf7 100%);
  border: 1px solid #d1fae5;
  border-radius: 20px;
  padding: 1.6rem 1.75rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.25rem;
  box-shadow: 0 8px 24px rgba(5, 150, 105, .06);
}
.ihp-eyebrow { display: flex; align-items: center; gap: .45rem; font-size: .68rem; font-weight: 800; letter-spacing: .08em; color: #047857; }
.ihp-title { margin: .35rem 0 .2rem; font-size: 1.6rem; font-weight: 800; color: #0f172a; }
.ihp-title span { color: #059669; }
.ihp-subtitle { margin: 0; font-size: .9rem; color: #64748b; }
.ihp-hero__actions { display: flex; gap: .6rem; flex-wrap: wrap; }
.ihp-btn {
  display: inline-flex; align-items: center; gap: .45rem;
  border: 1px solid #e2e8f0; background: #fff; color: #334155;
  border-radius: 11px; padding: .65rem 1rem; font-size: .82rem; font-weight: 700; cursor: pointer;
  transition: all .18s ease;
}
.ihp-btn:hover { border-color: #a7f3d0; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(15,23,42,.06); }
.ihp-btn--primary { background: #059669; color: #fff; border-color: #059669; }
.ihp-btn--primary:hover { background: #047857; border-color: #047857; box-shadow: 0 8px 20px rgba(5,150,105,.28); }
.ihp-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
.ihp-stat {
  position: relative; overflow: hidden; text-align: left;
  background: #fff; border: 1px solid #eef2f7; border-radius: 18px;
  padding: 1.1rem 1.2rem; display: flex; align-items: center; gap: .9rem;
  cursor: pointer; transition: all .2s ease;
}
.ihp-stat::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 3px; background: transparent; }
.ihp-stat--accent::before { background: linear-gradient(90deg, #059669, #34d399); }
.ihp-stat:hover { transform: translateY(-3px); border-color: #a7f3d0; box-shadow: 0 14px 28px rgba(5,150,105,.10); }
.ihp-stat:hover .ihp-stat__arrow { opacity: 1; transform: translateX(0); }
.ihp-stat__icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 21px; flex-shrink: 0; }
.ihp-stat__body { display: flex; flex-direction: column; gap: .12rem; flex: 1; min-width: 0; }
.ihp-stat__label { font-size: .66rem; font-weight: 800; letter-spacing: .07em; color: #64748b; }
.ihp-stat__value { font-size: 1.6rem; font-weight: 800; line-height: 1.05; }
.ihp-stat__sub { font-size: .7rem; color: #94a3b8; font-weight: 600; }
.ihp-stat__arrow { font-size: 15px; color: #059669; opacity: 0; transform: translateX(-6px); transition: all .2s; }
.ihp-spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid #e5e7eb; border-top-color: #059669; border-radius: 50%; animation: ihp-spin .6s linear infinite; }
@keyframes ihp-spin { to { transform: rotate(360deg); } }
.ihp-grid { display: grid; gap: 1.25rem; width: 100%; min-width: 0; }
.ihp-grid--2 { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
.ihp-card { background: #fff; border: 1px solid #eef2f7; border-radius: 18px; padding: 1.25rem 1.35rem; min-width: 0; }
.ihp-card__head { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; margin-bottom: 1rem; }
.ihp-card__head h2 { margin: 0; font-size: .98rem; font-weight: 800; color: #0f172a; }
.ihp-card__head p { margin: .2rem 0 0; font-size: .75rem; color: #94a3b8; }
.ihp-chip { display: inline-flex; align-items: center; gap: .35rem; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; border-radius: 999px; padding: .28rem .7rem; font-size: .72rem; font-weight: 700; }
.ihp-link { border: 0; background: transparent; color: #059669; font-size: .76rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: .15rem; }
.ihp-status-list { display: flex; flex-direction: column; gap: .8rem; }
.ihp-status-row { display: grid; grid-template-columns: 10px 90px 1fr 38px; align-items: center; gap: .55rem; }
.ihp-status-dot { width: 9px; height: 9px; border-radius: 50%; }
.ihp-status-name { font-size: .78rem; font-weight: 600; color: #475569; }
.ihp-status-bar { height: 8px; border-radius: 999px; background: #f1f5f9; overflow: hidden; }
.ihp-status-bar span { display: block; height: 100%; border-radius: 999px; transition: width .35s ease; }
.ihp-status-count { text-align: right; font-size: .82rem; font-weight: 800; color: #0f172a; }
.ihp-mini { display: flex; gap: 1.25rem; margin-top: 1rem; padding-top: .9rem; border-top: 1px dashed #e2e8f0; font-size: .75rem; color: #64748b; }
.ihp-mini b { color: #059669; }
.ihp-list { display: flex; flex-direction: column; }
.ihp-row { display: flex; align-items: center; gap: .7rem; width: 100%; border: 0; background: transparent; padding: .65rem .25rem; border-bottom: 1px solid #f1f5f9; cursor: pointer; text-align: left; border-radius: 8px; transition: background .15s; }
.ihp-row:last-child { border-bottom: 0; }
.ihp-row:hover { background: #f8fafc; }
.ihp-row__main { display: flex; flex-direction: column; gap: .1rem; flex: 1; min-width: 0; }
.ihp-code { font-size: .82rem; font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ihp-row__meta { font-size: .7rem; color: #94a3b8; }
.ihp-row__arrow { color: #cbd5e1; font-size: 14px; }
.ihp-pill { font-size: .66rem; font-weight: 800; padding: .25rem .55rem; border-radius: 999px; white-space: nowrap; }
.ihp-pill.is-created { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.ihp-pill.is-active { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.ihp-pill.is-done { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.ihp-pill.is-rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.ihp-empty { padding: 1.5rem; text-align: center; color: #94a3b8; font-size: .82rem; }
.ihp-months { display: flex; align-items: flex-end; gap: .65rem; height: 150px; padding: .25rem 0 .5rem; }
.ihp-month { flex: 1; display: flex; flex-direction: column; align-items: center; gap: .45rem; height: 100%; justify-content: flex-end; min-width: 0; }
.ihp-month__bars { display: flex; align-items: flex-end; gap: 3px; height: 118px; width: 100%; justify-content: center; }
.ihp-month__bar { width: 12px; border-radius: 4px 4px 2px 2px; min-height: 6px; transition: height .35s ease; }
.ihp-month__bar--tender { background: #059669; }
.ihp-month__bar--offer { background: #bfdbfe; }
.ihp-month__label { font-size: .66rem; font-weight: 700; color: #94a3b8; white-space: nowrap; }
.ihp-legend { display: flex; gap: 1rem; justify-content: center; font-size: .7rem; color: #64748b; margin-top: .35rem; }
.ihp-legend span { display: inline-flex; align-items: center; gap: .35rem; }
.ihp-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.ihp-dot--tender { background: #059669; }
.ihp-dot--offer { background: #bfdbfe; }
.ihp-quick { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
.ihp-quick__card { display: flex; align-items: center; gap: .8rem; text-align: left; background: #fff; border: 1px solid #eef2f7; border-radius: 16px; padding: 1rem 1.1rem; cursor: pointer; transition: all .2s ease; }
.ihp-quick__card:hover { transform: translateY(-3px); border-color: #a7f3d0; box-shadow: 0 12px 24px rgba(5,150,105,.10); }
.ihp-quick__icon { font-size: 24px; color: #059669; }
.ihp-quick__card b { display: block; font-size: .82rem; color: #0f172a; font-weight: 800; }
.ihp-quick__card small { display: block; font-size: .7rem; color: #94a3b8; margin-top: .1rem; }
@media (max-width: 1100px) { .ihp-grid--2 { grid-template-columns: minmax(0, 1fr); } .ihp-quick { grid-template-columns: repeat(2, minmax(0,1fr)); } }
@media (max-width: 768px) { .ihp-dashboard { padding: 1rem; } .ihp-stats { grid-template-columns: repeat(2, minmax(0,1fr)); } .ihp-hero { flex-direction: column; align-items: flex-start; } .ihp-quick { grid-template-columns: 1fr; } }
</style>
