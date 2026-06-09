<?php

use Livewire\Component;
use App\Models\BEMS\Node;
use App\Models\BEMS\Building;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    #[Computed]
    public function stats()
    {
        $nodes = Node::all();
        return [
            'total'    => $nodes->count(),
            'active'   => $nodes->where('status', true)->count(),
            'inactive' => $nodes->where('status', false)->count(),
        ];
    }

    // FIX: Group gedung by nama (case-insensitive) agar tidak duplikat
    #[Computed]
    public function buildings()
    {
        return Building::with(['rooms.nodes'])->get()
            ->groupBy(fn($b) => strtolower(trim($b->name)))
            ->map(function ($group) {
                $nodes = $group->flatMap->rooms->flatMap->nodes;
                return [
                    'name'     => trim($group->first()->name),
                    'rooms'    => $group->sum(fn($b) => $b->rooms->count()),
                    'total'    => $nodes->count(),
                    'active'   => $nodes->where('status', true)->count(),
                    'inactive' => $nodes->where('status', false)->count(),
                ];
            })
            ->values();
    }

    #[Computed]
    public function recentNodes()
    {
        return Node::with('room.building')
            ->latest()
            ->take(5)
            ->get();
    }
}; ?>

<div>
    <x-header
        title="Dashboard Maintenance"
        subtitle="Pantau semua node yang terdaftar di setiap gedung"
        separator
        progress-indicator
    >
        <x-slot:actions>
            <x-button label="Daftarkan Node" icon="o-plus" href="/maintenance/register-node" class="btn-primary btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- STAT CARDS — jajar ke samping --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                    <x-icon name="o-server-stack" class="w-6 h-6 text-zinc-500 dark:text-zinc-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['total'] }}</p>
                    <p class="text-xs text-zinc-500">Total Node</p>
                </div>
            </div>
        </x-card>

        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-green-500/10 flex items-center justify-center shrink-0">
                    <x-icon name="o-check-circle" class="w-6 h-6 text-green-500" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-green-500">{{ $this->stats['active'] }}</p>
                    <p class="text-xs text-zinc-500">Node Aktif</p>
                </div>
            </div>
        </x-card>

        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-red-500/10 flex items-center justify-center shrink-0">
                    <x-icon name="o-x-circle" class="w-6 h-6 text-red-500" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-red-500">{{ $this->stats['inactive'] }}</p>
                    <p class="text-xs text-zinc-500">Node Nonaktif</p>
                </div>
            </div>
        </x-card>
    </div>

    <div class="grid grid-cols-5 gap-4">

        {{-- BUILDING LIST --}}
        <div class="col-span-3">
            <x-card
                title="Gedung Terdaftar"
                subtitle="Jumlah node per gedung"
                shadow
                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl h-full"
            >
                @forelse($this->buildings as $b)
                <div class="flex items-center gap-4 py-3 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                    <div class="w-10 h-10 rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                        <x-icon name="o-building-office" class="w-5 h-5 text-zinc-500" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 truncate">{{ $b['name'] }}</p>
                        <p class="text-xs text-zinc-400">{{ $b['rooms'] }} ruangan</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs shrink-0">
                        <span class="flex items-center gap-1 text-green-500 font-semibold">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            {{ $b['active'] }}
                        </span>
                        <span class="flex items-center gap-1 text-red-500 font-semibold">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>
                            {{ $b['inactive'] }}
                        </span>
                        <span class="text-zinc-400">/ {{ $b['total'] }} node</span>
                    </div>
                </div>
                @empty
                <div class="py-10 text-center text-zinc-400">
                    <x-icon name="o-building-office" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                    <p class="text-sm">Belum ada gedung terdaftar.</p>
                </div>
                @endforelse
            </x-card>
        </div>

        {{-- RECENT NODES --}}
        <div class="col-span-2">
            <x-card
                title="Node Terbaru"
                subtitle="5 node yang baru didaftarkan"
                shadow
                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl h-full"
            >
                @forelse($this->recentNodes as $node)
                <div class="flex items-start gap-3 py-3 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                    <div class="mt-1 w-2 h-2 rounded-full shrink-0 {{ $node->status ? 'bg-green-500' : 'bg-zinc-500' }}"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200 truncate">{{ $node->name }}</p>
                        <p class="text-[11px] text-zinc-400 truncate">
                            {{ $node->room?->building?->name }} › {{ $node->room?->name }}
                        </p>
                        <x-badge value="{{ $node->node_type }}" class="badge-ghost badge-sm mt-1 capitalize" />
                    </div>
                    <p class="text-[10px] text-zinc-500 shrink-0 mt-0.5">{{ $node->created_at->diffForHumans() }}</p>
                </div>
                @empty
                <div class="py-10 text-center text-zinc-400">
                    <x-icon name="o-server-stack" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                    <p class="text-sm">Belum ada node.</p>
                </div>
                @endforelse
            </x-card>
        </div>

    </div>
</div>