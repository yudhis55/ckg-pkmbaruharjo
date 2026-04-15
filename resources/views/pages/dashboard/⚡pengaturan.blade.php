<?php

use Livewire\Component;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use App\Models\Tahun;

new class extends Component {
    use WithPagination, WithoutUrlPagination;

    public $tahun, $tahun_selected_id;
    public $name, $email, $password, $user_selected_id;
    public $search = '';

    #[Computed]
    public function tahuns()
    {
        return Tahun::orderBy('tahun', 'desc')->paginate(4, pageName:'tahun_page');
    }

    #[Computed]
    public function users()
    {
        $query = User::query()->when($this->search, function ($q) {
            $q->where('name', 'like', '%' . $this->search . '%')->orWhere('email', 'like', '%' . $this->search . '%');
        });
        return $query->paginate(10, pageName: 'user_page');
    }

    public function addYear()
    {
        $validated = $this->validate([
            'tahun' => 'required|integer|unique:tahun,tahun',
        ]);
        Tahun::create($validated);
        Flux::modals()->close();
        flash()->use('theme.ruby')->option('position', 'bottom-right')->success('Tahun berhasil ditambahkan.');
    }

    public function editYear()
    {
        $validated = $this->validate([
            'tahun' => 'required|integer|unique:tahun,tahun,' . $this->tahun_selected_id,
        ]);
        Tahun::findOrFail($this->tahun_selected_id)->update(['tahun' => $this->tahun]);
        Flux::modals()->close();
        flash()->use('theme.ruby')->option('position', 'bottom-right')->success('Tahun berhasil diperbarui.');
    }

    public function deleteYear()
    {
        if (Tahun::findOrFail($this->tahun_selected_id)->is_active) {
            Flux::modals()->close();
            flash()->use('theme.ruby')->option('position', 'bottom-right')->error('Tahun aktif tidak dapat dihapus. Silakan set tahun lain sebagai aktif terlebih dahulu.');
            return;
        }
        Tahun::findOrFail($this->tahun_selected_id)->delete();
        Flux::modals()->close();
        flash()->use('theme.ruby')->option('position', 'bottom-right')->success('Tahun berhasil dihapus.');
    }

    public function setTahunAktif($id)
    {
        Tahun::query()->update(['is_active' => false]);
        Tahun::findOrFail($id)->update(['is_active' => true]);
        flash()->use('theme.ruby')->option('position', 'bottom-right')->success('Tahun aktif berhasil diubah.');
    }

    public function addUser()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => 'user',
        ]);

        Flux::modals()->close();
        flash()->use('theme.ruby')->option('position', 'bottom-right')->success('User berhasil ditambahkan.');
    }

    public function editUser()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->user_selected_id,
            'password' => 'nullable|string',
        ]);
        $user = User::findOrFail($this->user_selected_id);
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();
        Flux::modals()->close();
        flash()->use('theme.ruby')->option('position', 'bottom-right')->success('User berhasil diperbarui.');
    }

    public function updatedSearch()
    {
        $this->resetPage('user_page');
    }
};
?>

