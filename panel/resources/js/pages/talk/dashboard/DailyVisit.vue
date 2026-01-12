<script>

    import Plib from '@/lib/pickle';
    import Simplebar from 'simplebar-vue';
    import 'simplebar-vue/dist/simplebar.min.css';
    import { wTrans } from 'laravel-vue-i18n';
    import VueApexCharts from "vue3-apexcharts";


    export default {
        props: {
            qtype : {
                type: String
            },
            qcolclass : {
                type: String
            },
        },
        components: {
            VueApexCharts,
            Simplebar
        },
        setup() {
            // expose to template and other options API hooks
            return {
                Plib,
            }
        },
        data() {
            return {
                plib            : new Plib(),
                dailyVisit      : {
                    notExited  : 0,
                    total      : 0,
                    facilities : {},
                    data       : []
                },
                chartOptions: {
                    series: [{
                        name: 'Inflation',
                        data: [2.3, 3.1, 4.0, 10.1, 4.0, 3.6, 3.2, 2.3, 1.4, 0.8, 0.5, 0.2]
                    }],
                    chart: {
                        type: 'bar',
                    },
                    plotOptions: {
                        bar: {
                            borderRadius: 10,
                            dataLabels: {
                                position: 'top', // top, center, bottom
                            },
                        }
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function (val) {
                            return val;
                        },
                        offsetY: -30,
                        style: {
                            fontSize: '14px',
                            colors: ["#304758"]
                        }
                    },
                    xaxis: {
                        categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
                        
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        },
                        
                        crosshairs: {
                            fill: {
                            type: 'gradient',
                                gradient: {
                                    colorFrom: '#D8E3F0',
                                    colorTo: '#BED1E6',
                                    stops: [0, 100],
                                    opacityFrom: 0.4,
                                    opacityTo: 0.5,
                                }
                            }
                        },
                        tooltip: {
                            enabled: true,
                        }
                    },
       
                },
            }
        },
        mounted() {
            this.plib.request({
                url      : '/api/v1/dashboard/webSites',
                method   : 'GET',
            },null).then(rsp => {
                console.log(rsp);
                this.dailyVisit.total = rsp.totalCount;
            });
        },
        methods: {

        }
    }

</script>


<template>
    <div class="min-card row m-0">
        <div class="card col-md-6 col-lg-3">
            <span class="icon"><span class="kontent-icon" name="Attach"></span></span>
            <router-link :to="{ name: 'FList' }">
                <a href="#" alt="" class="stretched-link">Web Siteleri</a>
            </router-link>
            <p>
                <span>{{ dailyVisit.total }}</span>
                Tüm Web Siteleri
            </p>
            <span class="arrow"><span class="kontent-icon" name="DownRight"></span></span>
        </div>

        <div id="carouselCard" class="carousel slide col-md-6 col-lg-3">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#carouselCard" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#carouselCard" data-bs-slide-to="1" aria-label="Slide 2"></button>
                
            </div>
            <div class="carousel-inner">
                <div class="carousel-item active w-100">
                    <div class="carousel-caption card w-100">
                        <span class="icon"><span class="kontent-icon" name="User"></span></span>
                        <div class="pie-main">
                            <div class="pie animate" :style="'--p:'+(((dailyVisit.total) / dailyVisit.total) * 100)">
                                <div class="detail">
                                    <p>Günlük 500 Hataları <span>{{ dailyVisit.total}}</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="carousel-caption card">
                        <span class="icon"><span class="kontent-icon" name="User"></span></span>
                        <div class="pie-main">
                            <div class="pie animate" :style="'--p:'+((dailyVisit.notExited / dailyVisit.total) * 100)">
                                <div class="detail">
                                    <p>Günlük 404 Hataları<span>{{ dailyVisit.notExited }}</span></p>
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
            <h3 style="max-width: unset !important;">Günlük Log Kayıtları</h3>
        </div>
        <!--<div class="chart-main">
           
            <VueApexCharts class="pt-5" :options="chartOptions" :series="chartOptions.series"/>
        </div>-->
        <div class="chart-main-list mt-3"  style="height: 400px !important;overflow-y: auto;">
            
            <div class="list-card" v-for="visit in dailyVisit.data">
                
                <div class="list-card-head">
                    <h4>{{ visit.name }}</h4>
                    <span>{{ visit.phone }}</span>
                </div>
                <p class="list-card-status me-5" :class="{'text-danger':visit.exited_at == undefined}" style="width: 200px !important;">
                   <i :class="{'fa fa-check' : visit.exited_at != undefined,'fa fa-clock' :visit.exited_at == undefined}"></i>
                    {{ visit.exited_at == undefined ? 'İçeride' : 'Çıkış Yaptı'}}
                </p>
                <p class="list-card-status"><i class="fa fa-building"></i> {{ visit.facility }}</p>
              
                <p class="date-time">
                    {{ visit.entered_at.split(' ')[0].split('-').reverse().join('/') }} 
                    <span>
                        <i class="fa fa-clock me-2"></i>{{ visit.entered_at.split(' ')[1] }} 
                    </span>
                </p>

                <p class="date-time text-info" v-if="visit.exited_at !== undefined">
                    {{ visit.exited_at.split(' ')[0].split('-').reverse().join('/') }} 
                    <span>
                        <i class="fa fa-clock me-2"></i>
                        {{ visit.exited_at.split(' ')[1] }} 
                    </span>
                </p>
                
            </div>
            
        </div>
    </div>
</template>