<script>
import { useEventDataStore } from '@/stores/events'
export default {
    components: {},
    setup() {
        // expose to template and other options API hooks
        return {
            useEventDataStore,
        }
    },
    mounted(){
        if(this.taskDataStore.tasks.length == 0) this.taskDataStore.setTaskData();
        if(this.taskDataStore.events.length == 0) this.taskDataStore.setEventData();
    },  
    data() {
        return {
            taskDataStore   : useEventDataStore(),
        };
    },
}
</script>
<style>
.header{
    background: white;
    border: 1px solid #dbe0eb;
}
.ph{
    color: black !important;
}
</style>

<template>
    <header class="header"> 
        <button type="button" class="d-xl-none icon me-2 ms-n2.5 sidebar-toggle"> 
            <svg width="15" height="12" fill="none" class="pe-none" viewBox="0 0 16 13">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M1 1h14M1 6.25h9.546M1 11.5h14" /></svg> 
            <span class="visually-hidden">Open Sidebar</span>
        </button> 
        <a class="d-none d-sm-block logo" href="#" style="color: black;">Aydem Link Kısaltma</a> <i class="ms-auto"></i>
        <div hidden style="display: none !important;" class="content-search d-lg-flex d-none"> <i class="fs-5 ph ph-magnifying-glass"></i> <input type="text"
                class="form-control" placeholder="Search..."> </div>
        <ul class="header-menu ms-6 ms-xl-10">
            <li class="d-lg-none"> <button type="button" class="ph ph-magnifying-glass" data-bs-toggle="dropdown"
                    data-bs-display="static" data-bs-auto-close="outside" aria-expanded="false"> <span
                        class="visually-hidden">Search</span> </button>
                <div class="dropdown-menu header-dropdown-menu">
                    <div class="align-items-center d-flex flex-shrink-0 h-11 px-4">
                        <div class="fw-medium text-body-emphasis">Search</div> <button type="button"
                            class="icon me-n1 ms-auto ph ph-gear"> <span class="visually-hidden">Settings</span>
                        </button>
                    </div>
                    <div class="align-items-center d-flex mb-6 mx-n0.5 px-4"> <i
                            class="fs-5 me-n7 ms-3 ph ph-magnifying-glass position-relative"></i> <input type="text"
                            class="form-control ps-9" placeholder="Type a keyword..." aria-label="Search"> </div>
                    <div class="fs-7 mb-1 px-4 text-body-secondary">Recent Searches</div>
                    <div class="flex-grow-1 mx-n2 pb-1 px-2" data-simplebar>
                        <div id="recent-searches"></div>
                    </div>
                </div>
            </li>
            
            
            
            
            
           
            <li class="dropdown"> 
                <button type="button" class="h-11 me-n0.5 p-2 rounded w-11" data-bs-toggle="dropdown"
                    aria-expanded="false"> 
                    <img class="h-8 rounded" src="http://icons.iconarchive.com/icons/sykonist/looney-tunes/256/Taz-icon.png"> 
                </button>
                <div class="dropdown-menu"> 
                    <!--<a href="#" class="dropdown-item"> <i class="ph ph-user-circle"></i> Profile
                    </a> 
                    <a href="#" class="dropdown-item"> <i class="ph ph-currency-circle-dollar"></i> Billing </a> 
                    <a href="#" class="dropdown-item"> <i class="ph ph-gear"></i> Preferences </a> -->
                    <a href="/logout" class="dropdown-item"> <i class="ph ph-sign-out"></i> {{ $t('top.logout') }} </a> 
                </div>
            </li>
        </ul>
    </header>
</template>