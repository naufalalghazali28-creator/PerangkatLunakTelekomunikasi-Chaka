<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\BEMS\Building;
use App\Models\BEMS\Node;
use App\Models\User;
use Livewire\Attributes\Computed;

new class extends Component {
    use WithPagination;

    public string $search       = '';
    public ?int   $filterClient = null;

    public function updatedSearch(): void       { $this->resetPage(); }
    public function updatedFilterClient(): void { $this->resetPage(); }

    #[Computed]
    public function buildings()
    {
        return Building::with(['client', 'rooms.nodes.creator', 'rooms.nodes.activator'])
            ->when($this->search, fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhereHas('client', fn($c) => $c->where('name', 'like', "%{$this->search}%"))
            )
            ->when($this->filterClient, fn($q) => $q->where('client_id', $this->filterClient))
            ->orderBy('name')
            ->paginate(10);
    }

    #[Computed]
    public function clients()
    {
        return \App\Models\BEMS\Client::orderBy('name')->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->toArray();
    }

    // Staff yang terlibat per gedung (maintenance & operator)
    private function getStaffForBuilding(Building $building): array
    {
        $nodeIds     = $building->rooms->flatMap->nodes->pluck('id');
        $maintenance = Node::whereIn('id', $nodeIds)->with('creator')
            ->whereNotNull('created_by')->get()
            ->pluck('creator')->filter()->unique('id');
        $operators   = Node::whereIn('id', $nodeIds)->with('activator')
            ->whereNotNull('activated_by')->get()
            ->pluck('activator')->filter()->unique('id');
        return [
            'maintenance' => $maintenance,
            'operators'   => $operators,
        ];
    }
}; ?>

<div>
    <x-header title="List Gedung" subtitle="Semua gedung beserta informasi client, staff, dan sensor" separator progress-indicator />

    {{-- FILTER --}}
    <div class="flex gap-3 mb-4 flex-wrap">
        <x-input wire:model.live.debounce.400ms="search" placeholder="Cari nama gedung atau client..." icon="o-magnifying-glass" class="flex-1" clearable />
        <x-select wire:model.live="filterClient" :options="$this->clients" placeholder="Semua Client" class="w-48" />
        @if($search || $filterClient)
        <x-button label="Reset" wire:click="$set('search',''); $set('filterClient',null)" class="btn-ghost btn-sm" />
        @endif
    </div>

    <div class="space-y-4">
        @forelse($this->buildings as $building)
        @php
            $nodes    = $building->rooms->flatMap->nodes;
            $active   = $nodes->where('status', true)->count();
            $inactive = $nodes->where('status', false)->count();
            $staff    = $this->getStaffForBuilding($building);
        @endphp
        <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            {{-- Header Gedung --}}
            <div class="flex items-start gap-4 mb-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                <div class="w-12 h-12 rounded-2xl bg-violet-500/10 flex items-center justify-center shrink-0">
                    <x-icon name="o-building-office" class="w-6 h-6 text-violet-500" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-base font-bold text-zinc-900 dark:text-white">{{ $building->name }}</p>
                    <p class="text-sm text-zinc-400">
                        Client: <span class="font-medium text-zinc-600 dark:text-zinc-300">{{ $building->client?->name ?? '-' }}</span>
                        · {{ $building->rooms->count() }} ruangan
                    </p>
                </div>
                <div class="flex gap-2 shrink-0">
                    <span class="flex items-center gap-1.5 text-xs font-semibold text-blue-500 bg-blue-500/10 px-2.5 py-1 rounded-lg">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>{{ $active }} aktif
                    </span>
                    <span class="flex items-center gap-1.5 text-xs text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-2.5 py-1 rounded-lg">
                        <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span>{{ $inactive }} nonaktif
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                {{-- Staff Maintenance --}}
                <div>
                    <p class="text-[10px] font-semibold text-zinc-400 uppercase tracking-widest mb-2">🔧 Maintenance</p>
                    @forelse($staff['maintenance'] as $u)
                    <div class="flex items-center gap-2 mb-1.5">
                        <div class="w-6 h-6 rounded-lg bg-green-500/10 flex items-center justify-center text-[10px] font-bold text-green-500 shrink-0">
                            {{ strtoupper(substr($u->name,0,1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300 truncate">{{ $u->name }}</p>
                            <p class="text-[10px] text-zinc-400 truncate">{{ $u->email }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-zinc-400 italic">—</p>
                    @endforelse
                </div>

                {{-- Staff Operator --}}
                <div>
                    <p class="text-[10px] font-semibold text-zinc-400 uppercase tracking-widest mb-2">⚡ Operator</p>
                    @forelse($staff['operators'] as $u)
                    <div class="flex items-center gap-2 mb-1.5">
                        <div class="w-6 h-6 rounded-lg bg-blue-500/10 flex items-center justify-center text-[10px] font-bold text-blue-500 shrink-0">
                            {{ strtoupper(substr($u->name,0,1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300 truncate">{{ $u->name }}</p>
                            <p class="text-[10px] text-zinc-400 truncate">{{ $u->email }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-zinc-400 italic">—</p>
                    @endforelse
                </div>

                {{-- Ruangan & Node --}}
                <div>
                    <p class="text-[10px] font-semibold text-zinc-400 uppercase tracking-widest mb-2">📍 Ruangan & Node</p>
                    @foreach($building->rooms->take(4) as $room)
                    @php $roomNodes = $room->nodes; @endphp
                    <div class="mb-1.5">
                        <p class="text-xs font-medium text-zinc-600 dark:text-zinc-300">
                            Lt.{{ $room->floor }} — {{ $room->name }}
                            <span class="text-zinc-400 font-normal">({{ $roomNodes->count() }} node)</span>
                        </p>
                        <div class="flex flex-wrap gap-1 mt-0.5">
                            @foreach($roomNodes->take(3) as $node)
                            @php $c = match($node->node_type){
                                'temperature'=>'bg-blue-500/10 text-blue-500',
                                'current'=>'bg-yellow-500/10 text-yellow-600',
                                'voltage'=>'bg-red-500/10 text-red-500',
                                'light'=>'bg-green-500/10 text-green-600',
                                default=>'bg-zinc-100 text-zinc-500'
                            }; @endphp
                            <span class="text-[9px] px-1.5 py-0.5 rounded-md {{ $c }} font-medium">
                                {{ match($node->node_type){'temperature'=>'Suhu','current'=>'Arus','voltage'=>'V','light'=>'Cahaya',default=>$node->node_type} }}
                            </span>
                            @endforeach
                            @if($roomNodes->count() > 3)
                            <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 text-zinc-400">+{{ $roomNodes->count()-3 }}</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                    @if($building->rooms->count() > 4)
                    <p class="text-[10px] text-zinc-400 mt-1">+{{ $building->rooms->count()-4 }} ruangan lainnya</p>
                    @endif
                </div>
            </div>
        </x-card>
        @empty
        <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="py-12 text-center text-zinc-400">
                <x-icon name="o-building-office" class="w-12 h-12 mx-auto mb-3 opacity-20" />
                <p class="text-sm">Tidak ada gedung ditemukan.</p>
            </div>
        </x-card>
        @endforelse

        {{ $this->buildings->links() }}
    </div>
</div>