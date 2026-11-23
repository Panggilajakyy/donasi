<?php
include 'koneksi.php';

// ── 1. Ambil kategori ────────────────────────────────────────
$result_kategori = mysqli_query(
  $conn,
  "SELECT id, nama_kategori, slug, icon_svg, warna_badge, halaman_url
     FROM kategori_donasi
     WHERE is_active = 1
     ORDER BY urutan ASC"
);

// ── 2. Ambil program donasi ──────────────────────────────────
$result_program = mysqli_query(
  $conn,
  "SELECT
        p.id,
        p.judul_program,
        p.judul_banner,
        p.deskripsi_singkat,
        p.gambar_banner,
        p.gambar_thumbnail,
        p.target_donasi,
        p.donasi_terkumpul,
        p.jumlah_donatur,
        p.sisa_hari,
        p.progress_percent,
        p.is_featured,
        p.urutan,
        k.nama_kategori,
        k.slug          AS kategori_slug,
        k.warna_badge
     FROM program_donasi p
     LEFT JOIN kategori_donasi k ON p.kategori_id = k.id
     WHERE p.status = 'aktif'
     ORDER BY p.urutan ASC"
);

// ── 3. Susun array programs ──────────────────────────────────
$programs = [];
if ($result_program) {
  while ($row = mysqli_fetch_assoc($result_program)) {

    // Persen — pakai kolom progress_percent kalau ada, fallback hitung manual
    $persen = 0;
    if (!empty($row['progress_percent'])) {
      $persen = (int) $row['progress_percent'];
    } elseif ($row['target_donasi'] > 0) {
      $persen = (int) round(($row['donasi_terkumpul'] / $row['target_donasi']) * 100);
    }

    // Format rupiah
    $terkumpul = (float) $row['donasi_terkumpul'];
    if ($terkumpul >= 1_000_000_000)
      $label = 'Rp ' . round($terkumpul / 1_000_000_000, 1) . ' M';
    elseif ($terkumpul >= 1_000_000)
      $label = 'Rp ' . round($terkumpul / 1_000_000) . ' jt';
    elseif ($terkumpul >= 1_000)
      $label = 'Rp ' . round($terkumpul / 1_000) . ' rb';
    else
      $label = 'Rp ' . number_format($terkumpul, 0, ',', '.');

    // Gambar: utamakan gambar_banner, fallback thumbnail
    $gambar = !empty($row['gambar_banner']) ? $row['gambar_banner'] : ($row['gambar_thumbnail'] ?? '');

    // Judul: utamakan judul_banner untuk display singkat, fallback judul_program
    $judul = !empty($row['judul_banner']) ? $row['judul_banner'] : $row['judul_program'];

    $programs[] = [
      'id' => (int) $row['id'],
      'judul' => $judul,
      'judul_program' => $row['judul_program'],
      'deskripsi_pendek' => $row['deskripsi_singkat'] ?? '',
      'gambar' => $gambar,
      'kategori' => $row['nama_kategori'] ?? 'Umum',
      'kategori_slug' => $row['kategori_slug'] ?? 'umum',
      'warna_badge' => $row['warna_badge'] ?? '#20b2aa',
      'terkumpul' => $terkumpul,
      'terkumpul_label' => $label,
      'target' => (float) $row['target_donasi'],
      'persen' => $persen,
      'jumlah_donatur' => (int) $row['jumlah_donatur'],
      'sisa_hari' => (int) $row['sisa_hari'],
      'tampil_banner' => (int) $row['is_featured'],
      'urutan_banner' => (int) $row['urutan'],
      'halaman_donasi' => 'donasi.php?id=' . $row['id'],
    ];
  }
}

