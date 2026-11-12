// forms/defs/requestf.js — field dictionary for 'op-doc-requestf-form'.
// Callbacks receive `ctx` (the form engine context) from formDefinitions.js.
export default (ctx) => ({

                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'text',
                                name  : 'title',
                                col      : 6,
                                required : true,
                                label : 'Başlık',
                                placeholder : 'Başlık giriniz',
                                oninput : (e) => ctx.submitDynamicChanges(e.target)
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'text',
                                name  : 'request_no',
                                col      : 6,
                                required : false,
                                label : 'Bağlı İhtiyaç No',
                                placeholder : 'İhtiyaç listesinden',
                                oninput : (e) => ctx.submitDynamicChanges(e.target)
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'textarea',
                                name  : 'desc',
                                col      : 12,
                                rows     : 3,
                                required : false,
                                label : 'Açıklama',
                                placeholder : 'Açıklama giriniz',
                                oninput : (e) => ctx.submitDynamicChanges(e.target)
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                name  : 'sub_talep_excel',
                                label : 'Yüklenen Excel',
                                type  : 'multiple',
                                hidden : true,
                                removable : false,
                                requiredIfFirst : false,
                                hideAdd : true,
                                group_key : 'talep_excel',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'file',
                                        accept : '.xls,.xlsx',
                                        name  : 'talep_excel_file',
                                        required : false,
                                        label : ' ',
                                        col      : 12,
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    }
);
