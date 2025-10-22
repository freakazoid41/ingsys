<script>
    import { useNavigationStore } from '@/stores/navigation';
    import Plib from '@/lib/pickle';
    import FrontLib from '@/lib/frontlib';
    import { wTrans } from 'laravel-vue-i18n';


    export default {
        components: {
           
        },
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                FrontLib,
                Plib,
                wTrans
            }
        },
        mounted() {
            this.inventories  = this.navigationStore.facility.inventories;
        },  
        data() {
            return {
                inventories     : {},
                hasMissing      : {},
                hasGiven        : {},
                plib            : new Plib(),
                frontLib        : new FrontLib(),
                navigationStore : useNavigationStore(),
            }
        },
        methods: {
            selectAll(e){
                const missingItems = document.querySelectorAll('.minv-item');
                if(missingItems.length > 0){
                    missingItems.forEach(el => {
                        el.checked = e.target.checked;
                        this.checkMissings({target : el});
                    })
                }
            },
            checkMissings(e){
                if(!e.target.checked){
                    delete this.hasGiven[e.target.dataset.id];
                }else{
                    this.hasGiven[e.target.dataset.id] = 'Given by us..';
                }
            },
            async enterFacility(){
                //here first check if all equipment are owned
                const items = document.querySelectorAll('.inv-item');
                let   invItems = {};
                items.forEach((el,i) => {
                    if(el.checked){
                        const key = "visitorinvgroup**"+((new Date()).getTime()+i)+"-"+i;
                        invItems["inventory**"+key]   = el.dataset.title;
                        invItems["description**"+key] = el.dataset.code;
                    }else{
                        this.hasMissing[el.dataset.id] = el.dataset;
                    }   
                });

                //do not pass if missing equipment exist
                if(Object.keys(this.hasMissing).length > 0 && Object.keys(this.hasGiven).length != Object.keys(this.hasMissing).length) return false;
                 



                //get selected inventories
                invItems['inventory-taken'] = true;
                invItems['entered_at']      = this.plib.getConvertedDate(new Date());

                this.navigationStore.toggle(true);
                //save selections
                let envelope  = new FormData();
                envelope.append('data',JSON.stringify({
                    "dynamicF" : {
                        [this.navigationStore.currentUser.conn] : {
                            "entities":invItems,
                            "tag":"op-doc-visit-form"
                        }
                    }
                }));
                

                await this.plib.request({
                    url      : '/api/v1/yeniziyaret/'+this.navigationStore.currentUser.qnid,
                    method   : 'PUT',
                },null,envelope);

                //here add given equipment from factory

                const missingItems = document.querySelectorAll('.minv-item');
                if(missingItems.length > 0){
                    invItems = {};
                    missingItems.forEach((el,i) => {
                        if(el.checked){
                            const key = "givengroup**"+((new Date()).getTime()+i)+"-"+i;
                            invItems["inventory**"+key]   = el.dataset.title;
                            invItems["description**"+key] = el.dataset.code;
                        } 
                    });

                    envelope  = new FormData();
                    envelope.append('data',JSON.stringify({
                        "dynamicF" : {
                            [this.navigationStore.currentUser.conn] : {
                                "entities":invItems,
                                "tag":"op-doc-visit-form"
                            }
                        }
                    }));

                    await this.plib.request({
                        url      : '/api/v1/yeniziyaret/'+this.navigationStore.currentUser.qnid,
                        method   : 'PUT',
                    },null,envelope);
                }

                this.navigationStore.toggle(false);

                this.frontLib.popup({
                    text: {
                        class : 'login-popup',
                        head: '',
                        body: `
                            <div class="view">
                                <div class="icon">
                                <img src='/front/assets/img/successCheckGreen.svg'>
                                <p>`+this.wTrans("inv.canenter").value+`</p>
                                </div>
                                <div class="info-box">
                                <div class="info-box-head"><img src='/front/assets/img/purpleCirc.svg'>`+this.wTrans("inv.canenter.greet").value+`</div>
                                <p>`+this.wTrans("inv.canenter.warn").value+`</p>
                                </div>
                            </div>
                        `,
                        
                    },
                    items: 'kvkk-choice'
                });
                document.querySelector("button.close").remove();
            }
        }
    }
