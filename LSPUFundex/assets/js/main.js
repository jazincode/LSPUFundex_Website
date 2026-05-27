// ============================================
// LSPUFundex - Main JavaScript
// File: assets/js/main.js
// ============================================

document.addEventListener('DOMContentLoaded', function () {

    // ============================================
    // SIDEBAR TOGGLE
    // ============================================
    const toggleBtn   = document.getElementById('sidebarToggle');
    const sidebar     = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const footer      = document.querySelector('.lspu-footer');

    if (!toggleBtn) {
        console.warn('LSPUFundex: sidebarToggle button not found');
    }
    if (!sidebar) {
        console.warn('LSPUFundex: sidebar not found');
    }

    // Create dark overlay for mobile
    const overlay = document.createElement('div');
    overlay.id = 'sidebarOverlay';
    overlay.style.cssText = `
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1038;
        transition: opacity 0.3s ease;
        opacity: 0;
    `;
    document.body.appendChild(overlay);

    function isMobile() {
        return window.innerWidth <= 768;
    }

    function openSidebarMobile() {
        sidebar.classList.add('show');
        overlay.style.display = 'block';
        setTimeout(function () { overlay.style.opacity = '1'; }, 10);
    }

    function closeSidebarMobile() {
        sidebar.classList.remove('show');
        overlay.style.opacity = '0';
        setTimeout(function () { overlay.style.display = 'none'; }, 300);
    }

    function toggleDesktop() {
        sidebar.classList.toggle('sidebar-collapsed-desktop');
        if (mainContent) mainContent.classList.toggle('sidebar-hidden');
        if (footer)      footer.classList.toggle('sidebar-hidden');
    }

    // Main toggle button click
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();

            if (isMobile()) {
                if (sidebar.classList.contains('show')) {
                    closeSidebarMobile();
                } else {
                    openSidebarMobile();
                }
            } else {
                toggleDesktop();
            }
        });
    }

    // Click overlay to close on mobile
    overlay.addEventListener('click', function () {
        closeSidebarMobile();
    });

    // Click any sidebar link → auto close on mobile
    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobile()) {
                    closeSidebarMobile();
                }
            });
        });
    }

    // Handle window resize
    window.addEventListener('resize', function () {
        if (!isMobile()) {
            // Reset mobile state
            if (sidebar) sidebar.classList.remove('show');
            overlay.style.display  = 'none';
            overlay.style.opacity  = '0';
        } else {
            // Reset desktop state
            if (sidebar)      sidebar.classList.remove('sidebar-collapsed-desktop');
            if (mainContent)  mainContent.classList.remove('sidebar-hidden');
            if (footer)       footer.classList.remove('sidebar-hidden');
        }
    });

    // ============================================
    // LIVE CLOCK
    // ============================================
    const clockEl = document.getElementById('navClock');
    if (clockEl) {
        function updateClock() {
            const now = new Date();
            clockEl.textContent =
                now.toLocaleDateString('en-PH', {
                    weekday: 'short', year: 'numeric',
                    month: 'short', day: 'numeric'
                }) + '  ' +
                now.toLocaleTimeString('en-PH', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit'
                });
        }
        updateClock();
        setInterval(updateClock, 1000);
    }

    // ============================================
    // AUTO-DISMISS FLASH MESSAGES
    // ============================================
    document.querySelectorAll('.alert-box').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, 4000);
    });

    // ============================================
    // CONFIRM BEFORE DELETE
    // ============================================
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm') || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

});