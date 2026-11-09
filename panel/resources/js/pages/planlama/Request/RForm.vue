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
        itemArea() {
            return document.querySelector('.area-target[data-tag="op-doc-request-item-form"]');
        },
        itemRows() {
            const area = this.itemArea();
            return area ? Array.from(area.querySelectorAll('.multiple-item-row')) : [];
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
            const set = (prefix, val) => {
                if (val === undefined || val === null || val === '') return;
                const inp = row.querySelector(`[name^="${prefix}**"]`);
                if (!inp) return;
                inp.value = String(val);
                inp.dispatchEvent(new Event('input', { bubbles: true }));
            };
            set('mal_grubu', item.mal_grubu);
            set('ihale_grubu', item.ihale_grubu);
            set('prod_code', item.prod_code);
            set('title', item.title);
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
            const months = this.expandMonths(head.donem_bas || '', head.donem_bit || '');
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
                    let target = this.itemRows().find(r => {
                        const p = r.querySelector('[name^="prod_code**"]');
                        return p && item.prod_code && p.value.trim() === item.prod_code;
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
    <div class="planlama-rform" :class="{ 'is-readonly': !canEdit }">
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
        <Form v-if="loadForm" formtypes="op-doc-request-form,op-doc-request-item-form" savebtntitle="Kaydet" :savecallback="submitForm" :readonlyFields="roFields" />
    </div>
</template>

<style scoped>
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
