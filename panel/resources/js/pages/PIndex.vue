<script>
  import { useNavigationStore } from '@/stores/navigation'
  import 'pickletable/assets/style.css';
  import Plib from '@/lib/pickle';
  import { wTrans } from 'laravel-vue-i18n';
  import Chart from 'chart.js/auto';
  import Swal from 'sweetalert2';
  import { Datepicker } from 'vanillajs-datepicker';
  import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';
  /*import Quill from 'quill';
  import "quill/dist/quill.core.css";
  import "quill/dist/quill.snow.css";*/

  export default {
      setup() {
        Object.assign(Datepicker.locales, tr);
        // expose to template and other options API hooks
        return {
            useNavigationStore,
            Plib,
            wTrans,
            Chart,
            Swal,
            Datepicker
            //Quill
        }
      },
      data() {
        return {
            plib            : new Plib(),
            navigationStore : useNavigationStore(),
            flatCount       : null,
            totalBalance    : null,
            outcome         : null,
            income          : null,
            lastInfo        : null,
            incomeWaiting   : null,
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

          /*this.navigationStore.setButtons([
            {
              icon : 'ph ph-download',
              onclick   : () => {console.log('Falan')},
            }
          ]);*/

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

            this.plib.request({
                url      : '/api/v1/dashboard/incomestatus',
                method   : 'GET',
            },null).then(rsp => {
              if(rsp.length > 0) this.incomeWaiting = [];
              rsp.forEach(r => {
                const mainInfo = JSON.parse(r.main_attr);
                mainInfo['per_name'] = [];
                JSON.parse(r.main_attr).forEach(element => {
                    mainInfo[element['Key']] = element['Value'];
                    if(element['Key'].includes('per_name')) mainInfo['per_name'].push(element['Value']);
                });
                mainInfo['per_name'] = mainInfo['per_name'].join(',');
                
                this.incomeWaiting.push({
                  balance : (r.balance ?? 0) + ' ' + r.cur,
                  title   : mainInfo.title,
                  perName : mainInfo['per_name']
                });
              });
            });

            this.plib.request({
                url      : '/api/v1/dashboard/updatedstatus',
                method   : 'GET',
            },null).then(rsp => {
              
              setTimeout(() => {
                let rentWaiting = 0;
                for(let key in rsp){
                  if(key.includes('meet_rent**')) rentWaiting = parseFloat(rsp[key]);
                }
                
                this.lastStatus = {
                  supervisor     : rsp.meet_active_supervisor_sub,  
                  projectCount   : rsp.project_count,
                  incomeWaiting  : (parseInt(rsp.flat_count) * parseFloat(rsp.meet_amount)),
                  rentWaiting    : rentWaiting ,
                  incomeReceived : parseFloat(rsp.total_income ?? 0),
                  rentReceived   : parseFloat(rsp.total_rent ?? 0),
                  cur            : rsp.cur
                }
              }, 500);
            });

          },
          bringModal(type){
            this.navigationStore.toggle(true);
            window.location.href = '/panel/'+type
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
                  .swal2-popup{ /*height:800px !important;*/background-color:#000000b1 !important;}
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
  }

</script>

<template>
  <div class="g-3 lh-1 mb-3 row  row-cols-lg-4 row-cols-sm-2">
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
    <div class="col-4">
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
      <div class="col-3 col-md-6 col-sm-6 mb-1">
        <div class="card h-100" style="overflow-y: auto;">
          <div class="card-body"> 
            <div class="align-items-start d-flex mb-5"> 
                <h5 class="card-title flex-grow-1 m-0"> {{ $t('dashboard.incomewaiting') }} </h5> 
                <!--<div class="d-flex gap-1 me-n1.5 mt-n1.5"> 
                    <a href="" class="icon ph ph-info"></a> 
                </div>--> 
            </div> 
            <div class="align-items-center d-flex fs-7 h-10 justify-content-between px-3 rounded text-body-secondary"> 
              <div class="flex-grow-1">Daire</div> 
              <div class="flex-shrink-0 text-end w-10 w-sm-20">Bakiye</div> 
            </div> 
            <div id="list-opportunities1" v-for="r in incomeWaiting">
              <div class="d-flex justify-content-between align-items-center h-12 rounded px-3">
                  
                  <div class="flex-grow-1 text-truncate">{{r.title+' ('+r.perName+')'}}</div>
                  
                  <div class="w-20 text-end flex-shrink-0 ms-sm-2">{{ r.balance }}</div>
              </div>
              
            </div> 
          </div>
        </div>
      </div>
      <div class="col-3 col-md-6 col-sm-6 mb-1">
        <div class="card h-100"> 
          <div class="card-body"> 
            <div class="align-items-start d-flex mb-7"> 
              <h5 class="card-title flex-grow-1 m-0">{{$t("dashboard.health")}}</h5> 
              <div class="d-flex gap-1 me-n1.5 mt-n1.5"> 
                <a href="/panel/meetings" class="icon ph ph-info"></a> 
              </div> 
            </div> 
            <div id="list-health1">
              <div class="d-flex align-items-center mt-3">
                  <div class="w-28 flex-shrink-0">
                      <div class="rounded bg-active text-body-emphasis text-success py-1.5 px-2.5 d-inline-flex align-items-center">
                          <i class="ph fs-4 me-1.5 ph-person"></i>
                          Denetçi
                      </div>
                  </div>
                  <div class="ms-2 flex-grow-1 border-bottom pb-3 mb-n3">
                    <div class="d-flex align-items-center" v-if="lastStatus == null">
                      <div class="spinner-border" style="width: 1rem !important;height: 1rem !important;" role="status"> 
                        <span class="visually-hidden">Loading...</span>
                       </div>
                    </div>
                    <span v-if="lastStatus != null">{{ lastStatus.supervisor }}</span>
                  </div>
                  
              </div>
              <div class="d-flex align-items-center mt-3">
                <div class="w-28 flex-shrink-0">
                    <div class="rounded bg-active text-body-emphasis text-success py-1.5 px-2.5 d-inline-flex align-items-center">
                        <i class="ph fs-4 me-1.5 ph-hammer"></i>
                        Aktif Proje
                    </div>
                </div>
                <div class="ms-2 flex-grow-1 border-bottom pb-3 mb-n3">
                  <div class="d-flex align-items-center" v-if="lastStatus == null">
                    <div class="spinner-border" style="width: 1rem !important;height: 1rem !important;" role="status"> 
                      <span class="visually-hidden">Loading...</span>
                      </div>
                  </div>
                  <span v-if="lastStatus != null">{{ lastStatus.projectCount ?? 0 }}</span> Aktif Proje
                </div>
                
              </div>
              <div class="d-flex align-items-center mt-3">
                <div class="w-28 flex-shrink-0">
                    <div class="rounded bg-active text-body-emphasis text-danger py-1.5 px-2.5 d-inline-flex align-items-center">
                        <i class="ph fs-4 me-1.5 ph-piggy-bank"></i>
                        Aidat
                    </div>
                </div>
                <div class="ms-2 flex-grow-1 border-bottom pb-3 mb-n3">
                  <div class="d-flex align-items-center" v-if="lastStatus == null">
                    <div class="spinner-border" style="width: 1rem !important;height: 1rem !important;" role="status"> 
                        <span class="visually-hidden">Loading...</span>
                    </div>
                  </div>
                  <span v-if="lastStatus != null">{{ this.plib.formatMoney(lastStatus.incomeWaiting ?? 0) + ' ' + lastStatus.cur }} (Aylık Beklenen) / {{ this.plib.formatMoney(lastStatus.incomeReceived ?? 0 ) + ' ' + lastStatus.cur}} (Toplanan)</span>
                </div>
                <i class="ph fs-3 ms-3 d-none d-sm-block ph-warning-octagon text-danger" v-if="(lastStatus?.incomeWaiting ?? 0) > (lastStatus?.incomeReceived ?? 0 )"></i>
              </div>
              <div class="d-flex align-items-center mt-3">
                <div class="w-28 flex-shrink-0">
                    <div class="rounded bg-active text-body-emphasis text-warning py-1.5 px-2.5 d-inline-flex align-items-center">
                        <i class="ph fs-4 me-1.5 ph-key"></i>
                        Kira
                    </div>
                </div>
                <div class="ms-2 flex-grow-1 border-bottom pb-3 mb-n3">
                  <div class="d-flex align-items-center" v-if="lastStatus == null">
                    <div class="spinner-border" style="width: 1rem !important;height: 1rem !important;" role="status"> 
                        <span class="visually-hidden">Loading...</span>
                    </div>
                  </div>
                  <span v-if="lastStatus != null">{{ this.plib.formatMoney(lastStatus.rentWaiting ?? 0) + ' ' + lastStatus.cur }} (Aylık Beklenen) / {{ this.plib.formatMoney(lastStatus.rentReceived ?? 0) + ' ' + lastStatus.cur}} (Toplanan)</span>
                </div>
                <i class="ph fs-3 ms-3 d-none d-sm-block ph-warning-octagon text-danger" v-if="(lastStatus?.rentWaiting ?? 0) > (lastStatus?.rentReceived ?? 0 )"></i>
              </div>
            </div> 
          </div> 
        </div>
      </div>
  </div>
  <div class="g-3 lh-1 mb-3">
    <div class="row">
      
    </div>
  </div>
  
        
</template>
