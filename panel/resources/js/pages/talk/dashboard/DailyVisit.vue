<script>

    import Plib from '@/lib/pickle';
    import Simplebar from 'simplebar-vue';
    import 'simplebar-vue/dist/simplebar.min.css';
    import { wTrans } from 'laravel-vue-i18n';
    import VueApexCharts from "vue3-apexcharts";
import dayjs from 'dayjs';


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
                this.dailyVisit.total = rsp.totalCount;
            });

            this.plib.request({
                url      : '/api/v1/dashboard/getDailyLogs',
                method   : 'GET',
            },null).then(rsp => {
                
                this.dailyVisit.error500   = 0;
                this.dailyVisit.error404   = 0;
                this.dailyVisit.errorTotal = rsp.totalCount;
                //this.dailyVisit.data       = rsp.data;
                for(let i = 0;i<rsp.totalCount; i++){
                    const row = JSON.parse(rsp.data[i].description);
                    if(row.status_code == 500){
                        this.dailyVisit.error500 += 1;
                    }
                    if(row.status_code == 404){
                        this.dailyVisit.error404 += 1;
                    }
                    if(i < 20){
                        row.createdAt = dayjs(rsp.data[i].created_at).format('DD.MM.YYYY HH:mm');
                        this.dailyVisit.data.push(row);
                    }
                }
            });
        },
        methods: {
            handleVisit(visit) {
                if (visit.url) {
                    try {
                        window.open(visit.url, '_blank');
                    } catch (e) {
                        console.error('Error opening URL:', e);
                    }
                }
            }
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
                            <div class="pie animate" :style="'--p:'+(((dailyVisit.error500) / dailyVisit.errorTotal) * 100)">
                                <div class="detail">
                                    <p>Günlük 500 Hataları <span>{{ dailyVisit.error500}}</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="carousel-caption card">
                        <span class="icon"><span class="kontent-icon" name="User"></span></span>
                        <div class="pie-main">
                            <div class="pie animate" :style="'--p:'+((dailyVisit.error404 / dailyVisit.errorTotal) * 100)">
                                <div class="detail">
                                    <p>Günlük 404 Hataları<span>{{ dailyVisit.error404 }}</span></p>
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
            
            <div class="list-card justify-content-between selectable-icon" v-for="visit in dailyVisit.data" @click="handleVisit(visit)">
                
                 
                <div class="list-card-head" style="max-width: unset !important;" >
                    <h4 >{{ visit.url }}</h4>
                </div>
                <p class="list-card-status me-5" :class="{'text-danger':visit.status_code == 500}" style="width: 200px !important;">
                    <i :class="{'fa fa-exclamation' : visit.status_code == 500,'fa fa-eye' :visit.status_code != 500}"></i>
                    {{ visit.status_code }}
                </p>
                <span>
                    {{ visit.createdAt }}
                </span>
              
                

               
                
            </div>
            
        </div>
    </div>
</template>