
<style>
html {
    height: -webkit-fill-available; /* We have to fix html height */
}

body {
    min-height: 100vh;
    min-height: -webkit-fill-available;
}

.content-body{min-height: 82vh !important;overflow: auto !important;    max-height: 82vh;}
</style>
<script setup>
import Simplebar from 'simplebar-vue';
import 'simplebar-vue/dist/simplebar.min.css';
</script>

<script>


export default {
    data() {
      return {
        loading: useState('loadActive'),
      }
    },
    mounted : () => {
      //document.getElementById('page-loader1').hidden = true;
    },
    beforeMount: () => {
      const theme = localStorage.getItem("sa-theme") || "1";
      useHead({
        bodyAttrs: {
          "data-sa-theme": theme ?? 1
        },
        head: {
          css: ['assets/css/theme.css'],
          script: [{
            src : '/js/index.js',
            type : 'module',
            tagPosition: 'bodyClose',
          },{
            src : '/js/vendor.js',
            type : 'module',
            tagPosition: 'bodyClose',
          }]
        },
          
      });

      
    },
}
</script>

<template>

    <div id="page-loader1"  v-show="loading">
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
            <h2 class="fs-6 m-0 ps-3 ">xxx {{ loading }}</h2> <i class="ms-auto"></i>
            <nav aria-label="breadcrumb" class="d-none d-sm-flex ms-8">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Miscellaneous</a></li>
                    <li class="breadcrumb-item"><a href="#">xxxx</a></li>
                    <li class="active breadcrumb-item" aria-current="page">Content</li>
                </ol>
            </nav>
        </div>
        <div class="content-body">
        
          <Simplebar>
            <slot/>
          </Simplebar>
        </div>
    </div>
</template>






