

<script>
    import { useAuthStore } from '@/stores/auth';
    import { useNavigationStore } from '@/stores/navigation'
    import { Calendar } from 'fullcalendar';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import { Datepicker } from 'vanillajs-datepicker';
    //import { Datepicker } from 'vanillajs-datepicker';
    import Simplebar from 'simplebar-vue';
    import 'simplebar-vue/dist/simplebar.min.css';

    export default {
        components: {
           Simplebar
        },
        setup() {
            document.querySelector('body').classList.add('sidebar-min');
            //Object.assign(Datepicker.locales, tr);
            // expose to template and other options API hooks
            return {
                useAuthStore,
                useNavigationStore,
                Plib,
                wTrans,
                Swal,
                Datepicker,
                Calendar,
                wTrans,
                
            }
        },
        data() {
            return {
                authStore       : useAuthStore(),
                navigationStore : useNavigationStore(),
                plib            : new Plib(),
                eventList       : []
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.navigationStore.setBread([
                {
                    title : 'Ana Sayfa',
                    url   : '/panel',
                },
                {
                    title : this.wTrans('menu.calendar'),
                    url   : '/panel/calendar',
                }
            ] ,this.wTrans('menu.calendar'));
            this.buildCalendar();

            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },
        methods: {
            async buildCalendar(){
                const CURRENT_MONTH = String(new Date().getMonth() + 1).padStart(2,'0');
                const CURRENT_YEAR = new Date().getFullYear();
                const data = await this.getData(CURRENT_YEAR+'-'+CURRENT_MONTH);

                const CALENDAR_EL = document.getElementById("calendar1");
                const calendar = new this.Calendar(CALENDAR_EL, {
                    initialView: 'dayGridMonth',
                    dayMaxEventRows: true,
                    height: 700,
                    
                    buttonIcons: {
                        prev: " ph ph-arrow-left", // Space is required at the beginning
                        next: " ph ph-arrow-right",
                        today: " ph ph-calendar-check",
                        dayGridMonth: " ph ph-squares-four",
                        timeGridWeek: " ph ph-rows",
                        timeGridDay: " ph ph-rectangle",
                    },
                    /*events : [
                        {
                            id: 1,
                            title: "Deserunt aliuipsit",
                            start: `${CURRENT_YEAR}-${FORMATTED_MONTH}-07T02:00:00`,
                        }
                    ],*/
                    /*events : this.eventList.map(evnt => {
                        return {
                            id    : evnt.id,
                            title : evnt.title,
                            start : evnt.start_date,
                            ...( evnt.end_date ? { end : evnt.end_date } : {allDay : true})
                        }
                    }),*/
                    events : async (info, successCallback, failureCallback) => {
                        const center = (new Date((info.start.getTime() + info.end.getTime()) / 2)).toISOString().split('-');
                        
                        return (await this.getData(center[0]+'-'+center[1])).map(evnt => {
                            return {
                                id    : evnt.id,
                                title : evnt.title,
                                start : evnt.start_date,
                                ...( evnt.end_date ? { end : evnt.end_date } : {allDay : true})
                            }
                        })
                    },
                    headerToolbar: {
                        left: "title",
                        center: "",
                        right: "prev today next dayGridMonth timeGridWeek timeGridDay",
                    },
                    //height: "100%",
                });
                calendar.render();
            },
            async getData(period){
                const rsp = await this.plib.request({
                    url      : '/api/v1/dashboard/monthlyEvents/'+period,
                    method   : 'GET',
                },null).then(rsp => {return rsp});

                const list = [];
                rsp.data.forEach(el => {
                    const attr = {
                        id : el.id,
                    };
                    JSON.parse(el.main_attr).forEach(at => {
                        attr[at['Key']] = at['Value'];
                    });
                    list.push(attr);
                });
                this.eventList = list;
                return list;
            }
        }
    }
</script>
<template>
    <div class="row">
        <div class="col-2">
            <div class="align-items-center d-flex fs-7 mb-1 text-body-secondary"> 
                <div class="flex-grow-1">{{$t('form.calendar.list')}}</div> 
                <a v-if="authStore.data.type == 'admin'" href="/panel/calendar/form" type="button" class="fs-6 icon ph ph-plus"> <span class="visually-hidden">Add</span> </a> 
            </div>
            
            <div id="calendar-events1" class="ms-n2" style="height: 70vh !important;">
                <Simplebar>
                <a v-for="event in eventList" :href="'/panel/calendar/form/'+event.id" class="hstack gap-3 p-2 rounded bg-hover lh-1 mb-0.5">
                    <i class="w-0.5 h-8 rounded bg-highlight"></i>
                    <div class="flex-grow-1 text-truncate">
                        <div class=" text-body text-truncate mb-2">{{event.title}}</div>
                        <div class="text-body-secondary fs-8 opacity-50">
                            {{event.start_date.split('-').reverse().join('.')}} 
                            {{event?.end_date ?  ' / '+event?.end_date?.split('-')?.reverse()?.join('.') : ''}}
                        </div>
                    </div>
                </a>
                </Simplebar>
            </div>
            
        </div>
        <div class="col-10">
            <div id="calendar1" class="calendar p-3 pt-0 fc fc-media-screen fc-direction-ltr fc-theme-standard"></div>
        </div>
    </div>
    
</template>