<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Mary\Traits\Toast;
use Livewire\Attributes\Layout;

new #[Layout('layouts.guest')] class extends Component
{
    // Menggunakan trait Toast dari library MaryUI untuk menampilkan notifikasi.
    use Toast;

    // Properti publik yang di-binding ke input form.
    public $email = '';
    public $password = '';

    // Aturan validasi untuk properti email dan password.
    protected $rules = [
        'email' => 'required|email',
        'password' => 'required|min:6',
    ];

    // Fungsi yang dipanggil saat form di-submit.
    public function login()
    {
        // Menjalankan validasi berdasarkan $rules.
        $this->validate();

        if (Auth::attempt([
            'email' => $this->email,
            'password' => $this->password
        ])) {

            // Jika berhasil, regenerasi session untuk keamanan.
            session()->regenerate();

            $user = Auth::user();
            $role = strtolower(trim($user->role ?? ''));

            // Arahkan ke dashboard spesifik berdasarkan role masing-masing
            if ($role === 'admin' || (method_exists($user, 'hasRole') && $user->hasRole('admin'))) {
                return redirect()->to('/admin');
            } elseif ($role === 'maintenance') {
                return redirect()->to('/maintenance');
            } elseif ($role === 'operator') {
                return redirect()->to('/operator');
            } elseif ($role === 'viewer') {
                return redirect()->to('/viewer');
            } elseif ($role === 'client') {
                return redirect()->to('/client');
            }

            // Redirect fallback jika role tidak dikenali
            return redirect()->intended('/');
        }

        // Jika autentikasi gagal, tampilkan pesan error menggunakan toast.
        $this->error(
            'Email atau password salah',
            position: 'toast-top toast-center'
        );
    }
};
?>

<div 
    x-data="{ 
        darkMode: localStorage.getItem('darkMode') === 'false' ? false : true 
    }"
    x-init="
        if(darkMode){
            document.documentElement.classList.add('dark')
        } else {
            document.documentElement.classList.remove('dark')
        }
    "
    :class="darkMode ? 'bg-black' : 'bg-zinc-100'"
    class="relative min-h-screen overflow-hidden flex items-center justify-center transition-all duration-500"
