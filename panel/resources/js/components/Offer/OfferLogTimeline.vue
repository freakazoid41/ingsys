
<script>
import Plib from '@/lib/pickle';

const FIELD_LABELS = {
    offer_type            : 'Teklif Tipi',
    clititle              : 'Tedarikçi',
    date                  : 'Belge Tarihi',
    target_type           : 'Santral',
    order_radius          : 'Sipariş Kapsamı',
    contract_start_date   : 'Teklif Başlangıç',
    contract_end_date     : 'Teklif Bitiş',
    unload_area           : 'Boşaltma Yeri',
    coal_size             : 'Ebat',
    coal_hgi              : 'HGİ',
    coal_ucucu            : 'Uçucu Madde',
    coal_type             : 'Cinsi',
    calory                : 'Kalori',
    humidity              : 'Nem',
    ash_content           : 'Kül Oranı',
    sulfur                : 'Kükürt',
    fuel_price_impact     : 'Akaryakıt Etki',
    tiufe_price_impact    : 'TÜFE Etki',
    fuel_price_impact_2   : 'Akaryakıt Etki %2',
    amount                : 'Miktar',
    payment_periods       : 'Hakediş Dönemleri',
    payment_due           : 'Ödeme Vadesi',
    transfer_start_date   : 'Sevkiyat Başlangıç',
    transfer_end_date     : 'Sevkiyat Bitiş',
    payment_desc          : 'Hakediş Açıklama',
    prime_condition_is        : 'Kalori Başlangıç',
    prime_condition_is_bellow : 'Kalori Bitiş',
    prime_unit_price          : 'Birim Fiyat',
};

function labelFor(key) {
    const base = key.split('**')[0];
    return FIELD_LABELS[base] ?? base;
}

function formatValue(key, val) {
    const base = key.split('**')[0];
    if (base === 'offer_type') return String(val).split('**')[1] ?? val;
    return val;
}

function diffEntities(before, after) {
    const changes = [];
    const allKeys = new Set([...Object.keys(before ?? {}), ...Object.keys(after ?? {})]);
    for (const key of allKeys) {
        if (key === 'qnid' || key === 'rev_date' || key === 'request_id' || key === 'cliid') continue;
        const bVal = (before ?? {})[key] ?? '';
        const aVal = (after ?? {})[key] ?? '';
        if (String(bVal).trim() !== String(aVal).trim()) {
            changes.push({ key, label: labelFor(key), before: formatValue(key, bVal), after: formatValue(key, aVal) });
        }
    }
    return changes;
}

const STATUS_LABEL = {
    'doc_trans_offer_approved' : 'Teklif Onaylandı',
    'doc_trans_offer_rejected' : 'Teklif Reddedildi',
    'doc_trans_offer_revision' : 'Revizyon Talep Edildi',
    'doc_trans_offer_review'   : 'İncelemeye Alındı',
    'doc_trans_offer_revised'  : 'Teklif Revize Edildi',
    'doc_trans_offer_sended'   : 'Teklif Gönderildi',
    'doc_trans_offer_draft'    : 'Taslak Oluşturuldu',
};

