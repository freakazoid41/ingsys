<script>
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import { useAuthStore } from '@/stores/auth';
import { planlamaLabel, planlamaColor, PLANLAMA_STATUS_TITLE } from '@/lib/planlamaStatus';
import { useNavigationStore } from '@/stores/navigation';

export default {
    breadcrumbs: {
        list: [{ title: 'Malzeme Planlama', path: '/planlamapanel' }, { title: 'Talep Dökümanları', path: '#' }],
        title: 'Talep Dökümanları'
    },
    setup() { return { Plib, useAuthStore, useNavigationStore }; },
    data() {
        return {
            plib: new Plib(),
            authStore: useAuthStore(),
            navigationStore: useNavigationStore(),
            table: null,
            _buildTimeouts: [],
            isPlanlama() { return this.$route.path.startsWith('/planlamapanel'); },
        };
    },
    computed: {
        isFront() { return this.$route.path.startsWith('/planlamapanel'); },
        formRoute() { return this.$route.path.startsWith('/planlamapanel') ? 'PlanlamaFForm' : 'PlanlamaFFormAdmin'; },
        canMan() { return (this.authStore.permissions || []).includes('per-041-05-01'); },
        canStd() { return (this.authStore.permissions || []).includes('per-041-05-04'); },
    },
    mounted() {
        this.navigationStore.toggle(true);
        this.buildTable();
        setTimeout(() => this.navigationStore.toggle(false), 300);
        this._buildTimeouts.push(setTimeout(() => this.enforceFrontHeight(), 400));
    },
    beforeUnmount() {
        this._buildTimeouts.forEach(clearTimeout);
    },
    methods: {
        enforceFrontHeight() {
            if (!this.isFront) return;
            try {
                const el = document.querySelector('.tedarik-card .pickletable');
                if (!el) return;
                el.style.setProperty('height', '75vh', 'important');
                el.style.setProperty('min-height', 'calc(75vh - 280px)', 'important');
                const dt = el.querySelector('.divTable');
                if (dt) {
                    dt.style.setProperty('height', '90%', 'important');
                    dt.style.setProperty('overflow', 'auto', 'important');
                }
            } catch {}
        },
        searchTable() {
            const inp = this.$refs.frontSearch || document.getElementById('mainSearch');
            const v = (inp?.value || '').trim();
            this.table.setFilter(v ? [{ key: 'all', type: '=', value: v }] : []);
        },
        resetSearch() {
            const inp = this.$refs.frontSearch || document.getElementById('mainSearch');
            if (inp) inp.value = '';
            this.table.setFilter([]);
        },
        goForm(id) {
            this.$router.push({ name: this.formRoute, params: id ? { id } : {} });
        },
        buildTable() {
            const isFront = this.$route.path.startsWith('/planlamapanel');
            this.table = new PickleTable({
                container: '#div_table',
                headers: [
                    { title: 'Başlık', key: 'title', order: true, type: 'string', width: isFront ? '200px' : undefined,
                      columnFormatter: (elm, rowData, col) => {
                          const s = document.createElement('span');
                          s.textContent = col || rowData.title || '—';
                          s.title = s.textContent;
                          s.style.cssText = 'font-weight:700;color:#0f172a;font-size:' + (isFront ? '13px' : '14px') + ';display:inline-block;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle;cursor:pointer;';
                          s.onclick = () => this.$router.push({ name: this.formRoute, params: { id: rowData.id } });
                          return s;
                      } },
                    { title: 'Bağlı İhtiyaç', key: 'request_no', order: true, type: 'string', width: isFront ? '150px' : undefined,
                      columnFormatter: (elm, rowData, col) => {
                          const s = document.createElement('span'); s.textContent = col || rowData.request_no || '—'; s.style.color = '#334155'; return s;
                      } },
                    { title: 'Müdürlük', key: 'firma_adi', order: true, type: 'string', width: isFront ? '170px' : undefined,
                      columnFormatter: (elm, rowData, col) => {
                          const s = document.createElement('span'); s.textContent = col || rowData.firma_adi || '—'; s.style.color = '#334155'; s.style.display='inline-block'; s.style.maxWidth='160px'; s.style.overflow='hidden'; s.style.textOverflow='ellipsis'; s.style.whiteSpace='nowrap'; return s;
                      } },
                    { title: 'Durum', key: 'status', order: false, type: 'string', width: isFront ? '190px' : undefined,
                      columnFormatter: (elm, rowData, col) => {
                          const parts = String(col || rowData.status || '').split('**');
                          const op = parts[0] || '';
                          const label = parts[1] || planlamaLabel(op);
                          const span = document.createElement('span');
                          span.textContent = label;
                          span.title = label;
                          span.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;padding:' + (isFront ? '5px 10px' : '6px 12px') + ';border-radius:' + (isFront ? '6px' : '8px') + ';font-size:' + (isFront ? '11.5px' : '0.82rem') + ';font-weight:600;white-space:nowrap;border:1px solid transparent;gap:6px;background:' + planlamaColor(op) + ';color:#fff;border-color:' + planlamaColor(op);
                          return span;
                      } },
                    { title: 'Oluşturulma', key: 'created_at', order: true, type: 'string', width: isFront ? '150px' : undefined,
                      columnFormatter: (elm, rowData, col) => {
                          const s = document.createElement('span'); s.textContent = col || rowData.created_at || '—'; s.style.color = '#334155'; return s;
                      } },
                    { title: '', key: 'id', order: false, type: 'string', width: isFront ? '200px' : undefined,
                      columnFormatter: (elm, rowData, columnData) => {
                          const wrap = document.createElement('div');
                          wrap.style.display = 'flex';
                          wrap.style.justifyContent = isFront ? 'flex-end' : 'center';
                          wrap.style.alignItems = 'center';
                          wrap.style.gap = '6px';
                          wrap.classList.add('planlama-acts');
                          const setRowBusy = (on) => {
                              wrap.querySelectorAll('button').forEach(x => { x.disabled = on; x.style.opacity = on ? '0.6' : ''; });
                          };
                          const mk = (label, bg, color, border) => {
                              const b = document.createElement('button');
                              b.classList.add('btn');
                              b.textContent = label;
                              b.style.cssText = `background:${bg};border:1px solid ${border};color:${color};border-radius:8px;padding:6px 14px;font-size:0.78rem;font-weight:600;cursor:pointer;white-space:nowrap;`;
                              return b;
                          };
                          const btn = mk('Detay', '#0e8ea4', '#ffffff', '#0e8ea4');
                          btn.onclick = () => this.$router.push({ name: this.formRoute, params: { id: columnData } });
                          wrap.appendChild(btn);
                          const stOp = String(rowData.status || '').split('**')[0] || '';
                          const mkBtn = (label, color, opKey, getNote) => {
                              const b = mk(label, color, '#fff', color);
                              b.onclick = async () => {
                                  let note = '';
                                  if (getNote) {
                                      const c = await Swal.fire({
                                          icon: 'warning', title: 'Müdürlük Reddi',
                                          html: '<textarea id="rej-note" class="swal2-textarea" placeholder="Red nedeni yazın..."></textarea>',
                                          showCancelButton: true, confirmButtonText: 'Reddet', cancelButtonText: 'Vazgeç',
                                          confirmButtonColor: '#dc2626',
                                          preConfirm: () => document.getElementById('rej-note').value.trim(),
                                      });
                                      if (!c.isConfirmed) return;
                                      note = c.value || '-';
                                  } else {
                                      const c = await Swal.fire({
                                          icon: 'question', title: label,
                                          text: 'Onaylansın mı?',
                                          showCancelButton: true, confirmButtonText: 'Evet', cancelButtonText: 'Vazgeç',
                                          confirmButtonColor: color,
                                      });
                                      if (!c.isConfirmed) return;
                                  }
                                  const original = b.innerHTML;
                                  setRowBusy(true);
                                  b.innerHTML = '<span class="tdk-mini-spin"></span>';
                                  try {
                                  const fd = new FormData();
                                  fd.append('id', columnData);
                                  fd.append('op_key', opKey);
                                  if (note) fd.append('note', note);
                                  const rsp = await this.plib.request({ url: '/api/v1/trans/set-status', method: 'POST' }, null, fd);
                                  if (!rsp?.success) { this.plib.toast(Swal, 'error', rsp?.msg || 'Durum güncellenemedi'); return; }
                                  this.plib.toast(Swal, 'success', 'Durum güncellendi');
                                  const titles = PLANLAMA_STATUS_TITLE;
                                  this.table.updateRow(columnData, { status: opKey + '**' + (titles[opKey] || opKey), id: columnData });
                                  } finally {
                                      setRowBusy(false);
                                      b.innerHTML = original;
                                  }
                              };
                              span.appendChild(b);
                          };
                          if (stOp === 'tr-request-man-awaiting' && (this.authStore.permissions || []).includes('per-041-05-01')) {
                              mkBtn('Onayla', '#16a34a', 'tr-request-man-approved', false);
                              mkBtn('Reddet', '#dc2626', 'tr-request-man-rejected', true);
                          } else if (stOp === 'tr-request-man-approved' && (this.authStore.permissions || []).includes('per-041-05-04')) {
                              mkBtn('Std. Onay', '#16a34a', 'tr-request-std-approved', false);
                          }
                          return wrap;
                      } },
                ],
                pageLimit: 10,
                height: isFront ? '75vh' : '70vh',
                type: 'ajax',
                columnSearch: false,
                paginationType: 'number',
                ajax: { url: '/api/v1/table/documents', data: {} },
                initialFilter: [
                    { key: 'form-type', type: '=', value: 'op-doc-requestf-form' },
                    { key: 'type', type: '=', value: 'op-doc-requestf' },
                ],
                nextPageIcon: '<i class="ki-outline ki-arrow-right"></i>',
                prevPageIcon: '<i class="ki-outline ki-arrow-left"></i>',
                rowFormatter: (elm, data) => {
                    try {
                        JSON.parse(data.main_attr).forEach(el => { data[el['Key']] = el['Value']; });
                    } catch(e) {}
                    return data;
                },
            });
        },
    }
};
</script>

