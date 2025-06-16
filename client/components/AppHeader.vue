<template>
    <header class="header"> 
        <button type="button" class="d-xl-none icon me-2 ms-n2.5 sidebar-toggle"> 
            <svg width="15" height="12" fill="none" class="pe-none" viewBox="0 0 16 13">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M1 1h14M1 6.25h9.546M1 11.5h14" /></svg> 
            <span class="visually-hidden">Open Sidebar</span>
        </button> 
        <a class="d-none d-sm-block logo" href="#">Super Admin 3.0</a> <i class="ms-auto"></i>
        <div class="content-search d-lg-flex d-none"> <i class="fs-5 ph ph-magnifying-glass"></i> <input type="text"
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
            <li class="dropdown header-notify"> <button type="button" class="ph ph-bell" data-bs-toggle="dropdown"
                    data-bs-display="static" data-bs-auto-close="outside" aria-expanded="false"> <span
                        class="visually-hidden">Notifications</span> </button>
                <div class="dropdown-menu header-dropdown-menu">
                    <div class="align-items-center d-flex flex-shrink-0 h-11 px-4">
                        <div class="fw-medium text-body-emphasis">Notifications</div>
                        <div class="d-flex gap-px me-n2 ms-auto"> <button type="button" class="icon ph ph-check-circle">
                                <span class="visually-hidden">Mark as read</span> </button> <button type="button"
                                class="icon ph ph-app-window"> <span class="visually-hidden">Open Notifications</span>
                            </button> <button type="button" class="icon ph ph-gear"> <span
                                    class="visually-hidden">Settings</span> </button> </div>
                    </div>
                    <div class="flex-grow-1 pb-1 px-1" data-simplebar>
                        <div id="top-notifications"></div>
                    </div>
                </div>
            </li>
            <li class="d-none d-sm-block dropdown"> <button type="button" class="ph ph-check-square-offset"
                    data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="outside"
                    aria-expanded="false"> <span class="visually-hidden">Tasks</span> </button>
                <div class="dropdown-menu header-dropdown-menu">
                    <div class="align-items-center d-flex flex-shrink-0 h-11 px-4">
                        <div class="fw-medium text-body-emphasis">Ongoing Tasks</div>
                        <div class="d-flex gap-px me-n2 ms-auto"> <button type="button" class="icon ph ph-check-circle">
                                <span class="visually-hidden">Settings</span> </button> <button type="button"
                                class="icon ph ph-app-window"> <span class="visually-hidden">Open Tasks</span> </button>
                            <button type="button" class="icon ph ph-gear"> <span class="visually-hidden">Settings</span>
                            </button> </div>
                    </div>
                    <div class="flex-grow-1 mx-n2 pt-2 px-2" data-simplebar>
                        <div id="top-tasks"></div>
                    </div>
                </div>
            </li>
            <li class="d-none d-sm-block dropdown"> <button type="button" class="ph ph-squares-four"
                    data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="outside"
                    aria-expanded="false"> <span class="visually-hidden">Shortcuts</span> </button>
                <div class="dropdown-menu header-dropdown-menu">
                    <div class="align-items-center d-flex flex-shrink-0 h-11 px-4">
                        <div class="fw-medium text-body-emphasis">Shortcuts</div>
                        <div class="d-flex gap-px me-n2 ms-auto"> <button type="button" class="icon ph ph-plus-circle">
                                <span class="visually-hidden">Add new</span> </button> <button type="button"
                                class="icon ph ph-gear"> <span class="visually-hidden">Settings</span> </button> </div>
                    </div>
                    <div class="flex-grow-1 pt-2" data-simplebar>
                        <div id="t" class="g-3 px-4 row row-cols-3">
                            <a href="/panel/calendar" class="d-block text-white">
                                <div class="bg-hover-inverse rounded text-center p-3">
                                    <i class="ph ph-calendar fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                                    <span class="d-block lh-1 mt-2 fs-7">Takvim</span>
                                </div>
                            </a>
                    
                    </a><a href="" class="d-block text-white">
                        <div class="bg-hover-inverse rounded text-center p-3">
                            <i class="ph ph-envelope-simple fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                            <span class="d-block lh-1 mt-2 fs-7">Mail</span>
                        </div>
                    </a><a href="" class="d-block text-white">
                        <div class="bg-hover-inverse rounded text-center p-3">
                            <i class="ph ph-chart-pie-slice fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                            <span class="d-block lh-1 mt-2 fs-7">Reports</span>
                        </div>
                    </a><a href="" class="d-block text-white">
                        <div class="bg-hover-inverse rounded text-center p-3">
                            <i class="ph ph-image fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                            <span class="d-block lh-1 mt-2 fs-7">Photos</span>
                        </div>
                    </a><a href="" class="d-block text-white">
                        <div class="bg-hover-inverse rounded text-center p-3">
                            <i class="ph ph-check-square fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                            <span class="d-block lh-1 mt-2 fs-7">Todos</span>
                        </div>
                    </a><a href="" class="d-block text-white">
                        <div class="bg-hover-inverse rounded text-center p-3">
                            <i class="ph ph-chat-centered-dots fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                            <span class="d-block lh-1 mt-2 fs-7">Messages</span>
                        </div>
                    </a><a href="" class="d-block text-white">
                        <div class="bg-hover-inverse rounded text-center p-3">
                            <i class="ph ph-invoice fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                            <span class="d-block lh-1 mt-2 fs-7">Invoice</span>
                        </div>
                    </a><a href="" class="d-block text-white">
                        <div class="bg-hover-inverse rounded text-center p-3">
                            <i class="ph ph-user-circle fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                            <span class="d-block lh-1 mt-2 fs-7">Contacts</span>
                        </div>
                    </a><a href="" class="d-block text-white">
                        <div class="bg-hover-inverse rounded text-center p-3">
                            <i class="ph ph-question fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                            <span class="d-block lh-1 mt-2 fs-7">FAQ</span>
                        </div>
                    </a><a href="" class="d-block text-white">
                        <div class="bg-hover-inverse rounded text-center p-3">
                            <i class="ph ph-rss fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                            <span class="d-block lh-1 mt-2 fs-7">News</span>
                        </div>
                    </a><a href="" class="d-block text-white">
                        <div class="bg-hover-inverse rounded text-center p-3">
                            <i class="ph ph-rows fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                            <span class="d-block lh-1 mt-2 fs-7">Timeline</span>
                        </div>
                    </a></div>
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
            
           
            <li class="dropdown"> <button type="button" class="h-11 me-n0.5 p-2 rounded w-11" data-bs-toggle="dropdown"
                    aria-expanded="false"> <img class="h-8 rounded" src="http://icons.iconarchive.com/icons/sykonist/looney-tunes/256/Taz-icon.png"> </button>
                <div class="dropdown-menu"> <a href="#" class="dropdown-item"> <i class="ph ph-user-circle"></i> Profile
                    </a> <a href="#" class="dropdown-item"> <i class="ph ph-currency-circle-dollar"></i> Billing </a> <a
                        href="#" class="dropdown-item"> <i class="ph ph-gear"></i> Preferences </a> <a href="#"
                        class="dropdown-item"> <i class="ph ph-sign-out"></i> Logout </a> </div>
            </li>
        </ul>
    </header>
</template>