export default {
    props: {
        documentQnid: { type: String, required: true },
    },
    data() {
        return {
            plib     : new Plib(),
            logs     : [],
            loading  : true,
            expanded : {},
        };
    },
    mounted() { this.fetchLogs(); },
    methods: {
        async fetchLogs() {
            this.loading = true;
            const tableReq = JSON.stringify({
                scale  : { page: 1, limit: 100 },
                filter : [{ key: 'doc_qnid', type: '=', value: this.documentQnid }],
            });
            const envelope = new FormData();
            envelope.append('tableReq', tableReq);
            envelope.append('model', 'userlogs');
            const rsp = await this.plib.request({ url: '/api/v1/table/userlog', method: 'POST' }, null, envelope);
            if (rsp?.data) {
                this.logs = rsp.data.map(row => {
                    let desc = null;
                    try { desc = JSON.parse(row.description); } catch {}

                    // Status change log
                    if (desc?.desc && !desc?.before) {
                        const opKey = desc?.after?.document?.op_key ?? '';
                        return {
                            ...row,
                            logType  : 'status',
                            label    : STATUS_LABEL[opKey] ?? desc.desc,
                            note     : desc.note ?? '',
                            changes  : [],
                            isCreate : false,
                            raw      : desc,
                        };
                    }

                    // File log
                    if (desc?.file_id != null) {
                        return {
                            ...row,
                            logType  : 'file',
                            label    : desc.desc ?? 'Dosya İşlemi',
                            note     : desc.note ?? '',
                            changes  : [],
                            isCreate : false,
                            raw      : desc,
                        };
                    }

                    // Create / update log
                    const beforeEntities = Object.values(desc?.before?.formFormat?.['op-doc-offer-form'] ?? {})[0]?.entities ?? {};
                    const afterEntities  = Object.values(desc?.after?.formFormat?.['op-doc-offer-form']  ?? {})[0]?.entities ?? {};
                    const changes  = diffEntities(beforeEntities, afterEntities);
                    const isCreate = changes.length > 0 && changes.every(c => !c.before || c.before === '');
                    return {
                        ...row,
                        logType  : 'edit',
                        label    : isCreate ? 'Teklif Oluşturuldu' : (row.title ?? 'Teklif Güncellendi'),
                        note     : '',
                        changes,
                        isCreate,
                        raw      : desc,
                    };
                });
            }
            this.loading = false;
        },
        toggle(idx) {
            this.expanded = { ...this.expanded, [idx]: !this.expanded[idx] };
        },
        dotClass(log) {
            if (log.logType === 'status') return 'tl-dot--status';
            if (log.logType === 'file')   return 'tl-dot--file';
            if (log.isCreate)             return 'tl-dot--create';
            return '';
        },
        iconFor(log) {
            if (log.logType === 'status') return 'ki-information-2';
            if (log.logType === 'file')   return 'ki-document';
            if (log.isCreate)             return 'ki-plus-circle';
            return 'ki-pencil';
        },
    },
};
</script>

<template>
    <div class="card otbl-card">
        <div class="card-header otbl-header">
            <span class="otbl-title">
                <i class="ki-outline ki-time fs-4 me-2"></i> İşlem Geçmişi
            </span>
        </div>
        <div class="card-body p-4">
            <div v-if="loading" class="text-center py-6 text-muted">
                <span class="spinner-border spinner-border-sm me-2"></span> Yükleniyor...
            </div>
            <div v-else-if="!logs.length" class="text-center py-6 text-muted fs-6">
                Bu teklife ait log kaydı bulunamadı.
            </div>
            <div v-else class="tl-wrap">
                <div v-for="(log, idx) in logs" :key="idx" class="tl-item">
                    <div class="tl-dot" :class="dotClass(log)">
                        <i :class="['ki-outline', iconFor(log), 'fs-4']"></i>
                    </div>
                    <div class="tl-body">
                        <div
                            class="tl-header"
                            @click="log.changes.length ? toggle(idx) : null"
                            :class="{ 'tl-clickable': log.changes.length }"
                        >
                            <div class="tl-meta">
                                <span class="tl-type">{{ log.label }}</span>
                                <span class="tl-sep">·</span>
                                <span class="tl-user">{{ log.name }}</span>
                                <span class="tl-sep">·</span>
                                <span class="tl-date">{{ log.created_at?.slice(0,16).replace('T',' ') }}</span>
                            </div>
                            <div v-if="log.changes.length" class="tl-badge">
                                {{ log.changes.length }} değişiklik
                                <i :class="['ki-outline', expanded[idx] ? 'ki-arrow-up' : 'ki-arrow-down', 'fs-6 ms-1']"></i>
                            </div>
                        </div>
                        <div v-if="log.note" class="tl-note">{{ log.note }}</div>
                        <div v-if="expanded[idx] && log.changes.length" class="tl-diff">
                            <template v-if="log.isCreate">
                                <div v-for="c in log.changes" :key="c.key" class="diff-row diff-row--create">
                                    <div class="diff-label">{{ c.label }}</div>
                                    <div class="diff-after" style="grid-column: 2 / -1">{{ c.after || '—' }}</div>
                                </div>
                            </template>
                            <template v-else>
                                <div v-for="c in log.changes" :key="c.key" class="diff-row">
                                    <div class="diff-label">{{ c.label }}</div>
                                    <div class="diff-before">{{ c.before || '—' }}</div>
                                    <div class="diff-arrow"><i class="ki-outline ki-arrow-right fs-6"></i></div>
                                    <div class="diff-after">{{ c.after || '—' }}</div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.otbl-card {
    border: 1px solid #dde3ee !important;
    box-shadow: 0 2px 8px rgba(15,40,90,.07) !important;
    border-radius: 12px !important;
    overflow: hidden;
}
.otbl-header {
    display: flex;
    align-items: center;
    padding: 14px 20px !important;
    border-bottom: 1px solid #eef0f4;
    background: #fff;
    min-height: unset !important;
}
.otbl-title {
    font-size: .95rem;
    font-weight: 700;
    color: #1e2a3b;
    display: flex;
    align-items: center;
}

