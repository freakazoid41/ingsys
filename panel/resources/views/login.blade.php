<!DOCTYPE html>
<html data-sa-theme="2">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{env('APP_NAME')}}</title>
    <meta name="theme-color" content="#6777ef" />
    <link rel="apple-touch-icon" href="{{ asset('img/icons/apple-touch-icon.png') }}">
   
    <style>
        [data-sa-theme="2"]{
            --bs-body-bg: #273c5b;
            --bs-emphasis-color: #d6e9ff;
            --bs-secondary-color: #7da3c2;
            --bs-body-color: #b3d5ff;
            --bs-link-color-rgb: 78, 151, 255;
            --bs-border-rgb: 102, 130, 162;
            --bs-tertiary-bg: #060b1240;
            --bs-tertiary-rgb: 6, 11, 18;
            --bs-tertiary-inverse-rgb: 131, 159, 202;
            --bs-highlight-text-rgb: 111, 185, 253;
            --bs-highlight-rgb: 111, 159, 255;
            --bs-highlight-secondary-rgb: 15, 26, 41;
            --bs-backdrop-blur: 1rem;
        }

        body{
            background:  rgb(245, 247, 249) !important;
        }
        .card{
            /*background: linear-gradient(45deg,#e45d0b 26%,#ffde59 128%) !important*/
            background: white !important;
            box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
        }
        button {
            background:linear-gradient(45deg,#e45d0b 26%,#ffde59 128%) !important
        }

        
    </style>
    @vite(['public/css/theme.css','resources/js/app.js'])
    <body class="align-items-center flex-column justify-content-center d-flex p-5 h-100" style="height:">
        <div class="card m-auto mw-400 p-8 w-100"> 
            <h2 class="fs-6" style="color : #343a40;">Hoşgeldiniz!</h2>
            <div class="mb-5 text-body-secondary">Sisteme giriş yapmak için bilgilerinizi giriniz..</div> 
            <form class="mb-5" action="{{route('login-user','admin')}}" id="login-form"  method="POST" novalidate="novalidate"> 
                @csrf
                <div class="mb-3 position-relative"> 
                    <i class="fs-3 left-0 m-2.5 ph ph-user-circle position-absolute text-body-secondary top-0"></i> 
                    <input type="text" style="color:black !important" name="email" class="form-control ps-10" placeholder="Username"> 
                </div> 
                <div class="mb-5 position-relative"> 
                    <i class="fs-3 left-0 m-2.5 ph ph-keyhole position-absolute text-body-secondary top-0"></i> 
                    <input type="password" style="color:black !important" name="password" class="form-control ps-10" placeholder="Password"> 
                </div> 
                
                @if (\Session::has('login-success'))
                    <button type="button" class="btn btn-secondary w-100" style="color : white">
                        Giriş Başarılı Ana Sayfaya Yönlendiriyor.. <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </button>
                    <input name="apiKey" hidden readonly value= "{{\Session::get('login-success')}}">
                @else
                    <button type="button" id="submit-button" class="btn btn-secondary w-100" style="color : white">Giriş Yap</button> 
                    
                @endif
            </form> 
            {{--<div class="fs-7 text-center"> 
                <a href="register.html" class="d-block link-muted mb-2">Register for a new account</a> 
                <a href="forgot-password.html" class="d-block link-muted">Forgot password?</a> 
                
            </div>--}} 
            @if (\Session::has('login-error'))
                <br>
                <div class="d-flex justify-content-center bg-opacity-75 btn btn-secondary w-100">
                    {!! \Session::get('login-error') !!}
                </div>
            @endif
        </div>
        @vite(['public/js/index.js','public/js/vendor.js'])
        <script type="module">
            export default class Page {
                constructor() {

                    

                    const login = document.querySelector('input[name="apiKey"]');
                    if(login != null){
                        localStorage.setItem('token',login.value.trim());
                        setTimeout(() => {
                            window.location.href = '/panel';
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
                }   
            }

            (new Page());
        </script>
    </body>

</html>
