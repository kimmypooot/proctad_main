<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2A338F">

    {{-- Progressive Web App — installable, offline-capable exam-day scanner --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ProCTAD">

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

    {{-- Open Graph image (generated SVG badge as fallback) --}}
    <meta property="og:image" content="{{ url('/images/brand/csclogo.png') }}">
    <meta property="og:image:width" content="512">
    <meta property="og:image:height" content="512">
    <meta name="twitter:image" content="{{ url('/images/brand/csclogo.png') }}">

    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/images/brand/proctad-logo.png">

    {{-- Font preconnect for performance --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>

    {{ Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead

    @php
        $orgSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'GovernmentOrganization',
            'name' => 'Civil Service Commission Regional Office VIII',
            'alternateName' => 'CSC RO VIII',
            'description' => 'The Professionalized Corps of Test Administrators Database (ProCTAD) — building a corps of competent, credible, and professional civil service examination administrators.',
            'url' => url('/'),
            'parentOrganization' => [
                '@type' => 'GovernmentOrganization',
                'name' => 'Civil Service Commission',
                'url' => 'https://www.csc.gov.ph',
            ],
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Palo',
                'addressRegion' => 'Leyte',
                'addressCountry' => 'PH',
            ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => '(053) 123-4567',
                'email' => 'cscro8@csc.gov.ph',
                'contactType' => 'customer service',
            ],
        ];
    @endphp
    {{-- Nonce because the Content-Security-Policy has no 'unsafe-inline' for
         scripts; browsers apply script-src to ld+json blocks too, so without it
         the structured data is silently dropped. See SecurityHeaders. --}}
    <script type="application/ld+json" nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">{{ json_encode($orgSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</script>
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @inertia
</body>
</html>
