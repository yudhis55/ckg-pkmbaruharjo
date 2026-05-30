<?php

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Pasien;

new class extends Component {
    public string $cookie = '';
    public string $startDate = '';
    public string $endDate = '';
    public array $histories = [];

    public function syncData()
    {
        $validated = $this->validate([
            'cookie'    => ['required', 'string'],
            'startDate' => ['required', 'date'],
            'endDate'   => ['required', 'date', 'after_or_equal:startDate'],
        ]);

        // Kirim raw cookie header — Python service yang parse dengan attribute mapping yang benar
        // (cf_clearance → sameSite=None, asik_web_stk/ctk/token_eksternal → httpOnly=true, dst.)
        // Tidak perlu parseCookieHeaderToPlaywrightCookies() di Laravel lagi.
        $pythonPayload = [
            'cookies_header' => $validated['cookie'],
            'request' => [
                'target_url'             => 'https://sehatindonesiaku.kemkes.go.id/ckg-pendaftaran-individu',
                'list_endpoint_contains' => '/api/pkg/list-individu',
                'timeout_ms'             => 300000,
                // Hapus timing overrides — biarkan server default yang sudah dioptimasi:
                // headless=true, wait_after_action_ms=300, settle_after_filter_ms=500
                // Override hanya jika site lambat:
                // 'settle_after_filter_ms' => 2000,
                // 'wait_after_action_ms'   => 1000,
                'headless'               => false,  // set false untuk debug lokal
            ],
            'dates' => [
                'start_date' => $validated['startDate'],
                'end_date'   => $validated['endDate'],
            ],
            'pagination' => [
                'max_pages_per_date' => 20,
            ],
        ];

        try {
            $response = Http::connectTimeout(30)
                ->timeout(600)
                ->acceptJson()
                ->post('http://127.0.0.1:9999/scrape', $pythonPayload);

            if (! $response->successful()) {
                session()->flash('error', 'Sinkronisasi gagal. HTTP ' . $response->status() . ': ' . $response->body());
                return;
            }

            $json = $response->json();

            if (! data_get($json, 'ok', false)) {
                session()->flash('error', 'Sinkronisasi gagal. Service mengembalikan ok=false.');
                return;
            }

            $dateResults = data_get($json, 'results', []);

            $allPatients = collect($dateResults)
                ->filter(fn ($r) => (bool) data_get($r, 'success', false) === true)
                ->flatMap(fn ($r) => data_get($r, 'data', []))
                ->values()
                ->all();

            $syncResult = $this->upsertPasiens($allPatients);

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
                'Sinkronisasi selesai. ' .
                'Dibuat: ' . $syncResult['created'] . ', ' .
                'Diperbarui: ' . $syncResult['updated'] . ', ' .
                'Dilewati: ' . $syncResult['skipped']
            );
        } catch (Throwable $e) {
            session()->flash('error', 'Sinkronisasi gagal: ' . $e->getMessage());
        }
    }

    protected function upsertPasiens(array $patients): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($patients as $patient) {
            $nik      = (string) data_get($patient, 'patient_nik', '');
            $tglLahir = (string) data_get($patient, 'patient_born_date', '');

            if ($nik === '' || $tglLahir === '') {
                $result['skipped']++;
                continue;
            }

            $pasien = Pasien::updateOrCreate(
                ['nik' => $nik],
                [
                    'reg_id'        => (string) data_get($patient, 'reg_id', ''),
                    'nomor_tiket'   => (string) data_get($patient, 'ticket_number', ''),
                    'nama'          => (string) data_get($patient, 'patient_full_name', ''),
                    'tgl_lahir'     => $tglLahir,
                    'jenis_kelamin' => (string) data_get($patient, 'patient_gender', ''),
                    'rt_rw'         => (string) data_get($patient, 'patient_domicile.address', ''),
                    'kel'           => (string) data_get($patient, 'patient_domicile.sub_district_name', ''),
                    'kec'           => (string) data_get($patient, 'patient_domicile.district_name', ''),
                    'kab'           => (string) data_get($patient, 'patient_domicile.city_name', ''),
                    'faskes'        => (string) data_get($patient, 'faskes_name', ''),
                    'no_wa'         => (string) data_get($patient, 'patient_mobile_number', ''),
                    'register_date' => (string) data_get($patient, 'register_date', ''),
                    'tahun'         => (string) data_get($patient, 'screening_year', ''),
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
        <flux:heading size="xl" level="1">Sinkronisasi Data CKG Umum</flux:heading>
    </div>
    <flux:separator variant="subtle" class="mb-6" />

    <div class="mb-6 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
        <div class="mb-4">
            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Form Sinkronisasi Data</p>
            <p class="text-xs text-zinc-400">
                Tempel cookie dari browser DevTools (F12 → Network → pilih request ke sehatindonesiaku → copy nilai header Cookie)
            </p>
        </div>

        <form wire:submit.prevent="syncData" class="space-y-4">
            <div>
                <flux:label>Cookie Header</flux:label>
                <flux:textarea
                    wire:model.defer="cookie"
                    rows="4"
                    placeholder="asik_web_stk=...; asik_web_ctk=...; access_menu=...; token_eksternal=..."
                />
                @error('cookie')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
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

            <flux:button type="submit" wire:loading.attr="disabled" variant="primary">
                <span wire:loading.remove wire:target="syncData">Sinkronisasi</span>
                <span wire:loading wire:target="syncData">Memproses...</span>
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
            <p class="text-xs text-zinc-400">Riwayat sinkronisasi data dari sistem eksternal</p>
        </div>

        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Waktu</flux:table.column>
                    <flux:table.column>Tanggal Pemeriksaan</flux:table.column>
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
                            <flux:table.cell>{{ $history['error'] ?? '-' }}</flux:table.cell>
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
