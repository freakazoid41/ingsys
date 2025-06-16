
<script>
    import { useNavigationStore } from '@/stores/navigation'
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import VMasker  from 'vanilla-masker';
    import { Datepicker } from 'vanillajs-datepicker';
    import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';


    export default {
        setup() {
            Object.assign(Datepicker.locales, tr);
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                Datepicker
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTestTable();
            
            this.navigationStore.setBread([
                {
                    title : this.wTrans('menu.home'),
                    url   : '/panel',
                },
                {
                    title : this.wTrans('menu.flats'),
                    url   : '/panel/flats',
                }
            ] ,this.wTrans('form.flats.list'));

            this.navigationStore.setButtons([
              {
                icon : 'ph ph-download',
                onclick   : () => window.open('/export/documents/flats'),
              },{
                icon : 'ph ph-plus-circle',
                onclick   : () => {window.location.href = '/panel/flats/form'},
              }
            ]);


            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },  
        data() {
            return {
                plib : new Plib(),
                navigationStore : useNavigationStore(),
            }
        },
        methods: {
            buildTestTable(){
                
                //set headers
                const headers = [
                   /*{
                        title : 'ID',
                        key   : 'id',
                        order : true,
                        type  : 'string', // if column is string then make type string
                        
                    },*/{
                        title : 'Daire İsmi',
                        key   : 'title',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Kat Maliki',
                        key   : 'per_name',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Güncel Bakiye',
                        key   : 'balance',
                        order : true,
                        colAlign : 'right',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => this.plib.formatMoney(columnData,2,',','.') + ' ' + rowData.cur
                    },{
                        title : 'İşlem',
                        key   : 'id',
                        order : true,
                        colAlign : 'center',
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const span = document.createElement('span');
                            let btn = document.createElement('button');
                            btn.classList.add('btn','btn-secondary','me-1');
                            btn.innerHTML = '<i class="ph ph-download-simple fs-4 text-body-emphasis me-2"></i> Bakiye Ekle ';
                            btn.onclick   = () => this.transmodal('addbalance',columnData,rowData.title);
                            span.appendChild(btn);

                            btn = document.createElement('button');
                            btn.classList.add('btn','btn-secondary','me-1');
                            btn.innerHTML = '<i class="ph ph-upload-simple fs-4 text-body-emphasis me-2"></i> Ödeme Ekle' ;
                            btn.onclick   = () => this.transmodal('income',columnData,rowData.title);
                            span.appendChild(btn);
                            return span;
                        }
                    },{
                        title : '',
                        key   : 'id',
                        order : false,
                        type  : 'string', // if column is string then make type string
                        columnFormatter : (elm,rowData,columnData) => {
                            const div = document.createElement('div');
                            div.classList.add('row','justify-content-center');

                            const edit       = document.createElement('a');
                            edit.href        = '/panel/flats/form/'+columnData;
                            edit.style.width = 'auto';
                            edit.innerHTML   = '<i class="fc-icon fc-icon- fs-4 ph ph-note-pencil" role="img"></i>';
                            div.appendChild(edit);

                            const del       = document.createElement('a');
                            del.href        = 'javascript:;';
                            del.style.width = 'auto';
                            del.innerHTML   = '<i class="fc-icon fc-icon- fs-4 ph ph-x-circle"  role="img"></i>';
                            del.onclick     = async () => {
                                this.navigationStore.toggle(true);
                                await this.plib.request({
                                    url      : '/api/v1/document/'+columnData,
                                    method   : 'DELETE',
                                },null);

                                this.table.deleteRow(columnData);
                                setTimeout(() => {
                                    this.navigationStore.toggle(false);
                                }, 300);
                                
                            };
                            div.appendChild(del);

                            return div;
                        }
                    }
                ];
                
                //initiate table
                this.table = new PickleTable({
                    container : '#div_table', //table target div
                    headers   : headers,
                    pageLimit : 10, // -1 for closing pagination
                    height    : '70vh',
                    type      : 'ajax',
                    //columnSearch : true, // true - false for opening and closig
                    paginationType : 'number',// scroll - number (number for default)
                    ajax:{
                        url:'/api/v1/table/documents',
                        data:{
                            //order:{},
                        }
                    },
                    initialFilter : [
                        {
                            key   : 'form-type',
                            type  : '=',
                            value : 'op-doc-flat-form'
                        },{
                            key   : 'type',
                            type  : '=',
                            value : 'op-doc-flat'
                        }
                    ],
                    nextPageIcon : '<i class="ph ph-arrow-right  text-body-emphasis"></i>',
                    prevPageIcon : '<i class="ph ph-arrow-left text-body-emphasis"></i>',
                    rowFormatter:(elm,data)=>{
                        //console.log(elm,data);
                        //modify row element
                        //elm.style.backgroundColor = 'yellow';
                        //modify data
                        JSON.parse(data.main_attr).forEach(element => {
                            data[element['Key']] = element['Value'];
                            if(data['per_name'] == undefined) data['per_name'] = []
                            if(element['Key'].includes('per_name')) data['per_name'].push(element['Value']);
                        });
                        data['per_name'] = data['per_name'].join(' , ');
                        //data.status = JSON.parse(data.status).OpTitle;
                        return data;
                    },
                });
            },
            transmodal(type,id,text = '-'){
                this.navigationStore.toggle(true);
                Swal.fire({
                    width: 650,
                    confirmButtonText : 'Kaydet',
                    showCloseButton : true,
                    cancelButtonText : 'İptal',
                    showCancelButton : true,
                    customClass: {
                        confirmButton: "btn btn-secondary me-3",
                        cancelButton: "btn btn-secondary me-3"
                    },
                    buttonsStyling: false,
                    html : `<div class="row w-100">
                            ${type == 'income' ? `
                                    <div class="col-12 mt-5 d-flex justify-content-center">
                                        <div class="form-check form-switch mb-3"> 
                                            <input class="form-check-input trans-form" name="from_safe" type="checkbox" role="switch" id="flexSwitchCheckDefault"> 
                                            <label class="form-check-label" for="flexSwitchCheckDefault">Ödeme Elden Olacak (Cari Kasasına Giriş Yapar)</label>
                                        </div>
                                    </div>
                                    <div class="col-5 mt-5">
                                        <select class="form-select trans-form" name="type" required > 
                                            <option selected="" value="">İşlem Tipi</option> 
                                            <option value="doc_acc_rent">Kira</option> 
                                            <option value="doc_acc_fuel">Yakıt</option> 
                                            <option value="doc_acc_aidat">Aidat</option> 
                                            <option value="doc_acc_sometinguntransable">Demirbaş</option> 
                                            <option value="doc_acc_other">Diğer</option> 
                                            <option value="doc_acc_dept">Borç</option> 
                                        </select>
                                    </div>
                                    <div class="col-7 mt-5">
                                        <select class="form-select trans-form" name="account_id" required > 
                                            <option selected="" value="">Ödeme Kasası</option> 
                                        </select>
                                    </div>
                                    
                                ` : ''}
                                <div class="col-5 mt-5">
                                    <input type="text" readonly name="flat_text" class="form-control">
                                    <input hidden type="text" name="flat_id" required class="form-control trans-form">
                                </div>
                                <div class="col-4 mt-5">
                                    <input type="text" style="text-align:right;" name="amount" required class="form-control trans-form"  placeholder="Miktar">
                                </div>
                                <div class="col-3 mt-5">
                                    <select class="form-select trans-form" name="currency" id="selCur" required aria-label="Default select example"> 
                                        <option selected="" value="">Kur</option> 
                                    </select>
                                </div>
                                <div class="col-6 mt-5">
                                    <input type="text" readonly name="period" required class="form-control trans-form"  placeholder="İşlem Dönemi">
                                </div>
                                <div class="col-6 mt-5">
                                    <input class="form-control trans-form" name="trans_file" type="file" id="formFile">
                                </div>
                                <div class="col-12 mt-5">
                                    <textarea required class="form-control trans-form" name="note" rows="3" placeholder="Açıklama.."></textarea>
                                </div>
                            </div>`,
                    willOpen : async () => {
                        document.querySelector('input[name="flat_id"]').value = id;
                        document.querySelector('input[name="flat_text"]').value = text;


                        const rsp = await this.plib.request({
                            url      : '/api/v1/trans/prepare-payment',
                            method   : 'GET',
                        },null);

                        //insert values
                        const curs = document.querySelector('select[name="currency"]');
                        rsp.data.currencies.forEach(el => {
                            const op = document.createElement('option');
                            op.text  = el.icon+' '+el.title;
                            op.value = el.code;
                            curs.appendChild(op);
                        });

                        const accounts = document.querySelector('select[name="account_id"]');
                        if(accounts != null){
                            rsp.data.accounts.forEach(el => {
                            const attr = {};
                            JSON.parse(el.main_attr).forEach(item => {
                                attr[item.Key] = item.Value;
                                //return r;
                            });
                            
                            const op = document.createElement('option');
                                op.text  = attr.title;
                                op.value = el.id;
                                op.dataset.cur = attr.currency;
                                op.onclick = () => console.log(el.currency)
                                accounts.appendChild(op);
                            });

                            accounts.addEventListener('input',e=>{
                                const cur = curs.querySelector('option[value="'+(e.target.options[e.target.selectedIndex].dataset.cur)+'"]');
                                if(cur !== null) cur.selected = true;
                                curs.disabled = true;
                            });
                        }
                        
                        

                        new VMasker(document.querySelector('input[name="amount"]')).maskMoney({
                            // Decimal precision -> "90"
                            precision: 2,
                            // Decimal separator -> ",90"
                            separator: ',',
                            // Number delimiter -> "12.345.678"
                            delimiter: '.',
                        });

                        const datepicker = new Datepicker(document.querySelector('input[name="period"]'), {
                            language   : 'tr',
                            pickLevel  : 1, // for month select
                            format     : 'mm/yyyy',
                            autohide   : true
                        }); 
                        //just for this date component
                        document.querySelector('input[name="period"]').addEventListener('changeDate',e => e.target.dispatchEvent(new Event('input')));

                        this.navigationStore.toggle(false);
                    },
                    allowOutsideClick : () => !Swal.isLoading(),
                    preConfirm : async () => {
                        Swal.showLoading();
                        const form = this.plib.checkForm('.trans-form');
                        if(form.valid){
                            const   envelope  = new FormData();
                            //register files
                            envelope.append('op',type);
                            for(let key in form.obj){
                                //console.log(form.obj[key]);
                                if(key == 'amount'){
                                    form.obj[key] = form.obj[key].replace(/\./g,'');
                                    form.obj[key] = form.obj[key].replace(',','.');
                                }
                                if(key == 'period') form.obj[key] = form.obj[key].split('/').reverse().join('-');
                                //console.log(form.obj[key]);
                                envelope.append(key,form.obj[key]);
                            }

                            form.s_file.forEach((element,i)=> {
                                envelope.append('trans_file[]',element);
                            });
                            
                            const rsp = await this.plib.request({
                                url      : '/api/v1/trans/set-payment',
                                method   : 'POST',
                            },null,envelope);

                            if(rsp.success == true){
                                this.plib.toast(this.Swal,'success','Transfer Hareketi Tamamlandı',() => {window.location.reload();});
                            }else{
                                Swal.showValidationMessage('Hata Oluştu..');
                                return false;
                            }
                        }else{
                            Swal.showValidationMessage('Bilgileri Giriniz..');
                            return false;
                        }
                    }
                });
            }
        }
    }

</script>
<template>
    <div class="card">
        <div class="card-body">
            <div id="div_table"></div>
        </div>
    </div>
</template>
