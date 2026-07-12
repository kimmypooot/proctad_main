<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2A338F">

    <title inertia>{{ config('app.name', 'ProCTAD') }}</title>
    <meta name="description" content="The Professionalized Corps of Test Administrators Database (ProCTAD) of the Civil Service Commission Regional Office VIII — building a corps of competent, credible, and professional civil service examination administrators.">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="ProCTAD — CSC Regional Office VIII">
    <meta property="og:title" content="Professionalized Corps of Test Administrators">
    <meta property="og:description" content="Official portal of the Professionalized Corps of Test Administrators, Civil Service Commission Regional Office VIII.">
    <meta property="og:url" content="{{ url()->current() }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Professionalized Corps of Test Administrators">
    <meta name="twitter:description" content="Official portal of the Professionalized Corps of Test Administrators, Civil Service Commission Regional Office VIII.">

    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="/favicon.ico" sizes="any">

    {{ Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @inertia
</body>
</html>
