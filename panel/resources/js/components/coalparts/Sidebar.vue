
<script>
import { useAuthStore } from '@/stores/auth';
import { usePermissionDataStore } from '@/stores/permissiondata';
import Simplebar from 'simplebar-vue';
import 'simplebar-vue/dist/simplebar.min.css';

export default {
    components: {
        Simplebar
    },
    setup() {
        // expose to template and other options API hooks
        return {
            useAuthStore,
        }
    },
    async mounted(){
        await useAuthStore().getPermissions();
        //await usePermissionDataStore().fetchRoleTemplates();
        this.toggleSidebar();
    },  
    data() {
        return {
            useAuthStore   : useAuthStore(),
            title          : document.querySelector('input[name="header"]').value
        };
    },
    methods: {
        toggleSidebar(){
            
            let url = window.location.href.toString().split(window.location.host)[1].split('/');
            url = window.location.protocol+'//'+window.location.host+'/'+url[1]+(url[2] !== undefined ? '/'+url[2] : '')+(url[3] !== undefined ? '/'+url[3] : '');

            document.querySelectorAll('.menu-link').forEach(el => {
                if(el.href !== undefined && el.href == url){
                    
                    if(el.querySelector('.menu-title') !== null){
                        el.querySelector('.menu-title').classList.add('active-link');
                        el.querySelector('.menu-title').scrollIntoView();
                    } 
                    if(el.querySelector('.menu-section') !== null) el.querySelector('.menu-section').classList.add('active-link');

                    

                    document.querySelectorAll('span.menu-link').forEach(el => el.classList.remove('active'));
                    const mainRow = el.closest('.menu-row-main');
                    mainRow.querySelector('.menu-link')?.classList.add('active');
                    mainRow.querySelector('.menu-link')?.querySelector('.container-hamburger')?.classList.add('change');
                    mainRow.querySelector('.sub-menu')?.classList.remove('hidden-menu');
                }
            });

            document.querySelectorAll('.main-menu').forEach(item => {
                item.addEventListener('click',e => {
                    e.target.querySelector('.container-hamburger').classList.toggle('change');
                    e.target.parentNode.querySelector('.sub-menu').classList.toggle('hidden-menu');
                });
            });
        }
    }
}

</script>




        
        

