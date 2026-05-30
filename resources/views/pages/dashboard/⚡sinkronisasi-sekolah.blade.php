<?php

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use App\Models\Pasien;

new class extends Component {
    public string $cookie = '';
    public array $histories = [];

    public function syncDataSekolah()
    {
        $validated = $this->validate([
            'cookie' => ['required', 'string'],
        ]);

        $pythonPayload = [
            'cookies_header' => $validated['cookie'],
            'request' => [
                'target_url' => 'https://sehatindonesiaku.kemkes.go.id/ckg-pendaftaran-anak-sekolah',
                'list_endpoint_contains' => '/api/pkg/anak-sekolah/list-patient',
                'timeout_ms' => 300000,
                'headless'            => false,  // set false untuk debug lokal
            ],
            'pagination' => [
                'max_pages_per_date' => 20,
            ],
        ];

        try {
            $response = Http::connectTimeout(30)->timeout(600)->acceptJson()->post('http://127.0.0.1:9999/scrape-sekolah', $pythonPayload);

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
            $successCount = 0;

            foreach ($results as $item) {
                if (data_get($item, 'success', false)) {
                    $successCount++;
                }

                array_unshift($this->histories, [
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                    'sekolah' => (string) data_get($item, 'school_name', '-'),
                    'kelas' => (string) data_get($item, 'class_name', '-'),
                    'jumlah_data' => (int) data_get($item, 'total_data', 0),
                    'status' => data_get($item, 'success', false) ? 'Berhasil' : 'Gagal',
                    'error' => (string) data_get($item, 'error', ''),
                ]);
            }

            session()->flash('success', "Sinkronisasi selesai. {$successCount} dari " . count($results) . ' data sekolah/kelas berhasil diproses.');
        } catch (Throwable $e) {
            session()->flash('error', 'Sinkronisasi gagal: ' . $e->getMessage());
        }
    }
};
?>

<flux:main>
    <div class="mb-6">
        <flux:heading size="xl">Sinkronisasi Data CKG Sekolah</flux:heading>
        {{-- <p class="text-zinc-500 dark:text-zinc-400">Tarik data pendaftaran anak sekolah dari portal Sehat Indonesiaku
            (ASIK).</p> --}}
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <div class="mb-6 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
        <div class="mb-4">
            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Form Sinkronisasi Sekolah</p>
            <p class="text-xs text-zinc-400">
                Tempel cookie dari browser DevTools (F12 → Network → pilih request ke sehatindonesiaku → copy nilai
                header Cookie)
            </p>
        </div>

        <form wire:submit.prevent="syncDataSekolah" class="space-y-4">
            <div>
                <flux:label>Cookie Header</flux:label>
                <flux:textarea wire:model.defer="cookie" rows="4"
                    placeholder="asik_web_stk=...; asik_web_ctk=...; access_menu=...; token_eksternal=..." />
                @error('cookie')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            <flux:button type="submit" wire:loading.attr="disabled" variant="primary">
                <span wire:loading.remove wire:target="syncDataSekolah">Sinkronisasi</span>
                <span wire:loading wire:target="syncDataSekolah">Memproses...</span>
            </flux:button>
        </form>
    </div>

    @if (session('success'))
        <div
            class="mb-6 rounded-xl border border-green-200 bg-green-50 px-5 py-4 dark:border-green-900/30 dark:bg-green-900/20">
            <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('success') }}</p>
        </div>
    @endif

    @if (session('error'))
        <div
            class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 dark:border-red-900/30 dark:bg-red-900/20">
            <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ session('error') }}</p>
        </div>
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
        <div class="mb-4">
            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Histori Sinkronisasi</p>
            <p class="text-xs text-zinc-400">Riwayat sinkronisasi data sekolah dari sistem eksternal</p>
        </div>

        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Waktu</flux:table.column>
                    <flux:table.column>Sekolah</flux:table.column>
                    <flux:table.column>Kelas</flux:table.column>
                    <flux:table.column>Jumlah Data</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Error</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($histories as $history)
                        <flux:table.row>
                            <flux:table.cell>{{ $history['timestamp'] ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $history['sekolah'] ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $history['kelas'] ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $history['jumlah_data'] ?? 0 }}</flux:table.cell>
                            <flux:table.cell>
                                @if (($history['status'] ?? '') === 'Berhasil')
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200">Berhasil</span>
                                @else
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200">Gagal</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $history['error'] ?: '-' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center text-zinc-500">
                                Belum ada histori sinkronisasi.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</flux:main>
