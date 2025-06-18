<script>

    import Plib from '@/lib/pickle';
    import Simplebar from 'simplebar-vue';
    import 'simplebar-vue/dist/simplebar.min.css';
    import { wTrans } from 'laravel-vue-i18n';

    export default {
        props: {
            qtype : {
                type: String
            },
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
                incomeWaiting   : null,
                header          : wTrans(this.qtype == 'doc_acc_fuel' ? 'dashboard.incomewaiting.fuel' : 'dashboard.incomewaiting')
            }
        },
        mounted() {
            this.plib.request({
                url      : '/api/v1/dashboard/incomestatus/'+this.qtype,
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
        },
        methods: {

        }
    }

</script>


<template>
    <div :class="this.qcolclass">
        <div class="card h-100" >
            <div class="card-body" > 
                <div class="align-items-start d-flex mb-5"> 
                    <h5 class="card-title flex-grow-1 m-0"> {{ header }} </h5> 
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
</template>