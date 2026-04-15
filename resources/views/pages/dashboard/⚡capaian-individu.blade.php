<?php

use Livewire\Component;
use Livewire\Attributes\Session;
use Livewire\Attributes\Computed;
use App\Models\Tahun;
use App\Models\Pasien;
use App\Models\Pegawai;
use Carbon\Carbon;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

new class extends Component {
    use WithPagination, WithoutUrlPagination;

    #[Session(key: 'tahun_session')]
    public $tahun_session;

    public string $search = '';
    public string $selectedPegawai = '';
    public string $statusFilter = '';
    public int $perPage = 10;

    public function mount()
    {
        if (!$this->tahun_session) {
            $activeTahun = Tahun::where('is_active', true)->first();
            $nowTahun = date('Y');
            $this->tahun_session = $activeTahun ? $activeTahun->tahun : $nowTahun;
        }

        if (!$this->isAdmin && auth()->user()?->pegawai_id) {
            $this->selectedPegawai = (string) auth()->user()->pegawai_id;
        }
    }

    #[Computed]
    public function tahun()
    {
        return Tahun::all();
    }

    #[Computed]
    public function isAdmin()
    {
        return auth()->user()?->role === 'admin';
    }

    #[Computed]
    public function daftarPegawai()
    {
        return Pegawai::orderBy('nama')->get();
    }

    private function parsedDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function baseScopeQuery()
    {
        $query = Pasien::query()->where('tahun', (string) $this->tahun_session);

        if ($this->isAdmin) {
            if ($this->selectedPegawai !== '') {
                $query->where('pegawai_id', (int) $this->selectedPegawai);
            }
        } else {
            $pegawaiId = auth()->user()?->pegawai_id;
            $query->where('pegawai_id', $pegawaiId ?: 0);
        }

        return $query;
    }

    private function tableScopeQuery()
    {
        $query = $this->baseScopeQuery();

        if ($this->statusFilter === 'sudah') {
            $query->whereNotNull('pegawai_id');
        }

        if ($this->statusFilter === 'belum') {
            $query->whereNull('pegawai_id');
        }

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('nomor_tiket', 'like', '%' . $search . '%')
                    ->orWhere('nik', 'like', '%' . $search . '%')
                    ->orWhere('nama', 'like', '%' . $search . '%')
                    ->orWhere('kel', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    #[Computed]
    public function summary()
    {
        $collection = $this->baseScopeQuery()->get(['register_date', 'kel', 'jenis_kelamin', 'pegawai_id']);

        $total = $collection->count();
        $sudah = $collection->whereNotNull('pegawai_id')->count();
        $belum = $collection->whereNull('pegawai_id')->count();

        $laki = 0;
        $perempuan = 0;

        foreach ($collection as $item) {
            $jk = strtolower((string) $item->jenis_kelamin);

            if (str_contains($jk, 'laki')) {
                $laki++;
            } elseif (str_contains($jk, 'perem') || str_contains($jk, 'wanita')) {
                $perempuan++;
            }
        }

        return [
            'total' => $total,
            'sudah' => $sudah,
            'belum' => $belum,
            'rata_per_bulan' => (int) round($sudah / 12),
            'laki' => $laki,
            'perempuan' => $perempuan,
        ];
    }

    #[Computed]
    public function pasien()
    {
        return $this->tableScopeQuery()->latest('id')->paginate($this->perPage);
    }

    #[Computed]
    public function chartOptions()
    {
        $collection = $this->baseScopeQuery()->get(['register_date', 'kel', 'pegawai_id']);

        $bulananLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $bulananValues = array_fill(0, 12, 0);

        foreach ($collection as $item) {
            $date = $this->parsedDate($item->register_date);

            if ($date && (int) $date->year === (int) $this->tahun_session) {
                $bulananValues[$date->month - 1]++;
            }
        }

        $desa = $collection
            ->groupBy(fn($item) => $item->kel ?: '-')
            ->map(
                fn($items, $label) => [
                    'label' => $label,
                    'value' => $items->count(),
                ],
            )
            ->sortByDesc('value')
            ->take(8)
            ->values();

        return [
            'capaianBulananIndividu' => [
                'backgroundColor' => 'transparent',
                'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'line']],
                'grid' => ['left' => 40, 'right' => 20, 'top' => 10, 'bottom' => 35],
                'xAxis' => [
                    'type' => 'category',
                    'data' => $bulananLabels,
                    'axisLabel' => ['color' => '#94a3b8', 'fontSize' => 10],
                    'axisLine' => ['lineStyle' => ['color' => '#e2e8f0']],
                    'splitLine' => ['show' => false],
                ],
                'yAxis' => [
                    'type' => 'value',
                    'axisLabel' => ['color' => '#94a3b8', 'fontSize' => 10],
                    'splitLine' => ['lineStyle' => ['color' => '#e2e8f0', 'type' => 'dashed']],
                ],
                'series' => [
                    [
                        'name' => 'Selesai CKG',
                        'type' => 'bar',
                        'barMaxWidth' => 26,
                        'itemStyle' => [
                            'color' => [
                                'type' => 'linear',
                                'x' => 0,
                                'y' => 0,
                                'x2' => 0,
                                'y2' => 1,
                                'colorStops' => [['offset' => 0, 'color' => '#6366f1'], ['offset' => 1, 'color' => '#3b82f6']],
                            ],
                            'borderRadius' => [6, 6, 0, 0],
                        ],
                        'data' => $bulananValues,
                    ],
                ],
            ],
            'statusPasienIndividu' => [
                'tooltip' => ['trigger' => 'item', 'formatter' => '{b}: {c} ({d}%)'],
                'legend' => [
                    'bottom' => '2%',
                    'left' => 'center',
                    'textStyle' => ['color' => '#94a3b8', 'fontSize' => 11],
                ],
                'backgroundColor' => 'transparent',
                'series' => [
                    [
                        'type' => 'pie',
                        'radius' => ['45%', '68%'],
                        'center' => ['50%', '45%'],
                        'label' => ['show' => true, 'formatter' => '{b}\n{d}%', 'fontSize' => 11, 'color' => '#94a3b8'],
                        'labelLine' => ['show' => true, 'length' => 10, 'length2' => 8],
                        'data' => [['value' => $this->summary['sudah'], 'name' => 'Sudah CKG', 'itemStyle' => ['color' => '#22c55e']], ['value' => $this->summary['belum'], 'name' => 'Belum CKG', 'itemStyle' => ['color' => '#f97316']], ['value' => 0, 'name' => 'Dalam Proses', 'itemStyle' => ['color' => '#eab308']]],
                    ],
                ],
            ],
            'desaPasienIndividu' => [
                'backgroundColor' => 'transparent',
                'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
                'grid' => ['left' => 90, 'right' => 30, 'top' => 10, 'bottom' => 30],
                'xAxis' => [
                    'type' => 'value',
                    'axisLabel' => ['color' => '#94a3b8', 'fontSize' => 10],
                    'splitLine' => ['lineStyle' => ['color' => '#e2e8f0', 'type' => 'dashed']],
                ],
                'yAxis' => [
                    'type' => 'category',
                    'data' => $desa->pluck('label')->all(),
                    'axisLabel' => ['color' => '#94a3b8', 'fontSize' => 11],
                    'axisLine' => ['lineStyle' => ['color' => '#e2e8f0']],
                ],
                'series' => [
                    [
                        'name' => 'Pasien',
                        'type' => 'bar',
                        'barMaxWidth' => 24,
                        'itemStyle' => [
                            'color' => '#14b8a6',
                            'borderRadius' => [0, 6, 6, 0],
                        ],
                        'label' => ['show' => true, 'position' => 'right', 'color' => '#94a3b8', 'fontSize' => 10],
                        'data' => $desa->pluck('value')->all(),
                    ],
                ],
            ],
        ];
    }

    private function dispatchChartRefresh(): void
    {
        $this->dispatch('capaian-individu-charts-updated', options: $this->chartOptions);
    }

    public function updatedTahunSession(): void
    {
        session()->put('tahun_session', $this->tahun_session);
        $this->resetPage();
        $this->dispatchChartRefresh();
    }

    public function updatedSelectedPegawai(): void
    {
        $this->resetPage();
        $this->dispatchChartRefresh();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->dispatchChartRefresh();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }
};
?>

<script>
    window._chartOpts = {
        ...(window._chartOpts ?? {}),
        ...@js($this->chartOptions),
    };

    const reinitCapaianCharts = () => {
        document.querySelectorAll('[data-individu-chart]').forEach((element) => {
            const key = element.getAttribute('data-individu-chart');

            if (key && typeof window._initChart === 'function') {
                window._initChart(element, key);
            }
        });
    };

    document.addEventListener('livewire:init', () => {
        Livewire.on('capaian-individu-charts-updated', ({
            options
        }) => {
            if (options) {
                window._chartOpts = {
                    ...(window._chartOpts ?? {}),
                    ...options,
                };
            }

            requestAnimationFrame(() => {
                reinitCapaianCharts();
            });
        });
    });
</script>

<flux:main>
    <div class="mb-6 flex items-center justify-between gap-3">
        <flux:heading size="xl" level="1">Capaian Individu</flux:heading>
        <flux:select wire:model.live="tahun_session" class="w-36" placeholder="Pilih tahun...">
            @foreach ($this->tahun as $tahun)
                <flux:select.option value="{{ $tahun->tahun }}">{{ $tahun->tahun }}
                    {{ $tahun->is_active ? '(Aktif)' : '' }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>
    <flux:separator variant="subtle" class="mb-6" />


    {{-- <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg bg-zinc-50 px-6 py-4 dark:bg-zinc-700">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:subheading>Total Pasien Saya</flux:subheading>
                    <flux:heading size="xl" class="mb-1">
                        {{ number_format($this->summary['total'], 0, ',', '.') }}</flux:heading>
                    <flux:text variant="subtle" class="text-sm">Terdaftar di tahun {{ $tahun_session }}</flux:text>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-md bg-white dark:bg-zinc-800">
                    <flux:icon icon="users" class="size-5 text-zinc-500" />
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-zinc-50 px-6 py-4 dark:bg-zinc-700">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:subheading>Sudah CKG</flux:subheading>
                    <flux:heading size="xl" class="mb-1">
                        {{ number_format($this->summary['sudah'], 0, ',', '.') }}</flux:heading>
                    <flux:text variant="subtle" class="text-sm">
                        {{ $this->summary['total'] > 0 ? round(($this->summary['sudah'] / $this->summary['total']) * 100) : 0 }}%
                        dari total pasien
                    </flux:text>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-md bg-white dark:bg-zinc-800">
                    <flux:icon icon="check-circle" class="size-5 text-zinc-500" />
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-zinc-50 px-6 py-4 dark:bg-zinc-700">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:subheading>Belum CKG</flux:subheading>
                    <flux:heading size="xl" class="mb-1">
                        {{ number_format($this->summary['belum'], 0, ',', '.') }}</flux:heading>
                    <flux:text variant="subtle" class="text-sm">Perlu tindak lanjut</flux:text>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-md bg-white dark:bg-zinc-800">
                    <flux:icon icon="clock" class="size-5 text-zinc-500" />
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-zinc-50 px-6 py-4 dark:bg-zinc-700">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:subheading>Rata-rata per Bulan</flux:subheading>
                    <flux:heading size="xl" class="mb-1">
                        {{ number_format($this->summary['rata_per_bulan'], 0, ',', '.') }}</flux:heading>
                    <flux:text variant="subtle" class="text-sm">Pasien selesai CKG / bulan</flux:text>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-md bg-white dark:bg-zinc-800">
                    <flux:icon icon="chart-bar" class="size-5 text-zinc-500" />
                </div>
            </div>
        </div>
    </div> --}}

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mb-6">
        <div
            class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Total Pasien
                    Saya</p>
                <p class="mt-1 text-3xl font-bold text-zinc-800 dark:text-zinc-100">
                    23</p>
                <p class="mt-1 text-xs text-zinc-400">Jumlah pasien tahun {{ $tahun_session }}</p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
                <flux:icon.users class="text-blue-500" variant="outline" />
            </div>
        </div>
        {{-- <div
            class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Belum Diklaim
                </p>
                <p class="mt-1 text-3xl font-bold text-zinc-800">
                    </p>
                <p class="mt-1 text-xs text-zinc-400">Belum terhubung ke pegawai</p>
            </div>
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-50 dark:bg-orange-900/30">
                <flux:icon.clock class="text-orange-500" variant="outline" />
            </div>
        </div>
        <div
            class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Sudah Diklaim
                </p>
                <p class="mt-1 text-3xl font-bold text-zinc-800">
                    </p>
                <p class="mt-1 text-xs text-zinc-400">Sudah terhubung ke pegawai</p>
            </div>
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/30">
                <flux:icon.check-circle class="text-green-500" variant="outline" />
            </div>
        </div> --}}
        <div
            class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Pasien Saya Bulan Ini
                </p>
                <p class="mt-1 text-3xl font-bold text-zinc-800">
                    23</p>
                <p class="mt-1 text-xs text-zinc-400">Jumlah pasien bulan {{ now()->locale('id')->translatedFormat('F Y') }}</p>
            </div>
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                <flux:icon.chart-bar-square class="text-indigo-500" variant="outline" />
            </div>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="mb-1 text-sm font-semibold text-zinc-700 dark:text-zinc-200">Tren Capaian per Bulan</p>
            <p class="mb-4 text-xs text-zinc-400">Jumlah pasien selesai CKG oleh pegawai terpilih</p>
            <div data-individu-chart="capaianBulananIndividu" x-data x-init="window._initChart($el, 'capaianBulananIndividu')" class="h-72"></div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="mb-1 text-sm font-semibold text-zinc-700 dark:text-zinc-200">Komposisi Status Pasien</p>
            <p class="mb-4 text-xs text-zinc-400">Perbandingan pasien selesai, belum, dan proses</p>
            <div data-individu-chart="statusPasienIndividu" x-data x-init="window._initChart($el, 'statusPasienIndividu')" class="h-72"></div>
        </div>
    </div>

    <div class="mb-6 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
        <p class="mb-1 text-sm font-semibold text-zinc-700 dark:text-zinc-200">Distribusi Pasien per Desa</p>
        <p class="mb-4 text-xs text-zinc-400">Menampilkan sebaran domisili pasien yang ditangani</p>
        <div data-individu-chart="desaPasienIndividu" x-data x-init="window._initChart($el, 'desaPasienIndividu')" class="h-72"></div>
    </div>

    <div class="mb-3 flex items-center gap-2">
        <flux:heading size="lg">Daftar Pasien</flux:heading>
        <flux:spacer />
        <flux:button variant="outline" icon="arrow-down-tray">Export Excel</flux:button>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        <flux:input wire:model.live.debounce.300ms="search" class="w-full max-w-sm" icon="magnifying-glass"
            placeholder="Cari nama / NIK pasien..." />

        @if ($this->isAdmin)
            <flux:select wire:model.live="selectedPegawai" class="w-full md:w-60" placeholder="Semua pegawai">
                @foreach ($this->daftarPegawai as $pegawai)
                    <flux:select.option value="{{ $pegawai->id }}">{{ $pegawai->nama }}</flux:select.option>
                @endforeach
            </flux:select>
        @else
            <flux:input class="w-full md:w-60" disabled value="{{ auth()->user()?->name }}" />
        @endif

        <flux:select wire:model.live="statusFilter" class="w-full md:w-48" placeholder="Semua status">
            <flux:select.option value="sudah">Sudah CKG</flux:select.option>
            <flux:select.option value="belum">Belum CKG</flux:select.option>
        </flux:select>

        <flux:spacer />

        <div class="flex items-center gap-2">
            <flux:text>Tampilkan</flux:text>
            <flux:select wire:model.live="perPage" class="w-20" placeholder="10">
                <flux:select.option value="10">10</flux:select.option>
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
                <flux:select.option value="100">100</flux:select.option>
            </flux:select>
            <flux:text>data</flux:text>
        </div>
    </div>

    <flux:table :paginate="$this->pasien">
        <flux:table.columns>
            <flux:table.column>No</flux:table.column>
            <flux:table.column>No Tiket</flux:table.column>
            <flux:table.column>NIK</flux:table.column>
            <flux:table.column>Nama Pasien</flux:table.column>
            <flux:table.column>Desa</flux:table.column>
            <flux:table.column>Tanggal CKG</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->pasien as $index => $pasien)
                <flux:table.row>
                    <flux:table.cell>{{ $this->pasien->firstItem() + $index }}</flux:table.cell>
                    <flux:table.cell>{{ $pasien->nomor_tiket }}</flux:table.cell>
                    <flux:table.cell>{{ $pasien->nik }}</flux:table.cell>
                    <flux:table.cell variant="strong">{{ $pasien->nama }}</flux:table.cell>
                    <flux:table.cell>{{ $pasien->kel }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $this->parsedDate($pasien->register_date)?->format('Y-m-d') ?? '-' }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500">
                        Tidak ada data pasien untuk filter saat ini.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

</flux:main>
