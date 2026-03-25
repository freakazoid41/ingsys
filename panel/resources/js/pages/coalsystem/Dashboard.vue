<script>
  import { useNavigationStore } from '@/stores/navigation';
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
           
        }
      },
        mounted(){
            this.navigationStore.toggle(true);
            
            
            /*this.navigationStore.setBread([
                {
                    title : 'Ana Sayfa',
                    url   : '/panel',
                },
                {
                    title : this.wTrans('menu.home'),
                    url   : '/panel',
                }
            ] ,this.wTrans('menu.home'));*/

            /*this.navigationStore.setButtons([
                {
                icon : 'ph ph-download',
                onclick   : () => {console.log('Falan')},
                }
            ]);*/

           

            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 500);
        },  
      
        methods: {
            
            
        }
  }

</script>

<template>
    
        Hisdasdasdasda
   
  
  
        
</template>
