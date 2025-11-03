<script>
import { useEventDataStore } from '@/stores/events';
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation'
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';
import notification from 'duct-notification'

export default {
    components: {},
    setup() {
        // expose to template and other options API hooks
        return {
            useNavigationStore,
            useEventDataStore,
            useAuthStore,
            Plib,
            Swal
        }
    },
    mounted() {
        this.authDataStore.getData();

        //clear old notification interval
        if(this.navigationStore.notificationInterval) clearInterval(this.navigationStore.notificationInterval);
        //set new notification interval
        this.navigationStore.notificationInterval = setInterval(() => {
            this.getNotifications();
        }, 1000);
        

        /*notification({
            type: 'success',
            head: 'Information Notification',
            message: 'Lorem ipsum, dolor sit amet consectetur adipisicing elit. Dicta quaerat1 <b>TalkHeader 30. satır</b>',
            timer: 15000,
            hideClose: false
        })*/
        //if(this.taskDataStore.tasks.length == 0) this.taskDataStore.setTaskData();
        //if(this.taskDataStore.events.length == 0) this.taskDataStore.setEventData();
    },
    data() {
        return {
            nofifsList        : [],
            showBlink         : false,
            plib              : new Plib(),
            taskDataStore     : useEventDataStore(),
            navigationStore   : useNavigationStore(),
            authDataStore     : useAuthStore(),
            title             : document.querySelector('input[name="header"]').value
        };
    },
    methods: {
        async getNotifications() {
            this.plib.request({
                url: '/api/v1/dashboard/getLastNotifications',
                method: 'GET',
            }, null).then(rsp => {
                this.showBlink = rsp.newCount > 0;
                this.nofifsList = rsp.data;
            });
        },
        showNotifications() {
            Swal.fire({
                html: '<style>.swal2-modal{width:800px !important;}</style><div class="row w-100"><div class="col-12"><div id="logTable"></div></div></div></div>',
                showConfirmButton: false,
                showCloseButton: true,
                willOpen: () => {
                    this.table = new PickleTable({
                        container: '#logTable',
                        nextPageIcon: '<i class="fa fa-solid fa-chevron-right"></i>',
                        prevPageIcon: '<i class="fa fa-solid fa-chevron-left"></i>',
                        headers: [{
                            title: 'Tip',
                            key: 'type',
                            order: false,

                            headAlign: 'left',
                            type: 'string',
                        }, {
                            title: 'Tarih',
                            key: 'created_at',
                            order: true,
                            width: '250px',
                            headAlign: 'left',
                            type: 'string',
                            columnFormatter: (elm, rowData, columnData) => {
                                return columnData.split(' ')[0].split('-').reverse().join('.') + ' / ' + columnData.split(' ')[1];
                            }
                        }, {
                            title: 'Açıklama',
                            key: 'description',
                            order: true,
                            headAlign: 'left',
                            type: 'string',
                            columnFormatter: (elm, rowData, columnData) => {
                                return JSON.parse(columnData).desc;
                            }
                        }/*{
                                title:'Detay',
                                key:'id',
                                order:false,
                                width : '100px',
                                headAlign:'center',
                                type:'string',
                                columnFormatter : (elm,rowData,columnData) => {
                                    elm.style.textAlign = 'center';


                                    let link = '/secpanel/visit/form/'+columnData
                                    

                                    const tab          = document.createElement('a');
                                

                                    tab.href      = link;
                                    tab.innerHTML = `Detay <i class="ki-duotone ki-right fs-5 ms-1"></i>`;
                                    tab.onclick   = () => this.plib.processLoading();
                                    tab.classList.add('btn','btn-sm','btn-light','btn-flex','btn-center','btn-active-light-primary"');
                                    return tab;
                                }
                            }*/
                        ],
                        type: 'ajax',
                        height: '400px',
                        columnSearch: false, // true - false for opening and closig
                        paginationType: 'number',// scroll - number (number for default)
                        pageLimit: 20, // put '-1' for getting all data
                        initialFilter: [],
                        ajax: {
                            url: '/api/v1/table/user_logs',
                            data: {
                                //order:{},
                            }
                        },
                    });
                },
            });
        },
        async notcheck(e){
            const ielm = document.querySelector('i[data-ic="'+e.id+'"]');
            if(ielm != null){
                this.navigationStore.active = true;
                const   envelope = new FormData();
                        envelope.append('notid',e.id); // main info 
                const rsp = await this.plib.request({
                    url      : '/api/v1/setnotificationstatus',
                    method   : 'POST',
                },null,envelope);
                
                if(rsp.success){
                    
                    this.getNotifications();

                    ielm.remove();
                    this.$router.push({ name: 'VList' });
                    this.navigationStore.active = false;
                }
            }else{
                this.$router.push({ name: 'VList' });
            }
            
        }
    }
}
</script>

<template>
    <div class="right-bar-header">
        <div class="right-bar-header-head">
            <h1
                v-html="navigationStore?.currentTitle == '' ? 'Seç Sistemine <b>Hoş geldiniz.</b>' : navigationStore?.currentTitle">
            </h1>
            <ul class="breadcrumb">
                <li v-for="item in navigationStore.breadcrumps">
                    <a :href=item.url>{{ item.title }}</a>
                </li>
            </ul>
        </div>
        <div class="right-bar-header-menu">
            <div class="dropdown">
                <button class="h-button" type="button" data-bs-toggle="dropdown"
                    aria-expanded="false">
                    <span class="kontent-icon" name="Notification"></span>
                    <span class="ntf" v-if="showBlink"></span>
                </button>
                <ul class="dropdown-menu ntf-label-main">
                    <li v-for="row in nofifsList">
                        <div class="ntf-label-list" @click="notcheck(row)">
                            <span class="d-flex justify-content-between align-items-center">{{ JSON.parse(row.description).desc }} <i :data-ic="row.id" class="fa fa-circle" v-if="!row.is_new" style="color:red"></i></span>
                            <span class="text-muted">{{ row.created_at.split(' ')[0].split('-').reverse().join('/')+' '+row.created_at.split(' ')[1] }}</span>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item" @click="showNotifications()">Tümünü Görüntüle</button></li>
                </ul>
            </div>
            <!-- <button class="h-button" @click="showNotifications()">
                
            </button> -->
            <!--<button class="h-button notification"><span class="kontent-icon" name="Notification"></span><span class="ntf"></span></button> 
            <a href="" alt="" class="h-button settings"><span class="kontent-icon" name="Setting"></span></a> -->
            <div class="user-management h-button">
                <button>
                    <span><span class="kontent-icon" name="HeaderUser"></span></span>
                    <p>{{ authDataStore?.data?.ptitle }}<small>{{ authDataStore?.data?.type_key == 'op-pert-admin' ?
                        'Yönetici' : 'Normal Kullanıcı' }}</small></p>
                </button>
                <!--<div class="user-management-detail">
                    
                    <router-link :to="{ name: 'FList' }"><a href="javascript:;" class="dropdown-item"> <i class="ph ph-buildings text-body-emphasis"></i> Tesis Listesi </a></router-link>
                </div>-->
            </div>
            <a href="/logout" alt="" class="h-button logout"><span class="kontent-icon" name="Logout"></span></a>
            <button class="mobileButton"><span class="kontent-icon" name="MobileButton"></span></button>
        </div>
    </div>
</template>