<?php

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use App\Models\Pasien;

new class extends Component {
    public string $cookie = '';
    public string $selectedSekolah = '';  // empty = semua sekolah
    public string $selectedKelas = '';     // empty = semua kelas
    public array $histories = [];

    public array $sekolahOptions = [
        'MAS TERPADU AL ANWAR DURENAN',
        'MIS MIFTAHUL HUDA PAKIS',
        'MIS MUHAMMADIYAH KAMULAN',
        'MIS NURUL IMAN GADOR',
        'MIS TASMIRIT TARBIYAH SUMBERGAYAM',
        'MIS WAJIB BELAJAR HIDAYATUT THULLAB',
        'MTSS DARISSULAIMANIYYAH',
        'SD NEGERI 1 BARUHARJO',
        'SD NEGERI 1 GADOR',
        'SD NEGERI 1 KAMULAN',
        'SD NEGERI 1 KARANGANOM',
        'SD NEGERI 1 SUMBEREJO',
        'SD NEGERI 2 BARUHARJO',
        'SD NEGERI 2 GADOR',
        'SD NEGERI 2 KAMULAN',
        'SD NEGERI 2 KARANGANOM',
        'SD NEGERI 2 SUMBEREJO',
        'SD NEGERI PAKIS',
        'SD NEGERI SUMBERGAYAM',
        'SMK DARISSULAIMANIYYAH',
        'SMK TERPADU AL ANWAR DURENAN',
        'SMK TERPADU ASSALAM DURENAN',
        'SMP DARUL ISTIQOMAH',
        'SMP DARUSSALAM DURENAN',
        'SMP NEGERI 2 DURENAN',
        'SMP TERPADU AL ANWAR',
    ];

    public array $kelasOptions = [
        'Kelas 1', 'Kelas 2', 'Kelas 3', 'Kelas 4', 'Kelas 5',
        'Kelas 6', 'Kelas 7', 'Kelas 8', 'Kelas 9', 'Kelas 10',
    ];

    public function syncDataSekolah()
    {
        $validated = $this->validate([
            'cookie' => ['required', 'string'],
            'selectedSekolah' => ['nullable', 'string'],
            'selectedKelas' => ['nullable', 'string'],
        ]);

        // Validasi: kelas hanya boleh di-set jika sekolah dipilih
        if ($validated['selectedKelas'] && !$validated['selectedSekolah']) {
            $this->addError('selectedKelas', 'Pilih sekolah dulu sebelum memilih kelas.');
            session()->flash('error', 'Pilih sekolah dulu sebelum memilih kelas.');
            return;
        }

        $pythonPayload = [
            'cookies_header' => $validated['cookie'],
            'request' => [
                'target_url'             => 'https://sehatindonesiaku.kemkes.go.id/ckg-pendaftaran-anak-sekolah',
                'list_endpoint_contains' => '/api/pkg/anak-sekolah/list-patient',
                'timeout_ms'             => 300000,
                'headless'                => false,
            ],
            'pagination' => [
                'max_pages_per_date' => 20,
            ],
        ];

        // Tambahkan filter jika dipilih
        if ($validated['selectedSekolah']) {
            $pythonPayload['school_filter'] = $validated['selectedSekolah'];
        }
        if ($validated['selectedKelas']) {
            $pythonPayload['class_filter'] = $validated['selectedKelas'];
        }

        try {
            $response = Http::connectTimeout(30)
                ->timeout(600)
                ->acceptJson()
                ->post('http://127.0.0.1:9999/scrape-sekolah', $pythonPayload);

            if (!$response->successful()) {
                session()->flash('error', 'Sinkronisasi gagal. HTTP ' . $response->status() . ': ' . $response->body());
                return;
            }

            $json = $response->json();

            if (!data_get($json, 'ok', false)) {
                session()->flash('error', 'Sinkronisasi gagal. Service mengembalikan ok=false.');
                return;
            }

            $results = data_get($json, 'results', []);

            // Flatten semua patients dari semua kelas yang berhasil
            $allPatients = [];
            foreach ($results as $r) {
                if (!data_get($r, 'success', false)) continue;
                $schoolName = data_get($r, 'school_name', '');
                $className = data_get($r, 'class_name', '');
                foreach (data_get($r, 'data', []) as $patient) {
                    $patient['_school_name'] = $schoolName;  // injeksi context
                    $patient['_class_name'] = $className;
                    $allPatients[] = $patient;
                }
            }

            $syncResult = $this->upsertPasiensSekolah($allPatients);

            // Histori per sekolah/kelas
            foreach ($results as $item) {
                array_unshift($this->histories, [
                    'timestamp'    => now()->format('Y-m-d H:i:s'),
                    'sekolah'      => (string) data_get($item, 'school_name', '-'),
                    'kelas'        => (string) data_get($item, 'class_name', '-'),
                    'jumlah_data'  => (int) data_get($item, 'total_data', 0),
                    'status'       => data_get($item, 'success', false) ? 'Berhasil' : 'Gagal',
                    'error'        => (string) data_get($item, 'error', ''),
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

    protected function upsertPasiensSekolah(array $patients): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($patients as $patient) {
            $nik      = (string) data_get($patient, 'patient.nik', '');
            $tglLahir = (string) data_get($patient, 'patient.born_date', '');

            if ($nik === '' || $tglLahir === '') {
                $result['skipped']++;
                continue;
            }

            // Extract tahun dari register_date jika screening_year kosong
            $screeningYear = (string) data_get($patient, 'screening_year', '');
            if ($screeningYear === '') {
                $registerDate = (string) data_get($patient, 'register_date', '');
                if ($registerDate !== '') {
                    $screeningYear = substr($registerDate, 0, 4);
                }
            }

            $pasien = Pasien::updateOrCreate(
                ['nik' => $nik],
                [
                    'reg_id'        => (string) data_get($patient, 'reg_id', ''),
                    'nomor_tiket'   => (string) data_get($patient, 'ticket_number', ''),
                    'nama'          => (string) data_get($patient, 'patient.full_name', ''),
                    'tgl_lahir'     => $tglLahir,
                    'jenis_kelamin' => (string) data_get($patient, 'patient.gender', ''),
                    'alamat'        => (string) data_get($patient, 'patient.patient_domicile.address', ''),
                    'kel'           => (string) data_get($patient, 'patient.patient_domicile.sub_district_name', ''),
                    'kec'           => (string) data_get($patient, 'patient.patient_domicile.district_name', ''),
                    'kab'           => (string) data_get($patient, 'patient.patient_domicile.city_name', ''),
                    'faskes'        => (string) data_get($patient, 'faskes_name', ''),
                    'no_wa'         => (string) data_get($patient, 'patient.mobile_number', ''),
                    'tipe'          => 'sekolah',
                    'sekolah'       => (string) data_get($patient, '_school_name', ''),
                    'kelas'         => (string) data_get($patient, '_class_name', ''),
                    'register_date' => (string) data_get($patient, 'register_date', ''),
                    'tahun'         => $screeningYear,
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
    <div class="mb-6">
        <flux:heading size="xl">Sinkronisasi Data CKG Sekolah</flux:heading>
        <p class="text-zinc-500 dark:text-zinc-400">Tarik data pendaftaran anak sekolah dari portal Sehat Indonesiaku (ASIK).</p>
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <div class="mb-6 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
        <div class="mb-4">
            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Form Sinkronisasi Sekolah</p>
            <p class="text-xs text-zinc-400">
                Tempel cookie dari browser DevTools (F12 → Network → pilih request ke sehatindonesiaku → copy nilai header Cookie)
            </p>
        </div>

        <form wire:submit.prevent="syncDataSekolah" class="space-y-4">
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
                    <flux:label>Sekolah</flux:label>
                    <flux:select wire:model.defer="selectedSekolah">
                        <option value="">Semua Sekolah</option>
                        @foreach ($sekolahOptions as $sekolah)
                            <option value="{{ $sekolah }}">{{ $sekolah }}</option>
                        @endforeach
                    </flux:select>
                    @error('selectedSekolah')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
                <div>
                    <flux:label>Kelas</flux:label>
                    <flux:select wire:model.defer="selectedKelas">
                        <option value="">Semua Kelas</option>
                        @foreach ($kelasOptions as $kelas)
                            <option value="{{ $kelas }}">{{ $kelas }}</option>
                        @endforeach
                    </flux:select>
                    @error('selectedKelas')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                    <p class="text-xs text-zinc-400 mt-1">Pilih sekolah dulu jika ingin filter kelas.</p>
                </div>
            </div>

            <flux:button type="submit" wire:loading.attr="disabled" variant="primary">
                <span wire:loading.remove wire:target="syncDataSekolah">Sinkronisasi</span>
                <span wire:loading wire:target="syncDataSekolah">Memproses...</span>
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
            <p class="text-xs text-zinc-400">Riwayat sinkronisasi data sekolah dan kelas.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="pb-3 pr-3 font-semibold text-zinc-700 dark:text-zinc-200">Waktu</th>
                        <th class="pb-3 pr-3 font-semibold text-zinc-700 dark:text-zinc-200">Sekolah</th>
                        <th class="pb-3 pr-3 font-semibold text-zinc-700 dark:text-zinc-200">Kelas</th>
                        <th class="pb-3 pr-3 font-semibold text-zinc-700 dark:text-zinc-200">Data</th>
                        <th class="pb-3 pr-3 font-semibold text-zinc-700 dark:text-zinc-200">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($histories as $history)
                        <tr>
                            <td class="py-3 pr-3 text-zinc-600 dark:text-zinc-400">{{ $history['timestamp'] }}</td>
                            <td class="py-3 pr-3 text-zinc-600 dark:text-zinc-400">{{ $history['sekolah'] }}</td>
                            <td class="py-3 pr-3 text-zinc-600 dark:text-zinc-400">{{ $history['kelas'] }}</td>
                            <td class="py-3 pr-3 text-zinc-600 dark:text-zinc-400">{{ $history['jumlah_data'] }}</td>
                            <td class="py-3 pr-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $history['status'] === 'Berhasil' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                    {{ $history['status'] }}
                                </span>
                                @if ($history['error'])
                                    <p class="mt-1 text-[10px] text-red-500">{{ $history['error'] }}</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-zinc-400">Belum ada riwayat sinkronisasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</flux:main>
