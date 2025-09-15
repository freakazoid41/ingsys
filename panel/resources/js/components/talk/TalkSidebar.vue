<script>
import Simplebar from 'simplebar-vue';
import 'simplebar-vue/dist/simplebar.min.css';
import { useAuthStore } from '@/stores/auth';
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
    mounted(){
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
    <div class="left-bar-logo">
        <img src="/talk/img/logo.svg" alt="" title="" />
        <span>admin</span>
    </div>
    <div class="left-bar-menu">
        <Simplebar>
        <ul class="menu">
            <li><a href="/talkpanel/flist" alt=""><span class="kontent-icon" name="Menu5Icon"></span> Örnek listeleme</a></li>
            <!--<li><a href="/admin/index.html" alt="" class="active"><span class="kontent-icon" name="Menu1Icon"></span> Giriş</a></li>
            <li><a href="/admin/cari.html" alt=""><span class="kontent-icon" name="Menu2Icon"></span> Cari Ekstre</a></li>
            <li><a href="/admin/mutabakat-listesi.html" alt=""><span class="kontent-icon" name="Menu3Icon"></span> Mutabakat Listesi</a></li>
            <li><a href="/admin/imzali-mutabakat.html" alt=""><span class="kontent-icon" name="Menu4Icon"></span> İmzalı Mutabakat Talepleri</a></li>
            <li><a href="" alt=""><span class="kontent-icon" name="Menu5Icon"></span> Geçmiş Dönem Mutabakatlar</a></li>
            <li><a href="" alt=""><span class="kontent-icon" name="Menu6Icon"></span> Hata Bildir</a></li>
            <li><a href="" alt=""><span class="kontent-icon" name="Menu7Icon"></span> Kullanıcı Ayarları</a></li>-->
        </ul>
        </Simplebar>
    </div>
    <div class="left-bar-fast">
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