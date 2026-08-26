import Plib from "/coaltheme/js/pickle.js";
export default class Page {
    
    constructor() {
        this.plib = new Plib();
        const login = document.querySelector('input[name="apiKey"]');
        if(login != null){
            localStorage.setItem('token',login.value.trim());
            setTimeout(() => {
                    window.location.href = document.querySelector('input[name="firstLogin"]') ? 'auth/passwordreset/firstlogin' :  '/coalpanel';
            }, 400);
        }else{
            this.pageEvents();
            if(document.cookie.includes('email')){
                document.getElementById('email').value    = this.getCookie('email');
                document.getElementById('password').value = this.getCookie('password');
            }
        }

    }

    pageEvents(){
        const form = document.querySelector("#login-form");
        const submitButton = document.querySelector("#submit-button");
        //listen form
        submitButton.addEventListener('click',async e=>{
            e.preventDefault();
            /*const data = new FormData(e.target);
            const formData = Object.fromEntries(data.entries());*/
            e.target.innerHTML = 'Lütfen bekleyin... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>';
            
            form.submit();
            
            /*await this.plib.transaction('login','login',formData).then(rsp=>{
                if(rsp.success){
                    localStorage.setItem('token',rsp.token);
                    
                    window.location.href = '/admin';
                    Swal.close();
                }else{
                    Swal.fire({
                        icon  : 'error',
                        title : 'Kullanıcı Bulunamadı !!'
                    });
                }
            });*/
        });

        document.addEventListener("keypress", (event) => {
            // If the user presses the "Enter" key on the keyboard
            if (event.keyCode == 13) {
                // Cancel the default action, if needed
                event.preventDefault();
                // Trigger the button element with a click
                submitButton.click();
            }
        });

        document.getElementById('btn-forget').addEventListener('click',e=>{
            Swal.fire({
                title: 'E-mail Adresinizi Giriniz  ... <br><small>(Kullanıcı Adınız)</small>',
                input: 'text',
                inputAttributes: {
                autocapitalize: 'off'
                },
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
                        data   : {
                            mail : uinput
                        }
                    }).then(rsp=>{
                        if(rsp.success){
                            Swal.fire({
                                showConfirmButton : false,
                                showCloseButton   : true,
                                icon  : 'success',
                                heightAuto: false ,
                                title : rsp.message,
                                willClose : () => {
                                    window.location.reload();
                                },
                            });
                        }else{
                            Swal.fire({
                                icon  : 'error',
                                heightAuto: false ,
                                title : rsp.message,
                                willClose : () => {
                                    window.location.reload();
                                },
                            });
                        }
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            });
        });
    }   
    


}