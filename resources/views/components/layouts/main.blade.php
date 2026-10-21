<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <livewire:main.navbar />

    <flux:main container>
        {{ $slot }}
    </flux:main>

    @include('components.footer')

    @fluxScripts

    @persist('toast')
        <flux:toast />
    @endpersist
</body>

<div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10">

    <div
        class="absolute top-1/3 -right-40
               w-[42rem] h-[42rem]
               bg-indigo-200/20 dark:bg-indigo-900/25
               rounded-full blur-3xl">
    </div>
    
    <div
        class="absolute bottom-[-12rem] left-1/4
               w-[32rem] h-[32rem]
               bg-blue-100/40 dark:bg-blue-950/40
               rounded-full blur-3xl">
    </div>
</div>

</html>
