<?php

use Livewire\Component;

use Illuminate\Support\Facades\Auth;
use Mary\Traits\Toast;
use Livewire\Attributes\Layout;

#la
new #[Layout('layouts::guest')] class extends Component
{
    use Toast;
    public $email;
    public $password;

    protected $rules = [
        'email' => 'required|email',
        'password' => 'required|min:6',
    ];
    public function login()
    {
        //dd($this->email, $this->password);
        $this->validate();

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            //dd($this->email, $this->password);
            // Authentication passed...
            if(Auth::user()->hasRole('admin')){
                return redirect()->route('admin');
            }else{
                return redirect()->route('login');
            }


        }else{
            $this->error('Email atau password salah', position: 'toast-top toast-center');
        }
    }
};
?>

<div class="fixed inset-0 z-[100] flex items-center justify-center bg-base-200 p-4 font-sans antialiased">

    {{-- Main Login Card --}}
    <div class="w-full max-w-4xl bg-base-100 rounded-2xl shadow-2xl flex flex-col md:flex-row overflow-hidden">
        
        {{-- Left Section - Branding (Sengaja dibuat tetap gelap agar elegan di kedua mode) --}}
        <div class="w-full md:w-5/12 bg-slate-900 flex flex-col justify-center items-center p-12 relative">
            <div class="z-10 text-center flex flex-col items-center">
                <div class="flex items-center justify-center w-16 h-16 rounded-full bg-blue-500/20 border border-blue-500/30 mb-6">
                    <x-icon name="o-bolt" class="w-8 h-8 text-blue-400" />
                </div>
                <h2 class="text-3xl font-bold tracking-tight mb-4 text-white">Selamat Datang Kembali</h2>
                <p class="text-slate-400 text-sm max-w-[250px] leading-relaxed">
                    Akses dasbor Anda untuk mengelola sistem dengan platform aman kami.
                </p>
            </div>
        </div>

        {{-- Right Section - Form (Otomatis menyesuaikan Light/Dark Mode) --}}
        <div class="w-full md:w-7/12 flex flex-col justify-center py-12 px-8 sm:px-14 lg:px-20 relative">
            
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-base-content tracking-tight mb-2">Masuk ke Akun Anda</h1>
                <p class="text-sm text-base-content/60">Silakan masukkan detail Anda untuk melanjutkan.</p>
            </div>

            <form wire:submit.prevent="login" class="space-y-5">
                {{-- Input Email --}}
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-base-content">Alamat Email</label>
                    <x-input 
                        wire:model.blur="email" 
                        placeholder="email@bems.id" 
                        icon="o-envelope" 
                        class="input-bordered w-full rounded-lg bg-base-200 focus:border-blue-500 transition-all py-3" />
                </div>
                
                {{-- Input Password --}}
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-base-content">Kata Sandi</label>
                    <x-password 
                        wire:model="password" 
                        wire:keydown.enter="login" 
                        placeholder="••••••••" 
                        icon="o-lock-closed" 
                        right
                        class="input-bordered w-full rounded-lg bg-base-200 focus:border-blue-500 transition-all py-3" />
                </div>
                
                {{-- Action Button --}}
                <div class="pt-4">
                    <x-button 
                        label="Masuk" 
                        type="submit"
                        class="w-full rounded-lg bg-blue-600 hover:bg-blue-700 text-white border-none text-base font-bold shadow-lg shadow-blue-600/30 transition-all active:scale-[0.98] h-12" 
                        spinner="login" />
                </div>
            </form>

        </div>
    </div>
</div>