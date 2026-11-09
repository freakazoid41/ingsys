<script>
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { useRoute } from 'vue-router';
import { useNavigationStore } from '@/stores/navigation';
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
        };
    },
    computed: {
        canEdit() { return (this.authStore?.permissions || []).includes('per-041-05-03'); },
        canMan() { return (this.authStore?.permissions || []).includes('per-041-05-01'); },
        canStd() { return (this.authStore?.permissions || []).includes('per-041-05-04'); },
        roFields() {
            const base = ['title', 'request_no', 'desc', 'mal_grubu', 'ihale_grubu', 'prod_code', 'unit', 'ay'];
            return this.canEdit ? base : base.concat(['quantity']);
        },
        mudurLabel() {
            const m = (this.mudurlukler || []).find(x => x.qnid === this.selectedMudur);
            return m ? (m.client_system + '-' + m.lifnr + ' ' + m.title) : '';
        },
        statusLabel() {
            const map = {
                'tr-request-man-awaiting': 'Müdürlük Onayına Gönderildi',
                'tr-request-man-approved': 'Müdürlük Onayladı',
                'tr-request-man-rejected': 'Müdürlük Reddetti',
                'tr-request-std-approved': 'Standardizasyon Onayladı',
            };
            if (!this.docStatus) return '-';
            return map[this.docStatus.op] || this.docStatus.label || this.docStatus.op;
        },
        statusColor() {
            const map = {
                'tr-request-man-awaiting': '#FF5A1F',
                'tr-request-man-approved': '#0e7490',
                'tr-request-man-rejected': '#dc2626',
                'tr-request-std-approved': '#16a34a',
            };
            return (this.docStatus && map[this.docStatus.op]) || '#64748b';
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
            }
        });
        this.navigationStore.toggle(true);
    },
    methods: {
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
                const months = this.expandMonths(head.donem_bas || '', head.donem_bit || '');
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
                    let target = this.itemRows().find(r => {
                        const p = r.querySelector('[name^="prod_code**"]');
                        const t = r.querySelector('[name^="title**"]');
                        return p && t && !p.value && !t.value;
                    });
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
            const area = this.itemArea();
            return area ? Array.from(area.querySelectorAll('.multiple-item-row')) : [];
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
            const set = (prefix, val) => {
                if (val === undefined || val === null || val === '') {
                    if (!(zeroEmpty && prefix === 'quantity')) return;
                    val = '0';
                }
                const inp = row.querySelector(`[name^="${prefix}**"]`);
                if (!inp) return;
                inp.value = String(val);
                inp.dispatchEvent(new Event('input', { bubbles: true }));
            };
            set('mal_grubu', item.mal_grubu);
            set('ihale_grubu', item.ihale_grubu);
            set('prod_code', item.prod_code);
            set('title', item.title);
            set('ay', item.ay);
            set('quantity', item.quantity);
            if (item.unit) {
                const sel = row.querySelector('select[name^="unit**"]');
                if (sel) {
                    sel.value = item.unit;
                    sel.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        },
        mapUnit(v) {
            const k = String(v || '').toLocaleUpperCase('tr').trim();
            return UNIT_MAP[k] || '';
        },
        expandMonths(bas, bit) {
            const p = s => {
                const m = String(s || '').trim().match(/^(\d{1,2})\/(\d{4})$/);
                if (!m) return null;
                return { m: parseInt(m[1], 10), y: parseInt(m[2], 10) };
            };
            const b = p(bas), e = p(bit);
            if (!b || !e || b.m < 1 || b.m > 12 || e.m < 1 || e.m > 12) return [];
            const out = [];
            let m = b.m, y = b.y, guard = 0;
            while ((y < e.y || (y === e.y && m <= e.m)) && guard++ < 36) {
                out.push(String(m).padStart(2, '0') + '/' + y);
                if (++m > 12) { m = 1; y++; }
            }
            return out;
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
                let done = 0;
                for (const item of items) {
                    // 1) same material+month row 2) empty row 3) new row
                    let target = this.itemRows().find(r => {
                        const p = r.querySelector('[name^="prod_code**"]');
                        const a = r.querySelector('[name^="ay**"]');
                        if (!p || p.value.trim() !== item.prod_code) return false;
                        const av = a ? a.value.trim() : '';
                        return !av || !item.ay || av === item.ay;
                    });
                    if (!target) target = this.itemRows().find(r => {
                        const p = r.querySelector('[name^="prod_code**"]');
                        const t = r.querySelector('[name^="title**"]');
                        return p && t && !p.value && !t.value;
                    });
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
            if (!this.canEdit) { this.plib.toast(Swal, 'info', 'Salt okunur — düzenleme yetkiniz yok'); return; }
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
    <div class="planlama-rform" :class="{ 'is-readonly': !canEdit }">
        <div class="planlama-rform__head">
            <button type="button" class="planlama-rform__back" @click="$router.back()">← Geri</button>
            <h2>{{ id ? (canEdit ? 'Talep Dökümanı Düzenle' : 'Talep Dökümanı Detayı') : 'Yeni Talep Dökümanı' }}</h2>
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
            <button v-if="canEdit" type="button" class="planlama-rform__btn" @click="downloadExcelTemplate">Şablon</button>
            <button v-if="canEdit" type="button" class="planlama-rform__btn planlama-rform__btn--primary" :disabled="excelBusy || !loadForm" @click="triggerExcelUpload">
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
        <Form v-if="loadForm" formtypes="op-doc-requestf-form,op-doc-requestf-item-form" savebtntitle="Kaydet" :savecallback="submitForm" :readonlyFields="roFields" />
        <div v-if="isSubmitting" class="planlama-saving"><span class="spin"></span> Kaydediliyor — dosyalar işleniyor...</div>
    </div>
</template>

<style scoped>
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
.planlama-saving { position: fixed; left: 50%; bottom: 28px; transform: translateX(-50%); z-index: 9999; display: inline-flex; align-items: center; gap: 10px; background: #0f172a; color: #fff; font-size: 13px; font-weight: 700; padding: 10px 18px; border-radius: 999px; box-shadow: 0 12px 32px rgba(15,23,42,.35); }
.planlama-saving .spin { width: 16px; height: 16px; border-radius: 999px; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; animation: planlama-spin .7s linear infinite; display: inline-block; }
</style>
