export default class Plib {
    constructor() {
        // private property
        this._keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
    }

    /**
     * this method will clear text from html tags
     * @param {string} text 
     * @returns 
     */
    clearString(text){
        
        const div = document.createElement("div");
        div.innerHTML = text;
        return div.innerText;
        //return text.replace(/<\/?[^>]+(>|$)/gi, '');
    }

    /**
     * this method will create html element
     * @param {string} type 
     * @param {*} classList 
     * @param {*} obj 
     * @returns 
     */
    createElm (type,classList,obj) {
        const elm = document.createElement(type);
        elm.classList.add(...classList);
        for(let key in obj){
            elm[key] = obj[key]
        }
        return elm;
    };

    /**
     * system request method
     * @param {json object} rqs
     */
    async request(rqs, file = null,formData = null) {
        //set fetch options
        const op = {
            headers : {
                'credentials': 'include',
                'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content,
                ...(localStorage.getItem('token') !== null ? {
                    'Authorization' : 'Bearer '+ localStorage.getItem('token')
                }: {})
            },
            method: rqs.method,
        };

        switch (op.method.toLowerCase()) {
            case "delete":
                //because patch and delete methods will send url encoded data !!
                op.headers = {
                    ...op.headers,
                    ...{"Content-Type": "application/x-www-form-urlencoded"},
                };
                console.log(rqs.data);
                op.body = new URLSearchParams(rqs.data);
                break;
            case "put":
            case "post":
                if(formData == null) {
                    //create form data
                    const fD = new FormData();
                    for (let key in rqs.data) {
                        fD.append(key, rqs.data[key]);
                    }
                    if (file !== null && file !== undefined) {
                        fD.append("file", file, file.name);
                    }
                    op.body = fD;
                }else{
                    op.body = formData;
                }
                break;
            default:
                break;
        }

        //send fetch
        /*const rsp = await fetch(rqs.url, op).then((response) => {
            //convert to json



            return response.json();
        });*/

        const rsp = await fetch(rqs.url, op).then(response => response.text()) // Parse the response as text
        .then(text => {
            try {
                const data = JSON.parse(text); // Try to parse the response as JSON

                return data;
                // The response was a JSON object
                // Do your JSON handling here
            } catch(err) {
                Swal.fire({
                    title : 'Hata !',
                    html  : `<div>
                                ${text}
                            </div>`,
                });

                return false;
            }
        });
        //in this point check if api is send timeout command
        /*if (rsp.command !== undefined) {
                switch (parseInt(rsp.command)) {
                    case 0:
                        //this mean token is not valid
                        this._logout();
                        break;
                }
            }*/
        return rsp;
    }

    getMonths () {
        return ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
    } 

    /**
     * this method is return permissins for page
     */
    getPerms(){
        const perms = {}
        document.querySelectorAll('.perm-check').forEach(el=>{
            perms[el.dataset.key] = true;
        });

        return perms;
    }

    /**
     * Clear all items
     * @param {string} selector
     */
    clearElements(selector) {
        let elms = document.querySelectorAll(selector);
        for (let i = 0; i < elms.length; i++) {
            switch (elms[i].tagName) {
                case "SELECT":
                    elms[i].selectedIndex = 0;
                    if(elms[i].multiple == true){
                        
                        Array.from(elms[i].options).forEach(op => {
                            op.selected = false;
                        });
                    }
                    break;
                case "LABEL":
                    elms[i].innerHTML = "";
                    break;
                case "INPUT":
                    if (elms[i].getAttribute("type") === "radio" || elms[i].getAttribute("type") === "checkbox") {
                        elms[i].checked = false;
                    } else {
                        elms[i].value = "";
                        if (elms[i].name.includes('_key')) elms[i].value = "-";
                    }
                    break;
                default:
                    elms[i].value = "";
                    break;
                case 'TEXTAREA':
                    elms[i].value     = "";
                    elms[i].innerHTML = "";
                    break;    
                case 'DIV':
                    if (elms[i].classList.contains('text-editor')) {
                        const editor = elms[i].querySelector('.ql-editor');
                        if (editor !== null) {
                            editor.innerHTML = '';
                            delete elms[i].value;
                        }
                    }
                    break;
            }
            elms[i].classList.remove("is-invalid");
        }
        //set invisible language inputs
        elms = document.querySelectorAll(".div_lang_row");
        for (let i = 0; i < elms.length; i++) {
            elms[i].style.display = "none";
        }
    }

