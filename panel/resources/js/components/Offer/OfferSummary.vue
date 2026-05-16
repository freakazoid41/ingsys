
<script>
import dayjs from 'dayjs';

const SECTIONS = [
    { title: 'Genel Bilgiler', fields: [
        { key: 'clititle',           label: 'Tedarikçi' },
        { key: 'date',               label: 'Belge Tarihi' },
        { key: 'offer_type',         label: 'Teklif Tipi', format: 'offer_type' },
        { key: 'target_type',        label: 'Santral' },
        { key: 'order_radius',       label: 'Sipariş Kapsamı' },
        { key: 'contract_start_date',label: 'Teklif Başlangıç' },
        { key: 'contract_end_date',  label: 'Teklif Bitiş' },
        { key: 'unload_area',        label: 'Boşaltma Yeri' },
    ]},
    { title: 'Kömür Özellikleri', fields: [
        { key: 'coal_size',          label: 'Ebat' },
        { key: 'coal_hgi',           label: 'HGİ' },
        { key: 'coal_ucucu',         label: 'Uçucu Madde' },
        { key: 'coal_type',          label: 'Cinsi' },
        { key: 'calory',             label: 'Kalori' },
        { key: 'humidity',           label: 'Nem' },
        { key: 'ash_content',        label: 'Kül Oranı' },
        { key: 'sulfur',             label: 'Kükürt' },
    ]},
    { title: 'Kalori Aralığına Göre Birim Fiyat', isCalory: true },
    { title: 'Fiyat Etki Oranları', fields: [
        { key: 'fuel_price_impact',  label: 'Akaryakıt Etki' },
        { key: 'tiufe_price_impact', label: 'TÜFE Etki' },
        { key: 'fuel_price_impact_2',label: 'Akaryakıt Etki %2' },
    ]},
    { title: 'Diğer Bilgiler', fields: [
        { key: 'amount',             label: 'Miktar' },
        { key: 'payment_periods',    label: 'Hakediş Dönemleri' },
        { key: 'payment_due',        label: 'Ödeme Vadesi' },
        { key: 'transfer_start_date',label: 'Sevkiyat Başlangıç' },
        { key: 'transfer_end_date',  label: 'Sevkiyat Bitiş' },
        { key: 'payment_desc',       label: 'Hakediş Açıklama', wide: true },
    ]},
];

