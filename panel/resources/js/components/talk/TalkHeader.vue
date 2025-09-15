<script>
import { useEventDataStore } from '@/stores/events';
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation'
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';


export default {
    components: {},
    setup() {
        // expose to template and other options API hooks
        return {
            useNavigationStore,
            useEventDataStore,
            useAuthStore,
            Plib,
            Swal
        }
    },
    mounted(){
        this.authDataStore.getData();
        if(this.taskDataStore.tasks.length == 0) this.taskDataStore.setTaskData();
        if(this.taskDataStore.events.length == 0) this.taskDataStore.setEventData();
    },  
    data() {
        return {
            plib            : new Plib(),
            taskDataStore   : useEventDataStore(),
            navigationStore : useNavigationStore(),
            authDataStore   : useAuthStore(),
            title           : document.querySelector('input[name="header"]').value
        };
    },
    methods: {
        
    }
}
</script>

<template>
    <div class="right-bar-header">
        <div class="right-bar-header-head">
            <h1 v-html="navigationStore?.currentTitle == '' ? 'Seç Sistemine <b>Hoş geldiniz.</b>' : navigationStore?.currentTitle"></h1>  
            <ul class="breadcrumb">
                <li v-for="item in navigationStore.breadcrumps">
                    <a :href=item.url>{{ item.title }}</a>
                </li>
            </ul>
        </div>
        <div class="right-bar-header-menu">
            <button class="h-button notification"><span class="kontent-icon" name="Notification"></span><span class="ntf"></span></button> 
            <a href="" alt="" class="h-button settings"><span class="kontent-icon" name="Setting"></span></a> 
            <div class="user-management h-button">
                <button>
                    <span><span class="kontent-icon" name="HeaderUser"></span></span>
                    <p>{{ authDataStore?.data?.ptitle }}<small>{{ authDataStore?.data?.type_key == 'op-pert-admin' ? 'Yönetici' : 'Normal Kullanıcı' }}</small></p>
                </button>
                <div class="user-management-detail">
                    <span>sasd</span>
                </div>
            </div>
            <a href="/logout" alt="" class="h-button logout"><span class="kontent-icon" name="Logout"></span></a>
            <button class="mobileButton"><span class="kontent-icon" name="MobileButton"></span></button>
        </div>
    </div>
</template>