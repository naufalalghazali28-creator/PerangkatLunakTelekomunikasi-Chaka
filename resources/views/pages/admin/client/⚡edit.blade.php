<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\BEMS\Client;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public $clientEditModal = false;
    public $clientId;
    public $code;
    public $name;
    public $expirity;

    #[On('enableEditClient')]
    public function enableEditClient($clientId = null)
    {
        // Jika modal mau dibuka (clientId tidak null)
        if ($clientId) {
            $this->clientId = $clientId;
            $client = Client::find($clientId);

            if ($client) {
                $this->code = $client->code;
                $this->name = $client->name;
                $this->expirity = $client->expirity;
                $this->clientEditModal = true;
            }
        } else {
            // Jika tombol cancel ditekan
            $this->clientEditModal = false;
        }
    }

    public function updateClient()
    {
        $this->validate([
            'code' => 'required|string',
            'name' => 'required|string',
            'expirity' => 'required|date',
        ]);

        Client::find($this->clientId)->update([
            'code' => $this->code,
            'name' => $this->name,
            'expirity' => $this->expirity,
        ]);

        // Reset semua field agar bersih untuk pemakaian berikutnya
        $this->reset(['code', 'name', 'expirity', 'clientId']);
        
        $this->success('Client data has been updated!');
        $this->clientEditModal = false;
        $this->dispatch('refreshIndexClient');
    }
};
?>

<div>
    {{-- Modal Utama --}}
    <x-modal wire:model="clientEditModal" title="Edit Client" class="backdrop-blur">
        <div class="text-left">
            <x-card subtitle="Update client information" shadow separator>
                <div class="space-y-4">

                    {{-- Baris 1: Identitas Dasar (Code & Name) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input wire:model="code" label="Code" placeholder="Masukkan kode client..." />
                        <x-input wire:model="name" label="Name of client" placeholder="Nama lengkap client..." />
                    </div>

                    {{-- Baris 2: Expiry Date --}}
                    <div>
                        <x-datetime label="Expiry Date" wire:model="expirity" icon="o-calendar" />
                    </div>

                </div>

                <x-slot:actions>
                    <x-button wire:click="enableEditClient" label="Cancel" />
                    <x-button wire:click="updateClient" class="btn-primary" label="Update Data" spinner="updateClient" />
                </x-slot:actions>
            </x-card>
        </div>
    </x-modal>
</div>