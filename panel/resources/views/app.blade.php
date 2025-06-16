<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Vue Laravel SPA') }}</title>
    <meta name="theme-color" content="#6777ef" />
    <link rel="apple-touch-icon" href="{{ asset('img/icons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('/manifest.json') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    @vite(['public/css/theme.css','resources/js/app.js'])

<body class="">
    <div id="app" class=""></div>
    @vite(['public/js/index.js','public/js/vendor.js'])
</body>

</html>
