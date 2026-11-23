// db.js — selaras dengan kolom tabel di database

const BADGE_COLOR = {
    yatim: 'campaign-badge--red',
    darurat: 'campaign-badge--amber',
    pendidikan: 'campaign-badge--green',
    pemberdayaan: 'campaign-badge--teal',
    kesehatan: 'campaign-badge--teal',
};

// Mapping file donasi berdasarkan kategori
const FILE_MAPPING = {
    'yatim': 'donasiyatim.php',
    'pendidikan': 'donasipendidikan.php',
    'pemberdayaan': 'donasipemberdayaan.php',
    'kesehatan': 'donasikesehatan.php',
    'darurat': 'donasidarurat.php'
};

// ─────────────────────────────────────────────────────────────
//  HELPERS
// ─────────────────────────────────────────────────────────────
function fmtDonatur(n) {
    if (!n) return '0';
    n = parseInt(n);
    if (n >= 1000000) return (n / 1000000).toFixed(1).replace('.0', '') + ' jt';
    if (n >= 1000) return (n / 1000).toFixed(1).replace('.0', '') + ' rb';
    return String(n);
}

function smoothScrollTo(el) {
    if (!el) return;
    window.scrollTo({
        top: el.getBoundingClientRect().top + window.pageYOffset - 80,
        behavior: 'smooth'
    });
}

// ─────────────────────────────────────────────────────────────
//  RENDER BANNER
// ─────────────────────────────────────────────────────────────
function renderBanner(programs) {
    const track = document.getElementById('bannerTrack');
    const dots = document.getElementById('bannerDots');
    if (!track || !dots) return;

    let list = programs.filter(p => p.tampil_banner == 1);
    if (!list.length) list = programs.slice(0, 5);
    list.sort((a, b) => a.urutan_banner - b.urutan_banner);

    track.innerHTML = '';
    dots.innerHTML = '';

    if (!list.length) {
        track.innerHTML = '<div style="padding:40px;text-align:center;color:#666">Belum ada campaign</div>';
        return;
    }

    list.forEach((p, i) => {
        const title = p.judul.split(' ').slice(0, 2).join('<br>');
        const bgColor = p.warna_badge || '#20b2aa';

        const slide = document.createElement('div');
        slide.className = 'banner-slide';
        slide.dataset.target = 'card-' + p.id;
        slide.innerHTML = `
            <img class="banner-img" src="${p.gambar}" alt="${p.judul}" draggable="false" />
            <div class="banner-overlay">
                <span class="banner-badge" style="background:${bgColor}">${p.kategori}</span>
                <h2 class="banner-title">${title}</h2>
                <p class="banner-desc">${p.deskripsi_pendek || ''}</p>
            </div>`;
        track.appendChild(slide);

        const dot = document.createElement('div');
        dot.className = 'banner-dot' + (i === 0 ? ' active' : '');
        dots.appendChild(dot);
    });

    setTimeout(initSlider, 100);
}

