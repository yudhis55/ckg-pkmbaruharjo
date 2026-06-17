<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Http;
use App\Models\Pasien;

new class extends Component
{
    public string $emrUser = 'elink';
    public string $emrPassword = 'pkmbh*1';
    public string $startDate = '';
    public string $endDate = '';
    public bool $fetchBpjsFaskes = true;
    public array $histories = [];

    public function syncDataEmr()
    {
        $validated = $this->validate([
            'emrUser'     => ['required', 'string'],
            'emrPassword' => ['required', 'string'],
            'startDate'   => ['required', 'date'],
            'endDate'     => ['required', 'date', 'after_or_equal:startDate'],
        ]);

        $pythonPayload = [
            'user'     => $validated['emrUser'],
            'password' => $validated['emrPassword'],
            'request'  => [
                'target_url' => 'https://emrtrenggalek.my.id/daf',
                'id_cabang'  => '16',  // PUSKESMAS BARUHARJO
                'timeout_ms' => 600000,
                'headless' => 'false',
            ],
            'dates' => [
                'start_date' => $validated['startDate'],
                'end_date'   => $validated['endDate'],
            ],
            'fetch_bpjs_faskes' => $this->fetchBpjsFaskes,
        ];

        try {
            $response = Http::connectTimeout(30)
                ->timeout(900)
                ->acceptJson()
                ->post('http://127.0.0.1:9999/scrape-emr', $pythonPayload);

            if (! $response->successful()) {
                session()->flash('error', 'Sinkronisasi EMR gagal. HTTP ' . $response->status() . ': ' . $response->body());
                return;
            }

            $json = $response->json();

            if (! data_get($json, 'ok', false)) {
                session()->flash('error', 'Sinkronisasi EMR gagal. Service mengembalikan ok=false.');
                return;
            }

            $dateResults = data_get($json, 'results', []);

            $allPatients = collect($dateResults)
                ->filter(fn ($r) => (bool) data_get($r, 'success', false) === true)
                ->flatMap(fn ($r) => data_get($r, 'data', []))
                ->values()
                ->all();

            $syncResult = $this->upsertPasiensEmr($allPatients);

            foreach ($dateResults as $item) {
                array_unshift($this->histories, [
                    'timestamp'           => now()->format('Y-m-d H:i:s'),
                    'tanggal_pemeriksaan' => (string) data_get($item, 'date', ''),
                    'jumlah_data'         => (int) data_get($item, 'total_data', 0),
                    'status'              => data_get($item, 'success', false) ? 'Berhasil' : 'Gagal',
                    'error'               => (string) data_get($item, 'error', ''),
                ]);
            }

            session()->flash('success',
                'Sinkronisasi EMR selesai. ' .
                'Dibuat: ' . $syncResult['created'] . ', ' .
                'Diperbarui: ' . $syncResult['updated'] . ', ' .
                'Dilewati: ' . $syncResult['skipped']
            );
        } catch (Throwable $e) {
            session()->flash('error', 'Sinkronisasi EMR gagal: ' . $e->getMessage());
        }
    }

    protected function upsertPasiensEmr(array $patients): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($patients as $patient) {
            $nik      = (string) data_get($patient, 'nik', '');
            $tglLahir = (string) data_get($patient, 'tgl_lahir', '');

            if ($nik === '' || $tglLahir === '') {
                $result['skipped']++;
                continue;
            }

            $pasien = Pasien::updateOrCreate(
                ['nik' => $nik],
                [
                    'reg_id'        => '',
                    'nomor_tiket'   => '',
                    'nama'          => (string) data_get($patient, 'nama', ''),
                    'tgl_lahir'     => $tglLahir,
                    'jenis_kelamin' => (string) data_get($patient, 'jenis_kelamin', ''),
                    'alamat'        => (string) data_get($patient, 'alamat', ''),
                    'kel'           => (string) data_get($patient, 'kel', ''),
                    'kec'           => (string) data_get($patient, 'kec', ''),
                    'kab'           => (string) data_get($patient, 'kab', ''),
                    'faskes'        => (string) data_get($patient, 'faskes', ''),
                    'no_wa'         => (string) data_get($patient, 'no_wa', ''),
                    'tipe'          => 'emr',
                    'sekolah'       => null,
                    'kelas'         => null,
                    'register_date' => '',
                    'tahun'         => '',
                    'pegawai_id'    => null,
                ],
            );

            if ($pasien->wasRecentlyCreated) {
                $result['created']++;
            } else {
                $result['updated']++;
            }
        }

        return $result;
    }
};
?>

<flux:main>
    <div class="mb-6 flex items-center justify-between gap-3">
        <flux:heading size="xl" level="1">Sinkronisasi Data EMR Trenggalek</flux:heading>
    </div>
    <flux:separator variant="subtle" class="mb-6" />

    <div class="mb-6 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
        <div class="mb-4">
            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Form Sinkronisasi EMR</p>
            <p class="text-xs text-zinc-400">
                Login pakai akun EMR Trenggalek (user &amp; password puskesmas). Pilih rentang tanggal kunjungan.
            </p>
        </div>

        <form wire:submit.prevent="syncDataEmr" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <flux:label>User EMR</flux:label>
                    <flux:input type="text" wire:model.defer="emrUser" placeholder="elink" />
                    @error('emrUser')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
                <div>
                    <flux:label>Password EMR</flux:label>
                    <flux:input type="password" wire:model.defer="emrPassword" />
                    @error('emrPassword')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <flux:label>Tanggal Awal</flux:label>
                    <flux:input type="date" wire:model.defer="startDate" />
                    @error('startDate')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
                <div>
                    <flux:label>Tanggal Akhir</flux:label>
                    <flux:input type="date" wire:model.defer="endDate" />
                    @error('endDate')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            </div>

            <div class="flex items-center gap-2">
                <flux:checkbox wire:model.defer="fetchBpjsFaskes" />
                <flux:label class="text-sm">Ambil faskes BPJS (sedikit lebih lambat, hanya untuk pasien BPJS)</flux:label>
            </div>

            <flux:button type="submit" wire:loading.attr="disabled" variant="primary">
                <span wire:loading.remove wire:target="syncDataEmr">Sinkronisasi</span>
                <span wire:loading wire:target="syncDataEmr">Memproses...</span>
            </flux:button>
        </form>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-5 py-4 dark:border-green-900/30 dark:bg-green-900/20">
            <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('success') }}</p>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 dark:border-red-900/30 dark:bg-red-900/20">
            <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ session('error') }}</p>
        </div>
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
        <div class="mb-4">
            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Histori Sinkronisasi</p>
            <p class="text-xs text-zinc-400">Riwayat sinkronisasi data EMR Trenggalek</p>
        </div>

        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Waktu</flux:table.column>
                    <flux:table.column>Tanggal Kunjungan</flux:table.column>
                    <flux:table.column>Jumlah Data</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Error</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($histories as $history)
                        <flux:table.row>
                            <flux:table.cell>{{ $history['timestamp'] ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $history['tanggal_pemeriksaan'] ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $history['jumlah_data'] ?? 0 }}</flux:table.cell>
                            <flux:table.cell>
                                @if (($history['status'] ?? '') === 'Berhasil')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200">Berhasil</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200">Gagal</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $history['error'] ?: '-' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center text-zinc-500">
                                Belum ada histori sinkronisasi.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</flux:main>