export default {
    props: {
        editable:            { type: Boolean, default: false },
        entities:            { type: Object, default: () => ({}) },
        previewEntities:     { type: Object, default: null },
        versionOptions:      { type: Array,  default: () => [] },
        selectedVersionId:   { type: [String, Number], default: '' },
        onVersionChange:     { type: Function, default: null },
        document:            { type: Object, default: () => ({}) },
        statusList:          { type: Array,  default: () => [] },
        onEditable:          { type: Function, default: null },
        onRequestRevision:   { type: Function, default: null },
        onApprove:           { type: Function, default: null },
        onReject:            { type: Function, default: null },
        onDownloadPdf:       { type: Function, default: null },
        hideActions:         { type: Boolean,  default: false },
    },
    setup() { return { dayjs }; },
    data() { return { sections: SECTIONS }; },
    computed: {
        entitiesToRender() {
            return this.previewEntities && Object.keys(this.previewEntities).length ? this.previewEntities : this.entities;
        },
        isPreviewMode() {
            return this.selectedVersionId !== '';
        },
        currentEntities() {
            return this.entities ?? {};
        },
        isFileOffer() {
            const v = this.entitiesToRender?.offer_type ?? '';
            return String(v).split('**')[0] === 'op-doc-offer-file';
        },
        visibleSections() {
            if (this.isFileOffer) return SECTIONS.filter(s => s.title === 'Genel Bilgiler');
            return SECTIONS;
        },
        caloryRows() {
            const groups = {};
            for (const [key, val] of Object.entries(this.entitiesToRender ?? {})) {
                const parts = key.split('**');
                if (parts[1] !== 'calory_settings') continue;
                const tag = parts[2];
                if (!groups[tag]) groups[tag] = {};
                groups[tag][parts[0]] = val;
            }
            return Object.values(groups);
        },
        offerDocs() {
            return this.groupOfferDocs(this.entitiesToRender);
        },
        currentOfferDocs() {
            return this.groupOfferDocs(this.currentEntities);
        },
    },
    methods: {
        parseOfferDocFile(value) {
            if (!value) return null;
            if (typeof value !== 'string') return value;
            try {
                return JSON.parse(value);
            } catch (e) {
                return { description: value };
            }
        },
        offerDocLink(file) {
            if (!file?.description) return null;
            return `/order-file/${file.description}`;
        },
        groupOfferDocs(entities) {
            const groups = {};
            for (const [key, val] of Object.entries(entities ?? {})) {
                const parts = key.split('**');
                if (parts.length !== 3 || parts[1] !== 'offerotherdocs') continue;
                const tag = parts[2];
                if (!groups[tag]) groups[tag] = { id: tag, name: '—', file: null };
                if (parts[0] === 'offer_otherdocs_file_text') {
                    groups[tag].name = val || '—';
                }
                if (parts[0] === 'offer_otherdocs_file') {
                    groups[tag].file = this.parseOfferDocFile(val);
                }
            }
            return Object.values(groups).filter(row => row.name !== '—' || row.file);
        },
        offerDocFileLabel(file) {
            if (!file) return '—';
            return file.name || file.description || 'Dosya';
        },
        offerDocChanged(doc) {
            if (!this.isPreviewMode) return false;
            const current = this.currentOfferDocs.find(item => item.id === doc.id);
            if (!current) return true;
            const previewName = doc.name || '';
            const currentName = current.name || '';
            const previewDesc = doc.file?.description || '';
            const currentDesc = current.file?.description || '';
            return String(previewName).trim() !== String(currentName).trim() || String(previewDesc).trim() !== String(currentDesc).trim();
        },
        offerDocDiffClass(doc) {
            return this.offerDocChanged(doc) ? 'os-doc-row--changed' : '';
        },
        val(key) {
            const v = this.entitiesToRender?.[key];
            return v !== undefined && v !== null && v !== '' ? v : '—';
        },
        valueChanged(key) {
            if (!this.isPreviewMode) return false;
            const previewValue = this.previewEntities?.[key];
            const currentValue = this.currentEntities?.[key];
            return String(previewValue ?? '').trim() !== String(currentValue ?? '').trim();
        },
        valueDiffClass(key) {
            return this.valueChanged(key) ? 'os-field-value--changed' : '';
        },
        versionChanged(versionId) {
            if (this.onVersionChange) {
                this.onVersionChange(versionId);
            }
        },
        fmt(field) {
            const v = this.val(field.key);
            if (v === '—') return '—';
            if (field.format === 'offer_type') return v.split('**')[1] ?? v;
            return v;
        },
        statusBadgeClass(item) {
            const map = {
                'doc_trans_offer_approved' : 'badge-success',
                'doc_trans_offer_rejected' : 'badge-danger',
                'doc_trans_offer_revision' : 'badge-warning',
                'doc_trans_offer_review'   : 'badge-warning',
                'doc_trans_offer_revised'  : 'badge-warning',
                'doc_trans_offer_sended'   : 'badge-secondary',
                'doc_trans_offer_draft'    : 'badge-secondary',
                'doc_trans_created'        : 'badge-secondary',
            };
            return map[item.op_key] ?? 'badge-secondary';
        },
    },
};
</script>