$programs_json = json_encode($programs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dimzz Donasi</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="db.css" />
  <style>
    html {
      scroll-padding-top: 80px;
      scroll-padding-bottom: 80px;
    }

    .banner-track:empty::before {
      content: 'Memuat campaign...';
      display: block;
      text-align: center;
      padding: 40px;
      color: #666;
    }

    .banner-card {
      position: relative;
      overflow: hidden;
      border-radius: 16px;
      margin: 20px auto;
      max-width: 800px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, .1);
      user-select: none;
      -webkit-user-select: none;
    }

    .banner-track {
      display: flex;
      transition: transform .5s cubic-bezier(.4, 0, .2, 1);
      cursor: grab;
      touch-action: pan-y;
      will-change: transform;
    }

    .banner-track.dragging {
      cursor: grabbing;
      transition: none !important;
    }

    .banner-slide {
      min-width: 100%;
      flex-shrink: 0;
      position: relative;
      cursor: pointer;
    }

    .banner-slide img {
      width: 100%;
      height: 400px;
      object-fit: cover;
      pointer-events: none;
      display: block;
    }

    .banner-overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 40px 30px 60px;
      background: linear-gradient(to top, rgba(0, 0, 0, .85), transparent);
      color: #fff;
      pointer-events: none;
    }

    .banner-badge {
      display: inline-block;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 12px;
      text-transform: uppercase;
    }

    .banner-title {
      font-size: 32px;
      font-weight: 800;
      margin: 10px 0;
      line-height: 1.2;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, .3);
    }

    .banner-desc {
      font-size: 15px;
      opacity: .95;
      margin-top: 8px;
    }

    .banner-nav {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255, 255, 255, .95);
      border: none;
      width: 45px;
      height: 45px;
      border-radius: 50%;
      cursor: pointer;
      font-size: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all .3s;
      z-index: 10;
      box-shadow: 0 2px 10px rgba(0, 0, 0, .2);
      color: #333;
    }

    .banner-nav:hover {
      background: #fff;
      transform: translateY(-50%) scale(1.1);
    }

    .banner-prev {
      left: 15px;
    }

    .banner-next {
      right: 15px;
    }

    .banner-dots {
      display: flex;
      justify-content: center;
      gap: 8px;
      padding: 15px;
      position: absolute;
      bottom: 10px;
      left: 0;
      right: 0;
      z-index: 10;
      pointer-events: none;
    }

    .banner-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: rgba(255, 255, 255, .5);
      cursor: pointer;
      transition: all .3s;
      border: none;
      pointer-events: auto;
    }

    .banner-dot.active {
      background: #fff;
      width: 28px;
      border-radius: 4px;
    }

    .campaign-card {
      cursor: pointer;
      transition: transform .3s, box-shadow .3s;
    }

    .campaign-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, .15);
    }

    .campaign-card.highlight {
      animation: highlightPulse 1.5s ease-out;
    }

    @keyframes highlightPulse {

      0%,
      100% {
        box-shadow: 0 0 0 0 rgba(32, 178, 170, 0)
      }

      50% {
        box-shadow: 0 0 0 10px rgba(32, 178, 170, .3)
      }
    }

    #campaignSection,
    #tentangSection {
      scroll-margin-top: 80px;
    }

    @media(max-width:768px) {
      .banner-slide img {
        height: 280px
      }

      .banner-title {
        font-size: 24px
      }

      .banner-nav {
        width: 38px;
        height: 38px;
        font-size: 20px
      }
    }
  </style>
</head>

