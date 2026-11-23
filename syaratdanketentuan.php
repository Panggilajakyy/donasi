<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Syarat & Ketentuan — Dimzz</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <style>
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --teal: #20B2AA;
            --teal-dark: #178f88;
            --teal-lite: #e4f7f6;
            --white: #ffffff;
            --bg: #eef5f9;
            --text: #1a2a3a;
            --muted: #7a9aab;
            --shadow: 0 8px 32px rgba(32, 178, 170, 0.13);
            --sidebar-w: 260px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .navbar {
            background: var(--teal);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.12);
        }

        .nav-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .nav-logo img {
            width: 56px;
            height: 56px;
            object-fit: contain;
            border-radius: 12px;
            filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.2));
        }

        .nav-back {
            display: flex;
            align-items: center;
            gap: 6px;
            color: rgba(255, 255, 255, 0.85);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            margin-left: auto;
            background: rgba(255, 255, 255, 0.15);
            padding: 7px 14px;
            border-radius: 50px;
            transition: background 0.2s;
        }

        .nav-back:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .breadcrumb {
            max-width: 1100px;
            margin: 0 auto;
            padding: 16px 24px 0;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--muted);
        }

        .breadcrumb a {
            color: var(--teal);
            text-decoration: none;
            font-weight: 600;
        }

        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: var(--muted); }

        .layout {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 24px 60px;
            display: flex;
            gap: 28px;
            align-items: flex-start;
        }

        .sidebar {
            width: var(--sidebar-w);
            flex-shrink: 0;
            position: sticky;
            top: 80px;
        }

        .sidebar-title {
            font-size: 13px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
            padding: 0 4px;
        }

        .sidebar-nav {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .sidebar-item {
            display: block;
            padding: 13px 18px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
            cursor: pointer;
            border-bottom: 1px solid #f0f5f7;
        }

        .sidebar-item:last-child { border-bottom: none; }
        .sidebar-item:hover { background: var(--teal-lite); color: var(--teal-dark); }
        .sidebar-item.active { background: var(--teal); color: #fff; border-left-color: var(--teal-dark); }

        .content { flex: 1; min-width: 0; }

        .content-card {
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 40px 44px;
        }

        .content-section { display: none; }
        .content-section.active { display: block; }

        .content-header {
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--teal-lite);
        }

        .content-tag {
            display: inline-block;
            background: var(--teal-lite);
            color: var(--teal-dark);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 50px;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .content-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2;
            margin-bottom: 12px;
        }

        .content-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--muted);
        }

        .meta-avatar {
            width: 32px;
            height: 32px;
            background: var(--teal);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .meta-avatar img {
            width: 20px;
            height: 20px;
            object-fit: contain;
        }

        .content-body h3 {
            font-size: 17px;
            font-weight: 800;
            color: var(--text);
            margin: 28px 0 10px;
        }

        .content-body h3:first-child { margin-top: 0; }

        .content-body p {
            font-size: 14px;
            color: #4a6070;
            line-height: 1.85;
            margin-bottom: 14px;
            text-align: justify;
        }

        .content-body ul, .content-body ol { padding-left: 20px; margin-bottom: 14px; }

        .content-body li {
            font-size: 14px;
            color: #4a6070;
            line-height: 1.85;
            margin-bottom: 6px;
        }

        .content-body a { color: var(--teal); font-weight: 600; text-decoration: none; }
        .content-body a:hover { text-decoration: underline; }

        .content-body .intro {
            font-size: 14px;
            color: #4a6070;
            line-height: 1.85;
            font-style: italic;
            margin-bottom: 20px;
        }

        .highlight-box {
            background: var(--teal-lite);
            border-left: 4px solid var(--teal);
            border-radius: 0 12px 12px 0;
            padding: 16px 18px;
            margin: 18px 0;
        }

        .highlight-box p { margin: 0; color: var(--teal-dark); font-style: normal; }

        .mobile-menu-btn {
            display: none;
            width: 100%;
            background: var(--white);
            border: none;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            cursor: pointer;
            box-shadow: var(--shadow);
            margin-bottom: 12px;
            justify-content: space-between;
            align-items: center;
        }

        .mobile-menu-btn svg { transition: transform 0.3s; }
        .mobile-menu-btn.open svg { transform: rotate(180deg); }

        @media (max-width: 768px) {
            .layout { flex-direction: column; padding: 16px 16px 60px; }
            .sidebar { width: 100%; position: static; }
            .mobile-menu-btn { display: flex; }
            .sidebar-nav { display: none; border-radius: 12px; }
            .sidebar-nav.open { display: block; }
            .content-card { padding: 24px 20px; }
            .content-title { font-size: 22px; }
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <a class="nav-logo" href="db.php">
            <img src="logooo.png" alt="Dimzz Logo" />
        </a>
        <a class="nav-back" href="db.php">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="m15 18-6-6 6-6" />
            </svg>
            Kembali ke Beranda
        </a>
    </nav>

    <div class="breadcrumb">
        <a href="db.php">Beranda</a>
        <span>›</span>
        <span id="breadcrumbCurrent">Syarat &amp; Ketentuan</span>
    </div>

    <div class="layout">

        <aside class="sidebar">
            <div class="sidebar-title">Artikel dalam seksi ini</div>
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <span id="mobileMenuLabel">Syarat &amp; Ketentuan Dimzz</span>
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </button>
            <nav class="sidebar-nav" id="sidebarNav">
                <a class="sidebar-item active" data-section="syarat" href="#">Syarat &amp; Ketentuan Dimzz</a>
                <a class="sidebar-item" data-section="ketentuan-umum" href="#">Ketentuan Umum dan Definisi</a>
                <a class="sidebar-item" data-section="privasi" href="#">Kebijakan Privasi</a>
                <a class="sidebar-item" data-section="donatur" href="#">Ketentuan Donatur</a>
                <a class="sidebar-item" data-section="penerima" href="#">Ketentuan Penerima Manfaat</a>
                <a class="sidebar-item" data-section="penggalang" href="#">Ketentuan Penggalang Dana</a>
                <a class="sidebar-item" data-section="larangan" href="#">Konten yang Dilarang</a>
            </nav>
        </aside>

        <main class="content">
            <div class="content-card">

                <!-- SYARAT & KETENTUAN -->
                <section class="content-section active" id="section-syarat">
                    <div class="content-header">
                        <span class="content-tag">Dokumen Resmi</span>
                        <h1 class="content-title">Syarat &amp; Ketentuan Dimzz</h1>
                        <div class="content-meta">
                            <div class="meta-avatar">
                                <img src="logooo.png" alt="Dimzz" />
                            </div>
                            <span>Dimzz.id</span>
                            <span>·</span>
                            <span>Diperbarui: 1 Januari 2025</span>
                        </div>
                    </div>
                    <div class="content-body">
                        <p class="intro">Pembaruan terakhir pada 1 Januari 2025.</p>
                        <p>Hai #OrangBaik, selamat datang di Platform Dimzz!</p>
                        <p>Silakan membaca seluruh ketentuan pada Syarat dan Ketentuan ini dengan seksama sebelum
                            menggunakan Platform Dimzz, karena berdampak kepada hak dan kewajiban Anda selaku Pengguna
                            Platform Dimzz.</p>
                        <div class="highlight-box">
                            <p>Apabila Pengguna melakukan aktivitas seperti mengunjungi, mendaftar, dan/atau menggunakan
                                Platform Dimzz, maka Pengguna menyatakan telah membaca, mengerti, memahami, dan
                                menyetujui semua ketentuan yang berlaku.</p>
                        </div>
                        <p>Syarat dan Ketentuan ini merupakan perjanjian yang sah antara Pengguna dan Dimzz. Apabila
                            Pengguna tidak menyetujui salah satu, sebagian, atau seluruh ketentuan ini, maka Pengguna
                            tidak diperkenankan menggunakan layanan pada Platform Dimzz.</p>
                        <h3>Daftar Ketentuan</h3>
                        <p>Berikut adalah daftar ketentuan lengkap yang dapat Anda baca melalui panel navigasi di
                            sebelah kiri:</p>
                        <ul>
                            <li><a href="#" data-section="ketentuan-umum">Ketentuan Umum dan Definisi</a></li>
                            <li><a href="#" data-section="privasi">Kebijakan Privasi</a></li>
                            <li><a href="#" data-section="donatur">Ketentuan Donatur</a></li>
                            <li><a href="#" data-section="penerima">Ketentuan Penerima Manfaat</a></li>
                            <li><a href="#" data-section="penggalang">Ketentuan Penggalang Dana</a></li>
                            <li><a href="#" data-section="larangan">Konten yang Dilarang</a></li>
                        </ul>
                    </div>
                </section>

                <!-- KETENTUAN UMUM -->
                <section class="content-section" id="section-ketentuan-umum">
                    <div class="content-header">
                        <span class="content-tag">Umum</span>
                        <h1 class="content-title">Ketentuan Umum dan Definisi</h1>
                        <div class="content-meta">
                            <div class="meta-avatar">
                                <img src="logooo.png" alt="Dimzz" />
                            </div>
                            <span>Dimzz.id</span>
                            <span>·</span>
                            <span>Diperbarui: 1 Januari 2025</span>
                        </div>
                    </div>
                    <div class="content-body">
                        <h3>Definisi</h3>
                        <p>Dalam Syarat dan Ketentuan ini, istilah-istilah berikut memiliki arti sebagaimana
                            didefinisikan di bawah ini:</p>
                        <ul>
                            <li><strong>Platform Dimzz</strong> adalah layanan donasi digital yang dioperasikan oleh
                                Dimzz, meliputi website, aplikasi mobile, dan seluruh layanan terkait.</li>
                            <li><strong>Pengguna</strong> adalah setiap orang yang mengakses, mendaftar, dan/atau
                                menggunakan layanan Platform Dimzz.</li>
                            <li><strong>Donatur</strong> adalah Pengguna yang melakukan donasi melalui Platform Dimzz.</li>
                            <li><strong>Penerima Manfaat</strong> adalah individu, kelompok, atau lembaga yang menerima
                                dana dari kampanye donasi.</li>
                            <li><strong>Penggalang Dana</strong> adalah Pengguna yang membuat dan mengelola kampanye
                                donasi di Platform Dimzz.</li>
                            <li><strong>Kampanye</strong> adalah program atau kegiatan pengumpulan donasi yang dibuat di
                                Platform Dimzz.</li>
                        </ul>
                        <h3>Penggunaan Platform</h3>
                        <p>Platform Dimzz hanya dapat digunakan oleh Pengguna yang berusia minimal 17 tahun atau telah
                            mendapat persetujuan dari orang tua/wali. Dimzz berhak menolak atau menangguhkan akses
                            Pengguna yang melanggar ketentuan ini.</p>
                        <h3>Perubahan Ketentuan</h3>
                        <p>Dimzz berhak mengubah Syarat dan Ketentuan ini sewaktu-waktu. Pengguna akan diberitahu
                            melalui email atau notifikasi di platform. Dengan terus menggunakan Platform setelah
                            perubahan berlaku, Pengguna dianggap menyetujui perubahan tersebut.</p>
                    </div>
                </section>

                <!-- KEBIJAKAN PRIVASI -->
                <section class="content-section" id="section-privasi">
                    <div class="content-header">
                        <span class="content-tag">Privasi</span>
                        <h1 class="content-title">Kebijakan Privasi</h1>
                        <div class="content-meta">
                            <div class="meta-avatar">
                                <img src="logooo.png" alt="Dimzz" />
                            </div>
                            <span>Dimzz.id</span>
                            <span>·</span>
                            <span>Diperbarui: 1 Januari 2025</span>
                        </div>
                    </div>
                    <div class="content-body">
                        <p>Dimzz berkomitmen untuk melindungi privasi dan data pribadi setiap Pengguna. Kebijakan
                            Privasi ini menjelaskan bagaimana kami mengumpulkan, menggunakan, dan melindungi informasi Anda.</p>
                        <h3>Data yang Kami Kumpulkan</h3>
                        <ul>
                            <li>Informasi identitas: nama lengkap, alamat email, nomor telepon</li>
                            <li>Informasi pembayaran: nomor rekening, metode pembayaran (tidak termasuk data kartu secara penuh)</li>
                            <li>Data penggunaan: riwayat donasi, kampanye yang dibuat, aktivitas di platform</li>
                            <li>Data teknis: alamat IP, jenis perangkat, browser yang digunakan</li>
                        </ul>
                        <h3>Penggunaan Data</h3>
                        <p>Data yang kami kumpulkan digunakan untuk memproses transaksi donasi, mengirimkan konfirmasi
                            dan laporan, meningkatkan layanan platform, serta memenuhi kewajiban hukum yang berlaku.</p>
                        <div class="highlight-box">
                            <p>Dimzz tidak akan menjual, menyewakan, atau berbagi data pribadi Pengguna kepada pihak
                                ketiga untuk tujuan pemasaran tanpa izin eksplisit dari Pengguna.</p>
                        </div>
                        <h3>Keamanan Data</h3>
                        <p>Dimzz menggunakan enkripsi SSL/TLS untuk melindungi semua data yang dikirimkan melalui
                            platform. Kami secara rutin melakukan audit keamanan untuk memastikan perlindungan data yang optimal.</p>
                        <h3>Hak Pengguna</h3>
                        <p>Pengguna memiliki hak untuk mengakses, memperbarui, dan menghapus data pribadi mereka. Untuk
                            mengajukan permintaan, hubungi kami melalui halaman Pusat Bantuan.</p>
                    </div>
                </section>

                <!-- KETENTUAN DONATUR -->
                <section class="content-section" id="section-donatur">
                    <div class="content-header">
                        <span class="content-tag">Donatur</span>
                        <h1 class="content-title">Ketentuan Donatur</h1>
                        <div class="content-meta">
                            <div class="meta-avatar">
                                <img src="logooo.png" alt="Dimzz" />
                            </div>
                            <span>Dimzz.id</span>
                            <span>·</span>
                            <span>Diperbarui: 1 Januari 2025</span>
                        </div>
                    </div>
                    <div class="content-body">
                        <p>Sebagai Donatur di Platform Dimzz, Anda menyetujui ketentuan-ketentuan berikut:</p>
                        <h3>Proses Donasi</h3>
                        <p>Donasi yang telah berhasil diproses bersifat final dan tidak dapat dikembalikan, kecuali
                            dalam kondisi tertentu seperti kampanye yang dibatalkan atau terbukti melanggar ketentuan platform.</p>
                        <ul>
                            <li>Donasi dapat dilakukan tanpa membuat akun untuk donasi anonim</li>
                            <li>Donasi dengan akun akan tersimpan dalam riwayat transaksi</li>
                            <li>Konfirmasi donasi akan dikirimkan melalui email</li>
                            <li>Bukti donasi dapat diunduh melalui dashboard Pengguna</li>
                        </ul>
                        <h3>Biaya Layanan</h3>
                        <p>Dimzz menerapkan biaya layanan sebesar <strong>5%</strong> dari total donasi untuk mendukung
                            operasional platform. Biaya ini sudah termasuk dalam nominal yang Anda masukkan, bukan biaya tambahan.</p>
                        <div class="highlight-box">
                            <p>Donatur dapat memilih untuk menanggung biaya layanan secara terpisah agar 100% donasi
                                tersalurkan kepada penerima manfaat.</p>
                        </div>
                        <h3>Transparansi Dana</h3>
                        <p>Seluruh dana yang terkumpul akan dilaporkan secara transparan melalui laporan penggunaan dana
                            yang dipublikasikan oleh Penggalang Dana. Dimzz berhak melakukan audit atas penggunaan dana tersebut.</p>
                    </div>
                </section>

                <!-- KETENTUAN PENERIMA -->
                <section class="content-section" id="section-penerima">
                    <div class="content-header">
                        <span class="content-tag">Penerima</span>
                        <h1 class="content-title">Ketentuan Penerima Manfaat</h1>
                        <div class="content-meta">
                            <div class="meta-avatar">
                                <img src="logooo.png" alt="Dimzz" />
                            </div>
                            <span>Dimzz.id</span>
                            <span>·</span>
                            <span>Diperbarui: 1 Januari 2025</span>
                        </div>
                    </div>
                    <div class="content-body">
                        <h3>Kriteria Penerima Manfaat</h3>
                        <p>Penerima Manfaat adalah individu, komunitas, atau lembaga yang berhak menerima dana dari
                            kampanye donasi yang telah diverifikasi oleh Dimzz. Penerima manfaat harus memenuhi kriteria berikut:</p>
                        <ul>
                            <li>Memiliki kebutuhan yang nyata dan dapat diverifikasi</li>
                            <li>Memberikan dokumen pendukung yang sah dan akurat</li>
                            <li>Bersedia memberikan laporan penggunaan dana</li>
                            <li>Tidak terlibat dalam kegiatan ilegal atau melanggar hukum</li>
                        </ul>
                        <h3>Pencairan Dana</h3>
                        <p>Dana akan dicairkan kepada Penerima Manfaat setelah melalui proses verifikasi yang ketat.
                            Pencairan dilakukan secara bertahap sesuai dengan perkembangan dan kebutuhan program.</p>
                        <div class="highlight-box">
                            <p>Penerima Manfaat wajib memberikan laporan penggunaan dana dalam waktu 30 hari setelah
                                pencairan setiap tahap. Kegagalan memberikan laporan dapat mengakibatkan penangguhan
                                pencairan dana berikutnya.</p>
                        </div>
                    </div>
                </section>

                <!-- KETENTUAN PENGGALANG -->
                <section class="content-section" id="section-penggalang">
                    <div class="content-header">
                        <span class="content-tag">Penggalang Dana</span>
                        <h1 class="content-title">Ketentuan Penggalang Dana</h1>
                        <div class="content-meta">
                            <div class="meta-avatar">
                                <img src="logooo.png" alt="Dimzz" />
                            </div>
                            <span>Dimzz.id</span>
                            <span>·</span>
                            <span>Diperbarui: 1 Januari 2025</span>
                        </div>
                    </div>
                    <div class="content-body">
                        <p>Penggalang Dana bertanggung jawab penuh atas kebenaran informasi kampanye yang dibuat dan
                            pengelolaan dana yang terkumpul.</p>
                        <h3>Kewajiban Penggalang Dana</h3>
                        <ul>
                            <li>Memberikan informasi yang jujur, akurat, dan tidak menyesatkan</li>
                            <li>Memperbarui perkembangan kampanye secara berkala</li>
                            <li>Menggunakan dana sesuai dengan tujuan kampanye yang telah ditetapkan</li>
                            <li>Memberikan laporan pertanggungjawaban penggunaan dana</li>
                            <li>Merespons pertanyaan dari Donatur dalam waktu yang wajar</li>
                        </ul>
                        <h3>Verifikasi Kampanye</h3>
                        <p>Dimzz berhak melakukan verifikasi atas setiap kampanye yang dibuat. Kampanye yang tidak lulus
                            verifikasi tidak akan dipublikasikan. Proses verifikasi membutuhkan waktu 1–3 hari kerja.</p>
                        <h3>Penghentian Kampanye</h3>
                        <p>Dimzz berhak menghentikan kampanye yang terbukti melanggar ketentuan, mengandung informasi
                            palsu, atau berpotensi merugikan Donatur. Dana yang telah terkumpul akan dikembalikan kepada
                            Donatur dalam kondisi ini.</p>
                        <div class="highlight-box">
                            <p>Penggalang Dana yang terbukti melakukan penipuan akan dilaporkan kepada pihak berwajib
                                dan diblokir permanen dari Platform Dimzz.</p>
                        </div>
                    </div>
                </section>

                <!-- LARANGAN -->
                <section class="content-section" id="section-larangan">
                    <div class="content-header">
                        <span class="content-tag">Larangan</span>
                        <h1 class="content-title">Konten yang Dilarang</h1>
                        <div class="content-meta">
                            <div class="meta-avatar">
                                <img src="logooo.png" alt="Dimzz" />
                            </div>
                            <span>Dimzz.id</span>
                            <span>·</span>
                            <span>Diperbarui: 1 Januari 2025</span>
                        </div>
                    </div>
                    <div class="content-body">
                        <p>Platform Dimzz tidak memperbolehkan kampanye atau konten yang mengandung unsur-unsur berikut:</p>
                        <h3>Konten Terlarang</h3>
                        <ul>
                            <li>Penipuan, informasi palsu, atau klaim yang tidak dapat dibuktikan</li>
                            <li>Ujaran kebencian berdasarkan ras, agama, gender, atau orientasi seksual</li>
                            <li>Konten pornografi atau eksploitasi seksual</li>
                            <li>Kampanye untuk kegiatan ilegal, terlarang, atau melanggar hukum Indonesia</li>
                            <li>Pendanaan untuk organisasi teroris atau kegiatan yang mengancam keamanan negara</li>
                            <li>Kampanye yang melanggar hak kekayaan intelektual pihak lain</li>
                            <li>Konten yang mempromosikan kekerasan atau penganiayaan</li>
                        </ul>
                        <h3>Sanksi Pelanggaran</h3>
                        <p>Pelanggaran terhadap ketentuan ini dapat mengakibatkan:</p>
                        <ol>
                            <li>Penghapusan konten atau kampanye tanpa pemberitahuan</li>
                            <li>Penangguhan atau pemblokiran akun</li>
                            <li>Pengembalian dana kepada Donatur</li>
                            <li>Pelaporan kepada pihak berwajib jika diperlukan</li>
                        </ol>
                        <div class="highlight-box">
                            <p>Jika Anda menemukan konten yang melanggar ketentuan ini, harap laporkan melalui Pusat
                                Bantuan kami. Dimzz berkomitmen untuk menindaklanjuti setiap laporan dalam 24 jam.</p>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <script>
        const items = document.querySelectorAll('.sidebar-item');
        const sections = document.querySelectorAll('.content-section');
        const breadcrumb = document.getElementById('breadcrumbCurrent');
        const mobileBtn = document.getElementById('mobileMenuBtn');
        const mobileLabel = document.getElementById('mobileMenuLabel');
        const sidebarNav = document.getElementById('sidebarNav');

        function switchSection(sectionId, label) {
            sections.forEach(s => s.classList.remove('active'));
            items.forEach(i => i.classList.remove('active'));
            const target = document.getElementById('section-' + sectionId);
            if (target) target.classList.add('active');
            const activeItem = document.querySelector(`[data-section="${sectionId}"]`);
            if (activeItem) activeItem.classList.add('active');
            breadcrumb.textContent = label;
            mobileLabel.textContent = label;
            if (window.innerWidth <= 768) {
                sidebarNav.classList.remove('open');
                mobileBtn.classList.remove('open');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        items.forEach(item => {
            item.addEventListener('click', e => {
                e.preventDefault();
                switchSection(item.dataset.section, item.textContent.trim());
            });
        });

        document.querySelectorAll('.content-body a[data-section]').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                const section = link.dataset.section;
                const sidebarItem = document.querySelector(`[data-section="${section}"]`);
                const label = sidebarItem ? sidebarItem.textContent.trim() : section;
                switchSection(section, label);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        mobileBtn.addEventListener('click', () => {
            sidebarNav.classList.toggle('open');
            mobileBtn.classList.toggle('open');
        });
    </script>
</body>

</html>