<template>
    <div style="padding-bottom: 100px;">
        <!-- Action bar -->
        <div class="card mb-6" style="border:1px solid #dde3ee;border-radius:12px;overflow:hidden;">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center py-4 px-5">
                <button class="os-btn os-btn-ghost" @click="onDownloadPdf && onDownloadPdf()">
                    <i class="ki-outline ki-file-down fs-4"></i> PDF İndir
                </button>
                <div class="d-flex align-items-center" v-if="versionOptions.length">
                    <select class="form-select form-select-sm" :value="selectedVersionId" @change="versionChanged($event.target.value)">
                        <option value="">Mevcut Versiyon</option>
                        <option v-for="option in versionOptions" :key="option.id" :value="option.id">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <button v-if="editable" class="os-btn os-btn-ghost" @click="onEditable && onEditable()">
                    <i class="ki-outline ki-file-down fs-4"></i> Düzenle
                </button>
                <template v-if="!hideActions">
                    <button class="os-btn os-btn-warning" @click="onRequestRevision && onRequestRevision()">
                        <i class="ki-outline ki-arrows-circle fs-4"></i> Revize Talep Et
                    </button>
                    <button class="os-btn os-btn-success" @click="onApprove && onApprove()">
                        <i class="ki-outline ki-check-circle fs-4"></i> Onayla
                    </button>
                    <button class="os-btn os-btn-danger" @click="onReject && onReject()">
                        <i class="ki-outline ki-cross-circle fs-4"></i> Reddet
                    </button>
                </template>
                <div class="ms-auto d-flex align-items-center gap-2" v-if="statusList.length">
                    <span class="os-field-label mb-0" style="white-space:nowrap;">Mevcut Durum:</span>
                    <span class="os-current-badge" :class="statusBadgeClass(statusList[statusList.length - 1])">{{ statusList[statusList.length - 1].title }}</span>
                </div>
            </div>
        </div>
        <div v-if="isPreviewMode" class="card mb-6 os-preview-warning-card">
            <div class="card-body py-3 px-4 d-flex align-items-center gap-3">
                <i class="ki-outline ki-alert-triangle fs-4 text-warning"></i>
                <div>
                    <div class="fw-semibold">Eski versiyon görüntüleniyor</div>
                    <div class="text-muted fs-7">Seçtiğiniz versiyon, mevcut teklif verilerinden farklı olabilir. Ana versiyona dönmek için "Mevcut Versiyon"u seçin.</div>
                </div>
            </div>
        </div>

        <div class="row g-6">
            <!-- Left: field sections -->
            <div :class="statusList.length ? 'col-lg-8' : 'col-12'">
                <!-- File-type offer: download card -->
                    <div v-if="isFileOffer" class="card mb-6 os-card">
                        <div class="card-header os-card-header">
                            <span class="os-section-title">Teklif Dosyası</span>
                        </div>
                        <div class="card-body d-flex align-items-center gap-3 py-4 px-5">
                            <i class="ki-outline ki-document fs-2x text-primary"></i>
                            <div>
                                <div class="fw-semibold fs-6 mb-1">Tedarikçi teklifi dosya olarak yükledi.</div>
                                <div class="text-muted fs-7">PDF İndir butonunu kullanarak dosyaya erişebilirsiniz.</div>
                            </div>
                            <button class="ms-auto os-btn os-btn-ghost" @click="onDownloadPdf && onDownloadPdf()">
                                <i class="ki-outline ki-file-down fs-4"></i> Dosyayı İndir
                            </button>
                        </div>
                    </div>

                    <template v-for="section in visibleSections" :key="section.title">
                    <!-- Calory table -->
                    <div v-if="section.isCalory" class="card mb-6 os-card">
                        <div class="card-header os-card-header">
                            <span class="os-section-title">{{ section.title }}</span>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0" v-if="caloryRows.length">
                                <thead>
                                    <tr style="background:#f8fafc;">
                                        <th class="os-th">Kalori Başlangıç</th>
                                        <th class="os-th">Kalori Bitiş</th>
                                        <th class="os-th">Birim Fiyat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, i) in caloryRows" :key="i">
                                        <td class="os-td">{{ row.prime_condition_is || '—' }}</td>
                                        <td class="os-td">{{ row.prime_condition_is_bellow || '—' }}</td>
                                        <td class="os-td">{{ row.prime_unit_price || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="p-4 text-muted fs-6">Kalori aralığı tanımlı değil.</div>
                        </div>
                    </div>

                    <!-- Normal section -->
                    <div v-else class="card mb-6 os-card">
                        <div class="card-header os-card-header">
                            <span class="os-section-title">{{ section.title }}</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div
                                    v-for="field in section.fields"
                                    :key="field.key"
                                    :class="field.wide ? 'col-12' : 'col-md-4 col-sm-6'"
                                >
                                    <div class="os-field">
                                        <div class="os-field-label">{{ field.label }}</div>
                                        <div class="os-field-value" :class="valueDiffClass(field.key)">{{ fmt(field) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </template>

                    <div v-if="offerDocs.length" class="card mb-6 os-card col-6 col-md-6 col-sm-12">
                        <div class="card-header os-card-header">
                            <span class="os-section-title">Teklif Belgeleri</span>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0 os-doc-table">
                                
                                <tbody>
                                    <tr v-for="(doc, index) in offerDocs" :key="doc.id || index" :class="offerDocDiffClass(doc)">
                                        <td class="os-td">{{ doc.name || 'Ek Belge' }}</td>
                                        <td class="os-td" style="text-align: right;">
                                            <template v-if="doc.file?.description">
                                                <a :href="offerDocLink(doc.file)" target="_blank" rel="noopener" class="os-doc-link">
                                                    <i class="ki-outline ki-eye fs-5"></i>
                                                    Görüntüle
                                                </a>
                                            </template>
                                            <template v-else>
                                                —
                                            </template>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
            </div>

            <!-- Right: status history -->
            <div class="col-lg-4" v-if="statusList.length">
                <div class="card os-card" style="position:sticky;top:80px;">
                    <div class="card-header os-card-header">
                        <span class="os-section-title">Teklif Durum Geçmişi</span>
                    </div>
                    <div class="card-body p-4">
                        <template v-for="(item, index) in [...statusList].reverse()" :key="index">
                            <div class="os-status-item">
                                <div class="os-status-dot" :class="index === 0 ? 'os-status-dot--active' : ''"></div>
                                <div class="os-status-body">
                                    <div class="os-status-title">{{ item.title }}</div>
                                    <div class="os-status-meta">
                                        <span><i class="ki-outline ki-user fs-7 me-1"></i>{{ item.name }}</span>
                                        <span><i class="ki-outline ki-time fs-7 me-1"></i>{{ item.time ? dayjs(item.time).format('DD.MM.YYYY') : '—' }}</span>
                                    </div>
                                    <div class="os-status-note" v-if="item.note">{{ item.note }}</div>
                                </div>
                            </div>
                            <div v-if="index < statusList.length - 1" class="os-status-connector"></div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.os-card {
    border: 1px solid #dde3ee !important;
    box-shadow: 0 2px 8px rgba(15,40,90,.07) !important;
    border-radius: 12px !important;
    overflow: hidden;
}
.os-card-header {
    background: #fff;
    padding: 12px 20px !important;
    border-bottom: 1px solid #eef0f4;
    min-height: unset !important;
}
.os-section-title {
    font-size: .82rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #154b91;
}

/* Fields */
.os-field-label {
    font-size: .78rem;
    color: #99a1b7;
    margin-bottom: 3px;
    font-weight: 500;
}
.os-field-value {
    font-size: .95rem;
    font-weight: 600;
    color: #1e2a3b;
    word-break: break-word;
}

/* Table */
.os-th {
    font-size: .78rem;
    font-weight: 700;
    color: #4b5675;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: 10px 16px !important;
    border-bottom: 1px solid #eef0f4;
}
.os-td {
    font-size: .9rem;
    color: #2d3748;
    padding: 12px 16px !important;
    border-bottom: 1px solid #f5f6fa;
    vertical-align: middle;
}

.os-doc-table .os-th {
    background: #f8fafc;
}

.os-doc-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #154b91;
    font-weight: 600;
    text-decoration: none;
}

.os-doc-link:hover {
    color: #0d3a70;
    text-decoration: underline;
}

.os-doc-link i {
    display: inline-flex;
}

/* Current status badge */
.os-current-badge {
    display: inline-flex;
    align-items: center;
    height: 28px;
    padding: 0 12px;
    border-radius: 20px;
    font-size: .78rem;
    font-weight: 700;
    border: 1px solid transparent;
}
.os-current-badge.badge-success  { background: rgba(5,150,105,.1);  color: #059669; border-color: rgba(5,150,105,.25); }
.os-current-badge.badge-danger   { background: rgba(220,38,38,.08); color: #dc2626; border-color: rgba(220,38,38,.2); }
.os-current-badge.badge-warning  { background: rgba(217,119,6,.1);  color: #d97706; border-color: rgba(217,119,6,.25); }
.os-current-badge.badge-secondary{ background: #f4f6fa;             color: #4b5675; border-color: #e2e8f0; }

.os-preview-warning-card {
    border-color: #facc15 !important;
    background: rgba(254, 243, 199, 0.8);
}

.os-preview-warning-card .card-body {
    background: transparent;
}

.os-field-value--changed {
    background: rgba(254, 230, 138, 0.35);
    border: 1px solid rgba(245, 158, 11, 0.25);
    border-radius: 8px;
    padding: 10px 12px;
    display: inline-block;
    color: #92400e;
}

.os-doc-row--changed td {
    background: rgba(254, 230, 138, 0.18) !important;
}

/* Action buttons */
.os-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 38px;
    padding: 0 16px;
    border-radius: 8px;
    font-size: .875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: background .15s, color .15s;
}
.os-btn-ghost    { background: #f4f6fa; color: #4b5675; border: 1px solid #e2e8f0; }
.os-btn-ghost:hover { background: #e8edf5; }
.os-btn-warning  { background: rgba(217,119,6,.1); color: #d97706; border: 1px solid rgba(217,119,6,.25); }
.os-btn-warning:hover { background: rgba(217,119,6,.2); }
.os-btn-success  { background: rgba(5,150,105,.1); color: #059669; border: 1px solid rgba(5,150,105,.25); }
.os-btn-success:hover { background: rgba(5,150,105,.2); }
.os-btn-danger   { background: rgba(220,38,38,.08); color: #dc2626; border: 1px solid rgba(220,38,38,.2); }
.os-btn-danger:hover { background: rgba(220,38,38,.16); }

/* Status timeline */
.os-status-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.os-status-dot {
    flex-shrink: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #dde3ee;
    border: 2px solid #c5cdd8;
    margin-top: 5px;
}
.os-status-dot--active {
    background: #154b91;
    border-color: #154b91;
}
.os-status-body { flex: 1; padding-bottom: 4px; }
.os-status-title { font-size: .9rem; font-weight: 600; color: #1e2a3b; }
.os-status-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: .78rem;
    color: #99a1b7;
    margin-top: 3px;
}
.os-status-note {
    margin-top: 4px;
    font-size: .82rem;
    color: #4b5675;
    background: #f8fafc;
    border-radius: 6px;
    padding: 4px 8px;
}
.os-status-connector {
    width: 2px;
    height: 16px;
    background: #eef0f4;
    margin-left: 5px;
    margin-bottom: 2px;
}
</style>
