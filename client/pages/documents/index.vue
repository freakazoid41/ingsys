
<template>
    <div class="row area-target" data-tag="op-doc-per-edu">
        
    </div>
    <AppFab btntype="saveBtn" :callback="alerts" />
    
</template>
<script>
const loadActive = useState('loadActive')
export default {
    mounted(){
        this.forms = {
            'op-doc-per-edu' : {
                showRemoveButton : false,
                oncreated        : () => {
                    document.getElementById('op-doc-per-edu-alckttrlr-subadd').click();
                },
                fields           : [
                    {
                        class : ['searchable-select','form-item'],
                        type  : 'select',
                        name  : 'type_id',
                        required : true,
                        label : 'Eğitim Seviyesi',
                        /*options : (() => {
                            const container = document.querySelector('.addDynamicArea[data-tag="op-doc-per-edu"]');
                            if(container !== null){
                                const data = JSON.parse(container.dataset.types);
                            
                                return data.map(el=> {
                                    return {text : el.title , value : el.id}
                                });
                            }
                            return [];
                        })(),*/
                        oninput : (e) => this.submitDynamicChanges(e.target)
                    },{
                        label   : 'Anlaşılan Alacak ve Tutarlar',
                        type    : 'multiple',
                        group_key : 'alckttrlr',
                        subs    : [
                            {
                                class : ['searchable-select'],
                                type  : 'select',
                                isSearchable : true,
                                name  : 'alcktype',
                                col   : '6',
                                label : 'Alacak Türü',
                                placeholder : 'Alacak Türü Seçiniz',
                                options : (() => {
                                    return [{
                                        value : 'Kıdem Tazminatı',
                                        text  : 'Kıdem Tazminatı'
                                    },{
                                        value : 'Fazla Çalışma Ücreti',
                                        text  : 'Fazla Çalışma Ücreti'
                                    },{
                                        value : 'UBGT',
                                        text  : 'UBGT'
                                    },{
                                        value : 'Yıllık İzin Ücreti',
                                        text  : 'Yıllık İzin Ücreti'
                                    },{
                                        value : 'Hafta Tatili Ücreti',
                                        text  : 'Hafta Tatili Ücreti'
                                    },{
                                        value : 'Ücret Alacağı',
                                        text  : 'Ücret Alacağı'
                                    },{
                                        value : 'Prim',
                                        text  : 'Prim'
                                    },{
                                        value : 'Yol Ücreti',
                                        text  : 'Yol Ücreti'
                                    },{
                                        value : 'Yemek Ücreti',
                                        text  : 'Yemek Ücreti'
                                    },{
                                        value : 'İhbar Tazminatı',
                                        text  : 'İhbar Tazminatı'
                                    },{
                                        value : 'AGİ',
                                        text  : 'AGİ'
                                    },{
                                        value : 'Vardiya Ara Dinlenmesi',
                                        text  : 'Vardiya Ara Dinlenmesi'
                                    },{
                                        value : 'İş arama İzin Ücreti',
                                        text  : 'İş arama İzin Ücreti'
                                    },{
                                        value : 'Ücret Tenzilinden Doğan Fark Ücret Alacağı',
                                        text  : 'Ücret Tenzilinden Doğan Fark Ücret Alacağı'
                                    },{
                                        value : 'Rapor Ücreti Alacağı',
                                        text  : 'Rapor Ücreti Alacağı'
                                    },{
                                        value : 'Kötü Niyet Tazminatı',
                                        text  : 'Kötü Niyet Tazminatı'
                                    },{
                                        value : 'İşe Başlatmama Tazminatı',
                                        text  : 'İşe Başlatmama Tazminatı'
                                    },{
                                        value : 'Sendikal Tazminat',
                                        text  : 'Sendikal Tazminat'
                                    },{
                                        value : 'İşe İade Davası Kaynaklı Boşta Geçen Sürelere İlişkin Ücret',
                                        text  : 'İşe İade Davası Kaynaklı Boşta Geçen Sürelere İlişkin Ücret'
                                    },{
                                        value : 'İş Kazası/Meslek Hastalığı Sebebiyle Tazminat',
                                        text  : 'İş Kazası/Meslek Hastalığı Sebebiyle Tazminat'
                                    },{
                                        value : 'Eşit Davranmama Tazminatı',
                                        text  : 'Eşit Davranmama Tazminatı'
                                    },{
                                        value : 'Temel Ücret',
                                        text  : 'Temel Ücret'
                                    },{
                                        value : 'Vekalet Ücreti',
                                        text  : 'Vekalet Ücreti'
                                    },{
                                        value : 'Diğer',
                                        text  : 'Diğer'
                                    }];
                                    
                                })(),
                                oninput : (e) => submitDynamicChanges(e.target)
                            },{
                                class     : ['form-control','mb-2','mb-md-0',],
                                label     : 'Belgesi',
                                style     : 'input',
                                col       : '6',
                                name      : 'op-doc-per-arablclk-1',
                                type      : 'file',
                                accept    : "application/pdf",
                                oninput : (e) => submitDynamicChanges(e.target)
                            }
                        ],
                    },
                    {
                        class : ['form-control','mb-2','mb-md-0','date-input','form-item'],
                        type  : 'text',
                        name  : 'edu_title',
                        required : true,
                        label : 'Okul Bilgisi',
                        oninput : (e) => this.submitDynamicChanges(e.target)
                    },{
                        class : ['form-control','mb-2','mb-md-0','date-input','form-item'],
                        type  : 'text',
                        name  : 'area_title',
                        required : true,
                        label : 'Bölüm Bilgisi',
                        oninput : (e) => this.submitDynamicChanges(e.target)
                    },{
                        class : ['form-control','mb-2','mb-md-0','date-input'],
                        type  : 'text',
                        name  : 'completed_at',
                        isDate  : true,
                        label : 'Mezuniyet Tarihi',
                        oninput : (e) => this.submitDynamicChanges(e.target)
                    }
                ]
            },
        };

        this.buildDynamicFForm('op-doc-per-edu');

       
        loadActive.value = false;
    },  
    data() {
      return {
        bgColor: '#778899',
        position: 'top-right',
        fabActions: [
            {
                name: 'cache',
                icon: 'cached'
            },
            {
                name: 'alertMe',
                icon: 'add_alert'
            }
        ],
        loading: 'test' ,//useState('loadActive'),
      }
    },
    methods: {
        alerts(){
            loadActive.value = true;
            setTimeout(() => {
                loadActive.value = false;
            }, 1000);
        },
        submitDynamicChanges(el){
            /*const tag    = el.dataset.tag;
            const name   = el.name.split('*-*')[0];
            let value    = el.value;
            //const fileId = el.dataset.fileId;
            const rowId  = el.dataset.rowId;
            if(this.formData.dynamicF[tag+'**'+rowId] === undefined) this.formData.dynamicF[tag+'**'+rowId] = {
                entities : {},
                tag      : tag,
            };

            if(el.classList.contains('mask-this') && el.dataset.mask == 'money') value = value.replace(/\./g,'').replace(',','.');
    
            switch (el.type) {
                case 'file':
                    this.formData.files[tag+'**dynamicFile**'+el.dataset.fileId+'**'+rowId+'**'+name] = el.files[0];
                    break;
                default:
                    this.formData.dynamicF[tag+'**'+rowId].entities[name] = value;
                    if(el.type === 'checkbox') this.formData.dynamicF[tag+'**'+rowId].entities[name] = el.checked ? 1 : 0;
                    if(el.classList.contains('date-input')) this.formData.dynamicF[tag+'**'+rowId].entities[name] = value.trim().split('/').reverse().join('-');
                    break;
            }*/
            console.log(el.value);
        },
        buildDynamicFForm(tag,dynamicId = 'new-'+(new Date).getTime(),data = {},selector = null){
            const form   = this.forms[tag];
            const rowId  = dynamicId;
            const row    = document.createElement('div');
            const rowSub = document.createElement('div');
            let target;
            
            row.dataset.tag = tag;
            if(selector == null){
                target = document.querySelector('.area-target[data-tag="'+tag+'"]');
                row.classList.add('mb-10','mt-10','col-12','card','card-full');
                rowSub.classList.add('card-body');
            }else{
                row.classList.add('row');
                target = document.querySelector(selector);
            }
            
           
            
           

            for (let index = 0; index < form.fields.length; index++) {
                const fitem = form.fields[index];
                
                const itemRow = document.createElement('div');
                itemRow.classList.add('row','mt-3','mb-6','item-row');
                if(fitem?.rowClass !== undefined) itemRow.classList.add(...fitem?.rowClass);

                const inLabel   = document.createElement('label');
                inLabel.classList.add('form-label');
                if(fitem?.required) inLabel.classList.add('required');
                inLabel.innerHTML = fitem.label;
                
                itemRow.appendChild(inLabel);

               

                let input = null;

                const createInput = (attr,inputDiv = null) => {
                    if(inputDiv == null){
                        inputDiv = document.createElement('div');
                        inputDiv.classList.add('col-lg-'+(attr.col ?? '12'),'d-flex');
                    }
                    

                    const input          = document.createElement('input');
                    input.type           = attr.type;
                    input.name           = attr.name;
                    input.dataset.rowId  = rowId;
                    input.dataset.tag    = tag;
                    if(attr?.readOnly)  input.readOnly = attr.readOnly;
                    if(attr.class !== undefined) input.classList.add(...attr?.class);
                    input.classList.add('form-item');
                    input.placeholder    = attr?.placeholder ?? '';
                    if(attr?.required !== undefined) input.required = attr.required;

                    if(attr?.hidden) input.hidden = attr.hidden;

                    /*if(data?.entities[attr.name] !== undefined){
                        input.value = data.entities[attr.name];
                        if(attr?.isDate == true || attr?.isMonth == true) input.value = data.entities[attr.name].split(' ')[0].split('-').reverse().join('/');
                    }*/

                
                    input.oninput = (e) => attr.oninput(e);
                    inputDiv.appendChild(input);

                    /*if(attr?.class.includes('mask-this')){
                        input.dataset.mask    = attr.mask;
                        switch (attr.mask) {
                            case 'money':
                                input.style.textAlign = 'right';
                                VMasker(input).maskMoney({
                                    // Decimal precision -> "90"
                                    precision: 2,
                                    // Decimal separator -> ",90"
                                    separator: ',',
                                    // Number delimiter -> "12.345.678"
                                    delimiter: '.',
                                });
                                break;
                            case 'phone':
                                VMasker(input).maskPattern("(999) 999-99-99");
                                break;
                            case 'custom':
                                VMasker(input).maskPattern(attr.format);
                                break;
                            default:
                                break;
                        }
                    }*/

                    if(attr.type === 'file'){
                        //input.dataset.fileId = 0;
                        input.setAttribute('accept',attr?.accept);
                        input.dataset.fileId = 0;

                        /*let small = document.createElement('small');
                        small.innerHTML = 'En fazla 40MB lık dosya yükleyiniz.';
                        inputDiv.appendChild(small);*/

                        
                        /*if(data?.files[attr.name] !== undefined){
                            const fileData = Object.values(data?.files[attr.name])[0];
                            input.dataset.fileId = fileData.file_id;
                            //input.dataset.file = fileData?.file;
                            input.dataset.file = 'Dosya Mevcut';
                            if(fileData?.last_status === 'doc_file_rejected'){
                                input.classList.add('is-invalid');
                                
                                inLabel.setAttribute('style','padding:0 !important');
                                labelDiv.appendChild(document.createElement('br'));
                                let sm   = document.createElement('small');
                                sm.style.color = 'tomato';
                                sm.innerHTML = '('+fileData?.last_entry+')';
                                labelDiv.appendChild(sm);
                            
                            }

                            
                            small = document.createElement('a');
                            small.href  = '#';
                            small.innerHTML = '<a href="/order-file/'+fileData?.file+'" target="_BLANK"><small>Mevcut Dosya İçin Tıklayınız..</small></a>';
                            small.classList.add('text-danger','mt-2','d-block');
                            inputDiv.appendChild(small);

                            input.required = false;
                        }*/
                    } 
                
                    //do this after is added to div because lightpick component is not seeing before
                    /*if(attr?.isDate == true){
                        input.placeholder = 'Tarih Seçiniz';
                        
                        new Lightpick({
                            field  : input,
                            format : 'DD/MM/YYYY',
                            onSelect: (start, end) => {
                                input.dispatchEvent(new Event('input'));
                            }
                        });
                    }
                    
                    
                    if(attr?.isMonth == true){
                        console.log(attr)
                        input.placeholder = 'Tarih Seçiniz';
                        flatpickr(input,{
                            plugins: [
                                new monthSelectPlugin({
                                shorthand: true, //defaults to false
                                dateFormat: "m/Y", //defaults to "F Y"
                                })
                            ],
                            onChange: (selectedDates, dateStr, instance) =>{
                                input.dispatchEvent(new Event('input'));
                            }
                        });


                    }

                    if(attr?.isRange == true){
                        
                        new Lightpick({
                            field: input,
                            singleDate: false,
                            numberOfMonths: 2,
                            footer:true,
                            lang : 'tr-TR',
                            autoclose: false,
                            onSelect: (start, end) => {
                                input.dispatchEvent(new Event('input'));
                                document.querySelector('.lightpick__footer-message').innerHTML = start.format('DD/MM/YYYY')+ ' - '+end.format('DD/MM/YYYY');
                            },
                            ...(attr?.maxDate !== undefined ? {
                                startDate: moment(attr.maxDate).subtract(1,'months'),
                                //endDate: moment(fitem.maxDate),
                                maxDate : moment(attr.maxDate),
                            } : {})
                        });
                       
                    }*/

                    return inputDiv;
                };

                const createSelect = (attr,inputDiv = null) => {
                    if(inputDiv == null){
                        inputDiv = document.createElement('div');
                        inputDiv.classList.add('col-lg-'+(attr.col ?? '12'),'d-flex');
                    }


                    const input = document.createElement('select');
                    //input.dataset.fileId = fileId;
                    input.dataset.rowId  = rowId;
                    input.dataset.tag    = tag;
                    input.name           = attr.name;
                    if(attr?.class !== undefined) input.classList.add(...attr?.class);
                    input.classList.add('form-item','form-select');
                    if(attr?.required !== undefined) input.required = attr.required;
                    
                    let op      = document.createElement('option');
                    op.value    = '';
                    op.text     = attr?.placeholder ?? 'Seçiniz';
                    op.selected = true;
                    input.appendChild(op);
                    for (let index = 0; index < attr?.options?.length; index++) {
                        op = document.createElement('option');
                        op.text  = attr.options[index].text;
                        op.value = attr.options[index].value;
                        if(attr.options[index].key !== undefined) op.dataset.key = attr.options[index].key;
                        if(attr.options[index].limit !== undefined) op.dataset.limit = attr.options[index].limit;
                        //if(data?.entities[attr.name] !== undefined && data.entities[attr.name] == op.value) op.selected = true;

                        input.appendChild(op);
                    }

                    input.oninput = (e) => attr.oninput(e);

                    inputDiv.appendChild(input);
                    //NiceSelect.bind(input, attr?.isSearchable ? {searchable : true} : {});

                    return inputDiv;
                };
                
                const inputDiv = document.createElement('div');
                inputDiv.classList.add('col-lg-12');
                switch (fitem.type) {   
                    case 'sub':
                    case 'multiple':
                        inputDiv.classList.add('border','rounded','p-5');
                        inLabel.classList.add('d-flex','justify-content-between','align-items-center');
                        
                        const iconDiv = document.createElement('div');
                        iconDiv.classList.add('align-items-center','bg-highlight','d-flex','flex-shrink-0','h-10','justify-content-center','me-5','rounded-circle','w-10');


                        const icon = document.createElement('i');
                        icon.classList.add('ph','selectable-icon','ph-list-plus','fs-1','text-body-emphasis');
                        icon.id = tag+'-'+fitem.group_key+'-subadd';
                        iconDiv.appendChild(icon);

                        icon.onclick   = () => addElements();
                        
                        inLabel.appendChild(iconDiv);
                        //inputDiv.appendChild(multipleRow);
                        
                        const addElements = () => {
                            //addional item row element
                            const row = document.createElement('div');
                            row.classList.add('row','mt-2');

                            //row components
                            fitem.subs.forEach(element => {
                                const el = {...element};
                               
                                const rowElm = document.createElement('div');
                                rowElm.classList.add('col-md-'+el.col);
                                //item label
                                if(el?.label !== undefined && el?.label != ''){
                                    let lbl   = document.createElement('label');
                                    lbl.classList.add('form-label');
                                    
                                    lbl.innerHTML = el.label;
                                    
                                    rowElm.appendChild(lbl);
                                }

                                el.name = fitem.type == 'multiple' ? el.name+'**'+fitem.group_key + '**'+ inputDiv.querySelectorAll("[name^="+el.name+"]").length : el.name;
                                let elm;
                                if(el.type == 'select'){
                                    createSelect(el,rowElm);
                                }else{
                                    createInput(el,rowElm);
                                }
                                //rowElm.appendChild(elm);
                                
                                


                                row.appendChild(rowElm);
                            });

                            
                            
                            inputDiv.appendChild(row);

                            /*const rmvRow = document.createElement('a');
                            rmvRow.classList.add('btn','btn-sm','btn-light-danger');
                            rmvRow.href  = 'javascript:;';
                            rmvRow.onclick = () => {
                                

                                row.remove();
                            };
                            rmvRow.innerHTML = `<i class="ki-duotone ki-trash fs-5">
                                                <span class="path1"></span>
                                                <span class="path2"></span><span class="path3"></span>
                                                <span class="path4"></span><span class="path5"></span></i> Sil`;
                            row.appendChild(rmvRow);*/
                        };
                     
                        //here check multiple input values if exist
                        /*if(data?.entities !== undefined){
                            const keys = {};
                            Object.keys(data?.entities).filter(key => {
                                if(key.includes(fitem.group_key)){
                                    keys[key.split('**')[1]+'**'+key.split('**')[2]] = true
                                }
                            });

                            //foreach grouıp key item add row
                            for(let key in keys) addElements();
                        }else{
                            addElements();
                        }*/
                        
                        
                    
                        itemRow.appendChild(inputDiv);
                        break;
                    case 'section':
                        labelDiv.remove();
                        itemRow.appendChild(document.createElement('hr'));
                        break;
                    case 'yesno':
                        
                        const key = (new Date()).getTime();
                        inputDiv = document.createElement('div');
                        inputDiv.classList.add('col-lg-6','d-flex','flex-direction-row','justify-content-between');
                        
                        let checkDiv = document.createElement('div');
                        checkDiv.classList.add('form-check','form-check-custom','form-check-solid','form-check-lg');

                        
                        input = document.createElement('input');
                        input.type = 'radio';
                        input.oninput = (e) => fitem.oninput(e);
                        //input.dataset.fileId = fileId;
                        input.dataset.rowId  = rowId;
                        input.dataset.tag    = tag;
                        input.value          = 1;
                        input.name           = fitem.name+'*-*'+key;
                        input.classList.add('form-check-input','valid');


                        if( tag == 'op-doc-per-kanaat' && 
                            fitem.name == 'canWork' && 
                            document.querySelectorAll("[type='radio'][data-tag='"+tag+"'][name^='"+fitem.name+"']").length == 0 ){
                            input.checked = true;
                        }


                        if(data?.entities[fitem.name] !== undefined && data.entities[fitem.name] == 1) input.checked = true;

                        checkDiv.appendChild(input);

                        let label  = document.createElement('label');
                        label.innerHTML = 'Evet';
                        label.classList.add('form-check-label');

                        checkDiv.appendChild(label);
                    
                        inputDiv.appendChild(checkDiv);
                        
                        checkDiv = document.createElement('div');
                        checkDiv.classList.add('form-check','form-check-custom','form-check-solid','form-check-lg');

                        input = document.createElement('input');
                        input.type = 'radio';
                        input.oninput = (e) => fitem.oninput(e);
                        //input.dataset.fileId = fileId;
                        input.dataset.rowId  = rowId;
                        input.dataset.tag    = tag;
                        input.name           = fitem.name+'*-*'+key;
                        input.value          = 0;
                        input.classList.add('form-check-input','valid');

                        
                        if(data?.entities[fitem.name] !== undefined && data.entities[fitem.name] == 0) input.checked = true;

                        checkDiv.appendChild(input);

                        label  = document.createElement('label');
                        label.innerHTML = 'Hayır';
                        label.classList.add('form-check-label');
                        
                        if( tag == 'op-doc-per-kanaat' && 
                            fitem.name == 'canWork' && 
                            document.querySelectorAll("[type='radio'][data-tag='"+tag+"'][name^='"+fitem.name+"']").length == 0 &&
                            (data?.entities[fitem.name] === undefined || data.entities[fitem.name] != 0)){
                            input.hidden = true;
                            label.hidden = true;
                        }


                        checkDiv.appendChild(label);
                    
                        inputDiv.appendChild(checkDiv);

                        itemRow.appendChild(inputDiv);


                        break;
                    case 'textarea':
                        input                = document.createElement('textarea');
                        
                        input.name           = fitem.name;
                        //input.dataset.fileId = fileId;
                        input.dataset.rowId  = rowId;
                        input.dataset.tag    = tag;
                        input.classList.add(...fitem.class);
                        if(fitem?.required !== undefined) input.required = fitem.required;

                    
                        if(fitem?.hidden) input.hidden = fitem.hidden;

                        input.classList.add(...fitem.class);
                        input.oninput = (e) => fitem.oninput(e);

                        inputDiv.appendChild(input);
                        
                        if(data?.entities[fitem.name] !== undefined){
                            input.value = data.entities[fitem.name];
                            input.innerHTML = data.entities[fitem.name];
                        }

                        itemRow.appendChild(inputDiv);

                        break;
                    case 'button':
                        input           = document.createElement('button');
                        input.innerHTML = fitem.value;
                        input.type      = 'button';
                        input.classList.add(...fitem.class);
                        inputDiv.appendChild(input);
                        itemRow.appendChild(inputDiv);
                        break;
                    case 'select':
                        itemRow.appendChild(createSelect(fitem));
                        break;
                    case 'switch':
                        inputDiv = document.createElement('div');
                        inputDiv.classList.add('col-lg-4','fv-row','d-flex','align-items-center');
                        
                        const switchDiv = document.createElement('div');
                        switchDiv.classList.add('form-check','form-switch','form-check-custom','form-check-solid','me-10');

                        const lbl  = document.createElement('label');
                        lbl.classList.add('switch');

                        input = document.createElement('input');
                        input.type = 'checkbox';
                        input.oninput = (e) => fitem.oninput(e);
                        //input.dataset.fileId = fileId;
                        input.dataset.rowId  = rowId;
                        input.dataset.tag    = tag;
                        input.name           = fitem.name;
                        if(fitem?.required !== undefined) input.required = fitem.required;

                        if(data?.entities[fitem.name] !== undefined && data.entities[fitem.name] == 1) input.checked = true;

                        lbl.appendChild(input);
                        const sspan = document.createElement('span');
                        sspan.classList.add('slider','round');
                        lbl.appendChild(sspan);


                        switchDiv.appendChild(lbl);
                        inputDiv.appendChild(switchDiv);
                        itemRow.appendChild(inputDiv);

                        if(fitem?.sub){
                            for (let index = 0; index < fitem.sub.length; index++) {
                                const subItem = fitem.sub[index];

                                inputDiv = document.createElement('div');
                                inputDiv.classList.add('col-lg-4','fv-row');

                                const subDiv = document.createElement('div');
                                subDiv.classList.add('d-flex','align-items-center','gap-3');

                                const sinput = document.createElement('input');
                                sinput.type = subItem.type;
                                sinput.name = subItem.name;
                                sinput.placeholder = subItem.placeholder;
                                //sinput.dataset.fileId = fileId
                                sinput.dataset.rowId  = rowId;
                                sinput.dataset.tag    = tag;
                                sinput.oninput = (e) => subItem.oninput(e);
                                sinput.classList.add(...subItem.class);
                                if(fitem?.required !== undefined) sinput.required = fitem.required;

                                if(data?.entities[subItem.name] !== undefined) sinput.value = data.entities[subItem.name];

                                if(input.checked) sinput.style.visibility = 'visible';

                                subDiv.appendChild(sinput);


                                inputDiv.appendChild(subDiv);
                                itemRow.appendChild(inputDiv);
                            }
                        }
                        break;
                    default:
                        itemRow.appendChild(createInput(fitem,inputDiv));
                        break;
                }
                
                

                rowSub.appendChild(itemRow);
            }

            row.appendChild(rowSub);


            const rmvBtn = document.createElement('a');
            rmvBtn.classList.add('btn','btn-sm','btn-outline-danger','w-100','btn-block');
            rmvBtn.href  = 'javascript:;';
            rmvBtn.onclick = () => {
                /*if(!rowId.toString().includes('new')){
                    this.formData.removedData.push({
                        id    : rowId,
                        type  : 'dynamicF'
                    });
                }else{
                    delete this.formData.dynamicF[tag+'**'+rowId];
                }*/
                row.remove();
            };
            rmvBtn.innerHTML = `<i class="ki-duotone ki-trash fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span><span class="path3"></span>
                                <span class="path4"></span><span class="path5"></span></i> Sil`;
            const footer = document.createElement('div');
            footer.classList.add('card-body');
            footer.appendChild(rmvBtn);

            row.appendChild(footer);

            if(form?.showRemoveButton == false) rmvBtn.remove();
        
            if(form?.isFoldable && data?.entities[form?.foldableTag] !== undefined ){
                const collBtn = document.createElement('button');
                collBtn.classList.add('btn','btn-outline-danger','btn-outline','row','w-100','m-0','fold-btn');
                collBtn.innerHTML = data.entities[form?.foldableTag];
                collBtn.type = 'button';
                collBtn.onclick = () => {
                    target.querySelectorAll('.foldable-area').forEach(el => {if(el != row) el.hidden = true});
                    row.hidden = !row.hidden;

                    target.querySelectorAll('.fold-btn').forEach(el => {el.classList.remove('active')});

                    if(!row.hidden) collBtn.classList.add('active');
                };
                //for section seperation
                target.appendChild(document.createElement('hr'));
                target.appendChild(collBtn);

                row.classList.add('foldable-area');
                row.hidden = true;
            }

            target.appendChild(row);

            //for section seperation
            if(form?.isFoldable) target.appendChild(document.createElement('hr'));

            form.oncreated(row);
        }
    }
}



</script>