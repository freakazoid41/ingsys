// forms/defs/order.js — field dictionary for 'op-doc-order-form'.
// Callbacks receive `ctx` (the form engine context) from formDefinitions.js.
export default (ctx) => ({

                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_order_header',
                                label : '<h4>Sipariş Bilgileri (SAP)</h4>',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'order_no',
                                        col      : 3,
                                        readOnly : true,
                                        label : 'Sipariş No (EBELN)',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'buying_no',
                                        col      : 3,
                                        readOnly : true,
                                        label : 'Satınalma No (SUBMI)',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'spec_code',
                                        col      : 3,
                                        readOnly : true,
                                        label : 'Cari Kodu',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'sys_code',
                                        col      : 3,
                                        readOnly : true,
                                        label : 'Şirket Kodu (BUKRS)',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'ctitle',
                                        col      : 3,
                                        readOnly : true,
                                        label : 'Firma Ünvanı (MCOD1)',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'created_at',
                                        col      : 3,
                                        isDate : true,
                                        readOnly : true,
                                        label : 'Sipariş Tarihi (BEDAT)',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'textarea',
                                name  : 'order_desc',
                                col      : 12,
                                rows     : 3,
                                required : false,
                                label : 'Açıklama',
                                placeholder : 'Açıklama giriniz',
                                oninput : (e) => ctx.submitDynamicChanges(e.target)
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'text',
                                name  : 'imalatci_firma_adi',
                                col      : 12,
                                required : false,
                                label : 'İmalatçı Firma Adı',
                                placeholder : 'İmalatçı Firma Adı',
                                oninput : (e) => ctx.submitDynamicChanges(e.target)
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                name  : 'sub_order_kabul',
                                label : 'Malzeme Kabul Formu',
                                type  : 'multiple',
                                removable : false,
                                requiredIfFirst : true,
                                hideAdd : true,
                                group_key : 'transfer_kabul',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png',
                                        name  : 'transfer_kabul_file',
                                        required : true,
                                        label : ' ',
                                        col      : 12,
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                name  : 'sub_order_cins',
                                label : 'Malzeme Cins-Miktar Kabul Formu',
                                type  : 'multiple',
                                removable : false,
                                requiredIfFirst : true,
                                hideAdd : true,
                                group_key : 'transfer_cins',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png',
                                        name  : 'transfer_cins_file',
                                        required : true,
                                        label : ' ',
                                        col      : 12,
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    }
);
