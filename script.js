// script.js - Untuk halaman donasibanjir.php
function rupiah(n) {
    return "Rp " + n.toLocaleString("id-ID");
}

// Data campaign
document.getElementById("collected").innerText = rupiah(collected);
document.getElementById("target").innerText = rupiah(target);
setTimeout(function () {
    document.getElementById("progressBar").style.width = (collected / target) * 100 + "%";
}, 300);
document.getElementById("donasiCount").innerText = totalDonasi + " Donasi";

// Data doa
var doaAamiin = doaData.map(d => d.aamiin_count);
var userAamiin = new Array(doaData.length).fill(false);
var doaPage = 1;
var DOA_PER_PAGE = 3;

function formatTime(dateStr) {
    var date = new Date(dateStr), now = new Date(), diff = Math.floor((now - date) / 1000);
    if (diff < 60) return "Baru saja";
    if (diff < 3600) return Math.floor(diff / 60) + " menit lalu";
    if (diff < 86400) return Math.floor(diff / 3600) + " jam lalu";
    return Math.floor(diff / 86400) + " hari lalu";
}

function escapeHtml(str) {
    return str.replace(/[&<>]/g, function (m) {
        return m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;';
    });
}

function renderDoaItem(d, idx) {
    var count = doaAamiin[idx], active = userAamiin[idx];
    return `<div class="doa-item" id="doaItem${idx}">
        <div class="doa-item-header">
            <span class="doa-item-name">${escapeHtml(d.donator_name)}</span>
            <span class="doa-item-time">${formatTime(d.created_at)}</span>
        </div>
        <p class="doa-item-text">${escapeHtml(d.message)}</p>
        <button class="doa-aamiin-btn ${active ? 'active' : ''}" onclick="toggleAamiin(${idx}, ${d.id})">
            <span class="aamiin-icon">🤲</span>
            ${count > 0 ? count + ' Aamiin' : 'Aamiin-kan doa ini'}
        </button>
    </div>`;
}

function renderDoaList() {
    var list = document.getElementById('doaList');
    var loadMore = document.getElementById('doaLoadMore');
    var visible = doaData.slice(0, doaPage * DOA_PER_PAGE);
    var html = '';
    for (var i = 0; i < visible.length; i++) {
        html += renderDoaItem(visible[i], i);
    }
    list.innerHTML = html;
    document.getElementById('doaCount').innerText = '(' + doaData.length + ')';
    if (visible.length >= doaData.length) {
        loadMore.style.display = 'none';
    } else {
        loadMore.style.display = 'block';
    }
}

function loadMoreDoa() {
    doaPage++;
    renderDoaList();
}

