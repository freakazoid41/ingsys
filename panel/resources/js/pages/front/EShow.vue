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
            this.navigationStore.getFacility().then(rsp => {
                if(rsp) this.inventories  = this.navigationStore.facility.inventories;
            });
        },  
        data() {
            return {
                inventories     : {},
                plib            : new Plib(),
                frontLib        : new FrontLib(),
                navigationStore : useNavigationStore(),
            }
        },
        methods: {
            async exitFacility(){
                if(document.getElementById('teslim1').checked){
                    
                
                    //get selected inventories
                    const items = document.querySelectorAll('.inv-item:checked');
                    let   invItems = {};

                    items.forEach((el,i) => {
                        const key = "revievedgroup**"+((new Date()).getTime()+i)+"-"+i;
                        invItems["inventory**"+key] = el.dataset.title;
                        invItems["description**"+key] = el.dataset.code;
                    });

                    invItems["inventory-given"] = true;
                    invItems["exited_at"]       = this.plib.getConvertedDate(new Date());

                    this.navigationStore.toggle(true);
                    //save selections
                    const envelope  = new FormData();
                    envelope.append('data',JSON.stringify({
                        "dynamicF" : {
                            [this.navigationStore.currentUser.conn] : {
                                "entities" : invItems,
                                "tag"      : "op-doc-visit-form"
                            }
                        }
                    }));
                    

                    const response = await this.plib.request({
                        url      : '/api/v1/yeniziyaret/'+this.navigationStore.currentUser.qnid,
                        method   : 'PUT',
                    },null,envelope);

                    this.navigationStore.toggle(false);

                    this.$router.push({ name: 'EMessage' , params: { id  : this.navigationStore.facility.qr }});
                }else{
                    this.frontLib.popup({
                        text: {
                            class : 'login-popup',
                            head: '',
                            body: `
                                <div class="view" style="height:unset !important">
                                    <div class="info-box">
                                        <div class="info-box-head"><img src='/front/assets/img/yellowCirc.svg'>`+this.wTrans('inv.canenter.greet').value+`</div>
                                        <p>`+this.wTrans('exit.signwarn').value+`</p>
                                    </div>
                                </div>
                            `,
                            
                        },
                        items: 'kvkk-choice'
                    });
                }
                
                    
            }
        }
    }
</script>
<template>
    <div class="main-body">
        <div class="main-rew-head">
            <img src="/front/assets/img/logout.png" class="icon" alt="">
            <h3>{{ $t('exit.invheader') }}</h3>
            <p>{{ $t('exit.invwarn') }}</p>
        </div>
        <div class="check-list mt-4">
            <div class="form-check" v-for="value,key in inventories" v-show="
                    this.navigationStore.currentLanguge != 'tr' ? (value?.['title--lng--'+this.navigationStore.currentLanguge] !== undefined) : (value?.title !== undefined)
                ">
                <div class="checkbox-theme md">
                    <input type="checkbox" :data-code="value.code" :data-title="value.title" class="inv-item" :id="'inv'+key " :name="'inv'+key " :value="value.qnid" />
                    <label :for="'inv'+key ">
                    <svg viewBox="0,0,50,50">
                        <path d="M5 30 L 20 45 L 45 5"></path>
                    </svg>
                    </label>
                </div>
                <label class="form-check-label text-dark" :for="'inv'+key ">{{ this.navigationStore.currentLanguge != 'tr' ? value['title--lng--'+this.navigationStore.currentLanguge] : value.title }}</label>
            </div>
            <hr>
            <div class="form-check">
                <div class="checkbox-theme">
                    <input type="checkbox" id="teslim1" name="" />
                    <label for="teslim1">
                    <svg viewBox="0,0,50,50">
                        <path d="M5 30 L 20 45 L 45 5"></path>
                    </svg>
                    </label>
                </div>
                <label class="form-check-label" for="teslim1">{{ $t('exit.invsign') }}</label>
            </div>
        </div>
       
    </div>
    <div class="main-footer twin-button" >
        <button class="button-theme resume-button w-100" @click="exitFacility">
            {{ $t('exit.exitbutton') }} <img src="/front/assets/img/rightArrow.png" alt="">
        </button>
    </div>
</template>