>

    {{-- Latar Belakang Hexagon Interaktif --}}
    <div id="hexagon-container"
         class="absolute inset-0 overflow-hidden z-0">
        {{-- Grid untuk menampung semua hexagon --}}
        <div id="hexagon-grid"
             class="absolute inset-0">
        </div>
    </div>

    {{-- Efek Cahaya di Latar Belakang --}}
    <div class="absolute inset-0">
        <div class="absolute top-[-200px] left-[-200px] w-[500px] h-[500px] bg-green-500/10 blur-3xl rounded-full"></div>
        <div class="absolute bottom-[-200px] right-[-200px] w-[500px] h-[500px] bg-emerald-500/10 blur-3xl rounded-full"></div>
    </div>

    {{-- TOGGLE DARK/LIGHT MODE --}}
    <div class="absolute top-6 right-6 z-20">

        <button
            @click="
                darkMode = !darkMode;

                localStorage.setItem('darkMode', darkMode);

                if(darkMode){
                    document.documentElement.classList.add('dark')
                } else {
                    document.documentElement.classList.remove('dark')
                }
            "
            class="w-14 h-14 rounded-2xl border border-white/10 backdrop-blur-xl
            flex items-center justify-center transition-all duration-300
            hover:scale-105"
            :class="darkMode 
                ? 'bg-[#111]/80 text-white hover:bg-[#1b1b1b]' 
                : 'bg-white text-black hover:bg-zinc-200 border-zinc-300'"
        >

            {{-- ICON MOON --}}
            <template x-if="darkMode">
                <svg xmlns="http://www.w3.org/2000/svg"
                    class="w-6 h-6"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor">

                    <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 12.79A9 9 0 1111.21 3
                        7 7 0 0021 12.79z"/>
                </svg>
            </template>

            {{-- ICON SUN --}}
            <template x-if="!darkMode">
                <svg xmlns="http://www.w3.org/2000/svg"
                    class="w-6 h-6"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor">

                    <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364
                        6.364l-1.414-1.414M7.05
                        7.05 5.636 5.636m12.728
                        0L16.95 7.05M7.05
                        16.95l-1.414 1.414M12
                        8a4 4 0 100 8 4 4 0 000-8z"/>
                </svg>
            </template>

        </button>

    </div>

    {{-- Kartu Login --}}
    <div class="relative z-10 w-[380px]">

        <div
            :class="darkMode
                ? 'bg-[#0b0b0b]/90 border-white/10 shadow-[0_0_50px_rgba(0,0,0,0.7)]'
                : 'bg-white/90 border-zinc-200 shadow-[0_0_50px_rgba(0,0,0,0.08)]'"
            class="border rounded-3xl p-6 backdrop-blur-2xl transition-all duration-500"
        >

            {{-- Header Kartu --}}
            <div class="mb-6">
                <h1
                    :class="darkMode ? 'text-white' : 'text-zinc-900'"
                    class="text-2xl font-semibold tracking-tight transition-all duration-300"
                >
                    Login Page
                </h1>
            </div>

            {{-- Form Login --}}
            {{-- `wire:submit.prevent="login"` akan memanggil method `login` di komponen Livewire saat form disubmit. --}}
            <form wire:submit.prevent="login" class="space-y-4">

                {{-- Input Email --}}
                <div class="relative">

                    {{-- `wire:model.live="email"` akan mengikat nilai input ini ke properti `$email` secara real-time. --}}
                    <input
                        wire:model.live="email"
                        type="email"
                        placeholder="Username or email"
                        :class="darkMode
                            ? 'bg-[#101010] border-white/10 text-white placeholder:text-zinc-500'
                            : 'bg-zinc-100 border-zinc-300 text-zinc-900 placeholder:text-zinc-400'"
                        class="w-full h-14 border rounded-2xl px-5 pr-14 focus:outline-none focus:ring-2 focus:ring-green-500/30 focus:border-green-500 transition-all"
                    >

                    {{-- Ikon Sukses (Centang) --}}
                    @if(filter_var($email, FILTER_VALIDATE_EMAIL))
                        <div class="absolute right-4 top-1/2 -translate-y-1/2">
                            <div class="w-6 h-6 rounded-full bg-green-500 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="w-4 h-4 text-black"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor">

                                    <path stroke-linecap="round"
                                          stroke-linejoin="round"
                                          stroke-width="3"
                                          d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Input Password --}}
                <div class="relative" x-data="{ showPassword: false }">

                    {{-- `wire:model="password"` akan mengikat nilai input ini ke properti `$password`. --}}
                    <input
                        wire:model="password"
                        :type="showPassword ? 'text' : 'password'"
                        placeholder="Masukan password"
                        :class="darkMode ? 'bg-[#101010] border-white/10 text-white placeholder:text-zinc-500' : 'bg-zinc-100 border-zinc-300 text-zinc-900 placeholder:text-zinc-400'"
                        {{-- Padding kanan dinaikkan jadi pr-24 agar teks tidak tertutup ikon --}}
                        class="w-full h-14 border rounded-2xl px-5 pr-24 focus:outline-none focus:ring-2 focus:ring-green-500/30 focus:border-green-500 transition-all text-sm"
                    >
                    
                    {{-- Kontainer Ikon di Sebelah Kanan --}}
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 flex items-center gap-2.5">
                        {{-- Tombol Mata Toggling --}}
                        <button type="button" @click="showPassword = !showPassword" class="text-zinc-500 hover:text-zinc-400 focus:outline-none transition-colors">
                            <template x-if="!showPassword">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </template>
                            <template x-if="showPassword">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </template>
                        </button>

                        {{-- Loading Spinner --}}
                        <div wire:loading wire:target="login">
                            <div class="w-5 h-5 border-2 border-green-500 border-t-transparent rounded-full animate-spin"></div>
                        </div>
                    </div>
                </div>

                {{-- Tombol Submit --}}
                <button
                    type="submit"
                    :class="darkMode
                        ? 'bg-white text-black hover:bg-zinc-200'
                        : 'bg-zinc-900 text-white hover:bg-black'"
                    <button type="submit" class="w-full h-14 mt-2 rounded-2xl font-semibold bg-zinc-900 text-white dark:bg-white dark:text-black hover:opacity-90 transition-all duration-300 active:scale-[0.98]">
                    Sign In
                </button>

            </form>

            {{-- Pemisah dan Opsi Login Lain --}}
            <div class="mt-6 border-t border-white/5 pt-6">

                <div class="flex items-center justify-center gap-5">

                    {{-- Tombol Login via Github (UI Saja) --}}
                    <button
                        class="flex-shrink-0 w-14 h-14 rounded-full bg-[#151515] hover:bg-[#1d1d1d] border border-white/5 flex items-center justify-center transition-all hover:scale-105"
                    >
                        <svg class="w-7 h-7 text-white"
                             fill="currentColor"
                             viewBox="0 0 24 24">

                            <path d="M12 0C5.37 0 0 5.37 0 12a12 12 0 008.21 11.39c.6.11.82-.26.82-.58v-2.04c-3.34.73-4.04-1.61-4.04-1.61-.55-1.39-1.34-1.76-1.34-1.76-1.09-.74.08-.73.08-.73 1.2.09 1.84 1.24 1.84 1.24 1.07 1.83 2.8 1.3 3.48.99.11-.78.42-1.31.76-1.61-2.66-.3-5.47-1.33-5.47-5.93 0-1.31.47-2.38 1.24-3.22-.12-.3-.54-1.52.12-3.17 0 0 1-.32 3.3 1.23a11.5 11.5 0 016 0c2.3-1.55 3.3-1.23 3.3-1.23.66 1.65.24 2.87.12 3.17.77.84 1.24 1.91 1.24 3.22 0 4.61-2.81 5.62-5.49 5.92.43.37.82 1.1.82 2.22v3.29c0 .32.22.7.82.58A12 12 0 0024 12c0-6.63-5.37-12-12-12z"/>
                        </svg>
                    </button>

                    {{-- Tombol Login via Gmail (UI Saja) --}}
                    <button
                        class="flex-shrink-0 w-14 h-14 rounded-full bg-[#151515] hover:bg-[#1d1d1d] border border-white/5 flex items-center justify-center transition-all hover:scale-105"
                    >
                        <svg class="w-7 h-7"
                            viewBox="0 0 24 24"
                            fill="none">

                            <path d="M3 5.5L12 13L21 5.5"
                                stroke="#EA4335"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"/>

                            <path d="M3 5.5V18H21V5.5"
                                stroke="#FFFFFF"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"/>
                        </svg>
                    </button>

                    {{-- Tombol Login via Apple (UI Saja) --}}
                    <button
                        class="flex-shrink-0 w-14 h-14 rounded-full bg-[#151515] hover:bg-[#1d1d1d] border border-white/5 flex items-center justify-center transition-all hover:scale-105"
                    >
                        <svg class="w-7 h-7 text-white"
                            fill="currentColor"
                            viewBox="0 0 24 24">

                            <path d="M16.365 1.43c0 1.14-.465 2.206-1.255 2.98-.84.82-2.015 1.29-3.12 1.2-.14-1.08.4-2.23 1.16-2.98.82-.8 2.12-1.38 3.215-1.2zM20.485 17.22c-.565 1.26-.835 1.82-1.565 2.96-1.015 1.59-2.445 3.57-4.215 3.585-1.575.015-1.98-1.02-4.12-1.005-2.145.015-2.59 1.02-4.165 1.005-1.77-.015-3.125-1.8-4.14-3.39-2.84-4.455-3.135-9.675-1.385-12.36 1.245-1.905 3.21-3.03 5.055-3.03 1.885 0 3.07 1.035 4.63 1.035 1.515 0 2.44-1.04 4.615-1.04 1.645 0 3.39.9 4.63 2.46-4.06 2.225-3.4 8.015.66 9.78z"/>
                        </svg>
                    </button>

                    {{-- Tombol Login via Youtube (UI Saja) --}}
                    <button
                        class="flex-shrink-0 w-14 h-14 rounded-full bg-[#151515] hover:bg-[#1d1d1d] border border-white/5 flex items-center justify-center transition-all hover:scale-105"
                    >
                        <svg class="w-7 h-7 text-red-500"
                             fill="currentColor"
                             viewBox="0 0 24 24">

                            <path d="M23.5 6.2a3 3 0 00-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.5A3 3 0 00.5 6.2 31.4 31.4 0 000 12a31.4 31.4 0 00.5 5.8 3 3 0 002.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 002.1-2.1A31.4 31.4 0 0024 12a31.4 31.4 0 00-.5-5.8zM9.5 15.6V8.4L15.8 12l-6.3 3.6z"/>
                        </svg>
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>

