<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::auth')] class extends Component {
    public $email, $password;

    public function login()
    {
        $this->validate();
        $credentials = ['email' => $this->email, 'password' => $this->password];
        if (Auth::attempt($credentials)) {
            session()->regenerate();
            // flash()->use('theme.ruby')->option('position', 'bottom-right')->success('Welcome, ' . Auth::user()->name . '!');
            // Flux::toast(heading: 'Changes saved.', text: 'You can always update this in your settings.');
            return redirect()->intended('/dashboard');
        }
        flash()->use('theme.ruby')->option('position', 'bottom-right')->error('Login gagal. Pastikan email dan password benar.');
    }

    protected function rules()
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
};
?>


{{-- <flux:card class="space-y-6">
    <div>
        <flux:heading size="lg">Log in to your account</flux:heading>
        <flux:text class="mt-2">Welcome back!</flux:text>
    </div>

    <div class="space-y-6">
        <flux:input label="Email" type="email" placeholder="Your email address" />

        <flux:field>
            <div class="mb-3 flex justify-between">
                <flux:label>Password</flux:label>

                <flux:link href="#" variant="subtle" class="text-sm">Forgot password?</flux:link>
            </div>

            <flux:input type="password" placeholder="Your password" />

            <flux:error name="password" />
        </flux:field>
    </div>

    <div class="space-y-2">
        <flux:button variant="primary" class="w-full">Log in</flux:button>

        <flux:button variant="ghost" class="w-full">Sign up for a new account</flux:button>
    </div>
</flux:card> --}}

<div class="flex min-h-screen">
    <div class="flex-1 flex justify-center items-center">
        <div class="w-80 max-w-80 space-y-6">
            <div class="flex justify-center opacity-100">
                <img src="{{ asset('logo/logo-pkm.png') }}" alt="Logo Puskesmas Baruharjo"
                    class="h-20 w-auto object-contain" />
            </div>

            <flux:heading class="text-center" size="xl">Aplikasi Pintar Siap Saji</flux:heading>

            <flux:separator text="Login untuk melanjutkan" />

            <form wire:submit="login">
                <div class="flex flex-col gap-6">
                    <flux:input wire:model="email" label="Email" type="email" placeholder="email@baruharjo" />
                    <flux:field>
                        <div class="mb-3 flex justify-between">
                            <flux:label>Password</flux:label>
                            {{-- <flux:link href="#" variant="subtle" class="text-sm">Forgot password?</flux:link> --}}
                        </div>
                        <flux:input wire:model="password" type="password" placeholder="Masukkan password" viewable />
                        <flux:error name="password" />
                    </flux:field>

                    {{-- <flux:checkbox label="Remember me for 30 days" /> --}}

                    <flux:button type="submit" variant="primary" color="teal">Log In</flux:button>

                    {{-- <flux:button variant="primary" class="w-full">Log in</flux:button> --}}
                </div>
            </form>

            {{-- <flux:subheading class="text-center">
                First time around here? <flux:link href="#">Sign up for free</flux:link>
            </flux:subheading> --}}
        </div>
    </div>

    <div class="flex-1 p-4 max-lg:hidden">
        <div></div>
        <div class="text-white relative rounded-lg h-full w-full bg-zinc-900 flex flex-col items-start justify-end p-16"
            style="background-image: url('{{ asset('assets/teslogin5.png') }}'); background-size: cover">
            {{-- <div class="flex gap-2 mb-4">
                    <flux:icon.star variant="solid" />
                    <flux:icon.star variant="solid" />
                    <flux:icon.star variant="solid" />
                    <flux:icon.star variant="solid" />
                    <flux:icon.star variant="solid" />
                </div> --}}

            {{-- <div class="mb-6 italic font-base text-3xl xl:text-4xl">
                Flux has enabled me to design, build, and deliver apps faster than ever before.
            </div> --}}

            {{-- <div class="flex gap-4">
                <flux:avatar src="https://fluxui.dev/img/demo/caleb.png" size="xl" />

                <div class="flex flex-col justify-center font-medium">
                    <div class="text-lg">Caleb Porzio</div>
                    <div class="text-zinc-300">Creator of Livewire</div>
                </div>
            </div> --}}
        </div>
    </div>
</div>
