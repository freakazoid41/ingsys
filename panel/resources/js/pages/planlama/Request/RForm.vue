<script>
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { useRoute } from 'vue-router';
import { useNavigationStore } from '@/stores/navigation';
import { useAuthStore } from '@/stores/auth';
import { useFormDataStore } from '@/stores/formdata';
import { expandMonths, fillItemRow, findMatchingRow, findEmptyRow, itemRowsIn } from '@/lib/talepRows';
import Form from '@/components/coalparts/Form.vue';

const UNIT_MAP = { 'ADT': 'ST', 'ADET': 'ST', 'ST': 'ST', 'KG': 'KG', 'KILOGRAM': 'KG', 'M': 'M', 'MT': 'M', 'METRE': 'M' };

export default {
    breadcrumbs: {
        list: [{ title: 'İhtiyaç Listesi', path: '/coalpanel/planlama/talepler' }],
        title: 'İhtiyaç Listesi Formu'
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
        };
    },
    computed: {
        isFront() { return this.$route.path.startsWith('/planlamapanel'); },
        canEdit() { return (this.authStore.permissions || []).includes('per-041-05-02'); },
        roFields() {
            return this.canEdit ? [] : ['title', 'desc', 'donem_bas', 'donem_bit', 'mal_grubu', 'ihale_grubu', 'prod_code', 'unit'];
        },
    },
    mounted() {
        const checkData = async () => this.id
            ? await this.plib.request({ url: '/api/v1/document/' + this.id, method: 'GET' }, null)
            : { success: false };
        checkData().then(rsp => {
            this.formDataStore.setData(rsp?.data?.formFormat);
            this.formDataStore.rawData = rsp?.data || null;
            this.loadForm = true;
            this.navigationStore.toggle(false);
        });
        this.navigationStore.toggle(true);
    },
    methods: {
        listRoute() {
            return this.$route.path.startsWith('/planlamapanel') ? 'PlanlamaRList' : 'PlanlamaRListAdmin';
        },
        goBackToList() {
            this.$router.push({ name: this.listRoute() }).catch(() => {});
        },
        itemArea() {
            return document.querySelector('.area-target[data-tag="op-doc-request-item-form"]');
        },
        itemRows() {
            return itemRowsIn(this.itemArea());
        },
        addRow() {
            const icon = document.querySelector('i[id*="request_items-subadd"]');
            if (icon) icon.click();
        },
        async waitForRows(n, tries = 20) {
            for (let i = 0; i < tries; i++) {
                if (this.itemRows().length >= n) return true;
                await new Promise(r => setTimeout(r, 60));
            }
            return this.itemRows().length >= n;
        },
        fillRow(row, item) {
            fillItemRow(row, item);
        },
        mapUnit(v) {
            const k = String(v || '').toLocaleUpperCase('tr').trim();
            return UNIT_MAP[k] || '';
        },
        requestHead() {
            const out = {};
            try {
                const ff = (this.formDataStore.rawData && this.formDataStore.rawData.formFormat && this.formDataStore.rawData.formFormat['op-doc-request-form']) || {};
                for (const rid in ff) {
                    const ents = ff[rid]?.entities || {};
                    for (const k in ents) {
                        if (k.includes('**')) continue;
                        if (out[k] === undefined) out[k] = ents[k];
                    }
                }
            } catch(e) {}
            // prefer live form values (unsaved edits)
            try {
                const area = document.querySelector('.area-target[data-tag="op-doc-request-form"]');
                ['donem_bas', 'donem_bit'].forEach(n => {
                    const inp = area && area.querySelector(`[name="${n}"]`);
                    if (inp && inp.value) out[n] = inp.value;
                });
            } catch(e) {}
            return out;
        },
        downloadExcelTemplate() {
            const aoa = [
                ['Mal Grubu', 'İhale Grubu', 'Malzeme Kodu', 'Malzeme kısa metni', 'Ölçü Birimi'],
                ['PREFABRİK BİNA', 'PREFABRİK BİNA', '122.5.5.053.IA', 'PREFABRİK BİNA-EK-1C,A=12M,B=2.6M', 'ADT'],
            ];
            const ws = XLSX.utils.aoa_to_sheet(aoa);
            ws['!cols'] = [{ wch: 20 }, { wch: 20 }, { wch: 20 }, { wch: 40 }, { wch: 14 }];
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Kalemler');
            XLSX.writeFile(wb, 'ihtiyac-kalem-sablon.xlsx');
        },
        exportItemsExcel() {
            const rows = this.itemRows();
            const val = (row, prefix) => {
                const inp = row.querySelector(`[name^="${prefix}**"]`);
                return inp ? String(inp.value || '').trim() : '';
            };
            const aoa = [['Mal Grubu', 'İhale Grubu', 'Malzeme Kodu', 'Malzeme İsmi', 'Ölçü Birimi']];
            const head = this.requestHead();
            const months = expandMonths(head.donem_bas || '', head.donem_bit || '');
            const mlabels = months.map(m => {
                const p = m.split('/');
                return p[1] + '-' + p[0];
            });
            mlabels.forEach(m => aoa[0].push(m));
            rows.forEach(r => {
                const kod = val(r, 'prod_code'), ad = val(r, 'title');
                if (!kod && !ad) return;
                const line = [val(r, 'mal_grubu'), val(r, 'ihale_grubu'), kod, ad, val(r, 'unit')];
                mlabels.forEach(() => line.push(''));
                aoa.push(line);
            });
            if (aoa.length < 2) { this.plib.toast(Swal, 'info', 'İndirilecek kalem yok'); return; }
            const ws = XLSX.utils.aoa_to_sheet(aoa);
            ws['!cols'] = [{ wch: 20 }, { wch: 20 }, { wch: 20 }, { wch: 40 }, { wch: 14 }].concat(mlabels.map(() => ({ wch: 12 })));
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Kalemler');
            XLSX.writeFile(wb, 'ihtiyac-kalemleri.xlsx');
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
                };
                if (ci.kod < 0) { this.plib.toast(Swal, 'error', 'Malzeme Kodu sütunu yok'); return; }
                const items = [];
                for (let i = hi + 1; i < rows.length; i++) {
                    const r = rows[i];
                    if (!Array.isArray(r)) continue;
                    const prod = String(r[ci.kod] || '').trim();
                    const title = String(ci.ad >= 0 ? (r[ci.ad] || '') : '').trim();
                    if (!prod && !title) continue;
                    items.push({
                        mal_grubu: ci.mal >= 0 ? String(r[ci.mal] || '').trim() : '',
                        ihale_grubu: ci.ihale >= 0 ? String(r[ci.ihale] || '').trim() : '',
                        prod_code: prod,
                        title: title,
                        unit: this.mapUnit(ci.birim >= 0 ? r[ci.birim] : ''),
                    });
                }
                if (!items.length) { this.plib.toast(Swal, 'info', 'Excelde kalem bulunamadı'); return; }
                let done = 0;
                for (const item of items) {
                    // 1) same material row 2) empty row 3) new row
                    let target = findMatchingRow(this.itemRows(), item);
                    if (!target) target = findEmptyRow(this.itemRows());
                    if (!target) {
                        const before = this.itemRows().length;
                        this.addRow();
                        await this.waitForRows(before + 1);
                        const all = this.itemRows();
                        target = all[all.length - 1];
                    }
                    if (target) { this.fillRow(target, item); done++; }
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
            formData.typeKey = 'op-doc-request';
            // sub items have no quantity — store 0, never shown
            try {
                for (const k in (formData.dynamicF || {})) {
                    if (k.startsWith('op-doc-request-item-form**')) {
                        formData.dynamicF[k].entities = formData.dynamicF[k].entities || {};
                        formData.dynamicF[k].entities.quantity = '0';
                    }
                }
            } catch(e) {}
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
        }
    }
};
</script>