.tl-wrap { display: flex; flex-direction: column; }
.tl-item {
    display: flex;
    gap: 16px;
    padding: 14px 0;
    border-bottom: 1px solid #f0f2f7;
}
.tl-item:last-child { border-bottom: none; }

.tl-dot {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #eef2fa;
    border: 2px solid #dde3ee;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #154b91;
    margin-top: 2px;
}
.tl-dot--create {
    background: rgba(5,150,105,.1);
    border-color: rgba(5,150,105,.3);
    color: #059669;
}
.tl-dot--status {
    background: rgba(217,119,6,.08);
    border-color: rgba(217,119,6,.25);
    color: #d97706;
}
.tl-dot--file {
    background: rgba(99,102,241,.08);
    border-color: rgba(99,102,241,.2);
    color: #6366f1;
}

.tl-body { flex: 1; min-width: 0; }
.tl-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.tl-clickable { cursor: pointer; }
.tl-clickable:hover .tl-type { color: #154b91; }

.tl-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.tl-type { font-weight: 600; font-size: .95rem; color: #1e2a3b; }
.tl-sep  { color: #c5cdd8; font-size: .85rem; }
.tl-user { font-size: .875rem; color: #4b5675; }
.tl-date { font-size: .82rem; color: #99a1b7; }

.tl-note {
    margin-top: 4px;
    font-size: .82rem;
    color: #4b5675;
    background: #f8fafc;
    border-radius: 6px;
    padding: 4px 8px;
    display: inline-block;
}

.tl-badge {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    font-size: .78rem;
    font-weight: 600;
    color: #154b91;
    background: rgba(21,75,145,.08);
    border: 1px solid rgba(21,75,145,.15);
    border-radius: 20px;
    padding: 3px 10px;
    white-space: nowrap;
}

.tl-diff {
    margin-top: 10px;
    border: 1px solid #eef0f4;
    border-radius: 8px;
    overflow: hidden;
}
.diff-row {
    display: grid;
    grid-template-columns: 160px 1fr 24px 1fr;
    align-items: center;
    padding: 8px 12px;
    border-bottom: 1px solid #f5f6fa;
    font-size: .85rem;
}
.diff-row:last-child { border-bottom: none; }
.diff-row:nth-child(even) { background: #fafbfd; }
.diff-label  { font-weight: 600; color: #4b5675; padding-right: 12px; }
.diff-before { color: #dc2626; background: rgba(220,38,38,.05); border-radius: 4px; padding: 2px 6px; word-break: break-word; }
.diff-arrow  { display: flex; justify-content: center; color: #99a1b7; }
.diff-after  { color: #059669; background: rgba(5,150,105,.05); border-radius: 4px; padding: 2px 6px; word-break: break-word; }

@media (max-width: 767px) {
    .diff-row { grid-template-columns: 1fr; gap: 4px; }
    .diff-arrow { display: none; }
}
</style>
