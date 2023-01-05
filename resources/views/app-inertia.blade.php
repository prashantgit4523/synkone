<!DOCTYPE html>
<html lang="en">
<head>
    <title>@yield('title', decodeHTMLEntity($globalSetting->display_name))</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta http-equiv="Content-Security-Policy" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <meta name="api-base-url" content="{{ url('/') }}" />
    <meta name="api-disk-driver" content="{{config('filesystems.default')}}" />
    <meta name="api-storage-url" content="{{ rtrim(\Illuminate\Support\Facades\Storage::url('/'),'/') }}" />
{{--    {{dd(rtrim(\Illuminate\Support\Facades\Storage::url('/'),'/'))}}--}}
    <meta name="top-level-data-scope" content='@json($topLevelDataScope)' />
    <meta name="page-title" content="{{ @$pageTitle }}" />

    <!-- App favicon -->

    @if(config('filesystems.default') == 'local')
        @if(env('TENANCY_ENABLED'))
        <link rel="shortcut icon" href="{{$globalSetting->favicon =='assets/images/cyberarrow-favicon.png' || $globalSetting->favicon =='assets/images/ebdaa-Logo.png' ? asset($globalSetting->favicon):tenant_asset($globalSetting->favicon) }}">
        @else
            <link rel="shortcut icon" href="{{$globalSetting->favicon =='assets/images/cyberarrow-favicon.png' || $globalSetting->favicon =='assets/images/ebdaa-Logo.png' ? asset($globalSetting->favicon):asset('storage'.$globalSetting->favicon) }}">
        @endif
    @else
    <link rel="shortcut icon" href="{{ $globalSetting->favicon =='assets/images/cyberarrow-favicon.png' || $globalSetting->favicon =='assets/images/ebdaa-Logo.png' ? asset($globalSetting->favicon):$globalSetting->favicon }}">
    @endif

    <!-- css root variables -->
    @include('layouts.partials.root-css-vars')

    <!-- Plugins css -->
    <link href="{{asset('assets/css/bootstrap.min.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/css/icons.min.css')}}" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link href="{{asset('assets/css/app.min.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/css/custom.css')}}" rel="stylesheet" type="text/css" />
    <style>
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background: var(--secondary-color) !important;
        }
        .particles-js-canvas-el{
            position: absolute;
            top: 0%;
            z-index: -1;
        }
        
        </style>


        @routes(null, csp_nonce())

</head>

    <body class="center-menu @yield('body-class')" id="app-body">
        @inertia
    <!-- React App js-->
    <!-- <link href="{{ mix('/css/app.css') }}" rel="stylesheet" /> -->
    <script src="{{ mix('/js/manifest.js') }}" defer></script>
    <script src="{{ mix('/js/vendor.js') }}" defer></script>
    <script src="{{ mix('/js/app.js') }}" defer></script>
</body>

</html>
