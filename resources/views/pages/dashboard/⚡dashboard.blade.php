<?php

use Livewire\Component;
use Livewire\Attributes\Session;
use App\Models\Tahun;
use App\Models\Pegawai;
use App\Models\Pasien;
use Carbon\Carbon;
use Livewire\Attributes\Computed;

new class extends Component {
    #[Session(key: 'tahun_session')]
    public $tahun_session;

    public string $bulan = '';
    public string $statusKunjungan = '';
    public string $desa = '';
    public string $jenisCkg = '';
    public string $klasterUsia = '';
    public string $pekerjaan = '';
    public string $pegawai = '';

    private function pasienTahunQuery()
    {
        return Pasien::query()->where('tahun', (string) $this->tahun_session);
    }

    private function parsedRegisterDate(?string $value): ?Carbon
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

    private function filteredPasienCollection()
    {
        $pasien = $this->pasienTahunQuery()->get();

        if ($this->bulan !== '') {
            $pasien = $pasien->filter(function ($item) {
                $date = $this->parsedRegisterDate($item->register_date);

                return $date && (int) $date->month === (int) $this->bulan;
            });
        }

        if ($this->desa !== '') {
            $pasien = $pasien->where('kel', $this->desa);
        }

        if ($this->pegawai !== '') {
            $pasien = $pasien->where('pegawai_id', (int) $this->pegawai);
        }

        return $pasien->values();
    }

    public function mount()
    {
        if (!$this->tahun_session) {
            $activeTahun = Tahun::where('is_active', true)->first();
            $nowTahun = date('Y');
            $this->tahun_session = $activeTahun ? $activeTahun->tahun : $nowTahun;
        }
    }

    public function updatedTahunSession(): void
    {
        session()->put('tahun_session', $this->tahun_session);

        $this->dispatch('dashboard-charts-updated', options: $this->chartOptions);
    }

    #[Computed]
    public function tahun()
    {
        return Tahun::all();
    }

    #[Computed]
    public function jumlahPasien()
    {
        return $this->filteredPasienCollection()->count();
    }

    #[Computed]
    public function jumlahPasienBelumDiambil()
    {
        return $this->filteredPasienCollection()->whereNull('pegawai_id')->count();
    }

    #[Computed]
    public function jumlahPasienSudahDiambil()
    {
        return $this->filteredPasienCollection()->whereNotNull('pegawai_id')->count();
    }

    #[Computed]
    public function jumlahPegawai()
    {
        return Pegawai::count();
    }

    #[Computed]
    public function jumlahPasienTahun()
    {
        return $this->pasienTahunQuery()->count();
    }

    #[Computed]
    public function pegawaiRank()
    {
        $pasien = $this->filteredPasienCollection()->whereNotNull('pegawai_id');
        $pegawaiIds = $pasien->pluck('pegawai_id')->filter()->unique()->values();
        $pegawaiNames = Pegawai::whereIn('id', $pegawaiIds)->pluck('nama', 'id');

        return $pasien
            ->groupBy('pegawai_id')
            ->map(function ($items, $pegawaiId) use ($pegawaiNames) {
                return (object) [
                    'id' => $pegawaiId,
                    'nama' => $pegawaiNames[$pegawaiId] ?? 'Pegawai #' . $pegawaiId,
                    'pasien_count' => $items->count(),
                ];
            })
            ->sortByDesc('pasien_count')
            ->values()
            ->take(5);
    }

    #[Computed]
    public function pegawaiRankMax()
    {
        return max(1, (int) ($this->pegawaiRank->max('pasien_count') ?? 1));
    }

    #[Computed]
    public function chartOptions()
    {
        $pasien = $this->filteredPasienCollection();
        $total = max(1, $pasien->count());

        $belum = $pasien->whereNull('pegawai_id')->count();
        $sudah = $pasien->whereNotNull('pegawai_id')->count();

        $jenisKelamin = [
            'Laki-laki' => 0,
            'Perempuan' => 0,
        ];

        foreach ($pasien as $item) {
            $jk = strtolower((string) $item->jenis_kelamin);

            if (str_contains($jk, 'laki')) {
                $jenisKelamin['Laki-laki']++;
            } elseif (str_contains($jk, 'perem') || str_contains($jk, 'wanita')) {
                $jenisKelamin['Perempuan']++;
            }
        }

        $desa = $pasien
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

        $rankData = $this->pegawaiRank;

        $registrasiLabels = [];
        $registrasiValues = [];

        if ($this->bulan !== '') {
            $bulan = (int) $this->bulan;
            $tanggal = Carbon::create((int) $this->tahun_session, $bulan, 1);
            $daysInMonth = $tanggal->daysInMonth;

            $registrasiLabels = range(1, $daysInMonth);
            $registrasiValues = array_fill(0, $daysInMonth, 0);

            foreach ($pasien as $item) {
                $date = $this->parsedRegisterDate($item->register_date);

                if ($date && (int) $date->year === (int) $this->tahun_session && (int) $date->month === $bulan) {
                    $registrasiValues[$date->day - 1]++;
                }
            }
        } else {
            $registrasiLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            $registrasiValues = array_fill(0, 12, 0);

            foreach ($pasien as $item) {
                $date = $this->parsedRegisterDate($item->register_date);

                if ($date && (int) $date->year === (int) $this->tahun_session) {
                    $registrasiValues[$date->month - 1]++;
                }
            }
        }

        return [
            'belumSudah' => [
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
                        'data' => [['value' => $belum, 'name' => 'Belum Diambil', 'itemStyle' => ['color' => '#f97316']], ['value' => $sudah, 'name' => 'Sudah Diambil', 'itemStyle' => ['color' => '#22c55e']]],
                    ],
                ],
            ],
            'registrasiHarian' => [
                'backgroundColor' => 'transparent',
                'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'cross']],
                'grid' => ['left' => 40, 'right' => 20, 'top' => 10, 'bottom' => 40],
                'xAxis' => [
                    'type' => 'category',
                    'boundaryGap' => false,
                    'data' => $registrasiLabels,
                    'axisLabel' => ['color' => '#94a3b8', 'fontSize' => 10, 'interval' => $this->bulan !== '' ? 'auto' : 0],
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
                        'name' => 'Registrasi',
                        'type' => 'line',
                        'smooth' => true,
                        'symbol' => 'circle',
                        'symbolSize' => 5,
                        'lineStyle' => ['color' => '#6366f1', 'width' => 2.5],
                        'itemStyle' => ['color' => '#6366f1'],
                        'areaStyle' => [
                            'color' => [
                                'type' => 'linear',
                                'x' => 0,
                                'y' => 0,
                                'x2' => 0,
                                'y2' => 1,
                                'colorStops' => [['offset' => 0, 'color' => 'rgba(99,102,241,0.35)'], ['offset' => 1, 'color' => 'rgba(99,102,241,0.02)']],
                            ],
                        ],
                        'data' => $registrasiValues,
                    ],
                ],
            ],
            'distribusiDesa' => [
                'backgroundColor' => 'transparent',
                'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
                'grid' => ['left' => 90, 'right' => 60, 'top' => 10, 'bottom' => 30],
                'xAxis' => [
                    'type' => 'value',
                    'axisLabel' => ['color' => '#94a3b8', 'fontSize' => 10],
                    'splitLine' => ['lineStyle' => ['color' => '#e2e8f0', 'type' => 'dashed']],
                ],
                'yAxis' => [
                    'type' => 'category',
                    'inverse' => true,
                    'data' => $desa->pluck('label')->all(),
                    'axisLabel' => ['color' => '#94a3b8', 'fontSize' => 11],
                    'axisLine' => ['lineStyle' => ['color' => '#e2e8f0']],
                ],
                'series' => [
                    [
                        'name' => 'Pasien',
                        'type' => 'bar',
                        'barMaxWidth' => 28,
                        'itemStyle' => [
                            'color' => [
                                'type' => 'linear',
                                'x' => 0,
                                'y' => 0,
                                'x2' => 1,
                                'y2' => 0,
                                'colorStops' => [['offset' => 0, 'color' => '#60a5fa'], ['offset' => 1, 'color' => '#6366f1']],
                            ],
                            'borderRadius' => [0, 6, 6, 0],
                        ],
                        'label' => ['show' => true, 'position' => 'right', 'color' => '#94a3b8', 'fontSize' => 10],
                        'data' => $desa->pluck('value')->all(),
                    ],
                ],
            ],
            'jenisKelamin' => [
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
                        'data' => [['value' => $jenisKelamin['Laki-laki'], 'name' => 'Laki-laki', 'itemStyle' => ['color' => '#3b82f6']], ['value' => $jenisKelamin['Perempuan'], 'name' => 'Perempuan', 'itemStyle' => ['color' => '#ec4899']]],
                    ],
                ],
            ],
            'pegawaiRank' => [
                'backgroundColor' => 'transparent',
                'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
                'grid' => ['left' => 120, 'right' => 30, 'top' => 10, 'bottom' => 20],
                'xAxis' => [
                    'type' => 'value',
                    'axisLabel' => ['color' => '#94a3b8', 'fontSize' => 10],
                    'splitLine' => ['lineStyle' => ['color' => '#e2e8f0', 'type' => 'dashed']],
                ],
                'yAxis' => [
                    'type' => 'category',
                    'inverse' => true,
                    'data' => $rankData->pluck('nama')->all(),
                    'axisLabel' => ['color' => '#94a3b8', 'fontSize' => 11],
                    'axisLine' => ['lineStyle' => ['color' => '#e2e8f0']],
                ],
                'series' => [
                    [
                        'name' => 'Jumlah CKG',
                        'type' => 'bar',
                        'barMaxWidth' => 26,
                        'itemStyle' => [
                            'color' => [
                                'type' => 'linear',
                                'x' => 0,
                                'y' => 0,
                                'x2' => 1,
                                'y2' => 0,
                                'colorStops' => [['offset' => 0, 'color' => '#14b8a6'], ['offset' => 1, 'color' => '#0f766e']],
                            ],
                            'borderRadius' => [0, 6, 6, 0],
                        ],
                        'label' => ['show' => true, 'position' => 'right', 'color' => '#94a3b8', 'fontSize' => 10],
                        'data' => $rankData->pluck('pasien_count')->all(),
                    ],
                ],
            ],
        ];
    }
};
?>

