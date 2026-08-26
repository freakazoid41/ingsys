<script>
import { useNavigationStore } from '@/stores/navigation';
import { useAuthStore } from '@/stores/auth';
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';
import Plib from '@/lib/pickle';
import { isOfferCancelled, WITH_CANCELLED_FILTER } from '@/lib/offerStatus';
import Swal from 'sweetalert2';
export default {
  setup() {
    return {
      useNavigationStore,
      useAuthStore,
      Plib
    }
  },
  data() {
    return {
      plib : new Plib(),
      authStore: useAuthStore(),
      navigationStore: useNavigationStore(),
      selectedTab: 'Tümü',
      menuItems: [
        {
          path  : '/coalpanel/users',
          title: 'Sistem Kullanıcıları',
          svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`
        },
        {
          path : '/coalpanel/orders',
          title: 'Siparişler',
          svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m14.5 12.5-8 8a2.119 2.119 0 0 1-3-3l8-8"/><path d="m16 16 6-6"/><path d="m8 8 6-6"/><path d="m9 7 8 8"/><path d="m21 11-8-8"/></svg>`
        },
        {
          path : '/coalpanel/client',
          title: 'Firmalar',
          svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M7 10h.01"/><path d="M11 10h6"/><path d="M7 14h.01"/><path d="M11 14h6"/></svg>`
        },
        {
          path : '/coalpanel/transfers', 
          title: 'Transferler',
          svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect x="2" y="8" width="20" height="12" rx="2"/></svg>`
        },
        {
          path : '/coalpanel/documents',
          title: 'Dökümanlar',
          svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`
        }
      ],
      selectedMenu: '',
      offerTabs: ['Tümü', 'Firma Seç'],
      
      statusCards: [
        {
          title: 'Eklenen ihale',
          state: 'Pasif',
          note: 'Tamamlanamadı',
          subNote: '',
          date: '13.07.2024',
          stateColor: '#f87171',
          iconBg: '#fef2f2',
          svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>`
        },
        {
          title: 'Yeni eklenen ihale',
          state: 'Aktif',
          note: 'Devam ediyor...',
          subNote: 'İki gün önce eklendi',
          date: '13.07.2024',
          stateColor: '#10b981',
          iconBg: '#f0fdf4',
          svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`
        },
        {
          title: 'Yeni eklenen ihale',
          state: 'Aktif',
          note: 'Devam ediyor...',
          subNote: 'İki gün önce eklendi',
          date: '13.07.2024',
          stateColor: '#10b981',
          iconBg: '#f0fdf4',
          svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`
        }
      ],
      activityItems: [
        {
          title: 'Şifre yenileme talebi',
          status: 'Gönderim başarılı',
          time: '19.07.2024 12:04',
          iconBg: '#e4e9f2',
          iconColor: '#154b90',
          svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>`
        },
        {
          title: 'Kullanıcı hatalı işlem yaptı',
          status: '———',
          time: '13.07.2024 15:33',
          iconBg: '#fef2f2',
          iconColor: '#ef4444',
          svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`
        },
        {
          title: 'Dosya yüklemesi yapıldı',
          status: 'Tedarikçi yüklendi',
          time: '09.06.2024 10:00',
          iconBg: '#eff6ff',
          iconColor: '#3b82f6',
          svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>`
        },
        {
          title: 'Giriş yaptı',
          status: 'Başarılı',
          time: '09.06.2024 10:00',
          iconBg: '#f0fdf4',
          iconColor: '#22c55e',
          svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`
        }
      ],
      summaryCards: [
        { value: 25, label: 'Bildirimler', detail: 'Haz 2023' },
        { value: 12, label: 'Yeni Duyurular', detail: 'Tem 2023' },
        { value: 12, label: 'Bekleyen Görevler', detail: 'Tem 2023' }
      ]
    }
  },
  mounted() {
    
    this.buildOfferTable();
    
    
  },
  beforeUnmount() {
    
  },
  methods: {
    onOfferTabClick(tab) {
      this.selectedTab = tab
      switch (tab) {
        case 'Tümü':
          this.offerTable.setFilter([]);
          break
        case 'Firma Seç':
          this.buildClientFilter();
          break
        default:
          console.log('Offer tab clicked:', tab)
      }
    },
    onMenuClick(item) {
      this.selectedMenu = item.title
      if (item.path && this.$router) {
        this.$router.push(item.path).catch(() => {})
      } else {
        console.log('Menu clicked:', item)
      }
    },
    
   
    buildClientFilter(){
      Swal.fire({
        showConfirmButton : false,
        showCloseButton : true,
        html : '<style>.swal2-popup{width:800px !important;}</style><div id="cli-table"></div>',
        willOpen : () => {
          //set headers
          const headers = [
              {
                  title : 'Firma Ünvan',
                  key   : 'title',
                  order : true,
                  type  : 'string', // if column is string then make type string
              },{
                  title : 'Firma Kodu',
                  key   : 'clicode',
                  order : true,
                  type  : 'string', // if column is string then make type string
              }
          ];
                
          //initiate table
          this.cliTable = new PickleTable({
              container : '#cli-table', //table target div
              headers   : headers,
              pageLimit : 10, // -1 for closing pagination
              height    : '50vh',
              type      : 'ajax',
              columnSearch : true, // true - false for opening and closig
              paginationType : 'number',// scroll - number (number for default)
              ajax:{
                  url:'/api/v1/table/documents',
                  data:{
                      //order:{},
                  }
              },
              initialFilter : [
                  {
                      key   : 'form-type',
                      type  : '=',
                      value : 'op-doc-client-form'
                  },{
                      key   : 'type',
                      type  : '=',
                      value : 'op-doc-client'
                  }
              ],
              nextPageIcon : '<i class="ki-outline ki-arrow-right "></i>',
              prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
              rowClick : (el,data) => {
                this.offerTable.setFilter([
                  {
                    key : 'attr',
                    type : 'cliid',
                    value : data.id
                  }
                ]);
                Swal.close();
              },
              rowFormatter:(elm,data)=>{
                  //console.log(elm,data);
                  //modify row element
                  //elm.style.backgroundColor = 'yellow';
                  //modify data
                  JSON.parse(data.main_attr).forEach(element => {
                      data[element['Key']] = element['Value'];
                      //if(data['cont_name'] == undefined) data['cont_name'] = []
                      //if(element['Key'].includes('cont_name')) data['cont_name'].push(element['Value']);
                  });
                  //data['cont_name'] = (data['cont_name'] ?? []).join(' , ');
                  //data.status = JSON.parse(data.status).OpTitle;
                  return data;
              },
          });
        }
      });
    },
    buildOfferTable(){
                
      //set headers
      const headers = [
          {
              title : 'Cari',
              key   : 'clititle',
              order : true,
              type  : 'string', // if column is string then make type string
          },{
              title : 'Santral',
              key   : 'target_type',
              order : true,
              type  : 'string', // if column is string then make type string
          },{
              title : 'Teklif tipi',
              key   : 'offer_type',
              order : true,
              type  : 'string', // if column is string then make type string
              columnFormatter : (elm,rowData,columnData) => {
                  return columnData.split('**')[1];
              }
          },{
              title : 'Belge Tarihi',
              key   : 'date',
              order : true,
              type  : 'string', // if column is string then make type string
          },{
              title : 'Güncel Durum',
              key   : 'status',
              order : true,
              width : '250px',
              type  : 'string', // if column is string then make type string
              columnFormatter : (elm,rowData,columnData) => {
                  const key = rowData.status?.split('**');
                  const  btn    = document.createElement('button');
                  btn.classList.add('btn','d-flex','align-items-center');

                  //cancellation overrides whatever the last transaction was
                  if(isOfferCancelled(rowData)){
                      btn.classList.add('btn-danger');
                      btn.type = 'button';
                      btn.innerHTML = '<i class="ki-outline ki-cross-circle fs-2 me-3"></i> İptal Edildi';
                      return btn;
                  }

                  let icon  = '<i class="ph ph-timer fs-2 me-3"></i>';
                  switch(key?.[0]){
                      case 'doc_trans_offer_draft':
                      default:
                      case 'doc_trans_offer_sended':
                          if(key?.[1]) key[1] = 'Teklif Gönderildi';
                          icon  = '<i class="ki-outline ki-timer fs-2 me-3"></i>';
                          btn.classList.add('btn-default');
                          break;
                      case 'doc_trans_offer_approved':
                          icon  = '<i class="ki-outline ki-check fs-2 me-3"></i>';
                          btn.classList.add('btn-success');
                          break;
                      case 'doc_trans_offer_revision':
                      case 'doc_trans_offer_revised':
                      case 'doc_trans_offer_review':
                          icon  = '<i class="ki-outline ki-timer fs-2 me-3"></i>';
                          btn.classList.add('btn-warning');
                          break;
                      case 'doc_trans_offer_rejected':
                          icon  = '<i class="ki-outline ki-cross-circle fs-2 me-3"></i>';
                          btn.classList.add('btn-danger');
                          break;
                  }
                  btn.innerHTML = icon+' '+(key?.[1] ?? 'Teklif Gönderildi') ;
                  btn.type      = 'button';
                  //here we are looking request form permissions
                  /*btn.onclick   = this.useAuthStore().permissions?.includes('per-05-02') ? (e) => {
                      Swal.fire({
                          showConfirmButton : false,
                          showCloseButton : true,
                          html : `<small class="mb-5 mt-5">Listeden İstediğiniz Durumu Seçip Güncelleyebilirsiniz</small>
                                  <div class="row m-5 justify-content-center">
                                      <button class="btn btn-warning mb-5 doc-status" data-key="doc_trans_offer_review"    type="button">İnceleniyor</button>
                                      <button class="btn btn-info mb-5 doc-status" data-key="doc_trans_offer_revision"  type="button">Revizyon Talebi</button>
                                       <button class="btn btn-danger mb-5 doc-status"  data-key="doc_trans_offer_rejected"  type="button">Reddedildi</button>
                                       <button class="btn btn-success mb-5 doc-status"  data-key="doc_trans_offer_approved"  type="button">Kabul Edildi</button>
                                  </div>`,
                          willOpen : async () => {
                              Swal.showValidationMessage(key?.[2]);
                              document.querySelectorAll('.doc-status').forEach(btn => {
                                  btn.addEventListener('click', e => {
                                      Swal.fire({
                                          confirmButtonText : 'Kaydet..',
                                          showCloseButton : true,
                                          html : `<small class="mb-5">Durum Notu Giriniz (Boş Olabilir)</small>
                                                  <div class="row m-5 justify-content-center">
                                                      <div class="col-12">
                                                          <textarea class="form-control" id="exampleFormControlTextarea1" rows="3" placeholder="..."></textarea>
                                                      </div>
                                                  </div>`,
                                          allowOutsideClick: () => !Swal.isLoading(),
                                          preConfirm : async () => {
                                              try {
                                                  const note     = document.getElementById('exampleFormControlTextarea1').value.trim();
                                                  const envelope = new FormData();
                                                  envelope.append('id',rowData.id);
                                                  envelope.append('op_key',e.target.dataset.key);
                                                  envelope.append('note',note);
                                                  const rsp = await this.plib.request({
                                                      url      : '/api/v1/trans/set-status',
                                                      method   : 'POST',
                                                  },null,envelope);
                                                  if(rsp.success){
                                                      this.table.updateRow(rowData.id,{status : e.target.dataset.key+'**'+rsp.data+'**'+note});
                                                      this.plib.toast(this.Swal,'success','İşlem Tamamlandı');
                                                  }else{
                                                      Swal.showValidationMessage(rsp.msg);
                                                  }
                                                  return rsp.success;
                                              } catch (error) {
                                                  console.log(error)
                                                  Swal.showValidationMessage(`
                                                      Request failed: ${error}
                                                  `);
                                              }
                                          }
                                      });
                                  });
                              });
                          }
                      });
                  } : () => {};*/
                  return btn;
              }
          }
      ];
       

      // build initialFilter based on mode
      let initialFilter = [
        {
          key   : 'form-type',
          type  : '=',
          value : 'op-doc-offer-form'
        },{
          key   : 'type',
          type  : '=',
          value : 'op-doc-offer'
        },
        //cancelled offers stay on the board, shown as "İptal Edildi"
        WITH_CANCELLED_FILTER
      ]

      this.offerTable = new PickleTable({
          container : '#offer-table', //table target div
          headers   : headers,
          pageLimit : 10, // -1 for closing pagination
          height    : '50vh',
          type      : 'ajax',
          columnSearch : false, // true - false for opening and closig
          paginationType : 'number',// scroll - number (number for default)
          ajax:{
              url:'/api/v1/table/documents',
              data:{
                  //order:{},
              }
          },
          initialFilter : initialFilter,
          nextPageIcon : '<i class="ki-outline ki-arrow-right "></i>',
          prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
          rowClick : (elm,data) => {
            this.$router.push('/coalpanel/offer/form/'+data.id);
          },
          rowFormatter:(elm,data)=>{
              //console.log(elm,data);
              //modify row element
              //elm.style.backgroundColor = 'yellow';
              //modify data
              JSON.parse(data.main_attr).forEach(element => {
                  data[element['Key']] = element['Value'];
                  //if(data['cont_name'] == undefined) data['cont_name'] = []
                  //if(element['Key'].includes('cont_name')) data['cont_name'].push(element['Value']);
              });
              //data['cont_name'] = (data['cont_name'] ?? []).join(' , ');
              //data.status = JSON.parse(data.status).OpTitle;
              return data;
          },
      });
    },
  }
}
</script>
<template>
    <div class="dashboard-hero">
      <!-- Flowing wave decoration -->
      <div class="hero-deco" aria-hidden="true">
        <img src="banner-bg-new.webp" width="100%">
      </div>
      <div class="hero-top">
        <div>
          <p class="hero-subtitle">Tedarik Yönetim Sistemine</p>
          <h1 class="hero-title">Hoş Geldiniz.</h1>
        </div>
        
      </div>
      <div class="hero-menu">
        <div
          v-for="item in menuItems"
          :key="item.title"
          class="hero-menu-card"
          role="button"
          tabindex="0"
          @click="onMenuClick(item)"
          @keyup.enter="onMenuClick(item)"
        >
          <div class="hero-menu-icon" v-html="item.svg"></div>
          <span class="hero-menu-name">{{ item.title }}</span>
        </div>
      </div>
    </div>

    <div class="dashboard-main">
      <div class="card rlist-card">
        <div class="card-header rlist-header">
          <div class="rlist-search-group">
            <div class="tabs-pill">
              <button
                v-for="tab in offerTabs"
                :key="tab"
                @click="onOfferTabClick(tab)"
                :class="['pill-button', { 'pill-button--active': selectedTab === tab }]"
                type="button"
              >
                {{ tab }}
              </button>
            </div>
          </div>
        </div>
        <div class="card-body p-0">
          <div id="offer-table"></div>
        </div>
      </div>

      <div class="dashboard-panel dashboard-status-card" hidden>
        <div class="status-card-list">
          <div
            v-for="(card, i) in statusCards"
            :key="i"
            class="status-list-item"
            :class="{ 'status-list-item--active': card.state === 'Aktif', 'status-list-item--pasif': card.state !== 'Aktif' }"
          >
            <span
              class="status-list-icon"
              :style="{ background: card.iconBg, color: card.stateColor }"
              v-html="card.svg"
            ></span>
            <div class="status-list-body">
              <div class="status-list-title">{{ card.title }}</div>
              <div class="status-list-state">
                <span class="state-dot" :style="{ background: card.stateColor }"></span>
                <span :style="{ color: card.stateColor }" class="state-label">{{ card.state }}</span>
              </div>
              <div class="status-list-note">{{ card.note }}</div>
            </div>
            <div class="status-list-right">
              <div class="status-list-meta">
                <div class="status-list-date">{{ card.date }}</div>
                <div v-if="card.subNote" class="status-list-subnote">{{ card.subNote }}</div>
              </div>
              <button class="status-list-arrow" :class="{ 'status-list-arrow--active': card.state === 'Aktif' }" type="button">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="dashboard-bottom" hidden>
      <div class="dashboard-panel dashboard-activity-card">
        <div class="activity-headline">
          <div>
            <p class="small-label">Bildirimler</p>
            <h2>Son aktiviteler</h2>
          </div>
          <button class="text-button" type="button">Tümü</button>
        </div>
        <ul class="activity-feed">
          <li v-for="item in activityItems" :key="item.title + item.time" class="activity-item">
            <div class="activity-title">{{ item.title }}</div>
            <div class="activity-item-mid">
              <span
                class="activity-icon"
                :style="{ background: item.iconBg, color: item.iconColor }"
                v-html="item.svg"
              ></span>
              <span class="activity-status-text">{{ item.status }}</span>
            </div>
            <div class="activity-time">
              <span>{{ item.time.split(' ')[0] }}</span>
              <span>{{ item.time.split(' ')[1] }}</span>
            </div>
          </li>
        </ul>
      </div>

      <div class="dashboard-panel dashboard-summary-visual">
        <div class="summary-top">
          <p class="small-label">Bildirimler</p>
          <button class="text-button" type="button">Tümü</button>
        </div>
        <div class="summary-value">{{ summaryCards[0].value }}</div>
        <div class="summary-subtitle">{{ summaryCards[0].detail }}</div>
        <div class="summary-item-list">
          <div v-for="card in summaryCards" :key="card.label" class="summary-item-row">
            <span class="summary-item-num">{{ card.value }}</span>
            <div class="summary-item-info">
              <div class="summary-item-label">{{ card.label }}</div>
              <div class="summary-item-detail">{{ card.detail }}</div>
            </div>
            <button class="detay-button" type="button">Detay →</button>
          </div>
        </div>
        <div class="summary-expand-btn">
          <button type="button" class="expand-button">↗</button>
        </div>
      </div>
    </div>
</template>
<style scoped>
.dashboard-page {
  padding: 0 24px 24px;
  display: grid;
  gap: 24px;
}

/* ── Hero decoration ── */
.hero-deco {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 0;
}

.hero-deco svg {
  width: 100%;
  height: 100%;
}

.hero-deco img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* ── Hero ── */
.dashboard-hero {
  position: relative;
  background: #fde4cc;
  border-radius: 32px;
  padding: 32px 32px 26px;
  overflow: hidden;
  min-height: 220px;
 margin-bottom: 50px !important;
}

.hero-top {
  position: relative;
  z-index: 1;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  flex-wrap: wrap;
}

.hero-subtitle {
  margin: 0 0 5px;
  font-size: 0.88rem;
  font-weight: 500;
  letter-spacing: 0.01em;
  color: #154b90;
}

.hero-title {
  margin: 0;
  font-size: clamp(2rem, 3.2vw, 3.2rem);
  font-weight: 800;
  line-height: 1.05;
  color: #154b90;
}

.hero-actions {
  display: flex;
  gap: 10px;
}

.icon-button {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  border: none;
  background: rgb(21, 75, 144);
  color: white;
  cursor: pointer;
  display: grid;
  place-items: center;
  box-shadow: 0 4px 14px rgba(21, 75, 144,0.35);
}

.icon-button:hover {
  background: #123f7b;
}

.hero-menu {
  position: relative;
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
  margin-top: 28px;
  z-index: 1;
}

.hero-menu-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  border-radius: 18px;
  background: #154b9085;
  backdrop-filter: blur(20px) saturate(1.4);
  -webkit-backdrop-filter: blur(20px) saturate(1.4);
  border: 1px solid rgba(255,255,255,0.12);
  cursor: pointer;
  transition: background 0.18s;
}

.hero-menu-card:hover {
  background: #154b90b3;
}

.hero-menu-icon {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: rgba(255,255,255,0.16);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.hero-menu-icon :deep(svg) {
  width: 24px;
  height: 24px;
  stroke: #fff;
}
.hero-menu-icon :deep(svg) [stroke]:not([stroke="none"]) { stroke: #fff; }

.hero-menu-name {
  font-weight: 600;
  color: rgba(255,255,255,0.95);
  font-size: 0.84rem;
  line-height: 1.35;
}

/* ── Grid layouts ── */
.dashboard-main {
  display: grid;
  grid-template-columns: 2.2fr 1fr;
  gap: 20px;
}

.dashboard-table-card--bordered {
  border: 1.5px solid #fed7aa;
}

.dashboard-bottom {
  display: grid;
  grid-template-columns: 1.6fr 0.9fr;
  gap: 20px;
}

.dashboard-panel {
  background: #ffffff;
  border-radius: 28px;
  padding: 22px 24px;
  box-shadow: 0 4px 24px rgba(15, 23, 42, 0.07);
  border: 1px solid rgba(203, 213, 225, 0.35);
}

/* ── Table card ── */
.dashboard-table-card {
  display: grid;
  gap: 16px;
}

.table-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.tabs-pill {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.pill-button {
  padding: 9px 22px;
  border-radius: 999px;
  border: 1.5px solid rgb(21, 75, 144);
  background: #ffffff;
  color: #154b90;
  font-weight: 700;
  font-size: 0.88rem;
  cursor: pointer;
  transition: all 0.15s;
}

.pill-button--active {
  background: rgb(21, 75, 144);
  color: white;
  border-color: transparent;
  box-shadow: 0 6px 18px rgba(21, 75, 144, 0.28);
}

.sort-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 14px;
  border-radius: 999px;
  border: 1.5px solid #e2e8f0;
  background: #f8fafc;
  color: #64748b;
  font-size: 0.84rem;
  font-weight: 600;
  cursor: pointer;
}

.sort-chip--active {
  color: #154b90;
  border-color: #154b90;
  background: #154b901f;
}

.table-panel {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 520px;
}

th, td {
  text-align: left;
  padding: 14px 12px;
}

th {
  font-size: 0.76rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #94a3b8;
  border-bottom: 1px solid #f1f5f9;
  font-weight: 600;
}

.th-filter {
  color: #64748b;
  width: 28px;
  padding-right: 4px;
}

.th-sorted {
  color: #154b90 !important;
  display: flex;
  align-items: center;
  gap: 3px;
  white-space: nowrap;
}

tr {
  border-bottom: 1px solid #f8fafc;
}

tr:last-child {
  border-bottom: none;
}

.row-icon {
  width: 36px;
}

td {
  color: #334155;
  font-size: 0.92rem;
  vertical-align: middle;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 600;
}

.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #16a34a;
  flex-shrink: 0;
}

.status-pill--green {
  background: #f0fdf4;
  color: #16a34a;
}

/* ── Status list card ── */
.dashboard-status-card {
  padding: 12px;
  background: #ffffff;
}

.status-card-list {
  display: grid;
  gap: 10px;
}

.status-list-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  border-radius: 18px;
  border: 1px solid #e8edf2;
  background: #ffffff;
}

.status-list-item--pasif {
  background: #ffffff;
  border-color: #e8edf2;
}

.status-list-item--active {
  background: #f0fdf4;
  border-color: #bbf7d0;
}

.status-list-icon {
  width: 46px;
  height: 46px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.status-list-icon :deep(svg) {
  width: 20px;
  height: 20px;
}

.status-list-body {
  flex: 1;
  min-width: 0;
}

.status-list-title {
  font-weight: 700;
  color: #0f172a;
  font-size: 0.92rem;
  line-height: 1.3;
}

.status-list-state {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-top: 4px;
}

.state-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.state-label {
  font-size: 0.82rem;
  font-weight: 700;
}

.status-list-note {
  color: #64748b;
  font-size: 0.82rem;
  margin-top: 4px;
}

.status-list-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: space-between;
  gap: 10px;
  flex-shrink: 0;
  align-self: stretch;
}

.status-list-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.status-list-date {
  font-size: 0.8rem;
  color: #64748b;
  white-space: nowrap;
  font-weight: 500;
}

.status-list-subnote {
  font-size: 0.74rem;
  color: #94a3b8;
  text-align: right;
  line-height: 1.3;
  white-space: nowrap;
}

.status-list-arrow {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: 1.5px solid #cbd5e1;
  background: white;
  color: #94a3b8;
  cursor: pointer;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  transition: all 0.15s;
}

.status-list-arrow--active {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: #16a34a;
  border-color: #16a34a;
  color: white;
  box-shadow: 0 4px 12px rgba(22,163,74,0.30);
}

/* ── Activity feed ── */
.dashboard-activity-card {
  display: grid;
  gap: 18px;
}

.activity-headline {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.small-label {
  text-transform: uppercase;
  letter-spacing: 0.12em;
  font-size: 0.74rem;
  color: #94a3b8;
  margin: 0;
  font-weight: 600;
}

.dashboard-activity-card h2 {
  margin: 4px 0 0;
  font-size: 1.4rem;
  color: #0f172a;
  font-weight: 700;
}

.text-button {
  border: none;
  background: transparent;
  color: #64748b;
  font-weight: 600;
  font-size: 0.88rem;
  cursor: pointer;
  padding: 0;
}

.activity-feed {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0;
}

.activity-item {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: 12px;
  padding: 14px 0;
  border-bottom: 1px solid #f1f5f9;
}

.activity-item:last-child {
  border-bottom: none;
}

.activity-item-mid {
  display: flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}

.activity-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.activity-icon :deep(svg) {
  width: 16px;
  height: 16px;
}

.activity-title {
  font-size: 0.88rem;
  font-weight: 600;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.activity-status-text {
  color: #64748b;
  font-size: 0.82rem;
}

.activity-time {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 1px;
  font-size: 0.78rem;
  color: #94a3b8;
  white-space: nowrap;
}

/* ── Summary / Bildirimler ── */
.dashboard-summary-visual {
  display: flex;
  flex-direction: column;
  gap: 10px;
  position: relative;
}

.summary-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.summary-value {
  font-size: 4rem;
  font-weight: 800;
  color: #154b90;
  line-height: 1;
  margin-top: 2px;
}

.summary-subtitle {
  color: #94a3b8;
  font-size: 0.88rem;
  margin-bottom: 8px;
}

.summary-item-list {
  display: grid;
  gap: 10px;
  margin-top: 4px;
}

.summary-item-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid #f1f5f9;
  background: transparent;
  border-radius: 0;
}

.summary-item-row:last-child {
  border-bottom: none;
}

.summary-item-num {
  font-size: 1.6rem;
  font-weight: 800;
  color: #334155;
  min-width: 34px;
  line-height: 1;
}

.summary-item-info {
  flex: 1;
  min-width: 0;
}

.summary-item-label {
  font-weight: 600;
  color: #334155;
  font-size: 0.88rem;
}

.summary-item-detail {
  color: #94a3b8;
  font-size: 0.8rem;
}

.detay-button {
  border: none;
  background: transparent;
  color: #94a3b8;
  font-size: 0.82rem;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}

.summary-expand-btn {
  display: flex;
  justify-content: flex-end;
  margin-top: auto;
}

.expand-button {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  background: white;
  color: #64748b;
  cursor: pointer;
  display: grid;
  place-items: center;
  font-size: 0.9rem;
}

@media (max-width: 1100px) {
  .dashboard-main,
  .dashboard-bottom {
    grid-template-columns: 1fr;
  }

  .hero-menu {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 740px) {
  .dashboard-page {
    padding: 14px;
  }

  .hero-menu {
    grid-template-columns: repeat(2, 1fr);
  }

  .hero-top {
    flex-direction: column;
    align-items: stretch;
  }
}

@media (max-width: 500px) {
  .hero-menu {
    grid-template-columns: 1fr;
  }

  
}
</style>