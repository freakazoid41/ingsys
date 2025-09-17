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
        if(this.$route.name == 'Calendar'){
            document.querySelector('body').classList.add('sidebar-min');
        }else{
            document.querySelector('body').classList.remove('sidebar-min');
        }
        //console.log(useAuthStore().data);
    },  
    data() {
        return {
            useAuthStore   : useAuthStore(),
            title          : document.querySelector('input[name="header"]').value
        };
    },
}

</script>
<template>
    <button type="button" id="sidebar-close" class="icon sidebar-close"> <i
            class="fs-5 ph ph-x"></i> <span class="visually-hidden">Close Sidebar</span> </button>
    <div class="sidebar-header"> <a class="d-none d-sm-block logo" href="index.html">{{ title }}</a> </div>
    <div >
        <ul class="menu">
            <!--<li class="menu-title">{{ $t('menu.documents') }} </li>-->
            <!--<li> <a href="/panel/documents"> <i class="ph ph-kanban"></i> <span>{{ $t('menu.documents') }}</span> </a> </li>-->
            <li> 
                <router-link :to="{ name: 'Index' }"> 
                    <i class="ph ph-kanban"></i> <span>{{ $t('menu.home') }}</span> 
                </router-link>
            </li>
            
            <li>  
                <router-link :to="{ name: 'FlatList' }" v-if="useAuthStore.data.type=='admin'">
                    <i class="ph ph-house-line"></i> <span>{{ $t('menu.flats') }}</span>
                </router-link>
            </li>
            <li> 
                <router-link :to="{ name: 'TargetList' }" v-if="useAuthStore.data.type=='admin'">
                    <i class="ph ph-folder-lock"></i> <span>{{ $t('menu.targets') }}</span> 
                </router-link>
            </li>
            <li> 
                <router-link :to="{ name: 'Transactions' }" v-if="useAuthStore.data.type=='admin'"> 
                    <i class="ph ph-money"></i> 
                    <span>{{ $t('menu.transactions') }}</span> 
                </router-link>
            </li>
            <li> 
                <router-link :to="{ name: 'MeetingList' }"> 
                    <i class="ph ph-users-three"></i> <span>{{ $t('menu.meetings') }}</span> 
                </router-link>
            </li>
            <li> 
                <router-link :to="{ name: 'UserList' }" v-if="useAuthStore.data.type=='admin'"> 
                    <i class="ph ph-user"></i> <span>{{ $t('menu.users') }}</span> 
                </router-link>
            </li>
            <li> 
                <router-link :to="{ name: 'ProjectList' }"> 
                    <i class="ph ph-hammer"></i> <span>{{ $t('menu.projects') }}</span> 
                </router-link>
            </li>
            <li> 
                <router-link :to="{ name: 'Calendar' }"> 
                    <i class="ph ph-calendar"></i> <span>{{ $t('menu.calendar') }}</span> 
                </router-link>
            </li>
            <li> 
                <router-link :to="{ name: 'InventoryList' }"> 
                    <i class="ph ph-wrench"></i> <span>{{ $t('menu.inventory') }}</span> 
                </router-link> 
            </li>
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

