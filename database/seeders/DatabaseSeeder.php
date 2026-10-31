<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\Product;
use App\Models\ProductModule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin Default
        $admin = User::firstOrCreate(
            ['email' => 'admin@switchcode.id'],
            [
                'name' => 'Switch Code Admin',
                'password' => Hash::make('AdminSwitchCode2026!'),
                'role' => User::ROLE_ADMIN,
            ]
        );

        // 2. Customer Demo
        User::firstOrCreate(
            ['email' => 'customer@switchcode.id'],
            [
                'name' => 'Demo Customer',
                'password' => Hash::make('Customer2026!'),
                'role' => User::ROLE_CUSTOMER,
            ]
        );

        // 3. Dummy Product: Website Company Profile
        $product1 = Product::firstOrCreate(
            ['slug' => 'company-profile-pro'],
            [
                'name' => 'Company Profile Pro',
                'summary' => 'Website profesional modern untuk branding perusahaan & instansi dengan kecepatan tinggi.',
                'description' => 'Paket website korporat lengkap berbasis Next.js dan Tailwind CSS, SEO ready, mobile-first, dan terintegrasi sistem CMS.',
                'base_price' => 2500000,
                'is_active' => true,
            ]
        );

        $modules1 = [
            ['name' => 'Multi-Language (ID / EN)', 'description' => 'Fitur alih bahasa bilingual otomatis dan manual translation', 'price' => 500000, 'is_required' => false],
            ['name' => 'WhatsApp Floating Chatbot', 'description' => 'Widget interaktif WhatsApp dengan greeting pesan otomatis', 'price' => 250000, 'is_required' => false],
            ['name' => 'Custom CMS Portal Berita & Artikel', 'description' => 'Dashboard admin upload berita, galeri, dan dokumentasi kegiatan', 'price' => 1200000, 'is_required' => false],
            ['name' => 'SSL & Domain Setup (.id / .com)', 'description' => 'Konfigurasi domain dan sertifikat keamanan SSL 1 tahun', 'price' => 350000, 'is_required' => true],
        ];

        foreach ($modules1 as $idx => $mod) {
            ProductModule::firstOrCreate(
                ['product_id' => $product1->id, 'name' => $mod['name']],
                array_merge($mod, ['sort_order' => $idx])
            );
        }

        // 4. Dummy Product: Sistem Informasi & ERP Lite
        $product2 = Product::firstOrCreate(
            ['slug' => 'sistem-informasi-kustom'],
            [
                'name' => 'Sistem Informasi & Manajemen Operasional',
                'summary' => 'Aplikasi internal custom untuk pencatatan inventaris, transaksi, dan pelaporan otomatis.',
                'description' => 'Solusi digitalisasi alur kerja perusahaan berbasis web. Aman, modular, dan dapat dikembangkan sesuai kebutuhan SOP bisnis.',
                'base_price' => 5000000,
                'is_active' => true,
            ]
        );

        $modules2 = [
            ['name' => 'Modul Multi-Role & Permissions (RBAC)', 'description' => 'Hak akses bertingkat untuk Superadmin, Manager, Staff, dan Keuangan', 'price' => 1500000, 'is_required' => true],
            ['name' => 'Modul Export Laporan PDF & Excel', 'description' => 'Generate laporan rekap berkala dengan format tabel rapi', 'price' => 750000, 'is_required' => false],
            ['name' => 'Integrasi Payment Gateway (DOKU / Midtrans)', 'description' => 'Pembayaran otomatis via Virtual Account, QRIS, dan Kartu Kredit', 'price' => 2000000, 'is_required' => false],
            ['name' => 'Modul Notifikasi Telegram / WhatsApp Bot', 'description' => 'Notifikasi real-time transaksi & status persetujuan ke chat group', 'price' => 850000, 'is_required' => false],
        ];

        foreach ($modules2 as $idx => $mod) {
            ProductModule::firstOrCreate(
                ['product_id' => $product2->id, 'name' => $mod['name']],
                array_merge($mod, ['sort_order' => $idx])
            );
        }

        // 5. Dummy Article
        Article::firstOrCreate(
            ['slug' => 'peluncuran-ekosistem-switch-code-2026'],
            [
                'author_id' => $admin->id,
                'title' => 'Peluncuran Ekosistem Digital Switch Code 2026',
                'summary' => 'Switch Code resmi memperkenalkan solusi rekayasa perangkat lunak modern untuk akselerasi bisnis di Indonesia.',
                'content' => 'Di era transformasi digital yang serba cepat, Switch Code hadir sebagai mitra teknologi strategis yang menyediakan solusi rekayasa software mulai dari arsitektur cloud, web portal generasi terbaru, hingga otomatisasi workflow bisnis.',
                'is_published' => true,
                'published_at' => now(),
            ]
        );
    }
}
