
<script>
    import { useNavigationStore } from '@/stores/navigation'
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import { Datepicker } from 'vanillajs-datepicker';
    import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';
    import Simplebar from 'simplebar-vue';
    import 'simplebar-vue/dist/simplebar.min.css';
    import Swal from 'sweetalert2';

    export default {
        components: {
            Simplebar
        },
        setup() {
            Object.assign(Datepicker.locales, tr);
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                Datepicker
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.fetchLogs();
            
            this.navigationStore.setBread([
                {
                    title : this.wTrans('menu.home'),
                    url   : '/kontent',
                },
                {
                    title : this.wTrans('menu.facility'),
                    url   : '/kontent/facility',
                }
            ] ,this.wTrans('form.facility.list'));

            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },  
        data() {
            return {
                pageStatus      : 'list',
                plib            : new Plib(),
                navigationStore : useNavigationStore(),
                list            : [],
                pos             : 0
            }
        },
        methods: {
            fetchLogs() {
                fetch(`/live-logs/fetch?pos=${this.pos}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.lines.length > 0) {
                                data.lines.forEach(element => {
                                    this.list.unshift(element);
                                });

                                if (this.list.length > 10) {
                                    this.list.splice(10, this.list.length - 10);
                                }
                            }
                            
                            this.pos = data.pos;
                            this.timeoutId = setTimeout(this.fetchLogs, 800);
                        });
                }
        },
        unmounted() {
            return clearTimeout(this.timeoutId);
        }
    }

</script>
<template>
    <!--<div class="card">
        <div class="card-body">
            <div id="div_table"></div>
        </div>
    </div>-->
    <div class="table-tab">
        <div class="table-tab-head">
            <button href="javascript:;" class="table-toggle active" body="1">Canlı Log Takibi</button>
        </div>
        <div class="table-tab-body">
            <div class="table-custom-filter mb-5">
                
               
                <!--<div class="table-custom-filter-date">
                    <p>Tarih Aralığı</p>
                    <div class="date-form">
                        <label for="startDate">Başlangıç Tarihi:</label>
                        <input type="text" id="startDate" placeholder="--.--.---">
                        <span class="icon"><span class="kontent-icon" name="DownFeth"></span></span>
                    </div>
                    <div class="date-form">
                        <label for="endDate">Bitiş Tarihi:</label>
                        <input type="text" id="endDate" placeholder="--.--.----">
                        <span class="icon"><span class="kontent-icon" name="DownFeth"></span></span>
                    </div>
                </div>-->
            </div>
            
            <div class="body-1 table-main">
                    <Simplebar style="height: 500px !important;">
                    <div id="div_table" >
                        
                        <table class="table" >
                            <thead>
                                <tr>
                                    <th>Detay</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in list">
                                    <td>{{ item }}</td>
                                </tr>
                            </tbody>
                        </table>
                        
                    </div>
                    </Simplebar>
            </div>
           
        </div>
    </div>
</template>
