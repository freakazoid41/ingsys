<script>
    import { useNavigationStore } from '@/stores/navigation';
    import Plib from '@/lib/pickle';
    import DailyVisit from '@/pages/talk/dashboard/DailyVisit.vue';

    export default {
        components: {
            DailyVisit,
        },
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                Plib,
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            
            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },  
        data() {
            return {
                
                pageStatus      : 'list',
                plib            : new Plib(),
                navigationStore : useNavigationStore(),
            }
        },
        methods: {

        }
    }

</script>

<template>
    <div class="admin-dashboard">
                <div class="admin-dashboard-l" style="max-width: unset !important;">
                    <DailyVisit/>
                </div>
                <!--<div class="admin-dashboard-r">
                    <div class="list-card-type-2" style="display: none !important;">
                        <div class="list-card-type-2-head">
                            <button class="filter-button active">Güncel <span>7</span></button>
                            <button class="filter-button">Tümü <span>23</span></button>
                            <div class="search">
                                <button><span class="kontent-icon" name="PurpleSearch"></span></button>
                            </div>
                            <div class="dropdown order">
                                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Sıralama
                                </button>
                                <ul class="dropdown-menu">
                                  <li><button class="dropdown-item" href="#">Action</button></li>
                                  <li><button class="dropdown-item" href="#">Another action</button></li>
                                  <li><button class="dropdown-item" href="#">Something else here</button></li>
                                </ul>
                            </div>
                        </div>
                        <div class="list-card-type-2-body">
                            <div class="card">
                                <div class="card-head">
                                    <span class="icon"><span class="kontent-icon" name="Document"></span></span>
                                    <div class="card-head-title">
                                        <p>Ön Ödeme Avans Talebi</p>
                                        <h4>SAMSUN PİLİÇ SANAYİ ANONİM ŞİRKETİ</h4>
                                    </div>
                                    <p class="time"><span class="kontent-icon" name="Clock"></span>14:02</p>
                                    <span class="status success">Aktif</span>
                                    <div class="ntf">
                                        <button>•••</button>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <p class="date"><span class="kontent-icon" name="Calendar"></span>17.07.2024</p>
                                    <p class="cari">Cari Numara <span>98374520343</span></p>
                                    <p class="cari">Şirket kodu <span>AYDEM</span></p>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-head">
                                    <span class="icon"><span class="kontent-icon" name="Document"></span></span>
                                    <div class="card-head-title">
                                        <p>Ön Ödeme Avans Talebi</p>
                                        <h4>TAYKON ÇELİK İNŞAAT TAAHHÜT SANAYİ VE TİCARET LİMİTED ŞİRKETİ</h4>
                                    </div>
                                    <p class="time"><span class="kontent-icon" name="Clock"></span>14:02</p>
                                    <span class="status success">Aktif</span>
                                    <div class="ntf">
                                        <button>•••</button>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <p class="date"><span class="kontent-icon" name="Calendar"></span>17.07.2024</p>
                                    <p class="cari">Cari Numara <span>98374520343</span></p>
                                    <p class="cari">Şirket kodu <span>AYDEM</span></p>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-head">
                                    <span class="icon"><span class="kontent-icon" name="Document"></span></span>
                                    <div class="card-head-title">
                                        <p>Ön Ödeme Avans Talebi</p>
                                        <h4>NAKKAŞTEPE GAYRİMENKUL YATIRIMLARI</h4>
                                    </div>
                                    <p class="time"><span class="kontent-icon" name="Clock"></span>14:02</p>
                                    <span class="status success">Aktif</span>
                                    <div class="ntf">
                                        <button>•••</button>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <p class="date"><span class="kontent-icon" name="Calendar"></span>17.07.2024</p>
                                    <p class="cari">Cari Numara <span>98374520343</span></p>
                                    <p class="cari">Şirket kodu <span>AYDEM</span></p>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-head">
                                    <span class="icon"><span class="kontent-icon" name="Document"></span></span>
                                    <div class="card-head-title">
                                        <p>Ön Ödeme Avans Talebi</p>
                                        <h4>YENİ MAĞAZACILIK ANONİM ŞİRKETİ</h4>
                                    </div>
                                    <p class="time"><span class="kontent-icon" name="Clock"></span>14:02</p>
                                    <span class="status warning">Güncel</span>
                                    <div class="ntf">
                                        <button>•••</button>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <p class="date"><span class="kontent-icon" name="Calendar"></span>17.07.2024</p>
                                    <p class="cari">Cari Numara <span>98374520343</span></p>
                                    <p class="cari">Şirket kodu <span>AYDEM</span></p>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-head">
                                    <span class="icon"><span class="kontent-icon" name="Document"></span></span>
                                    <div class="card-head-title">
                                        <p>Ön Ödeme Avans Talebi</p>
                                        <h4>SAMSUN PİLİÇ SANAYİ ANONİM ŞİRKETİ</h4>
                                    </div>  
                                    <p class="time"><span class="kontent-icon" name="Clock"></span>14:02</p>
                                    <span class="status danger">Red</span>
                                    <div class="ntf">
                                        <button>•••</button>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <p class="date"><span class="kontent-icon" name="Calendar"></span>17.07.2024</p>
                                    <p class="cari">Cari Numara <span>98374520343</span></p>
                                    <p class="cari">Şirket kodu <span>AYDEM</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>-->
            </div>
</template>