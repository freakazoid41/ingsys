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
    @vite(['public/talk/css/bootstrap5.css','public/front/assets/css/main.css','resources/js/front.js'])
    <input hidden name="header"    value="{{session('grp_title') ?? env('APP_NAME')}}">
    <input hidden name="phonedata" value="{{session('phone')}}">
    <input hidden name="connkey"   value="{{session('connKey')}}">
    <input hidden name="qnid"      value="{{session('qnid')}}">
    <input hidden name="facility"  value="{{session('facility')}}">
    @if(session('mustWatch') != null)
        <input hidden name="mustwatch" value="true">
    @endif
<body class="">
    <div id="app" class=""></div>
    @vite(['public/talk/js/bootstrap5.js'])
</body>

</html>
