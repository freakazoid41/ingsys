<script>
  /*import { useNavigationStore } from '@/stores/navigation';
  import { useAuthStore } from '@/stores/auth';
  import 'pickletable/assets/style.css';
  import Plib from '@/lib/pickle';
  import { wTrans } from 'laravel-vue-i18n';
  import Chart from 'chart.js/auto';
  import Swal from 'sweetalert2';
  import { Datepicker } from 'vanillajs-datepicker';
  import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';
  import Simplebar from 'simplebar-vue';
  import 'simplebar-vue/dist/simplebar.min.css';
  import IncomeWaiting from '@/components/dashboard/IncomeWaiting.vue';
  import LastStatus from '@/components/dashboard/LastStatus.vue';
      


  export default {
      components: {
        IncomeWaiting,
        LastStatus,
        Simplebar
      },
      setup() {
        Object.assign(Datepicker.locales, tr);
        // expose to template and other options API hooks
        return {
            useNavigationStore,
            Plib,
            wTrans,
            Chart,
            Swal,
            Datepicker,
            useAuthStore
            //Quill
        }
      },
      data() {
        return {
            plib            : new Plib(),
            authStore       : useAuthStore(),
            navigationStore : useNavigationStore(),
            flatCount       : null,
            totalBalance    : null,
            outcome         : null,
            income          : null,
            lastInfo        : null,
            lastStatus      : null,
        }
      },
      mounted(){
          this.navigationStore.toggle(true);
          
          
          this.navigationStore.setBread([
            {
                title : 'Körfez Apt.',
                url   : '/panel',
            },
            {
                title : this.wTrans('menu.home'),
                url   : '/panel',
            }
          ] ,this.wTrans('menu.home'));

         

          this.bringBoxes();

          setTimeout(() => {
              this.navigationStore.toggle(false);
          }, 300);
      },  
      
      methods: {
          buildEditor(element) {
            let icons = Quill.import("ui/icons");
            icons["bold"] = '<i class="ph ph-text-b"></i>';
            icons["italic"] = '<i class="ph ph-text-italic"></i>';
            icons["underline"] = '<i class="ph ph-text-underline"></i>';
            icons["link"] = '<i class="ph ph-link"></i>';
            icons["image"] = '<i class="ph ph-image"></i>';
            icons["list"]["ordered"] = '<i class="ph ph-list-numbers"></i>';
            icons["list"]["bullet"] = '<i class="ph ph-list-bullets"></i>';
            //icons["align"] = '<i class="ph ph-list-bullets"></i>';
            return new Quill('.quill-inner', {
              modules: {
                toolbar: [
                  ["bold", "italic", "underline"],
                  [{ list: "ordered" }, { list: "bullet" },{ 'align': [] }],
                  
                  [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                  [{ 'font': [] }],
                  ["clear"],
                ],
              },
              //placeholder: placeholder,
              theme: "snow",
            });
          },  
          bringBoxes(){
            this.plib.request({
                url      : '/api/v1/dashboard/flatcount',
                method   : 'GET',
            },null).then(rsp => {
              setTimeout(() => {
                this.flatCount = rsp.data;
              }, 500);
            });

            this.plib.request({
                url      : '/api/v1/dashboard/totalBalance',
                method   : 'GET',
            },null).then(rsp => {
                
              setTimeout(() => {
                this.totalBalance = this.plib.formatMoney(rsp.data,2,',','.') + ' '+rsp.currency;

                const chart = new Chart(document.getElementById('balance-area'), {
                  type: 'line',
                  
                  
                  data: {
                    labels: Object.keys(rsp.chart),
                    datasets: [{
                      label: rsp.currency + ' Bazında Miktar ',
                      data: Object.values(rsp.chart),
                      borderColor: '#0000004d',
                      borderWidth: 3,
                    }]
                  },
                  options: {
                    maintainAspectRatio: false,
                    responsive : true,
                    plugins: {
                      tooltip: {
                            callbacks: {
                                label: (context) => context.parsed.y + ' '+rsp.currency
                            }
                        },
                        legend: {
                            labels: {
                                // This more specific font property overrides the global property
                                font: {
                                    size: 14
                                },color : 'white'
                            }
                        }
                    },
                    scales: {
                      x: {
                        ticks: {
                          color : 'white',
                        }
                      },
                      y: {
                        beginAtZero: true,
                        ticks: {
                          color : 'white',
                          // Include a dollar sign in the ticks
                          callback: (value, index, ticks) =>{
                              return value+' '+rsp.currency;
                          }
                        }
                      }
                    }
                  }
                });
              }, 500);
            });

            this.plib.request({
                url      : '/api/v1/dashboard/outcome',
                method   : 'GET',
            },null).then(rsp => {
              setTimeout(() => {
                this.outcome = {};
                this.outcome.total = this.plib.formatMoney(rsp.data,2,',','.')+' '+rsp.cur;
                this.outcome.ratio = rsp.ratio;
              }, 500);
            });

            this.plib.request({
                url      : '/api/v1/dashboard/income',
                method   : 'GET',
            },null).then(rsp => {
              setTimeout(() => {
                this.income = {};
                this.income.total = this.plib.formatMoney(rsp.data,2,',','.')+' '+rsp.cur;
                this.income.ratio = rsp.ratio;
              }, 500);
            });

            this.plib.request({
                url      : '/api/v1/dashboard/updatedmeeting',
                method   : 'GET',
            },null).then(rsp => {
              setTimeout(() => {
                this.lastInfo = {
                  supervisor : rsp.meet_active_supervisor,
                  amount     : this.plib.formatMoney(rsp.meet_amount,2,',','.')+' '+rsp.cur
                };
              }, 500);
            });
          },
          bringModal(type){
            if(this.authStore.data.type == 'admin'){
              this.navigationStore.toggle(true);
              window.location.href = '/panel/'+type;
            }
            
          },
          exportReport(){
            this.navigationStore.toggle(true);
            Swal.fire({
                width : 400,
                confirmButtonText : 'Çıkart',
                showCloseButton : true,
                cancelButtonText : 'İptal',
                showCancelButton : true,
                customClass: {
                    confirmButton: "btn btn-secondary me-3",
                    cancelButton: "btn btn-secondary me-3"
                },
                buttonsStyling: false,
                html : `
                <style>
                  .ql-picker-options {background-color:#000000b1 !important;}
                  .ql-picker-label,
                  .ql-snow .ql-picker {color:white !important;}
                  .quill.border {border-color: #ffffff80 !important}
                  .ql-editor {height:500px !important}
                  .swal2-popup{ background-color:#000000b1 !important;}
                </style>
                <div class="row w-100">
                    <div class="col-12 mt-5">
                        <input type="text" style="text-align:center" readonly name="period" required class="form-control trans-form"  placeholder="İşlem Dönemi">
                    </div>
                    <div class="col-12 mt-5" hidden>
                      <div class="quill border">
                        <div class="quill-inner"></div>
                      </div>
                    </div>
                </div>`,
                willOpen : async () => {
                    //this.icmalhtml = this.buildEditor(document.querySelector('.quill-inner'));
                    const datepicker = new Datepicker(document.querySelector('input[name="period"]'), {
                        language   : 'tr',
                        pickLevel  : 1, // for month select
                        format     : 'mm/yyyy',
                        orientation : 'top',
                        maxNumberOfDates : 2,
                        dateDelimiter : ' - '
                        
                    }); 
                    //just for this date component
                    //document.querySelector('input[name="period"]').addEventListener('changeDate',e => e.target.dispatchEvent(new Event('input')));

                    this.navigationStore.toggle(false);
                },
                allowOutsideClick : () => !Swal.isLoading(),
                preConfirm : async () => {
                  Swal.showLoading();
                  const form = this.plib.checkForm('.trans-form');
                  if(form.valid && form.obj['period'].includes(' - ')){
                    await this.plib.openTab('POST','/reportpdf/icmal',{
                      'dates' : form.obj['period'],
                      //'note'  : this.icmalhtml.getSemanticHTML()
                    },'_BLANK');
                    return false; 
                    //Swal.close();
                  }else{
                    Swal.showValidationMessage('Başlangıç ve Bitiş Tarihi Seçiniz...');
                    return false;
                  }
                }
            });
          }
      }
  }*/