<script>
    window._chartOpts = @js($this->chartOptions);

    const reinitDashboardCharts = () => {
        document.querySelectorAll('[data-chart-key]').forEach((element) => {
            const key = element.getAttribute('data-chart-key');

            if (key && typeof window._initChart === 'function') {
                window._initChart(element, key);
            }
        });
    };

    document.addEventListener('livewire:init', () => {
        Livewire.on('dashboard-charts-updated', ({
            options
        }) => {
            if (options) {
                window._chartOpts = options;
            }

            requestAnimationFrame(() => {
                reinitDashboardCharts();
            });
        });
    });
</script>

<flux:main>
    <div class="mb-6 flex items-center justify-between gap-3">
        <flux:heading size="xl" level="1">Dashboard Capaian CKG</flux:heading>
        <flux:select wire:model.live="tahun_session" class="w-36" placeholder="Pilih tahun...">
            @foreach ($this->tahun as $tahun)
                <flux:select.option value="{{ $tahun->tahun }}">{{ $tahun->tahun }}
                    {{ $tahun->is_active ? '(Aktif)' : '' }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>
    <flux:separator variant="subtle" class="mb-6" />

    {{-- ===== STATS CARDS ===== --}}
    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4 mb-6">
        <div
            class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Total Pasien</p>
                <p class="mt-1 text-3xl font-bold text-zinc-800 dark:text-zinc-100">
                    {{ number_format($this->jumlahPasienTahun, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-zinc-400">Jumlah pasien tahun {{ $tahun_session }}</p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
                <flux:icon.users class="text-blue-500" variant="outline" />
            </div>
        </div>
        <div
            class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Belum Diklaim
                </p>
                <p class="mt-1 text-3xl font-bold text-zinc-800">
                    {{ number_format($this->jumlahPasienBelumDiambil, 0, ',', '.') }}</p>
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
                    {{ number_format($this->jumlahPasienSudahDiambil, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-zinc-400">Sudah terhubung ke pegawai</p>
            </div>
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/30">
                <flux:icon.check-circle class="text-green-500" variant="outline" />
            </div>
        </div>
        <div
            class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-4 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Jumlah Pegawai
                </p>
                <p class="mt-1 text-3xl font-bold text-zinc-800">{{ number_format($this->jumlahPegawai, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-zinc-400">Jumlah pegawai terdaftar</p>
            </div>
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                <flux:icon.chart-bar-square class="text-indigo-500" variant="outline" />
            </div>
        </div>
    </div>

    {{-- ===== ROW 4: Peringkat Pegawai CKG Terbanyak ===== --}}
    <div class="mb-6 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <p class="mb-1 text-sm font-semibold text-zinc-700 dark:text-zinc-200">Peringkat CKG Pegawai
                </p>
                <p class="text-xs text-zinc-400">Data berdasarkan pasien tahun {{ $tahun_session }}</p>
            </div>
            <flux:button variant="ghost" size="sm" icon="arrow-down-tray">Export</flux:button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <th class="px-3 py-2">Rank</th>
                        <th class="px-3 py-2">Nama Pegawai</th>
                        <th class="px-3 py-2">Jumlah CKG</th>
                        <th class="px-3 py-2">Persentase</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                    @foreach ($this->pegawaiRank as $pegawaiRank)
                        <tr>
                            <td class="px-3 py-3 font-semibold text-zinc-700 dark:text-zinc-200">{{ $loop->iteration }}
                            </td>
                            <td class="px-3 py-3">{{ $pegawaiRank->nama }}</td>
                            <td class="px-3 py-3 font-medium">{{ $pegawaiRank->pasien_count }} pasien</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-28 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                        <div class="h-full rounded-full bg-emerald-500"
                                            style="width: {{ round(($pegawaiRank->pasien_count / max(1, $this->jumlahPasienTahun)) * 100) }}%">
                                        </div>
                                    </div>
                                    <span
                                        class="text-xs text-zinc-500">{{ round(($pegawaiRank->pasien_count / max(1, $this->jumlahPasienTahun)) * 100) }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ===== FILTER PANEL ===== --}}
    <div x-data="{ open: true }"
        class="mb-6 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/60">
        <button @click="open = !open"
            class="flex w-full items-center justify-between px-5 py-3 text-sm font-semibold text-zinc-700 dark:text-zinc-200">
            <div class="flex items-center gap-2">
                <flux:icon.funnel class="h-4 w-4 text-zinc-400" variant="outline" />
                <span>Filter Data</span>
            </div>
            <flux:icon.chevron-down class="h-4 w-4 text-zinc-400 transition-transform duration-200"
                ::class="{ 'rotate-180': open }" variant="outline" />
        </button>
        <div x-show="open" x-transition class="border-t border-zinc-200 dark:border-zinc-700 px-5 py-4">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">

                {{-- Bulan Pelayanan --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Bulan
                        Pelayanan</label>
                    <flux:select wire:model="bulan" placeholder="Semua Bulan">
                        @foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $i => $bln)
                            <flux:select.option value="{{ $i + 1 }}">{{ $bln }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                {{-- Status Kunjungan --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Status
                        Kunjungan</label>
                    <flux:select wire:model="statusKunjungan" placeholder="Semua">
                        <flux:select.option value="belum_hadir">Belum Hadir</flux:select.option>
                        <flux:select.option value="dalam_pelayanan">Dalam Pelayanan</flux:select.option>
                        <flux:select.option value="selesai">Selesai Layanan</flux:select.option>
                    </flux:select>
                </div>

                {{-- Desa --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Desa</label>
                    <flux:select wire:model="desa" placeholder="Semua Desa">
                        <flux:select.option value="baruharjo">Baruharjo</flux:select.option>
                        <flux:select.option value="kanigoro">Kanigoro</flux:select.option>
                        <flux:select.option value="krisikan">Krisikan</flux:select.option>
                        <flux:select.option value="pulosari">Pulosari</flux:select.option>
                        <flux:select.option value="tegalombo">Tegalombo</flux:select.option>
                        <flux:select.option value="gemaharjo">Gemaharjo</flux:select.option>
                        <flux:select.option value="kasihan">Kasihan</flux:select.option>
                        <flux:select.option value="ngreco">Ngreco</flux:select.option>
                    </flux:select>
                </div>

                {{-- Jenis CKG --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Jenis CKG</label>
                    <flux:select wire:model="jenisCkg" placeholder="Semua">
                        <flux:select.option value="sekolah">Sekolah</flux:select.option>
                        <flux:select.option value="umum">Umum</flux:select.option>
                    </flux:select>
                </div>

                {{-- Klaster Usia --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Klaster Usia</label>
                    <flux:select wire:model="klasterUsia" placeholder="Semua">
                        <flux:select.option value="0-28h">0-28 hari</flux:select.option>
                        <flux:select.option value="1-4b">1-4 Bulan</flux:select.option>
                        <flux:select.option value="5-11b">5-11 Bulan</flux:select.option>
                        <flux:select.option value="1t">1 Tahun</flux:select.option>
                        <flux:select.option value="2t">2 Tahun</flux:select.option>
                        <flux:select.option value="3-4t">3-4 Tahun</flux:select.option>
                        <flux:select.option value="5t">5 Tahun</flux:select.option>
                        <flux:select.option value="6t">6 Tahun</flux:select.option>
                        <flux:select.option value="7t">7 Tahun</flux:select.option>
                        <flux:select.option value="8-9t">8-9 Tahun</flux:select.option>
                        <flux:select.option value="10-12t">10-12 Tahun</flux:select.option>
                        <flux:select.option value="13-14t">13-14 Tahun</flux:select.option>
                        <flux:select.option value="15-17t">15-17 Tahun</flux:select.option>
                        <flux:select.option value="18-21t">18-21 Tahun</flux:select.option>
                        <flux:select.option value="22-24t">22-24 Tahun</flux:select.option>
                        <flux:select.option value="25-29t">25-29 Tahun</flux:select.option>
                        <flux:select.option value="30-39t">30-39 Tahun</flux:select.option>
                        <flux:select.option value="40-44t">40-44 Tahun</flux:select.option>
                        <flux:select.option value="45-49t">45-49 Tahun</flux:select.option>
                        <flux:select.option value="50-59t">50-59 Tahun</flux:select.option>
                        <flux:select.option value="60-69t">60-69 Tahun</flux:select.option>
                        <flux:select.option value="70+t">70 Tahun ke atas</flux:select.option>
                    </flux:select>
                </div>

                {{-- Pekerjaan --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Pekerjaan</label>
                    <flux:select wire:model="pekerjaan" placeholder="Semua Pekerjaan">
                        <flux:select.option value="petani">Petani</flux:select.option>
                        <flux:select.option value="buruh">Buruh</flux:select.option>
                        <flux:select.option value="pedagang">Pedagang</flux:select.option>
                        <flux:select.option value="pns">PNS / ASN</flux:select.option>
                        <flux:select.option value="swasta">Karyawan Swasta</flux:select.option>
                        <flux:select.option value="wirausaha">Wirausaha</flux:select.option>
                        <flux:select.option value="ibu_rumah_tangga">Ibu Rumah Tangga</flux:select.option>
                        <flux:select.option value="pelajar">Pelajar / Mahasiswa</flux:select.option>
                        <flux:select.option value="tidak_bekerja">Tidak Bekerja</flux:select.option>
                    </flux:select>
                </div>

                {{-- Pegawai CKG --}}
                {{-- <div class="col-span-2 md:col-span-1">
                    <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Pegawai yang
                        CKG</label>
                    <flux:select wire:model="pegawai" placeholder="Semua Pegawai">
                        <flux:select.option value="1">Budi Santoso</flux:select.option>
                        <flux:select.option value="2">Siti Rahayu</flux:select.option>
                        <flux:select.option value="3">Ahmad Fauzi</flux:select.option>
                        <flux:select.option value="4">Dewi Lestari</flux:select.option>
                        <flux:select.option value="5">Eko Prasetyo</flux:select.option>
                    </flux:select>
                </div> --}}

                {{-- Reset Button --}}
                <div class="flex items-end col-span-2 md:col-span-1">
                    <flux:button variant="ghost" icon="arrow-path" class="w-full">
                        Reset Filter
                    </flux:button>
                </div>

            </div>
        </div>
    </div>

    {{-- ===== ROW 1: Donut (Belum/Sudah) + Line (Registrasi per hari) ===== --}}
    <div class="grid grid-cols-1 gap-6 mb-6 xl:grid-cols-2">

        {{-- Donut: Perbandingan Pemeriksaan --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200 mb-1">Status Pengambilan Pasien</p>
            <p class="text-xs text-zinc-400 mb-4">Belum diambil vs. sudah diambil</p>
            <div data-chart-key="belumSudah" x-data x-init="window._initChart($el, 'belumSudah')" class="h-64"></div>
        </div>

        {{-- Line: Registrasi per hari --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200 mb-1">Tren Registrasi</p>
            <p class="text-xs text-zinc-400 mb-4">Jumlah registrasi per hari</p>
            <div data-chart-key="registrasiHarian" x-data x-init="window._initChart($el, 'registrasiHarian')" class="h-64"></div>
        </div>

    </div>

    {{-- ===== ROW 2: Horizontal Bar (Distribusi Per Desa) ===== --}}
    {{-- <div class="mb-6 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
        <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200 mb-1">Distribusi Pasien Per Desa</p>
        <p class="text-xs text-zinc-400 mb-4">Jumlah pasien berdasarkan desa domisili</p>
        <div data-chart-key="distribusiDesa" x-data x-init="window._initChart($el, 'distribusiDesa')" class="h-72"></div>
    </div> --}}

    {{-- ===== ROW 3: Donut (L/P) + Bar Pegawai ===== --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">

        {{-- Bar: Pegawai Terbanyak --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200 mb-1">Distribusi Pasien Per Desa</p>
            <p class="text-xs text-zinc-400 mb-4">Jumlah pasien berdasarkan desa domisili</p>
            <div data-chart-key="distribusiDesa" x-data x-init="window._initChart($el, 'distribusiDesa')" class="h-72"></div>
        </div>

        {{-- Donut: Laki vs Perempuan --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200 mb-1">Perbandingan Jenis Kelamin</p>
            <p class="text-xs text-zinc-400 mb-4">Laki-laki vs. Perempuan</p>
            <div data-chart-key="jenisKelamin" x-data x-init="window._initChart($el, 'jenisKelamin')" class="h-64"></div>
        </div>

    </div>



</flux:main>
