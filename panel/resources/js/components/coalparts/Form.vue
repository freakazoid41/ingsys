<script>
    import Plib from '@/lib/pickle';
    import AppFab from '@/components/coalparts/AppFab.vue';
    import flatpickr from 'flatpickr';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect/index.js';
    import { Turkish } from 'flatpickr/dist/l10n/tr.js';
    import TreeModal from '@/lib/treeModal.js';
    import { generatePassword } from '@/lib/password';
    import VMasker  from 'vanilla-masker';
    import { wTrans } from 'laravel-vue-i18n';
     import { useRoute } from 'vue-router';
    import { useFormDataStore } from '@/stores/formdata';
    import { usePermissionDataStore } from '@/stores/permissiondata';
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import Swal from 'sweetalert2';
    import dayjs from 'dayjs';


    export default {
        props: {
            formtypes : {
                type: String
            },
            fabtype : {
                type: String
            },
            savecallback: {
                type: Function
            },
            savebtntitle:{
                type: String
            },
            rejectcallback: {
                type: Function
            },
            acceptcallback: {
                type: Function
            }
        },
        components: {
            AppFab,
            
        },
        setup() {
            // localize flatpickr to Turkish
            flatpickr.localize(Turkish);
            // expose to template and other options API hooks
            return {
                
                usePermissionDataStore,
                useFormDataStore,
                flatpickr,
                AppFab,
                Plib,
                VMasker,
                wTrans,
                useAuthStore,
                Swal,
                PickleTable,
                dayjs
            }
        },
        data() {
            const usersClientClick = (e,element) => {
                Swal.fire({
                    title: 'Cari Seçiniz',
                    showCloseButton: true,
                    showConfirmButton: false,
                    html: ` <style>
                                /* make the full row red when any cell is hovered */
                                .pickletable tr:hover td {
                                    background-color: #154b91 !important;
                                    color : white !important;
                                }
                                .pickletable tr:hover {
                                    border-radius: 10px !important;
                                }

                                .pickletable tr:hover td:first-child {
                                    border-top-left-radius: 10px !important;
                                    border-bottom-left-radius: 10px !important;
                                }

                                .pickletable tr:hover td:last-child {
                                    border-top-right-radius: 10px !important;
                                    border-bottom-right-radius: 10px !important;
                                }
                            </style>
                            <div id="client-tree" class="text-start"></div>`,
                    willOpen : () => {
                        this.buildClientTable('#client-tree',(data) => {
                            //check if same client is already added
                            const existingClients = document.querySelectorAll('input[name^="cliid"]');
                            let isDuplicate = false;
                            
                            existingClients.forEach((input) => {
                                if(input.value === data.id.toString() && input.value !== '') {
                                    isDuplicate = true;
                                }
                            });
                            
                            if(isDuplicate) {
                                this.plib.toast(this.Swal, 'warning', 'Bu cari zaten eklenmiştir!');
                                return false;
                            }

                            //element.value = data.clicode;
                            const codeElm = element.parentNode.parentNode.parentNode.querySelector('input[name^="clicode"]');
                            const titleElm = element.parentNode.parentNode.parentNode.querySelector('input[name^="clititle"]');
                            const idElm   = element.parentNode.parentNode.parentNode.querySelector('input[name^="cliid"]');
                            titleElm.value = data.title;
                            idElm.value    = data.id;
                            codeElm.value  = data.clicode; 
                           
                            codeElm.dispatchEvent(new Event('input'));
                            idElm.dispatchEvent(new Event('input'));
                            titleElm.dispatchEvent(new Event('input'));
                            Swal.close();
                        });
                        
                    }
                });
            };
          
            return {
                permList        : this.usePermissionDataStore().list,
                authStore       : useAuthStore(),
                formDataStore   : useFormDataStore(),
                route           : useRoute(),
                plib            : new Plib(),
                isLoadingRoles  : true,
                ftypes          : this.formtypes.split(','),
                forms           : {
                    'op-doc-flat-form' : {
                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_4',
                                label : ' ',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'title',
                                        col      : 3,
                                        required : true,
                                        label : 'Daire İsmi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1',
                                label : '',
                                type  : 'multiple',
                                group_key : 'flatcontgroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cont_name',
                                        //required : true,
                                        label : 'Kat Malik Adı',
                                        col      : 6,
                                        placeholder : 'Kat Malik Adı',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cont_phone',
                                        isMasked : true,
                                        mask : 'phone',
                                        label : 'Kat Malik Telefon',
                                        col      : 6,
                                        placeholder : 'Kat Malik Telefon',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    },'op-doc-user-form'    : {
                        showRemoveButton : false,
                        oncreated        : (id,row) => {
                            if(this.permissionTree.getChecked().length == 0){
                                row.querySelector('select[name="user_role"]').dispatchEvent(new Event('input'));
                            }
                        },
                        fields           : [
                            {
                                class : ['btn','btn-outline','custom-button'],
                                type  : 'button',
                                name  : 'createpss',
                                col : 4,
                                value : 'Şifre Üret',
                                label : ' ',
                                oninput : async (e) => {
                                    e.preventDefault();

                                    const password = generatePassword(8);
                                    const main  = document.querySelector('input[name="user_password"]');
                                    const check = document.querySelector('input[name="user_password_check"]');

                                    main.value  = password;
                                    check.value = password;

                                    //DOM'a yazmak yetmiyor: kaydetme payload'i formData.dynamicF'ten
                                    //okunuyor (UForm.vue), DOM'dan degil. Model guncellenmedigi icin
                                    //uretilen parola backend'e hic gitmiyordu.
                                    this.submitDynamicChanges(main);
                                    this.submitDynamicChanges(check);

                                    this.formData.passwordGenerated = true;

                                    //Parola bu modalda BILEREK gosterilmiyor: kaydetme basarisiz
                                    //olursa yonetici kopyaladigi parolanin calismadigini sonradan
                                    //ogrenirdi - issue #8'in yazilma sebebi tam olarak buydu.
                                    //Parola yalnizca kayit basariyla tamamlandiktan sonra gosterilir.
                                    const confirmed = await Swal.fire({
                                        icon  : 'info',
                                        title : 'Şifre üretildi',
                                        text  : 'Yeni şifre form alanlarına yazıldı. Form şimdi kaydedilsin mi? Kaydetme tamamlandığında şifreyi kopyalayabileceğiniz bir pencere açılacak.',
                                        showCancelButton  : true,
                                        confirmButtonText : 'Devam Et',
                                        cancelButtonText  : 'İptal Et',
                                        reverseButtons    : true,
                                    });

                                    //Iptal: kaydetme tetiklenmez. Uretilen parola alanlarda kalir,
                                    //yonetici isterse diger alanlari duzenleyip normal akista kaydeder.
                                    if(confirmed.isConfirmed) this.formCallback();
                                }
                            },
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1',
                                label : ' ',
                                subs  : [
                                    {
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'main_name',
                                        //isDate   : true,
                                        required : true,
                                        label : 'İsim & Soyisim',
                                        col      : 4,
                                        placeholder : 'İsim & Soyisim',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        name     : 'type_key',
                                        disabled : !useAuthStore().permissions?.includes('per-04-02'),
                                        col      : 4,
                                        required : useAuthStore().permissions?.includes('per-04-02'),
                                        label    : 'Kullanıcı Tipi',
                                        options  : [
                                            {
                                                text  : 'Yönetici',
                                                value : 'op-pert-admin'
                                            }, {
                                                text  : 'Tedarikçi',
                                                value : 'op-pert-reseller'
                                            }
                                        ],
                                        oninput  : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        name     : 'user_status',
                                        disabled : !useAuthStore().permissions?.includes('per-04-02'),
                                        col      : 4,
                                        required : useAuthStore().permissions?.includes('per-04-02'),
                                        label    : 'Durum',
                                        options  : [
                                            {
                                                text  : 'Onay Bekliyor',
                                                value : '-1'
                                            },
                                            {
                                                text  : 'Aktif',
                                                value : '1'
                                            }, {
                                                text  : 'Pasif',
                                                value : '0'
                                            }
                                        ],
                                        oninput  : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        name     : 'user_role',
                                        col      : 4,
                                        required : useAuthStore().permissions?.includes('per-04-02'),
                                        disabled : !useAuthStore().permissions?.includes('per-04-02'),
                                        label    : 'Rol',
                                        triggerInputOnSetup : false,
                                        options  : [],
                                        setOptions: async () => {
                                            const store = this.usePermissionDataStore()
                                            if (!store.roleList.length) await store.fetchRoleTemplates();
                                            return store.roleList.map(role => ({ text: role.name, value: role.op_key,data:role }))
                                        },
                                        oninput  : async (e) => {
                                            this.submitDynamicChanges(e.target);
                                            const selectedOption = e.target.options[e.target.selectedIndex];
                                            const permissionList = JSON.parse(selectedOption.dataset.info).permissions;
                                            await this.permissionTree.setChecked(permissionList);
                                       
                                        }
                                    },/*{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        col      : 4,
                                        hasMultiple : true,
                                        label    : 'Tesis Sınırlaması',
                                        setOptions  : async () => {
                                            await this.formDataStore.setFacilitiesData()
                                            return this.formDataStore.facilities.map(inv => {
                                                return {
                                                    text  : inv.title,
                                                    value : inv.id,
                                                };
                                            });
                                        },
                                        name  : 'user_grp_code',
                                        oninput  : (e) => this.submitDynamicChanges(e.target)
                                    },*/{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'email',
                                        name  : 'user_username',
                                        required : true,
                                        col : 4,
                                        placeholder : 'Kullanıcı Email',
                                        label : 'Kullanıcı Email',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'password',
                                        name  : 'user_password',
                                        //required : true,
                                        col : 4,
                                        label : 'Parola',
                                        placeholder : '*********',
                                        oninput : (e) => {

                                            const main  = document.querySelector('input[name="user_password"]');
                                            const check = document.querySelector('input[name="user_password_check"]');

                                            if(main.value == check.value) {
                                                main.classList.remove('is-invalid');
                                                check.classList.remove('is-invalid');
                                            }

                                            this.submitDynamicChanges(e.target);
                                        }
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'password',
                                        name  : 'user_password_check',
                                        //required : true,
                                        col : 4,
                                        label : 'Parola (Kontrol)',
                                        placeholder : '*********',
                                        oninput : (e) => {

                                            const main  = document.querySelector('input[name="user_password"]');
                                            const check = document.querySelector('input[name="user_password_check"]');

                                            if(main.value == check.value) {
                                                main.classList.remove('is-invalid');
                                                check.classList.remove('is-invalid');
                                            }

                                            this.submitDynamicChanges(e.target);
                                        }
                                    },
                                ],
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_31',
                                label : 'İletişim',
                                hideAdd : true,
                                group_key : 'userfacilitygroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 4,
                                        name  : 'conttitle',
                                        label : 'Başlık',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 4,
                                         mask  : 'phone',
                                        isMasked : true,
                                        name  : 'contphone',
                                        label : 'Telefon',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'email',
                                        col   : 4,
                                        name  : 'contmail',
                                        label : 'E-posta',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_312',
                                disabled : !useAuthStore().permissions?.includes('per-04-02'),
                                label : 'Bağlı Cariler',
                                col   : 6,
                                group_key : 'userclientgroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 4,
                                        readOnly : true,
                                        name  : 'cliid',
                                        hidden : true,
                                        label : '',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 4,
                                        readOnly : true,
                                        name  : 'clicode',
                                        label : 'Cari Kodu',
                                        onclick : usersClientClick,
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        readOnly : true,
                                        col   : 4,
                                        name  : 'clititle',
                                        label : 'Cari Başlık',
                                        onclick : usersClientClick,
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'tree',
                                name  : 'permissions',
                                label : 'İzinler',
                                disabled : !useAuthStore().permissions?.includes('per-04-02'),
                                list  : this.usePermissionDataStore().list,
                                col   : 6,
                                oncheck : (e) => this.submitDynamicChanges(e,true,'permissions')
                                
                            }
                        ]
                    },'op-doc-request-form' : {
                        showRemoveButton : false,
                        oncreated       : (id) => {
                            document.querySelector('input[name="fuel_price_impact_2"]')?.parentNode?.parentNode?.parentNode?.parentNode?.parentNode?.style != undefined ?
                                document.querySelector('input[name="fuel_price_impact_2"]').parentNode.parentNode.parentNode.parentNode.parentNode.style.display = 'none'
                            : null;


                            document.querySelector("[data-tag*='calory_settings']")?.parentNode?.parentNode && (document.querySelector("[data-tag*='calory_settings']").parentNode.parentNode.hidden = false);
                            document.querySelector("[data-tag*='coal_specs']")?.parentNode?.parentNode && (document.querySelector("[data-tag*='coal_specs']").parentNode.parentNode.hidden = false);
                        },
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_4',
                                label : 'Talep Formu',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'qnid',
                                        col      : 4,
                                        required : false,
                                        hidden : true,
                                        //showOnValue : true,
                                        label : 'Belge Kodu',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'date',
                                        isDate : true,
                                        readOnly : true,
                                        col      : 4,
                                        required : true,
                                        defaultValue : dayjs().format('DD/MM/YYYY'),
                                        label : 'Belge Tarihi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'rev_date',
                                        isDate : true,
                                        col      : 4,
                                        hidden : true,
                                        showOnValue : true,
                                        label : 'Belge Rev. Tarihi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'title',
                                        col      : 4,
                                        required : true,
                                        label : 'İhale Konusu',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        name     : 'target_type',
                                        col      : 4,
                                        required : true,
                                        label    : 'Alıcı',
                                        options  : [
                                            {
                                                text  : 'Yatağan',
                                                value : 'Yatağan'
                                            },
                                            {
                                                text  : 'ÇATES',
                                                value : 'ÇATES'
                                            }/*, {
                                                text  : 'Her İkisi',
                                                value : 'Her İkisi'
                                            }*/
                                        ],
                                        oninput  : (e) =>{
                                            const rtype = document.querySelector('input[name="request_type"]');
                                            if(rtype !== null){
                                                switch (e.target.value) {
                                                    case 'ÇATES':
                                                        rtype.parentNode.parentNode.style.display = '';
                                                        break;
                                                
                                                    default:
                                                        rtype.parentNode.parentNode.style.display = 'none';
                                                        break;
                                                }
                                            }
                                            
                                            this.submitDynamicChanges(e.target)
                                        } 
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        name     : 'order_radius',
                                        col      : 4,
                                        required : true,
                                        label    : 'Sipariş Kapsamı',
                                        options  : [
                                            {
                                                text  : 'Nakliye Dahil',
                                                value : 'Nakliye Dahil'
                                            },
                                            {
                                                text  : 'Nakliye Hariç',
                                                value : 'Nakliye Hariç'
                                            }, {
                                                text  : 'Sadece Nakliye',
                                                value : 'Sadece Nakliye'
                                            }
                                        ],
                                        oninput  : (e) =>{
                                            const elements = {
                                                'priceRow' : document.querySelector('input[name="fuel_price_impact_2"]')?.parentNode?.parentNode?.parentNode?.parentNode?.parentNode,
                                                'calory_settings': document.querySelector("[data-tag*='calory_settings']")?.parentNode?.parentNode,
                                                'coal_specs': document.querySelector("[data-tag*='coal_specs']")?.parentNode?.parentNode
                                            }

                                            switch (e.target.value) {
                                                case 'Nakliye Dahil':
                                                    if(elements['priceRow'])elements['priceRow'].style.display = '';

                                                    if(elements['calory_settings'])elements['calory_settings'].hidden = false;
                                                    if(elements['coal_specs'])elements['coal_specs'].hidden = false;
                                                    break;
                                                case 'Sadece Nakliye':
                                                    if(elements['priceRow'])elements['priceRow'].style.display = '';


                                                    if(elements['calory_settings'])elements['calory_settings'].hidden = true;
                                                    if(elements['coal_specs'])elements['coal_specs'].hidden = true;
                                                    break;
                                            
                                                default:
                                                    if(elements['priceRow'])elements['priceRow'].style.display = 'none';

                                                    if(elements['calory_settings'])elements['calory_settings'].hidden = false;
                                                    if(elements['coal_specs'])elements['coal_specs'].hidden = false;
                                                    
                                                    break;
                                            }
                                            
                                            


                                            this.submitDynamicChanges(e.target);
                                        } 
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'contract_start_date',
                                        col      : 4,
                                        isDate : true,
                                        required : true,
                                        label : 'İhale Başlangıç Tarihi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'contract_end_date',
                                        col      : 4,
                                        isDate : true,
                                        required : true,
                                        label : 'İhale Bitiş Tarihi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'unload_area',
                                        col      : 4,
                                        required : true,
                                        label : 'Ürün Boşaltma Yeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class    : ['form-control','mb-2','mb-md-0','form-item'],
                                type     : 'switch',
                                name     : 'her_ikisi',
                                col      : 12,
                                //required : true,
                                label    : ' ',
                                desc     : 'Talep Her İki Sistemdede Gözüksün',
                                oninput  : (e) => this.submitDynamicChanges(e.target)
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_5',
                                group_key : 'coal_specs',
                                label : 'Kömürün Özellikleri / Red Şartları',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'coal_size',
                                        col      : 4,
                                        required : false,
                                        label : 'Ebat Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'coal_hgi',
                                        col      : 4,
                                        required : false,
                                        label : 'HGİ Değeri ',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'coal_ucucu',
                                        col      : 4,
                                        required : false,
                                        label : 'Uçucu Madde Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'coal_type',
                                        col      : 4,
                                        required : false,
                                        label : 'Cinsi Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'calory',
                                        col      : 4,
                                        required : false,
                                        label : 'Kalori Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'humidity',
                                        col      : 4,
                                        required : false,
                                        label : 'Nem Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'ash_content',
                                        col      : 4,
                                        required : false,
                                        label : 'Kül Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'sulfur',
                                        col      : 4,
                                        required : false,
                                        label : 'Kükürt Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_6',
                                group_key : 'calory_settings',
                                label : 'Kömür kalori aralığına göre birim fiyatı',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'prime_condition_is',
                                        col      : 4,
                                        required : false,
                                        label : 'Kalori Aralığı Başlangıç( Kcal)',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'prime_condition_is_bellow',
                                        col      : 4,
                                        required : false,
                                        label : 'Kalori Aralığı Bitiş (Kcal)',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask  : 'money',
                                        name  : 'prime_unit_price',
                                        col      : 4,
                                        required : false,
                                        label : 'Birim Fiyat',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_7',
                                label : ' ',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'amount',
                                        col      : 2,
                                        isMasked : true,
                                        mask : 'money',
                                        required : false,
                                        hasIcon : true,
                                        icon : 'Ton',
                                        label : 'Miktar',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        name     : 'payment_periods',
                                        col      : 4,
                                        label    : 'Hakediş Dönemleri',
                                        options  : [
                                            {
                                                text  : 'Günlük',
                                                value : 'Günlük'
                                            },
                                            {
                                                text  : 'Haftalık',
                                                value : 'Haftalık'
                                            }, {
                                                text  : 'Aylık',
                                                value : 'Aylık'
                                            }
                                        ],
                                        oninput  : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'payment_desc',
                                        col      : 6,
                                        label : "Hakediş Açıklama",
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_8',
                                label : 'Nakliye Birim Fiyat Değişim Oranları',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask  : 'money',
                                        name  : 'tiufe_price_impact',
                                        col      : 4,
                                        required : false,
                                        hasIcon : true,
                                        icon : '%',
                                        label : 'Birim Fiyatın  ((Tİ-ÜFE +TÜFE)/2 ) Etkilenme Oranı',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask  : 'money',
                                        name  : 'fuel_price_impact',
                                        col      : 4,
                                        required : false,
                                        hasIcon : true,
                                        icon : '%',
                                        label : 'Birim Fiyatın Akaryakıt (Artış/Azalış) Etkilenme Oranı',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask  : 'money',
                                        name  : 'fuel_price_impact_2',
                                        col      : 4,
                                        required : false,
                                        hasIcon : true,
                                        icon : '%',
                                        label : 'Birim Fiyatın Akaryakıttan Etkilenmeyecek Oranı',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_7',
                                label : ' ',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'payment_due',
                                        col      : 4,
                                        required : false,
                                        label : "Ödeme Vadesi",
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'transfer_start_date',
                                        isDate : true,
                                        col      : 4,
                                        required : false,
                                        label : 'Sevkiyata Başlangıç Tarihi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'transfer_end_date',
                                        isDate : true,
                                        col      : 4,
                                        required : false,
                                        label : 'Sevkiyata Bitiş Tarihi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class    : ['form-control','mb-2','mb-md-0','form-item'],
                                type     : 'switch',
                                name     : 'request_type',
                                col      : 12,
                                required : true,
                                label    : 'Talep Türü',
                                desc     : 'Rodevans talep',
                                oninput  : (e) => this.submitDynamicChanges(e.target)
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'textarea',
                                name  : 'desc',
                                col      : 12,
                                required : false,
                                label : 'Ek Açıklama',
                                oninput : (e) => this.submitDynamicChanges(e.target)
                            }
                        ]
                    },'op-doc-client-form' : {
                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['btn','btn-danger','custom-button'],
                                type  : 'button',
                                hidden : !useAuthStore().currentStatus.canProceed || !(useAuthStore().typeKey == 'op-pert-reseller'),
                                name  : 'userrequest',
                                col : 4,
                                value : 'Kullanıcı Talebinde Bulun',
                                label : ' ',
                                oninput : (e) => {
                                    e.preventDefault();
                                    if(this.route?.params?.id === undefined || this.route?.params?.id == ''){
                                        this.plib.toast(this.Swal,'error','Lütfen önce cari oluşturunuz.');
                                        return false;
                                    }


                                    //ortak uretici: Turkce karakter uretmiyor (form dogrulamasi
                                    //yalnizca ASCII kabul ediyor) ve guvenli rastgelelik kullaniyor
                                    const password = generatePassword(8);

                                    Swal.fire({
                                        title: 'Kullanıcı Talep Formu',
                                        html: `
                                            <div class="text-start">
                                                <div class="mb-3">
                                                    <label class="form-label">E-posta</label>
                                                    <input type="email" id="userEmail" class="form-control" placeholder="ornek@email.com">
                                                    <small id="emailError" class="text-danger"></small>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Telefon</label>
                                                    <input type="text" id="userPhone" class="form-control" placeholder="(5XX) XXX-XX-XX">
                                                    <small id="phoneError" class="text-danger"></small>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Parola</label>
                                                    <input type="text" id="userPassword" class="form-control" value="${password}" readonly>
                                                    <small class="text-muted">Otomatik oluşturulan parola</small>
                                                </div>
                                            </div>
                                        `,
                                        showCancelButton: true,
                                        confirmButtonText: 'Gönder',
                                        cancelButtonText: 'İptal',
                                        willOpen : () => {
                                            const input = document.getElementById('userPhone');
                                            new VMasker(input).maskPattern("(999) 999-99-99");
                                        },
                                        preConfirm: async () => {
                                            const email = document.getElementById('userEmail').value.trim();
                                            const phone = document.getElementById('userPhone').value.trim();
                                            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                                            const phoneRegex = /^\(\d{3}\)\s\d{3}-\d{2}-\d{2}$/;
                                            
                                            let isValid = true;
                                            
                                            if (!email || !emailRegex.test(email)) {
                                                document.getElementById('emailError').textContent = 'Geçerli bir e-posta adresi giriniz';
                                                isValid = false;
                                            } else {
                                                document.getElementById('emailError').textContent = '';
                                            }
                                            
                                            if (!phone || !phoneRegex.test(phone)) {
                                                document.getElementById('phoneError').textContent = 'Telefon formatı (5XX) XXX-XX-XX olmalıdır';
                                                isValid = false;
                                            } else {
                                                document.getElementById('phoneError').textContent = '';
                                            }
                                            
                                            if (!isValid) return false;
                                            Swal.showLoading();

                                            const data = { 
                                                email, 
                                                phone, 
                                                password, 
                                                'cli_id': this.route?.params?.id,
                                                'cli_code': document.querySelectorAll('input[name="clicode"]')[0]?.value ?? '',
                                                'cli_title': document.querySelectorAll('input[name="title"]')[0]?.value ?? ''
                                            };

                                            
                                            const envelope = new FormData();
                                            for(let key in data) envelope.append(key,data[key]);
                                            
                                           
                                            const rsp = await this.plib.request({
                                                url      : '/api/v1/auth/register',
                                                method   : 'POST',
                                            },null,envelope);

                                            if(rsp.success){
                                                this.plib.toast(this.Swal, 'success', 'Talep gönderildi');
                                            }else{
                                                Swal.showValidationMessage(rsp.msg);
                                            }
                                            Swal.hideLoading();

                                            return false;
                                        },
                                        showLoaderOnConfirm : true,
                                        allowOutsideClick : false,
                                        showCloseButton : true,
                                        
                                    });
                                }
                            },
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_4',
                                label : '<h4>Firma Bilgileri</h4>',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'clicode',
                                        col      : 4,
                                        //kodu backend uretiyor (belgenin qnid'i); kimse elle
                                        //yazamaz, bu yuzden zorunlu girdi de degil
                                        required : false,
                                        readOnly : true,
                                        placeholder : 'Kayıt sonrasında otomatik atanacak',
                                        label : 'Firma Kodu',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'title',
                                        col      : 4,
                                        required : true,
                                        label : 'Firma Ünvanı',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cli_vat_id',
                                        col      : 4,
                                        required : true,
                                        label : 'Firma Vergi Numarası',
                                        oninput : (e) =>{
                                            if(this.vergiKimlikDogrula(e.target.value)){
                                                e.target.classList.remove('is-invalid');
                                                e.target.classList.add('is-valid');
                                            }else{
                                                e.target.classList.remove('is-valid');
                                                e.target.classList.add('is-invalid');
                                            }
                                            this.submitDynamicChanges(e.target);
                                        }
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cli_vat_title',
                                        col      : 4,
                                        required : true,
                                        label : 'Firma Vergi Dairesi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cli_fax',
                                        col      : 4,
                                        required : false,
                                        label : 'Firma Faks Numarası',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cli_website',
                                        col      : 4,
                                        required : false,
                                        label : 'Firma Web Sitesi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'textarea',
                                name  : 'cli_desc',
                                col      : 12,
                                required : false,
                                label : 'Firma Ek Açıklama',
                                oninput : (e) => this.submitDynamicChanges(e.target)
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1_2',
                                label : 'Yetkililer (İletişim Bilgileriyle Beraber Girilmelidir)',
                                type  : 'multiple',
                                requiredIfFirst : true,
                                group_key : 'clientcontgroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cont_name',
                                        required : true,
                                        label : 'Adı Soyadı',
                                        col      : 4,
                                        placeholder : 'Adı Soyadı',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cont_phone',
                                        isMasked : true,
                                        required : true,
                                        mask : 'phone',
                                        label : 'Telefon',
                                        col      : 4,
                                        placeholder : 'Telefon',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'email',
                                        name  : 'cont_email',
                                        isMasked : true,
                                        required : true,
                                        mask : 'email',
                                        label : 'E-Posta',
                                        col      : 4,
                                        placeholder : 'E-Posta',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1_1',
                                label : 'İmza Sirküleri',
                                type  : 'multiple',
                                removable : false,
                                requiredIfFirst : true,
                                group_key : 'clientimzasirku',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png',
                                        name  : 'cont_imza_file',
                                        required : true,
                                        label : ' ',
                                        col      : 12,
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1',
                                label : 'Vergi Levhaları',
                                type  : 'multiple',
                                removable : false,
                                requiredIfFirst : true,
                                group_key : 'clientvergilevha',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png',
                                        name  : 'cont_vergi_file',
                                        required : true,
                                        label : ' ',
                                        col      : 12,
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_2',
                                label : 'Oda / Ticaret Sicil Belgeleri',
                                type  : 'multiple',
                                removable : false,
                                requiredIfFirst : true,
                                group_key : 'clientodasicil',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png',
                                        name  : 'cont_odasicil_file',
                                        required : true,
                                        label : ' ',
                                        col      : 12,
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_3',
                                label : 'Kaşeli-İmzali IBAN Bilgi Formları',
                                type  : 'multiple',
                                removable : false,
                                requiredIfFirst : true,
                                group_key : 'clientibanbilgi',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png',
                                        name  : 'cont_iban_file',
                                        required : true,
                                        label : ' ',
                                        col      : 12,
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_4',
                                label : 'Diğer Belgeler',
                                type  : 'multiple',
                                removable : false,
                                //requiredIfFirst : false,
                                group_key : 'clientotherdocs',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cont_otherdocs_file_text',
                                        required : false,
                                        label : 'Belge İsmi',
                                        col      : 6,
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png',
                                        name  : 'cont_otherdocs_file',
                                        required : false,
                                        label : 'Belge Dosyası',
                                        col      : 6,
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    },'op-doc-offer-form' : {
                        showRemoveButton : false,
                        oncreated       : (id) => {
                            document.querySelector('input[name="fuel_price_impact_2"]')?.parentNode?.parentNode?.parentNode?.parentNode?.parentNode?.style != undefined ?
                                document.querySelector('input[name="fuel_price_impact_2"]').parentNode.parentNode.parentNode.parentNode.parentNode.style.display = 'none'
                            : null;

                            

                            document.querySelector("[data-tag*='calory_settings']")?.parentNode?.parentNode && (document.querySelector("[data-tag*='calory_settings']").parentNode.parentNode.hidden = false);
                            document.querySelector("[data-tag*='coal_specs']")?.parentNode?.parentNode && (document.querySelector("[data-tag*='coal_specs']").parentNode.parentNode.hidden = false);
                        },
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_4',
                                label : 'Teklif Formu',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'request_id',
                                        readOnly : true,
                                        hidden : true,
                                        col      : 4,
                                        required : true,
                                        label : 'Talep kodu',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'offer_type',
                                        hidden : true,
                                        col      : 4,
                                        required : true,
                                        label : 'Teklif Türü',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'clititle',
                                        readOnly : true,
                                        col      : 4,
                                        required : true,
                                        label : 'Teklif Veren Cari',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cliid',
                                        readOnly : true,
                                        hidden : true,
                                        col      : 4,
                                        required : true,
                                        label : 'Teklif Veren Cari Kodu',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'qnid',
                                        col      : 4,
                                        required : false,
                                        readOnly : true,
                                        //showOnValue : true,
                                        hidden : true,
                                        label : 'Teklif Kodu',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'date',
                                        isDate : true,
                                        readOnly : true,
                                        col      : 4,
                                        required : true,
                                        defaultValue : dayjs().format('DD/MM/YYYY'),
                                        label : 'Belge Tarihi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'rev_date',
                                        hidden : true,
                                        isDate : true,
                                        showOnValue : true,
                                        label : 'Revizyon Tarihi',
                                        col      : 4,
                                        required : false,
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file' ? 'select' : 'text',
                                        name     : 'target_type',
                                        col      : 4,
                                        required : true,
                                        readOnly : !(useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file'),
                                        label    : 'Alıcı',
                                        options  : [
                                            {
                                                text  : 'Yatağan',
                                                value : 'Yatağan'
                                            },
                                            {
                                                text  : 'ÇATES',
                                                value : 'ÇATES'
                                            }, {
                                                text  : 'Her İkisi',
                                                value : 'Her İkisi'
                                            }
                                        ],
                                        oninput  : (e) =>{
                                            this.submitDynamicChanges(e.target)
                                        } 
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        name     : 'order_radius',
                                        col      : 4,
                                        required : true,
                                        label    : 'Sipariş Kapsamı',
                                        options  : [
                                            {
                                                text  : 'Nakliye Dahil',
                                                value : 'Nakliye Dahil'
                                            },
                                            {
                                                text  : 'Nakliye Hariç',
                                                value : 'Nakliye Hariç'
                                            }, {
                                                text  : 'Sadece Nakliye',
                                                value : 'Sadece Nakliye'
                                            }
                                        ],
                                        oninput  : (e) =>{
                                            const elements = {
                                                'priceRow' : document.querySelector('input[name="fuel_price_impact_2"]')?.parentNode?.parentNode?.parentNode?.parentNode?.parentNode,
                                                'calory_settings': document.querySelector("[data-tag*='calory_settings']")?.parentNode?.parentNode,
                                                'coal_specs': document.querySelector("[data-tag*='coal_specs']")?.parentNode?.parentNode
                                            }


                                            if(useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file'){
                                                switch (e.target.value) {
                                                    case 'Nakliye Dahil':
                                                        if(elements['priceRow'])elements['priceRow'].style.display = '';

                                                        if(elements['calory_settings'])elements['calory_settings'].hidden = false;
                                                        if(elements['coal_specs'])elements['coal_specs'].hidden = false;
                                                        break;
                                                    case 'Sadece Nakliye':
                                                        if(elements['priceRow'])elements['priceRow'].style.display = '';

                                                        if(elements['calory_settings'])elements['calory_settings'].hidden = true;
                                                        if(elements['coal_specs'])elements['coal_specs'].hidden = true;
                                                        break;
                                                
                                                    default:
                                                        if(elements['priceRow'])elements['priceRow'].style.display = 'none';

                                                        if(elements['calory_settings'])elements['calory_settings'].hidden = false;
                                                        if(elements['coal_specs'])elements['coal_specs'].hidden = false;
                                                       
                                                        break;
                                                }
                                            }else{
                                                if(elements['priceRow'])elements['priceRow'].style.display = 'none';
                                            }
                                            


                                            this.submitDynamicChanges(e.target);
                                        } 
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'contract_start_date',
                                        col      : 4,
                                        isDate : true,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Teklif Başlangıç Tarihi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'contract_end_date',
                                        col      : 4,
                                        isDate : true,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Teklif Bitiş Tarihi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'load_area',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Ürün Yükleme Yeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'unload_area',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Ürün Boşaltma Yeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_5',
                                group_key : 'coal_specs',
                                hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                label : 'Kömürün Özellikleri / Red Şartları',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'coal_size',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Ebat Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'coal_hgi',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'HGİ Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'coal_ucucu',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Uçucu Madde Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'coal_type',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Cinsi Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'calory',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Kalori Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'humidity',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Nem Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'ash_content',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Kül Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'sulfur',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Kükürt Değeri',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_6',
                                group_key : 'calory_settings',
                                hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                label : 'Kömür kalori aralığına göre birim fiyatı',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'prime_condition_is',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Kalori Aralığı Başlangıç( Kcal)',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'prime_condition_is_bellow',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Kalori Aralığı Bitiş (Kcal)',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask  : 'money',
                                        name  : 'prime_unit_price',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Birim Fiyat',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_7',
                                
                                label : ' ',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'amount',
                                        isMasked : true,
                                        mask : 'money',
                                        col      : 2,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        hasIcon : true,
                                        icon : 'Ton',
                                        label : 'Miktar',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        name     : 'payment_periods',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label    : 'Hakediş Dönemleri',
                                        options  : [
                                            {
                                                text  : 'Günlük',
                                                value : 'Günlük'
                                            },
                                            {
                                                text  : 'Haftalık',
                                                value : 'Haftalık'
                                            }, {
                                                text  : 'Aylık',
                                                value : 'Aylık'
                                            }
                                        ],
                                        oninput  : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'payment_desc',
                                        col      : 6,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : "Hakediş Açıklama",
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_8',
                                label : 'Akaryakıt Birim Fiyat Değişim Oranları',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask  : 'money',
                                        name  : 'tiufe_price_impact',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        hasIcon : true,
                                        icon : '%',
                                        label : 'Birim Fiyatın  ((Tİ-ÜFE +TÜFE)/2 ) Etkilenme Oranı',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask  : 'money',
                                        name  : 'fuel_price_impact',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        hasIcon : true,
                                        icon : '%',
                                        label : 'Birim Fiyatın Akaryakıt (Artış/Azalış) Etkilenme Oranı',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask  : 'money',
                                        name  : 'fuel_price_impact_2',
                                        col      : 4,
                                        required : false,
                                        hasIcon : true,
                                        icon : '%',
                                        label : 'Birim Fiyatın Akaryakıttan Etkilenmeyecek Oranı',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask  : 'money',
                                        name  : 'transfer_price_impact',
                                        col      : 4,
                                        required : false,
                                        label : 'Nakliye Birim Fiyat',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_7',
                                
                                label : ' ',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'payment_due',
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : "Ödeme Vadesi",
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'transfer_start_date',
                                        isDate : true,
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Sevkiyata Başlangıç Tarihi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'transfer_end_date',
                                        isDate : true,
                                        col      : 4,
                                        hidden : useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Sevkiyat Bitiş Tarihi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'textarea',
                                name  : 'desc',
                                col      : 12,
                                required : false,
                                label : 'Ek Açıklama',
                                oninput : (e) => this.submitDynamicChanges(e.target)
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_4',
                                label : 'Teklif Belgeleri',
                                type  : 'multiple',
                                //removable : false,
                                //requiredIfFirst : false,
                                group_key : 'offerotherdocs',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'offer_otherdocs_file_text',
                                        required : false,
                                        label : 'Belge İsmi',
                                        col      : 6,
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png',
                                        name  : 'offer_otherdocs_file',
                                        required : false,
                                        label : 'Belge Dosyası',
                                        col      : 6,
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    }
                },
                formData        : {
                    dynamicF : {},
                    files : {},
                    removedData : []
                },
                flatpickrInstances : []
            }
        },
        async mounted() {
            this.isLoadingRoles = true;
            await this.usePermissionDataStore().fetchRoleTemplates();
            this.isLoadingRoles = false;

            // Ensure template is re-rendered so .area-target nodes are present before we attach rows
            await this.$nextTick();
            //here wait all forms are building
            await Promise.all(this.ftypes.map(async key => {
                const formData = this.formDataStore.formData?.[key] ?? undefined;
                await this.buildDynamicFForm(
                    key,
                    formData !== undefined ? Object.keys(formData)[0] : 'new-'+(new Date).getTime(),
                    formData !== undefined ? Object.values(formData)[0] : {}
                );
            }));
            //here empty form data to prevent submit with old data because buildDynamicFForm set formData with default values
            this.formDataStore.setData({},{});
        },
        methods: {
            tcKimlikDogrula(tc) {
                tc = tc.toString().trim();

                // 1. Uzunluk ve sadece rakam kontrolü
                if (tc.length !== 11 || !/^\d{11}$/.test(tc)) {
                    return false;
                }

                // 2. İlk rakam 0 olamaz
                if (tc[0] === '0') {
                    return false;
                }

                let digits = tc.split('').map(Number);

                // 3. 10. hane kontrolü
                let tekToplam = digits[0] + digits[2] + digits[4] + digits[6] + digits[8];
                let ciftToplam = digits[1] + digits[3] + digits[5] + digits[7];
                let onuncuHane = (tekToplam * 7 - ciftToplam) % 10;

                if (onuncuHane !== digits[9]) {
                    return false;
                }

                // 4. 11. hane kontrolü
                let ilkOnToplam = digits.slice(0, 10).reduce((a, b) => a + b, 0);
                let onbirinciHane = ilkOnToplam % 10;

                return onbirinciHane === digits[10];
            },
            /**
             * 10 Haneli Vergi Kimlik Numarası (VKN) doğrulama
             * @param {string} vkn - Doğrulanacak 10 haneli vergi kimlik numarası
             * @returns {boolean} Geçerli ise true, değilse false
             */
            vergiKimlikDogrula(vkn) {
                if (vkn.length !== 10 && vkn.length !== 11) {
                    return false;
                }else{
                    if(vkn.length === 11){
                        return this.tcKimlikDogrula(vkn);
                    }
                }
                return true;

                /*const digits = vkn.split('').map(Number);
                const controlDigit = digits[9];           // Son basamak (kontrol basamağı)
                
                let toplam = 0;

                for (let i = 0; i < 9; i++) {
                    let x = (digits[i] + (9 - i)) % 10;   // (rakam + ağırlık) mod 10
                    let weight = Math.pow(2, 9 - i);      // 512, 256, 128, 64, 32, 16, 8, 4, 2
                    
                    let val = (x * weight) % 9;
                    
                    // Eğer x != 0 ve sonuç 0 ise, 9 kabul edilir
                    if (x !== 0 && val === 0) {
                        val = 9;
                    }
                    
                    toplam += val;
                }

                // Hesaplanan kontrol basamağı
                const hesaplananKontrol = (10 - (toplam % 10)) % 10;

                return hesaplananKontrol === controlDigit;*/
            },
            async formCallback() {
                if(!this.savecallback) return false;

                // wait for any in-flight temp uploads before building the save payload
                const pending = Object.values(this.formData.files || {}).filter(f => f && f.uploading && f.promise);
                if(pending.length){
                    await Promise.all(pending.map(f => f.promise));
                }

                // retry failed temp uploads once before giving up
                const failed = Object.values(this.formData.files || {}).some(f => f && f.error);
                if(failed){
                    await this.retryFailedUploads();
                }

                if(Object.values(this.formData.files || {}).some(f => f && f.error)){
                    this.plib.toast(this.Swal, 'error', 'Bazı dosyalar yüklenemedi. Lütfen dosyaları yeniden seçin.');
                    return false;
                }

                return this.savecallback(this.formData);
            },
            async retryFailedUploads() {
                const failedKeys = Object.keys(this.formData.files || {}).filter(key => {
                    const item = this.formData.files[key];
                    return item && item.error && item.file;
                });

                await Promise.all(failedKeys.map(async key => {
                    const item = this.formData.files[key];
                    try {
                        const ref = await this.uploadTempFile(item.file);
                        this.formData.files[key] = { uploading: false, reference: ref };
                    } catch (err) {
                        console.error('Retry failed for', key, err);
                    }
                }));
            },
            submitDynamicChanges(el,isDatalist = false,datalistKey = null){

                if(isDatalist){
                    this.formData[datalistKey] = el.length == 0 ? ["empty"] : el;
                    return true;
                }

                const tag    = el.dataset.tag;
                const name   = el.name.split('*-*')[0];
                let value    = el.value;
                
                const rowId  = el.dataset.rowId;
                if(this.formData.dynamicF[tag+'**'+rowId] === undefined) this.formData.dynamicF[tag+'**'+rowId] = {
                    entities : {},
                    tag      : tag,
                };

                //because vanilla masker , input events coming after shaping input value. make sure set everytime
                if(el.dataset.mask == 'money'){
                    if(value.includes(',')) value = value.replace(/\./g,'').replace(',','.');
                }
        
                switch (el.type) {
                    case 'file':
                        if(el.files && el.files[0]){
                            const fileKey = tag+'**dynamicFile**'+el.dataset.fileId+'**'+rowId+'*-*'+name;
                            // Store uploading state
                            this.formData.files[fileKey] = { uploading: true, file: el.files[0], promise: null };
                            // Upload immediately to temp storage
                            this.formData.files[fileKey].promise = this.uploadTempFile(el.files[0]).then(ref => {
                                this.formData.files[fileKey] = { uploading: false, reference: ref };
                            }).catch(err => {
                                console.error('Temp upload failed:', err);
                                this.formData.files[fileKey] = { uploading: false, error: true };
                                this.plib.toast(this.Swal, 'error', 'Dosya yüklenemedi: ' + (err.msg || 'Bilinmeyen hata'));
                            });
                        }
                        break;
                    default:
                        this.formData.dynamicF[tag+'**'+rowId].entities[name] = value;
                        if(el.type === 'checkbox') this.formData.dynamicF[tag+'**'+rowId].entities[name] = el.checked ? 1 : 0;
                        if(el.classList.contains('date-input')){
                            value = value.split(' ');
                            this.formData.dynamicF[tag+'**'+rowId].entities[name] = value[0].split('/').reverse().join('-')+(value[1] ? ' '+value[1] : '' );
                        } 
                        break;
                }
            },
            async uploadTempFile(file) {
                const fd = new FormData();
                fd.append('file', file);
                const rsp = await this.plib.request({
                    url: '/api/v1/temp-upload',
                    method: 'POST'
                }, null, fd);
                if(rsp && rsp.success){
                    return rsp;
                }else{
                    throw new Error(rsp?.msg || 'Yükleme başarısız');
                }
            },
            async buildDynamicFForm(tag,dynamicId = 'new-'+(new Date).getTime(),data = {},selector = null){
                const form   = this.forms[tag];
                const rowId    = dynamicId;
                const row    = document.createElement('div');
                const rowSub = document.createElement('div');
                let target;
               
                row.dataset.id  = rowId;
                row.dataset.tag = tag;
                row.classList.add('dform-row');
                if(selector == null){
                    target = document.querySelector('.area-target[data-tag="'+tag+'"]');
                    row.classList.add('mb-10','mt-10','col-12','card','card-full');
                    rowSub.classList.add('card-body');
                }else{
                    row.classList.add('row');
                    target = document.querySelector(selector);
                }

                if (!target) {
                    console.error('buildDynamicFForm: target not found for', tag, 'selector', selector)
                    return;
                }
                
                for (let index = 0; index < form.fields.length; index++) {
                    const fitem = form.fields[index];
                    
                    const itemRow = document.createElement('div');
                    itemRow.classList.add('row','mt-3','mb-6','item-row');
                    if(fitem?.rowClass !== undefined) itemRow.classList.add(...fitem?.rowClass);
                    if(fitem?.disabled) itemRow.style.display = 'none';
                    
                    const inLabel   = document.createElement('label');
                    inLabel.classList.add('form-label');
                    if(fitem?.required) inLabel.classList.add('required');
                    inLabel.innerHTML = fitem.label.length == 0 ? '<span> </span>' : fitem.label;
                    
                    itemRow.appendChild(inLabel);

                    let input = null;

                    const createInput = (attr,iDiv = null) => {
                        if(iDiv == null){
                            iDiv = document.createElement('div');
                            iDiv.classList.add('col-lg-'+(attr.col ?? '12'),'d-flex');
                        }
                        

                        const input = document.createElement('input');
                        // try to prevent browser autofill
                        input.setAttribute('autocomplete', 'off');
                        input.autocomplete = 'off';
                        input.setAttribute('autocorrect', 'off');
                        input.autocapitalize = 'off';
                        input.setAttribute('spellcheck', 'false');
                        if (attr?.type === 'password') {
                            // stronger hint for password fields
                            input.setAttribute('autocomplete', 'new-password');
                            input.autocomplete = 'new-password';
                        }
                        
                        input.type           = attr.type;
                        input.name           = attr.name;
                        input.dataset.rowId  = rowId;
                        input.dataset.tag    = tag;
                        input.placeholder    = attr?.placeholder ?? '';
                        
                        attr.element         = input;
                        
                        if(attr?.defaultValue)            input.value = attr.defaultValue;
                        if(attr?.readOnly)                input.readOnly = attr.readOnly;
                        if(attr.class !== undefined)      input.classList.add(...attr?.class);
                        if(attr?.required !== undefined)  input.required = attr.required;
                        if(attr?.hidden){
                            //here check if showOnValue is setted
                            if(!(attr?.showOnValue === true && data?.entities?.[attr.name] !== undefined)) {
                                input.hidden = attr.hidden;
                                if(iDiv?.parentNode)iDiv.parentNode.style.display = 'none';
                                //if its hidden do not make required even if required is setted
                                input.required = false;
                            }
                        }                  

                        input.classList.add('form-item');
                        
                        input.onclick = (e) => attr?.onclick?.(e,input) ?? (() => {})();
                        input.oninput = (e) => attr?.oninput?.(e) ?? (() => {})();
                        iDiv.appendChild(input);

                        if(attr?.hasIcon === true){
                            iDiv.classList.add('input-group');
                            const icon = document.createElement('span');
                            icon.classList.add('input-group-text','password-toggle');
                            icon.style.cursor = 'pointer';
                            icon.innerHTML = attr?.icon;
                            iDiv.appendChild(icon);
                        }


                        // add show/hide toggle for password fields
                        if (attr?.type === 'password') {
                            // Use Keen theme icons for consistency with the project
                            const makeIcon = (visible) => visible
                                ? '<i class="ki-outline ki-eye-slash fs-5" role="img"></i>'
                                : '<i class="ki-outline ki-eye fs-5" role="img"></i>';

                            let toggle;
                            if (iDiv.classList && iDiv.classList.contains('input-group')) {
                                toggle = document.createElement('span');
                                toggle.classList.add('input-group-text','password-toggle');
                                toggle.style.cursor = 'pointer';
                                toggle.innerHTML = makeIcon(false);
                               
                            } else {
                                toggle = document.createElement('button');
                                toggle.type = 'button';
                                toggle.classList.add('btn','btn-outline-secondary','ms-2','password-toggle');
                                toggle.style.minWidth = '38px';
                                toggle.style.display = 'inline-flex';
                                toggle.style.alignItems = 'center';
                                toggle.style.justifyContent = 'center';
                                toggle.innerHTML = makeIcon(false);
                                
                            }

                            iDiv.appendChild(toggle);

                            toggle.onclick = (ev) => {
                                ev.preventDefault();
                                const visible = input.type !== 'password';
                                input.type = visible ? 'password' : 'text';
                                // update icon (use opposite since visible var was previous state)
                                toggle.innerHTML = makeIcon(!visible);
                            };
                        }
                        
                        if(attr.type === 'file'){
                            input.setAttribute('accept',attr?.accept);
                            input.dataset.fileId = 0;
                                
                            const sizeFeedback = document.createElement('div');
                            sizeFeedback.classList.add('m-1');
                            sizeFeedback.innerHTML = 'Max. Boyut : 40 MB ve Dosya Tipi : .pdf, .xls, .xlsx, .jpg, .jpeg, .png olmalıdır.';
                            sizeFeedback.style.display = 'block';
                            iDiv.parentNode.appendChild(sizeFeedback);


                            const fileData = JSON.parse(data?.entities?.[attr.name] ?? '{}');
                            if(fileData?.description){
                                
                                input.dataset.fileId = fileData.id;
                                input.dataset.file = 'Dosya Mevcut';

                                switch(fileData?.last_status?.op_key){
                                    case 'doc_file_accepted':
                                        input.classList.add('is-valid');
                                        const validFeedback = document.createElement('div');
                                        validFeedback.classList.add('valid-feedback');
                                        validFeedback.innerHTML = 'Dosya kabul edilmiş durumda.';
                                        validFeedback.style.display = 'block';
                                        iDiv.parentNode.appendChild(validFeedback);
                                        break;
                                    case 'doc_file_rejected':
                                        input.classList.add('is-invalid');
                                        const invalidFeedback = document.createElement('div');
                                        invalidFeedback.classList.add('invalid-feedback');
                                        invalidFeedback.innerHTML = 'Dosya reddedilmiş durumda. Lütfen yeni bir dosya yükleyiniz.';
                                        invalidFeedback.style.display = 'block';
                                        iDiv.parentNode.appendChild(invalidFeedback);
                                        break;
                                }

                                const showB     = document.createElement('span');
                                showB.classList.add('input-group-text','rmv-btn-form');
                                showB.innerHTML = '<i class="ki-outline ki-magnifier fs-4"></i>';
                                showB.onclick   = (e) => {
                                    window.open('/order-file/'+(fileData?.qnid ?? fileData?.description));
                                };
                                iDiv.prepend(showB);
                                /*const small = document.createElement('a');
                                small.href  = '#';
                                small.innerHTML = '<a href="/order-file/'+fileData?.description+'" target="_BLANK"><small>Mevcut Dosya İçin Tıklayınız..</small></a>';
                                small.classList.add('text-danger','mt-2','d-block');
                                
                                iDiv.parentNode.appendChild(small);*/

                                
                                // Simulate file inserted for native labels
                                const myFileContent = ['My File Content'];
                                const myFile = new File(myFileContent, fileData?.description);

                                // Create a data transfer object. Similar to what you get from a `drop` event as `event.dataTransfer`
                                const dataTransfer = new DataTransfer();

                                // Add your file to the file list of the object
                                dataTransfer.items.add(myFile);

                                // Save the file list to a new variable
                                const fileList = dataTransfer.files;
                                input.files = fileList;
                            }
                        }else {
                            input.value          = data?.entities?.[attr.name] ?? (attr.defaultValue ?? '');
                            
                            if(this.formDataStore.addional?.[attr.name] !== undefined){
                                input.value = this.formDataStore.addional[attr.name];
                                input.dispatchEvent(new Event('input') );
                            }
                        } 
                        if(attr?.defaultValue) input.dispatchEvent(new Event('input'));

                        if(attr?.isMasked){
                            input.dataset.mask    = attr.mask;
                            switch (attr.mask) {
                                case 'money':
                                    input.style.textAlign = 'right';
                                    //for non float values
                                    //if(!input.value.includes('.')) input.value += '.00';
                                    new VMasker(input).maskMoney({
                                        // Decimal precision -> "90"
                                        precision: 2,
                                        // Decimal separator -> ",90"
                                        separator: ',',
                                        // Number delimiter -> "12.345.678"
                                        delimiter: '.',
                                    });
                                    break;
                                case 'phone':
                                    new  VMasker(input).maskPattern("(999) 999-99-99");
                                    break;
                                case 'custom':
                                    new  VMasker(input).maskPattern(attr.format);
                                    break;
                                default:
                                    break;
                            }
                        }

                        //do this after is added to div because lightpick component is not seeing before
                        if(attr?.isDate == true){
                            input.readOnly    = true;
                            input.placeholder = 'Tarih Seçiniz';
                            if(data?.entities?.[attr.name] !== undefined){
                                if(attr?.hasTime){
                                    let dvalue        = data?.entities?.[attr.name].split(' ');
                                    input.value       = dvalue[0].split('-').reverse().join('/')+' '+dvalue[1];
                                }else{
                                    input.value       = data?.entities?.[attr.name] !== undefined ? data?.entities[attr.name].split('-').reverse().join('/') : '';
                                }
                            }
                            if(attr?.readOnly !== true){
                                // initialize flatpickr
                                const fpOptions = {
                                    dateFormat : attr?.hasTime ? 'd/m/Y H:i' : 'd/m/Y',
                                    enableTime : !!attr?.hasTime,
                                    time_24hr  : true,
                                    locale     : 'tr',
                                    defaultDate: data?.entities?.[attr.name] ?? null,
                                    onChange   : (selectedDates, dateStr, instance) => {
                                        if(selectedDates.length){
                                            const formatted = instance.formatDate(selectedDates[0], attr?.hasTime ? 'd/m/Y H:i' : 'd/m/Y');
                                            input.value = formatted;
                                            input.dispatchEvent(new Event('input', { bubbles: true }));
                                        }
                                    }
                                };
                                
                                const fp = flatpickr(input, fpOptions);
                                this.flatpickrInstances.push(fp);
                            }
                            
                            input.readOnly = true;
                        }

                        if(attr?.isMonth == true){
                            input.readOnly    = true;
                            input.placeholder = 'Tarih Seçiniz';
                            input.value       = data?.entities?.[attr.name] !== undefined ? data?.entities[attr.name].split('-').reverse().join('/') : '';

                            // initialize flatpickr with month select plugin
                            const fpOptions = {
                                dateFormat : 'm/Y',
                                locale     : 'tr',
                                defaultDate: data?.entities?.[attr.name] ?? null,
                                onChange   : (selectedDates, dateStr, instance) => {
                                    if(selectedDates.length){
                                        const formatted = instance.formatDate(selectedDates[0], 'm/Y');
                                        input.value = formatted;
                                        input.dispatchEvent(new Event('input', { bubbles: true }));
                                    }
                                },
                                plugins    : [ new monthSelectPlugin({ shorthand: true, dateFormat: 'm/Y', altFormat: 'F Y' }) ]
                            };

                            const fp = flatpickr(input, fpOptions);
                            this.flatpickrInstances.push(fp);

                        }

                        return iDiv;
                    };

                    const createSelect = async (attr,iDiv = null) => {
                        if(iDiv == null){
                            iDiv = document.createElement('div');
                            iDiv.classList.add('col-lg-'+(attr.col ?? '12'),'d-flex');
                        }


                        const input = document.createElement('select');
                        input.classList.add('form-item','form-select');
                        //input.dataset.fileId = fileId;
                        input.dataset.rowId  = rowId;
                        input.dataset.tag    = tag;
                        input.name           = attr.name;

                        if(attr?.class !== undefined)    input.classList.add(...attr?.class);
                        if(attr?.disabled !== undefined && attr.disabled) iDiv.style.display = 'none';
                        if(attr?.required !== undefined) input.required = attr.required;
                        if(attr?.hidden){
                            input.hidden = attr.hidden;
                            iDiv.hidden = attr.hidden;
                            if(iDiv.parentNode) iDiv.parentNode.style.display = 'none';
                        }  
                        
                        let op      = document.createElement('option');
                        op.value    = '';
                        op.text     = attr?.placeholder ?? 'Seçiniz';
                        op.selected = true;

                        input.appendChild(op);

                        if(attr?.setOptions !== undefined){
                            attr.options = await  attr.setOptions();
                        }
                        input.oninput = (e) => attr.oninput(e);
                        for (let index = 0; index < attr?.options?.length; index++) {
                            op = document.createElement('option');
                            op.text  = attr.options[index].text;
                            op.value = attr.options[index].value;
                            if(attr.options[index].key !== undefined) op.dataset.key = attr.options[index].key;
                            if(attr.options[index].limit !== undefined) op.dataset.limit = attr.options[index].limit;
                            if(attr.options[index].data !== undefined) op.dataset.info = JSON.stringify(attr.options[index].data);
                            if(data?.entities?.[attr.name] !== undefined && data?.entities?.[attr.name] == op.value) op.selected = true;
                            if(this.formDataStore.addional?.[attr.name] !== undefined && this.formDataStore.addional[attr.name] == op.value){
                                op.selected = true;
                                input.dispatchEvent(new Event('input') );
                            }
                            
                            input.appendChild(op);
                        }

                        if(this.formDataStore.addional?.[attr.name] !== undefined){
                            input.dispatchEvent(new Event('input') );
                        }
                        iDiv.appendChild(input);
                        if(data?.entities?.[attr.name] !== undefined){
                            if(attr?.triggerInputOnSetup === undefined || attr?.triggerInputOnSetup == true) input.dispatchEvent(new Event('input'));
                        } 

                        
                        //NiceSelect.bind(input, attr?.isSearchable ? {searchable : true} : {});
                        
                        return iDiv;
                    };
                    
                    let inputDiv = document.createElement('div');
                    inputDiv.classList.add('col-lg-12');
                    inputDiv.classList.add('row');
                    
                    switch (fitem.type) {   
                        case 'sub':
                        case 'multiple':
                            this.keyLock = [];
                            //this method will add sub elements
                            const addElements = async (nameTag = null,withClick = false) => {
                                
                                //addional item row element
                                const row = document.createElement('div');
                                row.classList.add('row','mb-2','multiple-item-row');
                                
                                inputDiv.appendChild(row);

                                //row components
                                for(let i = 0;i < fitem.subs.length; i++){
                                    const element = fitem.subs[i];
                                    
                                    const el = {...element};
                                    //create unique nametag
                                    if(nameTag == null) nameTag = (new Date).getTime()+'-'+inputDiv.querySelectorAll("[name^="+el.name+"]").length;
                                    
                                    const rowElm = document.createElement('div');
                                    rowElm.classList.add('col-md-'+el.col);
                                    let lbl   = document.createElement('label');
                                    lbl.classList.add('form-label','mt-5','input-label');
                                    //item label
                                    if(el?.label !== undefined && el?.label != ''){
                                        lbl.innerHTML = el.label;
                                        rowElm.appendChild(lbl);
                                    }

                                    

                                    const inpGroup = document.createElement('div');
                                    inpGroup.classList.add('input-group');
                                    rowElm.appendChild(inpGroup);

                                    el.name = fitem.type == 'multiple' ? el.name+'**'+(fitem.group_key ?? 'unalign-group-key') + '**'+ nameTag : el.name;
                                    
                                    if(el.type == 'select'){
                                        await createSelect(el,rowElm);
                                    }else{
                                        createInput(el,inpGroup);
                                    }

                                    

                                    //if sub element has subs :D
                                    if(el?.multiple){
                                        lbl.classList.add('d-flex','justify-content-between','align-items-center');
                                        const   addSub    = async (name = null) => {
                                            const inpGroup2 = document.createElement('div');
                                            inpGroup2.classList.add('input-group','mt-2');
                                            rowElm.appendChild(inpGroup2);

                                            const cloneElm = {...el};

                                            const number   = rowElm.querySelectorAll("[name^='"+el.name+"']").length;
                                            cloneElm.name += '-'+number;
                                            if(name != null) cloneElm.name = name;
                                            cloneElm.type == 'select' ? await createSelect(cloneElm, rowElm) : createInput(cloneElm, inpGroup2);
                                            
                                            //sub item remove
                                            const rmvInp     = document.createElement('span');
                                            rmvInp.classList.add('input-group-text','rmv-btn-form');
                                            rmvInp.innerHTML = '<i class="fa fa-trash fs-5 selectable-icon"></i>';
                                            rmvInp.onclick   = (e) => {
                                                this.formData.removedData.push({
                                                    id    : rowId,
                                                    type  : 'entity',
                                                    key   : cloneElm.name
                                                });
                                                delete this.formData?.dynamicF?.[tag+'**'+rowId]?.entities?.[cloneElm.name];

                                                inpGroup2.remove();

                                                //custom event for talk panel
                                                cloneElm?.onremove(rowElm);
                                            };
                                            inpGroup2.appendChild(rmvInp);
                                        }
                                        const   inpPlus   = document.createElement('i');
                                        inpPlus.classList.add('fa','fa-plus','fs-4','selectable-icon');
                                        inpPlus.onclick   = async (e) => addSub();
                                        lbl.prepend(inpPlus);
                                        
                                        //search if data has this items
                                        for(let key in data.entities){
                                            if(key.includes(el.name) && key.split(el.name)[1] !== undefined && key.split(el.name)[1] !== ''){
                                                addSub(key);
                                            }
                                        }
                                        
                                    }

                                    if(el.isDate){
                                        const calInp     = document.createElement('span');
                                        calInp.classList.add('input-group-text');
                                        calInp.innerHTML = '<i class="fa fa-calendar fs-5"></i>';
                                        calInp.onclick = () => el.element.dispatchEvent(new Event('focus'));
                                        inpGroup.appendChild(calInp);
                                    }

                                    if(el.isMasked){
                                        const calInp     = document.createElement('span');
                                        switch (el.mask) {
                                            case 'phone':
                                                calInp.classList.add('input-group-text');
                                                calInp.innerHTML = '<i class="fa fa-phone fs-5"></i>';
                                                calInp.onclick = () => el.element.dispatchEvent(new Event('focus'));
                                                inpGroup.appendChild(calInp);
                                                break;
                                            case 'money':
                                                calInp.classList.add('input-group-text');
                                                calInp.innerHTML = el?.moneyIcon ?? '';
                                                calInp.onclick = () => el.element.dispatchEvent(new Event('focus'));
                                                if(el?.moneyIcon) inpGroup.appendChild(calInp);
                                                break;
                                            default:
                                                break;
                                        }
                                    }

                                    if(fitem.type == 'multiple' && fitem.subs[fitem.subs.length-1] === element){
                                        const rmvInp     = document.createElement('span');
                                        rmvInp.classList.add('input-group-text','rmv-btn-form');
                                        rmvInp.innerHTML = '<i class="selectable-icon ki-outline ki-trash fs-4"></i>';
                                        rmvInp.onclick   = (e) => {
                                            document.querySelectorAll('[name*="'+nameTag.split('-')[0]+'"]').forEach(rmElm => {
                                                this.formData.removedData.push({
                                                    id    : rowId,
                                                    type  : 'entity',
                                                    key   : rmElm.name
                                                });
                                                delete this.formData?.dynamicF?.[tag+'**'+rowId]?.entities?.[rmElm.name];
                                            });
                                            row.remove();
                                        };
                                        
                                        if(fitem?.removable != false ) inpGroup.appendChild(rmvInp);
                                        
                                    }

                                    
                                    row.appendChild(rowElm);
                                }

                                row.dataset.tag = (fitem.group_key ?? 'unalign-group-key')+'-'+nameTag.split('-')[0]+'-row';
                                this.keyLock.push(row.dataset.tag);
                            };

                            inLabel.classList.add('d-flex','justify-content-between','align-items-center');
                            if(fitem.label && fitem.label.trim().length > 0) inLabel.classList.add('form-section-heading');
                            else inLabel.remove();
                            
                            const iconDiv = document.createElement('div');
                            iconDiv.classList.add('align-items-center','bg-highlight','d-flex','flex-shrink-0','h-10','justify-content-center','me-5','rounded-circle','w-10');
                            
                            if(fitem.type === 'multiple'){
                                inputDiv.classList.add('border','rounded','p-2');
                                const icon = document.createElement('i');
                                icon.classList.add('ki-outline','selectable-icon','ki-plus','fs-1');
                                icon.id = tag+'-'+(fitem.group_key ?? 'unalign-group-key')+'-subadd-'+rowId;
                                iconDiv.appendChild(icon);

                                icon.onclick   = async () =>{
                                    await addElements(null,true);
                                }; 
                                
                                inLabel.appendChild(iconDiv);
                                
                                //await addElements(null,true);
                                let hasData = false;
                                //here create elements if data is exist on given data with object nametag
                                if(data?.entities){
                                    for(let key in data?.entities) {
                                        if(key.includes('**'+fitem.group_key) && !this.keyLock.includes(fitem.group_key+'-'+key.split('**')[2].split('-')[0]+'-row')){
                                            
                                            await addElements(key.split('**')[2]);
                                            hasData = true;
                                        } 
                                    };
                                }

                                if(!hasData){
                                    //append one empty row
                                    icon.click();
                                }

                                if(fitem?.hideAdd === true) icon.hidden = true;
                                
                            } 
                        
                            //here check multiple input values if exist
                            /*if(data?.entities !== undefined){
                                const keys = {};
                                Object.keys(data?.entities).filter(key => {
                                    if(key.includes(fitem.group_key)){
                                        keys[key.split('**')[1]+'**'+key.split('**')[2]] = true
                                    }
                                });

                                //foreach grouıp key item add row
                                for(let key in keys) addElements();
                            }else{
                                addElements();
                            }*/
                            
                            if(fitem.type == 'sub') await addElements();
                            itemRow.appendChild(inputDiv);
                            if(fitem.hidden == true) itemRow.style.display = 'none';
                            
                            if(fitem?.requiredIfFirst){
                                       
                                //here check row count
                                const rowCount = itemRow.querySelectorAll('.multiple-item-row').length;
                                if(rowCount > 1){
                                    //remove required attr from first row elements
                                    itemRow.querySelectorAll('.multiple-item-row .form-item').forEach(el => {
                                        el.required = false;
                                    });
                                }
                            }
                            break;
                        case 'section':
                            labelDiv.remove();
                            itemRow.appendChild(document.createElement('hr'));
                            break;
                        case 'yesno':
                            
                            const key = (new Date()).getTime();
                            inputDiv = document.createElement('div');
                            inputDiv.classList.add('col-lg-6','d-flex','flex-direction-row','justify-content-between');
                            
                            let checkDiv = document.createElement('div');
                            checkDiv.classList.add('form-check','form-check-custom','form-check-solid','form-check-lg');

                            
                            input = document.createElement('input');
                            input.type = 'radio';
                            input.oninput = (e) => fitem.oninput(e);
                            //input.dataset.fileId = fileId;
                            input.dataset.rowId  = rowId;
                            input.dataset.tag    = tag;
                            input.value          = 1;
                            input.name           = fitem.name+'*-*'+key;
                            input.classList.add('form-check-input','valid');


                            if( tag == 'op-doc-per-kanaat' && 
                                fitem.name == 'canWork' && 
                                document.querySelectorAll("[type='radio'][data-tag='"+tag+"'][name^='"+fitem.name+"']").length == 0 ){
                                input.checked = true;
                            }


                            if(data?.entities?.[fitem.name] !== undefined && data?.entities?.[fitem.name] == 1) input.checked = true;

                            checkDiv.appendChild(input);

                            let label  = document.createElement('label');
                            label.innerHTML = 'Evet';
                            label.classList.add('form-check-label');

                            checkDiv.appendChild(label);
                        
                            inputDiv.appendChild(checkDiv);
                            
                            checkDiv = document.createElement('div');
                            checkDiv.classList.add('form-check','form-check-custom','form-check-solid','form-check-lg');

                            input = document.createElement('input');
                            input.type = 'radio';
                            input.oninput = (e) => fitem.oninput(e);
                            //input.dataset.fileId = fileId;
                            input.dataset.rowId  = rowId;
                            input.dataset.tag    = tag;
                            input.name           = fitem.name+'*-*'+key;
                            input.value          = 0;
                            input.classList.add('form-check-input','valid');

                            
                            if(data?.entities[fitem.name] !== undefined && data.entities[fitem.name] == 0) input.checked = true;

                            checkDiv.appendChild(input);

                            label  = document.createElement('label');
                            label.innerHTML = 'Hayır';
                            label.classList.add('form-check-label');
                            
                            if( tag == 'op-doc-per-kanaat' && 
                                fitem.name == 'canWork' && 
                                document.querySelectorAll("[type='radio'][data-tag='"+tag+"'][name^='"+fitem.name+"']").length == 0 &&
                                (data?.entities[fitem.name] === undefined || data.entities[fitem.name] != 0)){
                                input.hidden = true;
                                label.hidden = true;
                            }


                            checkDiv.appendChild(label);
                        
                            inputDiv.appendChild(checkDiv);

                            itemRow.appendChild(inputDiv);


                            break;
                        case 'textarea':
                            input                = document.createElement('textarea');
                            input.classList.add('m-2');
                            input.name           = fitem.name;
                            //input.dataset.fileId = fileId;
                            input.dataset.rowId  = rowId;
                            input.dataset.tag    = tag;
                            input.classList.add(...fitem.class);
                            if(fitem?.required !== undefined) input.required = fitem.required;

                        
                            if(fitem?.hidden) input.hidden = fitem.hidden;

                            input.classList.add(...fitem.class);
                            input.oninput = (e) => fitem.oninput(e);

                            inputDiv.appendChild(input);
                            
                            if(data?.entities?.[fitem.name] !== undefined){
                                input.value = data.entities[fitem.name];
                                input.innerHTML = data.entities[fitem.name];
                            }

                            inLabel.classList.add('input-label');
                            itemRow.appendChild(inputDiv);

                            break;
                        case 'button':
                            input           = document.createElement('button');
                            input.onclick   = (e) => fitem.oninput(e);
                            input.innerHTML = fitem.value;
                            input.type      = 'button';
                            input.classList.add(...fitem.class);
                            inputDiv.appendChild(input);
                            itemRow.appendChild(inputDiv);
                            break;
                        case 'tree':
                            inputDiv = document.createElement('div');
                            inputDiv.classList.add('col-lg-'+fitem?.col,'d-flex','align-items-center');
                            itemRow.appendChild(inputDiv);
                            this.permissionTree = TreeModal.render({
                                target: inputDiv,
                                items: fitem?.list,
                                defaultChecked: data?.entities?.[fitem.name] ?? [],
                                onChange: (checkedItems) => fitem?.oncheck(checkedItems),
                            });

                            if (this.permissionTree) {
                                this._treeModalInstance = this.permissionTree;
                            }


                            break;
                        case 'select':
                            itemRow.appendChild(await createSelect(fitem));
                            inLabel.classList.add('input-label');
                            break;
                        case 'switch':
                            inLabel.classList.add('input-label');
                            inputDiv = document.createElement('div');
                            inputDiv.classList.add('form-check','m-5','form-check-custom','form-check-solid');
                            
                            input = document.createElement('input');
                            input.classList.add('form-check-input');
                            input.type = 'checkbox';
                            input.oninput = (e) => fitem.oninput(e);
                            //input.dataset.fileId = fileId;
                            input.dataset.rowId  = rowId;
                            input.dataset.tag    = tag;
                            input.name           = fitem.name;
                            if(fitem?.required !== undefined) input.required = fitem.required;

                            if(data?.entities?.[fitem.name] !== undefined && data?.entities?.[fitem.name] == 1) input.checked = true;

                            inputDiv.appendChild(input);

                            const lbl  = document.createElement('label');
                            lbl.classList.add('form-check-label','ps-2');
                            lbl.innerHTML = fitem?.desc ?? '-';
                            inputDiv.appendChild(lbl);
                            itemRow.appendChild(inputDiv);

                            if(fitem?.sub){
                                for (let index = 0; index < fitem.sub.length; index++) {
                                    const subItem = fitem.sub[index];

                                    inputDiv = document.createElement('div');
                                    inputDiv.classList.add('col-lg-4','fv-row');

                                    const subDiv = document.createElement('div');
                                    subDiv.classList.add('d-flex','align-items-center','gap-3');

                                    const sinput = document.createElement('input');
                                    sinput.type = subItem.type;
                                    sinput.name = subItem.name;
                                    sinput.placeholder = subItem.placeholder;
                                    //sinput.dataset.fileId = fileId
                                    sinput.dataset.rowId  = rowId;
                                    sinput.dataset.tag    = tag;
                                    sinput.oninput = (e) => subItem.oninput(e);
                                    sinput.classList.add(...subItem.class);
                                    if(fitem?.required !== undefined) sinput.required = fitem.required;

                                    if(data?.entities[subItem.name] !== undefined) sinput.value = data.entities[subItem.name];

                                    if(input.checked) sinput.style.visibility = 'visible';

                                    subDiv.appendChild(sinput);


                                    inputDiv.appendChild(subDiv);
                                    itemRow.appendChild(inputDiv);
                                }
                            }

                            if(fitem?.hidden){
                                itemRow.style.display = 'none';
                            }  
                            break;
                        default:
                            itemRow.appendChild(createInput(fitem,inputDiv));
                            break;
                    }
                    
                    rowSub.appendChild(itemRow);
                }

                row.appendChild(rowSub);


                const rmvBtn = document.createElement('a');
                rmvBtn.classList.add('btn','btn-sm','btn-outline-danger','w-100','btn-block');
                rmvBtn.href  = 'javascript:;';
                rmvBtn.onclick = () => {
                    /*if(!rowId.toString().includes('new')){
                        this.formData.removedData.push({
                            id    : rowId,
                            type  : 'connection'
                        });
                    }else{
                        delete this.formData.dynamicF[tag+'**'+rowId];
                    }*/
                    row.remove();
                };
                rmvBtn.innerHTML = `<i class="ki-duotone ki-trash fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span><span class="path3"></span>
                                    <span class="path4"></span><span class="path5"></span></i> Sil`;
                const footer = document.createElement('div');
                footer.classList.add('card-body');
                footer.appendChild(rmvBtn);

                row.appendChild(footer);

                if(form?.showRemoveButton == false) rmvBtn.remove();
            
                if(form?.isFoldable && data?.entities[form?.foldableTag] !== undefined ){
                    const collBtn = document.createElement('button');
                    collBtn.classList.add('btn','btn-outline-danger','btn-outline','row','w-100','m-0','fold-btn');
                    collBtn.innerHTML = data.entities[form?.foldableTag];
                    collBtn.type = 'button';
                    collBtn.onclick = () => {
                        target.querySelectorAll('.foldable-area').forEach(el => {if(el != row) el.hidden = true});
                        row.hidden = !row.hidden;

                        target.querySelectorAll('.fold-btn').forEach(el => {el.classList.remove('active')});

                        if(!row.hidden) collBtn.classList.add('active');
                    };
                    //for section seperation
                    target.appendChild(document.createElement('hr'));
                    target.appendChild(collBtn);

                    row.classList.add('foldable-area');
                    row.hidden = true;
                }

                target.appendChild(row);
                
                //for section seperation
                if(form?.isFoldable) target.appendChild(document.createElement('hr'));
                form.oncreated(rowId,row);
            },
            buildClientTable(target,clickEvent){
                    
                //set headers
                const headers = [
                    {
                        title : 'Firma Ünvan',
                        key   : 'title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Firma Kodu',
                        key   : 'clicode',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    }
                ];
                
                //initiate table
                this.table = new PickleTable({
                    container : target, //table target div
                    headers   : headers,
                    pageLimit : 10, // -1 for closing pagination
                    height    : '30vh',
                    type      : 'ajax',
                    columnSearch : true, // true - false for opening and closig
                    paginationType : 'number',// scroll - number (number for default)
                    ajax:{
                        url:'/api/v1/table/documents',
                        data:{
                            //order:{},
                        }
                    },
                    initialFilter : [
                        {
                            key   : 'form-type',
                            type  : '=',
                            value : 'op-doc-client-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-client'
                        }
                    ],
                    nextPageIcon : '<i class="ki-outline ki-arrow-right "></i>',
                    prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                    rowClick     : (elm,data)=> clickEvent(data),
                    rowFormatter : (elm,data)=>{
                        //console.log(elm,data);
                        //modify row element
                        //elm.style.backgroundColor = 'yellow';
                        //modify data
                        JSON.parse(data.main_attr).forEach(element => {
                            data[element['Key']] = element['Value'];
                            //if(data['cont_name'] == undefined) data['cont_name'] = []
                            //if(element['Key'].includes('cont_name')) data['cont_name'].push(element['Value']);
                        });
                        //data['cont_name'] = (data['cont_name'] ?? []).join(' , ');
                        //data.status = JSON.parse(data.status).OpTitle;
                        return data;
                    },
                });
            },
        },
        beforeUnmount() {
            if (Array.isArray(this.flatpickrInstances)) {
                this.flatpickrInstances.forEach(inst => {
                    try {
                        if (inst && typeof inst.destroy === 'function') inst.destroy();
                    } catch (e) {}
                });
                this.flatpickrInstances = [];
            }

            if (this._treeModalInstance && typeof this._treeModalInstance.destroy === 'function') {
                try { this._treeModalInstance.destroy(); } catch (e) {}
            }
        },
        
    }
</script>
<template>
    <div v-if="isLoadingRoles" class="text-center p-4">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Yükleniyor...</span>
        </div>
        <div>Roller yükleniyor, lütfen bekleyin...</div>
    </div>
    <div v-else>
        <div class="area-target" v-for="(item, index) in ftypes" :data-tag="item"></div>
        <AppFab v-if="authStore.data.type=='admin' || formtypes=='op-doc-request-form'" :savebtntitle="savebtntitle ?? 'Formu Kaydet'" :btntype="fabtype" :callback="formCallback" :rejectcallback="rejectcallback" :acceptcallback="acceptcallback" :cancelcallback="() => $router.go(-1)" />
    </div>
</template>

<style>
.custom-button{
    background-color: #154b91!important;
    color: #fff!important;
    border-color: #154b91!important;
    width: auto;
    margin-left: auto;
}
.custom-button:hover{
    background-color: #fff!important;
    color: #154b91!important;
    border-color: #0c3560!important;
}
.area-target .input-label{
    font-weight: 600 !important;
    color: slategrey !important;
}
</style>