// ============================================================
// assets/js/app.js  — TKA Kecamatan App Scripts v3.0
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // ── Sidebar Toggle ─────────────────────────────────────
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar       = document.getElementById('sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 992
                && !sidebar.contains(e.target)
                && e.target !== sidebarToggle
                && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // ── Scroll to Top ──────────────────────────────────────
    const scrollBtn = document.getElementById('scrollTop');
    if (scrollBtn) {
        window.addEventListener('scroll', () => {
            scrollBtn.style.display = window.scrollY > 320 ? 'flex' : 'none';
        });
        scrollBtn.addEventListener('click', () =>
            window.scrollTo({ top: 0, behavior: 'smooth' })
        );
    }

    // ── DataTables (global init) ───────────────────────────
    if (typeof $.fn.DataTable !== 'undefined') {

        // Ganti alert() DataTables dengan console.warn — tidak muncul popup di browser
        $.fn.dataTable.ext.errMode = 'none';
        $(document).on('error.dt', function (e, settings, techNote, message) {
            console.warn('DataTables warning [' + (settings.sTableId || 'no-id') + ']:', message);
        });

        $('.datatable').each(function () {
            if ($.fn.DataTable.isDataTable(this)) return;

            const $table  = $(this);
            const thCount = $table.find('thead th').length;

            // Validasi: semua baris non-colspan harus memiliki jumlah td == jumlah th
            let valid = true;
            $table.find('tbody tr').each(function () {
                if ($(this).find('td[colspan]').length) return; // skip empty-state row
                const tdCount = $(this).find('td').length;
                if (tdCount > 0 && tdCount !== thCount) {
                    valid = false;
                    return false;
                }
            });
            if (!valid) {
                console.warn('DataTables skip — column mismatch on table:', this.id || '(no id)');
                return;
            }

            try {
                $table.DataTable({
                    language: {
                        search:         'Cari:',
                        lengthMenu:     'Tampilkan _MENU_ data',
                        info:           'Data _START_\u2013_END_ dari _TOTAL_',
                        infoEmpty:      'Tidak ada data',
                        infoFiltered:   '(dari _MAX_ total)',
                        paginate:       { previous: '\u2039', next: '\u203a' },
                        zeroRecords:    'Tidak ada data yang cocok',
                        emptyTable:     'Belum ada data',
                        loadingRecords: 'Memuat...',
                    },
                    responsive:  true,
                    pageLength:  15,
                    dom: "<'row mb-2'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                         "<'row'<'col-sm-12'tr>>" +
                         "<'row mt-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                });
            } catch(e) {
                console.error('DataTables error (' + (this.id || 'no id') + '):', e.message);
            }
        });
    }

    // ── SweetAlert Hapus Konfirmasi ────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            const msg  = this.dataset.confirm || 'Yakin ingin menghapus data ini?';
            const href = this.href || this.dataset.href;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Konfirmasi Hapus',
                    html: msg,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: '<i class="bi bi-trash me-1"></i>Ya, Hapus!',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                }).then(result => {
                    if (result.isConfirmed) window.location.href = href;
                });
            } else {
                if (confirm(msg)) window.location.href = href;
            }
        });
    });

    // ── Preview Gambar ─────────────────────────────────────
    const fotoInput   = document.getElementById('gambar');
    const fotoPreview = document.getElementById('gambar-preview');
    if (fotoInput && fotoPreview) {
        fotoInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => {
                    fotoPreview.src = e.target.result;
                    fotoPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // ── Auto-dismiss alerts (5 detik) ─────────────────────
    document.querySelectorAll('.alert-dismissible').forEach(el => {
        if (el.classList.contains('alert-success') || el.classList.contains('alert-info')) {
            setTimeout(() => {
                if (typeof bootstrap !== 'undefined') {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
                    if (bsAlert) bsAlert.close();
                }
            }, 5000);
        }
    });

    // ── Bootstrap Tooltips ────────────────────────────────
    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                new bootstrap.Tooltip(el, { trigger: 'hover' });
            });
        }
    } catch (e) {
        console.warn('Tooltip init failed:', e.message);
    }

    // ── Input uppercase helper ────────────────────────────
    document.querySelectorAll('[data-uppercase]').forEach(el => {
        el.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
    });

    // ── Fullscreen Toggle ─────────────────────────────────
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const fsIcon        = document.getElementById('fullscreenIcon');
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', () => {
            if (!document.fullscreenElement) {
                const docEl = document.documentElement;
                if (docEl.requestFullscreen) {
                    docEl.requestFullscreen();
                } else if (docEl.webkitRequestFullscreen) { /* Safari */
                    docEl.webkitRequestFullscreen();
                } else if (docEl.msRequestFullscreen) { /* IE11 */
                    docEl.msRequestFullscreen();
                }
                if (fsIcon) fsIcon.classList.replace('bi-fullscreen', 'bi-fullscreen-exit');
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) { /* Safari */
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) { /* IE11 */
                    document.msExitFullscreen();
                }
                if (fsIcon) fsIcon.classList.replace('bi-fullscreen-exit', 'bi-fullscreen');
            }
        });
    }

    // ── Dark Mode Toggle ──────────────────────────────────
    const darkModeToggle = document.getElementById('darkModeToggle');
    const darkModeIcon   = document.getElementById('darkModeIcon');
    const html           = document.documentElement;
    const body           = document.body;

    if (darkModeToggle) {
        // Sync icon on load
        if (html.classList.contains('dark-mode') || body.classList.contains('dark-mode')) {
            if (darkModeIcon) {
                darkModeIcon.classList.remove('bi-moon-stars-fill');
                darkModeIcon.classList.add('bi-sun-fill');
            }
        }

        darkModeToggle.addEventListener('click', () => {
            html.classList.toggle('dark-mode');
            body.classList.toggle('dark-mode');
            
            const isDark = html.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
            
            if (darkModeIcon) {
                if (isDark) {
                    darkModeIcon.classList.replace('bi-moon-stars-fill', 'bi-sun-fill');
                } else {
                    darkModeIcon.classList.replace('bi-sun-fill', 'bi-moon-stars-fill');
                }
            }
        });
    }
});

// ── SweetAlert helpers ─────────────────────────────────────
function showSuccess(msg, timer = 2000) {
    Swal.fire({ icon: 'success', title: 'Berhasil!', text: msg, timer, showConfirmButton: false });
}
function showError(msg) {
    Swal.fire({ icon: 'error', title: 'Gagal!', html: msg });
}
function showInfo(msg) {
    Swal.fire({ icon: 'info', title: 'Informasi', text: msg });
}
