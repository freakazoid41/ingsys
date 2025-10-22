<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Vue Laravel SPA') }}</title>
    <meta name="theme-color" content="#6777ef" />
    <link rel="apple-touch-icon" href="{{ asset('img/icons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('/manifest.json') }}">
    <link rel="icon" type="image/x-icon" href="/public/css/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:ital,wght@0,300;0,400;0,500;0,700;1,300;1,400;1,500;1,700&display=swap">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <link href="/talk/css/bootstrap5.css" rel="stylesheet">
    <link href="/front/assets/css/main.css" rel="stylesheet">
    <input hidden name="header" value="{{session('grp_title') ?? env('APP_NAME')}}">
    

<body class="">
    <div class="main">
    <div class="main-header">
      <div class="logo">
        <img src="/front/assets/img/logo.png" alt="">
      </div>
      <div class="detail">
        <div class="dropdown">
          <button class="btn btn-secondary dropdown-toggle lang-button" type="button" data-bs-toggle="dropdown"
            aria-expanded="false">
            <img src="/front/assets/img/network.png" class="network" alt="">{{strtoupper(App::getLocale())}}
          </button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" data-lang="tr" href="#">TR</a></li>
            <li><a class="dropdown-item" data-lang="en" href="#">EN</a></li>
          </ul>
        </div>
        <p>{{$title}}</p>
        <small>{{$address}}</small>
      </div>
    </div>
    <div class="main-body">
      <div class="main-body-head">
        
        @if(App::getLocale() == 'tr')
          <h1>{{$title}}’e <br>{{ __('main.greet') }}</h1>
        @else
          <h1>{{ __('main.greet') }} <br>{{$title}}</h1>
        @endif
        <p>{{ __('main.desc') }}</p>
      </div>
      <div class="form-main mt-0">
        <form action="/ziyaretcikontrol" id="enterform" method="post">
            <div class="form-group theme-group">
                <label for="">{{ __('main.form.phone') }}</label>
                <input type="text" id="phone" name="phone" class="form-control"   @if(App::getLocale() == 'tr')  
                                                                                    placeholder="0 (5__) ___ __ __" 
                                                                                  @else  
                                                                                    placeholder="+90 (5__) ___ __ __"
                                                                                  @endif>
                <input hidden name="facility" value="{{$id}}">
            </div>
        </form>
      </div>
    </div>
    <div class="main-footer">
      <button class="button-theme" id="continueBtn">{{ __('test.next') }} <img src="/front/assets/img/rightArrow.png" class="icon"></button>
    </div>
  </div>
  <div class="time-period">
    <p class="clock">14:03</p>
    <p class="date">20/06/2025</p>
  </div>
  <script src="/talk/js/bootstrap5.js"></script>
  <script src="https://unpkg.com/imask"></script>
  <script>
    const element = document.getElementById('phone');
    @if(App::getLocale() != 'en')
      const maskOptions = {
        mask: '{\\0} (500) 000 00 00'
    };
    @else
    const maskOptions = {
        mask: '+{9\\0} (500) 000 00 00'
    };
    @endif
    const mask = IMask(element, maskOptions);
    document.getElementById('continueBtn').addEventListener('click',e => document.getElementById('enterform').submit());

    //listen lang buttons for lang change on login screen
    document.querySelectorAll('.dropdown-item').forEach(element => {
        element.addEventListener('click', async e=> {
          const envelope  = new FormData();
          envelope.append('locale',e.target.dataset.lang);
          await fetch("/api/set-locale", {
            method: "POST",
            body: envelope,
            // …
          }).then(rsp => window.location.reload());
        });
    });
  </script>
</body>

</html>
