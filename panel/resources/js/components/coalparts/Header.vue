<template>
    <div class="header-mobile py-3">
        <div class="container d-flex flex-stack">
            <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                <a href="index.html">
                    <img alt="Logo" src="/system/front/media/logos/gdz-logo.svg" class="h-35px">
                </a>
            </div>

            <button class="btn btn-icon btn-active-color-primary me-n4" id="kt_aside_toggle">
                <i class="ki-duotone ki-abstract-14 fs-2x">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </button>
        </div>
    </div>
    <div id="kt_header" class="header py-6 py-lg-0" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{lg: '300px'}">
                <div hidden="" class="header-container container-xxl">
                                        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-20 py-3 py-lg-0 me-3">
                        <h1 class="d-flex flex-column text-gray-900 fw-bold my-1">
                            <span class="text-white fs-1"></span>
                            <small class="text-white fs-6 fw-normal pt-2"></small>
                        </h1>
                    </div>
                                        <div class="d-flex align-items-center flex-wrap">
                        
                        <div class="d-flex align-items-center py-3 py-lg-0">
                            <div class="me-3">
                                                                                                <a href="#" class="btn btn-icon btn-custom btn-active-color-primary position-relative has-sub-btn">
                                    <i class="ki-duotone ki-notification-bing fs-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <span hidden="" class="bullet bullet-dot bg-success h-6px w-6px position-absolute translate-middle top-0 start-50 animation-blink"></span>
                                </a>
                                <div class="has-sub-div menu menu-sub menu-sub-dropdown menu-column w-350px w-lg-375px" data-kt-menu="true" id="kt_menu_notifications">
                                    <div class="d-flex flex-column bgi-no-repeat rounded-top" style="background:var(--bs-primary)">
                                        <h3 class="text-white fw-semibold px-9 mt-10 mb-6">Bildirimler
                                            <span class="fs-8 opacity-75 ps-3">0
                                                Yeni Bildirim</span>
                                        </h3>
                                        <ul class="nav nav-line-tabs nav-line-tabs-2x nav-stretch fw-semibold px-9" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link active text-white opacity-75 opacity-state-100 pb-4" data-bs-toggle="tab" href="#kt_topbar_notifications_1" aria-selected="true" role="tab">Uyarılar</a>
                                            </li>

                                            <li class="nav-item" hidden="" role="presentation">
                                                <a class="nav-link text-white opacity-75 opacity-state-100 pb-4" data-bs-toggle="tab" href="#kt_topbar_notifications_3" aria-selected="false" tabindex="-1" role="tab">Log
                                                    Kayıtları</a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane active show fade" id="kt_topbar_notifications_1" role="tabpanel">
                                            <div class="scroll-y mh-325px my-5 px-8">
                                                

                                                                                            </div>

                                            <div class="py-3 text-center border-top">
                                                <a href="#" data-not="null" class="btn btn-color-gray-600 btn-active-color-primary clickable-icon not-modal">Tümünü Gör
                                                     <i class="ki-duotone ki-arrow-right fs-5">
                                                          <span class="path1"></span>
                                                          <span class="path2"></span>
                                                     </i>
                                                </a>
                                           </div>
                                        </div>

                                        <div class="tab-pane fade" id="kt_topbar_notifications_3" role="tabpanel">
                                            <div class="scroll-y mh-325px my-5 px-8">
                                                <div class="d-flex flex-stack py-4">
                                                    <div class="d-flex align-items-center me-2">
                                                        <span class="w-70px badge badge-light-success me-4">200
                                                            OK</span>
                                                        <a href="#" class="text-gray-800 text-hover-primary fw-semibold">New
                                                            order</a>
                                                    </div>
                                                    <span class="badge badge-light fs-8">Just now</span>
                                                </div>
                                                <div class="d-flex flex-stack py-4">
                                                    <div class="d-flex align-items-center me-2">
                                                        <span class="w-70px badge badge-light-danger me-4">500
                                                            ERR</span>
                                                        <a href="#" class="text-gray-800 text-hover-primary fw-semibold">New
                                                            customer</a>
                                                    </div>
                                                    <span class="badge badge-light fs-8">2 hrs</span>
                                                </div>
                                                <div class="d-flex flex-stack py-4">
                                                    <div class="d-flex align-items-center me-2">
                                                        <span class="w-70px badge badge-light-warning me-4">300
                                                            WRN</span>
                                                        <a href="#" class="text-gray-800 text-hover-primary fw-semibold">Search
                                                            query</a>
                                                    </div>
                                                    <span class="badge badge-light fs-8">2 days</span>
                                                </div>
                                            </div>
                                            <div class="py-3 text-center border-top">
                                                <a href="pages/user-profile/activity.html" class="btn btn-color-gray-600 btn-active-color-primary">Tümünü Gör
                                                    <i class="ki-duotone ki-arrow-right fs-5">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="me-3">

                                <a href="javascript:;" id="personInfoBox" class="btn btn-icon btn-custom btn-active-color-primary has-sub-btn">
                                    <i class="ki-duotone ki-user fs-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </a>
                                <div id="personInfoDiv" class="has-sub-div menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px" data-kt-menu="true">
                                    <div class="menu-item px-3">
                                        <div class="menu-content d-flex align-items-center px-3">
                                            <div class="symbol symbol-50px me-5">
                                                <img alt="Logo" src="/system/front/media/logos/gdz-logo.svg">
                                            </div>
                                            <div class="d-flex flex-column">
                                                <div class="fw-bold d-flex align-items-center fs-5">
                                                    talk.test  
                                                </div>
                                                <a href="#" style="overflow-wrap: break-word;word-break: break-all;" class="fw-semibold text-muted text-hover-primary fs-7">talk.test@GDZldap.com</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="separator my-2"></div>
                                    <div class="menu-item px-5">
                                        <a href="/kullanici/olustur/anabilgiler/9880" class="menu-link px-5">Profil</a>
                                    </div>
                                    
                                </div>
                            </div>
                            <a href="/logout" class="btn btn-icon btn-custom btn-active-color-primary" data-kt-menu-trigger="click" data-kt-menu-overflow="true" data-kt-menu-placement="top-start">
                                <i class="ki-duotone ki-entrance-left fs-2x">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </a>
                            
                        </div>
                    </div>
                </div>
                <div class="header-offset"></div>
            </div>
</template>