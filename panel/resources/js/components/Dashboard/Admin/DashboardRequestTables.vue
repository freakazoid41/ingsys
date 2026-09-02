<template>
  <div class="row request-tables">
    <!-- Son Talepler -->
    <div class="col-12">
      <div class="table-card">
        <div class="card-header">
          <h5 class="card-title">Son Talepler</h5>
          <router-link :to="{ name: 'RequestList' }" class="card-link">Tümünü Gör →</router-link>
        </div>
        <div id="request-table"></div>
      </div>
    </div>

    <div v-if="sysCode === 'GDZ'" class="col-12 mt-5">
      <div class="table-card">
        <div class="card-header">
          <h5 class="card-title">Son Talepler (Rödevans)</h5>
          <router-link :to="{ name: 'RequestList' }" class="card-link">Tümünü Gör →</router-link>
        </div>
        <div id="request-rodevans-table"></div>
      </div>
    </div>

    <div class="col-12 mt-5">
      <div class="table-card">
        <div class="card-header">
          <h5 class="card-title">Son Teklifler</h5>
          <router-link :to="{ name: 'OList' }" class="card-link">Tümünü Gör →</router-link>
        </div>
        <div id="offer-table"></div>
      </div>
    </div>
  </div>
</template>

<script>
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';
import { offerStatus, WITH_CANCELLED_FILTER } from '@/lib/offerStatus';