    /**
     * Get form element with validation
     * @param {string} selector
     */
    checkForm(selector) {
        const rsp = {
            obj: {},
            s_file: [],
            valid: true,
            failed : {}
        };
        //get elements
        const elms = document.querySelectorAll(selector);
        for (let i = 0; i < elms.length; i++) {
            
            //for editor
            if (elms[i].classList.contains('text-editor')) {
                elms[i].value = tinymce.get(elms[i].id).getContent();
                elms[i].name = elms[i].dataset.name.trim();
                elms[i].required = elms[i].dataset.required;
            }
            
            if (elms[i].value === undefined) elms[i].value = '';


            if(elms[i].tagName == 'SELECT' && elms[i].multiple == true){
                
                rsp.obj[elms[i].name] = [];
                Object.values(elms[i].options).forEach(op => {
                    if(op.value !== '' && op.hasAttribute('selected')){
                        rsp.obj[elms[i].name].push(op.value);
                    } 
                });
                rsp.obj[elms[i].name] = rsp.obj[elms[i].name].join(',');
                elms[i].value = ''; // for jumping to validation
            }


            if (elms[i].value && elms[i].value.trim() !== '') {
                
                if (elms[i].type == "file") {
                    rsp.s_file.push(elms[i].files[0]);
                } else {

                    if (elms[i].name !== undefined && elms[i].name !== null && elms[i].name.trim() !== "") {
                        //for language
                        if (elms[i].dataset.lang !== undefined) {
                            if (rsp.obj[elms[i].name] === undefined) {
                                rsp.obj[elms[i].name] = {};
                            } else {
                                rsp.obj[elms[i].name] = JSON.parse(rsp.obj[elms[i].name]);
                            }

                            rsp.obj[elms[i].name][elms[i].dataset.lang] = elms[i].value.trim();

                            rsp.obj[elms[i].name] = JSON.stringify(rsp.obj[elms[i].name]);
                        } else {
                            rsp.obj[elms[i].name] = elms[i].value;
                        }
                        //for checkbox
                        if (elms[i].type === "checkbox") {
                            if (elms[i].dataset.active !== undefined) {
                                rsp.obj[elms[i].name] = elms[i].checked ? elms[i].dataset.active : elms[i].dataset.passive;
                            } else {
                                rsp.obj[elms[i].name] = Number(elms[i].checked);
                            }
                        }
                    }
                }
                elms[i].classList.remove("is-invalid");
                //for nice select
                
                if(elms[i].classList.contains('searchable-select')){
                    elms[i].parentNode.querySelector('.nice-select').removeAttribute('style');
                }
            } else {

                if (elms[i].required && !elms[i].disabled && elms[i].multiple != true) {

                    //for nice select
                    if(elms[i].classList.contains('searchable-select')){
                       elms[i].parentNode.querySelector('.nice-select').setAttribute('style','border-color:tomato !important;');
                    }


                    elms[i].classList.add("is-invalid");
                    rsp.valid = false;
                    rsp.failed[elms[i].name] = elms[i];
                    
                    elms[i].focus();
                } else {
                    elms[i].classList.remove("is-invalid");
                }
            }
            
            const errCont = document.getElementById("err-"+elms[i].name);
           
            if(errCont !== null){
                if(!rsp.valid){
                    errCont.style.display = "unset";
                }else{
                    errCont.style.cssText = 'display:none !important';
                }
            }

            
        }
        return rsp;
    }

    /**
     * this function will check password validity
     */
    validatePassword(input) {
        let p = input.value,
            errors = [];
        if (p.length < 8) {
            errors.push("Parola en az 8 karakterden oluşmalıdır..");
        }
        if (p.search(/[a-z]/i) < 0) {
            errors.push("Parolada en az 1 harf olmalıdır.."); 
        }
        if (p.search(/[0-9]/) < 0) {
            errors.push("Parolada en az 1 rakam bulunmalıdır..");
        }

        return {
            errors : errors,
            valid  : errors.length === 0
        };
    }

    fileInfo(evt) {
        const fileTypes = ['jpg', 'jpeg', 'png', 'pdf'];  //acceptable file types

        const f = evt.target === undefined ? evt.files[0] : evt.target.files[0];
        
        const extension = f.name.split('.').pop().toLowerCase();
        return {
            typeValid : fileTypes.includes(extension),
            sizeValid : f.size <= 42000000, // 40 mb~ //4096000, // 4 mb
            size      : f.size
        }
    }