<body>

  <!-- NAVBAR -->
  <nav class="navbar">
    <div class="nav-inner">
      <div class="nav-logo"><img src="logo.png" alt="Dimzz Logo" /></div>
      <div class="search-bar">
        <svg width="15" height="15" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8" />
          <path d="m21 21-4.35-4.35" />
        </svg>
        <input type="text" placeholder="Search Donasi" id="searchInput" />
        <button class="search-clear" id="searchClear" style="display:none">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M18 6 6 18M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>
  </nav>

  <div class="wrapper">

    <!-- BANNER -->
    <div class="banner-card" id="bannerCard">
      <div class="banner-track" id="bannerTrack"></div>
      <button class="banner-nav banner-prev" id="bannerPrev" type="button">&#8249;</button>
      <button class="banner-nav banner-next" id="bannerNext" type="button">&#8250;</button>
      <div class="banner-dots" id="bannerDots"></div>
    </div>

    <!-- CATEGORIES -->
    <div class="categories">
      <div class="cat-grid">
        <?php
        if ($result_kategori && mysqli_num_rows($result_kategori) > 0):
          mysqli_data_seek($result_kategori, 0);
          while ($kat = mysqli_fetch_assoc($result_kategori)):
            ?>
            <a class="cat-item" href="<?= htmlspecialchars($kat['halaman_url'] ?: '#') ?>"
              data-cat="<?= htmlspecialchars($kat['slug']) ?>">
              <div class="cat-icon"><?= $kat['icon_svg'] ?></div>
              <span class="cat-label"><?= htmlspecialchars($kat['nama_kategori']) ?></span>
            </a>
            <?php
          endwhile;
        else:
          // Fallback statis
          $fallback = [
            ['slug' => 'yatim', 'nama' => 'Yatim', 'url' => 'donasiyatim.php', 'icon' => '<svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/></svg>'],
            ['slug' => 'pendidikan', 'nama' => 'Pendidikan', 'url' => 'donasipendidikan.php', 'icon' => '<svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>'],
            ['slug' => 'pemberdayaan', 'nama' => 'Pemberdayaan', 'url' => 'donasipemberdayaan.php', 'icon' => '<svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>'],
            ['slug' => 'kesehatan', 'nama' => 'Kesehatan', 'url' => 'donasikesehatan.php', 'icon' => '<svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>'],
            ['slug' => 'darurat', 'nama' => 'Darurat', 'url' => 'donasidarurat.php', 'icon' => '<svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>'],
          ];
          foreach ($fallback as $f):
            ?>
            <a class="cat-item" href="<?= $f['url'] ?>" data-cat="<?= $f['slug'] ?>">
              <div class="cat-icon"><?= $f['icon'] ?></div>
              <span class="cat-label"><?= $f['nama'] ?></span>
            </a>
            <?php
          endforeach;
        endif;
        ?>
      </div>
    </div>

    <!-- SECTION TITLE -->
    <div class="section-title">✦ Mulai Kebaikan Hari Ini ✦</div>

    <!-- CTA -->
    <div class="cta-group">
      <button class="cta-donate" id="btnDonasi" type="button">
        <svg width="18" height="18" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
          <path
            d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
        </svg>
        Donasi Sekarang
      </button>
      <button class="cta-location" type="button"
        onclick="window.open('https://www.google.com/maps/search/?api=1&query=Jl.+Seruni+Utara+No.1b,+Klego,+Kec.+Pekalongan+Tim.,+Kota+Pekalongan,+Jawa+Tengah+51124', '_blank')">
        <svg width="16" height="16" fill="none" stroke="var(--teal)" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
          <circle cx="12" cy="10" r="3" />
        </svg>
        Kunjungi Kantor Kami
      </button>
    </div>

    <div class="campaign-label" id="campaignSection">CAMPAIGN PILIHAN</div>

    <!-- CAMPAIGN CARDS -->
    <div class="campaigns" id="campaignList">
      <div class="campaign-empty" id="campaignEmpty" style="display:none">
        <svg width="48" height="48" fill="none" stroke="var(--muted)" stroke-width="1.5" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8" />
          <path d="m21 21-4.35-4.35" />
        </svg>
        <p>Tidak ada campaign ditemukan</p>
      </div>
    </div>

    <!-- HADIST -->
    <div class="hadist-card">
      <div class="hadist-quote">"Barang siapa berniat sedekah, kecepatan Allah membalasnya lebih dari kecepatan gerakan
        sedekahnya."</div>
      <div class="hadist-source">
        <div class="hadist-logo"><img src="logo.png" alt="Dimzz Logo" /></div>
        <span class="hadist-label">(Hadist Qudsi)</span>
      </div>
    </div>

    <!-- TENTANG — ganti bagian about-card di db.php dengan ini -->
    <div class="about-section" id="tentangSection">
      <div class="about-tagline">✦ BERGERAK BERSAMA KEBAIKAN ✦</div>
      <div class="about-card">

        <h2 class="about-heading">Tentang Dimzz</h2>
        <p class="about-text">Dimzz adalah platform donasi digital yang menghubungkan para dermawan dengan
          program-program kemanusiaan terpercaya di seluruh Indonesia. Kami hadir untuk memudahkan setiap orang berbuat
          kebaikan — kapan saja, di mana saja, dengan cara yang aman, mudah, dan transparan.</p>

        <h2 class="about-heading">Apa Makna dari Kata Dimzz?</h2>
        <p class="about-text">Kata "Dimzz" terinspirasi dari kata <em>dimensi</em> — sebuah nama yang mencerminkan visi
          kami untuk membawa kebaikan melampaui batas dimensi ruang dan waktu. Kami percaya bahwa satu langkah kecil
          dari satu orang bisa menciptakan dampak besar bagi ribuan jiwa yang membutuhkan. Dimzz hadir sebagai jembatan
          antara niat baik dan aksi nyata.</p>

        <h2 class="about-heading">Visi</h2>
        <p class="about-text">Menjadi platform kebaikan digital terpercaya yang menghubungkan jutaan jiwa dermawan
          dengan mereka yang membutuhkan, secara transparan, akuntabel, dan berdampak nyata di seluruh pelosok
          Indonesia.</p>

        <h2 class="about-heading">Misi</h2>
        <p class="about-text">Menyediakan platform donasi yang mudah diakses, aman, dan transparan bagi seluruh lapisan
          masyarakat. Menggandeng lembaga-lembaga amanah untuk memastikan donasi tersalurkan tepat sasaran. Mengedukasi
          masyarakat tentang pentingnya berbagi, berinfak, dan bersedekah. Membangun ekosistem kebaikan yang
          berkelanjutan dan berdampak jangka panjang bagi Indonesia.</p>

        <h2 class="about-heading">Mengapa Memilih Dimzz?</h2>
        <p class="about-text">Setiap donasi yang masuk melalui Dimzz dikelola secara transparan dan dapat dipantau
          perkembangannya secara langsung. Kami bekerja sama hanya dengan lembaga-lembaga yang telah terverifikasi dan
          memiliki rekam jejak yang baik. Dengan teknologi yang kami bangun, proses donasi menjadi lebih mudah — cukup
          beberapa langkah dari genggaman tangan Anda.</p>

        <h2 class="about-heading">Legalitas & Kepercayaan</h2>
        <p class="about-text">Berdiri sejak 2024, Dimzz telah memiliki izin resmi pengumpulan donasi dari lembaga
          berwenang dan secara rutin menjalani audit keuangan dengan hasil Wajar Tanpa Pengecualian (WTP). Kepercayaan
          Anda adalah amanah terbesar bagi kami.</p>

      </div>
    </div>
    <!-- FOOTER -->
    <footer class="site-footer">
      <p class="footer-desc">Berdiri sejak 2024, Dimzz memiliki izin resmi pengumpulan donasi dari lembaga berwenang.
        Dimzz rutin diaudit dengan status Wajar Tanpa Pengecualian (WTP).</p>
      <div class="footer-divider"></div>
      <div class="footer-links">
        <span id="footerTentang" style="cursor:pointer">Tentang Dimzz</span>
        <span class="footer-sep">|</span>
        <a href="syaratdanketentuan.php">Syarat &amp; Ketentuan</a>
        <span class="footer-sep">|</span>
        <span style="cursor: pointer;" onclick="window.open('https://wa.me/6285166376862', '_blank')">Pusat
          Bantuan</span>
      </div>
      <div class="footer-socmed">
        <a class="socmed-btn" href="https://www.facebook.com/share/1AvRSgH2WX/?mibextid=wwXIfr" target="_blank"
          rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
          </svg></a>
        <a class="socmed-btn" href="https://x.com/dimzdonasi?s=21" target="_blank" rel="noopener"><svg
            viewBox="0 0 24 24" fill="currentColor">
            <path
              d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
          </svg></a>
        <a class="socmed-btn" href="https://www.instagram.com/dimzdonasi?igsh=M3hqYzAwdDN0ZG1x&utm_source=qr"
          target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="2" width="20" height="20" rx="5" />
            <circle cx="12" cy="12" r="4" />
            <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
          </svg></a>
        <a class="socmed-btn" href="https://youtube.com/@dimzdonasi?si=5hlB7j8tcEidPTg4" target="_blank"
          rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor">
            <path
              d="M23 7s-.3-2-1.2-2.8c-1.1-1.2-2.4-1.2-3-1.3C16.6 2.8 12 2.8 12 2.8s-4.6 0-6.8.1c-.6.1-1.9.1-3 1.3C1.3 5 1 7 1 7S.7 9.1.7 11.2v2c0 2.1.3 4.2.3 4.2s.3 2 1.2 2.8c1.1 1.2 2.6 1.1 3.3 1.2C7.5 21.6 12 21.6 12 21.6s4.6 0 6.8-.2c.6-.1 1.9-.1 3-1.3.9-.8 1.2-2.8 1.2-2.8s.3-2.1.3-4.2v-2C23.3 9.1 23 7 23 7zM9.7 15.5V8.4l8.1 3.6-8.1 3.5z" />
          </svg></a>
        <a class="socmed-btn" href="https://www.tiktok.com/@dimzdonasi4?_r=1&_t=ZS-94c9oAudDJo" target="_blank"
          rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor">
            <path
              d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.27 8.27 0 0 0 4.84 1.55V6.79a4.85 4.85 0 0 1-1.07-.1z" />
          </svg></a>
      </div>
      <p class="footer-copy">Copyright &copy; 2026 Dimzz. All Rights Reserved</p>
    </footer>

    <!-- FLOATING NOTIFICATION -->
    <div class="notif-card" id="notifCard">
      <div class="notif-avatar">O</div>
      <div class="notif-body">
        <div class="notif-name">Orang Baik</div>
        <div class="notif-text">Baru saja Donasi di Sempurnakan Ramadhan dengan Kebaikan</div>
        <div class="notif-verified">
          <svg width="12" height="12" viewBox="0 0 24 24">
            <path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" fill="none" stroke="#20B2AA" stroke-width="2.5"
              stroke-linecap="round" />
          </svg>
          Verified · 6 jam yang lalu
        </div>
      </div>
    </div>

    <!-- BOTTOM NAV -->
    <nav class="bottom-nav">
      <div class="nav-item active" id="navHome">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
          <polyline points="9 22 9 12 15 12 15 22" />
        </svg>
        Home
      </div>
      <div class="nav-item" id="navDonasi">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path
            d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
        </svg>
        Donasi
      </div>
      <div class="nav-item" id="navTentang">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
          <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
        </svg>
        Tentang Kita
      </div>
      <div class="nav-item" id="navAdmin">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
          <circle cx="12" cy="7" r="4" />
        </svg>
        Admin
      </div>
    </nav>

  </div>

  <!-- JSON DATA DARI PHP → dibaca db.js -->
  <script id="programs-data" type="application/json"><?= $programs_json ?></script>
  <script src="db.js"></script>
  <script src="notif-live.js"></script> <!-- ← tambahkan ini -->
</body>
</body>

</html>