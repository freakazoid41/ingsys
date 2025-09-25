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
            
        },  
        data() {
            return {
                plib            : new Plib(),
                frontLib        : new FrontLib(),
                navigationStore : useNavigationStore(),
            }
        },
        methods: {
           
        }
    }
</script>
<template>
    
    <div class="main-body logout-main">
      <img src="/front/assets/img/checkSuccess.png" class="success-img" alt="">
      <h2 v-html="$t('exit.exithappend')"></h2>
      <p v-html="$t('exit.exitmsg')"></p>

    </div>
</template>