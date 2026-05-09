<?php

use Livewire\Component;

new class extends Component {
    public function render() {
        return <<<'HTML'
        <div>
            {{-- Header Halaman --}}
            <x-header title="Viewer | Monitoring" subtitle="Pemantauan Kondisi dan Konsumsi Energi Gedung" separator progress-indicator />

            {{-- Konten Utama --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-card title="Total Konsumsi Energi" shadow>
                    <div class="text-3xl font-bold text-primary mt-2">1,240 kWh</div>
                    <div class="text-sm opacity-70">Bulan ini</div>
                </x-card>

                <x-card title="Status Keseluruhan" shadow>
                    <div class="text-3xl font-bold text-success mt-2">Normal</div>
                    <div class="text-sm opacity-70">Tidak ada anomali terdeteksi</div>
                </x-card>

                <x-card title="Laporan Cepat" shadow>
                    <p class="text-sm opacity-70 mb-2">Lihat statistik penggunaan ruangan dan perangkat lebih detail.</p>
                    <x-button label="Buka Statistik" icon="o-chart-bar" class="btn-outline btn-primary btn-sm w-full" link="/viewer/statistik" />
                </x-card>
            </div>
        </div>
        HTML;
    }
}; ?>
