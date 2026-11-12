// forms/defs/order-item.js — field dictionary for 'op-doc-order-item-form'.
// Callbacks receive `ctx` (the form engine context) from formDefinitions.js.
export default (ctx) => ({

                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_item_header',
                                label : '<h4>Kalem Bilgileri (SAP)</h4>',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'prod_code',
                                        col      : 3,
                                        readOnly : true,
                                        label : 'Ürün Kodu (MATNR**EBELP)',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'title',
                                        col      : 3,
                                        readOnly : true,
                                        label : 'Ürün Adı (TXZ01)',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'quantity',
                                        col      : 2,
                                        readOnly : true,
                                        isMasked : true,
                                        mask : 'money',
                                        label : 'Miktar (MENGE)',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'unit',
                                        col      : 2,
                                        readOnly : true,
                                        label : 'Birim (MEINS)',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_item_test',
                                label : 'Ürün Test Dokümanları',
                                group_key : 'item_test_docs',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png',
                                        name  : 'item_test_file',
                                        required : false,
                                        label : 'Test Dokümanı',
                                        col      : 12,
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_item_images',
                                label : 'Ürün Görselleri (Çoklu)',
                                group_key : 'item_images',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.jpg,.jpeg,.png,.pdf',
                                        name  : 'item_images_file',
                                        required : false,
                                        label : 'Görsel',
                                        col      : 12,
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    }
);
