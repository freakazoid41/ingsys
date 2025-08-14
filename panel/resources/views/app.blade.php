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
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    @vite(['public/css/theme.css','public/css/custom.css',$type == 'admin' ? 'resources/js/app.js' : 'resources/js/client.js'])
    <input hidden name="header" value="{{session('grp_title') ?? env('APP_NAME')}}">
    <input hidden name="SYS_CUR" value="{{env('SYS_CUR')}}">

<body class="">
    <div id="app" class=""></div>
    @vite(['public/js/index.js','public/js/vendor.js'])
</body>

</html>
