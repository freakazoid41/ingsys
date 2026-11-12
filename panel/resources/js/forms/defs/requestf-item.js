// forms/defs/requestf-item.js — field dictionary for 'op-doc-requestf-item-form'.
// Callbacks receive `ctx` (the form engine context) from formDefinitions.js.
export default (ctx) => ({

                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                name  : 'sub_requestf_items',
                                label : 'Talep Kalemleri',
                                type  : 'multiple',
                                removable : false,
                                requiredIfFirst : false,
                                hideAdd : true,
                                group_key : 'requestf_items',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'mal_grubu',
                                        col      : 3,
                                        required : false,
                                        label : 'Mal Grubu',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'ihale_grubu',
                                        col      : 3,
                                        required : false,
                                        label : 'İhale Grubu',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'prod_code',
                                        col      : 3,
                                        required : true,
                                        label : 'Malzeme Kodu',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'title',
                                        col      : 3,
                                        required : true,
                                        label : 'Malzeme Kısa Metni',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'select',
                                        name  : 'unit',
                                        col      : 3,
                                        required : true,
                                        label : 'Ölçü Birimi',
                                        options  : [
                                            { text: 'Adet (ST)', value: 'ST' },
                                            { text: 'KG', value: 'KG' },
                                            { text: 'Metre (M)', value: 'M' },
                                        ],
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'ay',
                                        col      : 3,
                                        required : true,
                                        isMonth : true,
                                        label : 'Ay',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'quantity',
                                        col      : 6,
                                        required : true,
                                        label : 'Miktar',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    }
);
