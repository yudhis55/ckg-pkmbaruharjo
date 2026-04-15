<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @fluxAppearance
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">
    <flux:sidebar sticky collapsible class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            {{-- <flux:sidebar.brand href="#" logo="/logo/logo-pkm.png"
                logo:dark="https://fluxui.dev/img/demo/dark-mode-logo.png" name="CKG PKM Baruharjo" /> --}}
            <flux:sidebar.brand href="#" logo="/logo/logo-pkm.png" name="SAPI" />
            <flux:sidebar.collapse
                class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>
        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" href="{{ route('dashboard') }}"
                :current="request()->routeIs('dashboard')">Dashboard</flux:sidebar.item>
            <flux:sidebar.item icon="user-group" href="{{ route('pasien') }}" :current="request()->routeIs('pasien')">
                Klaim Pasien</flux:sidebar.item>
            <flux:sidebar.item icon="document-chart-bar" href="{{ route('capaian-individu') }}"
                :current="request()->routeIs('capaian-individu')">Capaian Individu</flux:sidebar.item>
            <flux:sidebar.item icon="arrow-path" href="{{ route('sinkron-data') }}"
                :current="request()->routeIs('sinkron-data')">Sinkron Data</flux:sidebar.item>
            <flux:sidebar.item icon="cog-6-tooth" href="{{ route('pengaturan') }}"
                :current="request()->routeIs('pengaturan')">Pengaturan</flux:sidebar.item>
            <flux:sidebar.item icon="user" href="{{ route('profil-saya') }}"
                :current="request()->routeIs('profil-saya')">Profil Saya</flux:sidebar.item>
        </flux:sidebar.nav>
        <flux:sidebar.spacer />
        <flux:sidebar.nav>
            <flux:sidebar.item>
                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                    <flux:radio value="light" icon="sun" />
                    <flux:radio value="dark" icon="moon" />
                    <flux:radio value="system" icon="computer-desktop" />
                </flux:radio.group>
            </flux:sidebar.item>
        </flux:sidebar.nav>
        <flux:dropdown position="top" align="start" class="max-lg:hidden">
            <flux:sidebar.profile avatar="https://fluxui.dev/img/demo/user.png" name="{{ Auth::user()->name }}" />
            <flux:menu>
                <flux:menu.item icon="arrow-right-start-on-rectangle">Informasi Akun</flux:menu.item>
                <flux:menu.separator />
                <livewire:auth.logout />
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:spacer />
        <flux:dropdown position="top" align="start">
            <flux:profile avatar="https://fluxui.dev/img/demo/user.png" />
            <flux:menu>
                <flux:menu.item icon="arrow-right-start-on-rectangle">Informasi Akun</flux:menu.item>
                <flux:menu.separator />
                <livewire:auth.logout />
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @livewireScripts
    @fluxScripts
</body>

</html>
