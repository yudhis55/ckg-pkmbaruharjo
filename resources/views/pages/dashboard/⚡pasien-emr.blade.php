<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\Pasien;
use App\Models\Pegawai;

new class extends Component
{
    use WithPagination;

    public int $perPage = 10;
    public string $search = '';
    public string $filterDesa = '';
    public string $filterKecamatan = '';
    public string $filterStatus = '';
    public $selectedPasienIds = [];
    public $selectedPasienId;
    public $selectedPegawaiId;

    #[Computed]
    public function pasienEmr()
    {
        return Pasien::query()
            ->where('tipe', 'emr')
            ->with('pegawai:id,nama')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->where('nik', 'like', '%' . $this->search . '%')
                        ->orWhere('nama', 'like', '%' . $this->search . '%')
                        ->orWhere('no_wa', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterDesa !== '', fn($q) => $q->where('kel', $this->filterDesa))
            ->when($this->filterKecamatan !== '', fn($q) => $q->where('kec', $this->filterKecamatan))
            ->when($this->filterStatus === 'sudah', fn($q) => $q->whereNotNull('pegawai_id'))
            ->when($this->filterStatus === 'belum', fn($q) => $q->whereNull('pegawai_id'))
            ->latest('id')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function desaList()
    {
        return Pasien::where('tipe', 'emr')
            ->whereNotNull('kel')
            ->where('kel', '!=', '')
            ->distinct()
            ->orderBy('kel')
            ->pluck('kel');
    }

    #[Computed]
    public function kecamatanList()
    {
        return Pasien::where('tipe', 'emr')
            ->whereNotNull('kec')
            ->where('kec', '!=', '')
            ->distinct()
            ->orderBy('kec')
            ->pluck('kec');
    }

    #[Computed]
    public function pegawai()
    {
        return Pegawai::select('id', 'nama')->get();
    }

    #[Computed]
    public function totalPasienEmr()
    {
        return Pasien::where('tipe', 'emr')->count();
    }

    #[Computed]
    public function totalPasienBpjs()
    {
        return Pasien::where('tipe', 'emr')->where('faskes', '!=', '')->whereNotNull('faskes')->count();
    }

    #[Computed]
    public function totalBelumDiklaim()
    {
        return Pasien::where('tipe', 'emr')->whereNull('pegawai_id')->count();
    }

    #[Computed]
    public function totalSudahDiklaim()
    {
        return Pasien::where('tipe', 'emr')->whereNotNull('pegawai_id')->count();
    }

    public function ambilPasien($id)
    {
        if (Auth::user()->role === 'admin') {
            $this->selectedPasienId = $id;
            $this->modal('pick-pasien-emr')->show();
            return;
        }

        $pegawai_id = Auth::user()->pegawai_id;
        $pasien = Pasien::where('tipe', 'emr')->find($id);

        if ($pasien && !$pasien->pegawai_id) {
            $pasien->update(['pegawai_id' => $pegawai_id]);
            flash()->use('theme.ruby')->option('position', 'bottom-right')->success('Pasien EMR berhasil diklaim.');
        } else {
            flash()->use('theme.ruby')->option('position', 'bottom-right')->error('Pasien tidak ditemukan atau sudah diklaim.');
        }
    }

    public function ambilMultiPasien()
    {
        if (empty($this->selectedPasienIds)) {
            flash()->use('theme.ruby')->option('position', 'bottom-right')->warning('Tidak ada pasien yang dipilih.');
            return;
        }

        $pegawai_id = Auth::user()->pegawai_id;

        if (Pasien::whereIn('id', $this->selectedPasienIds)->whereNotNull('pegawai_id')->exists()) {
            flash()->use('theme.ruby')->option('position', 'bottom-right')->error('Beberapa pasien yang dipilih sudah diklaim.');
            return;
        }

        Pasien::whereIn('id', $this->selectedPasienIds)->update(['pegawai_id' => $pegawai_id]);
        flash()->use('theme.ruby')->option('position', 'bottom-right')
            ->success(count($this->selectedPasienIds) . ' pasien EMR berhasil diklaim.');
        $this->selectedPasienIds = [];
    }

    public function batalAmbilPasien($id)
    {
        $pasien = Pasien::where('tipe', 'emr')->find($id);
        if ($pasien && ($pasien->pegawai_id === Auth::user()->pegawai_id || Auth::user()->role === 'admin')) {
            $pasien->update(['pegawai_id' => null]);
            flash()->use('theme.ruby')->option('position', 'bottom-right')->success('Klaim pasien EMR dibatalkan.');
        } else {
            flash()->use('theme.ruby')->option('position', 'bottom-right')->error('Pasien tidak ditemukan atau tidak dapat dibatalkan.');
        }
    }

    public function ambilUntukPegawai()
    {
        $pasienId = $this->selectedPasienId;
        $pegawaiId = $this->selectedPegawaiId;
        $pasien = Pasien::where('tipe', 'emr')->find($pasienId);

        if ($pasien && !$pasien->pegawai_id) {
            $pasien->update(['pegawai_id' => $pegawaiId]);
            Flux::modals()->close();
            flash()->use('theme.ruby')->option('position', 'bottom-right')->success('Pasien EMR berhasil diklaim untuk pegawai.');
        } else {
            Flux::modals()->close();
            flash()->use('theme.ruby')->option('position', 'bottom-right')->error('Pasien tidak ditemukan atau sudah diklaim.');
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterDesa', 'filterKecamatan', 'filterStatus']);
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDesa(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKecamatan(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }
};
?>

<flux:main>
    <div class="mb-6 flex items-center justify-between gap-3">
        <flux:heading size="xl" level="1">Daftar Pasien EMR</flux:heading>
    </div>
    <flux:separator variant="subtle" class="mb-6" />

    {{-- Stats Cards --}}
    <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Total Pasien EMR</p>
                <p class="mt-1 text-3xl font-bold text-zinc-800 dark:text-zinc-100">{{ $this->totalPasienEmr }}</p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
                <flux:icon.users class="text-blue-500" variant="outline" />
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Dengan Faskes BPJS</p>
                <p class="mt-1 text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $this->totalPasienBpjs }}</p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
                <flux:icon.shield-check class="text-blue-500" variant="outline" />
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Belum Diklaim</p>
                <p class="mt-1 text-3xl font-bold text-orange-600 dark:text-orange-400">{{ $this->totalBelumDiklaim }}</p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-50 dark:bg-orange-900/30">
                <flux:icon.clock class="text-orange-500" variant="outline" />
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Sudah Diklaim</p>
                <p class="mt-1 text-3xl font-bold text-green-600 dark:text-green-400">{{ $this->totalSudahDiklaim }}</p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/30">
                <flux:icon.check-circle class="text-green-500" variant="outline" />
            </div>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="mb-3 flex items-center gap-2">
        <div class="flex shrink-0 items-center">
            <flux:text>Tampilkan</flux:text>
            <flux:select wire:model.live="perPage" class="mx-2 w-20" placeholder="10">
                <flux:select.option value="10">10</flux:select.option>
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
                <flux:select.option value="100">100</flux:select.option>
            </flux:select>
            <flux:text>data</flux:text>
        </div>

        <flux:input class="min-w-0 flex-1" wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Cari NIK / Nama / No HP" />

        <div class="flex shrink-0 items-center gap-2">
            <flux:modal.trigger name="filter-emr">
                <flux:button variant="ghost" icon="funnel">Filter</flux:button>
            </flux:modal.trigger>
            <flux:button wire:click="ambilMultiPasien" x-cloak x-show="$wire.selectedPasienIds.length > 0"
                icon="users">Klaim <span x-text="$wire.selectedPasienIds.length"></span> Pasien</flux:button>
        </div>
    </div>

    <flux:modal name="filter-emr" class="md:w-136">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Filter Pasien EMR</flux:heading>
                <flux:text class="mt-1" variant="subtle">Sesuaikan data pasien EMR yang ditampilkan</flux:text>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <flux:select wire:model.live="filterStatus" placeholder="Status Klaim">
                    <flux:select.option value="belum">Belum Diklaim</flux:select.option>
                    <flux:select.option value="sudah">Sudah Diklaim</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="filterDesa" placeholder="Filter Desa">
                    @foreach ($this->desaList as $desa)
                        <flux:select.option value="{{ $desa }}">{{ $desa }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="filterKecamatan" placeholder="Filter Kecamatan">
                    @foreach ($this->kecamatanList as $kecamatan)
                        <flux:select.option value="{{ $kecamatan }}">{{ $kecamatan }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center justify-end gap-2">
                <flux:button wire:click="resetFilters" variant="subtle" icon="arrow-path">Reset Filter</flux:button>
                <flux:modal.close>
                    <flux:button variant="primary">Tutup</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- Table --}}
    <flux:table :paginate="$this->pasienEmr">
        <flux:table.columns>
            <flux:table.column></flux:table.column>
            <flux:table.column>NIK</flux:table.column>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>L/P</flux:table.column>
            <flux:table.column>Tgl Lahir</flux:table.column>
            <flux:table.column>Alamat</flux:table.column>
            <flux:table.column>Kecamatan</flux:table.column>
            <flux:table.column>No HP/WA</flux:table.column>
            <flux:table.column>Faskes BPJS</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->pasienEmr as $pasien)
                <flux:table.row wire:key="{{ $pasien->id }}">
                    <flux:table.cell class="pr-2">
                        @if (!$pasien->pegawai_id)
                            <flux:checkbox wire:model="selectedPasienIds" :value="$pasien->id"
                                wire:key="emr-{{ $pasien->id }}" />
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $pasien->nik ?: '-' }}</flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $pasien->nama ?: '-' }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $pasien->jenis_kelamin === 'LAKI-LAKI' ? 'L' : ($pasien->jenis_kelamin === 'PEREMPUAN' ? 'P' : '-') }}
                    </flux:table.cell>
                    <flux:table.cell class="text-xs">
                        {{ $pasien->tgl_lahir ? \Carbon\Carbon::parse($pasien->tgl_lahir)->translatedFormat('d M Y') : '-' }}
                    </flux:table.cell>
                    <flux:table.cell class="text-xs">
                        {{ $pasien->alamat ?: '-' }}
                        @if ($pasien->kel)
                            <span class="block text-zinc-400">{{ $pasien->kel }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-xs">{{ $pasien->kec ?: '-' }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $pasien->no_wa ?: '-' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($pasien->faskes)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200">
                                {{ $pasien->faskes }}
                            </span>
                        @else
                            <span class="text-zinc-400">-</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($pasien->pegawai_id === Auth::user()->pegawai_id && $pasien->pegawai_id !== null && Auth::user()->role !== 'admin')
                            <flux:button wire:click="batalAmbilPasien({{ $pasien->id }})" variant="primary"
                                color="red" icon="x-circle" size="sm">Batal</flux:button>
                        @elseif ($pasien->pegawai_id !== null && Auth::user()->role !== 'admin')
                            <flux:button disabled variant="subtle" icon="lock-closed" size="sm">{{ $pasien->pegawai->nama }}</flux:button>
                        @elseif (Auth::user()->role === 'admin' && $pasien->pegawai_id !== null)
                            <flux:button disabled variant="subtle" icon="lock-closed" size="sm">{{ $pasien->pegawai->nama }}</flux:button>
                            <flux:button wire:click="batalAmbilPasien({{ $pasien->id }})" variant="primary"
                                color="red" icon="x-circle" size="sm">Batal</flux:button>
                        @else
                            <flux:button wire:click="ambilPasien({{ $pasien->id }})" icon="user-plus"
                                variant="primary" color="emerald" size="sm">Klaim</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="10" class="text-center text-zinc-500 py-8">
                        Belum ada pasien EMR. Admin perlu melakukan sinkronisasi terlebih dahulu.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if (Auth::user()->role === 'admin')
        <flux:modal name="pick-pasien-emr" class="min-w-[22rem]">
            <form wire:submit="ambilUntukPegawai">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Klaim Pasien EMR untuk Pegawai</flux:heading>
                        <flux:text class="mt-1" variant="subtle">Pilih pegawai yang akan mengklaim pasien EMR ini</flux:text>
                    </div>
                    <flux:select wire:model.live="selectedPegawaiId" label="Pilih Pegawai" placeholder="Pilih pegawai...">
                        @foreach ($this->pegawai as $pegawai)
                            <flux:select.option value="{{ $pegawai->id }}">{{ $pegawai->nama }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div class="flex justify-end gap-2 mt-6">
                        <flux:modal.close>
                            <flux:button variant="ghost">Batal</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary">Klaim</flux:button>
                    </div>
                </div>
            </form>
        </flux:modal>
    @endif
</flux:main>