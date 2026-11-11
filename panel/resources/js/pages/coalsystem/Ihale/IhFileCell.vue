<script>
    // Compact file state tile for the offer matrix.
    // Empty → Yükle button; filled → status tile (green check / red reject / amber expired).
    // All actions (preview, renew, history, note) live in the parent's modal.
    export default {
        name : 'IhFileCell',
        props : {
            cell : { type : Object, required : true },
            locked : { type : Boolean, default : false },
            accept : { type : String, default : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png' },
        },
        emits : ['pick', 'open'],
        computed : {
            opKey(){
                return this.cell?.fileStatus || this.cell?.savedFile?.last_status?.op_key || '';
            },
            rejectNote(){
                const n = this.cell?.savedFile?.last_status?.note;
                if (!n) return '';
                try { const p = JSON.parse(n); return (p && p.note) ? String(p.note) : String(n); } catch(e) { return String(n); }
            },
            isExpired(){
                if (this.cell?.expired) return true;
                const vu = this.cell?.savedFile?.valid_until;
                if (!vu) return false;
                return String(vu).slice(0, 10) < new Date().toISOString().slice(0, 10);
            },
            tone(){
                if (this.isExpired) return 'is-expired';
                if (this.opKey === 'doc_file_rejected' || this.opKey === 'offer-file-rejected') return 'is-rejected';
                return 'is-ok';
            },
            tip(){
                if (this.isExpired) return 'Süresi doldu' + (this.cell?.savedFile?.valid_until ? ' · ' + this.cell.savedFile.valid_until : '');
                if (this.opKey === 'doc_file_rejected' || this.opKey === 'offer-file-rejected') return 'Reddedildi' + (this.rejectNote ? ' · ' + this.rejectNote : '');
                if (this.cell?.savedFile?.valid_until) return 'Geçerli · ' + this.cell.savedFile.valid_until;
                return 'Yüklendi';
            },
            icon(){
                if (this.tone === 'is-ok') {
                    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>';
                }
                if (this.tone === 'is-expired') {
                    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
                }
                return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg>';
            },
        },
    }
</script>

<template>
    <span v-if="cell.state === 'uploading'" class="iho-cell-loading">Yükleniyor...</span>

    <span v-else-if="cell.state === 'empty'">
        <label v-if="!locked" class="iho-cell-upload" title="Yükle">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 16V4" />
                <path d="M7 9l5-5 5 5" />
                <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
            </svg>
            <input type="file" :accept="accept" @change="$emit('pick', $event)" />
        </label>
        <span v-else class="iho-cell-none">—</span>
    </span>

    <span v-else-if="cell.state === 'pending'" class="iho-cell-pending">
        {{ cell.pendingFile ? cell.pendingFile.name : 'Bekliyor' }}
    </span>

    <button v-else type="button" class="iho-cell" :class="tone" @click="$emit('open')" :title="tip">
        <span class="iho-cell-icon" v-html="icon"></span>
    </button>
</template>

<style scoped>
.iho-cell-upload {
    display: inline-flex; align-items: center; justify-content: center;
    width: 38px; height: 38px;
    padding: 0;
    border: 1px solid #2f80ed;
    border-radius: 10px;
    color: #2f80ed;
    background: #fff;
    cursor: pointer;
    transition: background .15s ease, transform .15s ease;
}
.iho-cell-upload:hover { background: #eff6ff; transform: translateY(-1px); }
.iho-cell-upload input { display: none; }
.iho-cell-none { color: #cbd5e1; font-size: 13px; }
.iho-cell-loading { font-size: 12px; color: #94a3b8; }
.iho-cell-pending { font-size: 12px; color: #b45309; }

.iho-cell {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    padding: 0;
    border-radius: 10px;
    border: 1px solid transparent;
    background: #fff;
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease;
}
.iho-cell:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(15,23,42,.10); }

.iho-cell-icon { display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; width: 30px; height: 30px; flex: 0 0 30px; }

.iho-cell.is-ok { border-color: #a7f3d0; background: #f0fdf4; }
.iho-cell.is-ok .iho-cell-icon { background: #16a34a; color: #fff; }

.iho-cell.is-rejected { border-color: #fecaca; background: #fef2f2; }
.iho-cell.is-rejected .iho-cell-icon { background: #dc2626; color: #fff; }

.iho-cell.is-expired { border-color: #fed7aa; background: #fff7ed; }
.iho-cell.is-expired .iho-cell-icon { background: #f59e0b; color: #fff; }
</style>