<style>
    /*
     * ===============================================
     * STYLING UNTUK LATAR BELAKANG HEXAGON
     * ===============================================
     */

    /* Kontainer utama untuk latar belakang hexagon. */
    #hexagon-container {
    position: absolute;
    inset: 0;
    overflow: hidden;
    z-index: 0;
    background: #000;
    }

    /* Efek cahaya hijau yang menyebar dari tengah. */
    #hexagon-container::after {

        content: '';

        position: absolute;

        inset: 0;

        pointer-events: none;

        background:
            radial-gradient(
                circle at center,
                rgba(34,197,94,0.05),
                transparent 70%
            );
    }

    /* Grid yang akan diisi dengan hexagon oleh JavaScript. */
    #hexagon-grid {
        position: absolute;
        inset: 0;
    }

    /* Styling dasar untuk setiap hexagon. */
    .hexagon {

    position: absolute;

    width: 90px;
    height: 52px;

    background: #0a0a0a;

    /* Border kiri dan kanan untuk membentuk sisi hexagon. */
    border-left: 1px solid rgba(255,255,255,0.06);
    border-right: 1px solid rgba(255,255,255,0.06);

    box-sizing: border-box;

    /* Transisi halus untuk perubahan properti. */
    transition:
        background 0.22s cubic-bezier(0.4,0,0.2,1),
        border-color 0.22s cubic-bezier(0.4,0,0.2,1),
        box-shadow 0.22s cubic-bezier(0.4,0,0.2,1),
        transform 0.22s cubic-bezier(0.4,0,0.2,1);

    will-change: transform;
    }

    /* Pseudo-elements untuk membuat bagian atas dan bawah hexagon. */
    .hexagon::before,
    .hexagon::after {

    content: '';

    position: absolute;

    width: 63.64px;
    height: 63.64px;

    left: 12.18px;

    background: inherit; /* Mewarisi background dari .hexagon */

    /* Transformasi untuk memutar dan menskalakan persegi menjadi segitiga. */
    transform: scaleY(0.5774) rotate(-45deg);

    z-index: 1;
    }

    /* Styling untuk bagian atas hexagon. */
    .hexagon::before {

    top: -31.82px;

    border-top: 1px solid rgba(255,255,255,0.06);

    border-right: 1px solid rgba(255,255,255,0.06);
    }

    /* Styling untuk bagian bawah hexagon. */
    .hexagon::after {

    bottom: -31.82px;

    border-bottom: 1px solid rgba(255,255,255,0.06);

    border-left: 1px solid rgba(255,255,255,0.06);
    }

    /* Styling untuk hexagon yang aktif (saat mouse berada di dekatnya). */
    .hexagon.active {

    background: rgba(34,197,94,0.12);

    border-left-color: rgba(34,197,94,0.35);
    border-right-color: rgba(34,197,94,0.35);

    box-shadow:
        0 0 12px rgba(34,197,94,0.18),
        0 0 24px rgba(34,197,94,0.08);

    transform: scale(1.02);

    z-index: 2;
    }

    /* Menyesuaikan border untuk pseudo-elements saat aktif. */
    .hexagon.active::before {

    border-top-color: rgba(34,197,94,0.35);

    border-right-color: rgba(34,197,94,0.35);
}

    .hexagon.active::after {

    border-bottom-color: rgba(34,197,94,0.35);

    border-left-color: rgba(34,197,94,0.35);
    }

