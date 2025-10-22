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
    @vite(['public/talk/css/bootstrap5.css','public/talk/css/fontawesome5.css','public/talk/css/main.css','resources/js/talk.js'])
    <input hidden name="header" value="{{session('grp_title') ?? env('APP_NAME')}}">
    <input hidden name="menubar" value="{{session('type_key')}}">

<body class="">
    <div id="app" class="h-100" style="height:100% !important"></div>
    @vite(['public/talk/js/bootstrap5.js'])
</body>

</html>
