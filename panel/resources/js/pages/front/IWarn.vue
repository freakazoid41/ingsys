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
            this.inventories  = this.navigationStore.facility.invdetail;
            
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
            async selectInventory(type = 'facility'){
                this.$router.push({ name: (type == 'facility' ? 'IGiven' : 'IOwned') , params: { id  : this.navigationStore.facility.qr }});
            }
        }
    }
</script>
<template>
    <div class="main-body iwarn-body">
        <div class="main-rew-head">
            <h3>{{ $t('invwarn.header') }}</h3>
            <p class="fs-14" v-html="$t('invwarn.warn')"></p>
        </div>
        <div class="item-group-list">
            <h5 class="item-group-list-head"><img src="/front/assets/img/page20i.svg" class="icon"> {{ $t('inv.header') }}</h5>
            <ul class="list">
                <li v-for="value,key in inventories">
                    * {{ inventories[key][this.navigationStore.currentLanguage != 'tr' ? 'title--lng--'+this.navigationStore.currentLanguage : 'title'] }} 
                </li>
               
            </ul>
        </div>
    </div>
    <div class="main-footer iwarn-footer" >
        <button class="button-theme body-custom w-100 " @click="selectInventory('owned')">{{ $t('invwarn.button') }}</button>
        <button class="button-theme resume-button w-100" @click="selectInventory('facility')">
            {{ $t('invwarn.enterbutton') }} <img src="/front/assets/img/rightArrow.png" alt="">
        </button>
    </div>
</template>