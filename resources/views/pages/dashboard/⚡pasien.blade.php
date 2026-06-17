<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Livewire\Attributes\Computed;
use App\Models\Pasien;
use App\Models\Pegawai;
use App\Models\Tahun;
use Carbon\Carbon;
use Livewire\Attributes\Session;

new class extends Component {
    use WithPagination, WithFileUploads;

    #[Session(key: 'tahun_session')]
    public $tahun_session;

    public int $perPage = 10;
    public $search = '';
    public $filterTahun = '';
    public $filterStatus = '';
    public $filterDesa = '';
    public $filterTipe = '';
    public $selectedPasienIds = [];
    public $selectedPasienId;
    public $selectedPegawaiId;
    public $excelFile;

    public function mount()
    {
        if (!$this->tahun_session) {
            $activeTahun = Tahun::where('is_active', true)->first();
            $nowTahun = date('Y');
            $this->tahun_session = $activeTahun ? $activeTahun->tahun : $nowTahun;
        }
    }

    #[Computed]
    public function tahun()
    {
        return Tahun::all();
    }

    #[Computed]
    public function desa()
    {
        return Pasien::whereIn('tipe', ['umum', 'sekolah'])
            ->distinct('kel')
            ->pluck('kel');
    }

    #[Computed]
    public function pegawai()
    {
        return Pegawai::select('id', 'nama')->get();
    }

    #[Computed]
    public function pasien()
    {
        return Pasien::query()
            ->when($this->filterTipe !== '', fn($q) => $q->where('tipe', $this->filterTipe))
            ->when($this->filterTipe === '', fn($q) => $q->whereIn('tipe', ['umum', 'sekolah']))
            ->with('pegawai:id,nama')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->where('nomor_tiket', 'like', '%' . $this->search . '%')
                        ->orWhere('nik', 'like', '%' . $this->search . '%')
                        ->orWhere('nama', 'like', '%' . $this->search . '%');
                });
            })
            // ->when($this->filterTahun !== '', fn($query) => $query->where('tahun', $this->filterTahun))
            ->when($this->tahun_session !== '', fn($query) => $query->where('tahun', $this->tahun_session))
            ->when($this->filterStatus === 'sudah', fn($query) => $query->whereNotNull('pegawai_id'))
            ->when($this->filterStatus === 'belum', fn($query) => $query->whereNull('pegawai_id'))
            ->when($this->filterDesa !== '', fn($query) => $query->where('kel', $this->filterDesa))
            ->latest('register_date')
            ->paginate($this->perPage);
    }

    public function ambilPasien($id)
    {
        if (Auth::user()->role === 'admin') {
            $this->selectedPasienId = $id;
            $this->modal('pick-pasien')->show();
            return;
        }
        $pegawai_id = Auth::user()->pegawai_id;
        $pasien = Pasien::find($id);
        if ($pasien && !$pasien->pegawai_id) {
            $pasien->update(['pegawai_id' => $pegawai_id]);
            flash()->use('theme.ruby')->option('position', 'bottom-right')->success('Pasien berhasil diambil.');
        } else {
            flash()->use('theme.ruby')->option('position', 'bottom-right')->error('Pasien tidak ditemukan atau sudah diambil.');
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
            flash()->use('theme.ruby')->option('position', 'bottom-right')->error('Beberapa pasien yang dipilih sudah diambil');
            return;
        }
        Pasien::whereIn('id', $this->selectedPasienIds)->update(['pegawai_id' => $pegawai_id]);

        flash()
            ->use('theme.ruby')
            ->option('position', 'bottom-right')
            ->success(count($this->selectedPasienIds) . ' pasien berhasil diambil.');
        $this->selectedPasienIds = [];
    }

    public function batalAmbilPasien($id)
    {
        $pasien = Pasien::find($id);
        if ($pasien && ($pasien->pegawai_id === Auth::user()->pegawai_id || Auth::user()->role === 'admin')) {
            $pasien->update(['pegawai_id' => null]);
            flash()->use('theme.ruby')->option('position', 'bottom-right')->success('Pengambilan pasien dibatalkan.');
        } else {
            flash()->use('theme.ruby')->option('position', 'bottom-right')->error('Pasien tidak ditemukan atau tidak dapat dibatalkan.');
        }
    }

    public function ambilUntukPegawai()
    {
        $pasienId = $this->selectedPasienId;
        $pegawaiId = $this->selectedPegawaiId;
        $pasien = Pasien::find($pasienId);
        if ($pasien && !$pasien->pegawai_id) {
            $pasien->update(['pegawai_id' => $pegawaiId]);
            Flux::modals()->close();
            flash()->use('theme.ruby')->option('position', 'bottom-right')->success('Pasien berhasil diambil untuk pegawai.');
        } else {
            Flux::modals()->close();
            flash()->use('theme.ruby')->option('position', 'bottom-right')->error('Pasien tidak ditemukan atau sudah diambil.');
        }
    }

    #[Computed]
    public function jumlahPasien()
    {
        return Pasien::whereIn('tipe', ['umum', 'sekolah'])
            ->where('tahun', $this->tahun_session)
            ->count();
    }

    #[Computed]
    public function jumlahPasienBelumDiambil()
    {
        return Pasien::whereIn('tipe', ['umum', 'sekolah'])
            ->where('tahun', $this->tahun_session)
            ->whereNull('pegawai_id')
            ->count();
    }

    #[Computed]
    public function jumlahPasienSudahDiambil()
    {
        return Pasien::whereIn('tipe', ['umum', 'sekolah'])
            ->where('tahun', $this->tahun_session)
            ->whereNotNull('pegawai_id')
            ->count();
    }

    public function importFromExcel()
    {
        $this->validate([
            'excelFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $pegawai_id = Auth::user()->pegawai_id;

        if (!$pegawai_id && Auth::user()->role !== 'admin') {
            flash()->use('theme.ruby')->option('position', 'bottom-right')->error('Akun Anda tidak terhubung ke data pegawai.');
            return;
        }

        try {
            $spreadsheet = IOFactory::load($this->excelFile->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Find NIK column in header row
            $nikColIndex = null;
            if (!empty($rows)) {
                $header = array_map(fn($cell) => strtolower(trim((string) $cell)), $rows[0]);
                foreach ($header as $index => $col) {
                    if ($col === 'nik') {
                        $nikColIndex = $index;
                        break;
                    }
                }
            }

            if ($nikColIndex === null) {
                flash()->use('theme.ruby')->option('position', 'bottom-right')->error('Kolom "NIK" tidak ditemukan di file Excel.');
                return;
            }

            $niks = [];
            for ($i = 1; $i < count($rows); $i++) {
                $nik = trim((string) ($rows[$i][$nikColIndex] ?? ''));
                if ($nik !== '') {
                    $niks[] = $nik;
                }
            }

            if (empty($niks)) {
                flash()->use('theme.ruby')->option('position', 'bottom-right')->warning('Tidak ada data NIK yang ditemukan di file.');
                return;
            }

            $claimed = 0;
            $notFound = 0;
            $alreadyMine = 0;
            $claimedByOthers = 0;

            foreach ($niks as $nik) {
                $pasien = Pasien::whereIn('tipe', ['umum', 'sekolah'])
                    ->where('tahun', $this->tahun_session)
                    ->where('nik', $nik)
                    ->first();

                if (!$pasien) {
                    $notFound++;
                    continue;
                }

                if ($pasien->pegawai_id) {
                    if ($pasien->pegawai_id == $pegawai_id) {
                        $alreadyMine++;
                    } else {
                        $claimedByOthers++;
                    }
                    continue;
                }

                $pasien->update(['pegawai_id' => $pegawai_id]);
                $claimed++;
            }

            Flux::modals()->close();

            $msg = "Impor selesai. Berhasil diklaim: {$claimed}";
            if ($alreadyMine > 0) {
                $msg .= ", Sudah Anda klaim sebelumnya: {$alreadyMine}";
            }
            if ($claimedByOthers > 0) {
                $msg .= ", Sudah diklaim orang lain: {$claimedByOthers}";
            }
            if ($notFound > 0) {
                $msg .= ", NIK tidak ditemukan: {$notFound}";
            }

            if ($claimedByOthers > 0) {
                flash()->use('theme.ruby')->option('position', 'bottom-right')->warning($msg);
            } else {
                flash()->use('theme.ruby')->option('position', 'bottom-right')->success($msg);
            }
        } catch (\Throwable $e) {
            flash()
                ->use('theme.ruby')
                ->option('position', 'bottom-right')
                ->error('Gagal memproses file: ' . $e->getMessage());
        } finally {
            $this->reset('excelFile');
        }
    }
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTahun(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDesa(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTipe(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['filterTahun', 'filterStatus', 'filterDesa', 'filterTipe']);
        $this->resetPage();
    }
};
?>

<flux:main>
    <div class="mb-6 flex items-center justify-between gap-3">
        <flux:heading size="xl" level="1">Daftar Pasien</flux:heading>

        <flux:select wire:model.live="tahun_session" class="w-36" placeholder="Pilih tahun...">
            @foreach ($this->tahun as $tahun)
                <flux:select.option value="{{ $tahun->tahun }}">{{ $tahun->tahun }}
                    {{ $tahun->is_active ? '(Aktif)' : '' }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>
    {{-- <flux:text class="mt-2 mb-6 text-base">Here's what's new today</flux:text> --}}
    <flux:separator variant="subtle" class="mb-2" />

    {{-- <div class="my-3 flex flex-wrap items-center gap-3">
        <flux:text class="text-xs" variant="subtle">Update terakhir: 2023-10-01 08:30:00</flux:text>
    </div> --}}

    {{-- <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-lg bg-zinc-50 px-6 py-4 dark:bg-zinc-700">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:subheading>Total Pasien</flux:subheading>
                    <flux:heading size="xl" class="mb-1">{{ $this->jumlahPasien }}</flux:heading>
                    <flux:text variant="subtle" class="text-sm">Jumlah seluruh data pasien</flux:text>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-md bg-white dark:bg-zinc-800">
                    <flux:icon icon="users" class="size-5 text-zinc-500" />
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-zinc-50 px-6 py-4 dark:bg-zinc-700">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:subheading>Pasien Sudah Diambil</flux:subheading>
                    <flux:heading size="xl" class="mb-1">{{ $this->jumlahPasienSudahDiambil }}</flux:heading>
                    <flux:text variant="subtle" class="text-sm">Sudah terhubung ke pegawai</flux:text>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-md bg-white dark:bg-zinc-800">
                    <flux:icon icon="user-plus" class="size-5 text-zinc-500" />
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-zinc-50 px-6 py-4 dark:bg-zinc-700">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:subheading>Pasien Belum Diambil</flux:subheading>
                    <flux:heading size="xl" class="mb-1">{{ $this->jumlahPasienBelumDiambil }}</flux:heading>
                    <flux:text variant="subtle" class="text-sm">Belum terhubung ke pegawai</flux:text>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-md bg-white dark:bg-zinc-800">
                    <flux:icon icon="user-minus" class="size-5 text-zinc-500" />
                </div>
            </div>
        </div>
    </div> --}}

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div
            class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Total Pasien</p>
                <p class="mt-1 text-3xl font-bold text-zinc-800 dark:text-zinc-100">
                    {{ $this->jumlahPasien }}
                </p>
                <p class="mt-1 text-xs text-zinc-400">Jumlah seluruh data pasien</p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
                <flux:icon.users class="text-blue-500" variant="outline" />
            </div>
        </div>
        <div
            class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Pasien Sudah
                    Diambil</p>
                <p class="mt-1 text-3xl font-bold text-zinc-800 dark:text-zinc-100">
                    {{ $this->jumlahPasienSudahDiambil }}
                </p>
                <p class="mt-1 text-xs text-zinc-400">Sudah terhubung ke pegawai</p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
                <flux:icon.users class="text-blue-500" variant="outline" />
            </div>
        </div>
        <div
            class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Pasien Belum
                    Diambil</p>
                <p class="mt-1 text-3xl font-bold text-zinc-800 dark:text-zinc-100">
                    {{ $this->jumlahPasienBelumDiambil }}
                </p>
                <p class="mt-1 text-xs text-zinc-400">Belum terhubung ke pegawai</p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
                <flux:icon.users class="text-blue-500" variant="outline" />
            </div>
        </div>
    </div>

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
            placeholder="Cari No Tiket / NIK / Nama" />

        <div class="flex shrink-0 items-center gap-2">
            <flux:modal.trigger name="filter-pasien">
                <flux:button variant="ghost" icon="funnel">Filter</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="import-excel">
                <flux:button variant="primary" color="sky" icon="document-text">Klaim dari Excel</flux:button>
            </flux:modal.trigger>
            <flux:button wire:click="ambilMultiPasien" x-cloak x-show="$wire.selectedPasienIds.length > 0"
                icon="users">Ambil <span x-text="$wire.selectedPasienIds.length"></span> Pasien</flux:button>
        </div>
    </div>

    <flux:modal name="filter-pasien" class="md:w-136">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Filter Daftar Pasien</flux:heading>
                <flux:text class="mt-1" variant="subtle">Sesuaikan data pasien yang ditampilkan</flux:text>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">

                <flux:select wire:model.live="filterStatus" placeholder="Status Pengambilan">
                    <flux:select.option value="belum">Belum Diambil</flux:select.option>
                    <flux:select.option value="sudah">Sudah Diambil</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="filterDesa" placeholder="Filter Desa">
                    <flux:select.option value="Gador">Gador</flux:select.option>
                    <flux:select.option value="Karanganom">Karanganom</flux:select.option>
                    <flux:select.option value="Pakis">Pakis</flux:select.option>
                    <flux:select.option value="Kamulan">Kamulan</flux:select.option>
                    <flux:select.option value="Sumberejo">Sumberejo</flux:select.option>
                    <flux:select.option value="Sumbergayam">Sumbergayam</flux:select.option>
                    <flux:select.option value="Baruharjo">Baruharjo</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="filterTipe" placeholder="Jenis Pasien">
                    <flux:select.option value="umum">Umum</flux:select.option>
                    <flux:select.option value="sekolah">Sekolah</flux:select.option>
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

    <flux:table :paginate="$this->pasien">
        <flux:table.columns>
            <flux:table.column></flux:table.column>
            <flux:table.column>No Tiket</flux:table.column>
            <flux:table.column>NIK</flux:table.column>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Gender</flux:table.column>
            <flux:table.column>Alamat</flux:table.column>
            <flux:table.column>Tgl Daftar</flux:table.column>
            <flux:table.column></flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->pasien as $pasien)
                <flux:table.row>
                    <flux:table.cell class="pr-2">
                        @if (!$pasien->pegawai_id)
                            <flux:checkbox wire:model="selectedPasienIds" :value="$pasien->id"
                                wire:key="pasien-{{ $pasien->id }}" />
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $pasien->nomor_tiket }}</flux:table.cell>
                    <flux:table.cell>{{ $pasien->nik }}</flux:table.cell>
                    <flux:table.cell>{{ $pasien->nama }}</flux:table.cell>
                    <flux:table.cell>{{ $pasien->jenis_kelamin == 'LAKI-LAKI' ? 'L' : 'P' }}</flux:table.cell>
                    <flux:table.cell>{{ $pasien->kel }}</flux:table.cell>
                    <flux:table.cell>
                        {{ Carbon::parse($pasien->register_date)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y | H:i:s') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($pasien->pegawai_id === Auth::user()->pegawai_id && $pasien->pegawai_id !== null && Auth::user()->role !== 'admin')
                            <flux:button wire:click="batalAmbilPasien({{ $pasien->id }})" variant="primary"
                                color="red" icon="x-circle">Batal Ambil</flux:button>
                        @elseif ($pasien->pegawai_id !== Auth::user()->pegawai_id && $pasien->pegawai_id !== null && Auth::user()->role !== 'admin')
                            <flux:button disabled variant="subtle" icon="lock-closed">{{ $pasien->pegawai->nama }}
                            </flux:button>
                        @elseif (Auth::user()->role === 'admin' && $pasien->pegawai_id !== null)
                            <flux:button disabled variant="subtle" icon="lock-closed">{{ $pasien->pegawai->nama }}
                            </flux:button>
                            <flux:button wire:click="batalAmbilPasien({{ $pasien->id }})" variant="primary"
                                color="red" icon="x-circle">Batal Ambil</flux:button>
                        @else
                            <flux:button wire:click="ambilPasien({{ $pasien->id }})" icon="user-plus"
                                variant="primary" color="emerald">Ambil
                            </flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center text-zinc-500">
                        Tidak ada data pasien.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse

        </flux:table.rows>
    </flux:table>

    @if (Auth::user()->role === 'admin')
        <flux:modal name="pick-pasien" class="min-w-[22rem]">
            <form wire:submit="ambilUntukPegawai({{ $selectedPasienId }}, {{ $selectedPegawaiId }})">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Ambil Pasien untuk Pegawai</flux:heading>
                        <flux:text class="mt-1" variant="subtle">Pilih pegawai yang akan mengambil pasien
                        </flux:text>
                    </div>
                    {{-- <flux:input wire:model="" label="" placeholder="" /> --}}
                    <flux:select wire:model.live="selectedPegawaiId" label="Pilih Pegawai"
                        placeholder="Pilih pegawai...">
                        @foreach ($this->pegawai as $pegawai)
                            <flux:select.option value="{{ $pegawai->id }}">{{ $pegawai->nama }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="tahun" />
                    <div class="flex justify-end gap-2 mt-6">
                        <flux:modal.close>
                            <flux:button variant="ghost">Batal</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary">Ambil</flux:button>
                    </div>
                </div>
            </form>
        </flux:modal>
    @endif


    <flux:modal name="import-excel" class="md:w-[28rem]">
        <form wire:submit="importFromExcel">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Impor Pasien dari Excel</flux:heading>
                    <flux:text class="mt-1" variant="subtle">Upload file Excel (.xlsx/.xls/.csv) yang berisi kolom
                        NIK untuk klaim pasien.</flux:text>
                </div>

                <div>
                    <flux:label>File Excel</flux:label>
                    <input type="file" wire:model="excelFile" accept=".xlsx,.xls,.csv"
                        class="mt-1 block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-300" />
                    @error('excelFile')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-zinc-400">File harus memiliki kolom "NIK" di baris pertama.</p>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="importFromExcel">Impor & Klaim</span>
                        <span wire:loading wire:target="importFromExcel">Memproses...</span>
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</flux:main>
