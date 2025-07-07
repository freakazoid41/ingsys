<script>
import { useAuthStore } from '@/stores/auth';
export default {
    components: {},
    setup() {
        // expose to template and other options API hooks
        return {
            useAuthStore,
        }
    },
    mounted(){
        console.log(useAuthStore().data);
    },  
    data() {
        return {
            useAuthStore   : useAuthStore(),
        };
    },
}

</script>
<style>
#sidebar{
    background: white !important;
    border: 1px solid #dbe0eb;
}

</style>
<template>
    <button type="button" id="sidebar-close" class="icon sidebar-close">
        <i class="fs-5 ph ph-x"></i> 
        <span class="">Close Sidebar</span> 
    </button>
    <div class="sidebar-header" style="opacity: 1 !important; border-bottom: 1px solid #dbe0eb;">
        <a class="btn btn-primary w-100" type="button" href="/panel/links/form">Yeni Link Ekle</a>
    </div>
    <div>
        <ul class="menu">
            <!--<li class="menu-title">{{ $t('menu.documents') }} </li>-->
            <!--<li> <a href="/panel/documents"> <i class="ph ph-kanban"></i> <span>{{ $t('menu.documents') }}</span> </a> </li>-->
            <li> <a href="/panel"> <i class="ph ph-kanban" style="color: black;"></i> <span style="color: black;">{{ $t('menu.home') }}</span> </a> </li>
            <li> <a href="/panel/links"> <i class="ph ph-newspaper" style="color: black;"></i> <span style="color: black;">Link Listesi</span> </a> </li>
            <li> <a href="/panel/users" > <i class="ph ph-user" style="color: black;"></i> <span  style="color: black;">{{ $t('menu.users') }}</span> </a> </li>
            <!--<li> <a href="/panel/flats" v-if="useAuthStore.data.type=='admin'"> <i class="ph ph-house-line"></i> <span>{{ $t('menu.flats') }}</span> </a> </li>
            <li> <a href="/panel/targets" v-if="useAuthStore.data.type=='admin'"> <i class="ph ph-folder-lock"></i> <span>{{ $t('menu.targets') }}</span> </a> </li>
            <li> <a href="/panel/transactions"> <i class="ph ph-money"></i> <span>{{ $t('menu.transactions') }}</span> </a> </li>
            <li> <a href="/panel/meetings"> <i class="ph ph-users-three"></i> <span>{{ $t('menu.meetings') }}</span> </a> </li>
            <li> <a href="/panel/users" v-if="useAuthStore.data.type=='admin'"> <i class="ph ph-user"></i> <span>{{ $t('menu.users') }}</span> </a> </li>
            <li> <a href="/panel/projects"> <i class="ph ph-hammer"></i> <span>{{ $t('menu.projects') }}</span> </a> </li>
            <li> <a href="/panel/calendar"> <i class="ph ph-calendar"></i> <span>{{ $t('menu.calendar') }}</span> </a> </li>
            <li> <a href="/panel/inventory"> <i class="ph ph-wrench"></i> <span>{{ $t('menu.inventory') }}</span> </a> </li>-->
            <!--<li> <a href="/panel/documents"> <i class="ph ph-newspaper"></i> <span>{{ $t('menu.documentfiles') }}</span> </a> </li>-->
            <li hidden class="menu-sub"> <a href="#"> <i class="ph ph-browser"></i> <span>Sample Pages</span> </a>
                <ul>
                    <li><a href="invoice.html">Invoice</a></li>
                    <li><a href="pricing-table.html">Pricing Table</a></li>
                    <li><a href="search-results.html">Search Results</a></li>
                    <li><a href="faq.html">FAQ</a></li>
                    <li><a href="team.html">Team</a></li>
                    <li><a href="login.html">Login</a></li>
                    <li><a href="register.html">Register</a></li>
                    <li><a href="forgot-password.html">Forgot Password</a></li>
                    <li><a href="coming-soon.html">Coming Soon</a></li>
                    <li><a href="error.html">Error</a></li>
                </ul>
            </li>
        </ul>
    </div>
</template>

