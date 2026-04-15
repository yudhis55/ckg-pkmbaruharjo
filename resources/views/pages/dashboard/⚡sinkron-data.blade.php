<?php

use App\Models\Pasien;
use Livewire\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {
    public string $cookie = '';
    public string $payload = '';
    public array $histories = [];

    public function syncData()
    {
        $validated = $this->validate([
            'cookie' => ['required', 'string'],
            'payload' => ['required', 'json'],
        ]);

        $body = json_decode($validated['payload'], true);
        $url = 'https://sehatindonesiaku.kemkes.go.id/api/pkg/list-individu';

        try {
            $response = Http::withHeaders([
                'Cookie' => $validated['cookie'],
                'Content-Type' => 'application/json',
            ])->post($url, $body);

            $responseJson = $response->json();
            $dataCount = $this->extractDataCount($responseJson);
            $status = $response->successful() ? 'Berhasil' : 'Gagal';

            $syncResult = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
            ];

            if ($response->successful()) {
                $syncResult = $this->upsertPasiens($responseJson);
            }

            array_unshift($this->histories, [
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'jumlah_data' => $dataCount,
                'status' => $status,
                'tanggal_pemeriksaan' => now()->format('Y-m-d'),
            ]);

            if ($response->successful()) {
                $this->dispatch('notify', message: 'Sinkronisasi berhasil.');
                session()->flash('success', "Sinkronisasi berhasil. Total API: {$dataCount}. Dibuat: {$syncResult['created']}, diperbarui: {$syncResult['updated']}, dilewati: {$syncResult['skipped']}.");
            } else {
                session()->flash('error', 'Sinkronisasi gagal. HTTP ' . $response->status());
            }
        } catch (Throwable $exception) {
            array_unshift($this->histories, [
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'jumlah_data' => 0,
                'status' => 'Gagal',
                'tanggal_pemeriksaan' => now()->format('Y-m-d'),
            ]);

            session()->flash('error', 'Sinkronisasi gagal: ' . $exception->getMessage());
        }
    }

    protected function upsertPasiens($responseJson): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        $patients = data_get($responseJson, 'data', []);

        if (!is_array($patients)) {
            return $result;
        }

        foreach ($patients as $patient) {
            $nik = (string) data_get($patient, 'patient_nik', '');
            $tglLahir = (string) data_get($patient, 'patient_born_date', '');

            if ($nik === '' || $tglLahir === '') {
                $result['skipped']++;
                continue;
            }

            $pasien = Pasien::updateOrCreate(
                ['nik' => $nik],
                [
                    'reg_id' => (string) data_get($patient, 'reg_id', ''),
                    'nomor_tiket' => (string) data_get($patient, 'ticket_number', ''),
                    'nama' => (string) data_get($patient, 'patient_full_name', ''),
                    'tgl_lahir' => $tglLahir,
                    'jenis_kelamin' => (string) data_get($patient, 'patient_gender', ''),
                    'rt_rw' => (string) data_get($patient, 'patient_domicile.address', ''),
                    'kel' => (string) data_get($patient, 'patient_domicile.sub_district_name', ''),
                    'kec' => (string) data_get($patient, 'patient_domicile.district_name', ''),
                    'kab' => (string) data_get($patient, 'patient_domicile.city_name', ''),
                    'faskes' => (string) data_get($patient, 'faskes_name', ''),
                    'no_wa' => (string) data_get($patient, 'patient_mobile_number', ''),
                    'register_date' => (string) data_get($patient, 'register_date', ''),
                    'tahun' => (string) data_get($patient, 'screening_year', ''),
                    'pegawai_id' => null,
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

    protected function extractDataCount($responseJson): int
    {
        if (!is_array($responseJson)) {
            return 0;
        }

        if (isset($responseJson['data']) && is_array($responseJson['data'])) {
            return count($responseJson['data']);
        }

        return 0;
    }
};
?>

<flux:main>
    <div class="mb-6 flex items-center justify-between gap-3">
        <flux:heading size="xl" level="1">Sinkronisasi Data</flux:heading>
        <flux:select class="w-36" placeholder="Pilih tahun...">
            <flux:select.option>2025</flux:select.option>
            <flux:select.option>2026</flux:select.option>
        </flux:select>
    </div>
    <flux:separator variant="subtle" class="mb-2" />

    <div class="mt-6 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
        <flux:heading size="lg">Konfigurasi Sinkronisasi</flux:heading>
        <flux:text class="mt-1 mb-4" variant="subtle">Isi data cookie dan body request untuk proses sinkronisasi.
        </flux:text>

        @if (session()->has('success'))
            <flux:text class="mb-3 text-green-600 dark:text-green-400">{{ session('success') }}</flux:text>
        @endif

        @if (session()->has('error'))
            <flux:text class="mb-3 text-red-600 dark:text-red-400">{{ session('error') }}</flux:text>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <flux:textarea wire:model.defer="cookie" rows="5" label="Request Header - Cookie"
                placeholder="Masukkan cookie..." />
            <flux:textarea wire:model.defer="payload" rows="5" label="Request Payload"
                placeholder="Masukkan body request..." />
        </div>

        @error('cookie')
            <flux:text class="mt-2 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror

        @error('payload')
            <flux:text class="mt-2 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror

        <div class="mt-4 flex justify-end">
            <flux:button wire:click="syncData" wire:loading.attr="disabled" wire:target="syncData" icon="arrow-path">
                <span wire:loading.remove wire:target="syncData">Proses Sinkronisasi</span>
                <span wire:loading wire:target="syncData">Memproses...</span>
            </flux:button>
        </div>
    </div>

    <div class="mt-8">
        <div class="mb-3">
            <flux:heading size="lg">Riwayat Sinkronisasi</flux:heading>
            <flux:text class="mt-1" variant="subtle">Daftar hasil sinkronisasi data sebelumnya.</flux:text>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Timestamp Sinkron</flux:table.column>
                <flux:table.column>Jumlah Data</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Tanggal Pemeriksaan</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->histories as $history)
                    <flux:table.row>
                        <flux:table.cell>{{ $history['timestamp'] }}</flux:table.cell>
                        <flux:table.cell>{{ $history['jumlah_data'] }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="{{ $history['status'] === 'Berhasil' ? 'green' : 'red' }}" size="sm"
                                inset="top bottom">
                                {{ $history['status'] }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $history['tanggal_pemeriksaan'] }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">
                            Belum ada riwayat sinkronisasi.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</flux:main>