// ─────────────────────────────────────────────────────────────
//  RENDER CAMPAIGN CARDS
// ─────────────────────────────────────────────────────────────
function renderCards(programs) {
    const list = document.getElementById('campaignList');
    const empty = document.getElementById('campaignEmpty');

    if (!list) {
        console.error('[CARDS] campaignList tidak ditemukan');
        return;
    }

    // Hapus card lama
    list.querySelectorAll('.campaign-card').forEach(el => el.remove());

    if (!programs || programs.length === 0) {
        if (empty) empty.style.display = '';
        console.log('[CARDS] Tidak ada program');
        return;
    }

    if (empty) empty.style.display = 'none';
    console.log('[CARDS] Rendering', programs.length, 'cards');

    programs.forEach(p => {
        // Validasi data dengan default value
        const gambar = p.gambar || p.gambar_banner || p.gambar_thumbnail || 'https://via.placeholder.com/400x300?text=Dimzz';
        const judul = p.judul || p.judul_program || 'Campaign';
        const kategori = p.kategori || 'Umum';
        const kategori_slug = p.kategori_slug || 'umum';
        const organisasi = p.organisasi || 'Dimzz';
        const sisa_hari = p.sisa_hari || 30;
        const persen = Math.min(parseInt(p.persen) || 0, 100);
        const terkumpul_label = p.terkumpul_label || 'Rp 0';
        const jumlah_donatur = p.jumlah_donatur || 0;
        const card_id = p.card_id || 'card-' + (p.id || Math.random());

        // Tentukan file tujuan berdasarkan kategori
        const halaman_donasi = FILE_MAPPING[kategori_slug] || 'donasi.php';

        const badgeClass = BADGE_COLOR[kategori_slug] || 'campaign-badge--teal';

        const card = document.createElement('div');
        card.className = 'campaign-card';
        card.id = card_id;
        card.dataset.cat = kategori_slug;
        card.dataset.title = judul;
        card.dataset.programId = p.id || 0;
        card.dataset.halamanDonasi = halaman_donasi;

        card.innerHTML = `
            <div class="campaign-thumb-wrap">
                <img class="campaign-thumb" src="${gambar}" alt="${judul}" draggable="false" />
                <span class="campaign-badge ${badgeClass}">${kategori}</span>
            </div>
            <div class="campaign-body">
                <div class="campaign-meta">
                    <span class="campaign-org">${organisasi}</span>
                    <span class="campaign-days">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                        </svg>
                        ${sisa_hari} hari lagi
                    </span>
                </div>
                <div class="campaign-title">${judul}</div>
                <div class="campaign-progress">
                    <div class="progress-track">
                        <div class="progress-fill" style="width:${persen}%"></div>
                    </div>
                    <div class="progress-info">
                        <span class="progress-amount">${terkumpul_label}</span>
                        <span class="progress-percent">${persen}%</span>
                    </div>
                </div>
                <div class="campaign-footer">
                    <span class="campaign-donors">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                        </svg>
                        ${fmtDonatur(jumlah_donatur)} donatur
                    </span>
                    <a class="campaign-btn" href="${halaman_donasi}" onclick="event.stopPropagation();">
                        Donasi
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                    </a>
                </div>
            </div>
        `;

        // Event listener untuk klik card → redirect ke file spesifik
        card.addEventListener('click', function (e) {
            // Jangan redirect jika klik tombol donasi
            if (e.target.closest('.campaign-btn')) return;

            // Highlight effect
            card.classList.add('highlight');
            setTimeout(() => card.classList.remove('highlight'), 1500);

            // Redirect ke file spesifik berdasarkan kategori
            if (halaman_donasi && halaman_donasi !== '#') {
                window.location.href = halaman_donasi;
            }
        });

        list.insertBefore(card, empty);
    });

    initSearch();
}

// ─────────────────────────────────────────────────────────────
//  LOAD DATA dari <script id="programs-data">
// ─────────────────────────────────────────────────────────────
function loadPrograms() {
    try {
        const el = document.getElementById('programs-data');
        if (!el) {
            console.error('[ERROR] programs-data not found');
            return;
        }

        const textContent = el.textContent.trim();
        if (!textContent || textContent === 'null' || textContent === 'undefined') {
            console.error('[ERROR] Data kosong');
            return;
        }

        const programs = JSON.parse(textContent);

        if (!Array.isArray(programs) || !programs.length) {
            console.warn('[WARN] Data kosong atau bukan array');
            return;
        }

        console.log('[LOAD] Loaded', programs.length, 'programs');
        renderBanner(programs);
        renderCards(programs);
        console.log('[OK] Rendering complete');
    } catch (e) {
        console.error('[FATAL] loadPrograms error:', e);
    }
}

// ─────────────────────────────────────────────────────────────
//  SLIDER (swipe + drag + auto + dots)
// ─────────────────────────────────────────────────────────────
let sliderIndex = 0, sliderTimer = null;
let isDragging = false, hasDragged = false, dragStartX = 0, dragCurrentX = 0;

