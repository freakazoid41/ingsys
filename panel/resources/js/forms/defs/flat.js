// forms/defs/flat.js — field dictionary for 'op-doc-flat-form'.
// Callbacks receive `ctx` (the form engine context) from formDefinitions.js.
export default (ctx) => ({

                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_4',
                                label : ' ',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'title',
                                        col      : 3,
                                        required : true,
                                        label : 'Daire İsmi',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1',
                                label : '',
                                type  : 'multiple',
                                group_key : 'flatcontgroup',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cont_name',
                                        //required : true,
                                        label : 'Kat Malik Adı',
                                        col      : 6,
                                        placeholder : 'Kat Malik Adı',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'cont_phone',
                                        isMasked : true,
                                        mask : 'phone',
                                        label : 'Kat Malik Telefon',
                                        col      : 6,
                                        placeholder : 'Kat Malik Telefon',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    }
);