<template>
    <div id="kt_aside" class="aside display-flex justify-content-between" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="auto" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_toggle">
        <div class="aside-logo flex-column-auto pt-10 pt-lg-20" id="kt_aside_logo">
            <a href="https://yts.gdzelektrik.com.tr/dashboard">
                <img alt="Logo" src="/system/front/media/logos/gdz-logo.svg" class="h-50px">
            </a>
        </div>
        <h1 class="d-flex flex-column text-center fw-bold mt-5 px-lg-5">
            <span class="text-primary fs-5">Kömür Tedarik Sistemi</span>
        </h1>
        <Simplebar>
        <div class="aside-menu flex-column-fluid pt-0 pb-7 py-lg-10" id="kt_aside_menu">
            <div class="w-100 hover-scroll-y scroll-lg-ms d-flex" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: trur}" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer" data-kt-scroll-wrappers="#kt_aside, #kt_aside_menu" data-kt-scroll-offset="0">
                <div id="kt_aside_menu" class="menu menu-column menu-title-gray-600 menu-state-primary menu-state-icon-primary menu-state-bullet-primary menu-icon-primary menu-arrow-primary fw-semibold fs-6 my-auto" data-kt-menu="true">
                    <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="right-start" class="menu-item py-2 menu-row-main">
                        <span class="menu-link main-menu px-3">
                            <span class="menu-icon me-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="21.248" height="20.702" viewBox="0 0 21.248 20.702">
                                    <path id="Union_6" data-name="Union 6" d="M17.095,19.1l-4.52-4.523-.006-.005a.357.357,0,0,1,.006-.5l.141-.141L8.988,10.2,10.3,8.882l.513-.514L14.549,12.1c.165-.208.419-.359.647-.14l4.523,4.519a1.809,1.809,0,0,1,.528,1.281,1.907,1.907,0,0,1-1.92,1.869A1.687,1.687,0,0,1,17.095,19.1ZM.35,18.392a.362.362,0,0,1,0-.723H.726v-.835a1.48,1.48,0,0,1,1.479-1.482H8.948a1.484,1.484,0,0,1,1.483,1.482v.835H10.8a.361.361,0,0,1,0,.723ZM5.2,13.542.943,9.284A1.107,1.107,0,0,1,2.508,7.718c.175.173.619.621.8.8l2.658,2.657c.39.455,1.139.892,1.117,1.565a1.119,1.119,0,0,1-1.884.8ZM3.308,7.5,8.114,2.692l3.68,3.681c-1.5,1.5-3.306,3.3-4.805,4.805Zm9.281-1.348c-.175-.172-.619-.621-.8-.8L9.132,2.692c-.177-.18-.624-.622-.8-.8A1.109,1.109,0,0,1,9.9.324l4.259,4.259a1.109,1.109,0,0,1-1.569,1.566Z" transform="translate(0.5 0.574)" fill="#ff671d" stroke="rgba(0,0,0,0)" stroke-width="1"></path>
                                </svg>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section fs-5 fw-bolder ps-2 py-1 text-dark">Kontrol Paneli</span>
                                <div class="container-hamburger">
                                    <div class="bar1"></div>
                                    <div class="bar2"></div>
                                    <div class="bar3"></div>
                                </div>
                            </div>
                            
                        </span>
                        <div class=" px-2 w-250px mh-75 overflow-auto sub-menu hidden-menu">
                            <div data-kt-menu-trigger="click" class="menu-item  menu-accordion" v-if="this.useAuthStore().permissions?.includes('per-04-03')">
                                <div class="menu-item ps-5 pe-15 d-flex align-items-center justify-content-between">
                                    <router-link :to="{ name: 'Roles' }" :class="['menu-link px-3']" >
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Rol ve Yetki Yönetimi</span>
                                    </router-link>
                                    
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item  menu-accordion" v-if="this.useAuthStore().permissions?.includes('per-04')">
                                <div class="menu-item ps-5 pe-15 d-flex align-items-center justify-content-between">
                                    <router-link :to="{ name: 'UList' }" :class="['menu-link px-3']" >
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Kullanıcı Yönetimi</span>
                                    </router-link>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="this.useAuthStore().permissions?.includes('per-05')" data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="right-start" class="menu-item py-2 menu-row-main">
                        <span class="menu-link main-menu px-3">
                            <span class="menu-icon me-0">
                                <i class="ki-solid ki-briefcase fs-1"></i>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section fs-5 fw-bolder ps-2 py-1 text-dark">Talep</span>
                                <div class="container-hamburger">
                                    <div class="bar1"></div>
                                    <div class="bar2"></div>
                                    <div class="bar3"></div>
                                </div>
                            </div>
                            
                        </span>
                        <div class=" px-2 w-250px mh-75 overflow-auto sub-menu hidden-menu">
                            <div data-kt-menu-trigger="click" class="menu-item  menu-accordion" v-if="this.useAuthStore().permissions?.includes('per-05-02')">
                                <div class="menu-item ps-5 pe-15 d-flex align-items-center justify-content-between">
                                    <router-link :to="{ name: 'RequestForm' }" :class="['menu-link px-3']" >
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Talep Oluştur</span>
                                    </router-link>
                                    
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item  menu-accordion" v-if="this.useAuthStore().permissions?.includes('per-05-01')">
                                <div class="menu-item ps-5 pe-15 d-flex align-items-center justify-content-between">
                                    <router-link :to="{ name: 'RequestList' }" :class="['menu-link px-3']" >
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Talep Listesi</span>
                                    </router-link>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="this.useAuthStore().permissions?.includes('per-06')" data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="right-start" class="menu-item py-2 menu-row-main">
                        <span class="menu-link main-menu px-3">
                            <span class="menu-icon me-0">
                                <i class="ki-solid ki-home fs-1"></i>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section fs-5 fw-bolder ps-2 py-1 text-dark">Firma</span>
                                <div class="container-hamburger">
                                    <div class="bar1"></div>
                                    <div class="bar2"></div>
                                    <div class="bar3"></div>
                                </div>
                            </div>
                            
                        </span>
                        <div class=" px-2 w-250px mh-75 overflow-auto sub-menu hidden-menu">
                            <div data-kt-menu-trigger="click" class="menu-item  menu-accordion" v-if="this.useAuthStore().permissions?.includes('per-06-02')">
                                <div class="menu-item ps-5 pe-15 d-flex align-items-center justify-content-between">
                                    <router-link :to="{ name: 'CForm' }" :class="['menu-link px-3']" >
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Firma Oluştur</span>
                                    </router-link>
                                    
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item  menu-accordion" v-if="this.useAuthStore().permissions?.includes('per-06-01')">
                                <div class="menu-item ps-5 pe-15 d-flex align-items-center justify-content-between">
                                    <router-link :to="{ name: 'CList' }" :class="['menu-link px-3']" >
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Firma Listesi</span>
                                    </router-link>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </Simplebar>
        <div class="aside-footer flex-column-auto pb-5 pb-lg-10" id="kt_aside_footer">
            <div class="d-flex flex-center w-100 scroll-px" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-dismiss="click" data-kt-initialized="1">
                <a href="/logout" class="btn btn-custom" data-kt-menu-trigger="click" data-kt-menu-overflow="true" data-kt-menu-placement="top-start">
                    <i class="ki-duotone ki-entrance-left fs-2x">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </a>
            </div>
        </div>
    </div>
        
</template>