function initSlider() {
    const track = document.getElementById('bannerTrack');
    const dotsCt = document.getElementById('bannerDots');
    const prev = document.getElementById('bannerPrev');
    const next = document.getElementById('bannerNext');
    if (!track || !dotsCt) return;

    const dots = dotsCt.children;
    const total = track.children.length;
    if (!total) return;

    console.log('[SLIDER] Initialized with', total, 'slides');

    function goTo(i) {
        sliderIndex = ((i % total) + total) % total;
        track.style.transform = `translateX(-${sliderIndex * 100}%)`;
        Array.from(dots).forEach((d, idx) => d.classList.toggle('active', idx === sliderIndex));
    }

    function nextSlide() {
        goTo(sliderIndex + 1);
    }

    function prevSlide() {
        goTo(sliderIndex - 1);
    }

    function reset() {
        clearInterval(sliderTimer);
        sliderTimer = setInterval(nextSlide, 5000);
    }

    // Tombol prev/next
    if (prev) {
        prev.addEventListener('click', e => {
            e.stopPropagation();
            prevSlide();
            reset();
        });
        prev.addEventListener('mousedown', e => e.stopPropagation());
        prev.addEventListener('touchstart', e => e.stopPropagation(), { passive: true });
    }

    if (next) {
        next.addEventListener('click', e => {
            e.stopPropagation();
            nextSlide();
            reset();
        });
        next.addEventListener('mousedown', e => e.stopPropagation());
        next.addEventListener('touchstart', e => e.stopPropagation(), { passive: true });
    }

    // Dots
    Array.from(dots).forEach((d, i) => {
        d.addEventListener('click', e => {
            e.stopPropagation();
            goTo(i);
            reset();
        });
    });

    // Klik slide → scroll ke card
    Array.from(track.children).forEach(slide => {
        slide.addEventListener('click', () => {
            if (hasDragged) return;
            const targetId = slide.dataset.target;
            if (targetId) {
                const t = document.getElementById(targetId);
                if (t) {
                    smoothScrollTo(t);
                    t.classList.add('highlight');
                    setTimeout(() => t.classList.remove('highlight'), 1500);
                }
            }
        });
    });

    // Drag / swipe
    const startDrag = x => {
        isDragging = true;
        hasDragged = false;
        dragStartX = dragCurrentX = x;
        track.classList.add('dragging');
    };

    const moveDrag = x => {
        if (!isDragging) return;
        dragCurrentX = x;
        const d = dragCurrentX - dragStartX;
        if (Math.abs(d) > 10) {
            hasDragged = true;
            track.style.transform = `translateX(calc(-${sliderIndex * 100}% + ${(d / track.offsetWidth) * 100}%))`;
        }
    };

    const endDrag = () => {
        if (!isDragging) return;
        isDragging = false;
        track.classList.remove('dragging');
        if (hasDragged) {
            const d = dragStartX - dragCurrentX;
            if (Math.abs(d) > 50) {
                if (d > 0) {
                    nextSlide();
                } else {
                    prevSlide();
                }
            } else {
                goTo(sliderIndex);
            }
            reset();
        }
        hasDragged = false;
        dragStartX = 0;
        dragCurrentX = 0;
    };

    track.addEventListener('mousedown', e => {
        if (!e.target.closest('.banner-nav,.banner-dot')) {
            startDrag(e.clientX);
            e.preventDefault();
        }
    });
    document.addEventListener('mousemove', e => moveDrag(e.clientX));
    document.addEventListener('mouseup', endDrag);
    track.addEventListener('touchstart', e => {
        if (!e.target.closest('.banner-nav,.banner-dot')) {
            startDrag(e.touches[0].clientX);
        }
    }, { passive: true });
    track.addEventListener('touchmove', e => moveDrag(e.touches[0].clientX), { passive: true });
    track.addEventListener('touchend', endDrag, { passive: true });

    track.addEventListener('mouseenter', () => clearInterval(sliderTimer));
    track.addEventListener('mouseleave', reset);

    goTo(0);
    reset();
}

