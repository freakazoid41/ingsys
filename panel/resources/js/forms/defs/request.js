// forms/defs/request.js — field dictionary for 'op-doc-request-form'.
// Callbacks receive `ctx` (the form engine context) from formDefinitions.js.
export default (ctx) => ({

                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'text',
                                name  : 'title',
                                col      : 12,
                                required : true,
                                label : 'Başlık',
                                placeholder : 'Başlık giriniz',
                                oninput : (e) => ctx.submitDynamicChanges(e.target)
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_donem',
                                label : '',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'donem_bas',
                                        col      : 6,
                                        required : true,
                                        isMonth : true,
                                        label : 'Başlangıç Ayı',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'donem_bit',
                                        col      : 6,
                                        required : true,
                                        isMonth : true,
                                        label : 'Bitiş Ayı',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
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
                            }
                        ]
                    }
);
