
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
            <span class="text-primary fs-5">Yüklenici Takip Sistemi</span>
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
                                                                    <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="right-start" class="menu-item py-2 menu-row-main">
                        <span class="menu-link main-menu  px-3">
                            <span class="menu-icon me-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="19.997" height="19.994" viewBox="0 0 19.997 19.994">
                                    <path id="Union_8" data-name="Union 8" d="M2.911,16.3a9.892,9.892,0,0,1,3.354-1.4C6.9,17.265,7.992,18.994,9.5,18.994A9.4,9.4,0,0,1,2.911,16.3Zm9.817-1.4a9.9,9.9,0,0,1,3.36,1.392,9.4,9.4,0,0,1-6.591,2.7C11,18.993,12.089,17.265,12.728,14.9Zm-5.363-.2A14.629,14.629,0,0,1,9.5,14.541a14.824,14.824,0,0,1,2.133.155c-.549,1.962-1.367,3.184-2.133,3.184S7.914,16.66,7.365,14.7Zm5.62-.879a22.466,22.466,0,0,0,.4-3.768h5.584a9.564,9.564,0,0,1-2.132,5.419A10.833,10.833,0,0,0,12.984,13.821ZM.028,10.053H5.612a22.548,22.548,0,0,0,.4,3.758,10.673,10.673,0,0,0-3.861,1.642A9.572,9.572,0,0,1,.028,10.053ZM9.5,13.429a16.318,16.318,0,0,0-2.4.179,20.974,20.974,0,0,1-.375-3.555h5.543a20.813,20.813,0,0,1-.375,3.557A15.939,15.939,0,0,0,9.5,13.429ZM13.391,8.94V7.233a2.786,2.786,0,0,1,2.783-2.782,2.817,2.817,0,0,1,2.82,2.782V8.94Zm-6.677,0V7.233a2.782,2.782,0,0,1,5.565,0V8.94ZM0,8.94V7.233A2.817,2.817,0,0,1,2.82,4.451,2.785,2.785,0,0,1,5.6,7.233V8.94ZM13.948,2.226a2.226,2.226,0,1,1,2.226,2.226A2.225,2.225,0,0,1,13.948,2.226Zm-6.677,0A2.226,2.226,0,1,1,9.5,4.451,2.225,2.225,0,0,1,7.271,2.226Zm-6.677,0A2.226,2.226,0,1,1,2.82,4.451,2.225,2.225,0,0,1,.594,2.226Z" transform="translate(0.502 0.5)" fill="#ff671d" stroke="rgba(0,0,0,0)" stroke-width="1"></path>
                                </svg>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section fs-5 fw-bolder ps-2 py-1 text-dark">Yüklenici Firma</span>
                                <div class="container-hamburger">
                                    <div class="bar1"></div>
                                    <div class="bar2"></div>
                                    <div class="bar3"></div>
                                </div>
                            </div>
                            
                        </span>
                        <div class=" px-2 w-250px mh-75 overflow-auto hidden-menu sub-menu">
                                                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15 d-flex align-items-center">
                                    <a class="menu-link " href="https://yts.gdzelektrik.com.tr/yuklenici/olustur">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Yüklenici Oluştur</span>
                                        </a><a href="/system/front/media/yuklenici-olustur.mp4" class="pop-video video-popup mfp-iframe" data-lity="">
                                            <svg version="1.1" class="ms-1" id="svg519" fill="#989898" xml:space="preserve" width="20px" height="20px" viewBox="0 0 682.66669 682.66669" xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg">
                                                <defs id="defs523">
                                                    <clipPath clipPathUnits="userSpaceOnUse" id="clipPath533">
                                                        <path d="M 0,512 H 512 V 0 H 0 Z" id="path531"></path>
                                                    </clipPath>
                                                </defs>
                                                <g id="g525" transform="matrix(1.3333333,0,0,-1.3333333,0,682.66667)">
                                                    <g id="g527">
                                                        <g id="g529" clip-path="url(#clipPath533)">
                                                            <g id="g535" transform="translate(336.333,368.4668)">
                                                                <path d="m 0,0 c 0,17.746 -14.38,32.133 -32.133,32.133 h -257.066 c -17.754,0 -32.134,-14.387 -32.134,-32.133 v -224.934 c 0,-17.745 14.38,-32.133 32.134,-32.133 h 257.066 c 17.753,0 32.133,14.388 32.133,32.133 z" style="fill: none; stroke: #989898; stroke-width: 30; stroke-linecap: round; stroke-linejoin: round; stroke-miterlimit: 10; stroke-dasharray: none; stroke-opacity: 1;" id="path537"></path>
                                                            </g>
                                                            <g id="g539" transform="translate(497,368.4668)">
                                                                <path d="m 0,0 -160.667,-64.267 v -96.4 L 0,-224.934 Z" style="fill: none; stroke: #989898; stroke-width: 30; stroke-linecap: round; stroke-linejoin: round; stroke-miterlimit: 10; stroke-dasharray: none; stroke-opacity: 1;" id="path541"></path>
                                                            </g>
                                                        </g>
                                                    </g>
                                                </g>
                                            </svg>

                                        </a>
                                    

                                </div>
                            </div>
                                                                                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15 d-flex align-items-center">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/yuklenici">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Yüklenici Listesi</span>
                                        </a><a href="/system/front/media/yuklenici-duzenle.mp4" class="pop-video video-popup mfp-iframe" data-lity="">
                                            <svg version="1.1" class="ms-1" id="svg519" fill="#989898" xml:space="preserve" width="20px" height="20px" viewBox="0 0 682.66669 682.66669" xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg">
                                                <defs id="defs523">
                                                    <clipPath clipPathUnits="userSpaceOnUse" id="clipPath533">
                                                        <path d="M 0,512 H 512 V 0 H 0 Z" id="path531"></path>
                                                    </clipPath>
                                                </defs>
                                                <g id="g525" transform="matrix(1.3333333,0,0,-1.3333333,0,682.66667)">
                                                    <g id="g527">
                                                        <g id="g529" clip-path="url(#clipPath533)">
                                                            <g id="g535" transform="translate(336.333,368.4668)">
                                                                <path d="m 0,0 c 0,17.746 -14.38,32.133 -32.133,32.133 h -257.066 c -17.754,0 -32.134,-14.387 -32.134,-32.133 v -224.934 c 0,-17.745 14.38,-32.133 32.134,-32.133 h 257.066 c 17.753,0 32.133,14.388 32.133,32.133 z" style="fill: none; stroke: #989898; stroke-width: 30; stroke-linecap: round; stroke-linejoin: round; stroke-miterlimit: 10; stroke-dasharray: none; stroke-opacity: 1;" id="path537"></path>
                                                            </g>
                                                            <g id="g539" transform="translate(497,368.4668)">
                                                                <path d="m 0,0 -160.667,-64.267 v -96.4 L 0,-224.934 Z" style="fill: none; stroke: #989898; stroke-width: 30; stroke-linecap: round; stroke-linejoin: round; stroke-miterlimit: 10; stroke-dasharray: none; stroke-opacity: 1;" id="path541"></path>
                                                            </g>
                                                        </g>
                                                    </g>
                                                </g>
                                            </svg>

                                        </a>
                                    
                                </div>
                            </div>
                                                        </div>
                    </div>
                                                                    <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="right-start" class="menu-item py-2 menu-row-main">
                        <span class="menu-link main-menu  px-3">
                            <span class="menu-icon me-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22.036" height="19.406" viewBox="0 0 22.036 19.406">
                                    <path id="Union_7" data-name="Union 7" d="M6.136,18.406a.877.877,0,0,1-.877-.877V9.641A2.629,2.629,0,0,1,7.889,7.012h.877l1.753,5.259,1.753-5.259h.876a2.629,2.629,0,0,1,2.63,2.629v7.889a.877.877,0,0,1-.877.877ZM.877,15.771A.877.877,0,0,1,0,14.894V9.635A1.753,1.753,0,0,1,1.753,7.881h3.1a3.479,3.479,0,0,0-.472,1.753v6.136Zm15.777,0V9.635a3.488,3.488,0,0,0-.472-1.753h3.1a1.753,1.753,0,0,1,1.753,1.753v5.259a.877.877,0,0,1-.877.877ZM14.666,4.959a2.2,2.2,0,1,1,2.2,2.2A2.2,2.2,0,0,1,14.666,4.959Zm-12.7,0a2.2,2.2,0,1,1,2.2,2.2A2.2,2.2,0,0,1,1.968,4.959ZM7.012,3.506a3.506,3.506,0,1,1,3.506,3.506A3.506,3.506,0,0,1,7.012,3.506Z" transform="translate(0.5 0.5)" fill="#ff671d" stroke="rgba(0,0,0,0)" stroke-width="1"></path>
                                </svg>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section fs-5 fw-bolder ps-2 py-1 text-dark">Personel</span>
                                <div class="container-hamburger">
                                    <div class="bar1"></div>
                                    <div class="bar2"></div>
                                    <div class="bar3"></div>
                                </div>
                            </div>
                            
                        </span>
                        <div class=" px-2 w-250px mh-75 overflow-auto hidden-menu sub-menu">
                                                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-10">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/yuklenici-personel/olustur">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Personel Oluştur</span>
                                    </a>
                                </div>
                            </div>
                                                                                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15 d-flex align-items-center">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/yuklenici-personel">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Personel Listesi</span>
                                        </a><a href="/system/front/media/personele-giris-2.mp4" class="pop-video video-popup mfp-iframe" data-lity="">
                                            <svg version="1.1" class="ms-1" id="svg519" fill="#989898" xml:space="preserve" width="20px" height="20px" viewBox="0 0 682.66669 682.66669" xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg">
                                                <defs id="defs523">
                                                    <clipPath clipPathUnits="userSpaceOnUse" id="clipPath533">
                                                        <path d="M 0,512 H 512 V 0 H 0 Z" id="path531"></path>
                                                    </clipPath>
                                                </defs>
                                                <g id="g525" transform="matrix(1.3333333,0,0,-1.3333333,0,682.66667)">
                                                    <g id="g527">
                                                        <g id="g529" clip-path="url(#clipPath533)">
                                                            <g id="g535" transform="translate(336.333,368.4668)">
                                                                <path d="m 0,0 c 0,17.746 -14.38,32.133 -32.133,32.133 h -257.066 c -17.754,0 -32.134,-14.387 -32.134,-32.133 v -224.934 c 0,-17.745 14.38,-32.133 32.134,-32.133 h 257.066 c 17.753,0 32.133,14.388 32.133,32.133 z" style="fill: none; stroke: #989898; stroke-width: 30; stroke-linecap: round; stroke-linejoin: round; stroke-miterlimit: 10; stroke-dasharray: none; stroke-opacity: 1;" id="path537"></path>
                                                            </g>
                                                            <g id="g539" transform="translate(497,368.4668)">
                                                                <path d="m 0,0 -160.667,-64.267 v -96.4 L 0,-224.934 Z" style="fill: none; stroke: #989898; stroke-width: 30; stroke-linecap: round; stroke-linejoin: round; stroke-miterlimit: 10; stroke-dasharray: none; stroke-opacity: 1;" id="path541"></path>
                                                            </g>
                                                        </g>
                                                    </g>
                                                </g>
                                            </svg>

                                        </a>
                                    
                                </div>
                            </div>
                                                        </div>
                    </div>
                                                                    <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="right-start" class="menu-item py-2 menu-row-main">
                        <a href="https://yts.gdzelektrik.com.tr/sistem-belgeleri" class="menu-link px-3">
                            <span class="menu-icon me-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="17.401" height="19.157" viewBox="0 0 17.401 19.157">
                                    <path id="Union_9" data-name="Union 9" d="M2.3,18.157a1.027,1.027,0,0,1-1.014-.868L.01,8.975a.78.78,0,0,1,.78-.9H.863l-.11-.646A1.027,1.027,0,0,1,.975,6.6a1,1,0,0,1,.763-.363H5.865a1.3,1.3,0,0,1,.98.468L8,8.074h6.351a.786.786,0,0,1,.784.668l1.254,8.509a.784.784,0,0,1-.184.63.793.793,0,0,1-.6.275ZM13.864,7.087V1.2H5.192V5.394h-1.3V.836A.824.824,0,0,1,4.706,0H14.3a.771.771,0,0,1,.767.762V7.087ZM7.591,6.24l-.09-.1A2.145,2.145,0,0,0,6.4,5.46.643.643,0,0,1,7,4.936h4.968a.653.653,0,0,1,0,1.3ZM7,3.843A.653.653,0,0,1,7,2.538h4.968a.653.653,0,0,1,0,1.305Z" transform="translate(0.502 0.5)" fill="#ff671d" stroke="rgba(0,0,0,0)" stroke-width="1"></path>
                                </svg>
                            </span>
                            <div class="menu-content">
                                <span class="menu-section fs-5 fw-bolder ps-2 py-1 text-dark">Doküman Kontrol</span>
                            </div>
                        </a>
                    </div>
                                                                    <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="right-start" class="menu-item py-2 menu-row-main">
                        <a href="/ihale/hakedis" class="menu-link px-3">
                            <span class="menu-icon me-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="23.692" height="15.976" viewBox="0 0 23.692 15.976">
                                    <path id="Union_10" data-name="Union 10" d="M0,14.8V7.626H2.013L2.948,6.5a4.8,4.8,0,0,1,2.815-1.65A4.809,4.809,0,0,0,8.74,3l2.232-3a2.982,2.982,0,0,1,.785,4.211L9.5,7.385l-.037,2.951,2.78,1.233,4.7-.878,1.761-1.6a2.591,2.591,0,0,1,3.817.366L18.415,13.5l-7.271,1.3ZM10.8,9.482l.01-.8,4.09-.861,1.768-1.683a2.656,2.656,0,0,1,3.92.286l-.651.71a3.9,3.9,0,0,0-2.11.982L16.341,9.462l-3.933.734Z" transform="translate(0.5 0.678)" fill="#ff671d" stroke="rgba(0,0,0,0)" stroke-width="1"></path>
                                </svg>
                            </span>
                            <div class="menu-content">
                                <span class="menu-section fs-5 fw-bolder ps-2 py-1 text-dark">Hakediş</span>
                            </div>
                        </a>
                    </div>
                    <div v-if="useAuthStore.permissions?.includes('per-04')" data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="right-start" class="menu-item py-2 menu-row-main">

                        <router-link :to="{ name: 'UList' }" :class="['menu-link px-3']" >
                            <span class="menu-icon me-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24.592" height="23.411" viewBox="0 0 24.592 23.411">
                                    <path id="Union_11" data-name="Union 11" d="M10.852,22.337V20.9a4.574,4.574,0,0,1-1.486-.618L8.342,21.293a5.831,5.831,0,0,1-1.33-1.33l1.019-1.019a4.386,4.386,0,0,1-.618-1.486H5.973a5.775,5.775,0,0,1,0-1.887H7.412a4.39,4.39,0,0,1,.618-1.487L7.012,13.065A5.785,5.785,0,0,1,8.347,11.73l1.02,1.02a4.478,4.478,0,0,1,1.486-.618V10.692a5.775,5.775,0,0,1,1.887,0v1.439a4.445,4.445,0,0,1,1.487.618l1.019-1.02a5.856,5.856,0,0,1,1.335,1.336l-1.018,1.019a4.6,4.6,0,0,1,.617,1.487h1.439a6.034,6.034,0,0,1,0,1.887H16.179a4.592,4.592,0,0,1-.617,1.486l1.018,1.019A5.966,5.966,0,0,1,15.245,21.3L14.226,20.28a4.579,4.579,0,0,1-1.487.618v1.439a6.065,6.065,0,0,1-.945.074A6,6,0,0,1,10.852,22.337ZM9.2,16.514a2.6,2.6,0,1,0,2.6-2.595A2.594,2.594,0,0,0,9.2,16.514ZM4.964,15.043a.943.943,0,0,1-.623-.887V11.92c.005-2.648,2.8-3.856,5.011-4.37a4.592,4.592,0,0,0,4.893,0c2.2.519,5,1.717,5.006,4.369v2.237a.939.939,0,0,1-.627.887c-.042.019-.089.033-.137.052,0-.033-.014-.067-.019-.1a.473.473,0,0,0-.462-.368H16.883l-.156-.377.793-.794a.471.471,0,0,0,.066-.585A6.822,6.822,0,0,0,15.44,10.72a.477.477,0,0,0-.585.066l-.8.8-.373-.155V10.305a.475.475,0,0,0-.368-.463,6.869,6.869,0,0,0-3.038,0,.47.47,0,0,0-.368.463v1.123l-.378.156-.792-.8a.478.478,0,0,0-.585-.066A6.879,6.879,0,0,0,6,12.873a.47.47,0,0,0,.066.584l.8.793-.156.378H5.582A.467.467,0,0,0,5.125,15c-.01.032-.014.066-.024.1C5.058,15.076,5.011,15.062,4.964,15.043ZM.311,13.429A.468.468,0,0,1,0,12.985V11.39c0-1.755,1.859-2.547,3.317-2.9a3.339,3.339,0,0,0,1.221.458A4.4,4.4,0,0,0,3.4,11.919v2.246A13.869,13.869,0,0,1,.311,13.429Zm19.883.726V11.918a4.4,4.4,0,0,0-1.141-2.972,3.364,3.364,0,0,0,1.222-.457c1.448.354,3.321,1.147,3.317,2.9v1.595a.467.467,0,0,1-.311.443,13.808,13.808,0,0,1-3.086.736Zm-4.1-8.53a2.421,2.421,0,1,1,2.42,2.42A2.423,2.423,0,0,1,16.095,5.625Zm-13.438,0a2.421,2.421,0,1,1,2.42,2.421A2.422,2.422,0,0,1,2.657,5.624ZM8.14,3.657A3.657,3.657,0,1,1,11.8,7.313,3.657,3.657,0,0,1,8.14,3.657Z" transform="translate(0.5 0.5)" fill="#ff671d" stroke="rgba(0,0,0,0)" stroke-width="1"></path>
                                </svg>
                            </span>
                            <div class="menu-content">
                                <span class="menu-section fs-5 fw-bolder ps-2 py-1 text-dark">
                                    Sistem Kullanıcıları
                                </span>
                            </div>
                        </router-link>
                    </div>
                                                                    <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="right-start" class="menu-item py-2 menu-row-main">
                        <span class="menu-link main-menu px-3">
                            <span class="menu-icon me-0">
                                <i class="ki-duotone ki-people fs-2x">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                    <span class="path5"></span>
                                </i>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section fs-5 fw-bolder ps-2 py-1 text-dark">Raporlar</span>
                                <div class="container-hamburger">
                                    <div class="bar1"></div>
                                    <div class="bar2"></div>
                                    <div class="bar3"></div>
                                </div>
                            </div>
                        </span>
                        <div class=" px-2 w-250px mh-75 overflow-auto hidden-menu sub-menu">
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/report/filereport">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Dosya Yükleme Raporu</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/report/ebis">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Ebis</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/edwars">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Edwars Listesi Raporu</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/izin">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">İzin Yükü Raporu</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/level">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Kıdem Yükü Raporu</span>
                                    </a>
                                </div>
                            </div>
                            <div hidden="" data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/kidem">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Kıdem Yükü Raporu</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/isegiris">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">İşe Girenler Raporu</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/report/leaveusage">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">İzin Kullanım Raporu</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/izinplanlama">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">İzin Planlama Raporu</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/istencikis">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Ayrılan Personel Raporu</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/report/health">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Sağlık Verileri</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/report/ekat">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">EKAT Personel İsim listesi</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/report/myk">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">MYK Belgesi İsim listesi</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/report/isg">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">İSG Eğitimleri İsim listesi</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/report/inventory">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Zimmet listesi</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/report/workers">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Firma Çalışan Sayıları</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/report/manhour">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Adam - Saat Raporu</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/report/badrecord">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Tutanak Listesi Raporu</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/raporlar/report/tedasdenetleme">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Tedaş Denetleme Verisi Raporu</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                                                                    <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="right-start" class="menu-item py-2 menu-row-main">
                        <span class="menu-link main-menu  px-3">
                            <span class="menu-icon me-0">
                                <i class="ki-duotone ki-people fs-2x">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                    <span class="path5"></span>
                                </i>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section fs-5 fw-bolder ps-2 py-1 text-dark">Sistem
                                    Değişkenler</span>
                                    <div class="container-hamburger">
                                        <div class="bar1"></div>
                                        <div class="bar2"></div>
                                        <div class="bar3"></div>
                                    </div>
                            </div>
                            
                        </span>
                        <div class=" px-2 w-250px mh-75 overflow-auto hidden-menu sub-menu">
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/sistem-degiskenleri/resmi-tatil">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Resmi Tatiller</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/sistem-degiskenleri/yan-haklar">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Yan Hak Çarpım Oranları</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/sistem-degiskenleri/kesintiler">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Kesintiler Çarpım Oranları</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/sistem-degiskenleri/ayrilan-odemeleri">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Ayrılan Ödemeleri</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/sistem-degiskenleri/asgari-ucret-istisnalari">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Asgari Ücret İstisnaları</span>
                                    </a>
                                </div>
                            </div>
                            <div hidden="" data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/sistem-degiskenleri/aylik-ucret">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Aylık Ücret</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/sistem-degiskenleri/vergi-dilimleri">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Vergi Dilimleri</span>
                                    </a>
                                </div>
                            </div>
                            <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <div class="menu-item ps-5 pe-15">
                                    <a class="menu-link" href="https://yts.gdzelektrik.com.tr/sistem-degiskenleri/vergi-%C3%B6deme-oranlari">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Vergi Ödeme Oranları (Sadece Maaş Hesabı)</span>
                                    </a>
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