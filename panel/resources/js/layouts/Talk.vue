<script>
import Simplebar from 'simplebar-vue';
import 'simplebar-vue/dist/simplebar.min.css';
import TalkSidebar from '@/components/talk/TalkSidebar.vue';
import TalkHeader from '@/components/talk/TalkHeader.vue';
import { useNavigationStore } from '@/stores/navigation';
import { useHead } from '@unhead/vue'

export default {
    components: {
        TalkSidebar,
        TalkHeader,
        Simplebar
    },
    setup() {
        // expose to template and other options API hooks
        return {
            useNavigationStore,
            useHead,
        }
    },
    data() {
      return {
        navigationStore: useNavigationStore(),
      }
    },
    mounted : () => {
      //document.body.dataset.saTheme = localStorage.getItem("sa-theme");
    },
    beforeMount: () => {
      //const theme = localStorage.getItem("sa-theme") || "1";
      useHead({
        bodyAttrs: {
          "class": 'admin'
        },
      });
    },
}
</script>
<template>
    <div id="page-loader1"  v-show="navigationStore.active">
          <div class="h-12 spinner-border w-12" role="status"> <span class="visually-hidden">Loading...</span> </div>
    </div>
     <div class="app">
       
        <div class="left-bar"><!-- small -->
            <TalkSidebar/>
        </div>
     
        <div class="right-bar">
            <TalkHeader/>
            <Simplebar>
                <router-view :key="$route.path"></router-view>
            </Simplebar>
        </div>
    </div>
    <!--<div id="page-loader1"  v-show="navigationStore.active">
        <div class="h-12 spinner-border w-12" role="status"> <span class="visually-hidden">Loading...</span> </div>
    </div>
    <AppHeader/>
    <aside id="sidebar">
      <Simplebar>
        <AppSidebar/>
      </Simplebar>
    </aside>
    <div id="content" class="main-div">
        <div class="content-header">
            <h2 class="fs-6 m-0 ps-3 text-body-emphasis">{{ navigationStore.currentTitle }}</h2>
            <nav aria-label="breadcrumb" class="d-none d-sm-flex ms-8">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item" v-for="item in navigationStore.breadcrumps">
                      <a :href=item.url>{{ item.title }}</a>
                    </li>
                </ol>
            </nav>
            <i class="ms-auto"></i>
            
              
            <div class="align-items-center d-flex gap-1 ms-3"> 
              <a href="javascript:;" class="icon icon-subtle" :class="item.icon" @click="item.onclick" v-for="item in navigationStore.breadbuttons"></a> 
             
            </div>
        </div>
        <div class="content-body">
        
          <Simplebar>
            <router-view :key="$route.path"></router-view>
          </Simplebar>
        </div>
    </div>-->
</template>





