<template>
  <div class="table-section">
    <div class="table-header">
      <h5 class="section-title mb-0">Verdiğim Teklifler</h5>
    </div>
    <div id="offer-table" class="p-3"></div>
    <router-link :to="{ name: 'OList' }" class="notifications-footer-btn">
      Tüm tekliflerimi görüntüle <i class="fa-solid fa-angle-right"></i>
    </router-link>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
import { offerStatus, WITH_CANCELLED_FILTER } from '@/lib/offerStatus';
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';

export default {
  name: 'ClientOfferTable',
  data() {
    return {
      authStore: useAuthStore(),
      sysCode: useNavigationStore().sys_code,
    };
  },
  mounted() {
    this.buildOfferTable();
  },
  methods: {
    buildOfferTable() {
      const headers = [
        {
          title: 'Cari',
          key: 'clititle',
          order: true,
          type: 'string',
        },
        {
          title: 'Santral',
          key: 'target_type',
          order: true,
          type: 'string',
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
          type: 'string',
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
          columnFormatter: (elm, rowData, columnData) => {
            const state = offerStatus(rowData);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.classList.add('status-pill', 'status-pill--' + state.variant);
            btn.textContent = state.label;
            return btn;
          }
        }
      ];

      this.table = new PickleTable({
        container: '#offer-table',
        headers: headers,
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
          //cancelled offers stay listed for the supplier, shown as "İptal Edildi"
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
.table-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.table-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e9ecef;
}

.table-responsive {
  overflow-x: auto;
}

.offers-table {
  width: 100%;
  border-collapse: collapse;
  margin: 0;
}

.offers-table thead {
  background: linear-gradient(135deg, #1a3a52 0%, #2d5a7b 100%);
}

.offers-table th {
  padding: 1.25rem;
  color: white;
  font-weight: 600;
  text-align: left;
  font-size: 0.9rem;
}

.offers-table tbody tr {
  border-bottom: 1px solid #e9ecef;
  transition: background 0.2s ease;
}

.offers-table tbody tr:hover {
  background: #f8f9fa;
}

.offers-table td {
  padding: 1rem 1.25rem;
  color: #212529;
  font-size: 0.9rem;
}

.offers-table td.fw-medium {
  font-weight: 600;
  color: #0d6efd;
}

.action-btn {
  background: none;
  border: none;
  color: #0d6efd;
  cursor: pointer;
  padding: 0.5rem;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.action-btn:hover {
  color: #0d6efd;
  transform: scale(1.1);
  opacity: 0.8;
}

.notifications-footer-btn {
  width: 95%;
  margin: 0 auto 1rem auto;
  border: 1px solid #edf1f7;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  transition: 0.3s;
  background: #cccccc2d;
  font-size: 1rem;
  height: 45px;
  border-radius: 10px;
}

.notifications-footer-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(37, 99, 255, 0.08);
}

.section-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: #212529;
  margin-bottom: 2rem;
  letter-spacing: -0.5px;
}
</style>
