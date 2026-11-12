// forms/defs/product.js — field dictionary for 'op-doc-product-form'.
// Callbacks receive `ctx` (the form engine context) from formDefinitions.js.
export default (ctx) => ({

                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1',
                                label : 'Ürün Bilgisi',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'prod_code',
                                        col      : 6,
                                        required : true,
                                        label : 'Ürün Kodu',
                                        placeholder : 'örn. 40.1.2.001',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'short_name',
                                        col      : 6,
                                        required : true,
                                        label : 'Kısa Ad',
                                        placeholder : 'Kısa ürün adı',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'select',
                                        name  : 'unit',
                                        col      : 6,
                                        required : true,
                                        label : 'Birim',
                                        options : [
                                            { text : 'Seçiniz', value : '' },
                                            { text : 'ST (Adet)', value : 'ST' },
                                            { text : 'KG (Kilogram)', value : 'KG' },
                                            { text : 'M (Metre)', value : 'M' },
                                        ],
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'category_title',
                                        col      : 6,
                                        required : true,
                                        readOnly : true,
                                        label : 'Malzeme Grubu (listeden seçin)',
                                        placeholder : 'Henüz seçilmedi',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'category_qnid',
                                        col      : 6,
                                        required : false,
                                        hidden : true,
                                        label : 'Kategori ID',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    }
);
