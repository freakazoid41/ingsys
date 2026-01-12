<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Login</title>
        <link href="talk/css/bootstrap5.css" rel="stylesheet">
        <link rel="stylesheet" href="/talk/css/main.css">
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    </head>
    <body>
        <div class="login">
            <div class="login-futured">
                <video class="bg-video" autoplay muted loop playsinline>
                    <source src="talk/loginbg.mp4" type="video/mp4">
                </video>
                
                <div class="login-futured-main">
                    <h1>Kontent Kontrol Paneline <b>Hoş Geldiniz.</b></h1>
                    <p>Anasayfaya dönmek için <a href="/seclogin" alt="">lütfen tıklayın <svg xmlns="http://www.w3.org/2000/svg" width="10.945" height="11.774" viewBox="0 0 10.945 11.774"><g transform="translate(17.445 17.86) rotate(180)"><path d="M16.445,18H7.5" transform="translate(0 -6.027)" fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/><path d="M11.973,16.445,7.5,11.973,11.973,7.5" transform="translate(0 0)" fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></g></svg></a></p>
                </div>
            </div>
            <div class="login-form">
                <div class="login-form-logo admin">
                    <img src="/front/assets/img/logo.png" style="width: 242px; height: auto" />
                    <span>Yönetim Paneli</span>
                </div>
                <form class="login-form-main"  action="{{route('login-user','admin')}}" method="POST" novalidate="novalidate"> <!-- <form> or <div> -->
                    @csrf
                    <div class="mb-3 form-line">
                       <input type="text" value="{{env('IS_TEST') && env('IS_TEST') == true ? 'admin@talk.com.tr' : ''}}" class="form-control w-100" id="email-put" name="email" placeholder="Lütfen Giriniz">
                        <label for="email-put" class="form-label">E-posta</label>
                    </div>
                    <div class="mb-3 mt-4 form-line">
                        <input type="password" value="{{env('IS_TEST') && env('IS_TEST') == true ? 'Talk412.' : ''}}" class="form-control w-100" id="phone-put" name="password" placeholder="********">
                        <label for="phone-put" class="form-label">Parola</label>
                    </div>
                    

                    @if (\Session::has('login-success'))
                        <button type="submit" id="submit-button" class="btn btn-theme w-100 mt-5">
                            Giriş Başarılı Ana Sayfaya Yönlendiriyor.. <span class="fa fa-disc fa-spin align-middle ms-2"></span>
                        </button>
                        <input name="apiKey" hidden readonly value= "{{\Session::get('login-success')}}">
                    @else
                        <!-- reCAPTCHA Widget -->
                        <div class="mb-3">
                            <div class="g-recaptcha" data-sitekey="{{ env('RECAPTCHA_SITE_KEY') }}"></div>
                        </div>
                        <button type="submit" id="submit-button" class="btn btn-theme w-100 mt-5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="21.755" height="21.755" viewBox="0 0 21.755 21.755">
                                <g transform="translate(0 0)">
                                    <g transform="translate(0 0)">
                                        <path d="M15.562,6.192,8.383,11.176.874,8.673A1.278,1.278,0,0,1,.886,6.244L20.084.061A1.278,1.278,0,0,1,21.693,1.67l-6.182,19.2a1.278,1.278,0,0,1-2.429.012l-2.515-7.545Z" transform="translate(0 0)" fill="#fff" fill-rule="evenodd"/>
                                    </g>
                                </g>
                            </svg> 
                            Giriş Yap
                        </button>
                        
                    @endif


                    <div class="mb-3 form-check">
                        <div class="checkbox-theme">
                            <input type="checkbox" id="checkMeOut"/>
                            <label for="checkMeOut">
                            <svg viewBox="0,0,50,50">
                                <path d="M5 30 L 20 45 L 45 5"></path>
                            </svg>
                            </label>
                        </div>
                        <label class="form-check-label" for="checkMeOut">Beni Hatırla</label>
                    </div>
                    @if (\Session::has('login-error'))
                        <br>
                        <div class="d-flex justify-content-center bg-opacity-75 btn btn-secondary w-100">
                            {!! \Session::get('login-error') !!}
                        </div>
                    @endif
                </form>
                
            </div>
        </div>
        <script type="module" >
            export default class Page {
                constructor() {
                    const login = document.querySelector('input[name="apiKey"]');
                    if(login != null){
                        localStorage.setItem('token',login.value.trim());
                        setTimeout(() => {
                            window.location.href = '/kontent';
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
                    const form = document.querySelector(".login-form-main");
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
                    })
                }   



            }

            (new Page());
        </script>
    </body>
</html>