</script>

<template>
  <!--<div class="g-3 lh-1 mb-3 row  row-cols-lg-4 row-cols-sm-2">
    <div class="col-4">
      <div class="card flex-row p-5 selectable transclick"  @click="bringModal('flats')">
        <i class="align-items-center bg-active d-flex flex-shrink-0 fs-2 h-11 justify-content-center me-4 ph ph-kanban rounded text-body-emphasis w-11"></i>
        <div class="d-flex align-items-center" v-if="flatCount == null">
          <div class="spinner-border"  role="status"> <span class="visually-hidden">Loading...</span> </div>
        </div>
        <div class="flex-grow-1" v-if="flatCount != null">
            <div class="align-items-center d-flex mb-1">
              <div class="fs-5 fw-medium mb-1 me-2 text-body-emphasis">
                {{ flatCount }}
              </div>
              <div style="opacity:0" class="align-items-center badge bg-danger bg-opacity-75 d-inline-flex fs-8 ms-auto mt-n1 rounded-pill">
                <i class="fs-6 me-1 ph ph-arrow-circle-down"></i> 0 
              </div>
            </div> {{$t('dashboard.totalflats')}}
        </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card flex-row p-5 selectable transclick"  @click="bringModal('transactions')"> 
        <i class="align-items-center bg-active d-flex flex-shrink-0 fs-2 h-11 justify-content-center me-4 ph ph-receipt rounded text-body-emphasis w-11"></i>
        <div class="d-flex align-items-center" v-if="outcome == null">
          <div class="spinner-border"  role="status"> <span class="visually-hidden">Loading...</span> </div>
        </div>
        <div class="flex-grow-1" v-if="outcome != null">
          <div class="align-items-center d-flex mb-1">
            <div class="fs-5 fw-medium mb-1 me-2 text-body-emphasis">{{ outcome.total }}</div>
            <div
              class="align-items-center badge bg-opacity-75 d-inline-flex fs-8 ms-auto mt-n1 rounded-pill" 
              :class="outcome.ratio < 0 ? 'bg-danger' : 'bg-success'">
              <i class="fs-6 me-1 ph"
                :class="outcome.ratio < 0 ?  'ph-arrow-circle-down' : 'ph-arrow-circle-up'"></i> {{ outcome.ratio }}% </div>
          </div> {{$t('dashboard.monthly.outcome')}}
        </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card flex-row p-5 selectable transclick"  @click="bringModal('transactions')"> 
        <i class="align-items-center bg-active d-flex flex-shrink-0 fs-2 h-11 justify-content-center me-4 ph ph-arrow-fat-lines-down rounded text-body-emphasis w-11"
          style="--bs-bg-opacity:.2"></i>
        <div class="d-flex align-items-center" v-if="income == null">
          <div class="spinner-border"  role="status"> <span class="visually-hidden">Loading...</span> </div>
        </div>
        <div class="flex-grow-1" v-if="income != null">
          <div class="align-items-center d-flex mb-1">
            <div class="fs-5 fw-medium mb-1 me-2 text-body-emphasis">{{ income.total }}</div>
            <div
              class="align-items-center badge bg-opacity-75  d-inline-flex fs-8 ms-auto mt-n1 rounded-pill"
              :class="income.ratio < 0 ? 'bg-danger' : 'bg-success'">
              <i class="fs-6 me-1 ph "
                :class="income.ratio < 0 ?  'ph-arrow-circle-down' : 'ph-arrow-circle-up'"></i> {{ income.ratio }}% </div>
          </div> {{$t('dashboard.monthly.income')}}
        </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card flex-row p-5 selectable transclick"  @click="bringModal('transactions')"> 
        <i class="align-items-center bg-active d-flex flex-shrink-0 fs-2 h-11 justify-content-center me-4 ph ph-vault rounded text-body-emphasis w-11"
          style="--bs-bg-opacity:.2"></i>
        <div class="d-flex align-items-center" v-if="totalBalance == null">
          <div class="spinner-border"  role="status"> <span class="visually-hidden">Loading...</span> </div>
        </div>
        <div class="flex-grow-1" v-if="totalBalance != null">
          <div class="align-items-center d-flex mb-1">
            <div class="fs-5 fw-medium mb-1 me-2 text-body-emphasis">{{ totalBalance }}</div>
            <div style="opacity:0" class="align-items-center badge bg-danger bg-opacity-75 d-inline-flex fs-8 ms-auto mt-n1 rounded-pill">
              <i class="fs-6 me-1 ph ph-arrow-circle-down"></i> 0.54% </div>
          </div> {{$t('dashboard.totalbalance') }}
        </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card flex-row p-5">
        <i class="align-items-center bg-active d-flex flex-shrink-0 fs-2 h-11 justify-content-center me-4 ph ph-user rounded text-body-emphasis w-11"
          style="--bs-bg-opacity:.2"></i>
          <div class="d-flex align-items-center" v-if="lastInfo == null">
            <div class="spinner-border"  role="status"> <span class="visually-hidden">Loading...</span> </div>
          </div>
          <div class="d-flex align-items-center" v-if="totalBalance != null">
              {{$t('dashboard.supervisor')  }} : {{ lastInfo?.supervisor }}
          </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card flex-row p-5">
        <i class="align-items-center bg-active d-flex flex-shrink-0 fs-2 h-11 justify-content-center me-4 ph ph-piggy-bank rounded text-body-emphasis w-11"
          style="--bs-bg-opacity:.2"></i>
          <div class="d-flex align-items-center" v-if="lastInfo == null">
            <div class="spinner-border"  role="status"> <span class="visually-hidden">Loading...</span> </div>
          </div>
          <div class="d-flex align-items-center" v-if="totalBalance != null">
              {{$t('dashboard.amount')  }} : {{ lastInfo?.amount }}
          </div>
      </div>
    </div>
    <div class="col-4" v-if="this.authStore.data.type == 'admin'">
      <div class="card flex-row p-5 selectable transclick" @click="bringModal('targets')"> 
        <i class="align-items-center bg-active d-flex flex-shrink-0 fs-2 h-11 justify-content-center me-4 ph ph-arrows-left-right rounded text-body-emphasis w-11"
          style="--bs-bg-opacity:.2"></i>
          <div class="d-flex align-items-center">
            {{ $t('dashboard.transaction') }}
          </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card flex-row p-5 selectable" @click="exportReport()"> 
        <i class="align-items-center bg-active d-flex flex-shrink-0 fs-2 h-11 justify-content-center me-4 ph ph-read-cv-logo rounded text-body-emphasis w-11"
          style="--bs-bg-opacity:.2"></i>
          <div class="d-flex align-items-center">
            {{ $t('dashboard.export') }}
          </div>
      </div>
    </div>
  </div>
  <div class="g-3 row mb-3">
      <div class="col-12 mb-1">
        <div class="card">
          <div class="card-body">
              <h3 class="card-title fs-5">Bütçe Durum Grafiği (Miktar / Tarih)</h3>
              <p class="card-subtitle mb-6"> Bu alanda yıl içerisindeki bütçe dalgalanması belirtilir </p>
              <div class="chart" > <canvas id="balance-area"></canvas> </div>
          </div>
        </div>
      </div>
      <IncomeWaiting qtype="doc_acc_aidat" qcolclass="col-6 col-md-4 col-sm-4 mb-1"></IncomeWaiting>
      <IncomeWaiting qtype="doc_acc_fuel" qcolclass="col-6 col-md-4 col-sm-4 mb-1"></IncomeWaiting>
      <lastStatus qcolclass="col-4 col-md-4 col-sm-4 mb-1"></lastStatus>
      
  </div>-->
  
        
</template>
