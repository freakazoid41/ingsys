<script>
    import Plib from '@/lib/pickle';
    import TalkFab from '@/components/talk/TalkFab.vue';
    import flatpickr from "flatpickr";
    import { Turkish } from "flatpickr/dist/l10n/tr.js"
    import NiceSelect from "nice-select2";
    import "nice-select2/dist/css/nice-select2.css";

    import 'flatpickr/dist/flatpickr.css';
    import VMasker  from 'vanilla-masker';
    import { wTrans } from 'laravel-vue-i18n';
    import { useFormDataStore } from '@/stores/formdata'
    import { useAuthStore } from '@/stores/auth';
    import QRCode from 'qrcode';
    import Swal from 'sweetalert2';
    import IMask from 'imask';


    export default {
        props: {
            formtypes : {
                type: String
            },
            savecallback: {
                type: Function
            }
        },
        components: {
            TalkFab,
        },
        setup() {
            //Object.assign(Datepicker.locales, tr);
            
            // expose to template and other options API hooks
            return {
                useFormDataStore,
                flatpickr,
                Turkish,
                Plib,
                IMask,
                VMasker,
                wTrans,
                useAuthStore
            }
        },
        data() {
            return {
                authStore       : useAuthStore(),
                formDataStore   : useFormDataStore(),
                plib            : new Plib(),
                ftypes          : this.formtypes.split(','),
                forms           : {
                    'op-doc-visit-form' : {
                        showRemoveButton : false,
                        oncreated        : (id) => {},
                        fields           : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1',
                                label : ' ',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'name',
                                        col      : 6,
                                        required : true,
                                        label : 'Ad',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask : 'phone',
                                        name  : 'phone',
                                        col      : 3,
                                        required : true,
                                        label : 'Telefon',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'email',
                                        col      : 3,
                                        required : true,
                                        label : 'E-Posta',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'select',
                                        col   : 6,
                                        required : true,
                                        options  :  [],
                                        setOptions  : async () => {
                                            return this.formDataStore.facilities.map(inv => {
                                                return {
                                                    text  : inv.title,
                                                    value : inv.title,
                                                };
                                            });
                                        },
                                        name  : 'facility',
                                        label : 'Tesis',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item','date-input'],
                                        type  : 'text',
                                        isDate : true,
                                        hasTime : true,
                                        name  : 'entered_at',
                                        col      : 3,
                                        required : true,
                                        label : 'Giriş',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item','date-input'],
                                        type  : 'text',
                                        isDate : true,
                                        hasTime : true,
                                        name  : 'exited_at',
                                        col      : 3,
                                        required : false,
                                        label : 'Çıkış',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1',
                                label : ' ',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'test-result',
                                        col      : 6,
                                        required : true,
                                        label : 'Test Sonucu',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'test-answers',
                                        col      : 6,
                                        required : true,
                                        label : 'Test Cevapları',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'textarea',
                                name  : 'feedback_desc',
                                col      : 12,
                                required : true,
                                label : 'Ziyaretçi Geri Bildirim Notu',
                                oninput : (e) => this.submitDynamicChanges(e.target)
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_3',
                                label : 'Verilen Ekipmanlar',
                                group_key : 'givengroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 6,
                                        /*options  :  [],
                                        setOptions  : async () => {
                                            return this.formDataStore.inventories.map(inv => {
                                                return {
                                                    text  : inv.title,
                                                    value : inv.title,
                                                };
                                            });
                                        },*/
                                        name  : 'inventory',
                                        label : 'Ekipman',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 6,
                                        name  : 'description',
                                        label : 'Ekipman Kodu',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_3',
                                label : 'Ziyaretçinin Getirdiği Ekipmanlar',
                                group_key : 'visitorinvgroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 6,
                                        /*options  :  [],
                                        setOptions  : async () => {
                                            return this.formDataStore.inventories.map(inv => {
                                                return {
                                                    text  : inv.title,
                                                    value : inv.title,
                                                };
                                            });
                                        },*/
                                        name  : 'inventory',
                                        label : 'Ekipman',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 6,
                                        name  : 'description',
                                        label : 'Ekipman Kodu',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_3',
                                label : 'Geri Alınan Ekipmanlar',
                                group_key : 'revievedgroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 6,
                                        /*options : [],
                                        setOptions  : async () => {
                                            return this.formDataStore.inventories.map(inv => {
                                                return {
                                                    text  : inv.title,
                                                    value : inv.title,
                                                };
                                            });
                                        },*/
                                        name  : 'inventory',
                                        label : 'Ekipman',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 6,
                                        name  : 'description',
                                        label : 'Açıklama',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    },'op-doc-inventory-form' : {
                        hasLang          : ['tr','en'],
                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1',
                                label : ' ',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'title',
                                        col      : 6,
                                        required : true,
                                        label : 'Envanter İsmi',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'code',
                                        col      : 6,
                                        required : true,
                                        label : 'Enventer Kodu',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },
                                ]
                            },
                        ]
                    },'op-doc-user-form'    : {
                        showRemoveButton : false,
                        oncreated        : (id) => {},
                        fields           : [
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
                                        disabled : useAuthStore().data.type_key === 'op-pert-reseller',
                                        col      : 4,
                                        required : true,
                                        label    : 'Kullanıcı Tipi',
                                        options  : [
                                            {
                                                text  : 'Yönetici',
                                                value : 'op-pert-admin'
                                            }, {
                                                text  : 'Tesis Görevlisi',
                                                value : 'op-pert-reseller'
                                            }
                                        ],
                                        oninput  : (e) => this.submitDynamicChanges(e.target)
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
                            },...[(useAuthStore().data.type_key !== 'op-pert-reseller' ? {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_31',
                                label : 'İletişim',
                                group_key : 'userfacilitygroup',
                                subs  : [
                                    {
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        col      : 6,
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
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 6,
                                        name  : 'userjob',
                                        label : 'Görev',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            } : {})]
                        ]
                    },'op-doc-facility-form' : {
                        hasLang          : ['tr','en'],
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
                                        col      : 4,
                                        required : true,
                                        label : 'Tesis İsmi',
                                        oninput : (e) => {
                                            document.querySelector('input[name="qr_code"]').value =  window.location.origin+'/facility/'+btoa(encodeURIComponent(e.target.value.trim()));
                                            document.querySelector('input[name="qr_code"]').dispatchEvent(new Event('input'));
                                            this.submitDynamicChanges(e.target);
                                        }
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'question_must_know',
                                        col      : 2,
                                        required : true,
                                        label : 'Doğru Soru Limit',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'text',
                                        name     : 'qr_code',
                                        isMasked : true,
                                        mask     : 'qrcode',
                                        col      : 6,
                                        required : true,
                                        label    : 'QR Kodu',
                                        oninput  : (e) => this.submitDynamicChanges(e.target)
                                    }
                                    
                                ]
                            },
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'textarea',
                                name  : 'address',
                                col      : 12,
                                required : true,
                                label : 'Adres',
                                oninput : (e) => this.submitDynamicChanges(e.target)
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_31',
                                label : 'İletişim',
                                group_key : 'facilitycontactgroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 4,
                                        name  : 'supervisor',
                                        label : 'Görevli',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 4,
                                        name  : 'job',
                                        label : 'Görev',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 4,
                                        name  : 'contact_mail',
                                        label : 'E-Posta',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_31',
                                label : 'Tesise Özel Ekipmanlar',
                                group_key : 'facilityinvetorygroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 6,
                                        /*options : [],
                                        setOptions  : async () => {
                                            return this.formDataStore.inventories.map(inv => {
                                                return {
                                                    text  : inv.title,
                                                    value : inv.title,
                                                };
                                            });
                                        },*/
                                        name  : 'inventory',
                                        label : 'Ekipman',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 6,
                                        name  : 'description',
                                        label : 'Ekipman Kodu',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_3',
                                label : 'Videolar',
                                group_key : 'videogroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'file',
                                        col   : 4,
                                        options  :  [],
                                        name  : 'videoitem',
                                        label : 'Video',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'select',
                                        col   : 4,
                                        options : [
                                            {
                                                text : 'Aktif',
                                                value : '1'
                                            },{
                                                text : 'Pasif',
                                                value : '0'
                                            }
                                        ],
                                        name  : 'status',
                                        label : 'Durum',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 4,
                                        name  : 'description',
                                        label : 'Açıklama',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_4',
                                label : 'Sorular',
                                group_key : 'questiongroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 4,
                                        options  :  [],
                                        name  : 'question',
                                        label : 'Soru',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 4,
                                        options  :  [],
                                        multiple : true,
                                        hasLetter: true,
                                        name  : 'answer',
                                        label : 'Cevaplar',
                                        onremove : (column) => {
                                            column.parentNode.querySelectorAll("[name^='answer']").forEach((el,i) => {
                                                el.value = String.fromCharCode(65+i) + '-) '+ (el.value.split('-) ')[1] ?? el.value);
                                                this.submitDynamicChanges(el);
                                            });
                                        },
                                        oninput : (e) =>{
                                            e.target.parentNode.parentNode.querySelectorAll("[name^='answer']").forEach((el,i) => {
                                                el.value = String.fromCharCode(65+i) + '-) '+ (el.value.split('-) ')[1] ?? el.value);
                                                this.submitDynamicChanges(el);
                                            });
                                            
                                        } 
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 4,
                                        options  :  [],
                                        name  : 'rightletter',
                                        label : 'Doğru Şık',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },
                                ]
                            },
                        ]
                    },
                },
                formData        : {
                    dynamicF : {},
                    files : {},
                    removedData : []
                }
            }
        },
        mounted() {
            
            this.ftypes.forEach(async key => {
                const formData = this.formDataStore.formData?.[key] ?? undefined;
                /**get facilities and inventories if visit form */
                if(key == 'op-doc-visit-form'){
                    await this.formDataStore.setFacilitiesData();
                    await this.formDataStore.setInventoriesData();
                }
                this.buildDynamicFForm(key,formData !== undefined ? Object.keys(formData)[0] : 'new-'+(new Date).getTime() ,formData !== undefined ? Object.values(formData)[0] : {});
            });

            this.formDataStore.setData({});
        },
        methods: {
            formCallback() {
                if(this.savecallback) return this.savecallback(this.formData);
            },
            submitDynamicChanges(el){
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
                        this.formData.files[tag+'**dynamicFile**'+el.dataset.fileId+'**'+rowId+'*-*'+name] = el.files[0];
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

            async buildDynamicFForm(tag,dynamicId = 'new-'+(new Date).getTime(),data = {},selector = null){
                const form    = this.forms[tag];
                const rowId   = dynamicId;
                const row     = document.createElement('div');
                const rowSub  = document.createElement('div');
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

                

                const createLangTabs = () => {
                    const langList = document.createElement('ul');
                    langList.classList.add('nav','nav-tabs');

                    form?.hasLang.forEach((ln,i) => {
                        const litem        = document.createElement('li');
                        litem.classList.add('nav-item');
                        litem.innerHTML    = '<a class="nav-link '+(i == 0 ? 'active' : '')+'" href="javascript:;">'+ln+'</a>';
                        litem.dataset.lang = ln;
                        litem.onclick = (e) => {
                            document.querySelectorAll('.item-row').forEach(linp => {
                                linp.hidden = ln !== linp.dataset.lang;
                            });
                            document.querySelectorAll('.nav-link').forEach(nl => nl.classList.remove('active'));
                            
                            litem.querySelector('a').classList.add('active');

                        }
                        langList.appendChild(litem);
                    });

                    const langRow = document.createElement('div');
                    langRow.classList.add('row');
                    langRow.appendChild(langList);

                    rowSub.appendChild(langRow);
                }

                /**
                 * this sub method will create input for given attributes to given element
                 */
                const createInput = (attr,iDiv = null) => {
                    if(iDiv == null){
                        iDiv = document.createElement('div');
                        iDiv.classList.add('col-lg-'+(attr.col ?? '12'),'d-flex');
                    }
                    

                    const input          = document.createElement('input');
                    input.type           = attr.type;
                    input.name           = attr.name;
                    input.dataset.rowId  = rowId;
                    input.dataset.tag    = tag;
                    input.placeholder    = attr?.placeholder ?? '';
                    
                    attr.element         = input;
                    
                    if(attr?.readOnly)                input.readOnly = attr.readOnly;
                    if(attr.class !== undefined)      input.classList.add(...attr?.class);
                    if(attr?.required !== undefined)  input.required = attr.required;
                    if(attr?.hidden)                  input.hidden = attr.hidden;

                    input.classList.add('form-item');
                    
                    input.oninput = (e) => attr.oninput(e);
                    iDiv.appendChild(input);
                    
                    if(attr.type === 'file'){
                        input.setAttribute('accept',attr?.accept);
                        input.dataset.fileId = 0;
                            
                        const fileData = JSON.parse(data?.entities?.[attr.name] ?? '{}');
                        if(fileData?.description){
                            
                            input.dataset.fileId = fileData.id;
                            input.dataset.file = 'Dosya Mevcut';


                            const showB     = document.createElement('span');
                            showB.classList.add('input-group-text','rmv-btn-form');
                            showB.innerHTML = '<i class="fa fa-camera fs-5 selectable-icon"></i>';
                            showB.onclick   = (e) => {
                                window.open('/order-file/'+fileData?.description);
                            };
                            iDiv.prepend(showB);
                            
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
                        attr.value           = data?.entities?.[attr.name] ?? '';
                        input.value          = data?.entities?.[attr.name] ?? '';
                    } 

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
                                IMask(input, {
                                    mask: '+{9\\0} (500) 000 00 00'
                                });
                                //new  VMasker(input).maskPattern("9 (999) 999-99-99");
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
                        

                        flatpickr(input, {
                            "locale": Turkish,
                            ...(attr?.hasTime ? {enableTime: true,dateFormat: 'd/m/Y H:i',time_24hr : true} : {dateFormat: 'd/m/Y'})
                        });
                        
                        input.readOnly = true;
                        //just for this date component
                        input.addEventListener('changeDate',e => e.target.dispatchEvent(new Event('input')));

                    }

                    if(attr?.isMonth == true){
                        input.readOnly    = true;
                        input.placeholder = 'Tarih Seçiniz';
                        input.value       = data?.entities?.[attr.name] !== undefined ? data?.entities[attr.name].split('-').reverse().join('/') : '';
                        flatpickr(input, {
                            plugins: [new monthSelectPlugin({
                                shorthand: true, //defaults to false
                                dateFormat: "m/y", //defaults to "F Y"
                            })]
                        });
                        //just for this date component
                        input.addEventListener('changeDate',e => e.target.dispatchEvent(new Event('input')));

                    }
                    
                    return iDiv;
                };

                /**
                 * this sub method will create select for given attributes to given element
                 */
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
                    if(attr?.disabled !== undefined) input.disabled = attr?.disabled;
                    if(attr?.required !== undefined) input.required = attr.required;
                    
                    let op      = document.createElement('option');
                    op.value    = '';
                    op.text     = attr?.placeholder ?? 'Seçiniz';
                    op.selected = true;

                    input.appendChild(op);

                    if(attr?.setOptions !== undefined){
                        attr.options = await  attr.setOptions();
                    }

                    for (let index = 0; index < attr?.options?.length; index++) {
                        op = document.createElement('option');
                        op.text  = attr.options[index].text;
                        op.value = attr.options[index].value;
                        if(attr.options[index].key !== undefined) op.dataset.key = attr.options[index].key;
                        if(attr.options[index].limit !== undefined) op.dataset.limit = attr.options[index].limit;

                        if(data?.entities?.[attr.name] !== undefined && data?.entities?.[attr.name] == op.value){
                            attr.value           = data?.entities?.[attr.name] ?? '';
                            op.selected          = true;
                        } 

                        input.appendChild(op);
                    }

                    input.oninput = (e) => attr.oninput(e);

                    iDiv.appendChild(input);
                    
                    if(attr?.hasMultiple !== undefined){
                        input.multiple = true;
                        const valued = new NiceSelect(input, {searchable: true});
                    } 
                    return iDiv;
                };

                /**
                 * this sub method will create all elements for form
                 */
                const createElements = async (langTag = 'tr',fromLang = false) => {
                    for (let index = 0; index < form.fields.length; index++) {
                        const fitem = form.fields[index];
                        if(Object.keys(fitem).length == 0) continue;

                        //add lang tag to name
                        if(fitem?.name) fitem.name           = fitem.name + (langTag !== 'tr' ? '--lng--'+langTag : '');
                        if(langTag != 'tr') fitem.required = false;

                        const itemRow = document.createElement('div');
                        itemRow.classList.add('row','mt-3','mb-6','item-row');
                        itemRow.dataset.lang = langTag;
                        if(fitem?.rowClass !== undefined) itemRow.classList.add(...fitem?.rowClass);

                        const inLabel   = document.createElement('label');
                        inLabel.classList.add('form-label');
                        if(fitem?.required) inLabel.classList.add('required');
                        inLabel.innerHTML = fitem.label.trim().length == 0 ? '<span> </span>' : (fitem.label + ' '+(langTag !== 'tr' ? '('+langTag+')' : ''));
                        
                        itemRow.appendChild(inLabel);

                        let input = null;

                        let inputDiv = document.createElement('div');
                        inputDiv.classList.add('col-lg-12');
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
                                        el.name = el.name + (langTag !== 'tr' ? '--lng--'+langTag : '');
                                        if(langTag != 'tr') el.required = false;
                                        //create unique nametag
                                        if(nameTag == null) nameTag = (new Date).getTime()+'-'+inputDiv.querySelectorAll("[name^="+el.name+"]").length;
                                        
                                        const rowElm = document.createElement('div');
                                        rowElm.classList.add('col-md-'+el.col);
                                        //item label
                                        let lbl   = document.createElement('label');
                                            lbl.classList.add('form-label','mt-5');
                                        if(el?.label !== undefined && el?.label != ''){
                                            
                                            
                                            lbl.innerHTML = el.label + ' '+(langTag !== 'tr' ? '('+langTag+')' : '');
                                            
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
                                            calInp.innerHTML = '<i class="fa fa-calendar fs-5 text-body-emphasis"></i>';
                                            calInp.onclick = () => el.element.dispatchEvent(new Event('focus'));
                                            inpGroup.appendChild(calInp);
                                        }

                                        if(el.isMasked){
                                            const calInp     = document.createElement('span');
                                            switch (el.mask) {
                                                case 'phone':
                                                    calInp.classList.add('input-group-text');
                                                    calInp.innerHTML = '<i class="fa fa-phone fs-5 text-body-emphasis"></i>';
                                                    calInp.onclick = () => el.element.dispatchEvent(new Event('focus'));
                                                    inpGroup.appendChild(calInp);
                                                    break;
                                                case 'money':
                                                    calInp.classList.add('input-group-text');
                                                    calInp.innerHTML = el?.moneyIcon ?? '';
                                                    calInp.onclick = () => el.element.dispatchEvent(new Event('focus'));
                                                    if(el?.moneyIcon) inpGroup.appendChild(calInp);
                                                    break;
                                                case 'qrcode':
                                                    calInp.classList.add('input-group-text');
                                                    calInp.innerHTML = '<i class="fa fa-qrcode selectable-icon fs-5 text-body-emphasis"></i>';
                                                    calInp.onclick = async () => {
                                                        const data = await QRCode.toDataURL(inpGroup.querySelector('input').value.trim(),{
                                                            quality : 1,
                                                            width   : 1080
                                                        });
                                                        Swal.fire({
                                                            confirmButtonText : 'İndir',
                                                            showCloseButton   : true,
                                                            imageUrl: data,
                                                            imageHeight: 400,
                                                            preConfirm : () => {
                                                                const a = document.createElement('a');
                                                                a.href = data;
                                                                a.download = "output.png";
                                                                document.body.appendChild(a);
                                                                a.click();
                                                                document.body.removeChild(a);
                                                                return false;
                                                            }
                                                        });
                                                    };
                                                    inpGroup.appendChild(calInp);
                                                    break;
                                                default:
                                                    break;
                                            }
                                        }

                                        if(fitem.type == 'multiple' && fitem.subs[fitem.subs.length-1] === element){
                                            const rmvInp     = document.createElement('span');
                                            rmvInp.classList.add('input-group-text','rmv-btn-form');
                                            rmvInp.innerHTML = '<i class="fa fa-trash fs-5 selectable-icon"></i>';
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
                                            inpGroup.appendChild(rmvInp);
                                        }
                                        

                                        /*console.log(el.value);
                                        if(nameTag != null && fitem.type == 'multiple' && !withClick && (el.value?.trim() == '')){
                                            row.classList.add('isEmpty');
                                        }else{
                                            row.classList.remove('isEmpty');
                                        }*/

                                        row.appendChild(rowElm);
                                    }

                                    row.dataset.tag = (fitem.group_key?.replace('-','**') ?? 'unalign-group-key')+'-'+nameTag.split('-')[0]+'-row';

                                    /**
                                     * sometimes even without data , dynamic multi row areas are creating cross language inputs.
                                     * this is not weird because form needs to be have cross language inputs for diffrent language entries but if you create data without form (seeders .etc) or simply not use other language inputs it will create anyway
                                     * this area will clear unnecessary inputs for only multiple row areas (others (normal form elements) have only 1 cross reference (same name with diffrent lang key))
                                     * expensive but neccesarry..
                                     * only works when updating form
                                     */
                                    
                                    if(fitem.type == 'multiple' && !withClick){
                                        const searchKey = (langTag != 'tr' ? '--lng--'+langTag+'**' : '')+(fitem.group_key?.replace('-','**') ?? 'unalign-group-key')+'**'+nameTag.split('-')[0];

                                        let hasItem = Object.keys(data.entities).filter(str => str.includes(searchKey));
                                        if(langTag == 'tr') hasItem = hasItem.filter(str => !str.includes('--lng--'));

                                        if(hasItem.length == 0) row.remove();
                                    }

                                    this.keyLock.push(row.dataset.tag);
                                };

                                inLabel.classList.add('d-flex','justify-content-between','align-items-center');
                                
                                const iconDiv = document.createElement('div');
                                iconDiv.classList.add('align-items-center','bg-highlight','d-flex','flex-shrink-0','mb-2','h-10','justify-content-center','rounded-circle');
                                
                                //here check values are exist in given object
                                if(fitem.type === 'multiple'){
                                    itemRow.classList.add('border','rounded','p-2');
                                    //inputDiv.classList.add('border','rounded','p-2');
                                    const icon = document.createElement('i');
                                    icon.classList.add('fa','selectable-icon','fa-plus-circle','fs-2');
                                    icon.id = tag+'-'+(fitem.group_key ?? 'unalign-group-key')+'-subadd-'+rowId;
                                    iconDiv.appendChild(icon);

                                    icon.onclick   = async () => await addElements(null,true);
                                    
                                    inLabel.appendChild(iconDiv);
                                    await addElements(null,true);
                                    //here create elements if data is exist on given data with object nametag
                                    if(data?.entities){
                                        for(let key in data?.entities) {
                                            if(key.includes('**'+fitem.group_key) && !this.keyLock.includes(fitem.group_key+'-'+key.split('**')[2].split('-')[0]+'-row')){
                                                await addElements(key.split('**')[2]);
                                            } 
                                        };
                                    }
                                    
                                } 
                                
                                if(fitem.type == 'sub') addElements();
                                itemRow.appendChild(inputDiv);
                                
                                break;
                            case 'textarea':
                                input                = document.createElement('textarea');
                                input.name           = fitem.name + (langTag !== 'tr' ? '--lng--'+langTag : '');
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

                                itemRow.appendChild(inputDiv);

                                break;
                            case 'button':
                                input           = document.createElement('button');
                                input.innerHTML = fitem.value;
                                input.type      = 'button';
                                input.classList.add(...fitem.class);
                                inputDiv.appendChild(input);
                                itemRow.appendChild(inputDiv);
                                break;
                            case 'select':
                                itemRow.appendChild(createSelect(fitem));
                                break;
                            default:
                                itemRow.appendChild(createInput(fitem,inputDiv));
                                break;
                        }
                        
                        rowSub.appendChild(itemRow);
                    }
                }

                //here integrate lang system
                if(form?.hasLang?.length > 0){
                    createLangTabs();

                    for(let i=0;i<form?.hasLang?.length;i++) {
                        await createElements(form?.hasLang?.[i],true);
                        if(i != 0) rowSub.querySelectorAll('.item-row[data-lang="'+form?.hasLang?.[i]+'"]').forEach(linp => linp.hidden = true);
                    };
                }else{
                    await createElements();
                }
                

                row.appendChild(rowSub);

                const footer = document.createElement('div');
                footer.classList.add('card-body');
                

                row.appendChild(footer);
                target.appendChild(row);
                form.oncreated(rowId,row);
            },
        }
    }
</script>
<template>
    <div class="area-target" v-for="(item, index) in ftypes" :data-tag="item">
       
    </div>
    <TalkFab v-if="authStore.data.type=='admin'" btntype="saveBtn" :callback="formCallback"/>
    
</template>