<template>
  <div class="order-list-card tedarik-card tedarik-docs-page">
    <div class="tdk-bilgi__header">
      <div class="tdk-bilgi__titleWrap">
        <div class="tdk-bilgi__icon"><i class="ki-outline ki-notification-bing"></i></div>
        <div>
          <h1 class="tdk-bilgi__title">Bilgilendirmeler <span v-if="unreadTotal > 0" class="tdk-bilgi__badge">{{ unreadTotal }}</span></h1>
          <p class="tdk-bilgi__subtitle">{{ unreadTotal > 0 ? `${unreadTotal} okunmamış bildiriminiz var` : 'Tüm bildirimler okundu — tertemiz 🧡' }}</p>
        </div>
      </div>
      <div class="tdk-bilgi__actions">
        <button class="tdk-bilgi__btn tdk-bilgi__btn--ghost" @click="refresh" :disabled="loading">
          <i class="ki-outline ki-arrows-loop" :class="{ spin: loading }"></i> Yenile
        </button>
        <button class="tdk-bilgi__btn tdk-bilgi__btn--primary" @click="markAll" :disabled="unreadTotal===0 || markingAll">
          <i class="ki-outline ki-check"></i> {{ markingAll ? 'İşleniyor…' : 'Tümünü Okundu İşaretle' }}
        </button>
      </div>
    </div>

    <div class="tdk-bilgi__filters">
      <button v-for="f in filterTabs" :key="f.key" class="tdk-bilgi__chip" :class="{ active: activeFilter===f.key }" @click="handleChip(f.key)">
        <i :class="f.icon" style="font-size:15px"></i> {{ f.label }}
        <span class="chip-count" :class="{ 'is-zero': f.count===0 }">{{ f.count }}</span>
      </button>
    </div>

    <div class="tedarik-docs-searchrow">
      <div class="tedarik-docs-searchbox">
        <i class="ki-outline ki-magnifier tedarik-docs-search-icon"></i>
        <input ref="searchInput" class="tedarik-docs-search-input" placeholder="Bildirim ara — sipariş no, firma, tür..." @keydown.enter="searchTable">
      </div>
      <button type="button" class="tedarik-btn-light tedarik-docs-btn" @click="searchTable">Ara</button>
      <button type="button" class="tedarik-btn-light tedarik-docs-btn tedarik-docs-btn--ghost" @click="resetSearch">Sıfırla</button>
    </div>

    <div class="order-list-body">
      <div id="div_table"></div>
      <div v-if="!loading && !rawItems.length" class="tdk-bilgi__empty" style="margin-top:14px">
        <div class="tdk-bilgi__emptyIcon"><i class="ki-outline ki-notification-bing"></i></div>
        <h3>Bildiriminiz yok</h3>
        <p>Yeni siparişler, dosya durumları ve sistem güncellemeleri burada görünecek.</p>
      </div>
      <div v-else-if="!loading && filteredCount===0 && rawItems.length" class="tdk-bilgi__empty" style="margin-top:14px">
        <div class="tdk-bilgi__emptyIcon"><i class="ki-outline ki-magnifier"></i></div>
        <h3>Sonuç bulunamadı</h3>
        <p>Filtreyi değiştirerek diğer bildirimleri görebilirsiniz.</p>
        <button class="tdk-bilgi__btn tdk-bilgi__btn--ghost" @click="handleChip('all')">Tümünü Göster</button>
      </div>
    </div>

    <div v-if="!loading && filteredCount>0" class="tedarik-bottom-note mt-5">
      <i class="ki-outline ki-information-5 me-5"></i>
      <span>Tablodaki bir bildirime tıklamak onu <b>okundu</b> işaretler ve ilgili sipariş / dosyaya götürür.</span>
    </div>
  </div>
</template>

<script>
import { useNavigationStore } from '@/stores/navigation';
import { useAuthStore } from '@/stores/auth';
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import { getCatMeta, CHIP_DEFS } from '@/lib/notificationMaps';
import { buildNotificationRows, isFileCat } from '@/lib/notificationHelpers';

