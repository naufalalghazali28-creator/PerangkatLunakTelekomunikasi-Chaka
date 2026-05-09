<?php

use Livewire\Component;
use App\Models\BEMS\Client;
use App\Models\BEMS\Building;
use App\Models\BEMS\Room;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Mary\Traits\Toast;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\Url;

new class extends Component
{
    use Toast;

    #[Url]
    public string $tab = 'infra'; 

    public bool $buildingModal = false, $editBuildingModal = false, $staffModal = false, $editStaffModal = false;
    public string $buildingName = '', $initialRoomName = '', $initialRoomFloor = '1';
    public string $staffName = '', $staffRole = 'operator';
    public string $editStaffName = '', $editStaffRole = 'operator';
    public int $selectedBuildingId = 0, $editStaffId = 0;
    public array $editingRooms = [];

    // --- 1. FUNGSI GEDUNG & RUANGAN ---
    public function saveBuilding() {
        $this->validate(['buildingName' => 'required', 'initialRoomName' => 'required']);
        $client = Client::where('user_id', Auth::id())->first();
        if (!$client) {
            $this->error('Profil klien tidak ditemukan!');
            return;
        }
        $newB = Building::create(['client_id' => $client->id, 'name' => $this->buildingName]);
        Room::create(['building_id' => $newB->id, 'name' => $this->initialRoomName, 'floor' => $this->initialRoomFloor]);
        $this->reset(['buildingName', 'initialRoomName', 'buildingModal']);
        $this->success('Gedung & Ruangan berhasil dibuat!');
    }

    public function editBuilding(Building $building) {
        $client = Client::where('user_id', Auth::id())->first();
        if ($building->client_id !== $client?->id) {
            $this->error('Akses ditolak: Gedung tidak ditemukan atau bukan milik Anda.');
            return;
        }
        $this->selectedBuildingId = $building->id;
        $this->buildingName = $building->name;
        $this->editingRooms = $building->rooms->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'floor' => $r->floor])->toArray();
        $this->editBuildingModal = true;
    }

    public function updateBuilding() {
        $building = Building::find($this->selectedBuildingId);
        $client = Client::where('user_id', Auth::id())->first();
        
        if ($building && $building->client_id === $client?->id) {
            $building->update(['name' => $this->buildingName]);
            foreach ($this->editingRooms as $roomData) {
                // Validasi agar ruangan yang diupdate benar-benar berada di dalam gedung ini
                Room::where('id', $roomData['id'])->where('building_id', $building->id)
                    ->update(['name' => $roomData['name'], 'floor' => $roomData['floor']]);
            }
            $this->editBuildingModal = false;
            $this->success('Data diperbarui!');
        } else {
            $this->error('Gagal memperbarui data.');
        }
    }

    public function deleteBuilding($id) {
        $building = Building::find($id);
        $client = Client::where('user_id', Auth::id())->first();
        if ($building && $building->client_id === $client?->id) {
            $building->delete();
            $this->success('Gedung dihapus.');
        } else {
            $this->error('Akses ditolak!');
        }
    }

    // --- EXPORT GEDUNG ---
    public function exportBuildingExcel() {
        return Excel::download(new class implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function collection() {
                $client = Client::where('user_id', Auth::id())->first();
                if (!$client) return collect([]);
                return Building::where('client_id', $client->id)->with('rooms')->get()->map(fn($b) => [
                    'Gedung' => $b->name,
                    'Lantai' => $b->rooms->pluck('floor')->unique()->implode(', '),
                    'Ruangan' => $b->rooms->pluck('name')->implode(', ')
                ]);
            }
            public function headings(): array { return ['Nama Gedung', 'Daftar Lantai', 'Daftar Ruangan']; }
        }, 'Infrastruktur_Gedung.xlsx');
    }

    public function exportBuildingPdf() {
        $client = Client::where('user_id', Auth::id())->first();
        if (!$client) {
            $this->error('Profil klien tidak ditemukan!');
            return;
        }
        $buildings = Building::where('client_id', $client->id)->with('rooms')->get();
        $html = "<h2>Data Infrastruktur Gedung</h2><table border='1' width='100%' style='border-collapse:collapse;'><tr><th>No</th><th>Gedung</th><th>Lantai</th><th>Ruangan</th></tr>";
        foreach($buildings as $idx => $b) {
            $floors = $b->rooms->pluck('floor')->unique()->implode(', ');
            $rooms = $b->rooms->pluck('name')->implode(', ');
            $html .= "<tr><td>".($idx+1)."</td><td>{$b->name}</td><td>{$floors}</td><td>{$rooms}</td></tr>";
        }
        $html .= "</table>";
        return response()->streamDownload(fn() => print(Pdf::loadHTML($html)->output()), "Data_Gedung.pdf");
    }

    // --- 2. FUNGSI STAF ---
    public function saveStaff() {
        $this->validate(['staffName' => 'required|alpha_dash']);
        $email = strtolower($this->staffName) . '@staff.id';
        
        // Cek jika ID/Email sudah dipakai (oleh staf dari client yang sama atau berbeda)
        if (User::where('email', $email)->exists()) {
            $this->error("ID '{$this->staffName}' sudah digunakan. Silakan pilih ID lain.");
            return;
        }
        
        User::create(['name' => $this->staffName, 'email' => $email, 'password' => Hash::make('password123'), 'role' => $this->staffRole, 'parent_id' => Auth::id()]);
        $this->reset(['staffName', 'staffModal']);
        $this->success("Staf terdaftar!");
    }

    public function editStaff($id) {
        $staff = User::where('id', $id)->where('parent_id', Auth::id())->first();
        if ($staff) {
            $this->editStaffId = $staff->id;
            $this->editStaffName = $staff->name;
            $this->editStaffRole = $staff->role;
            $this->editStaffModal = true;
        } else {
            $this->error('Staf tidak ditemukan.');
        }
    }

    public function updateStaff() {
        $this->validate(['editStaffName' => 'required|alpha_dash', 'editStaffRole' => 'required']);
        
        $staff = User::where('id', $this->editStaffId)->where('parent_id', Auth::id())->first();
        if ($staff) {
            $email = strtolower($this->editStaffName) . '@staff.id';
            
            if ($staff->email !== $email && User::where('email', $email)->exists()) {
                $this->error("ID '{$this->editStaffName}' sudah digunakan. Silakan pilih ID lain.");
                return;
            }

            $staff->update(['name' => $this->editStaffName, 'email' => $email, 'role' => $this->editStaffRole]);
            $this->editStaffModal = false;
            $this->success('Data staf berhasil diperbarui!');
        }
    }

    public function deleteStaff($id) {
        $staff = User::where('id', $id)->where('parent_id', Auth::id())->first();
        if ($staff) {
            $staff->delete();
            $this->success('Staf dihapus.');
        } else {
            $this->error('Gagal menghapus staf: Akses ditolak.');
        }
    }

    public function exportStaffExcel() {
        return Excel::download(new class implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function collection() {
                return User::where('parent_id', Auth::id())->get()->map(fn($s) => ['Nama' => $s->name, 'Email' => $s->email, 'Role' => ucfirst($s->role)]);
            }
            public function headings(): array { return ['Nama Staf', 'Email Staf', 'Role']; }
        }, 'Daftar_Staf.xlsx');
    }

    public function exportStaffPdf() {
        $myStaffs = User::where('parent_id', Auth::id())->get();
        $html = "<h2>Daftar Staf</h2><table border='1' width='100%' style='border-collapse:collapse;'><tr><th>No</th><th>Nama</th><th>Email</th><th>Role</th></tr>";
        foreach($myStaffs as $idx => $s) { $html .= "<tr><td>".($idx+1)."</td><td>{$s->name}</td><td>{$s->email}</td><td>".ucfirst($s->role)."</td></tr>"; }
        $html .= "</table>";
        return response()->streamDownload(fn() => print(Pdf::loadHTML($html)->output()), "Daftar_Staf.pdf");
    }

    public function render() {
        $userId = Auth::id();
        $clientProfile = Client::where('user_id', $userId)->first();
        return $this->view([
            'clients' => Client::where('user_id', $userId)->get()->map(function($c){
                $c->remain = $c->expirity ? (int)now()->diffInDays(Carbon::parse($c->expirity), false) : 0;
                return $c;
            }),
            'myBuildings' => Building::where('client_id', $clientProfile?->id)->with('rooms')->get(),
            'myStaffs' => User::where('parent_id', $userId)->get(),
        ]);
    }
}; ?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            @if($tab == 'infra') Manajemen Gedung @elseif($tab == 'staff') Kelola Staf Anggota @else Info Lisensi @endif
        </x-slot:title>
        <x-slot:actions>
            @if($tab == 'infra')
                <x-dropdown label="Export" icon="o-arrow-down-tray" class="btn-outline btn-sm" right>
                    <x-menu-item title="Export PDF" icon="o-document-text" wire:click="exportBuildingPdf" class="text-error" />
                    <x-menu-item title="Export Excel" icon="o-table-cells" wire:click="exportBuildingExcel" class="text-success" />
                </x-dropdown>
                <x-button label="Tambah Gedung" icon="o-plus" wire:click="$set('buildingModal', true)" class="btn-primary btn-sm" />
            @elseif($tab == 'staff')
                <x-dropdown label="Export" icon="o-arrow-down-tray" class="btn-outline btn-sm" right>
                    <x-menu-item title="Export PDF" icon="o-document-text" wire:click="exportStaffPdf" class="text-error" />
                    <x-menu-item title="Export Excel" icon="o-table-cells" wire:click="exportStaffExcel" class="text-success" />
                </x-dropdown>
                <x-button label="Tambah Staf" icon="o-user-plus" wire:click="$set('staffModal', true)" class="btn-primary btn-sm" />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="mt-2 animate-in fade-in duration-300">
        @if($tab == 'infra')
            <x-card shadow>
                <x-table :headers="[['key' => 'id', 'label' => 'No'], ['key' => 'name', 'label' => 'Nama Gedung'], ['key' => 'floors', 'label' => 'Lantai'], ['key' => 'room_list', 'label' => 'Daftar Ruangan'], ['key' => 'action', 'label' => 'Action']]" :rows="$myBuildings">
                    @scope('cell_id', $building, $loop) {{ $loop->iteration }} @endscope
                    @scope('cell_floors', $building)
                        <div class="flex gap-1">@foreach($building->rooms->pluck('floor')->unique()->sort() as $f) <x-badge :value="'Lt '.$f" class="badge-ghost badge-sm" /> @endforeach</div>
                    @endscope
                    @scope('cell_room_list', $building)
                        <div class="flex flex-wrap gap-1">@foreach($building->rooms as $r) <x-badge :value="$r->name" class="badge-outline badge-sm" /> @endforeach</div>
                    @endscope
                    @scope('cell_action', $building)
                        <div class="flex gap-2">
                            <x-button icon="o-pencil-square" wire:click="editBuilding({{ $building->id }})" class="btn-ghost btn-sm text-info" />
                            <x-button icon="o-trash" wire:click="deleteBuilding({{ $building->id }})" wire:confirm="Hapus gedung ini?" class="btn-ghost btn-sm text-error" />
                        </div>
                    @endscope
                </x-table>
            </x-card>
        @elseif($tab == 'staff')
            <x-card shadow>
                <x-table :headers="[['key' => 'id', 'label' => 'No'], ['key' => 'name', 'label' => 'Nama'], ['key' => 'email', 'label' => 'Email'], ['key' => 'role', 'label' => 'Role'], ['key' => 'action', 'label' => 'Action']]" :rows="$myStaffs">
                    @scope('cell_id', $staff, $loop) {{ $loop->iteration }} @endscope
                    @scope('cell_role', $staff) <x-badge :value="ucfirst($staff->role)" class="badge-info badge-outline" /> @endscope
                    @scope('cell_action', $staff) 
                        <div class="flex gap-2">
                            <x-button icon="o-pencil-square" wire:click="editStaff({{ $staff->id }})" class="btn-ghost btn-sm text-info" />
                            <x-button icon="o-trash" wire:click="deleteStaff({{ $staff->id }})" wire:confirm="Hapus staf ini?" class="btn-ghost btn-sm text-error" /> 
                        </div>
                    @endscope
                </x-table>
            </x-card>
        @elseif($tab == 'info')
            <x-card shadow>
                <x-table :headers="[['key' => 'code', 'label' => 'Code'], ['key' => 'name', 'label' => 'Name'], ['key' => 'expirity', 'label' => 'Expired'], ['key' => 'remain', 'label' => 'Sisa Hari']]" :rows="$clients">
                    @scope('cell_remain', $client) <span class="{{ $client->remain < 7 ? 'text-error' : 'text-success' }} font-bold">{{ $client->remain }} Hari</span> @endscope
                </x-table>
            </x-card>
        @endif
    </div>

    {{-- MODAL EDIT (BERSIH TANPA KOTAK) --}}
    <x-modal wire:model="editBuildingModal" title="Edit Detail Infrastruktur" separator>
        <div class="space-y-6">
            <x-input label="Nama Gedung" wire:model="buildingName" icon="o-building-office" />
            
            <div class="space-y-4">
                <p class="text-sm font-bold opacity-70 uppercase tracking-widest">Daftar Ruangan & Lantai</p>
                @foreach($editingRooms as $index => $room)
                    <div class="flex gap-2 items-end">
                        <div class="w-20"><x-input label="Lt" wire:model="editingRooms.{{ $index }}.floor" /></div>
                        <div class="flex-1"><x-input label="Nama Ruangan" wire:model="editingRooms.{{ $index }}.name" /></div>
                    </div>
                @endforeach
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Simpan Perubahan" wire:click="updateBuilding" class="btn-primary" spinner="updateBuilding" />
        </x-slot:actions>
    </x-modal>

    {{-- MODAL TAMBAH GEDUNG --}}
    <x-modal wire:model="buildingModal" title="Gedung Baru" separator>
        <x-input label="Nama Gedung" wire:model="buildingName" class="mb-3" />
        <div class="flex gap-2 p-1">
            <div class="w-20"><x-input label="Lantai" wire:model="initialRoomFloor" /></div>
            <div class="flex-1"><x-input label="Nama Ruangan" wire:model="initialRoomName" /></div>
        </div>
        <x-slot:actions><x-button label="Simpan" wire:click="saveBuilding" class="btn-primary" /></x-slot:actions>
    </x-modal>

    {{-- MODAL TAMBAH STAF --}}
    <x-modal wire:model="staffModal" title="Tambah Staf" separator>
        <x-input label="ID Staf" wire:model="staffName" class="mb-2" />
        <x-select label="Role" wire:model="staffRole" :options="[['id' => 'operator', 'name' => 'Operator'], ['id' => 'maintenance', 'name' => 'Maintenance'], ['id' => 'viewer', 'name' => 'Viewer']]" />
        <x-slot:actions><x-button label="Simpan" wire:click="saveStaff" class="btn-primary" /></x-slot:actions>
    </x-modal>

    {{-- MODAL EDIT STAF --}}
    <x-modal wire:model="editStaffModal" title="Edit Staf" separator>
        <x-input label="ID Staf" wire:model="editStaffName" class="mb-2" />
        <x-select label="Role" wire:model="editStaffRole" :options="[['id' => 'operator', 'name' => 'Operator'], ['id' => 'maintenance', 'name' => 'Maintenance'], ['id' => 'viewer', 'name' => 'Viewer']]" />
        <x-slot:actions>
            <x-button label="Batal" wire:click="$set('editStaffModal', false)" />
            <x-button label="Simpan Perubahan" wire:click="updateStaff" class="btn-primary" spinner="updateStaff" />
        </x-slot:actions>
    </x-modal>
</div>