<template>
    <div :class="['order-list-card', { 'tedarik-card': isFront }]">
        <div v-if="!isFront" class="card rlist-card">
            <div class="card-header rlist-header">
                <div class="rlist-search-group">
                    <div class="rlist-search-wrap">
                        <i class="ki-duotone ki-magnifier fs-4 rlist-search-icon">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                        <input type="text" id="mainSearch" class="rlist-search-input" placeholder="Talep ara...">
                    </div>
                    <button type="button" class="rlist-btn rlist-btn-primary" @click="searchTable">
                        <i class="ki-outline ki-magnifier fs-5"></i> Ara
                    </button>
                    <button type="button" class="rlist-btn rlist-btn-ghost" @click="resetSearch">Sıfırla</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="div_table"></div>
            </div>
        </div>
        <template v-else>
            <div class="tedarik-list-top">
                <div class="tedarik-list-title"><span>Talep Dökümanları</span></div>
                <div class="tedarik-filters"></div>
            </div>
            <div class="tedarik-docs-searchrow" style="margin: 0 0 14px;">
                <div class="tedarik-docs-searchbox">
                    <i class="ki-outline ki-magnifier tedarik-docs-search-icon"></i>
                    <input type="text" ref="frontSearch" class="tedarik-docs-search-input" placeholder="Talep ara — başlık, müdürlük..." @keydown.enter="searchTable">
                </div>
                <button type="button" class="tedarik-btn-light tedarik-docs-btn" @click="searchTable">Ara</button>
                <button type="button" class="tedarik-btn-light tedarik-docs-btn tedarik-docs-btn--ghost" @click="resetSearch">Sıfırla</button>
            </div>
            <div class="order-list-body">
                <div id="div_table"></div>
            </div>
            <div class="tedarik-bottom-note">
                <i class="ki-outline ki-information-5 me-5"></i>
                <span>Onay / red işlemleri satırdaki butonlarla yapılır.</span>
            </div>
        </template>
    </div>