const TABLE_HEIGHT = '60vh';
const TABLE_MIN_WIDTH = '920px';
const HEADERS = [
  { title: ' ', key: 'document_card', order: false, type: 'string', columnFormatter: () => document.createTextNode('') },
  {
    title: 'Belge Başlık', key: 'file_type', order: true, width: '185px', type: 'string',
    columnFormatter: (elm, rowData, col) => {
      const wrap = document.createElement('div'); wrap.className = 'bili-cell bili-cell--title';
      const box = document.createElement('div'); box.className = `bili-icon bili-icon--${rowData.cat}`;
      box.innerHTML = `<i class="${getCatMeta(rowData.cat).icon}" style="font-size:18px;"></i>`;
      const span = document.createElement('span'); span.className = 'bili-title'; span.textContent = col || '—'; span.title = col || '—';
      wrap.append(box, span);
      return wrap;
    }
  },
  {
    title: 'Sipariş / İlişki', key: 'group_key', order: true, width: '265px', type: 'string',
    columnFormatter: (elm, rowData, col) => {
      const orderCode = rowData.group_key || col || '';
      const iliski = rowData.ctitle || '';
      if (iliski && orderCode && iliski !== orderCode) {
        const wrap = document.createElement('div'); wrap.className = 'bili-merged';
        const b1 = document.createElement('span'); b1.className = 'bili-pill bili-pill--order'; b1.textContent = orderCode; b1.title = orderCode;
        const b2 = document.createElement('span'); b2.className = 'bili-pill bili-pill--company'; b2.textContent = iliski; b2.title = iliski;
        wrap.append(b1, b2);
        return wrap;
      }
      const span = document.createElement('span'); span.className = 'bili-single'; span.textContent = (orderCode || iliski || '—'); span.title = span.textContent;
      return span;
    }
  },
  { title: 'Eklenme Tarihi', key: '_created_at_fmt', order: true, width: '135px', type: 'string', columnFormatter: (elm, r) => { const s = document.createElement('span'); s.className = 'bili-date'; s.textContent = r._created_at_fmt || '—'; return s; } },
  {
    title: 'Güncel Durum', key: 'last_status', order: false, width: '175px', type: 'string',
    columnFormatter: (elm, rowData) => {
      const pill = document.createElement('span'); pill.className = `bili-status bili-status--${rowData.cat}`;
      pill.innerHTML = `<i class="${getCatMeta(rowData.cat).icon}" style="font-size:14px;"></i><span>${getCatMeta(rowData.cat).label}</span>`;
      return pill;
    }
  },
  {
    title: 'Detaylar', key: 'id', order: false, colAlign: 'center', headAlign: 'center', width: '160px', type: 'string',
    columnFormatter: (elm, rowData, _col, vm) => {
      const wrap = document.createElement('div'); wrap.className = 'bili-actions';
      const btn = (icon, tip, cls, cb) => {
        const b = document.createElement('button'); b.className = `bili-act ${cls}`; b.title = tip;
        b.innerHTML = `<i class="ki-outline ${icon}" style="font-size:16px;"></i>`;
        b.addEventListener('click', e => { e.stopPropagation(); cb(); });
        return b;
      };
      const txtBtn = (label, icon, tip, cls, cb) => {
        const b = document.createElement('button'); b.className = `bili-act ${cls} is-text`; b.title = tip;
        b.innerHTML = `<i class="ki-outline ${icon}" style="font-size:16px;"></i><span>${label}</span>`;
        b.addEventListener('click', e => { e.stopPropagation(); cb(); });
        return b;
      };
      // vm is `this` bound via .bind(this) in buildTable
      wrap.append(
        btn('ki-eye', 'Gör', 'is-view', () => vm.openItem(rowData)),
        txtBtn('Okundu', 'ki-check', 'Okundu işaretle', 'is-okundu', () => vm.markOne(rowData)),
        btn('ki-arrow-right', 'İlişkiye Git', 'is-go', () => vm.openItem(rowData))
      );
      return wrap;
    }
  },
];

