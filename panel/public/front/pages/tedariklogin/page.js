import Plib from "/coaltheme/js/pickle.js";
export default class Page {
    constructor() {
        this.plib = new Plib();
        const login = document.querySelector('input[name="apiKey"]');
        if(login != null){
            localStorage.setItem('token',login.value.trim());
            const isFirst = document.querySelector('input[name="firstLogin"]');
            setTimeout(() => {
                    window.location.href = isFirst ? 'auth/passwordreset/firstlogin' : '/tedarikpanel';
            }, 400);
        }else{
            this.pageEvents();
            if(document.cookie.includes('email')){
                const em = this.getCookie('email');
                const pw = this.getCookie('password');
                if (em) document.getElementById('email').value = em;
                if (pw) document.getElementById('password').value = pw;
                const rem = document.getElementById('remember');
                if (rem && em) rem.checked = true;
            }
        }
    }

    getCookie(name){
        const v = document.cookie.match('(^|;)\\s*'+name+'\\s*=\\s*([^;]+)');
        return v ? decodeURIComponent(v.pop()) : '';
    }

    setCookie(name,value,days=30){
        const d = new Date();
        d.setTime(d.getTime() + days*24*60*60*1000);
        document.cookie = name+"="+encodeURIComponent(value)+";expires="+d.toUTCString()+";path=/";
    }

    pageEvents(){
        const form = document.querySelector("#login-form");
        const submitButton = document.querySelector("#submit-button");
        if (!submitButton || !form) return;

        submitButton.addEventListener('click', async e=>{
            e.preventDefault();
            // basic required check
            const email = document.getElementById('email');
            const pass = document.getElementById('password');
            if (!email.value.trim() || !pass.value.trim()) {
                if(window.Swal) Swal.fire({icon:'warning', title:'Eksik Alan', text:'E-Posta ve şifre zorunludur.'});
                return;
            }
            // remember me
            const rem = document.getElementById('remember');
            if (rem && rem.checked) {
                this.setCookie('email', email.value.trim(), 30);
                this.setCookie('password', pass.value, 30);
            } else if (rem) {
                this.setCookie('email','', -1);
                this.setCookie('password','', -1);
            }
            e.target.innerHTML = 'Lütfen bekleyin... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>';
            e.target.disabled = true;
            form.submit();
        });

        document.addEventListener("keypress", (event) => {
            if (event.keyCode == 13) {
                event.preventDefault();
                submitButton.click();
            }
        });

        const forgetBtn = document.getElementById('btn-forget');
        if (forgetBtn) {
            forgetBtn.addEventListener('click',e=>{
                Swal.fire({
                    title: 'E-mail Adresinizi Giriniz  ... <br><small>(Kullanıcı Adınız)</small>',
                    input: 'text',
                    inputAttributes: { autocapitalize: 'off' },
                    heightAuto: false ,
                    showCancelButton: true,
                    cancelButtonText : 'İptal',
                    confirmButtonText: 'Sıfırla !',
                    showLoaderOnConfirm: true,
                    preConfirm: async (uinput) => {
                        Swal.showLoading();
                        await this.plib.request({
                            method : 'POST',
                            url    : '/api/auth/sendmail',
                            data   : { mail : uinput }
                        }).then(rsp=>{
                            if(rsp.success){
                                Swal.fire({
                                    showConfirmButton : false,
                                    showCloseButton   : true,
                                    icon  : 'success',
                                    heightAuto: false ,
                                    title : rsp.message,
                                    willClose : () => { window.location.reload(); },
                                });
                            }else{
                                Swal.fire({
                                    icon  : 'error',
                                    heightAuto: false ,
                                    title : rsp.message,
                                    willClose : () => { window.location.reload(); },
                                });
                            }
                        });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                });
            });
        }
    }
}
