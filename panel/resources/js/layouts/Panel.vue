
<style>
html {
    height: -webkit-fill-available !important; /* We have to fix html height */
}

body {
    min-height: 100vh !important;
    min-height: -webkit-fill-available !important;
    background:  rgb(245, 247, 249) !important;
}

.content-body{min-height: 82vh !important;overflow: auto !important;    max-height: 82vh;}
.card{
    /*background: linear-gradient(45deg,#e45d0b 26%,#ffde59 128%) !important*/
    background: white !important;
    box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
}
</style>

<script setup>
  import Simplebar from 'simplebar-vue';
  import 'simplebar-vue/dist/simplebar.min.css';
  import AppSidebar from '@/components/AppSidebar.vue'
  import AppHeader from '@/components/AppHeader.vue'
  import { useNavigationStore } from '@/stores/navigation'
</script>

<template>

    <div id="page-loader1"  v-show="navigationStore.active">
        <div class="h-12 spinner-border w-12" role="status"> <span class="visually-hidden">Loading...</span> </div>
    </div>
    <AppHeader/>
    <aside id="sidebar">
      <Simplebar>
        <AppSidebar/>
      </Simplebar>
    </aside>
    <div id="content">
        <div class="content-header">
            <h2 class="fs-6 m-0 ps-3 text-body-emphasis" style="color:black !important">{{ navigationStore.currentTitle }}</h2>
            <nav aria-label="breadcrumb" class="d-none d-sm-flex ms-8">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item" v-for="item in navigationStore.breadcrumps">
                      <a :href=item.url>{{ item.title }}</a>
                    </li>
                </ol>
            </nav>
            <i class="ms-auto"></i>
            <!--<div class="d-md-flex d-none date-range-picker date-range-picker-body fs-7"> 
              <i class="fs-3 me-2 ph ph-clock position-relative"></i> 
              <input type="text" name="start" value="07/10/2023" class="form-control-plaintext w-20 datepicker-input" required="" readonly="">
            </div>-->
              
            <div class="align-items-center d-flex gap-1 ms-3"> 
              <a href="javascript:;" class="icon icon-subtle" :class="item.icon" @click="item.onclick" v-for="item in navigationStore.breadbuttons"></a> 
              <!--<a href="" class="icon icon-subtle ph ph-download"></a> 
              <a href="" class="icon icon-subtle ph ph-gear"></a> -->
            </div>
        </div>
        <div class="content-body">
        
          <Simplebar>
            <router-view :key="$route.path"></router-view>
          </Simplebar>
        </div>
    </div>
</template>


<script>

import { useHead } from '@unhead/vue'
export default {
    data() {
      return {
        navigationStore: useNavigationStore(),
      }
    },
    mounted : () => {
      document.body.dataset.saTheme = localStorage.getItem("sa-theme");
    },
    beforeMount: () => {
      const theme = localStorage.getItem("sa-theme") || "1";
      useHead({
        bodyAttrs: {
          "data-sa-theme": theme ?? 1
        },
      });
    },
}
</script>



