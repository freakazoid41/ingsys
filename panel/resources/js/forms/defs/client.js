// forms/defs/client.js — field dictionary for 'op-doc-client-form'.
// Callbacks receive `ctx` (the form engine context) from formDefinitions.js.
export default (ctx) => ({

                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['btn','btn-danger','custom-button'],
                                type  : 'button',
                                hidden : !(ctx.useAuthStore().typeKey == 'op-pert-reseller'),
                                name  : 'userrequest',
                                col : 4,
                                value : 'Kullanıcı Talebinde Bulun',
                                label : ' ',
                                oninput : (e) => {
                                    e.preventDefault();
                                    if(ctx.route?.params?.id === undefined || ctx.route?.params?.id == ''){
                                        ctx.plib.toast(ctx.Swal,'error','Lütfen önce cari oluşturunuz.');
                                        return false;
                                    }


                                    //ortak uretici: Turkce karakter uretmiyor (form dogrulamasi
                                    //yalnizca ASCII kabul ediyor) ve guvenli rastgelelik kullaniyor
                                    const password = ctx.generatePassword(8);

                                    ctx.Swal.fire({
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
                                            new ctx.VMasker(input).maskPattern("(999) 999-99-99");
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
                                            ctx.Swal.showLoading();

                                            const data = { 
                                                email, 
                                                phone, 
                                                password, 
                                                'cli_id': ctx.route?.params?.id,
                                                'cli_code': document.querySelectorAll('input[name="clicode"]')[0]?.value ?? '',
                                                'cli_title': document.querySelectorAll('input[name="title"]')[0]?.value ?? ''
                                            };

                                            
                                            const envelope = new FormData();
                                            for(let key in data) envelope.append(key,data[key]);
                                            
                                           
                                            const rsp = await ctx.plib.request({
                                                url      : '/api/v1/auth/register',
                                                method   : 'POST',
                                            },null,envelope);

                                            if(rsp.success){
                                                ctx.plib.toast(ctx.Swal, 'success', 'Talep gönderildi');
                                            }else{
                                                ctx.Swal.showValidationMessage(rsp.msg);
                                            }
                                            ctx.Swal.hideLoading();

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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'title',
                                        col      : 4,
                                        required : true,
                                        label : 'Firma Ünvanı',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'select',
                                        name  : 'client_system',
                                        col      : 4,
                                        required : true,
                                        label : 'Sistem',
                                        defaultValue : 'GDZ',
                                        options  : [
                                            { text: 'GDZ', value: 'GDZ' },
                                            { text: 'ADM', value: 'ADM' },
                                        ],
                                        oninput  : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'lifnr',
                                        col      : 4,
                                        required : true,
                                        label : 'Cari Kodu',
                                        placeholder : '0000300181',
                                        oninput : (e) => {
                                            e.target.value = e.target.value.replace(/\D/g,'').slice(0,10);
                                            if(e.target.value.length === 10) e.target.classList.remove('is-invalid');
                                            else if(e.target.value.length > 0) e.target.classList.add('is-invalid');
                                            ctx.submitDynamicChanges(e.target);
                                        }
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cli_vat_id',
                                        col      : 4,
                                        required : true,
                                        label : 'Firma Vergi Numarası',
                                        oninput : (e) =>{
                                            if(ctx.vergiKimlikDogrula(e.target.value)){
                                                e.target.classList.remove('is-invalid');
                                                e.target.classList.add('is-valid');
                                            }else{
                                                e.target.classList.remove('is-valid');
                                                e.target.classList.add('is-invalid');
                                            }
                                            ctx.submitDynamicChanges(e.target);
                                        }
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cli_vat_title',
                                        col      : 4,
                                        required : true,
                                        label : 'Firma Vergi Dairesi',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cli_fax',
                                        col      : 4,
                                        required : false,
                                        label : 'Firma Faks Numarası',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cli_website',
                                        col      : 4,
                                        required : false,
                                        label : 'Firma Web Sitesi',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'textarea',
                                name  : 'cli_desc',
                                col      : 12,
                                required : false,
                                label : 'Firma Ek Açıklama',
                                oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png',
                                        name  : 'cont_otherdocs_file',
                                        required : false,
                                        label : 'Belge Dosyası',
                                        col      : 6,
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    }
);