export default {
  name: 'DashboardRequestTables',
  props: {
    sysCode: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      requestTable: null,
      requestRodevansTable: null,
      offerTable: null
    };
  },
  mounted() {
    this.buildRequestTable();
    this.buildOfferTable();
  },
  methods: {
    buildRequestTable() {
      const headers = [
        {
          title: 'Talep Kodu',
          key: 'req_no',
          order: true,
          width: '100px',
          type: 'string'
        },
        {
          title: 'Talep Başlık',
          key: 'title',
          order: true,
          type: 'string'
        },
        {
          title: 'Santral',
          key: 'target_type',
          order: true,
          type: 'string'
        },
        {
          title: 'Sipariş Kapsamı',
          key: 'order_radius',
          order: true,
          width: '150px',
          type: 'string'
        },
        {
          title: 'İhale Başlangıç / Bitiş',
          key: 'contract_start_date',
          order: true,
          width: '200px',
          type: 'string',
          columnFormatter: (elm, rowData) => {
            return rowData.contract_start_date && rowData.contract_end_date
              ? `<div>${rowData.contract_start_date} - ${rowData.contract_end_date}</div>`
              : '-';
          }
        },
        {
          title: 'Sevkiyat Başlangıç / Bitiş',
          key: 'transfer_start_date',
          order: true,
          width: '200px',
          type: 'string',
          columnFormatter: (elm, rowData) => {
            return rowData.transfer_start_date && rowData.transfer_end_date
              ? `<div>${rowData.transfer_start_date} - ${rowData.transfer_end_date}</div>`
              : '-';
          }
        },
        {
          title: 'Güncel Durum',
          key: 'status',
          order: true,
          width: '200px',
          type: 'string',
          columnFormatter: (elm, rowData) => {
            const btn = document.createElement('button');
            btn.classList.add('btn', 'd-flex', 'align-items-center');

            const key = rowData.status?.split('**');
            switch (key?.[0]) {
              case 'doc_trans_request_end':
                btn.classList.add('status-pill', 'status-pill--success');
                break;
              case 'doc_trans_request_start':
                btn.classList.add('status-pill', 'status-pill--warning');
                break;
              case 'doc_trans_request_cancelled':
                btn.classList.add('status-pill', 'status-pill--danger');
                break;
              case 'doc_trans_created':
              default:
                if (key?.[1]) key[1] = 'Taslak';
                btn.classList.add('status-pill', 'status-pill--secondary');
                break;
            }

            btn.innerHTML = (key?.[1] ?? 'Bekleniyor..');
            btn.type = 'button';
            return btn;
          }
        }
      ];

      this.requestTable = new PickleTable({
        container: '#request-table',
        headers,
        pageLimit: 10,
        height: '50vh',
        type: 'ajax',
        columnSearch: false,
        paginationType: 'number',
        ajax: {
          url: '/api/v1/table/documents',
          data: {}
        },
        initialFilter: [
          {
            key: 'form-type',
            type: '=',
            value: 'op-doc-request-form'
          },
          {
            key: 'type',
            type: '=',
            value: 'op-doc-request'
          },
          {
            key: 'is-rodevans',
            type: '=',
            value: 'false'
          }
        ],
        nextPageIcon: '<i class="ki-outline ki-arrow-right"></i>',
        prevPageIcon: '<i class="ki-outline ki-arrow-left"></i>',
        rowFormatter: (elm, data) => {
          JSON.parse(data.main_attr).forEach(element => {
            data[element['Key']] = element['Value'];
          });
          return data;
        }
      });

      if (this.sysCode === 'GDZ') {
        this.requestRodevansTable = new PickleTable({
          container: '#request-rodevans-table',
          headers,
          pageLimit: 10,
          height: '50vh',
          type: 'ajax',
          columnSearch: false,
          paginationType: 'number',
          ajax: {
            url: '/api/v1/table/documents',
            data: {}
          },
          initialFilter: [
            {
              key: 'form-type',
              type: '=',
              value: 'op-doc-request-form'
            },
            {
              key: 'type',
              type: '=',
              value: 'op-doc-request'
            },
            {
              key: 'is-rodevans',
              type: '=',
              value: 'true'
            }
          ],
          nextPageIcon: '<i class="ki-outline ki-arrow-right"></i>',
          prevPageIcon: '<i class="ki-outline ki-arrow-left"></i>',
          rowFormatter: (elm, data) => {
            JSON.parse(data.main_attr).forEach(element => {
              data[element['Key']] = element['Value'];
            });
            return data;
          }
        });
      }
    },
    buildOfferTable() {
      const headers = [
        {
          title: 'Cari',
          key: 'clititle',
          order: true,
          type: 'string'
        },
        {
          title: 'Santral',
          key: 'target_type',
          order: true,
          type: 'string'
        },
        {
          title: 'Teklif tipi',
          key: 'offer_type',
          order: true,
          type: 'string',
          columnFormatter: (elm, rowData, columnData) => {
            return columnData.split('**')[1];
          }
        },
        {
          title: 'Belge Tarihi',
          key: 'date',
          order: true,
          type: 'string'
        },
        {
          title: 'Talep',
          key: 'addional',
          order: true,
          type: 'string',
          columnFormatter: (elm, rowData, columnData) => {
            const data = JSON.parse(columnData ?? '{}');
            for (const key in data) {
              if (data[key]?.Key === 'title') {
                const spn = document.createElement('span');
                spn.innerHTML = data[key]?.Value ?? '-';
                const viewBtn = document.createElement('button');
                viewBtn.classList.add('btn', 'ms-2', 'btn-secondary', 'action-icon-btn', 'me-1');
                viewBtn.title = 'Detay';
                viewBtn.innerHTML = '<i class="ki-outline ki-eye fs-2"></i>';
                viewBtn.onclick = () => {
                  this.$router.push({ name: 'RequestForm', params: { id: rowData.request_id } });
                };
                spn.appendChild(viewBtn);
                return spn;
              }
            }
            return '-';
          }
        },
        {
          title: 'Güncel Durum',
          key: 'status',
          order: true,
          type: 'string',
          columnFormatter: (elm, rowData) => {
            const state = offerStatus(rowData);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.classList.add('status-pill', 'status-pill--' + state.variant);
            btn.textContent = state.label;
            return btn;
          }
        }
      ];

      this.offerTable = new PickleTable({
        container: '#offer-table',
        headers,
        pageLimit: 10,
        height: '50vh',
        type: 'ajax',
        columnSearch: false,
        paginationType: 'number',
        ajax: {
          url: '/api/v1/table/documents',
          data: {}
        },
        initialFilter: [
          {
            key: 'form-type',
            type: '=',
            value: 'op-doc-offer-form'
          },
          {
            key: 'type',
            type: '=',
            value: 'op-doc-offer'
          },
          //cancelled offers stay on the admin board, shown as "İptal Edildi"
          WITH_CANCELLED_FILTER
        ],
        nextPageIcon: '<i class="ki-outline ki-arrow-right"></i>',
        prevPageIcon: '<i class="ki-outline ki-arrow-left"></i>',
        rowFormatter: (elm, data) => {
          JSON.parse(data.main_attr).forEach(element => {
            data[element['Key']] = element['Value'];
          });
          return data;
        }
      });
    }
  }
};
</script>