<template>
    <div v-if="isFront" class="tedarik-detail" style="padding-bottom: 130px;">
        <div class="tedarik-detail-header">
            <div class="tedarik-detail-header-top">
                <div class="tedarik-detail-header-left">
                    <button @click="goBackToList" class="tedarik-back-link" title="İhtiyaç Listesi" aria-label="Geri">
                        <i class="ki-outline ki-arrow-left"></i>
                        <span>Tüm İhtiyaçlar</span>
                    </button>
                    <div class="tedarik-company-row">
                        <span class="tedarik-company-icon"><i class="ki-outline ki-document"></i></span>
                        <div class="tedarik-detail-title">{{ id ? (canEdit ? 'İhtiyaç Listesi Düzenle' : 'İhtiyaç Listesi Detayı') : 'Yeni İhtiyaç Listesi' }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="tedarik-step-card">
            <div class="tedarik-step-head"><span class="tedarik-step-num">1</span><span>Başlık, dönem ve kalemleri girin.</span></div>
            <div class="tedarik-step-body">
                <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
                    <button v-if="canEdit" type="button" class="tedarik-btn-light" @click="downloadExcelTemplate">Şablon</button>
                    <button type="button" class="tedarik-btn-light" @click="exportItemsExcel">Kalemleri İndir</button>
                    <button v-if="canEdit" type="button" class="tedarik-btn-light" :disabled="excelBusy || !loadForm" @click="triggerExcelUpload">{{ excelBusy ? 'Aktarılıyor...' : "Excel'den Yükle" }}</button>
                    <input ref="excelInput" type="file" accept=".xls,.xlsx" style="display:none" @change="onExcelSelected" />
                </div>
                <Form v-if="loadForm" formtypes="op-doc-request-form,op-doc-request-item-form" savebtntitle="Kaydet" :savecallback="submitForm" :readonlyFields="roFields" :showsave="canEdit" />
            </div>
        </div>
    </div>
    <div v-else class="planlama-rform" :class="{ 'is-readonly': !canEdit }">
        <div class="planlama-rform__head">
            <button type="button" class="planlama-rform__back" @click="$router.back()">← Geri</button>
            <h2>{{ id ? (canEdit ? 'İhtiyaç Listesi Düzenle' : 'İhtiyaç Listesi Detayı') : 'Yeni İhtiyaç Listesi' }}</h2>
            <span class="planlama-rform__spacer"></span>
            <button v-if="canEdit" type="button" class="planlama-rform__btn" @click="downloadExcelTemplate">Şablon</button>
            <button type="button" class="planlama-rform__btn" @click="exportItemsExcel">Kalemleri İndir</button>
            <button v-if="canEdit" type="button" class="planlama-rform__btn planlama-rform__btn--primary" :disabled="excelBusy || !loadForm" @click="triggerExcelUpload">
                {{ excelBusy ? 'Aktarılıyor...' : "Excel'den Yükle" }}
            </button>
            <input ref="excelInput" type="file" accept=".xls,.xlsx" style="display:none" @change="onExcelSelected" />
        </div>
        <Form v-if="loadForm" formtypes="op-doc-request-form,op-doc-request-item-form" savebtntitle="Kaydet" :savecallback="submitForm" :readonlyFields="roFields" :showsave="canEdit" />
    </div>
</template>

<style scoped>
.tedarik-detail-header { background: linear-gradient(135deg, #ffffff 0%, #fcfdff 100%) !important; border:1px solid #e2e8f0 !important; border-radius:16px !important; padding:18px 22px 16px !important; box-shadow:0 8px 24px rgba(15,23,42,0.04), 0 2px 8px rgba(15,23,42,0.03) !important; position:relative; overflow:hidden; margin-bottom:14px; }
.tedarik-detail-header::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background: linear-gradient(90deg, #FF5A1F 0%, #fb923c 50%, #FF5A1F 100%); }
.tedarik-detail-header-top { display:flex; justify-content:space-between; align-items:flex-end; gap:20px; flex-wrap:wrap; }
.tedarik-detail-header-left { display:flex; flex-direction:column; gap:10px; }
.tedarik-back-link { display:inline-flex; align-items:center; gap:6px; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:999px; padding:6px 14px; font-size:12.5px; font-weight:700; cursor:pointer; width:fit-content; }
.tedarik-back-link:hover { background:#ffedd5; }
.tedarik-company-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.tedarik-company-icon { width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#FF5A1F,#ff8a4a); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; }
.tedarik-detail-title { font-size:18px; font-weight:800; color:#0f172a; letter-spacing:-0.01em; }
.tedarik-step-card { background:#fff; border:1px solid #f1f5f9; border-radius:16px; padding:18px 20px; margin-bottom:14px; box-shadow:0 2px 8px rgba(0,0,0,0.03); }
.tedarik-step-card { overflow-x:clip; }
.tedarik-step-head { display:flex; align-items:center; gap:10px; font-size:14px; font-weight:700; color:#0f172a; margin-bottom:12px; }
.tedarik-step-num { width:26px; height:26px; border-radius:999px; background:#FF5A1F; color:#fff; font-size:13px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
.tedarik-step-body { font-size:13.5px; }
.tedarik-step-body :deep(.item-row) { margin-left:0 !important; margin-right:0 !important; }
.tedarik-btn-light { height:38px; padding:0 16px; border-radius:10px; background:#fff; border:1.5px solid #e2e8f0; color:#334155; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; }
.tedarik-btn-light:hover { background:#f8fafc; border-color:#cbd5e1; }
.tedarik-btn-light:disabled { opacity:.55; cursor:not-allowed; }
/* item section: İHTİYAÇ KALEMLERİ as header strip, kalem sub-cards flat below */
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
/* flatten shared Form's admin cards inside the panel step card */
.tedarik-step-body :deep(.area-target > .dform-row.card) { background:transparent !important; border:0 !important; box-shadow:none !important; margin:0 !important; padding:0 !important; }
.tedarik-step-body :deep(.area-target > .dform-row.card > .card-body) { padding:0 !important; }
.tedarik-step-body :deep(.area-target + .area-target) { margin-top:20px; padding-top:20px; border-top:1px dashed #ffe4cc; }
.tedarik-step-body :deep(.item-row) { margin:0 0 14px !important; }
.tedarik-step-body :deep(.item-row > .form-label) { margin-top:0 !important; }
.tedarik-step-body :deep(.form-control) { min-height:44px; border-radius:12px; font-size:13.5px; border:1.5px solid #e2e8f0; background:#fff; }
.tedarik-step-body :deep(.form-control:focus) { border-color:#FF5A1F; box-shadow:0 0 0 4px rgba(255,90,31,.12); outline:none; }
.tedarik-step-body :deep(textarea.form-control) { min-height:96px; }
.planlama-rform { background: #fff; border-radius: 12px; padding: 20px; }
.planlama-rform.is-readonly :deep(.fab-btns-wrapper),
.planlama-rform.is-readonly :deep(.fab-wrapper),
.planlama-rform.is-readonly :deep(i[id*="subadd"]),
.planlama-rform.is-readonly :deep(.rmv-btn-form) { display: none !important; }
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
</style>
