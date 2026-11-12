// forms/defs/offer.js — field dictionary for 'op-doc-offer-form'.
// Callbacks receive `ctx` (the form engine context) from formDefinitions.js.
export default (ctx) => ({

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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'offer_type',
                                        hidden : true,
                                        col      : 4,
                                        required : true,
                                        label : 'Teklif Türü',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'clititle',
                                        readOnly : true,
                                        col      : 4,
                                        required : true,
                                        label : 'Teklif Veren Cari',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cliid',
                                        readOnly : true,
                                        hidden : true,
                                        col      : 4,
                                        required : true,
                                        label : 'Teklif Veren Cari Kodu',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'date',
                                        isDate : true,
                                        readOnly : true,
                                        col      : 4,
                                        required : true,
                                        defaultValue : ctx.dayjs().format('DD/MM/YYYY'),
                                        label : 'Belge Tarihi',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file' ? 'select' : 'text',
                                        name     : 'target_type',
                                        col      : 4,
                                        required : true,
                                        readOnly : !(ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file'),
                                        label    : 'Alıcı',
                                        options  : [
                                            {
                                                text  : 'ADM',
                                                value : 'ADM'
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
                                            ctx.submitDynamicChanges(e.target)
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


                                            if(ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file'){
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
                                            


                                            ctx.submitDynamicChanges(e.target);
                                        } 
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'contract_start_date',
                                        col      : 4,
                                        isDate : true,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Teklif Başlangıç Tarihi',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'contract_end_date',
                                        col      : 4,
                                        isDate : true,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Teklif Bitiş Tarihi',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'load_area',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Ürün Yükleme Yeri',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'unload_area',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Ürün Boşaltma Yeri',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_5',
                                group_key : 'coal_specs',
                                hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                label : 'Kömürün Özellikleri / Red Şartları',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'coal_size',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Ebat Değeri',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'coal_hgi',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'HGİ Değeri',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'coal_ucucu',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Uçucu Madde Değeri',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'coal_type',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Cinsi Değeri',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'calory',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Kalori Değeri',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'humidity',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Nem Değeri',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'ash_content',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Kül Değeri',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'number',
                                        name  : 'sulfur',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        label : 'Kükürt Değeri',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_6',
                                group_key : 'calory_settings',
                                hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                label : 'Kömür kalori aralığına göre birim fiyatı',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'prime_condition_is',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Kalori Aralığı Başlangıç( Kcal)',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'prime_condition_is_bellow',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Kalori Aralığı Bitiş (Kcal)',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask  : 'money',
                                        name  : 'prime_unit_price',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Birim Fiyat',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        hasIcon : true,
                                        icon : 'Ton',
                                        label : 'Miktar',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type     : 'select',
                                        name     : 'payment_periods',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
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
                                        oninput  : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'payment_desc',
                                        col      : 6,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : "Hakediş Açıklama",
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        hasIcon : true,
                                        icon : '%',
                                        label : 'Birim Fiyatın  ((Tİ-ÜFE +TÜFE)/2 ) Etkilenme Oranı',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask  : 'money',
                                        name  : 'fuel_price_impact',
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : false,
                                        hasIcon : true,
                                        icon : '%',
                                        label : 'Birim Fiyatın Akaryakıt (Artış/Azalış) Etkilenme Oranı',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        isMasked : true,
                                        mask  : 'money',
                                        name  : 'transfer_price_impact',
                                        col      : 4,
                                        required : false,
                                        label : 'Nakliye Birim Fiyat',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : "Ödeme Vadesi",
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'transfer_start_date',
                                        isDate : true,
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Sevkiyata Başlangıç Tarihi',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'transfer_end_date',
                                        isDate : true,
                                        col      : 4,
                                        hidden : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] == 'op-doc-offer-file',
                                        required : ctx.useNavigationStore().routeParams?.offer_type?.split('**')[0] != 'op-doc-offer-file',
                                        label : 'Sevkiyat Bitiş Tarihi',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'textarea',
                                name  : 'desc',
                                col      : 12,
                                required : false,
                                label : 'Ek Açıklama',
                                oninput : (e) => ctx.submitDynamicChanges(e.target)
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
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png',
                                        name  : 'offer_otherdocs_file',
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