</script>
<template>
    <div class="main-body iwarn-body">
        <div class="main-rew-head">
            <h3>{{ $t('invlist.header') }}</h3>
        </div>
        <div class="check-list mt-4">
            <div class="form-check" v-for="value,key in inventories" v-show="
                    this.navigationStore.currentLanguge != 'tr' ? (value?.['title--lng--'+this.navigationStore.currentLanguge] !== undefined) : (value?.title !== undefined)
                ">
                <div class="checkbox-theme md">
                    <input type="checkbox" :data-code="value.code" :data-id="key" :data-title="value.title" class="inv-item" :id="'inv'+key " :name="'inv'+key " :value="value.qnid" />
                    <label :for="'inv'+key ">
                    <svg viewBox="0,0,50,50">
                        <path d="M5 30 L 20 45 L 45 5"></path>
                    </svg>
                    </label>
                </div>
                <label class="form-check-label text-dark" :for="'inv'+key ">{{ this.navigationStore.currentLanguge != 'tr' ? value['title--lng--'+this.navigationStore.currentLanguge] : value.title }}</label>
            </div>
        </div>
        <div class="alert-group-box" v-if="Object.keys(hasMissing).length > 0">
            <h5>{{ $t('invlist.warnh') }}</h5>
            <p>{{ $t('invlist.warnd') }}</p>
            <div class="form-check" v-for="value,key in inventories" v-show="
                    this.navigationStore.currentLanguge != 'tr' ? (value?.['title--lng--'+this.navigationStore.currentLanguge] !== undefined) : (value?.title !== undefined)
                ">
                <div class="checkbox-theme md"  v-if="hasMissing[key] !== undefined">
                    <input type="checkbox" @click="checkMissings($event)" :data-code="value.code" :data-id="key" :data-title="value.title" class="minv-item" :id="'minv'+key " :name="'minv'+key " :value="value.qnid">
                    <label :for="'minv'+key ">
                    <svg viewBox="0,0,50,50">
                        <path d="M5 30 L 20 45 L 45 5"></path>
                    </svg>
                    </label>
                </div>
                <label v-if="hasMissing[key] !== undefined" class="form-check-label text-dark" for="isguvenligi7">{{ this.navigationStore.currentLanguge != 'tr' ? value['title--lng--'+this.navigationStore.currentLanguge] : value.title }}*</label>
            </div>
        </div>
    </div>
    <div class="main-footer twin-button iwarn-footer" >
        <div class="form-check mt-auto mb-3" >
            <div class="checkbox-theme md ck-inv" v-if="Object.keys(hasMissing).length > 0">
                <input type="checkbox" id="gurultu5" name=""  @click="selectAll($event)">
                <label for="gurultu5">
                    <svg viewBox="0,0,50,50">
                    <path d="M5 30 L 20 45 L 45 5"></path>
                    </svg>
                </label>
            </div>
            <label class="form-check-label" v-if="Object.keys(hasMissing).length > 0" for="gurultu5"><b>{{ $t('invlist.check') }}</b></label>
        </div>
        <div class="d-flex w-100 gap-3">
            <button class="button-theme outline back-custom" @click="this.$router.push({ name: 'IWarn', params: { id: this.navigationStore.facility.qr } })">
                <img src="/front/assets/img/leftArrow.svg" alt="">
            </button>
            <button class="button-theme resume-button w-100" @click="enterFacility">
                {{ $t('inv.button') }} <img src="/front/assets/img/rightArrow.png" alt="">
            </button>
        </div>
        
    </div>
</template>