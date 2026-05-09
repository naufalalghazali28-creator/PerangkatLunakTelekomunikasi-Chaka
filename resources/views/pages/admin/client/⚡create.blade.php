<?php

use Livewire\Component;
use App\Models\BEMS\Client;
use App\Models\User;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\On;

new class extends Component
{
    use Toast;
    
    public $enableClientCreate = false;
    public $code, $name, $expirity;

    // Listener untuk membuka form dari tombol di idx.blade.php
    #[On('toggleCreateClient')]
    public function toggleCreation(){
        $this->enableClientCreate = !$this->enableClientCreate;
    }

    public function saveClient(){
        $this->validate([
            'code'      => 'required|string|unique:bems_clients,code',
            'name'      => 'required|string',
            'expirity'  => 'required|date',
        ]);

        $user = User::updateOrCreate(
            ['email' => $this->code . "@bems.id"], 
            [
                'name' => $this->code, 
                'password' => Hash::make($this->code . "1809##"),
                'role' => 'client'
            ]
        );

        Client::create([
            'code'      => $this->code,
            'name'      => $this->name,
            'user_id'   => $user->id,
            'expirity'  => $this->expirity,
        ]);

        $this->reset(['code', 'name', 'expirity']); 
        $this->success('Client data has been saved!');
        $this->dispatch('refreshIndexClient'); 
        $this->enableClientCreate = false; 
    }
}; ?>

<div>
    {{-- Form ini hanya muncul jika tombol Add Client diklik --}}
    @if($enableClientCreate)
    <div class="text-left animate-in fade-in duration-300 my-4">
        <x-card title="Add New Client" subtitle="Silakan isi data client baru" shadow separator>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input wire:model="code" label="Code" placeholder="Nama Pendek" />
                <x-input wire:model="name" label="Name" placeholder="Nama Lengkap" />
                <div class="col-span-full md:col-span-1">
                    <x-datetime label="Expiry Date" wire:model="expirity" icon="o-calendar" />
                </div>
            </div>

            <x-slot:actions>
                <x-button label="Cancel" wire:click="toggleCreation" class="btn-ghost" />
                <x-button label="Save Client" wire:click="saveClient" class="btn-primary" spinner="saveClient" />
            </x-slot:actions>
        </x-card>
    </div>
    @endif
</div>