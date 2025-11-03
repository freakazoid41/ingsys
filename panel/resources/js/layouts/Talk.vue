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
  mounted: () => {
    //document.body.dataset.saTheme = localStorage.getItem("sa-theme");
    document.querySelector('.mobileButton').addEventListener('click', function () {
      document.querySelector(".left-bar").classList.toggle("show");
    });

    document.querySelector('.menu-hide').addEventListener('click', function () {
      document.querySelector(".left-bar").classList.toggle("show");
    });

    
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
  <div id="page-loader1" v-show="navigationStore.active">
    <div class="h-12 spinner-border w-12" role="status"> <span class="visually-hidden">Loading...</span> </div>
  </div>
  <div class="app">

    <div class="left-bar"><!-- small -->
      <TalkSidebar />
      <button class="menu-hide"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="#262626"><path d="M183.1 137.4C170.6 124.9 150.3 124.9 137.8 137.4C125.3 149.9 125.3 170.2 137.8 182.7L275.2 320L137.9 457.4C125.4 469.9 125.4 490.2 137.9 502.7C150.4 515.2 170.7 515.2 183.2 502.7L320.5 365.3L457.9 502.6C470.4 515.1 490.7 515.1 503.2 502.6C515.7 490.1 515.7 469.8 503.2 457.3L365.8 320L503.1 182.6C515.6 170.1 515.6 149.8 503.1 137.3C490.6 124.8 470.3 124.8 457.8 137.3L320.5 274.7L183.1 137.4z"/></svg></button>
    </div>

    <div class="right-bar">
      <TalkHeader />
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
