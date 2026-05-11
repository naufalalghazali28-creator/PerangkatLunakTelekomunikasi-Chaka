<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Mary\Traits\Toast;
use Livewire\Attributes\Layout;

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
        $this->validate();

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            if (Auth::user()->hasRole('admin')) {
                return redirect()->route('admin');
            }
            return redirect()->intended('/dashboard');
        } else {
            $this->error('Email atau password salah', position: 'toast-top toast-center');
        }
    }
};
?>

<div x-data="{
        rows: 0,
        cols: 0,
        updateGrid() {
            // Kalkulasi jumlah hexagon agar memenuhi layar dari tengah
            this.rows = Math.ceil(window.innerHeight / 48) + 4;
            this.cols = Math.ceil(window.innerWidth / 60) + 4;
        }
    }"
    x-init="updateGrid()"
    @resize.window.debounce.150ms="updateGrid()"
    class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-[#050505] p-4 font-sans antialiased select-none overflow-hidden"
>
    {{-- CSS HONEYCOMB GEOMETRY --}}
    <style>
        .honeycomb-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            flex-direction: column;
            width: 125vw;
            height: 125vh;
            z-index: 0;
        }
        .honeycomb-row {
            display: flex;
            justify-content: center;
            margin-top: -16px; /* Kunci kerapatan vertikal agar interlock */
        }
        .hexagon {
            width: 60px;
            height: 68px;
            background: #111;
            position: relative;
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            margin: 2px;
            transition: all 0.8s ease;
        }
        .hexagon::before {
            content: '';
            position: absolute;
            inset: 1px; /* Tebal border hexagon */
            background: #050505;
            clip-path: inherit;
            z-index: 1;
        }
        .hexagon:hover {
            background: #0ea5e9;
            filter: drop-shadow(0 0 15px #0ea5e9);
            transition-duration: 0s;
        }
        .hexagon:hover::before {
            background: rgba(14, 165, 233, 0.1);
            transition-duration: 0s;
        }
        .row-offset {
            margin-left: 32px; /* Setengah lebar hexagon + margin */
        }
    </style>

    {{-- HEXAGON BACKGROUND --}}
    <div class="honeycomb-container pointer-events-auto">
        <template x-for="r in rows" :key="'r-'+r">
            <div :class="r % 2 === 0 ? 'honeycomb-row row-offset' : 'honeycomb-row'">
                <template x-for="c in cols" :key="'c-'+r+'-'+c">
                    <div class="hexagon"></div>
                </template>
            </div>
        </template>
    </div>
    
    {{-- LOGIN CARD --}}
    <div class="relative z-10 flex flex-col items-center gap-12 w-full max-w-[340px]">
        
        {{-- BOX LOGIN --}}
        <div class="w-full rounded-[28px] border border-white/10 bg-[#0f0f0f]/80 p-2 shadow-2xl backdrop-blur-2xl">
            <div class="relative flex flex-col gap-1 rounded-[22px] border border-white/5 bg-[#141414]/95 shadow-inner">
                
                {{-- Header --}}
                <div class="px-7 pt-7 pb-2">
                    <h2 class="text-[12px] font-black tracking-[0.3em] text-white uppercase opacity-90">
                        BEMS ACCESS
                    </h2>
                    <div class="h-0.5 w-10 bg-sky-500 mt-1.5 rounded-full"></div>
                </div>
                
                {{-- Form Login --}}
                <form wire:submit.prevent="login" class="flex flex-col gap-4 px-7 pb-8 pt-5">
                    <div class="space-y-1">
                        <div class="w-full rounded-xl border border-white/5 bg-[#1d1d1c] flex items-center h-11 px-4 focus-within:border-sky-500/50 transition-all shadow-inner group text-white">
                            <svg class="w-4 h-4 text-neutral-500 group-focus-within:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                            </svg>
                            <input type="email" wire:model.blur="email" placeholder="Email Address" required
                                class="w-full bg-transparent border-none text-[12px] font-medium text-white placeholder:text-neutral-600 focus:ring-0 pl-3 outline-none" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div class="w-full rounded-xl border border-white/5 bg-[#1d1d1c] flex items-center h-11 px-4 focus-within:border-sky-500/50 transition-all shadow-inner group text-white">
                            <svg class="w-4 h-4 text-neutral-500 group-focus-within:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <input type="password" wire:model="password" wire:keydown.enter="login" placeholder="Password" required
                                class="w-full bg-transparent border-none text-[12px] font-mono tracking-widest text-white placeholder:text-neutral-600 focus:ring-0 pl-3 outline-none" />
                        </div>
                    </div>

                    <button type="submit" class="w-full h-11 mt-1 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs tracking-[0.2em] transition-all flex justify-center items-center shadow-lg active:scale-[0.96]">
                        <span wire:loading.remove wire:target="login">SIGN IN</span>
                        <span wire:loading wire:target="login" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                    </button>
                </form>
            </div>
        </div>

        {{-- 3 ICON BULAT SEMPURNA & PUTIH TERANG --}}
        <div class="flex items-center justify-center gap-12 z-20">
            {{-- GITHUB --}}
            <button type="button" class="group flex items-center justify-center w-12 h-12 flex-shrink-0 rounded-full border-2 border-white/20 hover:border-white transition-all duration-300 hover:scale-110">
                <svg class="w-5 h-5 fill-current text-white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12Z"/>
                </svg>
            </button>
            {{-- GOOGLE --}}
            <button type="button" class="group flex items-center justify-center w-12 h-12 flex-shrink-0 rounded-full border-2 border-white/20 hover:border-white transition-all duration-300 hover:scale-110">
                <svg class="w-5 h-5 fill-current text-white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12.48 10.92v3.28h7.84c-.24 1.84-2 5.36-7.84 5.36-5.08 0-9.2-4.12-9.2-9.2s4.12-9.2 9.2-9.2c2.88 0 4.8 1.2 5.92 2.28l2.6-2.6C18.8 1.48 15.84 0 12.48 0 5.6 0 0 5.6 0 12.48S5.6 24.96 12.48 24.96c6.88 0 12.4-5.28 12.4-12.48 0-.8-.08-1.6-.24-2.28h-12.16z"/>
                </svg>
            </button>
            {{-- EMAIL --}}
            <button type="button" class="group flex items-center justify-center w-12 h-12 flex-shrink-0 rounded-full border-2 border-white/20 hover:border-white transition-all duration-300 hover:scale-110">
                <svg class="w-5 h-5 fill-current text-white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                </svg>
            </button>
        </div>

    </div>
</div>