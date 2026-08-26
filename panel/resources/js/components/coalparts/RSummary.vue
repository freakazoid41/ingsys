<script>
// Field schema mirrors Form.vue:439+ op-doc-request-form — update both if form changes.
import AppFab from '@/components/coalparts/AppFab.vue';

const STATUS_MAP = {
    doc_trans_created:           { label: 'Taslak',      color: '#6b7280', bg: 'rgba(107,114,128,0.10)', dot: '#6b7280' },
    doc_trans_request_start:     { label: 'Başladı',     color: '#d97706', bg: 'rgba(217,119,6,0.10)',   dot: '#d97706' },
    doc_trans_request_end:       { label: 'Tamamlandı',  color: '#059669', bg: 'rgba(5,150,105,0.10)',   dot: '#059669' },
    doc_trans_request_cancelled: { label: 'İptal',       color: '#dc2626', bg: 'rgba(220,38,38,0.10)',   dot: '#dc2626' },
};

function parseDate(str) {
    if (!str) return null;
    const ddmm = str.match(/^(\d{1,2})[.\/\-](\d{1,2})[.\/\-](\d{4})$/);
    if (ddmm) return new Date(+ddmm[3], +ddmm[2] - 1, +ddmm[1]);
    const iso = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) return new Date(+iso[1], +iso[2] - 1, +iso[3]);
    return null;
}

const TR_MONTHS = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

function fmtDate(d) {
    if (!d) return null;
    return `${d.getDate()} ${TR_MONTHS[d.getMonth()]} ${d.getFullYear()}`;
}

export default {
    components: { AppFab },
    props: {
        entities:         { type: Object,   default: () => ({}) },
        document:         { type: Object,   default: () => ({}) },
        addOfferCallback: { type: Function },
    },
    computed: {
        statusInfo() {
            let key = 'doc_trans_created';
            try {
                const raw = this.document?.status;
                if (Array.isArray(raw) && raw.length)
                    key = raw[raw.length - 1]?.op_key ?? key;
                else if (typeof raw === 'string' && raw.startsWith('[')) {
                    const p = JSON.parse(raw);
                    if (p.length) key = p[p.length - 1]?.op_key ?? key;
                } else if (typeof raw === 'string' && raw.includes('**'))
                    key = raw.split('**')[0] || key;
            } catch (_) {}
            return STATUS_MAP[key] ?? STATUS_MAP.doc_trans_created;
        },
        logoSrc() {
            const t = this.entities?.target_type ?? '';
            if (t.includes('ÇATES') || t.toUpperCase().includes('CATES')) return '/coaltheme/CATES.svg';
            if (t.includes('Yatağan') || t.toUpperCase().includes('YATAGAN')) return '/coaltheme/YATAGAN.svg';
            return null;
        },
        bothLogos() {
            return (this.entities?.target_type ?? '').includes('Her İkisi');
        },
        timeline() {
            const s = parseDate(this.entities?.contract_start_date);
            const e = parseDate(this.entities?.contract_end_date);
            if (!s || !e) return null;
            const today     = new Date(); today.setHours(0,0,0,0);
            const total     = Math.round((e - s) / 86400000);
            const elapsed   = Math.round((today - s) / 86400000);
            const remaining = Math.max(Math.round((e - today) / 86400000), 0);
            const pct       = total > 0 ? Math.min(Math.max((elapsed / total) * 100, 0), 100) : 0;
            return { startLabel: fmtDate(s), endLabel: fmtDate(e), pct, remaining, total, expired: today > e };
        },
        coalSpecs() {
            return [
                { key: 'calory',      label: 'Kalori (AID)' },
                { key: 'coal_size',   label: 'Ebat (%)' },
                { key: 'coal_hgi',    label: 'HGİ (%)' },
                { key: 'coal_ucucu',  label: 'Uçucu Madde (%)' },
                { key: 'coal_type',   label: 'Cinsi' },
                
                { key: 'humidity',    label: 'Nem (%)' },
                { key: 'ash_content', label: 'Kül Oranı (%)' },
                { key: 'sulfur',      label: 'Kükürt (%)' },
            ].filter(f => this.val(f.key) !== '—');
        },
        priceImpacts() {
            return [
                { key: 'fuel_price_impact',   label: 'Akaryakıt Etkilenme Oranı' },
                { key: 'tiufe_price_impact',  label: 'Tİ-ÜFE + TÜFE Etkilenme' },
                { key: 'fuel_price_impact_2', label: 'Akaryakıttan Etkilenmeyecek Oran' },
            ].filter(f => this.val(f.key) !== '—');
        },
        orderFields() {
            return [
                { key: 'amount',              label: 'Miktar (Ton)' },
                { key: 'payment_due',         label: 'Ödeme Vadesi' },
                { key: 'payment_periods',     label: 'Hakediş Dönemleri' },
                { key: 'payment_desc',        label: 'Hakediş Açıklama' },
                { key: 'transfer_start_date', label: 'Sevkiyata Başlangıç' },
                { key: 'transfer_end_date',   label: 'Sevkiyata Bitiş' },
                
                { key: 'desc',                label: 'Ek Açıklama' },
            ].filter(f => this.val(f.key) !== '—');
        },
        caloryRows() {
            const rows = {};
            for (const key in this.entities) {
                const parts = key.split('**');
                if (parts.length === 3 && parts[1] === 'calory_settings') {
                    if (!rows[parts[2]]) rows[parts[2]] = {};
                    rows[parts[2]][parts[0]] = this.entities[key];
                }
            }
            return Object.values(rows).filter(r =>
                r.prime_condition_is || r.prime_condition_is_bellow || r.prime_unit_price
            );
        },
    },
    methods: {
        val(key) {
            const v = this.entities?.[key];
            return (v === undefined || v === null || v === '') ? '—' : v;
        },
    },
};
</script>

