<!-- resources/views/layouts/base.blade.php -->
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title', trans('oh.title'))</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="color-scheme" content="light only">
    <meta name="description" content="@yield('description', trans('oh.description'))">
    <meta name="robots" content="index, follow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="theme-color" content="#f4881b">
    <meta property="og:title" content="@yield('title', trans('oh.title'))">
    <meta property="og:description" content="@yield('description', trans('oh.description'))">
    {{-- <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet"> --}}
    <meta property="og:image" content="{{ Vite::asset('resources/images/photo.jpg') }}">
    <meta property="og:image:secure_url" content="{{ Vite::asset('resources/images/photo.jpg') }}">
    <link rel="shortcut icon" type="image/png" href="{{ Vite::asset('resources/images/logo-16x16.png') }}" sizes="16x16">
    <link rel="shortcut icon" type="image/png" href="{{ Vite::asset('resources/images/logo-32x32.png') }}" sizes="32x32">
    <link rel="shortcut icon" type="image/png" href="{{ Vite::asset('resources/images/logo-96x96.png') }}" sizes="96x96">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ Vite::asset('resources/images/logo-120x120.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ Vite::asset('resources/images/logo-180x180.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ Vite::asset('resources/images/logo-152x152.png') }}">
    <link rel="apple-touch-icon" sizes="167x167" href="{{ Vite::asset('resources/images/logo-167x167.png') }}">
    @vite('resources/css/style.css')
{{--    @stack('styles')--}}
</head>
<body id="body">
<x-forms.loading :global="true" />
<header id="header" class="logo bg-gray-800 py-1 px-3.5">
    <div class="lg:container mx-auto sm:w-full flex justify-between items-center">
        <!-- Left-aligned logo -->
        <div class="flex items-center">
            <a href="/" class="text-black text-lg font-bold">
                <img src="{{ Vite::asset('resources/images/logo.webp') }}" alt="{{ trans('oh.title') }}" width="400" height="150">
            </a>
        </div>

        <!-- Center-aligned menu (hidden on small screens) -->
        <nav class="hidden lg:block text-black text-lg font-semibold">
            <ul class="flex">
                <li><a href="#services" class="text-link p-4 hover:text-orange hover:underline">{{ trans('Переваги') }}</a></li>
                <!--<li><a href="#team" class="text-link p-4 hover:text-orange hover:underline">{{ trans('Команда') }}</a></li>-->
                <li><a href="#offers" class="text-link p-4 hover:text-orange hover:underline">{{ trans('Індивідуальна розробка') }}</a></li>
                <li><a href="#consultation-form" class="text-link p-4 hover:text-orange hover:underline">{{ trans('Контакти') }}</a></li>
            </ul>
        </nav>

        <!-- Right-aligned menu toggle button (visible on small screens) -->
        <button id="menuToggle" class="menu lg:hidden md:block text-black focus:outline-none p-2 w-10 h-10" aria-label="{{ trans('menu')}}">
            <!--<i class="fas fa-bars"></i>
            <i class="fas fa-times hidden"></i>-->

            <svg id="openIcon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
            </svg>
            <svg id="closeIcon" class="hidden w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>

        <!-- Right-aligned social icons -->
        <div class="hidden lg:flex items-center">
            <a href="https://www.facebook.com/openhealthmis" class="text-black mr-4 hover:text-gray-300" aria-label="facebook">
                @icon('facebook', 'w-10 h-10')
            </a>
            <a href="https://github.com/openhealths/nationHealth" class="text-black mr-4 hover:text-gray-300" aria-label="github">
                @icon('github', 'w-10 h-10')
            </a>
            <a href="https://www.youtube.com/@NationHealth-mis" class="text-black hover:text-gray-300" aria-label="youtube">
                @icon('youtube', 'w-10 h-10')
            </a>
        </div>
    </div>

    <!-- Responsive menu (visible on small screens) -->
    <div id="responsiveMenu" class="hidden lg:hidden bg-gray-800">
        <ul class="text-black text-lg font-semibold">
            <li class="py-2 px-4"><a href="#services" class="text-center text-link block hover:text-orange">{{ trans('Переваги') }}</a></li>
            <!--<li class="py-2 px-4"><a href="#team" class="text-center text-link block hover:text-orange">{{ trans('Команда') }}</a></li>-->
            <li class="py-2 px-4"><a href="#offers" class="text-center text-link block hover:text-orange">{{ trans('Індивідуальна розробка') }}</a></li>
            <li class="py-2 px-4"><a href="#consultation-form" class="text-center text-link block hover:text-orange">{{ trans('Контакти') }}</a></li>
        </ul>

        <!-- Right-aligned social icons -->
        <div class="flex justify-center text-center items-center mt-8">
            <a href="https://www.facebook.com/openhealthmis" class="flex justify-center text-black mr-4 hover:text-gray-300" aria-label="facebook">
                @icon('facebook', 'w-10 h-10')
            </a>
            <a href="https://github.com/openhealths/nationHealth" class="text-black mr-4 hover:text-gray-300" aria-label="github">
                @icon('github', 'w-10 h-10')
            </a>
            <a href="https://www.youtube.com/@NationHealth-mis" class="text-black hover:text-gray-300" aria-label="youtube">
                @icon('youtube', 'w-10 h-10')
            </a>
        </div>
    </div>
</header>

<main>
    @yield('content')
</main>

<footer class="bg-gray-3 pt-6 sm:pt-5 pb-6 sm:pb-3">
    <div class="container w-full lg:w-3/5 mx-auto md:text-left text-center text-black">
        <div class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-3 gap-3">
            <div class="p-4">
                <div class="wrapper-content">
                    <h3 class="md:text-3xl text-xl font-bold mb-2">
                        &copy; {{ date('Y') }}
                        {{ trans('Nation Health') }}
                    </h3>
                    <p class="text-meta-10 font-bold">{{ trans('Медична інформаційна система') }}</p>
                </div>
            </div>
            <div class="p-4 flex justify-center">
                <div class="wrapper-content">
                    <h3 class="text-xl font-bold mb-2">{{ trans('Телефонуйте') }}</h3>
                    <p><a href="tel:{{ $phone }}" class="hover:text-orange hover:underline">{{ $phone }}</a></p>
                </div>
            </div>
            <div class="p-4 md:flex justify-end hidden">
                <div class="wrapper-content">
                    <h3 class="text-xl font-bold mb-2">{{ trans('Пишіть нам') }}</h3>
                    <p><a href="mailto:{{ $email }}" class="hover:text-orange hover:underline">{{ $email }}</a></p>
                </div>
            </div>
        </div>
        <ul class="flex justify-center mt-5">
            <li>
                <a href="https://www.facebook.com/openhealthmis" class="icon facebook" aria-label="facebook">
                    @icon('facebook', 'w-10 h-10 icon hover:fill-orange')
                </a>
            </li>
            <li class="ml-4">
                <a href="https://github.com/openhealths/nationHealth" class="icon github" aria-label="github">
                    @icon('github', 'w-10 h-10 icon hover:fill-orange')
                </a>
            </li>
            <li class="ml-4">
                <a href="https://www.youtube.com/@NationHealth-mis" class="icon youtube" aria-label="youtube">
                    @icon('youtube', 'w-10 h-10 icon hover:fill-orange')
                </a>
            </li>
        </ul>

        <div class="md:hidden sm:block grid grid-cols-1 sm:grid-cols-1 md:grid-cols-3 gap-3 mt-4">
            <div class="p-4">
                <h3 class="text-xl font-bold mb-2">{{ trans('Пишіть нам') }}</h3>
                <p><a href="mailto:{{ $email }}" class="hover:text-orange hover:underline">{{ $email }}</a></p>
            </div>
        </div>
    </div>
</footer>

@stack('modals')

@vite('resources/js/app.js')
@vite('resources/js/base.js')
@stack('scripts')
</body>
</html>