</style>

<script>
/**
 * =================================================================
 * SCRIPT UNTUK MENGELOLA LATAR BELAKANG HEXAGON INTERAKTIF
 * =================================================================
 */

// Fungsi untuk menginisialisasi latar belakang hexagon.
function initHexagonBackground() {

    const grid = document.getElementById('hexagon-grid');

    // Hentikan jika elemen grid tidak ditemukan.
    if (!grid) return;

    // Kosongkan grid sebelum membuat yang baru (untuk resize).
    grid.innerHTML = '';

    // Dimensi dan spasi untuk setiap hexagon.
    const hexWidth = 90;
    const hexHeight = 100;

    const verticalSpacing = 78;
    const horizontalSpacing = 68;

    // Hitung jumlah kolom dan baris yang dibutuhkan untuk mengisi layar.
    const cols = Math.ceil(window.innerWidth / horizontalSpacing) + 2;
    const rows = Math.ceil(window.innerHeight / verticalSpacing) + 2;

    // Array untuk menyimpan data setiap hexagon (elemen dan posisi).
    const hexagons = [];

    // Loop untuk membuat grid hexagon.
    for (let row = 0; row < rows; row++) {

        for (let col = 0; col < cols; col++) {

            const hex = document.createElement('div');

            hex.classList.add('hexagon');

            // Hitung posisi x dan y untuk setiap hexagon.
            // Baris ganjil digeser sedikit untuk menciptakan pola sarang lebah.
            const x =
                col * horizontalSpacing +
                (row % 2 ? 39 : 0);

            const y = row * 78;

            hex.style.left = `${x - 50}px`;
            hex.style.top = `${y - 40}px`;

            grid.appendChild(hex);

            // Tambahkan delay transisi acak untuk efek visual yang lebih dinamis.
            hex.style.transitionDelay = `${Math.random() * 0.15}s`;

            // Gunakan requestAnimationFrame untuk mendapatkan posisi setelah elemen di-render.
            requestAnimationFrame(() => {

                const rect = hex.getBoundingClientRect();

                // Simpan elemen dan koordinat tengahnya.
                hexagons.push({
                    element: hex,
                    x: rect.left + rect.width / 2,
                    y: rect.top + rect.height / 2
                });
            });
        }
    }

    // Tambahkan event listener untuk gerakan mouse.
    window.addEventListener('mousemove', (e) => {

        // Iterasi melalui setiap hexagon untuk memeriksa jaraknya dari kursor.
        hexagons.forEach((hex) => {

            const dx = e.clientX - hex.x;
            const dy = e.clientY - hex.y;

            // Hitung intensitas efek berdasarkan jarak (semakin dekat, semakin kuat).
            const distance = Math.sqrt(dx * dx + dy * dy);
            const intensity = Math.max(0, 1 - distance / 50);

            // Jika kursor cukup dekat, aktifkan hexagon.
            if (distance < 40) {

                hex.element.classList.add('active');

                // Opacity diatur berdasarkan intensitas, tapi ini mungkin tidak terlihat
                // karena class 'active' tidak menggunakan opacity.
                // Mungkin ini sisa dari eksperimen sebelumnya.
                hex.element.style.opacity = intensity;

            } else {

                // Jika kursor jauh, nonaktifkan hexagon.
                hex.element.classList.remove('active');

                // Reset opacity ke 1.
                hex.element.style.opacity = 1;
            }
        });
    });
}

// Panggil fungsi inisialisasi saat halaman selesai dimuat.
window.addEventListener('load', initHexagonBackground);

// Panggil ulang fungsi inisialisasi saat ukuran jendela diubah.
window.addEventListener('resize', () => {

    document.getElementById('hexagon-grid').innerHTML = '';

    initHexagonBackground();
});

document.addEventListener('livewire:navigated', () => {

    document.getElementById('hexagon-grid').innerHTML = '';

    initHexagonBackground();
});

</script>