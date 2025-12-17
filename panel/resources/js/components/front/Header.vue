<script>
import { useNavigationStore } from '@/stores/navigation';
import { loadLanguageAsync,getActiveLanguage } from 'laravel-vue-i18n';
import Plib from '@/lib/pickle';


export default {
    components: {},
    setup() {
        // expose to template and other options API hooks
        return {
            useNavigationStore,
        }
    },
    mounted(){
        //this.authDataStore.getData();
        //if(this.taskDataStore.tasks.length == 0) this.taskDataStore.setTaskData();
        //if(this.taskDataStore.events.length == 0) this.taskDataStore.setEventData();
    },  
    data() {
        
        return {
            plib            : new Plib(),
            activeLang      : getActiveLanguage(),
            navigationStore : useNavigationStore(),
        };
    },
    methods: {
        
    }
}
</script>

<template>
    <div class="main-header">
        <div class="logo">
            <img src="/front/assets/img/logo.png" alt="">
        </div>
        <div class="detail">
            <div class="dropdown">
            <button class="btn btn-secondary dropdown-toggle lang-button" type="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                <img src="/front/assets/img/network.png" class="network" alt="">{{ navigationStore.currentLanguage.toUpperCase() }}
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" @click="navigationStore.switchLanguage('tr')" href="#">TR</a></li>
                <li><a class="dropdown-item" @click="navigationStore.switchLanguage('en')" href="#">EN</a></li>
            </ul>
            </div>
            <p>{{this.navigationStore.facility?.title}}</p>
            <small>{{this.navigationStore.facility?.address}}</small>
        </div>
    </div>
</template>