<flux:main>
    <flux:heading size="xl" level="1" class="mb-6">Pengaturan</flux:heading>
    <flux:separator variant="subtle" class="mb-6" />

    <div class="mb-4">
        <flux:heading size="lg">Pengaturan Tahun</flux:heading>
        <flux:text class="mt-1" variant="subtle">Kelola tahun dan status aktif</flux:text>
    </div>

    <div class="mb-3 flex items-center gap-2">
        <flux:spacer />
        <flux:modal.trigger name="tambah-tahun">
            <flux:button icon="plus">Tambah Tahun</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>No</flux:table.column>
            <flux:table.column>Tahun</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->tahuns as $index => $tahuns)
                <flux:table.row>
                    <flux:table.cell>
                        {{ $this->tahuns->firstItem() + $index }}
                    </flux:table.cell>
                    <flux:table.cell variant="strong">{{ $tahuns->tahun }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($tahuns->is_active)
                            {{-- <flux:badge color="green" size="sm" inset="top bottom">Aktif</flux:badge> --}}
                            <flux:button variant="primary" color="green" icon="check-circle">Aktif
                            </flux:button>
                        @else
                            <flux:button wire:click="setTahunAktif({{ $tahuns->id }})">Set Aktif</flux:button>
                        @endif
                        {{-- <flux:badge color="green" size="sm" inset="top bottom">
                            {{ $tahuns->is_active ? 'Aktif' : 'Tidak Aktif' }}</flux:badge> --}}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:modal.trigger name="edit-tahun">
                                <flux:button
                                    @click="$wire.tahun_selected_id = {{ $tahuns->id }}; $wire.tahun = {{ $tahuns->tahun }}; $wire.is_active = {{ $tahuns->is_active }}"
                                    size="sm" variant="outline" icon="pencil-square">Edit</flux:button>
                            </flux:modal.trigger>
                            <flux:modal.trigger name="delete-tahun">
                                <flux:button @click="$wire.tahun_selected_id = {{ $tahuns->id }}" size="sm"
                                    variant="danger" icon="trash">Hapus</flux:button>
                            </flux:modal.trigger>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center text-zinc-500">
                        Tidak ada data tahun.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
    <flux:pagination :paginator="$this->tahuns" />

    <flux:separator variant="subtle" class="my-8" />

    <div class="mb-4">
        <flux:heading size="lg">Pengaturan User</flux:heading>
        <flux:text class="mt-1" variant="subtle">Kelola akun dan informasi user</flux:text>
    </div>

    <div class="mb-3 flex items-center gap-2">
        <flux:input wire:model.live="search" class="w-full max-w-sm" icon="magnifying-glass"
            placeholder="Cari user..." />
        <flux:spacer />
        <flux:modal.trigger name="tambah-user">
            <flux:button icon="user-plus">Tambah User</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>No</flux:table.column>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row>
                    <flux:table.cell class="text-zinc-400 text-sm">
                        {{ ($this->users()->currentPage() - 1) * $this->users()->perPage() + $loop->iteration }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&size=32"
                                size="xs" class="shrink-0" />
                            <span class="font-medium">{{ $user->name }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown position="bottom" align="end" offset="-15">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                            <flux:menu>
                                <flux:modal.trigger name="edit-user">
                                    <flux:menu.item
                                        @click="$wire.user_selected_id = {{ $user->id }}; $wire.name = '{{ $user->name }}'; $wire.email = '{{ $user->email }}'"
                                        icon="pencil-square">Edit</flux:menu.item>
                                </flux:modal.trigger>
                                <flux:menu.separator />
                                <flux:modal.trigger name="delete-user">
                                    <flux:menu.item icon="trash" variant="danger">Hapus</flux:menu.item>
                                </flux:modal.trigger>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
    <flux:pagination :paginator="$this->users" />

    {{-- Modal Tambah User --}}
    <flux:modal name="tambah-user" class="md:w-96">
        <form wire:submit="addUser">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Tambah User</flux:heading>
                    <flux:text class="mt-1" variant="subtle">Buat akun pengguna baru</flux:text>
                </div>
                <flux:input wire:model="name" label="Nama" placeholder="Nama lengkap" />
                <flux:input wire:model="email" label="Email" type="email" placeholder="email@baruharjo" />
                <flux:input wire:model="password" label="Password" type="password" placeholder="Password" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Simpan</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Edit User --}}
    <flux:modal name="edit-user" class="md:w-96">
        <form wire:submit="editUser">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Edit User</flux:heading>
                    <flux:text class="mt-1" variant="subtle">Ubah informasi pengguna</flux:text>
                </div>
                <flux:input wire:model="name" label="Nama" placeholder="Nama lengkap" />
                <flux:input wire:model="email" label="Email" type="email" placeholder="email@baruharjo" />
                <flux:input wire:model="password" label="Password Baru" type="password"
                    placeholder="Kosongkan jika tidak diubah" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Simpan</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Hapus User --}}
    <flux:modal name="delete-user" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus User?</flux:heading>
                <flux:text class="mt-2">
                    Data user ini akan dihapus secara permanen.<br>
                    Tindakan ini tidak dapat dibatalkan.
                </flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Hapus User</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="tambah-tahun" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Tambah Tahun Baru</flux:heading>
                <flux:text class="mt-1" variant="subtle">Tambahkan tahun pemeriksaan baru</flux:text>
            </div>
            <form wire:submit="addYear">
                <flux:input wire:model="tahun" label="Tahun" placeholder="Contoh: {{ now()->year + 1 }}" />
                <flux:error name="tahun" />
                <div class="flex justify-end gap-2 mt-6">
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Tambah Tahun</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="edit-tahun" class="min-w-[22rem]">
        <form wire:submit="editYear">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Edit Tahun</flux:heading>
                    <flux:text class="mt-1" variant="subtle">Ubah informasi tahun</flux:text>
                </div>
                <flux:input wire:model="tahun" label="Tahun" placeholder="Tahun" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Simpan</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-tahun" class="min-w-[22rem]">
        <form wire:submit="deleteYear">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Hapus Tahun?</flux:heading>
                    <flux:text class="mt-2">
                        Data tahun ini akan dihapus secara permanen.<br>
                        Tindakan ini tidak dapat dibatalkan.
                    </flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">Hapus Tahun</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

</flux:main>