async function toggleAamiin(index, commentId) {
    if (userAamiin[index]) return;
    try {
        var resp = await fetch('?action=aamiin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId })
        });
        var result = await resp.json();
        if (result.success) {
            userAamiin[index] = true;
            doaAamiin[index] = result.new_count;
            renderDoaList();
        } else {
            alert('Gagal mengirim Aamiin: ' + (result.message || 'Error'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

// DONATUR DARI DATABASE
var donaturMode = 'terbaru';
var donaturPage = 1;
var ITEMS_PER_PAGE = 8;
var totalDonatur = 0;

async function renderDonatur(mode) {
    donaturMode = mode;
    donaturPage = 1;
    await loadDonaturData();
}

async function loadDonaturData() {
    try {
        var url = `?action=get_donatur&mode=${donaturMode}&page=${donaturPage}&limit=${ITEMS_PER_PAGE}`;
        var resp = await fetch(url);
        var result = await resp.json();
        if (result.success) {
            totalDonatur = result.total;
            var donatur = result.data;
            var hasMore = (donaturPage * ITEMS_PER_PAGE) < totalDonatur;
            var html = '<div class="donatur-toggle-bar">' +
                '<button class="donatur-toggle-btn ' + (donaturMode === 'terbaru' ? 'active' : '') + '" onclick="renderDonatur(\'terbaru\')">Terbaru</button>' +
                '<button class="donatur-toggle-btn ' + (donaturMode === 'terbesar' ? 'active' : '') + '" onclick="renderDonatur(\'terbesar\')">Terbesar</button>' +
                '</div><div class="donatur-card-list" id="donaturCardList">';
            for (var i = 0; i < donatur.length; i++) {
                var d = donatur[i];
                html += '<div class="donatur-card-item">' +
                    '<div class="donatur-card-left">' +
                    '<div class="donatur-card-name">' + escapeHtml(d.name) + '</div>' +
                    '<div class="donatur-card-desc">Berdonasi sebesar <span>' + rupiah(d.amount) + '</span></div>' +
                    '</div>' +
                    '<div class="donatur-card-right">' + formatTime(d.time) + '</div>' +
                    '</div>';
            }
            html += '</div>';
            if (hasMore) {
                html += '<div class="donatur-load-more"><button onclick="loadMoreDonatur()">Muat Lebih Banyak</button></div>';
            }
            document.getElementById("tabContent").innerHTML = html;
        } else {
            document.getElementById("tabContent").innerHTML = '<p>Gagal memuat donatur: ' + (result.message || 'Error') + '</p>';
        }
    } catch (e) {
        document.getElementById("tabContent").innerHTML = '<p>Error memuat donatur: ' + e.message + '</p>';
    }
}

async function loadMoreDonatur() {
    donaturPage++;
    try {
        var url = `?action=get_donatur&mode=${donaturMode}&page=${donaturPage}&limit=${ITEMS_PER_PAGE}`;
        var resp = await fetch(url);
        var result = await resp.json();
        if (result.success) {
            var donatur = result.data;
            var list = document.getElementById('donaturCardList');
            for (var i = 0; i < donatur.length; i++) {
                var d = donatur[i];
                var div = document.createElement('div');
                div.className = 'donatur-card-item';
                div.innerHTML = '<div class="donatur-card-left"><div class="donatur-card-name">' + escapeHtml(d.name) + '</div><div class="donatur-card-desc">Berdonasi sebesar <span>' + rupiah(d.amount) + '</span></div></div><div class="donatur-card-right">' + formatTime(d.time) + '</div>';
                list.appendChild(div);
            }
            if ((donaturPage * ITEMS_PER_PAGE) >= totalDonatur) {
                var el = document.querySelector('.donatur-load-more');
                if (el) el.remove();
            }
        }
    } catch (e) { console.error(e); }
}

// KABAR TERBARU (data statis)
var newsList = [
    { title: "BANJIR PEKALONGAN MELUAS", text: "Ribuan rumah terendam dan warga membutuhkan bantuan darurat secepatnya", author: "— Relawan BNPB", img: "banjir.png", time: new Date() },
    { title: "BANTUAN LOGISTIK TIBA", text: "Makanan, obat, dan pakaian sudah mulai disalurkan ke pengungsian", author: "— Tim Donasi", img: "bantuan.png", time: new Date() },
    { title: "EVAKUASI DARURAT DILAKUKAN", text: "Tim SAR sedang melakukan evakuasi di wilayah terparah", author: "— Basarnas", img: "evakuasi.png", time: new Date() },
    { title: "POSKO BANTUAN DIBUKA", text: "Posko pengungsian telah dibuka di 3 lokasi strategis", author: "— Pemda Pekalongan", img: "posko.png", time: new Date() },
    { title: "DONASI MENGALIR DERAS", text: "Masyarakat antusias membantu korban banjir", author: "— Media Lokal", img: "donasi.png", time: new Date() }
];

function renderNews() {
    var html = '<div class="timeline-container"><div class="timeline-line"></div>';
    for (var i = 0; i < newsList.length; i++) {
        var n = newsList[i];
        html += '<div class="timeline-item">' +
            '<div class="timeline-bullet"></div>' +
            '<div class="timeline-card">' +
            '<h3 class="timeline-title">' + n.title + '</h3>' +
            '<p class="timeline-content">' + n.text + '</p>' +
            '<div class="timeline-author">' + n.author + '</div>' +
            '<img src="' + n.img + '" class="timeline-image">' +
            '<div class="timeline-footer">' +
            '<span class="timeline-time">' + formatTime(n.time) + '</span>' +
            '</div>' +
            '</div>' +
            '</div>';
    }
    html += '</div>';
    document.getElementById("tabContent").innerHTML = html;
}

function openTab(tabName) {
    if (tabName === 'keterangan') {
        document.getElementById("tabContent").innerHTML = `
            <h3>PEDULI BANJIR PEKALONGAN</h3>
            <div class="keterangan-wrapper">
                <div class="keterangan-container collapsed" id="keteranganContainer">
                    <img src="https://akcdn.detik.net.id/community/media/visual/2026/01/17/penampakan-banjir-di-pekalongan-barat-kota-pekalongan-sabtu-1712026-1768626383934_169.jpeg?w=700&q=90" style="margin-top:10px;border-radius:10px;">
                    <p style="margin-top:8px;">Banjir yang melanda Kota Pekalongan pada Januari 2026 telah menyebabkan ratusan warga mengungsi...</p>
                    <p style="margin-top:8px;">Hujan deras yang terus mengguyur wilayah Pekalongan selama tiga hari berturut-turut telah menyebabkan meluapnya Sungai Pekalongan.</p>
                    <p style="margin-top:8px;">Bantuan terus mengalir dari berbagai pihak. Kebutuhan mendesak: makanan, air bersih, obat-obatan, dan tempat tinggal sementara.</p>
                    <div class="keterangan-gradient"></div>
                </div>
                <div class="read-more-btn" id="readMoreBtn">
                    <button onclick="toggleKeterangan()">Baca selengkapnya ▾</button>
                </div>
            </div>
        `;
    } else if (tabName === 'kabar') {
        renderNews();
    } else if (tabName === 'donatur') {
        renderDonatur('terbaru');
    }
}

function toggleKeterangan() {
    var c = document.getElementById('keteranganContainer');
    var r = document.getElementById('readMoreBtn');
    if (c && r && c.classList.contains('collapsed')) {
        c.classList.remove('collapsed');
        c.classList.add('expanded');
        r.classList.add('hidden');
    }
}

function shareLink() {
    if (navigator.share) {
        navigator.share({ title: 'Peduli Banjir Pekalongan', url: window.location.href });
    } else {
        navigator.clipboard.writeText(window.location.href).then(() => alert('Link disalin!')).catch(() => prompt('Salin link:', window.location.href));
    }
}

function becomeFundraiser() {
    alert('Terima kasih telah mendaftar sebagai fundraiser! Tim kami akan segera menghubungi Anda.');
}

// INISIALISASI
document.addEventListener('DOMContentLoaded', function () {
    var homeImg = document.querySelector('.home-icon img');
    if (homeImg) homeImg.addEventListener('click', function () { window.location.href = 'db.html'; });

    var tabs = document.querySelectorAll('.tab');
    var indicator = document.querySelector('.tab-indicator');

    function updateIndicator(activeTab) {
        if (indicator && activeTab) {
            indicator.style.left = activeTab.offsetLeft + 'px';
            indicator.style.width = activeTab.offsetWidth + 'px';
        }
    }

    var activeOnLoad = document.querySelector('.tab.active');
    if (activeOnLoad) updateIndicator(activeOnLoad);

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            openTab(this.dataset.tab);
            updateIndicator(this);
        });
    });

    window.addEventListener('resize', function () {
        var cur = document.querySelector('.tab.active');
        if (cur) updateIndicator(cur);
    });

    openTab('keterangan');
    renderDoaList();
});

// Sticky bar scroll
var lastScrollY = window.scrollY;
window.addEventListener('scroll', function () {
    var nav = document.getElementById('navbar');
    if (nav) nav.classList.toggle('scrolled', window.scrollY > 150);
    var sb = document.querySelector('.sticky-bar');
    if (sb) {
        if (window.scrollY > lastScrollY && window.scrollY > 100) sb.classList.add('visible');
        else sb.classList.remove('visible');
        lastScrollY = window.scrollY;
    }
});