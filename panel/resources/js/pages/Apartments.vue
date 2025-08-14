<style>
    .apartments #content{
        position: relative;
        padding-left: 1rem !important;
    }
    .apartments .logo,
    .apartments .header-menu,
    .apartments .sidebar-toggle,
    .apartments #sidebar{
        display: none !important;
    }



    .apartment-icon i{
        font-size: 150px !important;
    }

    .apartment-icon:hover:not(.bar){
        background-color:#0000004d !important;
        cursor: pointer;
    }

    /*.bar .apt-text,
    .bar .ph {
        display: none !important;
        
    }
    .add-icon > * {
        pointer-events: none !important;
    }
    .apt-form {
        display: none;
    }
    .bar .apt-form{
        display: block !important;
    }
    .bar {
        position: fixed;
        
        z-index: 999;
        width: 500px;
        height: auto;
        background-color:#000000f7 !important;
        
        transform: translate(0%, 50%);
        transition: 0.5s ease-in;
    }*/
</style>
<script>
  import { useNavigationStore } from '@/stores/navigation';
  import { useAuthStore } from '@/stores/auth';
  import 'pickletable/assets/style.css';
  import Plib from '@/lib/pickle';
  import { wTrans } from 'laravel-vue-i18n';
  import Swal from 'sweetalert2';
  import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';
  import Simplebar from 'simplebar-vue';
  import 'simplebar-vue/dist/simplebar.min.css';




    export default {
        components: {
            Simplebar
        },
        setup() {

            document.body.classList.add('apartments')
            
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                Plib,
                wTrans,
                Swal,
                useAuthStore
            }
        },
        data() {
            return {
                plib            : new Plib(),
                authStore       : useAuthStore(),
                navigationStore : useNavigationStore(),
                apartments      : []
            }
        },
        mounted(){
            
            this.buildPage();
            
        },  
      
        methods: {
            async buildPage(){
                this.navigationStore.toggle(true);
                await this.navigationStore.setApartments();
                this.apartments = this.navigationStore.apartments
                setTimeout(() => {
                    this.navigationStore.toggle(false);
                }, 500);
                
                
            },
            newApartment(){
                Swal.fire({
                    customClass         : {
                        confirmButton : "btn btn-secondary me-3",
                        cancelButton  : "btn btn-secondary me-3"
                    },
                    showLoaderOnConfirm : true,
                    allowOutsideClick   : false,
                    showCloseButton     : true,
                    confirmButtonText   : 'Kaydet ve Giriş Yap',
                    cancelButtonText    : 'İptal',
                    showCancelButton    : true,
                    html : `<style>
                                .swal2-popup{background-color:#000000f7 !important;}
                            </style>
                            <div class="row apt-form mt-5">
                                <div class="col-12">
                                    <div class="border mb-8 p-5 rounded"> 
                                        <label for="inputPassword5" class="form-label">Apartman İsmi Giriniz</label> 
                                        <input type="text" name="title" class="form-control mb-5" aria-describedby="passwordHelpBlock"> 
                                        <div id="passwordHelpBlock" class="form-text"> 
                                            Giriceğiniz yeni apartman bundan böyle bu sayfada gözükmeye başlayacaktır...
                                        </div> 
                                    </div>
                                </div>
                            </div>`,
                    willOpen : () => {
                        document.querySelector('input[name="title"]').addEventListener('keypress', event => {
                            // If the user presses the "Enter" key on the keyboard
                            if (event.keyCode == 13) {
                                // Cancel the default action, if needed
                                event.preventDefault();
                                // Trigger the button element with a click
                                document.querySelector('.swal2-confirm').click();
                            }
                        })
                    },
                    preConfirm : async () => {
                        this.navigationStore.toggle(true);
                        const   envelope  = new FormData();
                        envelope.append('title',document.querySelector('input[name="title"]').value.trim());
                        
                        await this.plib.request({
                            url      : '/api/v1/set-apartments',
                            method   : 'POST',
                        },null,envelope);

                        await this.navigationStore.setApartments();

                        setTimeout(() => {
                            this.navigationStore.toggle(false);
                        }, 300);
                    }
                });

            }
        }
    }

</script>

<template>
    <div class="row justify-content-center">
        <div @click="newApartment()" class="bg-100-hover add-icon rounded col-md-4 col-sm-6 col-lg-2 p-3 text-center apartment-icon">
            <i class="ph ph-plus text-body-emphasis"></i>
            <div class="hidden sm:block text-body-secondary fs-1 mt-2 apt-text">Yeni Ekle</div>
            <!--<div class="row apt-form">
                <div class="col-12">
                    <div class="border mb-8 p-5 rounded"> 
                        <label for="inputPassword5" class="form-label">Apartman İsmi Giriniz</label> 
                        <input type="text" class="form-control mb-5" aria-describedby="passwordHelpBlock"> 
                        <div id="passwordHelpBlock" class="form-text"> 
                            Giriceğiniz yeni apartman bundan böyle bu sayfada gözükmeye başlayacaktır...
                        </div> 
                    </div>
                </div>
            </div>-->
        </div>
        <a v-for="(value, key) in apartments" :key="key" :href="'/setapartment/'+key" class="bg-100-hover rounded col-md-4 col-sm-6 col-lg-2 p-3 text-center apartment-icon">
            <i class="ph ph-buildings text-body-emphasis"></i>
            <div class="hidden sm:block text-body-secondary fs-1 mt-2">{{ value.title }}</div>
        </a>
        <a href="/logout" class="bg-100-hover rounded col-md-4 col-sm-6 col-lg-2 p-3 text-center apartment-icon">
            <i class="ph ph-sign-out text-body-emphasis"></i>
            <div class="hidden sm:block text-body-secondary fs-1 mt-2">Çıkış</div>
        </a>

    </div>
    
        
</template>
