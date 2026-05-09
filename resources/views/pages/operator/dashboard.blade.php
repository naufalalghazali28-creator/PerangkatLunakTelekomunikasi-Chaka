<?php

use Livewire\Component;

new class extends Component {
    public function render() {
        return <<<'HTML'
        <div>
            {{-- Header Halaman --}}
            <x-header title="Operator | Control Panel" subtitle="Kendali Perangkat Gedung Operasional" separator progress-indicator />

            {{-- Konten Utama --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-card title="AC Ruang Rapat (R.101)" subtitle="Gedung Utama" shadow>
                    <div class="flex justify-between items-center mt-2">
                        <div class="text-2xl font-bold text-info">24°C</div>
                        <x-toggle class="toggle-success" checked />
                    </div>
                </x-card>

                <x-card title="Aktivitas Terakhir" shadow>
                    <x-list-item :item="['name' => 'Lampu Lobby dimatikan', 'sub' => '10 menit lalu oleh Anda']" />
                    <x-button label="Lihat Log Lengkap" class="btn-ghost btn-sm w-full mt-2" link="/operator/jadwal" />
                </x-card>
            </div>
        </div>
        HTML;
    }
}; ?>
