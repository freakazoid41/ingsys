// forms/defs/product-category.js — field dictionary for 'op-doc-product-category-form'.
// Callbacks receive `ctx` (the form engine context) from formDefinitions.js.
export default (ctx) => ({

                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1',
                                label : 'Ürün Kategori Bilgisi',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'title',
                                        col      : 6,
                                        required : true,
                                        label : 'Başlık',
                                        placeholder : 'Kategori adı',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'code',
                                        col      : 6,
                                        required : true,
                                        label : 'Kod',
                                        placeholder : 'örn. ELK-001',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            },{
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'multiple',
                                name  : 'sub_files',
                                label : 'İstenen Dosya Listesi (şablon — dosya değil, tanım)',
                                group_key : 'category_files',
                                removable : true,
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'evrak_no',
                                        col      : 2,
                                        required : false,
                                        label : 'Evrak No',
                                        placeholder : 'örn. 8',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'standard',
                                        col      : 2,
                                        required : false,
                                        label : 'Standart',
                                        placeholder : 'örn. TS EN 60076',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'file_title',
                                        col      : 3,
                                        required : true,
                                        label : 'Belge Adı',
                                        placeholder : 'örn. Tip Test Raporu',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'file_group',
                                        col      : 3,
                                        required : true,
                                        label : 'Dosya Grubu',
                                        placeholder : 'örn. Tip Test Raporları',
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'select',
                                        name  : 'file_types',
                                        col      : 2,
                                        required : true,
                                        label : 'Dosya Tipleri',
                                        options : [
                                            { text : 'Seçiniz', value : '' },
                                            { text : 'PDF', value : 'pdf' },
                                            { text : 'PDF + Görsel (JPG/PNG)', value : 'pdf_img' },
                                            { text : 'Görsel (JPG/PNG)', value : 'img' },
                                            { text : 'Excel (XLS/XLSX)', value : 'xls' },
                                            { text : 'Tümü (PDF/JPG/PNG/XLS)', value : 'all' },
                                        ],
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'checks',
                                        name  : 'kva',
                                        col      : 12,
                                        required : false,
                                        label : 'Geçerli Güçler (boş = tümü)',
                                        emptyText : 'Henüz kVA tanımı yok — Ürün > Güç Tanımları sayfasından ekleyin.',
                                        setOptions : async () => {
                                            try {
                                                const fd = new FormData();
                                                fd.append('tableReq', JSON.stringify({ filter: [{ key : 'form-type', type : '=', value : 'op-doc-kva-form' }, { key : 'type', type : '=', value : 'op-doc-kva' }], scale : { page : 1, limit : 200 } }));
                                                const r = await fetch('/api/v1/table/documents', {
                                                    method : 'POST',
                                                    credentials : 'include',
                                                    headers : {
                                                        'X-Requested-With' : 'XMLHttpRequest',
                                                        ...(localStorage.getItem('token') ? { 'Authorization' : 'Bearer ' + localStorage.getItem('token') } : {})
                                                    },
                                                    body : fd
                                                });
                                                const j = await r.json();
                                                const rows = j?.data?.data || j?.data || [];
                                                return rows.map((row) => {
                                                    try {
                                                        const a = JSON.parse(row.main_attr || '[]');
                                                        const g = (k) => (a.find(x => x.Key === k) || {}).Value;
                                                        const code = g('code') || g('title');
                                                        if (!code) return null;
                                                        const t = g('title') || '';
                                                        return { text : (t && t !== code) ? (t + ' ' + code) : (t || code), value : code };
                                                    } catch(e) { return null; }
                                                }).filter(Boolean);
                                            } catch(e) { return []; }
                                        },
                                        oninput : (e) => ctx.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    }
);