<template>
<div class="rs-wrap pb-5">

    <!-- ══ HERO ══ -->
    <div class="rs-hero mb-4">
        <div class="rs-hero-inner">
            <!-- Top row: label + logos -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="rs-eyebrow">Talep Detayı</span>
                <div class="d-flex align-items-center gap-3">
                    <template v-if="bothLogos">
                        <img src="/coaltheme/CATES.svg"   class="rs-logo" alt="ÇATES" />
                        <img src="/coaltheme/YATAGAN.svg" class="rs-logo" alt="Yatağan" />
                    </template>
                    <img v-else-if="logoSrc" :src="logoSrc" class="rs-logo" alt="" />
                </div>
            </div>

            <!-- Title + status -->
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <h2 class="rs-title mb-0">{{ val('title') }}</h2>
                <span class="rs-status-badge"
                      :style="{ color: statusInfo.color, background: statusInfo.bg, borderColor: statusInfo.color + '40' }">
                    <span class="rs-dot" :style="{ background: statusInfo.dot }"></span>
                    {{ statusInfo.label }}
                </span>
            </div>

            <!-- Meta chips -->
            <div class="d-flex flex-wrap gap-2">
                <span class="rs-chip" v-if="val('date') !== '—'">
                    <i class="ki-outline ki-calendar fs-7"></i> {{ val('date') }}
                </span>
                <span class="rs-chip" v-if="val('target_type') !== '—'">
                    <i class="ki-outline ki-office-bag fs-7"></i> {{ val('target_type') }}
                </span>
                <span class="rs-chip" v-if="val('order_radius') !== '—'">
                    <i class="ki-outline ki-delivery fs-7"></i> {{ val('order_radius') }}
                </span>
                <span class="rs-chip" v-if="val('unload_area') !== '—'">
                    <i class="ki-outline ki-geolocation fs-7"></i> {{ val('unload_area') }}
                </span>
                <span class="rs-chip rs-chip-rodevans"
                      v-if="val('request_type') === '1' || val('request_type') === 1">
                    Rodevans Talep
                </span>
            </div>
        </div>
    </div>

    <!-- ══ TIMELINE ══ -->
    <div class="card mb-4" v-if="timeline">
        <div class="card-body py-4 px-5">
            <div class="rs-section-label mb-3">İhale Süreci</div>
            <!-- Date labels -->
            <div class="d-flex justify-content-between mb-2">
                <div>
                    <div class="rs-tl-sublabel">Başlangıç</div>
                    <div class="rs-tl-date">{{ timeline.startLabel }}</div>
                </div>
                <div class="text-end">
                    <div class="rs-tl-sublabel">Bitiş</div>
                    <div class="rs-tl-date">{{ timeline.endLabel }}</div>
                </div>
            </div>
            <!-- Track -->
            <div class="rs-tl-track">
                <div class="rs-tl-dot"></div>
                <div class="rs-tl-rail">
                    <div class="rs-tl-fill" :style="{ width: timeline.pct + '%' }"></div>
                </div>
                <div class="rs-tl-dot"></div>
            </div>
            <!-- Remaining -->
            <div class="d-flex justify-content-center align-items-center gap-2 mt-2">
                <template v-if="!timeline.expired">
                    <span class="rs-remaining">
                        <i class="ki-outline ki-timer fs-7"></i>
                        <strong>{{ timeline.remaining }}</strong> gün kaldı
                    </span>
                    <span class="rs-tl-total">/ toplam {{ timeline.total }} gün</span>
                </template>
                <span class="rs-remaining rs-expired" v-else>Süre doldu</span>
            </div>
        </div>
    </div>

    <!-- ══ KÖMÜR ÖZELLİKLERİ ══ -->
    <div class="card mb-4" v-if="coalSpecs.length">
        <div class="card-body py-4 px-5">
            <div class="rs-section-label mb-3">Kömürün Özellikleri / Red Şartları</div>
            <div class="rs-specs-grid">
                <div class="rs-spec-cell" v-for="f in coalSpecs" :key="f.key">
                    <div class="rs-spec-label">{{ f.label }}</div>
                    <div class="rs-spec-value">{{ val(f.key) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ KALORİ TABLOSU ══ -->
    <div class="card mb-4" v-if="caloryRows.length">
        <div class="card-body py-4 px-5">
            <div class="rs-section-label mb-3">Kömür Kalori Aralığına Göre Birim Fiyatı</div>
            <table class="rs-table w-100">
                <thead>
                    <tr>
                        <th>Kalori Başlangıç (Kcal)</th>
                        <th>Kalori Bitiş (Kcal)</th>
                        <th class="text-end">Birim Fiyat</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in caloryRows" :key="i">
                        <td>{{ row.prime_condition_is || '—' }}</td>
                        <td>{{ row.prime_condition_is_bellow || '—' }}</td>
                        <td class="text-end rs-price-cell">{{ row.prime_unit_price || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ══ FİYAT ETKİLENME ══ -->
    <div class="card mb-4" v-if="priceImpacts.length">
        <div class="card-body py-4 px-5">
            <div class="rs-section-label mb-3">Fiyat Etkilenme Oranları</div>
            <div class="rs-impact-row" v-for="f in priceImpacts" :key="f.key">
                <span class="rs-impact-label">{{ f.label }}</span>
                <span class="rs-impact-value">{{ val(f.key) }}</span>
            </div>
        </div>
    </div>

    <!-- ══ SİPARİŞ & ÖDEME ══ -->
    <div class="card mb-4" v-if="orderFields.length">
        <div class="card-body py-4 px-5">
            <div class="rs-section-label mb-3">Sipariş & Ödeme Bilgileri</div>
            <div class="rs-specs-grid">
                <div :class="['rs-spec-cell', (f.key === 'payment_desc' || f.key === 'desc') ? 'rs-spec-cell--full' : '']"
                     v-for="f in orderFields" :key="f.key">
                    <div class="rs-spec-label">{{ f.label }}</div>
                    <div class="rs-spec-value">{{ val(f.key) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- AppFab -->
    <AppFab
        v-if="addOfferCallback"
        savebtntitle="Teklif Ekle"
        :callback="addOfferCallback"
        :cancelcallback="() => $router.go(-1)"
    />
</div>
</template>

<style scoped>
/* ── Hero ────────────────────────────────────────────────────────────── */
.rs-hero {
    background: #fff;
    border: 1px solid #eef0f4;
    border-left: 4px solid #154b91;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(15,40,90,.04);
    overflow: hidden;
}

.rs-hero-inner {
    padding: 24px 28px 20px;
}

.rs-eyebrow {
    font-size: .72rem;
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #154b91;
    opacity: .7;
}

.rs-logo {
    height: 26px;
    width: auto;
    opacity: .8;
}

.rs-title {
    font-size: 1.6rem;
    font-weight: 700;
    color: #1e2a3b;
    line-height: 1.25;
    flex: 1;
}

.rs-status-badge {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: .875rem;
    font-weight: 600;
    padding: 5px 14px;
    border-radius: 20px;
    border: 1px solid;
    white-space: nowrap;
}

.rs-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
    animation: rsDotPulse 2.4s ease-in-out infinite;
}

@keyframes rsDotPulse {
    0%,100% { opacity:1; }
    50%      { opacity:.3; }
}

.rs-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: .875rem;
    font-weight: 500;
    color: #4b5675;
    background: #f4f6fa;
    border: 1px solid #eef0f4;
    padding: 4px 12px;
    border-radius: 20px;
}

.rs-chip-rodevans {
    color: #154b91;
    background: rgba(21,75,145,.07);
    border-color: rgba(21,75,145,.2);
}

/* ── Shared section label ───────────────────────────────────────────── */
.rs-section-label {
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: #154b91;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef0f4;
}

/* ── Timeline ───────────────────────────────────────────────────────── */
.rs-tl-sublabel {
    font-size: .72rem;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #99a1b7;
    margin-bottom: 2px;
}

.rs-tl-date {
    font-size: 1rem;
    font-weight: 600;
    color: #1e2a3b;
}

.rs-tl-track {
    display: flex;
    align-items: center;
    gap: 0;
    margin: 10px 0 8px;
}

.rs-tl-dot {
    width: 11px; height: 11px;
    border-radius: 50%;
    background: #154b91;
    flex-shrink: 0;
    box-shadow: 0 0 0 3px rgba(21,75,145,.15);
}

.rs-tl-rail {
    flex: 1;
    height: 5px;
    background: #eef0f4;
    border-radius: 3px;
    margin: 0 3px;
    overflow: hidden;
}

.rs-tl-fill {
    height: 100%;
    background: linear-gradient(90deg, #154b91, #3b6fd4);
    border-radius: 3px;
    transition: width .5s ease;
}

.rs-remaining {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: .78rem;
    font-weight: 600;
    color: #154b91;
    background: rgba(21,75,145,.07);
    padding: 3px 12px;
    border-radius: 20px;
}

.rs-tl-total {
    font-size: .72rem;
    color: #99a1b7;
}

.rs-expired {
    color: #dc2626;
    background: rgba(220,38,38,.08);
}

/* ── Specs grid ─────────────────────────────────────────────────────── */
.rs-specs-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0;
}

.rs-spec-cell {
    padding: 12px 12px 12px 0;
    border-bottom: 1px solid #eef0f4;
}

.rs-spec-cell:nth-last-child(-n+4) {
    border-bottom: none;
}

.rs-spec-cell--full {
    grid-column: 1 / -1;
    border-bottom: 1px solid #eef0f4 !important;
}

.rs-spec-cell--full:last-child {
    border-bottom: none !important;
}

.rs-spec-label {
    font-size: .72rem;
    font-weight: 600;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: #99a1b7;
    margin-bottom: 3px;
}

.rs-spec-value {
    font-size: 1rem;
    font-weight: 600;
    color: #1e2a3b;
}

/* ── Table ──────────────────────────────────────────────────────────── */
.rs-table {
    border-collapse: collapse;
    font-size: .95rem;
}

.rs-table thead tr {
    background: #154b91;
    border-radius: 6px;
}

.rs-table thead th {
    font-size: .72rem;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: rgba(255,255,255,.75);
    padding: 10px 14px;
    border: none;
}

.rs-table thead th:first-child { border-radius: 6px 0 0 6px; }
.rs-table thead th:last-child  { border-radius: 0 6px 6px 0; }

.rs-table tbody tr {
    border-bottom: 1px solid #eef0f4;
}

.rs-table tbody tr:last-child { border-bottom: none; }

.rs-table tbody tr:hover { background: #f7f9fd; }

.rs-table tbody td {
    padding: 12px 14px;
    color: #1e2a3b;
    font-weight: 500;
    border: none;
    background: transparent;
    vertical-align: middle;
}

.rs-price-cell {
    font-weight: 700;
    color: #154b91 !important;
}

/* ── Price impact rows ──────────────────────────────────────────────── */
.rs-impact-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px dashed #eef0f4;
}

.rs-impact-row:last-child { border-bottom: none; }

.rs-impact-label {
    font-size: .92rem;
    color: #4b5675;
}

.rs-impact-value {
    font-size: .95rem;
    font-weight: 700;
    color: #154b91;
}

/* ── Responsive ─────────────────────────────────────────────────────── */
@media (max-width: 767px) {
    .rs-hero-inner  { padding: 20px 18px 18px; }
    .rs-title       { font-size: 1.2rem; }
    .rs-specs-grid  { grid-template-columns: repeat(2, 1fr); }
    .rs-spec-cell:nth-last-child(-n+4) { border-bottom: 1px solid #eef0f4; }
    .rs-spec-cell:nth-last-child(-n+2) { border-bottom: none; }
}
</style>
