<script>
    import { useNavigationStore } from '@/stores/navigation'
    import Plib from '@/lib/pickle';
   
    export default {
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
                <div class="admin-dashboard-l">
                    <div class="min-card">
                        <div class="card">
                            <span class="icon"><span class="kontent-icon" name="Attach"></span></span>
                            <a href="#" alt="" class="stretched-link">Onaylanan Mutabakatlar</a>
                            <p><span>301</span>Tüm mutabakatlar</p>
                            <span class="arrow"><span class="kontent-icon" name="DownRight"></span></span>
                        </div>

                        <div id="carouselCard" class="carousel slide w-100">
                            <div class="carousel-indicators">
                                <button type="button" data-bs-target="#carouselCard" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                                <button type="button" data-bs-target="#carouselCard" data-bs-slide-to="1" aria-label="Slide 2"></button>
                                <button type="button" data-bs-target="#carouselCard" data-bs-slide-to="2" aria-label="Slide 3"></button>
                                <button type="button" data-bs-target="#carouselCard" data-bs-slide-to="3" aria-label="Slide 4"></button>
                            </div>
                            <div class="carousel-inner">
                                <div class="carousel-item active w-100">
                                    <div class="carousel-caption card w-100">
                                        <span class="icon"><span class="kontent-icon" name="User"></span></span>
                                        <div class="pie-main">
                                            <div class="pie animate" style="--p: 40;">
                                                <div class="detail">
                                                    <p>Giriş Yapan Kullanıcı <span>1.032</span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="carousel-item">
                                    <div class="carousel-caption card">
                                        <span class="icon"><span class="kontent-icon" name="User"></span></span>
                                        <div class="pie-main">
                                            <div class="pie animate" style="--p: 70;">
                                                <div class="detail">
                                                    <p>Giriş Yapan Kullanıcı <span>1.032</span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="carousel-item">
                                    <div class="carousel-caption card">
                                        <span class="icon"><span class="kontent-icon" name="User"></span></span>
                                        <div class="pie-main">
                                            <div class="pie animate" style="--p: 20;">
                                                <div class="detail">
                                                    <p>Giriş Yapan Kullanıcı <span>1.032</span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="carousel-item">
                                    <div class="carousel-caption card">
                                        <span class="icon"><span class="kontent-icon" name="User"></span></span>
                                        <div class="pie-main">
                                            <div class="pie animate" style="--p: 90;">
                                                <div class="detail">
                                                    <p>Giriş Yapan Kullanıcı <span>1.032</span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="big-card mt-4">
                        <div class="big-card-head">
                            <h3>Giriş Kayıtları</h3>
                            <div class="big-card-head-filter">
                                <button class="filter-button">T.C. No</button>
                                <button class="filter-button active">VKN</button>
                            </div>
                        </div>
                        <div class="chart-main">
                            <div class="chart-line-detail">
                                <span>Bugün</span>
                                <span>Dün</span>
                            </div>
                            <div class="chart" id="chart"></div>
                        </div>
                        <div class="chart-main-list">
                            <div class="list-card">
                                <div class="form-check">
                                    <div class="checkbox-theme">
                                        <input type="checkbox" id="checkMeOut"/>
                                        <label for="checkMeOut">
                                          <svg viewBox="0,0,50,50">
                                            <path d="M5 30 L 20 45 L 45 5"></path>
                                          </svg>
                                        </label>
                                    </div>
                                </div>
                                <div class="list-card-head">
                                    <h4>VKN 2965093</h4>
                                    <span>Ön Ödeme Avans Talebi</span>
                                </div>
                                <p class="list-card-status"><span class="kontent-icon" name="Send"></span> Gönderim başarılı</p>
                                <p class="date-time">19.07.2024 <span>12:04 <span class="kontent-icon" name="Clock"></span></span></p>
                            </div>
                            <div class="list-card">
                                <div class="form-check">
                                    <div class="checkbox-theme">
                                        <input type="checkbox" id="checkMeOut1"/>
                                        <label for="checkMeOut1">
                                          <svg viewBox="0,0,50,50">
                                            <path d="M5 30 L 20 45 L 45 5"></path>
                                          </svg>
                                        </label>
                                    </div>
                                </div>
                                <div class="list-card-head">
                                    <h4>VKN 2965093</h4>
                                    <span>Kullanıcı hatalı işlem yaptı</span>
                                </div>
                                <p class="list-card-status"><span class="kontent-icon" name="Danger"></span> ————</p>
                                <p class="date-time">19.07.2024 <span>12:04 <span class="kontent-icon" name="Clock"></span></span></p>
                            </div>
                            <div class="list-card">
                                <div class="form-check">
                                    <div class="checkbox-theme">
                                        <input type="checkbox" id="checkMeOut1"/>
                                        <label for="checkMeOut1">
                                          <svg viewBox="0,0,50,50">
                                            <path d="M5 30 L 20 45 L 45 5"></path>
                                          </svg>
                                        </label>
                                    </div>
                                </div>
                                <div class="list-card-head">
                                    <h4>VKN 2965093</h4>
                                    <span>Dosya yüklemesi yapıldı</span>
                                </div>
                                <p class="list-card-status"><span class="kontent-icon" name="Download"></span> ————</p>
                                <p class="date-time">19.07.2024 <span>12:04 <span class="kontent-icon" name="Clock"></span></span></p>
                            </div>
                            <div class="list-card">
                                <div class="form-check">
                                    <div class="checkbox-theme">
                                        <input type="checkbox" id="checkMeOut1"/>
                                        <label for="checkMeOut1">
                                          <svg viewBox="0,0,50,50">
                                            <path d="M5 30 L 20 45 L 45 5"></path>
                                          </svg>
                                        </label>
                                    </div>
                                </div>
                                <div class="list-card-head">
                                    <h4>VKN 4562793</h4>
                                    <span>Giriş yaptı</span>
                                </div>
                                <p class="list-card-status"><span class="kontent-icon" name="Success"></span> Başarılı</p>
                                <p class="date-time">19.07.2024 <span>12:04 <span class="kontent-icon" name="Clock"></span></span></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="admin-dashboard-r">
                    <div class="list-card-type-2">
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
                </div>
            </div>
</template>