    /**
     * this method will format money decimal
     * @param {float} amount
     * @param {int} decimalCount
     * @param {string} decimal
     * @param {string} thousands
     */
    formatMoney(amount, decimalCount = 2, decimal = ".", thousands = ",") {
        try {
            decimalCount = Math.abs(decimalCount);
            decimalCount = isNaN(decimalCount) ? 2 : decimalCount;

            const negativeSign = amount < 0 ? "-" : "";

            const i = parseInt((amount = Math.abs(Number(amount) || 0).toFixed(decimalCount))).toString();
            const j = i.length > 3 ? i.length % 3 : 0;

            return (
                negativeSign +
                (j ? i.substr(0, j) + thousands : "") +
                i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + thousands) +
                (decimalCount ?
                    decimal +
                    Math.abs(amount - i)
                    .toFixed(decimalCount)
                    .slice(2) :
                    "")
            );
        } catch (e) {
            console.log(e);
        }
    }

    /**
     * Toast message (sweet alert 2)
     * @param {string} type
     * @param {string} msg
     */
    toast(reference,type, msg,onclose) {

        reference.mixin({
            toast: true,
            position: "bottom",
            showConfirmButton: false,
            timer: 1500,
            timerProgressBar: true,
        }).fire({
            icon: type,
            title: msg,
            willClose : onclose
        });
        /*Swal.mixin({
            toast: true,
            position: "top-end",
            showConfirmButton: false,
            timer: 1000,
            timerProgressBar: true,
        }).fire({
            icon: type,
            title: msg,
            allowOutsideClick : true,
            heightAuto: true,
        });*/
    }

    /**
     * process loading message from sweet alert 2
     */
    processLoading(){
        Swal.fire({
            icon : 'warning',
            title: 'İşlem Gerçekleştiriliyor !',
            allowOutsideClick: false,
            showConfirmButton : false,
            willOpen: () => {
                Swal.showLoading();
            },
        });
    }

    seoTitle(title) {
        title = title.toLowerCase();
        title = title.replace(/ /g, "-");
        title = title.replace(/\./g, "-");
        title = title.replace(/ü/g, "u");
        title = title.replace(/ı/g, "i");
        title = title.replace(/ç/g, "c");
        title = title.replace(/ş/g, "s");
        title = title.replace(/ğ/g, "g");
        title = title.replace(/ö/g, "o");
        title = title.replace(/\)/g, "");
        title = title.replace(/\(/g, "");
        return title + (new Date()).getTime();
    }

    compressImage(file, success, ratio) {
        const f = file;
        const fileName = f.name.split('.')[0];
        const img = new Image();
        img.src = URL.createObjectURL(f);
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.width;
            canvas.height = img.height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            canvas.toBlob((blob) => {
                const f2 = new File([blob], fileName + ".jpeg");
                success(f2);
            }, 'image/jpeg', ratio);
        }
    }

    /**
     * Alert message (sweet alert 2)
     * @param {string} type
     * @param {string} msg
     */
    prompt(type, msg) {
        Swal.fire({
            type: type,
            text: msg,
            showConfirmButton: false,
        });
    }

    // sleep like method
    sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    //get languge value
    getLang = (value) =>  document.querySelector('.lang-value[data-key="'+value+'"]').value.trim();
    
    /**
     * this method will get file from url and convert to file object
     */
    async createFile(url,name){
        const type = url.split(/[#?]/)[0].split('.').pop().trim();
        const response = await fetch(url);
        const data = await response.blob();
        /* let metadata = {
          type: 'image/jpeg'
        };*/
        return new File([data], "copy-"+name /*, metadata*/);
        // ... do something with the file or return it
    }

    /**
     * this method will send transaction request
     * @param {string} type 
     * @param {string} model 
     * @param {object} data 
     * @returns {object} 'rsp' from api
     */
    async transaction(type = 'get',model,data = {}){
        const postData = {...data};
        const trans = {
            'login'  : {
                req : 'post',
                url : 'auth'
            },
            'get'    : {
                req : 'get',
                url : 'request'
            },
            'ask'    : {
                req : 'post',
                url : 'query'
            },
            'add'    : {
                req : 'post',
                url : 'request'
            },
            'update' : {
                req : 'put',
                url : 'request'
            },
            'delete' : {
                req : 'delete',
                url : 'request'
            },
            'upload' : {
                req : 'post',
                url : 'upload'
            }
        };

        const url = "/api/"+trans[type].url+ 
                (type === 'upload' ? 
                    '' : 
                    ('/'+model+(data.id && type != 'ask' ? '/'+data.id : ''))
        );
        if(postData.id && type != 'ask') delete postData.id;


        return await this.request({
            method: trans[type].req,
            url: url,
            data: postData,
        });
    }

    /**
     * this function will open new tab with poste parmeters
     * exp. openTab('POST', 'http://biber.picklecan.loc/spec/', {"Tedarik":"1523546", "Region":"TR"},'_blank');
     * @param {strng} verb
     * @param {string} url
     * @param {json} data
     * @param {string} target
     */
    async openTab(verb, url, data, target) {
        let form = document.createElement("form");
        form.action = url;
        form.method = verb;
        form.target = target || "_self";
        if (data) {
            for (var key in data) {
                var input = document.createElement("textarea");
                input.name = key;
                input.value = typeof data[key] === "object" ? JSON.stringify(data[key]) : data[key];
                form.appendChild(input);
            }
        }
        form.style.display = "none";
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    /**
     * this method will set loading to div
     * @param {string} selector
     * @param {boolean} event
     */
    setLoader(selector, event = true) {
        let elms = document.querySelectorAll(selector);
        if (event) {
            document.body.style.pointerEvents = "none";
            for (let i = 0; i < elms.length; i++) {
                elms[i].classList.add("b-loader");
            }
        } else {
            document.body.style.pointerEvents = "";
            for (let i = 0; i < elms.length; i++) {
                elms[i].classList.remove("b-loader");
            }
        }
    }

    /**
     * Get parameter from url
     */
    getUrlParam(param) {
        return new URL(window.location.href).searchParams.get(param);
    }

    getNumberOfDays(start, end) {
        // One day in milliseconds
        const oneDay = 1000 * 60 * 60 * 24;

        // Calculating the time difference between two dates
        const diffInTime = end.getTime() - start.getTime();

        // Calculating the no. of days between two dates
        const diffInDays = Math.round(diffInTime / oneDay);

        return diffInDays;
    }

    getDaysArray(s,e,type = 'day'){
        const a = {};
        for(const d=new Date(s);d<=new Date(e);d.setDate(d.getDate()+1)){
            const key = new Date(d).toISOString().slice(0,type === 'day' ? 10 : 7);
            a[key] = true;
        }
        return Object.keys(a);
    }


    async checkMail(email){
        return await this.request({
            method: 'post',
            url: '/api/auth/checkmail',
            data: {email : email},
        });
    }

    // private method for UTF-8 decoding
    _utf8_decode(utftext){
        let string = "";
        let i = 0;
        let c, c1 , c2 = 0;
  
        while ( i < utftext.length ) {
  
            c = utftext.charCodeAt(i);
    
            if (c < 128) {
                string += String.fromCharCode(c);
                i++;
            }
            else if((c > 191) && (c < 224)) {
                c2 = utftext.charCodeAt(i+1);
                string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
                i += 2;
            }
            else {
                c2 = utftext.charCodeAt(i+1);
                c3 = utftext.charCodeAt(i+2);
                string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
                i += 3;
            }
  
        }
  
        return string;
    }

    _utf8_encode(string) {
        string = string.replace(/\r\n/g,"\n");
        let utftext = "";
    
        for (let n = 0; n < string.length; n++) {
    
            let c = string.charCodeAt(n);
    
            if (c < 128) {
                utftext += String.fromCharCode(c);
            }
            else if((c > 127) && (c < 2048)) {
                utftext += String.fromCharCode((c >> 6) | 192);
                utftext += String.fromCharCode((c & 63) | 128);
            }
            else {
                utftext += String.fromCharCode((c >> 12) | 224);
                utftext += String.fromCharCode(((c >> 6) & 63) | 128);
                utftext += String.fromCharCode((c & 63) | 128);
            }
    
        }

        return utftext;
    };

    crypFunc(){

        return {
            // public method for encoding
            encode : (input) =>{
                var output = "";
                var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
                var i = 0;
            
                input = this._utf8_encode(input);
            
                while (i < input.length) {
            
                    chr1 = input.charCodeAt(i++);
                    chr2 = input.charCodeAt(i++);
                    chr3 = input.charCodeAt(i++);
            
                    enc1 = chr1 >> 2;
                    enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
                    enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
                    enc4 = chr3 & 63;
            
                    if (isNaN(chr2)) {
                    enc3 = enc4 = 64;
                    } else if (isNaN(chr3)) {
                    enc4 = 64;
                    }
            
                    output = output +
                    this._keyStr.charAt(enc1) + this._keyStr.charAt(enc2) +
                    this._keyStr.charAt(enc3) + this._keyStr.charAt(enc4);
            
                }
            
                return output;
            },
        
            // public method for decoding
            decode : (input) => {
                var output = "";
                var chr1, chr2, chr3;
                var enc1, enc2, enc3, enc4;
                var i = 0;
            
                input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");
            
                while (i < input.length) {
            
                    enc1 = this._keyStr.indexOf(input.charAt(i++));
                    enc2 = this._keyStr.indexOf(input.charAt(i++));
                    enc3 = this._keyStr.indexOf(input.charAt(i++));
                    enc4 = this._keyStr.indexOf(input.charAt(i++));
            
                    chr1 = (enc1 << 2) | (enc2 >> 4);
                    chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
                    chr3 = ((enc3 & 3) << 6) | enc4;
            
                    output = output + String.fromCharCode(chr1);
            
                    if (enc3 != 64) {
                    output = output + String.fromCharCode(chr2);
                    }
                    if (enc4 != 64) {
                    output = output + String.fromCharCode(chr3);
                    }
            
                }
            
                output = this._utf8_decode(output);
            
                return output;
        
            },
        }
    }
}