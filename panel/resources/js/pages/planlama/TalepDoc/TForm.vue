<script>
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { useRoute } from 'vue-router';
import { useNavigationStore } from '@/stores/navigation';
import { expandMonths, fillItemRow, findMatchingRow, findEmptyRow, itemRowsIn } from '@/lib/talepRows';
import { planlamaLabel, planlamaColor, PLANLAMA_STATUS_TITLE } from '@/lib/planlamaStatus';
import { useAuthStore } from '@/stores/auth';
import { useFormDataStore } from '@/stores/formdata';
import Form from '@/components/coalparts/Form.vue';

const UNIT_MAP = { 'ADT': 'ST', 'ADET': 'ST', 'ST': 'ST', 'KG': 'KG', 'KILOGRAM': 'KG', 'M': 'M', 'MT': 'M', 'METRE': 'M' };

export default {
    breadcrumbs: {
        list: [{ title: 'Talep Dökümanları', path: '/coalpanel/planlama/talep-dokumanlari' }],
        title: 'Talep Dökümanı Formu'
    },
    components: { Form },
    setup() { return { useNavigationStore, useAuthStore, useFormDataStore, Plib, useRoute }; },
    data() {
        const route = useRoute();
        return {
            loadForm: false,
            plib: new Plib(),
            navigationStore: useNavigationStore(),
            authStore: useAuthStore(),
            formDataStore: useFormDataStore(),
            id: route?.params?.id !== '' ? route?.params?.id : undefined,
            excelBusy: false,
            docStatus: null,
            statusBusy: false,
            isSubmitting: false,
            mudurlukler: [],
            selectedMudur: '',
            excelFiles: [],
            excelLoading: false,
        };
    },
    computed: {
        isFront() { return this.$route.path.startsWith('/planlamapanel'); },
        canEdit() { return (this.authStore?.permissions || []).includes('per-041-05-03'); },
        canMan() { return (this.authStore?.permissions || []).includes('per-041-05-01'); },
        canStd() { return (this.authStore?.permissions || []).includes('per-041-05-04'); },
        canSave() { return this.canEdit && (!this.id || (this.docStatus && this.docStatus.op === 'tr-request-man-rejected')); },
        roFields() {
            const base = ['title', 'request_no', 'desc', 'mal_grubu', 'ihale_grubu', 'prod_code', 'unit', 'ay', 'talep_excel_file'];
            return this.canSave ? base : base.concat(['quantity']);
        },
        mudurLabel() {
            const m = (this.mudurlukler || []).find(x => x.qnid === this.selectedMudur);
            return m ? (m.client_system + '-' + m.lifnr + ' ' + m.title) : '';
        },
        statusLabel() {
            if (!this.docStatus) return '-';
            const l = planlamaLabel(this.docStatus.op);
            return l !== this.docStatus.op ? l : (this.docStatus.label || this.docStatus.op);
        },
        statusColor() {
            return planlamaColor(this.docStatus && this.docStatus.op);
        },
    },
    mounted() {
        // Talep Dökümanı yalnızca bir İhtiyaç Listesi satırından, bağlı oluşturulur
        const fromQnid = this.$route.query && this.$route.query.from;
        if (!this.id && !fromQnid) {
            this.plib.toast(Swal, 'info', 'Talep Dökümanı yalnızca İhtiyaç Listesi satırından oluşturulur');
            this.$router.replace({ name: this.listRoute() });
            return;
        }
        const checkData = async () => this.id
            ? await this.plib.request({ url: '/api/v1/document/' + this.id, method: 'GET' }, null)
            : { success: false };
        checkData().then(async rsp => {
            this.formDataStore.setData(rsp?.data?.formFormat);
            this.formDataStore.rawData = rsp?.data || null;
            this.parseStatus(rsp?.data);
            this.loadForm = true;
            this.navigationStore.toggle(false);
            if (!this.id && fromQnid) {
                await this.fetchMudurlukler();
                await this.$nextTick();
                await this.linkFromSource(fromQnid);
            } else if (this.id) {
                await this.fetchMudurlukler();
                this.readSavedMudur(rsp?.data);
                await this.fetchExcelHistory();
            }
        });
        this.navigationStore.toggle(true);
    },
    methods: {
        async fetchExcelHistory() {
            if (!this.id) { this.excelFiles = []; return; }
            this.excelLoading = true;
            try {
                const rsp = await this.plib.request({ url: '/api/v1/request/' + this.id + '/files', method: 'GET' }, null);
                this.excelFiles = (rsp && rsp.success && rsp.data && rsp.data.files) || [];
            } catch (e) { console.error('[TalepExcel] history failed', e); }
            finally { this.excelLoading = false; }
        },
        excelDate(f) {
            try { return String(f.created_at || '').slice(0, 16).replace('T', ' '); } catch { return f.created_at || ''; }
        },
        openExcel(qnid) {
            if (qnid) window.open('/order-file/' + qnid, '_blank');
        },
        downloadExcel(f) {
            if (!f || !f.qnid) return;
            const a = document.createElement('a');
            a.href = '/order-file/' + f.qnid;
            a.setAttribute('download', '');
            document.body.appendChild(a);
            a.click();
            a.remove();
        },
        async fetchMudurlukler() {
            try {
                const fd = new FormData();
                fd.append('tableReq', JSON.stringify({
                    filter: [
                        { key: 'form-type', type: '=', value: 'op-doc-client-form' },
                        { key: 'type', type: '=', value: 'op-doc-client' },
                        { key: 'system_only', type: '=', value: '1' },
                    ],
                    scale: { page: 1, limit: 100 },
                }));
                const rsp = await this.plib.request({ url: '/api/v1/table/documents', method: 'POST' }, null, fd);
                const rows = rsp?.data || rsp || [];
                const list = (Array.isArray(rows) ? rows : []).map(d => {
                    let attrs = {};
                    try { JSON.parse(d.main_attr || '[]').forEach(el => { attrs[el['Key']] = el['Value']; }); } catch(e) {}
                    return {
                        qnid: d.id,
                        title: attrs.title || d.title || '-',
                        lifnr: attrs.lifnr || '-',
                        client_system: (attrs.client_system || 'GDZ').toString().toUpperCase(),
                    };
                }).filter(x => x.lifnr && x.lifnr !== '-');
                this.mudurlukler = list;
                if (list.length === 1) this.selectedMudur = list[0].qnid;
            } catch(e) { console.error(e); }
        },
        readSavedMudur(data) {
            try {
                const ff = (data?.formFormat || {})['op-doc-requestf-form'] || {};
                for (const rid in ff) {
                    const ents = ff[rid]?.entities || {};
                    if (ents.mudur_qnid) { this.selectedMudur = ents.mudur_qnid; break; }
                }
            } catch(e) {}
        },
        parseStatus(data) {
            try {
                let st = data?.document?.status;
                if (typeof st === 'string') st = JSON.parse(st || '[]');
                if (Array.isArray(st) && st.length) {
                    const last = st[st.length - 1];
                    const op = (last.op_key || '').split('**')[0] || last.op_key || '';
                    const label = (last.op_title || last.title || '').split('**')[0] || op;
                    this.docStatus = { op, label };
                    return;
                }
            } catch(e) {}
            this.docStatus = null;
        },
        async refreshStatus() {
            if (!this.id) return;
            const rsp = await this.plib.request({ url: '/api/v1/document/' + this.id, method: 'GET' }, null);
            this.formDataStore.rawData = rsp?.data || null;
            this.parseStatus(rsp?.data);
        },
        async setDocStatus(opKey, note) {
            this.statusBusy = true;
            try {
                const fd = new FormData();
                fd.append('id', this.id);
                fd.append('op_key', opKey);
                if (note) fd.append('note', note);
                const rsp = await this.plib.request({ url: '/api/v1/trans/set-status', method: 'POST' }, null, fd);
                if (!rsp?.success) {
                    this.plib.toast(Swal, 'error', rsp?.msg || rsp?.message || 'Durum güncellenemedi');
                    return;
                }
                this.plib.toast(Swal, 'success', 'Durum güncellendi');
                await this.refreshStatus();
            } finally {
                this.statusBusy = false;
            }
        },
        async approveMan() {
            const c = await Swal.fire({
                icon: 'question', title: 'Müdürlük Onayı',
                text: 'Talep müdürlük tarafından onaylansın mı?',
                showCancelButton: true, confirmButtonText: 'Evet, Onayla', cancelButtonText: 'Vazgeç',
                confirmButtonColor: '#0e7490',
            });
            if (c.isConfirmed) this.setDocStatus('tr-request-man-approved', '');
        },
        async rejectMan() {
            const c = await Swal.fire({
                icon: 'warning', title: 'Müdürlük Reddi',
                html: '<textarea id="rej-note" class="swal2-textarea" placeholder="Red nedeni yazın..."></textarea>',
                showCancelButton: true, confirmButtonText: 'Reddet', cancelButtonText: 'Vazgeç',
                confirmButtonColor: '#dc2626',
                preConfirm: () => document.getElementById('rej-note').value.trim(),
            });
            if (c.isConfirmed) this.setDocStatus('tr-request-man-rejected', c.value || '-');
        },
        async approveStd() {
            const c = await Swal.fire({
                icon: 'question', title: 'Standardizasyon Onayı',
                text: 'SAP gönderimi için onaylansın mı?',
                showCancelButton: true, confirmButtonText: 'Evet, Onayla', cancelButtonText: 'Vazgeç',
                confirmButtonColor: '#16a34a',
            });
            if (c.isConfirmed) this.setDocStatus('tr-request-std-approved', '');
        },
        listRoute() {
            return this.$route.path.startsWith('/planlamapanel') ? 'PlanlamaFList' : 'PlanlamaFListAdmin';
        },
        goBackToList() {
            this.$router.push({ name: this.listRoute() }).catch(() => {});
        },
        async linkFromSource(qnid) {
            try {
                const rsp = await this.plib.request({ url: '/api/v1/document/' + qnid, method: 'GET' }, null);
                const ff = rsp?.data?.formFormat?.['op-doc-request-form'] || {};
                let head = {};
                for (const rid in ff) {
                    const ents = ff[rid]?.entities || {};
                    for (const k in ents) {
                        if (k.includes('**')) continue;
                        if (head[k] === undefined) head[k] = ents[k];
                    }
                }
                const setHead = (name, val) => {
                    if (!val) return;
                    const inp = document.querySelector(`.area-target[data-tag="op-doc-requestf-form"] [name="${name}"]`);
                    if (inp) { inp.value = String(val); inp.dispatchEvent(new Event('input', { bubbles: true })); }
                };
            setHead('request_no', head.title || '');
                setHead('title', head.title || '');
                setHead('desc', head.desc || '');
                const months = expandMonths(head.donem_bas || '', head.donem_bit || '');
                const iff = rsp?.data?.formFormat?.['op-doc-request-item-form'] || {};
                const byRow = {};
                for (const rid in iff) {
                    const ents = iff[rid]?.entities || {};
                    for (const k in ents) {
                        const parts = k.split('**');
                        if (parts.length < 3) continue;
                        const rk = parts[2];
                        byRow[rk] = byRow[rk] || {};
                        byRow[rk][parts[0]] = ents[k];
                    }
                }
                const items = [];
                for (const base of Object.values(byRow)
                    .map(r => ({ mal_grubu: r.mal_grubu || '', ihale_grubu: r.ihale_grubu || '', prod_code: r.prod_code || '', title: r.title || '', unit: r.unit || '' }))
                    .filter(r => r.prod_code || r.title)) {
                    if (months.length) {
                        for (const m of months) items.push(Object.assign({ ay: m, quantity: '' }, base));
                    } else {
                        items.push(Object.assign({ ay: '', quantity: '' }, base));
                    }
                }
                if (!items.length) { this.plib.toast(Swal, 'info', 'Aktarılacak kalem yok'); return; }
                for (const item of items) {
                    let target = findEmptyRow(this.itemRows());
                    if (!target) {
                        const before = this.itemRows().length;
                        this.addRow();
                        await this.waitForRows(before + 1);
                        const all = this.itemRows();
                        target = all[all.length - 1];
                    }
                    if (target) this.fillRow(target, item);
                    await new Promise(r => setTimeout(r, 30));
                }
                if (items.length) this.plib.toast(Swal, 'success', `${items.length} kalem ihtiyaçtan aktarıldı`);
            } catch (err) {
                console.error(err);
                this.plib.toast(Swal, 'error', 'İhtiyaç yüklenemedi');
            }
        },
        itemArea() {
            return document.querySelector('.area-target[data-tag="op-doc-requestf-item-form"]');
        },
        itemRows() {
            return itemRowsIn(this.itemArea());
        },
        addRow() {
            const icon = document.querySelector('i[id*="requestf_items-subadd"]');
            if (icon) icon.click();
        },
        async waitForRows(n, tries = 20) {
            for (let i = 0; i < tries; i++) {
                if (this.itemRows().length >= n) return true;
                await new Promise(r => setTimeout(r, 60));
            }
            return this.itemRows().length >= n;
        },
        fillRow(row, item, zeroEmpty = false) {
            fillItemRow(row, item, zeroEmpty);
        },
        mapUnit(v) {
            const k = String(v || '').toLocaleUpperCase('tr').trim();
            return UNIT_MAP[k] || '';
        },
        downloadExcelTemplate() {
            const aoa = [
                ['Mal Grubu', 'İhale Grubu', 'Malzeme Kodu', 'Malzeme kısa metni', 'Ölçü Birimi', 'Ay (AA/YYYY)', 'Miktar'],
                ['PREFABRİK BİNA', 'PREFABRİK BİNA', '122.5.5.053.IA', 'PREFABRİK BİNA-EK-1C,A=12M,B=2.6M', 'ADT', '01/2026', '8'],
            ];
            const ws = XLSX.utils.aoa_to_sheet(aoa);
            ws['!cols'] = [{ wch: 20 }, { wch: 20 }, { wch: 20 }, { wch: 40 }, { wch: 14 }, { wch: 14 }, { wch: 12 }];
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Kalemler');
            XLSX.writeFile(wb, 'talep-kalem-sablon.xlsx');
        },
        triggerExcelUpload() {
            if (!this.loadForm) return;
            this.$refs.excelInput && this.$refs.excelInput.click();
        },
        async onExcelSelected(e) {
            const file = e.target.files && e.target.files[0];
            e.target.value = '';
            if (!file) return;
            this.excelBusy = true;
            try {
                const buf = await file.arrayBuffer();
                const wb = XLSX.read(buf, { type: 'array' });
                const ws = wb.Sheets[wb.SheetNames[0]];
                const rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
                // find header row containing Malzeme Kodu
                const norm = s => String(s || '').toLocaleLowerCase('tr').replace(/\s+/g, ' ').trim();
                let hi = rows.findIndex(r => Array.isArray(r) && r.some(c => norm(c).includes('malzeme kodu')));
                if (hi < 0) { this.plib.toast(Swal, 'error', 'Başlık satırı bulunamadı (Malzeme Kodu)'); return; }
                const heads = rows[hi].map(norm);
                const ci = {
                    mal: heads.findIndex(h => h.includes('mal grubu')),
                    ihale: heads.findIndex(h => h.includes('ihale grubu')),
                    kod: heads.findIndex(h => h.includes('malzeme kodu')),
                    ad: heads.findIndex(h => h.includes('kısa metni') || h.includes('kisa metni')),
                    birim: heads.findIndex(h => h.includes('ölçü') || h.includes('olcu') || h.includes('birim')),
                    ay: heads.findIndex(h => h === 'ay' || h.includes('ay (') || (h.includes('ay') && h.includes('/'))),
                    miktar: heads.findIndex(h => h.includes('miktar')),
                };
                if (ci.kod < 0) { this.plib.toast(Swal, 'error', 'Malzeme Kodu sütunu yok'); return; }
                const items = [];
                // matrix format (from İhtiyaç download): month columns like 2026-01 hold quantities
                const mcols = [];
                heads.forEach((h, idx) => {
                    let m = h.match(/^(\d{4})-(\d{1,2})$/);
                    if (m) mcols.push({ idx, ay: String(m[2]).padStart(2, '0') + '/' + m[1] });
                    else {
                        m = h.match(/^(\d{1,2})\/(\d{4})$/);
                        if (m && !h.includes('dönem') && !h.includes('tarih')) mcols.push({ idx, ay: String(m[1]).padStart(2, '0') + '/' + m[2] });
                    }
                });
                const hasQtyCol = ci.miktar >= 0;
                for (let i = hi + 1; i < rows.length; i++) {
                    const r = rows[i];
                    if (!Array.isArray(r)) continue;
                    const prod = String(r[ci.kod] || '').trim();
                    const title = String(ci.ad >= 0 ? (r[ci.ad] || '') : '').trim();
                    if (!prod && !title) continue;
                    const base = {
                        mal_grubu: ci.mal >= 0 ? String(r[ci.mal] || '').trim() : '',
                        ihale_grubu: ci.ihale >= 0 ? String(r[ci.ihale] || '').trim() : '',
                        prod_code: prod,
                        title: title,
                        unit: this.mapUnit(ci.birim >= 0 ? r[ci.birim] : ''),
                    };
                    if (mcols.length) {
                        for (const mc of mcols) {
                            const q = String(r[mc.idx] || '').trim();
                            items.push(Object.assign({ ay: mc.ay, quantity: q }, base));
                        }
                    } else {
                        items.push(Object.assign({
                            ay: ci.ay >= 0 ? String(r[ci.ay] || '').trim() : '',
                            quantity: hasQtyCol ? String(r[ci.miktar] || '').trim() : '',
                        }, base));
                    }
                }
                if (!items.length) { this.plib.toast(Swal, 'info', 'Excelde kalem bulunamadı'); return; }
                // rule: excel must contain the full predestined set (prod+ay), even with empty/zero qty
                const keyOf = o => String(o.prod_code || '').trim() + '|' + String(o.ay || '').trim();
                const expected = [];
                this.itemRows().forEach(r => {
                    const p = r.querySelector('[name^="prod_code**"]');
                    const a = r.querySelector('[name^="ay**"]');
                    const pv = p ? String(p.value || '').trim() : '';
                    if (!pv) return;
                    expected.push({ prod_code: pv, ay: a ? String(a.value || '').trim() : '' });
                });
                if (expected.length) {
                    const expKeys = new Set(expected.map(e => e.prod_code + '|' + e.ay));
                    const gotKeys = new Set(items.map(keyOf));
                    const missing = expected.filter(e => !gotKeys.has(e.prod_code + '|' + e.ay));
                    const seenExtra = new Set();
                    const extra = [];
                    items.forEach(o => {
                        const k = keyOf(o);
                        if (!expKeys.has(k) && !seenExtra.has(k)) { seenExtra.add(k); extra.push(o); }
                    });
                    if (missing.length || extra.length) {
                        let html = 'Excel geçersiz — kalem kümesi uyuşmuyor.<br>';
                        if (missing.length) html += '<br><b>Eksik satırlar:</b><br>' + missing.slice(0, 10).map(m => '• ' + m.prod_code + ' / ' + (m.ay || '-')).join('<br>');
                        if (extra.length) html += '<br><b>Fazladan satırlar:</b><br>' + extra.slice(0, 10).map(o => '• ' + o.prod_code + ' / ' + (o.ay || '-')).join('<br>');
                        Swal.fire({ icon: 'warning', title: 'Excel Geçersiz', html, confirmButtonText: 'Tamam' });
                        return;
                    }
                }
                // valid excel → attach the file itself to the Talep's excel slot (versioned on save like order files)
                try {
                    const slot = document.querySelector('.area-target[data-tag="op-doc-requestf-form"] input[name^="talep_excel_file"]');
                    if (slot) {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        slot.files = dt.files;
                        slot.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                } catch (e) { console.error('[TalepExcel] slot inject failed', e); }
                let done = 0;
                for (const item of items) {
                    // 1) same material+month row 2) empty row 3) new row
                    let target = findMatchingRow(this.itemRows(), item);
                    if (!target) target = findEmptyRow(this.itemRows());
                    if (!target) {
                        const before = this.itemRows().length;
                        this.addRow();
                        await this.waitForRows(before + 1);
                        const all = this.itemRows();
                        target = all[all.length - 1];
                    }
                    if (target) { this.fillRow(target, item, true); done++; }
                    await new Promise(r => setTimeout(r, 30));
                }
                this.plib.toast(Swal, 'success', `${done} kalem Excelden aktarıldı`);
            } catch (err) {
                console.error(err);
                this.plib.toast(Swal, 'error', 'Excel okunamadı: ' + (err.message || err));
            } finally {
                this.excelBusy = false;
            }
        },
        async submitForm(formData) {
            if (!this.canSave) { this.plib.toast(Swal, 'info', 'Bu aşamada kayıt değiştirilemez'); return; }
            this.isSubmitting = true;
            try {
            formData.typeKey = 'op-doc-requestf';
            // stamp which müdürlük files this talep (user picks when bound to several)
            if (!this.id) {
                try {
                    let mq = '', mt = '';
                    const sel = (this.mudurlukler || []).find(x => x.qnid === this.selectedMudur);
                    if (sel) { mq = sel.qnid; mt = sel.client_system + '-' + sel.lifnr + ' ' + sel.title; }
                    if (!mq && (this.mudurlukler || []).length > 1) {
                        this.plib.toast(Swal, 'info', 'Talep eden müdürlüğü seçmelisiniz');
                        return;
                    }
                    if (!mt) mt = (this.authStore.currentStatus && (this.authStore.currentStatus.clientTitle || this.authStore.currentStatus.main_name)) || this.authStore.userName || '';
                    for (const k in (formData.dynamicF || {})) {
                        if (k.startsWith('op-doc-requestf-form**')) {
                            formData.dynamicF[k].entities = formData.dynamicF[k].entities || {};
                            if (mq && !formData.dynamicF[k].entities.mudur_qnid) formData.dynamicF[k].entities.mudur_qnid = mq;
                            if (mt && !formData.dynamicF[k].entities.firma_adi) formData.dynamicF[k].entities.firma_adi = String(mt);
                        }
                    }
                } catch(e) {}
            }
            const check = this.plib.checkForm('.form-item');
            if (!check.valid) { this.plib.toast(Swal, 'info', 'Eksik Alanları Doldurmalısınız'); return; }
            const envelope = new FormData();
            envelope.append('data', JSON.stringify(formData));
            for (let key in formData.files) {
                const item = formData.files[key];
                if (!item) continue;
                if (item.reference) envelope.append(key, JSON.stringify(item.reference));
                else if (item.file) envelope.append(key, item.file);
            }
            const rsp = await this.plib.request({
                url: '/api/v1/document' + (this.id ? '/' + this.id : ''),
                method: this.id ? 'PUT' : 'POST'
            }, null, envelope);
            if (!rsp?.success) {
                this.plib.toast(Swal, 'error', rsp?.msg || 'Kayıt oluşturulamadı');
                return;
            }
            this.plib.toast(Swal, 'success', 'İşlem Tamamlandı', () => this.$router.push({ name: this.listRoute() }));
            } finally {
                this.isSubmitting = false;
            }
        }
    }
};
</script>

<template>
    <div v-if="isFront" class="tedarik-detail" style="padding-bottom: 130px;">
        <div class="tedarik-detail-header">
            <div class="tedarik-detail-header-top">
                <div class="tedarik-detail-header-left">
                    <button @click="goBackToList" class="tedarik-back-link" title="Talep Dökümanları" aria-label="Geri">
                        <i class="ki-outline ki-arrow-left"></i>
                        <span>Tüm Talepler</span>
                    </button>
                    <div class="tedarik-company-row">
                        <span class="tedarik-company-icon"><i class="ki-outline ki-clipboard"></i></span>
                        <div class="tedarik-detail-title">{{ id ? 'Talep Dökümanı Detayı' : 'Yeni Talep Dökümanı' }}</div>
                        <span v-if="mudurLabel" class="tedarik-tdno-badge">{{ mudurLabel }}</span>
                    </div>
                </div>
                <div class="tedarik-detail-meta">
                    <div class="tedarik-meta-item"><span>Durum :</span><b>{{ statusLabel }}</b></div>
                </div>
            </div>
            <div v-if="id && docStatus" class="tedarik-status-drum" :style="{ background: statusColor, color: '#fff' }">
                <span class="tedarik-status-dot"></span>
                <span>{{ statusLabel }}</span>
            </div>
        </div>
        <div class="tedarik-step-card" v-if="loadForm && !id">
            <div class="tedarik-step-head"><span class="tedarik-step-num">1</span><span>Talep eden müdürlüğü seçiniz.</span></div>
            <div class="tedarik-step-body" style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:13px;font-weight:800;color:#92400e;">Müdürlük:</span>
                <select v-if="mudurlukler.length > 1" v-model="selectedMudur" class="form-control form-select" style="max-width:320px;">
                    <option value="">Seçiniz</option>
                    <option v-for="m in mudurlukler" :key="m.qnid" :value="m.qnid">{{ m.client_system }}-{{ m.lifnr }} {{ m.title }}</option>
                </select>
                <span v-else style="font-size:13px;font-weight:700;color:#0f172a;">{{ mudurLabel || 'Müdürlük bulunamadı' }}</span>
            </div>
        </div>
        <div class="tedarik-step-card" v-else-if="loadForm && id && mudurLabel">
            <div class="tedarik-step-head"><span class="tedarik-step-num">1</span><span>Talep eden müdürlük.</span></div>
            <div class="tedarik-step-body"><span style="font-size:13px;font-weight:700;color:#0f172a;">{{ mudurLabel }}</span></div>
        </div>
        <div class="tedarik-step-card">
            <div class="tedarik-step-head"><span class="tedarik-step-num">2</span><span>Kalemleri doldurun, Excel ile toplu yükleyebilirsiniz.</span></div>
            <div class="tedarik-step-body">
                <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
                    <button v-if="canSave" type="button" class="tedarik-btn-light" @click="downloadExcelTemplate">Şablon</button>
                    <button v-if="canSave" type="button" class="tedarik-btn-light" :disabled="excelBusy || !loadForm" @click="triggerExcelUpload">{{ excelBusy ? 'Aktarılıyor...' : "Excel'den Yükle" }}</button>
                    <input ref="excelInput" type="file" accept=".xls,.xlsx" style="display:none" @change="onExcelSelected" />
                    <template v-if="id && docStatus && docStatus.op === 'tr-request-man-awaiting' && canMan">
                        <button type="button" class="tedarik-btn-ok" :disabled="statusBusy" @click="approveMan">Onayla</button>
                        <button type="button" class="tedarik-btn-danger" :disabled="statusBusy" @click="rejectMan">Reddet</button>
                    </template>
                    <template v-else-if="id && docStatus && docStatus.op === 'tr-request-man-approved' && canStd">
                        <button type="button" class="tedarik-btn-ok" :disabled="statusBusy" @click="approveStd">Standardizasyon Onayı</button>
                    </template>
                </div>
                <div v-if="loadForm && id" style="margin-bottom:12px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
                    <div style="font-size:13px;font-weight:800;color:#0f172a;margin-bottom:8px;">Yüklenen Excel Dosyaları <span v-if="excelFiles.length" style="font-size:11px;background:#ecfeff;color:#0e7490;border-radius:999px;padding:2px 8px;">{{ excelFiles.length }}</span></div>
                    <div v-if="excelLoading" style="font-size:13px;color:#64748b;text-align:center;">Yükleniyor...</div>
                    <div v-else-if="!excelFiles.length" style="font-size:13px;color:#64748b;text-align:center;">Henüz dosya yüklenmedi — Excel içe aktarın ve Kaydedin.</div>
                    <div v-for="(f, i) in excelFiles" :key="f.qnid || f.id" style="display:flex;align-items:center;gap:10px;padding:8px 0;border-top:1px solid #f1f5f9;font-size:13px;">
                        <span :style="`font-size:11px;font-weight:800;padding:2px 10px;border-radius:999px;white-space:nowrap;background:${i === 0 ? '#dcfce7' : '#f1f5f9'};color:${i === 0 ? '#15803d' : '#64748b'}`">{{ i === 0 ? 'Güncel' : ('v' + (excelFiles.length - i)) }}</span>
                        <span style="font-weight:600;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ (f.display_name || '').split('/').pop() }}</span>
                        <span style="font-size:12px;color:#94a3b8;white-space:nowrap;">{{ excelDate(f) }}</span>
                        <span style="flex:1;"></span>
                        <button type="button" class="tedarik-btn-light" @click="openExcel(f.qnid)">Önizle</button>
                        <button type="button" class="tedarik-btn-light" @click="downloadExcel(f)">İndir</button>
                    </div>
                </div>
                <Form v-if="loadForm" formtypes="op-doc-requestf-form,op-doc-requestf-item-form" savebtntitle="Kaydet" :savecallback="submitForm" :readonlyFields="roFields" :showsave="canSave" />
                <div v-if="isSubmitting" style="position:fixed;left:50%;bottom:28px;transform:translateX(-50%);z-index:9999;display:inline-flex;align-items:center;gap:10px;background:#0f172a;color:#fff;font-size:13px;font-weight:700;padding:10px 18px;border-radius:999px;">Kaydediliyor — dosyalar işleniyor...</div>
            </div>
        </div>
    </div>
    <div v-else class="planlama-rform" :class="{ 'is-readonly': !canSave }">
        <div class="planlama-rform__head">
            <button type="button" class="planlama-rform__back" @click="$router.back()">← Geri</button>
            <h2>{{ id ? (canSave ? 'Talep Dökümanı Düzenle' : 'Talep Dökümanı Detayı') : 'Yeni Talep Dökümanı' }}</h2>
            <template v-if="loadForm && !id">
                <span class="planlama-mudur__inline-label">Müdürlük:</span>
                <select v-if="mudurlukler.length > 1" v-model="selectedMudur" class="form-control form-select planlama-mudur__select">
                    <option value="">Seçiniz</option>
                    <option v-for="m in mudurlukler" :key="m.qnid" :value="m.qnid">{{ m.client_system }}-{{ m.lifnr }} {{ m.title }}</option>
                </select>
                <span v-else class="planlama-mudur__fixed">{{ mudurLabel || 'Müdürlük bulunamadı' }}</span>
            </template>
            <template v-if="loadForm && id && mudurLabel">
                <span class="planlama-mudur__inline-label">Müdürlük:</span>
                <span class="planlama-mudur__fixed">{{ mudurLabel }}</span>
            </template>
            <span class="planlama-rform__spacer"></span>
            <button v-if="canSave" type="button" class="planlama-rform__btn" @click="downloadExcelTemplate">Şablon</button>
            <button v-if="canSave" type="button" class="planlama-rform__btn planlama-rform__btn--primary" :disabled="excelBusy || !loadForm" @click="triggerExcelUpload">
                {{ excelBusy ? 'Aktarılıyor...' : "Excel'den Yükle" }}
            </button>
            <input ref="excelInput" type="file" accept=".xls,.xlsx" style="display:none" @change="onExcelSelected" />
        </div>
        <div v-if="id && docStatus" class="planlama-status">
            <span class="planlama-status__pill" :style="{ background: statusColor }">{{ statusLabel }}</span>
            <span class="planlama-status__spacer"></span>
            <template v-if="docStatus.op === 'tr-request-man-awaiting' && canMan">
                <button type="button" class="planlama-rform__btn planlama-rform__btn--ok" :disabled="statusBusy" @click="approveMan">Onayla</button>
                <button type="button" class="planlama-rform__btn planlama-rform__btn--danger" :disabled="statusBusy" @click="rejectMan">Reddet</button>
            </template>
            <template v-else-if="docStatus.op === 'tr-request-man-approved' && canStd">
                <button type="button" class="planlama-rform__btn planlama-rform__btn--ok" :disabled="statusBusy" @click="approveStd">Standardizasyon Onayı</button>
            </template>
            <span v-if="statusBusy" class="planlama-status__loading"><span class="spin"></span> Kaydediliyor...</span>
            <template v-else-if="docStatus.op === 'tr-request-man-rejected' || docStatus.op === 'tr-request-std-approved'">
                <span class="planlama-status__lock">İşlem tamamlandı — değiştirilemez</span>
            </template>
            <template v-else-if="docStatus.op === 'tr-request-man-approved'">
                <span class="planlama-status__lock">Standardizasyon onayı bekleniyor</span>
            </template>
        </div>
        <div v-if="loadForm && id" class="planlama-excel">
            <div class="planlama-excel__head">Yüklenen Excel Dosyaları <span v-if="excelFiles.length" class="planlama-excel__count">{{ excelFiles.length }}</span></div>
            <div v-if="excelLoading" class="planlama-excel__loading">Yükleniyor...</div>
            <div v-else-if="!excelFiles.length" class="planlama-excel__loading">Henüz dosya yüklenmedi — Excel içe aktarın ve Kaydedin.</div>
            <div v-for="(f, i) in excelFiles" :key="f.qnid || f.id" class="planlama-excel__row">
                <span class="planlama-excel__ver" :class="{ 'is-current': i === 0 }">{{ i === 0 ? 'Güncel' : ('v' + (excelFiles.length - i)) }}</span>
                <span class="planlama-excel__name">{{ (f.display_name || '').split('/').pop() }}</span>
                <span class="planlama-excel__date">{{ excelDate(f) }}</span>
                <span class="planlama-excel__spacer"></span>
                <button type="button" class="planlama-rform__btn" @click="openExcel(f.qnid)">Önizle</button>
                <button type="button" class="planlama-rform__btn" @click="downloadExcel(f)">İndir</button>
            </div>
        </div>
        <Form v-if="loadForm" formtypes="op-doc-requestf-form,op-doc-requestf-item-form" savebtntitle="Kaydet" :savecallback="submitForm" :readonlyFields="roFields" :showsave="canSave" />
        <div v-if="isSubmitting" class="planlama-saving"><span class="spin"></span> Kaydediliyor — dosyalar işleniyor...</div>
    </div>
</template>

<style scoped>
.tedarik-detail-header { background: linear-gradient(135deg, #ffffff 0%, #fcfdff 100%) !important; border:1px solid #e2e8f0 !important; border-radius:16px !important; padding:18px 22px 16px !important; box-shadow:0 8px 24px rgba(15,23,42,0.04), 0 2px 8px rgba(15,23,42,0.03) !important; position:relative; overflow:hidden; margin-bottom:14px; }
.tedarik-detail-header::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background: linear-gradient(90deg, #FF5A1F 0%, #fb923c 50%, #FF5A1F 100%); }
.tedarik-detail-header-top { display:flex; justify-content:space-between; align-items:flex-end; gap:20px; flex-wrap:wrap; padding-bottom:12px; border-bottom:1px solid #f1f5f9; margin-bottom:10px; }
.tedarik-detail-header-left { display:flex; flex-direction:column; gap:10px; }
.tedarik-back-link { display:inline-flex; align-items:center; gap:6px; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:999px; padding:6px 14px; font-size:12.5px; font-weight:700; cursor:pointer; width:fit-content; }
.tedarik-back-link:hover { background:#ffedd5; }
.tedarik-company-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.tedarik-company-icon { width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#FF5A1F,#ff8a4a); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; }
.tedarik-detail-title { font-size:18px; font-weight:800; color:#0f172a; letter-spacing:-0.01em; }
.tedarik-tdno-badge { font-size:11.5px; font-weight:800; background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; border-radius:999px; padding:4px 10px; white-space:nowrap; }
.tedarik-detail-meta { display:flex; gap:18px; flex-wrap:wrap; }
.tedarik-meta-item { font-size:11.5px; color:#94a3b8; display:flex; gap:6px; align-items:baseline; }
.tedarik-meta-item b { color:#0f172a; font-size:13px; }
.tedarik-status-drum { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; border-radius:999px; font-size:12px; font-weight:700; }
.tedarik-status-dot { width:7px; height:7px; border-radius:999px; background:currentColor; }
.tedarik-step-card { background:#fff; border:1px solid #f1f5f9; border-radius:16px; padding:18px 20px; margin-bottom:14px; box-shadow:0 2px 8px rgba(0,0,0,0.03); }
.tedarik-step-card { overflow-x:clip; }
.tedarik-step-head { display:flex; align-items:center; gap:10px; font-size:14px; font-weight:700; color:#0f172a; margin-bottom:12px; }
.tedarik-step-num { width:26px; height:26px; border-radius:999px; background:#FF5A1F; color:#fff; font-size:13px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
.tedarik-step-body :deep(.item-row) { margin-left:0 !important; margin-right:0 !important; }
.tedarik-step-body { font-size:13.5px; }
.tedarik-btn-light { height:38px; padding:0 16px; border-radius:10px; background:#fff; border:1.5px solid #e2e8f0; color:#334155; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; }
.tedarik-btn-light:hover { background:#f8fafc; border-color:#cbd5e1; }
.tedarik-btn-light:disabled { opacity:.55; cursor:not-allowed; }
.tedarik-btn-ok { height:38px; padding:0 16px; border-radius:10px; background:#16a34a; border:1px solid #16a34a; color:#fff; font-size:13px; font-weight:700; cursor:pointer; }
.tedarik-btn-danger { height:38px; padding:0 16px; border-radius:10px; background:#fff; border:1px solid #dc2626; color:#dc2626; font-size:13px; font-weight:700; cursor:pointer; }
.planlama-rform { background: #fff; border-radius: 12px; padding: 20px; }
.planlama-rform__head { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
.planlama-rform__head h2 { margin: 0; font-size: 17px; font-weight: 800; color: #0f172a; }
.planlama-rform__spacer { flex: 1; }
.planlama-rform__back { border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 999px; padding: 6px 14px; font-size: 13px; font-weight: 700; color: #334155; cursor: pointer; }
.planlama-rform__back:hover { background: #e2e8f0; }
.planlama-rform__btn { border: 1px solid #e2e8f0; background: #fff; border-radius: 999px; padding: 6px 14px; font-size: 13px; font-weight: 700; color: #0e7490; cursor: pointer; }
.planlama-rform__btn:hover { background: #ecfeff; }
.planlama-rform__btn--primary { background: #0e7490; border-color: #0e7490; color: #fff; }
.planlama-rform__btn--primary:hover { background: #155e75; }
.planlama-rform__btn:disabled { opacity: .6; cursor: default; }
.planlama-excel { margin-top: 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; }
.planlama-excel__head { font-size: 14px; font-weight: 800; color: #0f172a; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
.planlama-excel__count { font-size: 11px; font-weight: 800; background: #ecfeff; color: #0e7490; border-radius: 999px; padding: 2px 8px; }
.planlama-excel__loading { font-size: 13px; color: #64748b; text-align: center; padding: 8px 0; }
.planlama-excel__row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-top: 1px solid #f1f5f9; font-size: 13px; }
.planlama-excel__row:first-of-type { border-top: none; }
.planlama-excel__ver { font-size: 11px; font-weight: 800; padding: 2px 10px; border-radius: 999px; background: #f1f5f9; color: #64748b; white-space: nowrap; }
.planlama-excel__ver.is-current { background: #dcfce7; color: #15803d; }
.planlama-excel__name { font-weight: 600; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.planlama-excel__date { font-size: 12px; color: #94a3b8; white-space: nowrap; }
.planlama-excel__spacer { flex: 1; }
.planlama-rform.is-readonly :deep(.fab-btns-wrapper),
.planlama-rform.is-readonly :deep(.fab-wrapper),
.planlama-rform.is-readonly :deep(i[id*="subadd"]),
.planlama-rform.is-readonly :deep(.rmv-btn-form) { display: none !important; }
.planlama-mudur__inline-label { font-size: 13px; font-weight: 800; color: #92400e; white-space: nowrap; }
.planlama-mudur__select { max-width: 300px; }
.planlama-mudur__fixed { font-size: 13px; font-weight: 700; color: #0f172a; }
.planlama-rform__btn--danger { border-color: #dc2626; color: #dc2626; }
.planlama-rform__btn--danger:hover { background: #fef2f2; }
.planlama-rform__btn--ok { background: #16a34a; border-color: #16a34a; color: #fff; }
.planlama-rform__btn--ok:hover { background: #15803d; }
.planlama-status { display: flex; align-items: center; gap: 10px; margin: 0 0 16px; padding: 14px 16px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
.planlama-status__pill { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 800; color: #fff; }
.planlama-status__spacer { flex: 1; }
.planlama-status__lock { font-size: 12.5px; color: #64748b; font-weight: 600; }
.planlama-status__loading { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700; color: #0e7490; }
.planlama-status__loading .spin { width: 14px; height: 14px; border-radius: 999px; border: 2px solid #99f6e4; border-top-color: #0e7490; animation: planlama-spin .7s linear infinite; display: inline-block; }
@keyframes planlama-spin { to { transform: rotate(360deg); } }
/* item section: TALEP KALEMLERİ as header strip, kalem sub-cards flat below */
.tedarik-step-body :deep(.item-row > .form-label) { display:flex; align-items:center; gap:8px; background:linear-gradient(135deg,#fff 0%,#fff7ed 100%); border:1px solid #ffe4cc; border-top:3px solid #FF5A1F; color:#9a3412 !important; font-size:12.5px !important; font-weight:800 !important; letter-spacing:.06em; border-radius:12px; padding:10px 14px; margin:0 0 12px !important; text-transform:uppercase; }
.tedarik-step-body :deep(.item-row:has(.multiple-item-row)) { counter-reset:planlama-kalem; background:transparent !important; border:0 !important; border-radius:0 !important; box-shadow:none !important; padding:0 !important; margin:0 0 16px !important; }
.tedarik-step-body :deep(.item-row:has(.multiple-item-row) > .form-label) { margin:0 0 12px !important; }
.tedarik-step-body :deep(.item-row:has(.multiple-item-row) > div) { margin:0 !important; padding:0 !important; border:0 !important; background:transparent !important; border-radius:0 !important; row-gap:12px; }
.tedarik-step-body :deep(.item-row:has(.multiple-item-row) .multiple-item-card) { position:relative; background:#fff !important; border:1px solid #ffe4cc !important; border-top:3px solid #FF5A1F !important; border-radius:16px !important; box-shadow:0 6px 18px rgba(255,90,31,.07) !important; padding:14px 16px 6px !important; margin:0 !important; row-gap:12px; column-gap:12px; transition:border-color .15s ease, box-shadow .15s ease; }
.tedarik-step-body :deep(.item-row:has(.multiple-item-row) .multiple-item-card:hover) { border-color:#fdba74 !important; box-shadow:0 10px 24px rgba(255,90,31,.12) !important; }
.tedarik-step-body :deep(.multiple-item-card)::before { counter-increment:planlama-kalem; content:"Kalem " counter(planlama-kalem); flex:0 0 100%; max-width:100%; display:flex; align-items:center; font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#9a3412; background:#fff7ed; border:1px solid #ffe4cc; border-radius:8px; padding:6px 12px; margin:0 0 2px; }
/* kalem grid: gutter-free columns aligned to the KALEM strip edges */
.tedarik-step-body :deep(.item-row:has(.multiple-item-row) .multiple-item-card > [class*="col-"]) { padding-left:0 !important; padding-right:0 !important; margin-left:0 !important; margin-right:0 !important; min-width:0; }
.tedarik-step-body :deep(.item-row:has(.multiple-item-row) .multiple-item-card > .col-md-3) { flex:0 0 calc(25% - 9px) !important; max-width:calc(25% - 9px) !important; width:calc(25% - 9px) !important; }
.tedarik-step-body :deep(.item-row:has(.multiple-item-row) .multiple-item-card > .col-md-6) { flex:0 0 calc(50% - 6px) !important; max-width:calc(50% - 6px) !important; width:calc(50% - 6px) !important; }
@media (max-width:767.98px) {
.tedarik-step-body :deep(.item-row:has(.multiple-item-row) .multiple-item-card > [class*="col-"]) { flex:1 1 100% !important; max-width:100% !important; width:100% !important; }
}
.tedarik-step-body :deep(.multiple-item-row .input-label) { font-size:11.5px !important; font-weight:800 !important; color:#9a3412 !important; text-transform:uppercase; letter-spacing:.04em; margin:0 0 6px !important; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tedarik-step-body :deep(.multiple-item-row .form-control) { min-height:44px; border-radius:12px; font-size:13.5px; border:1.5px solid #e2e8f0; background:#fff; min-width:0; }
.tedarik-step-body :deep(.multiple-item-row .form-control:focus) { border-color:#FF5A1F; box-shadow:0 0 0 4px rgba(255,90,31,.12); outline:none; }
.tedarik-step-body :deep(.multiple-item-row select.form-control) { padding-right:32px; cursor:pointer; }
.tedarik-step-body :deep(.multiple-item-row .input-group) { flex-wrap:nowrap; width:100%; }
.tedarik-step-body :deep(.multiple-item-row .input-group:has(> .rmv-btn-form) > .form-control) { border-top-right-radius:0; border-bottom-right-radius:0; }
.tedarik-step-body :deep(.multiple-item-row .rmv-btn-form) { border-radius:0 12px 12px 0; align-items:center; display:inline-flex; background:#fef2f2; color:#dc2626; border:1px solid #fecaca; cursor:pointer; padding:0 12px; }
.tedarik-step-body :deep(.multiple-item-row .rmv-btn-form:hover) { background:#fee2e2; }
.tedarik-step-body :deep(i[id*="subadd"]) { background:#FF5A1F !important; color:#fff !important; border-radius:999px !important; width:34px; height:34px; display:inline-flex !important; align-items:center !important; justify-content:center !important; font-size:18px !important; cursor:pointer; box-shadow:0 4px 12px rgba(255,90,31,.25); margin-left:8px; }
.tedarik-step-body :deep(i[id*="subadd"]:hover) { background:#ea4a0f !important; }
.tedarik-step-body :deep(i[id*="subadd"][hidden]) { display:none !important; }
/* BAŞLIK + BAĞLI İHTİYAÇ NO side by side (6-6), AÇIKLAMA full width (12) */
.tedarik-step-body :deep(.area-target[data-tag="op-doc-requestf-form"] > .dform-row > .card-body) { display:flex; flex-wrap:wrap; gap:0 16px; }
.tedarik-step-body :deep(.area-target[data-tag="op-doc-requestf-form"] .item-row:has([name="title"])),
.tedarik-step-body :deep(.area-target[data-tag="op-doc-requestf-form"] .item-row:has([name="request_no"])) { flex:1 1 calc(50% - 8px); min-width:280px; max-width:calc(50% - 8px); }
.tedarik-step-body :deep(.area-target[data-tag="op-doc-requestf-form"] .item-row:has([name="desc"])) { flex:1 1 100%; max-width:100%; }
/* strip-to-input alignment: kill the bootstrap gutter on header field wrappers */
.tedarik-step-body :deep(.area-target[data-tag="op-doc-requestf-form"] .item-row > .col-lg-12.row) { padding-left:0 !important; padding-right:0 !important; margin-left:0 !important; margin-right:0 !important; }
.tedarik-step-body :deep(.area-target[data-tag="op-doc-requestf-form"] .item-row > .col-lg-12.row > .form-control) { width:100% !important; max-width:100% !important; }
/* flatten shared Form's admin cards inside the panel step card */
.tedarik-step-body :deep(.area-target > .dform-row.card) { background:transparent !important; border:0 !important; box-shadow:none !important; margin:0 !important; padding:0 !important; }
.tedarik-step-body :deep(.area-target > .dform-row.card > .card-body) { padding:0 !important; }
.tedarik-step-body :deep(.area-target + .area-target) { margin-top:20px; padding-top:20px; border-top:1px dashed #ffe4cc; }
.tedarik-step-body :deep(.item-row) { margin:0 0 14px !important; }
.tedarik-step-body :deep(.item-row > .form-label) { margin-top:0 !important; }
.tedarik-step-body :deep(.form-control) { min-height:44px; border-radius:12px; font-size:13.5px; border:1.5px solid #e2e8f0; background:#fff; }
.tedarik-step-body :deep(.form-control:focus) { border-color:#FF5A1F; box-shadow:0 0 0 4px rgba(255,90,31,.12); outline:none; }
.tedarik-step-body :deep(textarea.form-control) { min-height:96px; }
.planlama-saving { position: fixed; left: 50%; bottom: 28px; transform: translateX(-50%); z-index: 9999; display: inline-flex; align-items: center; gap: 10px; background: #0f172a; color: #fff; font-size: 13px; font-weight: 700; padding: 10px 18px; border-radius: 999px; box-shadow: 0 12px 32px rgba(15,23,42,.35); }
.planlama-saving .spin { width: 16px; height: 16px; border-radius: 999px; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; animation: planlama-spin .7s linear infinite; display: inline-block; }
</style>
