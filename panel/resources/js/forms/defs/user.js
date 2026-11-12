// forms/defs/user.js — field dictionary for 'op-doc-user-form'.
// Callbacks receive `ctx` (the form engine context) from formDefinitions.js.
export default (ctx) => ({

                        showRemoveButton : false,
                        oncreated        : (id,row) => {
                            if(ctx.permissionTree.getChecked().length == 0){
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

                                    const password = ctx.generatePassword(8);
                                    const main  = document.querySelector('input[name="user_password"]');
                                    const check = document.querySelector('input[name="user_password_check"]');

                                    main.value  = password;
                                    check.value = password;

                                    //DOM'a yazmak yetmiyor: kaydetme payload'i formData.dynamicF'ten
                                    //okunuyor (UForm.vue), DOM'dan degil. Model guncellenmedigi icin
                                    //uretilen parola backend'e hic gitmiyordu.
                                    ctx.submitDynamicChanges(main);
                                    ctx.submitDynamicChanges(check);

                                    ctx.formData.passwordGenerated = true;

                                    //Parola bu modalda BILEREK gosterilmiyor: kaydetme basarisiz
                                    //olursa yonetici kopyaladigi parolanin calismadigini sonradan
                                    //ogrenirdi - issue #8'in yazilma sebebi tam olarak buydu.
                                    //Parola yalnizca kayit basariyla tamamlandiktan sonra gosterilir.
                                    const confirmed = await ctx.Swal.fire({
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
                                    if(confirmed.isConfirmed) ctx.formCallback();
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        name     : 'type_key',
                                        disabled : !ctx.useAuthStore().permissions?.includes('per-04-02'),
                                        col      : 4,
                                        required : ctx.useAuthStore().permissions?.includes('per-04-02'),
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
                                        oninput  : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        name     : 'user_status',
                                        disabled : !ctx.useAuthStore().permissions?.includes('per-04-02'),
                                        col      : 4,
                                        required : ctx.useAuthStore().permissions?.includes('per-04-02'),
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
                                        oninput  : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        name     : 'user_role',
                                        col      : 4,
                                        required : ctx.useAuthStore().permissions?.includes('per-04-02'),
                                        disabled : !ctx.useAuthStore().permissions?.includes('per-04-02'),
                                        label    : 'Rol',
                                        triggerInputOnSetup : false,
                                        options  : [],
                                        setOptions: async () => {
                                            const store = ctx.usePermissionDataStore()
                                            if (!store.roleList.length) await store.fetchRoleTemplates();
                                            return store.roleList.map(role => ({ text: role.name, value: role.op_key,data:role }))
                                        },
                                        oninput  : async (e) => {
                                            ctx.submitDynamicChanges(e.target);
                                            const selectedOption = e.target.options[e.target.selectedIndex];
                                            const permissionList = JSON.parse(selectedOption.dataset.info).permissions;
                                            await ctx.permissionTree.setChecked(permissionList);
                                       
                                        }
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        col      : 4,
                                        label    : 'Sistem',
                                        required : true,
                                        name     : 'user_grp_code',
                                        defaultValue : (document.querySelector('input[name="SYS_CODE"]')?.value || 'GDZ'),
                                        options  : [
                                            { text: 'GDZ', value: 'GDZ' },
                                            { text: 'ADM', value: 'ADM' },
                                            { text: 'İki Sistemde Mevcut', value: 'BOTH' },
                                        ],
                                        oninput  : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'email',
                                        name  : 'user_username',
                                        required : true,
                                        col : 4,
                                        placeholder : 'Kullanıcı Email',
                                        label : 'Kullanıcı Email',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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

                                            ctx.submitDynamicChanges(e.target);
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

                                            ctx.submitDynamicChanges(e.target);
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 4,
                                         mask  : 'phone',
                                        isMasked : true,
                                        name  : 'contphone',
                                        label : 'Telefon',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'email',
                                        col   : 4,
                                        name  : 'contmail',
                                        label : 'E-posta',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_312',
                                disabled : !ctx.useAuthStore().permissions?.includes('per-04-02'),
                                label : 'Bağlı Cariler',
                                col   : 12,
                                group_key : 'userclientgroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 3,
                                        readOnly : true,
                                        name  : 'cliid',
                                        hidden : true,
                                        label : '',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        col   : 3,
                                        readOnly : true,
                                        name  : 'clicode',
                                        label : 'Cari Kodu',
                                        onclick : ctx.pickClient,
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        readOnly : true,
                                        col   : 3,
                                        name  : 'clititle',
                                        label : 'Cari Başlık',
                                        onclick : ctx.pickClient,
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0'],
                                        type  : 'text',
                                        readOnly : true,
                                        col   : 3,
                                        name  : 'client_system',
                                        label : 'Sistem',
                                        placeholder : 'GDZ / ADM',
                                        onclick : ctx.pickClient,
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'tree',
                                name  : 'permissions',
                                label : 'İzinler',
                                disabled : !ctx.useAuthStore().permissions?.includes('per-04-02'),
                                list  : ctx.usePermissionDataStore().list,
                                col   : 6,
                                oncheck : (e) => ctx.submitDynamicChanges(e,true,'permissions')
                                
                            }
                        ]
                    }
);
