<script>

    import Plib from '@/lib/pickle';
    import Simplebar from 'simplebar-vue';
    import 'simplebar-vue/dist/simplebar.min.css';

    export default {
        props: {
            qcolclass : {
                type: String
            },
        },
        components: {
            Simplebar
        },
        setup() {
            // expose to template and other options API hooks
            return {
                Plib,
            }
        },
        data() {
            return {
                plib            : new Plib(),
                lastStatus      : null,
            }
        },
        mounted() {
            this.plib.request({
                url      : '/api/v1/dashboard/updatedstatus',
                method   : 'GET',
            },null).then(rsp => {
              
              setTimeout(() => {
                let rentWaiting = 0;
                for(let key in rsp){
                  if(key.includes('meet_rent**')) rentWaiting += parseFloat(rsp[key]);
                }
                
                this.lastStatus = {
                    supervisor     : rsp.meet_active_supervisor, 
                    supervisor_sub : rsp.meet_active_supervisor_sub,  
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
        methods: {

        }
    }

</script>


<template>
    <div :class="this.qcolclass">
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
                            <span v-if="lastStatus != null">{{ lastStatus.supervisor }}</span>
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
                            <span v-if="lastStatus != null">{{ lastStatus.supervisor_sub }}</span>
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
                        <span v-if="lastStatus != null">{{ this.plib.formatMoney(lastStatus.incomeWaiting ?? 0) + ' ' + (lastStatus.cur ?? '') }} (Aylık Beklenen) / {{ this.plib.formatMoney(lastStatus.incomeReceived ?? 0 ) + ' ' +(lastStatus.cur ?? '')}} (Toplanan)</span>
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
                        <span v-if="lastStatus != null">{{ this.plib.formatMoney(lastStatus.rentWaiting ?? 0) + ' ' + (lastStatus.cur ?? '') }} (Aylık Beklenen) / {{ this.plib.formatMoney(lastStatus.rentReceived ?? 0) + ' ' + (lastStatus.cur ?? '')}} (Toplanan)</span>
                        </div>
                        <i class="ph fs-3 ms-3 d-none d-sm-block ph-warning-octagon text-danger" v-if="(lastStatus?.rentWaiting ?? 0) > (lastStatus?.rentReceived ?? 0 )"></i>
                    </div>
                </div> 
            </div> 
        </div>
    </div>
    
</template>