export default {
  name: 'TedarikBilgilendirmeler',
  data() {
    return {
      navigationStore: useNavigationStore(),
      authStore: useAuthStore(),
      plib: new Plib(),
      loading: true,
      markingAll: false,
      activeFilter: 'all',
      rawItems: [],
      filteredCount: 0,
      table: null,
      _buildTimeouts: [],
    };
  },
  computed: {
    unreadTotal() {
      const n = this.navigationStore?.notifications || {};
      return typeof n.unreadTotal === 'number' ? n.unreadTotal : this.rawItems.length;
    },
    filterTabs() {
      const counts = {};
      this.rawItems.forEach(it => { counts[it.cat] = (counts[it.cat] || 0) + 1; });
      return CHIP_DEFS.map(d => ({ ...d, count: d.key === 'all' ? this.rawItems.length : (counts[d.key] || 0) }));
    },
  },
  mounted() {
    this.buildTableWithData([]);
    this.load();
    this._buildTimeouts.push(setTimeout(() => this.handleResponsiveTable(), 400));
  },
  beforeUnmount() {
    this._buildTimeouts.forEach(clearTimeout);
    try { document.getElementById('div_table').innerHTML = ''; } catch {}
  },
  methods: {
    handleResponsiveTable() {
      const tbl = document.querySelector('#div_table .pickletable table');
      if (!tbl) return;
      const isMobile = window.innerWidth < 768;
      tbl.style.minWidth = isMobile ? '100%' : TABLE_MIN_WIDTH;
      tbl.style.width = '100%';
    },
    async load() {
      this.loading = true;
      try {
        await this.navigationStore.getNotifications();
        this.rawItems = buildNotificationRows(this.navigationStore.notifications, this.authStore.currentStatus?.rejectedFiles);
        this.applyFilterToTable();
      } catch (e) { console.error('[Bilgilendirmeler] load', e); }
      finally { this.loading = false; }
    },
    refresh() { return this.load(); },
    getFilteredData() {
      let data = [...this.rawItems];
      if (this.activeFilter !== 'all') data = data.filter(r => r.cat === this.activeFilter);
      const q = (this.$refs.searchInput?.value || '').trim().toLowerCase();
      if (!q) return data;
      return data.filter(r =>
        String(r.file_type || '').toLowerCase().includes(q) ||
        String(r.group_key || '').toLowerCase().includes(q) ||
        String(r.ctitle || '').toLowerCase().includes(q) ||
        String(r.catLabel || '').toLowerCase().includes(q) ||
        String(r._created_at_fmt || '').toLowerCase().includes(q)
      );
    },
    applyFilterToTable() {
      const data = this.getFilteredData();
      this.filteredCount = data.length;
      this.buildTableWithData(data);
    },
    handleChip(key) { this.activeFilter = key; this.applyFilterToTable(); },
    searchTable() { this.applyFilterToTable(); },
    resetSearch() {
      if (this.$refs.searchInput) this.$refs.searchInput.value = '';
      this.activeFilter = 'all';
      this.applyFilterToTable();
    },

    buildTableWithData(dataArray) {
      try { const c = document.getElementById('div_table'); if (c) c.innerHTML = ''; this.table = null; } catch {}
      const isMobile = window.innerWidth < 768;
      // bind vm for columnFormatters that need `this.openItem/markOne`
      const boundHeaders = HEADERS.map(h => {
        if (h.key === 'id') return { ...h, columnFormatter: h.columnFormatter.bind(this) };
        return h;
      });
      this.table = new PickleTable({
        container: '#div_table',
        headers: boundHeaders,
        pageLimit: isMobile ? 5 : 10,
        height: TABLE_HEIGHT,
        type: 'local',
        data: dataArray,
        columnSearch: false,
        paginationType: 'number',
        nextPageIcon: '<i class="ki-outline ki-arrow-right "></i>',
        prevPageIcon: '<i class="ki-outline ki-arrow-left"></i>',
        rowFormatter: (elm, data) => data,
      });
      this.$nextTick(() => {
        const enforce = () => {
          const el = document.querySelector('.tedarik-docs-page .pickletable');
          if (!el) return;
          el.style.setProperty('height', TABLE_HEIGHT, 'important');
          el.style.setProperty('max-height', TABLE_HEIGHT, 'important');
          const dt = el.querySelector('.divTable');
          if (dt) {
            dt.style.setProperty('height', `calc(${TABLE_HEIGHT} - 52px)`, 'important');
            dt.style.setProperty('max-height', `calc(${TABLE_HEIGHT} - 52px)`, 'important');
            dt.style.setProperty('overflow', 'auto', 'important');
          }
        };
        requestAnimationFrame(() => { enforce(); this._buildTimeouts.push(setTimeout(enforce, 400)); });
      });
    },

    async markOne(rowData) {
      try {
        if (rowData.cat === 'rejected') {
          this.rawItems = this.rawItems.filter(i => i.id !== rowData.id);
          this.applyFilterToTable();
          return;
        }
        await this.navigationStore.markNotificationRead(rowData.cat, rowData.qnid);
        this.rawItems = this.rawItems.filter(i => i.id !== rowData.id);
        this.applyFilterToTable();
        this.plib.toast(Swal, 'success', 'Okundu işaretlendi');
      } catch (e) { console.error('[Bilgilendirmeler] markOne', e); }
    },
    async markAll() {
      if (this.markingAll) return;
      this.markingAll = true;
      try {
        await this.navigationStore.markAllNotificationsRead();
        this.rawItems = [];
        this.applyFilterToTable();
        this.plib.toast(Swal, 'success', 'Tüm bildirimler okundu işaretlendi');
      } catch (e) { console.error('[Bilgilendirmeler] markAll', e); }
      finally { this.markingAll = false; }
    },
    openItem(rowData) {
      if (rowData.cat !== 'rejected' && rowData.qnid) {
        this.navigationStore.markNotificationRead(rowData.cat, rowData.qnid).catch(() => {});
        this.rawItems = this.rawItems.filter(i => i.id !== rowData.id);
        this.$nextTick(() => this.applyFilterToTable());
      }
      if (isFileCat(rowData.cat)) this.$router.push({ name: 'TedarikDList' }).catch(() => {});
      else if (rowData.qnid) this.$router.push({ name: 'TedarikOrderForm', params: { id: rowData.qnid } }).catch(() => {});
    },
  },
};
</script>

