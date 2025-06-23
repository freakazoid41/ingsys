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

<template>
    <header class="header"> 
        <button type="button" class="d-xl-none icon me-2 ms-n2.5 sidebar-toggle"> 
            <svg width="15" height="12" fill="none" class="pe-none" viewBox="0 0 16 13">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M1 1h14M1 6.25h9.546M1 11.5h14" /></svg> 
            <span class="visually-hidden">Open Sidebar</span>
        </button> 
        <a class="d-none d-sm-block logo" href="#">Körfez Apt.</a> <i class="ms-auto"></i>
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
            <li class="dropdown" :class="{
                'header-notify' : this.taskDataStore.events.length > 0
            }"> 
                <button type="button" class="ph ph-bell" data-bs-toggle="dropdown"
                    data-bs-display="static" data-bs-auto-close="outside" aria-expanded="false"> 
                    <span
                        class="visually-hidden">{{ $t('dashboard.notifications') }}</span> 
                </button>
                <div class="dropdown-menu header-dropdown-menu">
                    <div class="align-items-center d-flex flex-shrink-0 h-11 px-4">
                        <div class="fw-medium text-body-emphasis">{{ $t('dashboard.notifications') }}</div>
                        <div class="d-flex gap-px me-n2 ms-auto"> 
                            <button hidden type="button" class="icon ph ph-check-circle">
                                <span class="visually-hidden">Mark as read</span> 
                            </button> 
                            <button hidden type="button"
                                class="icon ph ph-app-window"> <span class="visually-hidden">Open Notifications</span>
                            </button> 
                            <a href="/panel/calendar" type="button" class="icon ph ph-gear"> <span
                                class="visually-hidden">Settings</span> 
                            </a> 
                        </div>
                    </div>
                    <div class="flex-grow-1 pb-1 px-1" data-simplebar>
                        <div id="top-notifications1">
                            <a v-for="event in this.taskDataStore.events" :href="'/panel/calendar/form/'+event.id" class="bg-hover-inverse d-flex align-items-center py-2 px-4 rounded">
                                <div class="w-9 h-9 rounded-circle d-grid place-content-center me-3 text-white">
                                    <i class="ph fs-3 bg-opacity-50 ph-calendar w-9 h-9 rounded-circle d-grid place-content-center me-3 text-white bg-success"></i>
                                </div>

                                <div class="flex-grow-1">
                                    <div class="text-body-emphasis">{{ event.title  }}</div>
                                    <div class="fs-7 text-body-secondary text-opacity-75">{{ event.start_date.split('-').reverse().join('.') + ' / ' + event.end_date.split('-').reverse().join('.') }}</div>
                                </div>

                                <i class="w-1.5 h-1.5 rounded-circle mb-4 bg-white"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </li>
            <li class="d-none d-sm-block dropdown"> <button type="button" class="ph ph-check-square-offset"
                    data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="outside"
                    aria-expanded="false"> <span class="visually-hidden">Tasks</span> </button>
                <div class="dropdown-menu header-dropdown-menu">
                    <div class="align-items-center d-flex flex-shrink-0 h-11 px-4">
                        <div class="fw-medium text-body-emphasis">{{$t('dashboard.ongoingtasks')}}</div>
                        <div class="d-flex gap-px me-n2 ms-auto"> 
                            <a href="/panel/projects" type="button" class="icon ph ph-app-window"> <span class="visually-hidden">{{$t('dashboard.ongoingtasks')}}</span> </a>
                        </div>
                    </div>
                    <div class="flex-grow-1 mx-n2 pt-2 px-2" data-simplebar>
                        <div id="top-tasks1">
                            <div class="py-1">
                                <div class="form-check mb-5" v-for="task in this.taskDataStore.tasks">
                                    <div class="mb-1 text-truncate text-body-emphasis d-flex justify-content-between align-items-center">
                                        {{task.title}}
                                        <a :href="'/panel/projects/form/'+task.id" type="button" class="icon ph ph-gear"> <span class="visually-hidden">Settings</span> </a>
                                    </div>
                                    
                                    <div class="d-flex align-items-center justify-content-between text-body-secondary">
                                        <div class="fw-medium">
                                            {{task.start_date.split('-').reverse().join('/')}}
                                            -
                                            {{task.end_date.split('-').reverse().join('/')}}
                                        </div>
                                        <div class="badge bg-opacity rounded-pill truncate"
                                            :class="{
                                                'text-danger bg-danger'   : task.status.split('**')[0] == 'doc_trans_project_sikinti',
                                                'text-success bg-success' : task.status.split('**')[0] == 'doc_trans_project_end',
                                                'text-primary bg-primary' : task.status.split('**')[0] == 'doc_trans_project_payment',
                                                'text-warning bg-warning' : task.status.split('**')[0] == 'doc_trans_project_start',
                                                'text-info bg-info'       : ['doc_trans_created','doc_file_waiting'].includes(task.status.split('**')[0])
                                            }"
                                            style="--bs-bg-opacity: 0.2">
                                            {{['doc_trans_created','doc_file_waiting'].includes(task.status.split('**')[0]) ? 'Bekleniyor...' :  task.status.split('**')[1]}}
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
            <li class="d-none d-sm-block dropdown"> 
                <button type="button" class="ph ph-squares-four"
                    data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="outside"
                    aria-expanded="false"> <span class="visually-hidden">Shortcuts</span> 
                </button>
                <div class="dropdown-menu header-dropdown-menu">
                    <!--<div class="align-items-center d-flex flex-shrink-0 h-11 px-4">
                        <div class="fw-medium text-body-emphasis">Shortcuts</div>
                        <div class="d-flex gap-px me-n2 ms-auto"> <button type="button" class="icon ph ph-plus-circle">
                                <span class="visually-hidden">Add new</span> </button> <button type="button"
                                class="icon ph ph-gear"> <span class="visually-hidden">Settings</span> </button> </div>
                    </div>-->
                    <div class="flex-grow-1 pt-2" data-simplebar>
                        <div id="top-shortcut" class="g-3 px-4 row row-cols-3">
                            <a href="/panel/calendar" class="d-block text-white">
                                <div class="bg-hover-inverse rounded text-center p-3">
                                    <i class="ph ph-calendar fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                                    <span class="d-block lh-1 mt-2 fs-7">{{$t('menu.calendar')}}</span>
                                </div>
                            </a>
                            <a href="/panel/contacts" class="d-block text-white">
                                <div class="bg-hover-inverse rounded text-center p-3">
                                    <i class="ph ph-address-book fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                                    <span class="d-block lh-1 mt-2 fs-7">{{$t('menu.contacts')}}</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </li>
            <li class="d-none d-sm-block dropdown"> <button type="button" class="ph ph-palette"
                    data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="outside"
                    aria-expanded="false"> <span class="visually-hidden">Theme Switch</span> </button>
                <div class="dropdown-menu header-dropdown-menu">
                    <div class="align-items-center d-flex flex-shrink-0 h-11 px-4">
                        <div class="fw-medium text-body-emphasis">Theme Switch</div>
                        <div class="d-flex gap-px me-n2 ms-auto"> <button type="button"
                                class="icon ph ph-arrow-counter-clockwise"> <span class="visually-hidden">Reset</span>
                            </button> </div>
                    </div>
                    <div class="flex-grow-1 mx-n2 pt-2 px-2" data-simplebar>
                        <div id="top-themes" class="d-flex flex-column gap-3 pb-4 px-4"></div>
                    </div>
                </div>
            </li>
            
           
            <li class="dropdown"> 
                <button type="button" class="h-11 me-n0.5 p-2 rounded w-11" data-bs-toggle="dropdown"
                    aria-expanded="false"> 
                    <img class="h-8 rounded" src="http://icons.iconarchive.com/icons/sykonist/looney-tunes/256/Taz-icon.png"> 
                </button>
                <div class="dropdown-menu"> 
                    <a href="#" class="dropdown-item"> <i class="ph ph-user-circle"></i> Profile
                    </a> 
                    <a href="#" class="dropdown-item"> <i class="ph ph-currency-circle-dollar"></i> Billing </a> 
                    <a href="#" class="dropdown-item"> <i class="ph ph-gear"></i> Preferences </a> 
                    <a href="/logout" class="dropdown-item"> <i class="ph ph-sign-out"></i> {{ $t('top.logout') }} </a> 
                </div>
            </li>
        </ul>
    </header>
</template>