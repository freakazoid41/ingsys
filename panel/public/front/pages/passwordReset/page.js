import Plib from "/coaltheme/js/pickle.js";


        export default class Page {
            constructor() {
                this.plib = new Plib();
                this.pageEvents();
            }

            async pageEvents(){ 
                
                const newLogin = window.location.href.includes('newlogin');

                //this method will check form elements validity with regex
                const checkInputs = (type) => {
                    const text = document.querySelector('.login-item[name="'+type+'"]').value.trim();
                    let valid = true;
                    
                    switch (type) {
                        case 'password':
                            valid = text.match(
                                /^(?=.{8,}$)(?=.*[a-z])(?=.*\d)(?=.*[=!\-@._*])[A-Za-z0-9=!\-@._*]+$/
                            ) != null;
                            break;
                        case 'email':
                            valid = text.match(
                                /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
                            ) != null;
                            break;
                    }

                    
                    
                    const errCont = document.getElementById("err-"+type);
                        
                    if(!valid){
                        errCont.style.display = "unset";
                    }else{
                        errCont.style.cssText = 'display:none !important';
                    }

                    return valid;
                }
                
                //form validator
                document.querySelectorAll('.login-item').forEach(el=>{
                    //validate form elements for each input element
                    el.addEventListener('input',e=>checkInputs(e.target.name));
                });
                    
                //send sms code
                /*const sendCode = async () => {
                    this.plib.processLoading();
                    const rsp = await this.plib.request({
                        method : 'POST',
                        url    : '/api/auth/sendcode',
                        data   : {
                            mail : document.getElementById('in-email').value.trim()
                        }
                    });

                    Swal.close();
                    return rsp;
                }*/

                /*if(!newLogin){
                    const codeSend = await sendCode();
                    if(codeSend.success === false){
                        this.plib.toast('error',codeSend.message);
                    }
                }else{
                    document.getElementById('div-sms').setAttribute('style','display:none !important;');
                    document.getElementById('login-form').style.display = 'flex';
                }*/
                
                





                //listen form
                document.getElementById('kt_sign_in_submit').addEventListener('click',async e=>{
                    Swal.fire({
                        heightAuto: false,
                        title: 'Lütfen Bekleyiniz..',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading()
                        }
                    });
                    const rsp = this.plib.validatePassword(document.getElementById('in-check'));
                    if(rsp.valid){
                        document.getElementById('in-check').classList.remove('is-invalid');
                        const pass = document.getElementById('in-check').value.trim();
                        //const mail = document.getElementById('in-email').value.trim();
                        if( pass === document.getElementById('in-main').value.trim()){
                        document.getElementById('login-form').submit();
                        }else{
                            Swal.close();
                            document.getElementById('in-check').classList.add('is-invalid');
                            this.plib.toast('error','Parolalar Uyuşmamaktadır..');
                            return false;
                        }
                    }else{
                        document.getElementById('in-check').classList.add('is-invalid');
                        this.plib.toast('error',rsp.errors.join("<br> \n"),8000);
                    }
                });
            }   
        }