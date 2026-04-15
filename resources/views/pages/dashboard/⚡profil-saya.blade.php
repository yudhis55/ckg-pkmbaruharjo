<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<flux:main>
    <flux:heading size="xl" level="1" class="mb-6">Profil Saya</flux:heading>
    <flux:separator variant="subtle" class="mb-6" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="lg">Foto Profil</flux:heading>
                <flux:text class="mt-1" variant="subtle">Ubah foto profil akun Anda.</flux:text>

                <div class="mt-6 flex flex-col items-center gap-4">
                    <flux:avatar src="https://ui-avatars.com/api/?name=Profil+Saya&size=160" size="2xl" />
                    <flux:text variant="subtle" class="text-sm text-center">Format JPG/PNG, maksimal 2MB</flux:text>
                    <flux:input type="file" />
                    <div class="flex w-full gap-2">
                        <flux:button variant="outline" class="w-full" icon="arrow-up-tray">Upload</flux:button>
                        <flux:button variant="ghost" class="w-full" icon="trash">Hapus</flux:button>
                    </div>
                </div>
            </div>
        </div>

        <div class="xl:col-span-8 space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="lg">Informasi Akun</flux:heading>
                <flux:text class="mt-1 mb-5" variant="subtle">Perbarui nama dan akun Anda.</flux:text>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <flux:input label="Nama Lengkap" placeholder="Masukkan nama lengkap" />
                    <flux:input label="Username" placeholder="Masukkan username" />
                    <div class="md:col-span-2">
                        <flux:input label="Email" type="email" placeholder="nama@email.com" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="lg">Ubah Password</flux:heading>
                <flux:text class="mt-1 mb-5" variant="subtle">Isi hanya jika ingin mengganti password.</flux:text>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <flux:input label="Password Saat Ini" type="password"
                            placeholder="Masukkan password saat ini" />
                    </div>
                    <flux:input label="Password Baru" type="password" placeholder="Masukkan password baru" />
                    <flux:input label="Konfirmasi Password Baru" type="password" placeholder="Ulangi password baru" />
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <flux:button variant="ghost">Batal</flux:button>
                <flux:button icon="check">Simpan Perubahan</flux:button>
            </div>
        </div>
    </div>
</flux:main>
