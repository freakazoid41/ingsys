<script>
    import { useNavigationStore } from '@/stores/navigation'
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import Plib from '@/lib/pickle';
    import { useTaskDataStore } from '@/stores/tasks'
    import Simplebar from 'simplebar-vue';


    export default {
        components: {
            Simplebar
        },
        setup() {
            
            // expose to template and other options API hooks
            return {
                useTaskDataStore,
                useNavigationStore,
                wTrans,
                Swal,
                Plib
            }
        },
        data() {
            return {
                taskDataStore   : useTaskDataStore(),
                plib            : new Plib(),
                navigationStore : useNavigationStore(),
                contacts        : null,
                showGroup       : 'all',
                showTab         : 'contacts',
                lastStatus      : null,
                tabRans         : {
                    'currentstatus'  : this.wTrans('dashboard.health'),
                    'contacts'       : this.wTrans('menu.contacts'),
                    'events'         : this.wTrans('dashboard.ongoingtasks'),
                    'all'            : this.wTrans('contact.allcontacts'),
                    'meet'           : this.wTrans('contact.government'),
                    'flat'           : this.wTrans('menu.flats'),
                    'proj'           : this.wTrans('contact.workers'),
                }
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            if(this.taskDataStore.tasks.length == 0) this.taskDataStore.setData();
            
            this.navigationStore.setBread([
                {
                    title : 'Körfez Apt.',
                    url   : '/panel',
                },
                {
                    title : this.wTrans('menu.contacts'),
                    url   : '/panel/contacts',
                }
            ] ,this.wTrans('menu.contacts'));

            /*this.navigationStore.setButtons([
                {
                icon : 'ph ph-download',
                onclick   : () => {console.log('Falan')},
                }
            ]);*/
            this.buildData();

            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },
        methods: {
            async changeImage(){
                /*const { value: file } = await Swal.fire({
                    showCloseButton : true,
                    confirmButtonText : 'Kaydet',
                    title: "Resim Seçin",
                    input: "file",
                    inputAttributes: {
                        "accept": "image/*",
                        "aria-label": "Upload your profile picture"
                    }
                });
                if (file) {
                    const envelope = new FormData();
                    envelope.append('bgfile',file);
                    await this.plib.request({
                        url      : '/api/v1/setbackground',
                        method   : 'POST',
                    },null,envelope);
                }*/
            },
            buildData(){
                const reqData = async (url,callback) => {
                    return this.plib.request({
                        url      : url,
                        method   : 'GET',
                    },null).then(rsp => {
                        callback(rsp);
                    });
                }

                reqData('/api/v1/dashboard/contacts',(rsp) => {
                    console.log(rsp);
                    setTimeout(() => {
                        this.contacts = rsp.list;
                    }, 500);
                });

                reqData('/api/v1/dashboard/updatedstatus',(rsp) => {
                    setTimeout(() => {
                        let rentWaiting = 0;
                        for(let key in rsp){
                            if(key.includes('meet_rent**')) rentWaiting = parseFloat(rsp[key]);
                        }
                        
                        this.lastStatus = {
                            ruler          : rsp.meet_active_supervisor,  
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

                reqData('/api/v1/dashboard/incomestatus',(rsp) => {
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
            }
        }
    }
</script>
<style>
.no-avatar {
  width: 200px;
  height: 200px;
  /*font-size: 7rem;*/
  color: white;
  border-radius: 50%;
  background-color: #0000004d;
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: "Roboto-Regular", Roboto, Helvetica, Arial;
}


.s-icon {
  color: white;
  font-size: 100px;
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  -ms-transform: translate(-50%, -50%);
  text-align: center;
}

.s-container:hover .s-overlay {
    opacity: 0.4;
    cursor: pointer;
}

.s-container{
    position: relative;
}

.s-overlay {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  height: 100%;
  width: 100%;
  opacity: 0;
  transition: .3s ease;
  background-color: red;
  border-radius: 5px;
}

</style>
<template>
    <div class="mw-1200 mx-auto w-100"> 
        <div class="card mb-3"> 
            <div class="p-1.5"> 
                <div class="s-container mb-1.5" >
                    <img class="h-64  object-fit-cover rounded-bottom-1 rounded-top w-100" src="https://super-admin.avidtemplates.com/cover.d7fbd183.jpeg" alt="">
                    <div class="s-overlay" @click="changeImage">
                        <a href="#" class="s-icon" title="User Profile">
                            <i class="ph ph-repeat"></i>
                        </a>
                    </div> 
                </div>
                <nav class="nav nav-pills nav-scroll"> 
                     <a class="nav-link" v-for="t in ['currentstatus','contacts','events']" :class="{
                        'active' : (showTab == t)
                    }" @click="showTab = t" href="javascript:;" >{{ tabRans[t] }}</a> 
                </nav> 
            </div> 
            <nav class="border-top fs-7 nav nav-pills nav-scroll p-1.5" v-if="showTab == 'contacts'">
                <a class="nav-link group-btn" v-for="t in ['all','meet','flat','proj']" :class="{
                    'active' : (showGroup == t)
                }" data-group="all" @click="showGroup = t" href="javascript:;">{{ tabRans[t] }}</a> 
            </nav>
        </div> 
        <div v-if="showTab== 'currentstatus'">
            <div class="g-3 row mb-3">
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
                            <div style="height: 30vh">
                                <Simplebar>
                                    <div id="list-opportunities1" v-for="r in incomeWaiting">
                                    <div class="d-flex justify-content-between align-items-center h-12 rounded px-3">
                                        
                                        <div class="flex-grow-1 text-truncate">{{r.title}}</div>
                                        
                                        <div class="w-20 text-end flex-shrink-0 ms-sm-2">{{ r.balance }}</div>
                                    </div>
                                    
                                    </div> 
                                </Simplebar>
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
                                            Yönetici
                                        </div>
                                    </div>
                                    <div class="ms-2 flex-grow-1 border-bottom pb-3 mb-n3">
                                        <div class="d-flex align-items-center" v-if="lastStatus == null">
                                        <div class="spinner-border" style="width: 1rem !important;height: 1rem !important;" role="status"> 
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        </div>
                                        <span v-if="lastStatus != null">{{ lastStatus.ruler }}</span>
                                    </div>
                                    
                                </div>
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
        </div>
        <div id="user-connections1" v-if="showTab== 'contacts'" class="gap-3 grid">
            <div v-for="c in contacts" class=" g-col-12 g-col-sm-6 g-col-lg-4 g-col-xl-3 cont-cards">
                <div v-if="(showGroup == 'all' || showGroup == c.group)" class="card p-3 h-100">
                    <div class="d-flex align-items-center mb-5">
                        
                        <a href="#" class="flex-shrink-0 mr-3">
                            <div class="no-avatar w-10 h-10 ms-1 me-3">{{ c.cont_name.match(/\p{Lu}/gu).join('') }}</div>
                            <!--<img class="w-10 h-10 ms-1 rounded-circle me-3" src="https://super-admin.avidtemplates.com/1.e810f372.jpg" alt="">-->
                        </a>
                        <div class="flex-grow-1">
                            <a href="" class="text-body-emphasis fw-medium">{{ c.cont_name + ( c.cont_spec ? ' ('+(c.cont_spec)+')' : '') }}</a>
                            <div class="text-body-secondary text-link-seondary fs-7">{{ c.cont_mail ?? '' }}</div>
                        </div>
                        <!--<div class="dropdown align-self-start">
                            <button class="icon ph ph-dots-three-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false"></button>
                            <nav class="dropdown-menu dropdown-menu-end" style="">
                                <a class="dropdown-item" href="#">Block</a>
                                <a class="dropdown-item" href="#">Message</a>
                            </nav>
                        </div>-->
                    </div>
                            
                    <div class="text-center">
                        <button class="btn btn-secondary btn-sm mb-1 w-40">{{ c.cont_phone }}</button>
                        <div class="text-body-secondary fs-8">{{ c.title ?? '' }}</div>
                    </div>
                </div>
            </div>
           
            
        </div> 
        <div v-if="showTab== 'events'">
            <div class="col-12 col-md-12 col-sm-12 mb-1">
                <div class="card h-100" style="overflow-y: auto;">
                    <div class="card-body"> 
                        <div class="align-items-start d-flex mb-5"> 
                            <h5 class="card-title flex-grow-1 m-0"> {{ $t('dashboard.incomewaiting') }} </h5> 
                            <!--<div class="d-flex gap-1 me-n1.5 mt-n1.5"> 
                                <a href="" class="icon ph ph-info"></a> 
                            </div>--> 
                        </div> 
                        <div class="align-items-center d-flex fs-7 h-10 justify-content-between px-3 rounded text-body-secondary"> 
                        <div class="flex-grow-1">Proje</div> 
                        <div class="flex-grow-1">Durum</div> 
                        <div class="flex-grow-1">Tarih</div> 
                        <div class="flex-shrink-0 text-end w-50 w-sm-50">Tahmini Gider</div> 
                        </div> 
                        <div id="list-opportunities1" v-for="task in this.taskDataStore.tasks">
                        <div class="d-flex justify-content-between align-items-center h-12 rounded px-3">
                            
                            <div class="flex-grow-1 text-truncate">{{task.title}}</div>
                            <div class="flex-grow-1 text-truncate">
                                <div class="badge bg-opacity rounded-pill truncate"
                                    :class="{
                                        'text-danger bg-danger'   : task.status.split('**')[0] == 'doc_trans_project_sikinti',
                                        'text-success bg-success' : task.status.split('**')[0] == 'doc_trans_project_end',
                                        'text-primary bg-primary' : task.status.split('**')[0] == 'doc_trans_project_payment',
                                        'text-warning bg-warning' : task.status.split('**')[0] == 'doc_trans_project_start',
                                        'text-info bg-info'       : ['doc_trans_created','doc_file_waiting'].includes(task.status.split('**')[0])
                                    }"
                                    style="--bs-bg-opacity: 0.2">
                                    {{['doc_trans_created','doc_file_waiting'].includes(task.status.split('**')[0]) ? 'Bekleniyor...' :  task.status.split('**')[1]}}
                                </div>
                            </div>
                            <div class="flex-grow-1 text-truncate">
                                <div class="fw-medium">
                                    {{task.start_date.split('-').reverse().join('/')}}
                                    -
                                    {{task.end_date.split('-').reverse().join('/')}}
                                </div>
                            </div>
                            <div class="w-50 text-end flex-shrink-0 ms-sm-2">{{this.plib.formatMoney(JSON.parse(task.main_attr).filter(el => {
                                return el['Key'] == 'planed_amount';
                            })[0]['Value'],',','.')+' '+task.cur}}</div>
                        </div>
                        
                        </div> 
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>