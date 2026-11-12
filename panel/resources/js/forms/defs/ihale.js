// forms/defs/ihale.js — field dictionary for 'op-doc-ihale-form'.
// Callbacks receive `ctx` (the form engine context) from formDefinitions.js.
export default (ctx) => ({

                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1',
                                label : 'İhale Bilgisi',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'ihale_kodu',
                                        col      : 6,
                                        required : true,
                                        label : 'İhale Kodu',
                                        placeholder : 'örn. IHL-2026-001',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'teklif_son_tarihi',
                                        col      : 6,
                                        isDate   : true,
                                        required : true,
                                        label : 'Teklif Son Tarihi',
                                        placeholder : 'gg.aa.yyyy',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                name  : 'sub_spec',
                                label : 'İlgili Teknik Şartname',
                                type  : 'multiple',
                                removable : false,
                                hideAdd : true,
                                group_key : 'ihale_spec',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'spec_title',
                                        col      : 6,
                                        required : true,
                                        label : 'İlgili Teknik Şartname Adı',
                                        placeholder : 'örn. Trafo Teknik Şartnamesi',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.pdf,.xls,.xlsx,.jpg,.jpeg,.png',
                                        name  : 'ihale_spec_file',
                                        col      : 6,
                                        // NOTE: file inputs can't use `required` — checkForm sees value=''
                                        // even after a pick, which would block every save. Required-ness is
                                        // enforced in IhForm.submitForm (hasSpecFile) instead.
                                        required : false,
                                        label : 'Dosya *',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                name  : 'sub_products',
                                label : 'Ürünler',
                                type  : 'multiple',
                                removable : true,
                                requiredIfFirst : true,
                                group_key : 'ihale_product_rows',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'product_qnid',
                                        col      : 12,
                                        required : false,
                                        hidden : true,
                                        label : 'Ürün ID',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'product_title',
                                        col      : 6,
                                        required : true,
                                        readOnly : true,
                                        label : 'Ürün (seçmek için tıklayın)',
                                        placeholder : 'Seçmek için tıklayın...',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'quantity',
                                        col      : 3,
                                        required : true,
                                        label : 'Miktar',
                                        placeholder : 'örn. 100',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'unit',
                                        col      : 3,
                                        required : false,
                                        readOnly : true,
                                        label : 'Birim',
                                        placeholder : '—',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    }
);