</template>

<style scoped>
.rlist-card { border: 1px solid #e2e8f0 !important; border-radius: 16px !important; overflow: hidden; background: #fff !important; }
.rlist-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 14px 18px !important; border-bottom: 1px solid #f1f5f9 !important; background: #fff !important; }
.rlist-search-group { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.rlist-search-wrap { position: relative; display: flex; align-items: center; }
.rlist-search-icon { position: absolute; left: 12px; color: #99a1b7; pointer-events: none; }
.rlist-search-input { height: 40px; border: 1px solid #e2e8f0; border-radius: 999px; padding: 0 14px 0 38px; font-size: 13px; color: #0f172a; background: #f8fafc; outline: none; width: 280px; }
.rlist-search-input:focus { border-color: #0e7490; background: #fff; }
.rlist-btn { display: inline-flex; align-items: center; gap: 6px; height: 40px; padding: 0 18px; border-radius: 999px; font-size: 13px; font-weight: 700; border: 1px solid transparent; cursor: pointer; white-space: nowrap; text-decoration: none; }
.rlist-btn-primary { background: #0e7490 !important; color: white !important; border-color: #0e7490; }
.rlist-btn-ghost { background: #f1f5f9; color: #334155; border-color: #e2e8f0; }
.rlist-btn-create { background: #0e7490 !important; color: white !important; border-color: #0e7490; }
#div_table { width: 100%; overflow-x: auto; }
.tedarik-card { border: none !important; border-radius: 0 !important; box-shadow: none !important; background: transparent !important; }
.tedarik-list-top { display: flex; align-items: center; justify-content: space-between; padding: 2px 4px 18px; gap: 12px; flex-wrap: wrap; border-bottom: none; }
.tedarik-list-title { display: flex; align-items: center; gap: 10px; font-size: 19px; font-weight: 700; color: #1e293b; letter-spacing: -0.02em; line-height: 1; }
.tedarik-docs-searchrow { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.tedarik-docs-searchbox { position: relative; display: flex; align-items: center; flex: 1; min-width: 220px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0 12px; height: 42px; }
.tedarik-docs-searchbox:focus-within { background: #fff; border-color: #cbd5e1; box-shadow: 0 0 0 4px rgba(255,90,31,.10); }
.tedarik-docs-search-icon { position: absolute; left: 12px; font-size: 18px; color: #94a3b8; }
.tedarik-docs-search-input { border: none; outline: none; background: transparent; height: 100%; width: 100%; padding-left: 30px; font-size: 13.5px; color: #0f172a; }
.tedarik-docs-search-input::placeholder { color: #94a3b8; }
.tedarik-btn-light { height: 42px; padding: 0 18px; border-radius: 10px; background: #fff; border: 1.5px solid #e2e8f0; color: #334155; font-size: 13px; font-weight: 700; cursor: pointer; }
.tedarik-btn-light:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
.tedarik-docs-btn--ghost { background: #f1f5f9; }
.tedarik-bottom-note { margin-top: 10px; display: flex; align-items: flex-start; gap: 6px; font-size: 11.5px; color: #8a8a8e; line-height: 1.6; }
.tedarik-bottom-note i { color: #0e9cb8; font-size: 13px; margin-top: 2px; flex-shrink: 0; }
.tedarik-card :deep(.pickletable) { height: auto !important; border: none !important; }
.tedarik-card :deep(.pickletable .divTable) { height: auto !important; overflow: visible !important; border-bottom: none !important; }
.tedarik-card :deep(.pickletable table) { border-collapse: separate !important; border-spacing: 0 7px !important; table-layout: auto !important; border: none !important; width: 100% !important; }
.tedarik-card :deep(.pickletable thead th) { background: transparent !important; color: #b0b0b5 !important; font-size: 13px !important; font-weight: 500 !important; border: none !important; border-bottom: none !important; box-shadow: none !important; padding: 0 14px 10px !important; text-transform: none !important; letter-spacing: 0 !important; white-space: nowrap; text-align: left !important; position: relative !important; top: auto !important; }
.tedarik-card :deep(.pickletable thead th:last-child) { text-align: right !important; }
.tedarik-card :deep(.pickletable tbody tr) { background: #fff !important; border-radius: 14px !important; box-shadow: 0 2px 8px rgba(0,0,0,0.06) !important; border: none !important; }
.tedarik-card :deep(.pickletable tbody tr:hover) { transform: translateY(-2px) !important; box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important; }
.tedarik-card :deep(.pickletable tbody tr:hover td) { background: transparent !important; }
.tedarik-card :deep(.pickletable tbody td) { background: transparent !important; padding: 14px 12px !important; font-size: 13px !important; color: #333 !important; border: none !important; vertical-align: middle !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; font-weight: 400; }
.tedarik-card :deep(.pickletable tbody td:first-child) { border-radius: 14px 0 0 14px !important; font-weight: 600 !important; color: #111827 !important; }
.tedarik-card :deep(.pickletable tbody td:last-child) { border-radius: 0 14px 14px 0 !important; }
.tedarik-card :deep(.pickletable .divPagination) { background: transparent !important; border-top: none !important; padding: 10px 0 0 !important; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; position: relative; }
.tedarik-card :deep(.pickletable .divPagination button), .tedarik-card :deep(.pickletable .divPagination .page-link) { border: 1px solid #e5e7eb !important; background: #fff !important; color: #8a8a8e !important; border-radius: 6px !important; padding: 5px 10px !important; font-size: 12px !important; font-weight: 500 !important; min-width: 32px; height: 30px; }
.tedarik-card :deep(.pickletable .divPagination .active button), .tedarik-card :deep(.pickletable .divPagination .active .page-link) { background: #fff !important; color: #FF5A1F !important; border-color: #FF5A1F !important; font-weight: 700 !important; }
:deep(.tdk-mini-spin) { width: 14px; height: 14px; border-radius: 999px; border: 2px solid rgba(255,255,255,.45); border-top-color: #fff; animation: tdk-mini-spin .7s linear infinite; display: inline-block; vertical-align: -2px; }
@keyframes tdk-mini-spin { to { transform: rotate(360deg); } }
:deep(.planlama-acts) { flex-wrap: nowrap !important; }
:deep(.planlama-acts .btn) { height: 32px !important; padding: 0 12px !important; font-size: 12px !important; font-weight: 700; border-radius: 999px !important; white-space: nowrap; }
:deep(.planlama-acts .btn:hover) { filter: brightness(.95); }
</style>
