<script>
import Simplebar from 'simplebar-vue';
import 'simplebar-vue/dist/simplebar.min.css';
export default {
    components: {
        Simplebar
    },
    setup() {
        // expose to template and other options API hooks
        return {
            
        }
    },
    mounted(){
        //console.log(useAuthStore().data);
    },  
    data() {
        return {
            menubar        : document.querySelector('input[name="menubar"]').value,
            title          : document.querySelector('input[name="header"]').value
        };
    },
}

</script>

<template>
    <div class="left-bar-logo">
        <img hidden src="/front/assets/img/logo.png" alt="" title="" width="200" />
        <span>Yönetim Paneli</span>
    </div>
    <div class="left-bar-menu">
        
        <ul class="menu">
            <li>
                <router-link :to="{ name: 'Index' }" :class="$route.name == 'Index' ? 'active' : ''" alt="">
                    <span class="kontent-icon" name="Menu1Icon"></span>{{ $t('menu.home')}}
                </router-link>
            </li>
            <li>
                <router-link  :to="{ name: 'FList' }" :class="$route.name == 'FList'  || $route.name == 'FForm' ? 'active' : ''" alt="">
                    <span class="kontent-icon" name="Menu2Icon"></span>{{ $t('menu.facility') }}
                </router-link>
            </li>
            <li>
                <router-link  :to="{ name: 'LList' }" :class="$route.name == 'LList'  ? 'active' : ''" alt="">
                    <span class="kontent-icon" name="Menu4Icon"></span>{{ $t('menu.logs') }}
                </router-link>
            </li>
            <li>
                <router-link  :to="{ name: 'WList' }" :class="$route.name == 'WList'  ? 'active' : ''" alt="">
                    <span class="kontent-icon" name="Menu5Icon"></span>Canlı Takip
                </router-link>
            </li>
            <li>
                <router-link v-if="menubar != 'op-pert-reseller'" :to="{ name: 'UList' }" :class="$route.name == 'UList'  || $route.name == 'UForm' ? 'active' : ''" alt=""><span class="kontent-icon" name="Menu7Icon">
                    </span>{{ $t('menu.users') }}
                </router-link>
            </li>
            <!--<li><a href="/admin/index.html" alt="" class="active"><span class="kontent-icon" name="Menu1Icon"></span> Giriş</a></li>
            <li><a href="/admin/cari.html" alt=""><span class="kontent-icon" name="Menu2Icon"></span> Cari Ekstre</a></li>
            <li><a href="/admin/mutabakat-listesi.html" alt=""><span class="kontent-icon" name="Menu3Icon"></span> Mutabakat Listesi</a></li>
            <li><a href="/admin/imzali-mutabakat.html" alt=""><span class="kontent-icon" name="Menu4Icon"></span> İmzalı Mutabakat Talepleri</a></li>
            <li><a href="" alt=""><span class="kontent-icon" name="Menu5Icon"></span> Geçmiş Dönem Mutabakatlar</a></li>
            <li><a href="" alt=""><span class="kontent-icon" name="Menu6Icon"></span> Hata Bildir</a></li>
            <li><a href="" alt=""><span class="kontent-icon" name="Menu7Icon"></span> Kullanıcı Ayarları</a></li>-->
        </ul>
        
    </div>
    <div class="left-bar-fast" hidden>
        <div>
            <span class="kontent-icon" name="Search"></span>
        </div>
        <div class="lang">
            <span class="kontent-icon" name="Earth"></span>
            <span class="kontent-icon" name="Down"></span>
        </div>
    </div>
    <button class="menuToggle"><span class="kontent-icon" name="Right"></span></button>
</template>