<script>
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import { useAuthStore } from '@/stores/auth';
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
            isPlanlama() { return this.$route.path.startsWith('/planlamapanel'); },
        };
    },
    computed: {
        formRoute() { return this.$route.path.startsWith('/planlamapanel') ? 'PlanlamaFForm' : 'PlanlamaFFormAdmin'; },
        canMan() { return (this.authStore.permissions || []).includes('per-041-05-01'); },
        canStd() { return (this.authStore.permissions || []).includes('per-041-05-04'); },
    },
    mounted() {
        this.navigationStore.toggle(true);
        this.buildTable();
        setTimeout(() => this.navigationStore.toggle(false), 300);
    },
    methods: {
        searchTable() {
            this.table.setFilter([{ key: 'all', type: '=', value: document.getElementById('mainSearch').value.trim() }]);
        },
        resetSearch() {
            document.getElementById('mainSearch').value = '';
            this.table.setFilter([]);
        },
        goForm(id) {
            this.$router.push({ name: this.formRoute, params: id ? { id } : {} });
        },
        buildTable() {
            this.table = new PickleTable({
                container: '#div_table',
                headers: [
                    { title: 'Başlık', key: 'title', order: true, type: 'string' },
                    { title: 'Bağlı İhtiyaç', key: 'request_no', order: true, type: 'string' },
                    { title: 'Müdürlük', key: 'firma_adi', order: true, type: 'string' },
                    { title: 'Durum', key: 'status', order: false, type: 'string',
                      columnFormatter: (elm, rowData, col) => {
                          const parts = String(col || rowData.status || '').split('**');
                          const op = parts[0] || '';
                          const label = parts[1] || op || '-';
                          const map = {
                              'tr-request-man-awaiting': '#FF5A1F',
                              'tr-request-man-approved': '#0e7490',
                              'tr-request-man-rejected': '#dc2626',
                              'tr-request-std-approved': '#16a34a',
                          };
                          const span = document.createElement('span');
                          span.textContent = label;
                          span.style.cssText = 'display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:800;color:#fff;background:' + (map[op] || '#64748b');
                          return span;
                      } },
                    { title: 'Oluşturulma', key: 'created_at', order: true, type: 'string' },
                    { title: '', key: 'id', order: false, type: 'string',
                      columnFormatter: (elm, rowData, columnData) => {
                          const span = document.createElement('span');
                          span.classList.add('d-flex','align-items-center','justify-content-center','gap-1','planlama-acts');
                          const btn = document.createElement('button');
                          btn.classList.add('btn');
                          btn.textContent = 'Detay';
                          btn.style.cssText = 'background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8;';
                          btn.onclick = () => this.$router.push({ name: this.formRoute, params: { id: columnData } });
                          span.appendChild(btn);
                          const stOp = String(rowData.status || '').split('**')[0] || '';
                          const mkBtn = (label, color, opKey, getNote) => {
                              const b = document.createElement('button');
                              b.classList.add('btn');
                              b.textContent = label;
                              b.style.cssText = 'background:' + color + ';border-color:' + color + ';color:#fff;';
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
                                  b.disabled = true;
                                  b.innerHTML = '<span class="tdk-mini-spin"></span>';
                                  try {
                                  const fd = new FormData();
                                  fd.append('id', columnData);
                                  fd.append('op_key', opKey);
                                  if (note) fd.append('note', note);
                                  const rsp = await this.plib.request({ url: '/api/v1/trans/set-status', method: 'POST' }, null, fd);
                                  if (!rsp?.success) { this.plib.toast(Swal, 'error', rsp?.msg || 'Durum güncellenemedi'); return; }
                                  this.plib.toast(Swal, 'success', 'Durum güncellendi');
                                  const titles = { 'tr-request-man-approved': 'Talep Listesi Müdürlük Tarafından Onaylandı', 'tr-request-man-rejected': 'Talep Listesi Müdürlük Tarafından Reddedildi', 'tr-request-std-approved': 'Talep Listesi Standardizasyon Tarafından Onaylandı' };
                                  this.table.updateRow(columnData, { status: opKey + '**' + (titles[opKey] || opKey), id: columnData });
                                  } finally {
                                      b.disabled = false;
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
                          return span;
                      } },
                ],
                pageLimit: 10,
                height: '70vh',
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
    <div class="card rlist-card">
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
:deep(.tdk-mini-spin) { width: 14px; height: 14px; border-radius: 999px; border: 2px solid rgba(255,255,255,.45); border-top-color: #fff; animation: tdk-mini-spin .7s linear infinite; display: inline-block; vertical-align: -2px; }
@keyframes tdk-mini-spin { to { transform: rotate(360deg); } }
:deep(.planlama-acts) { flex-wrap: nowrap !important; }
:deep(.planlama-acts .btn) { height: 32px !important; padding: 0 12px !important; font-size: 12px !important; font-weight: 700; border-radius: 999px !important; white-space: nowrap; }
:deep(.planlama-acts .btn:hover) { filter: brightness(.95); }
</style>