// ─────────────────────────────────────────────────────────────
//  SEARCH
// ─────────────────────────────────────────────────────────────
function initSearch() {
    const input = document.getElementById('searchInput');
    const clear = document.getElementById('searchClear');
    if (!input || !clear) return;

    console.log('[SEARCH] Initialized');

    function doSearch(q) {
        const kw = q.toLowerCase().trim();
        let found = 0;
        document.querySelectorAll('.campaign-card').forEach(card => {
            const txt = (card.dataset.cat + ' ' + (card.querySelector('.campaign-title')?.textContent || '')).toLowerCase();
            const match = !kw || txt.includes(kw);
            card.style.display = match ? '' : 'none';
            if (match) found++;
        });
        const empty = document.getElementById('campaignEmpty');
        if (empty) empty.style.display = found ? 'none' : '';
        clear.style.display = q ? '' : 'none';
    }

    input.addEventListener('input', e => doSearch(e.target.value));
    clear.addEventListener('click', () => {
        input.value = '';
        doSearch('');
        input.focus();
    });
}

// ─────────────────────────────────────────────────────────────
//  CATEGORY FILTER
// ─────────────────────────────────────────────────────────────
function initCategoryFilter() {
    document.querySelectorAll('.cat-item').forEach(item => {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            const cat = this.dataset.cat;
            document.querySelectorAll('.campaign-card').forEach(card => {
                card.style.display = (!cat || card.dataset.cat === cat) ? '' : 'none';
            });
            document.querySelectorAll('.cat-item').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            smoothScrollTo(document.getElementById('campaignSection'));
        });
    });
}

// ─────────────────────────────────────────────────────────────
//  NAVIGATION
// ─────────────────────────────────────────────────────────────
function initNavigation() {
    const setActive = el => {
        document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
        el?.classList.add('active');
    };

    const navHome = document.getElementById('navHome');
    const navDonasi = document.getElementById('navDonasi');
    const navTentang = document.getElementById('navTentang');
    const navAdmin = document.getElementById('navAdmin');
    const footerTentang = document.getElementById('footerTentang');
    const btnDonasi = document.getElementById('btnDonasi');

    if (navHome) {
        navHome.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setActive(navHome);
        });
    }

    if (navDonasi) {
        navDonasi.addEventListener('click', () => {
            smoothScrollTo(document.getElementById('campaignSection'));
            setActive(navDonasi);
        });
    }

    if (navTentang) {
        navTentang.addEventListener('click', () => {
            smoothScrollTo(document.getElementById('tentangSection'));
            setActive(navTentang);
        });
    }

    if (navAdmin) {
        navAdmin.addEventListener('click', () => window.location.href = 'admin.php');
    }

    if (footerTentang) {
        footerTentang.addEventListener('click', () => smoothScrollTo(document.getElementById('tentangSection')));
    }

    if (btnDonasi) {
        btnDonasi.addEventListener('click', () => smoothScrollTo(document.getElementById('campaignSection')));
    }

    console.log('[NAV] Navigation initialized');
}

// ─────────────────────────────────────────────────────────────
//  NOTIFICATION
// ─────────────────────────────────────────────────────────────
function initNotification() {
    const card = document.getElementById('notifCard');
    if (!card) return;
    setTimeout(() => card.classList.add('show'), 3000);
    setTimeout(() => {
        card.classList.remove('show');
        setTimeout(() => card.style.display = 'none', 500);
    }, 13000);
    card.onclick = () => {
        card.classList.remove('show');
        setTimeout(() => card.style.display = 'none', 500);
    };
    console.log('[NOTIF] Notification initialized');
}

// ─────────────────────────────────────────────────────────────
//  INIT
// ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    console.log('========================================');
    console.log('🚀 Dimzz Donasi - Initializing...');
    console.log('========================================');

    loadPrograms();
    initNavigation();
    initCategoryFilter();
    initNotification();

    console.log('========================================');
    console.log('✅ Dimzz Donasi - Ready!');
    console.log('========================================');
});