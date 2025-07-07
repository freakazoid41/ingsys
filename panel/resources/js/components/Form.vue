<script>
    import Plib from '@/lib/pickle';
    import AppFab from '@/components/AppFab.vue';
    import { Datepicker } from 'vanillajs-datepicker';
    import VMasker  from 'vanilla-masker';
    import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';
    import { wTrans } from 'laravel-vue-i18n';
    import { useFormDataStore } from '@/stores/formdata'
    import { useAuthStore } from '@/stores/auth';

    export default {
        props: {
            formtypes : {
                type: String
            },
            savecallback: {
                type: Function
            }
        },
        components: {
            AppFab 
        },
        setup() {
            Object.assign(Datepicker.locales, tr);
            
            // expose to template and other options API hooks
            return {
                useFormDataStore,
                Datepicker,
                AppFab,
                Plib,
                VMasker,
                wTrans,
                useAuthStore
            }
        },
        data() {
            return {
                authStore    : useAuthStore(),
                formDataStore   : useFormDataStore(),
                plib            : new Plib(),
                ftypes          : this.formtypes.split(','),
                forms           : {
                    'op-doc-user-form'    : {
                        showRemoveButton : false,
                        oncreated        : (id) => {},
                        fields           : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1',
                                label : ' ',
                                subs  : [
                                    {
                                        class    : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'main_name',
                                        //isDate   : true,
                                        required : true,
                                        label : 'İsim & Soyisim',
                                        col      : 3,
                                        placeholder : 'İsim & Soyisim',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class    : ['form-control','mb-2','mb-md-0','date-input','form-item'],
                                        type     : 'select',
                                        name     : 'type_key',
                                        col      : 3,
                                        required : true,
                                        label    : 'Tip',
                                        options  : [
                                            {
                                                text  : 'Yönetici',
                                                value : 'op-pert-admin'
                                            }
                                        ],
                                        oninput  : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'email',
                                        name  : 'user_username',
                                        required : true,
                                        col : 3,
                                        placeholder : 'Kullanıcı Email',
                                        label : 'Kullanıcı Email',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'password',
                                        name  : 'user_password',
                                        //required : true,
                                        col : 3,
                                        label : 'Parola',
                                        placeholder : '*********',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    }
                                ]
                            }
                        ]
                    },
                    'op-doc-link-form' : {
                        showRemoveButton : false,
                        oncreated       : (id) => {},
                        fields          : [
                            {
                                class : ['form-control','mb-2','mb-md-0','form-item'],
                                type  : 'sub',
                                name  : 'sub_1',
                                label : ' ',
                                subs  : [
                                    {
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'title',
                                        col      : 12,
                                        required : true,
                                        label : 'Başlık',
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'long_link',
                                        col      : 12,
                                        required : true,
                                        label : 'Hedef Link',
                                        oninput : (e) => {
                                            this.submitDynamicChanges(e.target);
                                           
                                            const makeid = (length) => {
                                                var result           = '';
                                                var characters       = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                                                var charactersLength = characters.length;
                                                for ( var i = 0; i < length; i++ ) {
                                                    result += characters.charAt(Math.floor(Math.random() * charactersLength));
                                                }
                                                return result;
                                            }
                                            
                                            document.querySelector('input[name="short_link"]').value = makeid(10);
                                            document.querySelector('input[name="short_link"]').dispatchEvent(new Event('input'));

                                        }
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'domain',
                                        col      : 6,
                                        placeholder : 'aydem.tr/',
                                        label : 'Domain <i class="ph ph-lock"></i>',
                                        readOnly : true,
                                        oninput : (e) => this.submitDynamicChanges(e.target)
                                    },{
                                        class : ['form-control','mb-2','mb-md-0','form-item'],
                                        type  : 'text',
                                        name  : 'short_link',
                                        col      : 6,
                                        required : true,
                                        label : 'Kısa Link',
                                        oninput : async (e) => {
                                            //check uniques here
                                            const envelope = new FormData();
                                            envelope.append('key',e.target.value.trim());
                                            const rsp = await this.plib.request({
                                                url      : '/api/v1/checkunique',
                                                method   : 'POST',
                                            },null,envelope);

                                            if(rsp.success == true){
                                                e.target.classList.remove('is-invalid');
                                                this.submitDynamicChanges(e.target)
                                            }else{
                                                e.target.classList.add('is-invalid');
                                            }
                                        }
                                    },
                                ]
                            },
                        ]
                    },
                },
                formData        : {
                    dynamicF : {},
                    files : {},
                    removedData : []
                }
            }
        },
        mounted() {
            this.ftypes.forEach(key => {
                const formData = this.formDataStore.formData?.[key] ?? undefined;
                
                this.buildDynamicFForm(key,formData !== undefined ? Object.keys(formData)[0] : 'new-'+(new Date).getTime() ,formData !== undefined ? Object.values(formData)[0] : {});
            });

            this.formDataStore.setData({});
        },
        methods: {
            formCallback() {
                if(this.savecallback) this.savecallback(this.formData);
            },
            submitDynamicChanges(el){
                const tag    = el.dataset.tag;
                const name   = el.name.split('*-*')[0];
                let value    = el.value;
                
                const rowId  = el.dataset.rowId;
                if(this.formData.dynamicF[tag+'**'+rowId] === undefined) this.formData.dynamicF[tag+'**'+rowId] = {
                    entities : {},
                    tag      : tag,
                };

                //because vanilla masker , input events coming after shaping input value. make sure set everytime
                if(el.dataset.mask == 'money'){
                    if(value.includes(',')) value = value.replace(/\./g,'').replace(',','.');
                }
        
                switch (el.type) {
                    case 'file':
                        this.formData.files[tag+'**dynamicFile**'+el.dataset.fileId+'**'+rowId+'*-*'+name] = el.files[0];
                        break;
                    default:
                        this.formData.dynamicF[tag+'**'+rowId].entities[name] = value;
                        if(el.type === 'checkbox') this.formData.dynamicF[tag+'**'+rowId].entities[name] = el.checked ? 1 : 0;
                        if(el.classList.contains('date-input'))this.formData.dynamicF[tag+'**'+rowId].entities[name] = value.trim().split('/').reverse().join('-');
                        break;
                }
            },

            buildDynamicFForm(tag,dynamicId = 'new-'+(new Date).getTime(),data = {},selector = null){
                const form   = this.forms[tag];
                const rowId    = dynamicId;
                const row    = document.createElement('div');
                const rowSub = document.createElement('div');
                let target;

                row.dataset.id  = rowId;
                row.dataset.tag = tag;
                row.classList.add('dform-row');
                if(selector == null){
                    target = document.querySelector('.area-target[data-tag="'+tag+'"]');
                    row.classList.add('mb-10','mt-10','col-6','card','card-full');
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
                    inLabel.innerHTML = fitem.label.length == 0 ? '<span> </span>' : fitem.label;
                    
                    itemRow.appendChild(inLabel);

                

                    let input = null;

                    const createInput = (attr,iDiv = null) => {
                        if(iDiv == null){
                            iDiv = document.createElement('div');
                            iDiv.classList.add('col-lg-'+(attr.col ?? '12'),'d-flex');
                        }
                        

                        const input          = document.createElement('input');
                        input.type           = attr.type;
                        input.name           = attr.name;
                        input.dataset.rowId  = rowId;
                        input.dataset.tag    = tag;
                        input.placeholder    = attr?.placeholder ?? '';
                        
                        
                        if(attr?.readOnly)                input.readOnly = attr.readOnly;
                        if(attr.class !== undefined)      input.classList.add(...attr?.class);
                        if(attr?.required !== undefined)  input.required = attr.required;
                        if(attr?.hidden)                  input.hidden = attr.hidden;

                        input.classList.add('form-item');
                        
                        input.oninput = (e) => attr.oninput(e);
                        iDiv.appendChild(input);
                       
                        if(attr.type === 'file'){
                            input.setAttribute('accept',attr?.accept);
                            input.dataset.fileId = 0;
                                
                            const fileData = JSON.parse(data?.entities?.[attr.name] ?? '{}');
                            if(fileData?.description){
                               
                                input.dataset.fileId = fileData.id;
                                input.dataset.file = 'Dosya Mevcut';


                                const showB     = document.createElement('span');
                                showB.classList.add('input-group-text','rmv-btn-form');
                                showB.innerHTML = '<i class="ph ph-file-arrow-up fs-4 text-body-emphasis"></i>';
                                showB.onclick   = (e) => {
                                    window.open('/order-file/'+fileData?.description);
                                };
                                iDiv.prepend(showB);
                                /*const small = document.createElement('a');
                                small.href  = '#';
                                small.innerHTML = '<a href="/order-file/'+fileData?.description+'" target="_BLANK"><small>Mevcut Dosya İçin Tıklayınız..</small></a>';
                                small.classList.add('text-danger','mt-2','d-block');
                                
                                iDiv.parentNode.appendChild(small);*/

                                
                                // Simulate file inserted for native labels
                                const myFileContent = ['My File Content'];
                                const myFile = new File(myFileContent, fileData?.description);

                                // Create a data transfer object. Similar to what you get from a `drop` event as `event.dataTransfer`
                                const dataTransfer = new DataTransfer();

                                // Add your file to the file list of the object
                                dataTransfer.items.add(myFile);

                                // Save the file list to a new variable
                                const fileList = dataTransfer.files;
                                input.files = fileList;
                            }
                        }else {
                            input.value          = data?.entities?.[attr.name] ?? '';
                        } 

                        if(attr?.isMasked){
                            input.dataset.mask    = attr.mask;
                            switch (attr.mask) {
                                case 'money':
                                    input.style.textAlign = 'right';
                                    //for non float values
                                    //if(!input.value.includes('.')) input.value += '.00';
                                    new VMasker(input).maskMoney({
                                        // Decimal precision -> "90"
                                        precision: 2,
                                        // Decimal separator -> ",90"
                                        separator: ',',
                                        // Number delimiter -> "12.345.678"
                                        delimiter: '.',
                                    });
                                    break;
                                case 'phone':
                                    new  VMasker(input).maskPattern("(999) 999-99-99");
                                    break;
                                case 'custom':
                                    new  VMasker(input).maskPattern(attr.format);
                                    break;
                                default:
                                    break;
                            }
                        }

                        //do this after is added to div because lightpick component is not seeing before
                        if(attr?.isDate == true){
                            input.readOnly    = true;
                            input.placeholder = 'Tarih Seçiniz';
                            input.value       = data?.entities?.[attr.name] !== undefined ? data?.entities[attr.name].split('-').reverse().join('/') : '';
                            const datepicker = new Datepicker(input, {
                                format     : 'dd/mm/yyyy',
                                language   : 'tr',
                            }); 
                            input.readOnly = true;
                            //just for this date component
                            input.addEventListener('changeDate',e => e.target.dispatchEvent(new Event('input')));

                        }

                        if(attr?.isMonth == true){
                            input.readOnly    = true;
                            input.placeholder = 'Tarih Seçiniz';
                            input.value       = data?.entities?.[attr.name] !== undefined ? data?.entities[attr.name].split('-').reverse().join('/') : '';
                            const datepicker = new Datepicker(input, {
                                language   : 'tr',
                                pickLevel  : 1, // for month select
                                format     : 'mm/yyyy',
                            }); 
                            //just for this date component
                            input.addEventListener('changeDate',e => e.target.dispatchEvent(new Event('input')));

                        }
                        
                        return iDiv;
                    };

                    const createSelect = (attr,iDiv = null) => {
                        if(iDiv == null){
                            iDiv = document.createElement('div');
                            iDiv.classList.add('col-lg-'+(attr.col ?? '12'),'d-flex');
                        }


                        const input = document.createElement('select');
                        input.classList.add('form-item','form-select');
                        //input.dataset.fileId = fileId;
                        input.dataset.rowId  = rowId;
                        input.dataset.tag    = tag;
                        input.name           = attr.name;

                        if(attr?.class !== undefined)    input.classList.add(...attr?.class);
                        
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

                            if(data?.entities?.[attr.name] !== undefined && data?.entities?.[attr.name] == op.value) op.selected = true;

                            input.appendChild(op);
                        }

                        input.oninput = (e) => attr.oninput(e);

                        iDiv.appendChild(input);
                        //NiceSelect.bind(input, attr?.isSearchable ? {searchable : true} : {});

                        return iDiv;
                    };
                    
                    let inputDiv = document.createElement('div');
                    inputDiv.classList.add('col-lg-12');
                    switch (fitem.type) {   
                        case 'sub':
                        case 'multiple':
                            this.keyLock = [];
                            //this method will add sub elements
                            const addElements = (nameTag = null) => {
                                
                                //addional item row element
                                const row = document.createElement('div');
                                row.classList.add('row','mt-2');
                                
                                inputDiv.appendChild(row);

                                //row components
                                fitem.subs.forEach(element => {
                                    const el = {...element};
                                    //create unique nametag
                                    if(nameTag == null) nameTag = (new Date).getTime()+'-'+inputDiv.querySelectorAll("[name^="+el.name+"]").length;
                                    
                                    const rowElm = document.createElement('div');
                                    rowElm.classList.add('col-md-'+el.col);
                                    //item label
                                    if(el?.label !== undefined && el?.label != ''){
                                        let lbl   = document.createElement('label');
                                        lbl.classList.add('form-label','mt-5');
                                        
                                        lbl.innerHTML = el.label;
                                        
                                        rowElm.appendChild(lbl);
                                    }

                                    const inpGroup = document.createElement('div');
                                    inpGroup.classList.add('input-group');
                                    rowElm.appendChild(inpGroup);

                                    el.name = fitem.type == 'multiple' ? el.name+'**'+(fitem.group_key ?? 'unalign-group-key') + '**'+ nameTag : el.name;
                                    
                                    if(el.type == 'select'){
                                        createSelect(el,rowElm);
                                    }else{
                                        createInput(el,inpGroup);
                                    }

                                    if(el.isDate){
                                        const calInp     = document.createElement('span');
                                        calInp.classList.add('input-group-text');
                                        calInp.innerHTML = '<i class="ph ph-calendar fs-4 text-body-emphasis"></i>';
                                        calInp.onclick = () => el.element.dispatchEvent(new Event('focus'));
                                        inpGroup.appendChild(calInp);
                                    }

                                    if(el.isMasked){
                                        const calInp     = document.createElement('span');
                                        switch (el.mask) {
                                            case 'phone':
                                                
                                                calInp.classList.add('input-group-text');
                                                calInp.innerHTML = '<i class="ph ph-phone fs-4 text-body-emphasis"></i>';
                                                calInp.onclick = () => el.element.dispatchEvent(new Event('focus'));
                                                inpGroup.appendChild(calInp);
                                                break;
                                            case 'money':
                                                calInp.classList.add('input-group-text');
                                                calInp.innerHTML = el?.moneyIcon ?? '';
                                                calInp.onclick = () => el.element.dispatchEvent(new Event('focus'));
                                                if(el?.moneyIcon) inpGroup.appendChild(calInp);
                                                break
                                            default:
                                                break;
                                        }
                                    }

                                    if(fitem.type == 'multiple' && fitem.subs[fitem.subs.length-1] === element){
                                        const rmvInp     = document.createElement('span');
                                        rmvInp.classList.add('input-group-text','rmv-btn-form');
                                        rmvInp.innerHTML = '<i class="ph ph-trash fs-4 text-body-emphasis"></i>';
                                        rmvInp.onclick   = (e) => {
                                            document.querySelectorAll('[name*="'+nameTag.split('-')[0]+'"]').forEach(rmElm => {
                                                this.formData.removedData.push({
                                                    id    : rowId,
                                                    type  : 'entity',
                                                    key   : rmElm.name
                                                });
                                                delete this.formData?.dynamicF?.[tag+'**'+rowId]?.entities?.[rmElm.name];
                                            });
                                            row.remove();

                                            
                                            /*this.formData.removedData.push({
                                                id    : rowId,
                                                type  : 'entity',
                                                key   : el.name
                                            });

                                            delete this.formData?.dynamicF?.[tag+'**'+rowId]?.entities?.[el.name];
                                            rowElm.remove();*/
                                        };
                                        inpGroup.appendChild(rmvInp);
                                    }/*else{
                                        inpGroup.classList.remove('input-group');
                                        console.log(fitem.type);
                                        //if(fitem.type == 'sub') inpGroup.classList.remove('input-group');
                                    }*/

                                    
                                   
                                    row.appendChild(rowElm);
                                });

                                row.dataset.tag = (fitem.group_key ?? 'unalign-group-key')+'-'+nameTag.split('-')[0]+'-row';
                                this.keyLock.push(row.dataset.tag);
                            };

                            inLabel.classList.add('d-flex','justify-content-between','align-items-center');
                            
                            const iconDiv = document.createElement('div');
                            iconDiv.classList.add('align-items-center','bg-highlight','d-flex','flex-shrink-0','h-10','justify-content-center','me-5','rounded-circle','w-10');
                            
                            if(fitem.type === 'multiple'){
                                inputDiv.classList.add('border','rounded','p-5');
                                const icon = document.createElement('i');
                                icon.classList.add('ph','selectable-icon','ph-list-plus','fs-1','text-body-emphasis');
                                icon.id = tag+'-'+(fitem.group_key ?? 'unalign-group-key')+'-subadd-'+rowId;
                                iconDiv.appendChild(icon);

                                icon.onclick   = () => addElements();
                                
                                inLabel.appendChild(iconDiv);
                                addElements();
                                //here create elements if data is exist on given data with object nametag
                                if(data?.entities){
                                    Object.keys(data?.entities).forEach(key => {
                                        if(key.includes('**'+fitem.group_key) && !this.keyLock.includes(fitem.group_key+'-'+key.split('**')[2].split('-')[0]+'-row')){
                                            addElements(key.split('**')[2]);
                                        } 
                                    });
                                }
                                
                            } 
                        
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
                            
                            if(fitem.type == 'sub') addElements();
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
                            
                            if(data?.entities?.[fitem.name] !== undefined){
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
                            type  : 'connection'
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

                form.oncreated(rowId,row);
            },
        }
    }
</script>
<style>
    label{
        color: black !important;
    }
    input{
        color: black !important;
        border-color: #dbe0eb !important;
    }
</style>
<template>
    <div class="area-target d-flex justify-content-center" v-for="(item, index) in ftypes" :data-tag="item">
       
    </div>
    <AppFab v-if="authStore.data.type=='admin'" btntype="saveBtn" :callback="formCallback"/>
    
</template>