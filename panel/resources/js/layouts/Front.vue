<script>

import { useNavigationStore } from '@/stores/navigation';
import { useHead } from '@unhead/vue'
import FrontHeader from '@/components/front/Header.vue';
import FrontLib from '@/lib/frontlib';

export default {
    components: {
      FrontHeader,
        
    },
    setup() {
        // expose to template and other options API hooks
        return {
            useNavigationStore,
            useHead,
            FrontLib,
        }
    },
    data() {
      return {
        frontLib        : new FrontLib(),
        time            : (new Date()).toLocaleTimeString('tr', {hour: '2-digit', minute:'2-digit', hour12: false}),
        navigationStore : useNavigationStore(),
      }
    },
    
    methods : {
      updateTime(){
        let self = this;
        setInterval(() => {
          self.time = (new Date()).toLocaleTimeString('tr', {hour: '2-digit', minute:'2-digit', hour12: false})
        }, 1000);
      }
    },
    mounted() {
      this.navigationStore.toggle(true);
      this.navigationStore.getFacility().then(rsp => {
          if(!rsp){
              this.frontLib.popup({
                  text: {
                      class : 'error-popup',
                      head: 'Üzgünüz..',
                      body: `<h4>Aradığınız Tesis Mevcut Değildir..</h4> <div class="view"><div class="icon"><img src='/front/assets/img/expl.svg'></div></div>`,
                      button: {
                      name: 'Görevli İle Görüşünüz..',
                          proccess: () => {}
                      }
                  },
                  items: 'kvkk-choice'
              });
              document.querySelector('button.close').remove();
              this.navigationStore.toggle(false);
          }

          setTimeout(() => {
              this.navigationStore.toggle(false);
          }, 300);
          
      });
    
      this.updateTime();
      //document.body.dataset.saTheme = localStorage.getItem("sa-theme");
    },

}
</script>
<template>
    <div id="page-loader1"  v-show="navigationStore.active">
          <div class="h-12 spinner-border w-12" role="status"> <span class="visually-hidden">Loading...</span> </div>
    </div>
    <div class="main">
        <FrontHeader/>
        <router-view :key="$route.path"></router-view>
    </div>
    <div class="time-period">
        <p class="clock">{{time  }}</p>
        <p class="date">{{ (new Date()).toLocaleDateString() }}</p>
    </div>
</template>