<style scoped>
.table-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  height: 100%;
  border: 1px solid var(--border-color);
}

.card-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--dark-text);
  margin: 0 0 1.25rem 0;
  font-family: 'Inter', sans-serif;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
}

.card-header .card-title {
  margin: 0;
}

.card-link {
  text-decoration: none;
  color: var(--primary-color);
  font-size: 0.85rem;
  font-weight: 600;
  transition: all 0.3s ease;
  white-space: nowrap;
}

.card-link:hover {
  color: #0f3a6b;
  transform: translateX(4px);
}

:deep(.status-pill) {
  display: inline-flex;
  align-items: center;
  height: 26px;
  padding: 0 12px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 700;
  border: 1px solid transparent;
  cursor: pointer;
  white-space: nowrap;
}
:deep(.status-pill--success) { background: rgba(23, 198, 83, 0.1); color: var(--success-color); border-color: rgba(23, 198, 83, 0.25); }
:deep(.status-pill--danger)  { background: rgba(248, 40, 90, 0.1); color: var(--danger-color); border-color: rgba(248, 40, 90, 0.25); }
:deep(.status-pill--warning) { background: rgba(246, 192, 0, 0.1); color: var(--warning-color); border-color: rgba(246, 192, 0, 0.25); }
:deep(.status-pill--secondary) { background: var(--light-bg); color: var(--text-secondary); border-color: var(--border-color); }

:deep(.pickletable th), :deep(.pickletable td) {
  white-space: nowrap;
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
}
:deep(.pickletable thead) { --bs-emphasis-color: rgba(255,255,255,.6); }
:deep(.pickletable thead tr) { background: #154b91 !important; }
:deep(.pickletable thead th) {
  background: #154b91 !important;
  color: rgba(255,255,255,.85) !important;
  font-size: .82rem !important;
  font-weight: 600 !important;
  letter-spacing: .04em;
  text-transform: uppercase;
  padding: 13px 16px !important;
  border: none !important;
  border-right: 1px solid rgba(255,255,255,.1) !important;
  white-space: nowrap;
}
:deep(.pickletable thead th:last-child) { border-right: none !important; }
:deep(.pickletable thead th svg),
:deep(.pickletable thead th i) { color: rgba(255,255,255,.6) !important; background: transparent !important; }
:deep(.pickletable thead th input) {
  background: rgba(255,255,255,.1) !important;
  border: 1px solid rgba(255,255,255,.2) !important;
  border-radius: 5px !important;
  color: #fff !important;
  font-size: .78rem !important;
  padding: 4px 8px !important;
  margin-top: 6px !important;
  width: 100% !important;
  outline: none !important;
}
:deep(.pickletable thead th input::placeholder) { color: rgba(255,255,255,.4) !important; }
:deep(.pickletable thead th input:focus) { background: rgba(255,255,255,.18) !important; border-color: rgba(255,255,255,.4) !important; }
:deep(.pickletable tbody tr) { border-bottom: 1px solid #eef0f4 !important; background: #fff !important; transition: background .12s; }
:deep(.pickletable tbody tr:hover) { background: #f7f9fd !important; }
:deep(.pickletable tbody td) {
  padding: 12px 16px !important;
  font-size: .9rem !important;
  color: #2d3748 !important;
  background: transparent !important;
  border: none !important;
  border-right: 1px solid #f0f2f7 !important;
  vertical-align: middle !important;
}
:deep(.pickletable tbody td:last-child) { border-right: none !important; }
</style>
