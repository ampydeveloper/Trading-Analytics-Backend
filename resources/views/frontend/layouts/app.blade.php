<!DOCTYPE html>
@langrtl
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    @else
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        @endlangrtl
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <title>@yield('title', app_name())</title>
            <meta name="author" content="@yield('meta_author', 'Slabstox')">
            <link rel="shortcut icon" type="image/jpg" href="/slabstox.ico"/>
            @yield('meta')

            @stack('before-styles')
            {{ style(mix('css/frontend.css')) }}
            @stack('after-styles')
        </head>
        <body>
            @include('includes.partials.read-only')

            <div id="app">
                @include('includes.partials.logged-in-as')
                @include('frontend.includes.nav')

                <div class="container-fluid">
                    @yield('content')
                </div><!-- container -->
            </div><!-- #app -->

            <!-- Scripts -->
            @stack('before-scripts')
            {!! script(mix('js/manifest.js')) !!}
            {!! script(mix('js/vendor.js')) !!}
            {!! script(mix('js/frontend.js')) !!}
            @stack('after-scripts')

            @include('includes.partials.ga')
        </body>
    </html>