<style scoped>
.tdk-bilgi__header{ background: linear-gradient(135deg,#fff 0%,#fff7ed 100%); border:1px solid #ffe4cc; border-top:3px solid #FF5A1F; border-radius:18px; padding:1.5rem 1.75rem; display:flex; justify-content:space-between; align-items:center; gap:1rem; box-shadow:0 2px 8px rgba(0,0,0,.04); margin-bottom:14px; }
.tdk-bilgi__titleWrap{ display:flex; align-items:center; gap:1rem; }
.tdk-bilgi__icon{ width:52px; height:52px; border-radius:16px; background: linear-gradient(135deg,#FF5A1F 0%,#ff8a4a 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-size:26px; box-shadow:0 6px 16px rgba(255,90,31,.25); }
.tdk-bilgi__title{ font-size:1.55rem; font-weight:800; color:#1e293b; margin:0; display:flex; align-items:center; gap:.7rem; }
.tdk-bilgi__badge{ background:#FF5A1F; color:#fff; font-size:.78rem; font-weight:800; padding:.18rem .6rem; border-radius:999px; }
.tdk-bilgi__subtitle{ margin:.25rem 0 0; color:#9a3412; opacity:.7; font-size:.88rem; font-weight:500; }
.tdk-bilgi__actions{ display:flex; gap:.6rem; align-items:center; }
.tdk-bilgi__btn{ height:42px; padding:0 16px; border-radius:10px; font-size:.88rem; font-weight:700; display:inline-flex; align-items:center; gap:7px; cursor:pointer; transition:.15s; border:1.5px solid transparent; white-space:nowrap; }
.tdk-bilgi__btn--ghost{ background:#fff; border-color:#e5e7eb; color:#374151; }
.tdk-bilgi__btn--ghost:hover{ border-color:#fed7aa; background:#fff7ed; color:#9a3412; }
.tdk-bilgi__btn--primary{ background:#FF5A1F; color:#fff; border-color:#FF5A1F; box-shadow:0 2px 8px rgba(255,90,31,.18); }
.tdk-bilgi__btn--primary:hover{ background:#ea4a0f; }
.tdk-bilgi__btn:disabled{ opacity:.55; cursor:not-allowed; }
.tdk-bilgi__btn .spin{ animation: spin .7s linear infinite; display:inline-block; }
@keyframes spin{ to{ transform:rotate(360deg);} }
.tdk-bilgi__filters{ display:flex; gap:.55rem; flex-wrap:wrap; padding:.2rem 0 8px;justify-content: center; }
.tdk-bilgi__chip{ height:36px; padding:0 14px; border-radius:999px; background:#fff; border:1.5px solid #e5e7eb; color:#475569; font-size:.84rem; font-weight:700; display:inline-flex; align-items:center; gap:7px; cursor:pointer; transition:.15s; }
.tdk-bilgi__chip:hover{ border-color:#fed7aa; background:#fff7ed; color:#9a3412; }
.tdk-bilgi__chip.active{ background:#FF5A1F; border-color:#FF5A1F; color:#fff; box-shadow:0 2px 8px rgba(255,90,31,.18); }
.tdk-bilgi__chip .chip-count{ min-width:20px; height:20px; padding:0 6px; border-radius:999px; background:#f1f5f9; color:#475569; font-size:.72rem; display:inline-flex; align-items:center; justify-content:center; }
.tdk-bilgi__chip.active .chip-count{ background:rgba(255,255,255,.22); color:#fff; }
.tdk-bilgi__chip .chip-count.is-zero{ opacity:.45; }
.tdk-bilgi__empty{ background:#fff; border:1px dashed #fed7aa; border-radius:18px; padding:3rem 2rem; text-align:center; display:flex; flex-direction:column; align-items:center; gap:.8rem; color:#9a3412; }
.tdk-bilgi__emptyIcon{ width:64px; height:64px; border-radius:20px; background:#fff7ed; border:1px solid #fed7aa; display:flex; align-items:center; justify-content:center; font-size:28px; color:#FF5A1F; }
.tdk-bilgi__empty h3{ margin:0; font-size:1.15rem; font-weight:800; color:#431407; }
.tdk-bilgi__empty p{ margin:0; color:#9a3412; opacity:.7; max-width:420px; font-size:.9rem; }
.tedarik-bottom-note{ margin-top:10px; display:flex; align-items:flex-start; gap:6px; font-size:11.5px; color:#8a8a8e; line-height:1.6; }
.tedarik-bottom-note i{ color:#0e9cb8; font-size:13px; margin-top:2px; flex-shrink:0; }
/* card chrome handled in global .tedarik-docs-page; hide dummy + thead here */
:deep(.pickletable tr:not(.table-group-header) td:first-child),
:deep(.pickletable th:first-child){ display:none; }
:deep(.pickletable thead){ display:none !important; }
:deep(.pickletable table){ border-spacing:0 7px !important; margin-top:4px !important; }
/* icon + pill helpers — no inline style in JS except dynamic cat colors via inline (kept minimal) */
:deep(.bili-cell){ display:flex; align-items:center; gap:10px; }
:deep(.bili-icon){ width:36px; height:36px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; border:1px solid #e2e8f0; }
:deep(.bili-icon--tedarik-01){ background:#fff7ed; color:#7c2d12; border-color:#fed7aa; }
:deep(.bili-icon--tedarik-02){ background:#fef3c7; color:#92400e; border-color:#fde68a; }
:deep(.bili-icon--tedarik-03){ background:#ffedd5; color:#9a3412; border-color:#fed7aa; }
:deep(.bili-icon--tedarik-04){ background:#dcfce7; color:#065f46; border-color:#86efac; }
:deep(.bili-icon--tedarik-05){ background:#fee2e2; color:#991b1b; border-color:#fecaca; }
:deep(.bili-icon--tedarik-06){ background:#d1fae5; color:#064e3b; border-color:#6ee7b7; }
:deep(.bili-icon--tedarik-07){ background:#fee2e2; color:#7f1d1d; border-color:#fca5a5; }
:deep(.bili-title){ font-weight:700; color:#0f172a; font-size:12.5px; display:inline-block; max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:middle; }
:deep(.bili-merged){ display:flex; align-items:center; gap:6px; flex-wrap:nowrap; overflow:hidden; }
:deep(.bili-pill){ display:inline-flex; align-items:center; padding:5px 10px; border-radius:999px; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex-shrink:0; border:1px solid transparent; }
:deep(.bili-pill--order){ font-weight:800; background:#fff7ed; color:#7c2d12; border-color:#fed7aa; max-width:102px; }
:deep(.bili-pill--company){ font-weight:700; background:#f1f5f9; color:#334155; border-color:#e2e8f0; max-width:138px; flex-shrink:1; }
:deep(.bili-single){ color:#334155; display:inline-block; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:middle; font-weight:600; font-size:13px; }
:deep(.bili-date){ color:#334155; white-space:nowrap; font-size:12.5px; font-weight:600; }
:deep(.bili-status){ display:inline-flex; align-items:center; justify-content:center; padding:5px 10px; border-radius:6px; font-size:11.5px; font-weight:600; white-space:nowrap; border:1px solid transparent; gap:6px; }
:deep(.bili-status--tedarik-01){ background:#FF5A1F; color:#fff; border-color:#FF5A1F; }
:deep(.bili-status--tedarik-02){ background:#FF5A1F; color:#fff; border-color:#FF5A1F; }
:deep(.bili-status--tedarik-03){ background:#f59e0b; color:#fff; border-color:#f59e0b; }
:deep(.bili-status--tedarik-04){ background:#22c55e; color:#fff; border-color:#22c55e; }
:deep(.bili-status--tedarik-05){ background:#ef4444; color:#fff; border-color:#ef4444; }
:deep(.bili-status--tedarik-06){ background:#10b981; color:#fff; border-color:#10b981; }
:deep(.bili-status--tedarik-07){ background:#ef4444; color:#fff; border-color:#ef4444; }
:deep(.bili-status--rejected){ background:#ef4444; color:#fff; border-color:#ef4444; }
:deep(.bili-actions){ display:flex; justify-content:flex-end; gap:4px; }
:deep(.bili-act){ width:32px; height:32px; border-radius:8px; border:none; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; transition:background .15s; }
:deep(.bili-act.is-view){ background:#f1f5f9; color:#334155; }
:deep(.bili-act.is-view:hover){ background:#e2e8f0; }
:deep(.bili-act.is-go){ background:#f0fdf4; color:#166534; }
:deep(.bili-act.is-go:hover){ background:#dcfce7; }
:deep(.bili-act.is-okundu){ background:#fff7ed; color:#9a3412; height:32px; width:auto; padding:0 12px; gap:6px; border-radius:8px; font-size:12px; font-weight:700; white-space:nowrap; }
:deep(.bili-act.is-okundu:hover){ background:#ffedd5; }
</style>

<style>
.tedarik-docs-page{ border:none !important; border-radius:0 !important; box-shadow:none !important; background:transparent !important; overflow:visible !important; }
.tedarik-docs-page .order-list-body{ background:transparent !important; flex:0 0 auto; display:block; min-height:0; height:auto; }
.tedarik-docs-page :deep(.pickletable .divTable){ overflow:auto !important; }
.tedarik-docs-page :deep(.pickletable table){ border-collapse:separate !important; border-spacing:0 7px !important; width:100% !important; table-layout:auto !important; border:none !important; }
.tedarik-docs-page :deep(.pickletable tbody tr){ background:transparent !important; box-shadow:none !important; transition: transform 0.15s ease, box-shadow 0.15s ease !important; }
.tedarik-docs-page :deep(.pickletable tbody tr:hover){ transform: translateY(-1px) !important; }
.tedarik-docs-page :deep(.pickletable tbody tr:hover td){ background:#fcfcfc !important; box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important; }
.tedarik-docs-page :deep(.pickletable tbody td){
  background:#fff !important; border:1px solid #e8e8ea !important; font-size:13.5px !important; padding:13px 14px !important; vertical-align:middle !important;
}
.tedarik-docs-page :deep(.pickletable tbody td:first-child){ border-left:1px solid #e8e8ea !important; border-top-left-radius:8px !important; border-bottom-left-radius:8px !important; }
.tedarik-docs-page :deep(.pickletable tbody td:last-child){ border-right:1px solid #e8e8ea !important; border-top-right-radius:8px !important; border-bottom-right-radius:8px !important; }
</style>
