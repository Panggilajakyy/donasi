/**
 * notif-live.js
 * Ambil donasi terbaru dari api_notif_donasi.php dan tampilkan di
 * notifikasi floating (#notifCard) secara bergantian setiap beberapa detik.
 *
 * Cara pakai: tambahkan <script src="notif-live.js"></script>
 * di db.php SETELAH elemen #notifCard ada di DOM.
 */

(function () {
  'use strict';

  /* ── Konfigurasi ─────────────────────────────────────────── */
  const API_URL        = 'api-notif-donasi.php'; // path relatif
  const FETCH_INTERVAL = 60_000;  // refresh data dari server tiap 60 detik
  const SHOW_INTERVAL  = 8_000;   // ganti notifikasi tiap 8 detik
  const SHOW_DURATION  = 6_000;   // notifikasi tampil 6 detik sebelum disembunyikan

  /* ── Warna avatar bergantian ─────────────────────────────── */
  const AVATAR_COLORS = [
    '#20b2aa', // teal (brand)
    '#f59e0b', // amber
    '#6366f1', // indigo
    '#ef4444', // red
    '#10b981', // emerald
    '#8b5cf6', // violet
    '#f97316', // orange
    '#06b6d4', // cyan
  ];

  /* ── State ───────────────────────────────────────────────── */
  let donations    = [];   // array data dari server
  let currentIndex = 0;   // indeks donasi yang sedang ditampilkan
  let showTimer    = null;
  let hideTimer    = null;
  let fetchTimer   = null;

  /* ── Elemen DOM ──────────────────────────────────────────── */
  const card     = document.getElementById('notifCard');
  const avatar   = card?.querySelector('.notif-avatar');
  const nameEl   = card?.querySelector('.notif-name');
  const textEl   = card?.querySelector('.notif-text');
  const verEl    = card?.querySelector('.notif-verified');

  if (!card || !avatar || !nameEl || !textEl || !verEl) {
    console.warn('[notif-live] Elemen #notifCard tidak lengkap, skip.');
    return;
  }

  /* ── Animasi masuk / keluar ──────────────────────────────── */
  function slideIn() {
    card.style.transition = 'transform .45s cubic-bezier(.34,1.56,.64,1), opacity .35s ease';
    card.style.transform  = 'translateY(0)';
    card.style.opacity    = '1';
    card.style.pointerEvents = 'auto';
  }

  function slideOut(cb) {
    card.style.transition = 'transform .35s ease, opacity .3s ease';
    card.style.transform  = 'translateY(120%)';
    card.style.opacity    = '0';
    card.style.pointerEvents = 'none';
    setTimeout(cb || (() => {}), 380);
  }

  /* ── Render satu donasi ke dalam card ───────────────────── */
  function renderDonasi(d, colorIndex) {
    const color = AVATAR_COLORS[colorIndex % AVATAR_COLORS.length];

    avatar.textContent        = d.inisial;
    avatar.style.background   = color;
    nameEl.textContent        = d.nama;

    // Teks: "Baru saja donasi Rp X di Nama Campaign"
    const nominal = d.nominal ? ` sebesar ${d.nominal}` : '';
    const campaign = d.campaign ? ` di "${d.campaign}"` : '';
    textEl.textContent = `Baru saja berdonasi${nominal}${campaign}`;

    // Verified + waktu
    verEl.innerHTML =
      `<svg width="12" height="12" viewBox="0 0 24 24">
         <path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"
               fill="none" stroke="#20B2AA" stroke-width="2.5" stroke-linecap="round"/>
       </svg>
       Verified · ${d.waktu}`;
  }

  /* ── Tampilkan notifikasi berikutnya ─────────────────────── */
  function showNext() {
    if (!donations.length) return;

    const d = donations[currentIndex];
    currentIndex = (currentIndex + 1) % donations.length;

    slideOut(() => {
      renderDonasi(d, currentIndex);
      slideIn();

      // Sembunyikan setelah SHOW_DURATION
      clearTimeout(hideTimer);
      hideTimer = setTimeout(() => slideOut(() => {}), SHOW_DURATION);
    });
  }

  /* ── Ambil data dari API ─────────────────────────────────── */
  function fetchData() {
    fetch(API_URL + '?_=' + Date.now())
      .then(r => r.json())
      .then(data => {
        if (Array.isArray(data) && data.length > 0) {
          donations = data;
          // Jika belum ada yang tampil, langsung tampilkan pertama
          if (donations.length && currentIndex === 0) {
            renderDonasi(donations[0], 0);
            currentIndex = 1;
            slideIn();
            hideTimer = setTimeout(() => slideOut(() => {}), SHOW_DURATION);
          }
        }
      })
      .catch(err => console.warn('[notif-live] Gagal fetch:', err));
  }

  /* ── Init ────────────────────────────────────────────────── */
  function init() {
    // Sembunyikan dulu (start dari bawah layar)
    card.style.transition    = 'none';
    card.style.transform     = 'translateY(120%)';
    card.style.opacity       = '0';
    card.style.pointerEvents = 'none';

    // Pertama kali ambil data
    fetchData();

    // Ganti notifikasi periodik
    showTimer = setInterval(showNext, SHOW_INTERVAL);

    // Refresh data dari server secara berkala
    fetchTimer = setInterval(fetchData, FETCH_INTERVAL);
  }

  // Tunggu DOM siap
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();