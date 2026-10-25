<?php if (isset($_SESSION['user_id'])): ?>
        </main>
    </div>
    <!-- Mobile Sidebar Backdrop -->
    <div class="sidebar-backdrop d-lg-none" id="sidebarBackdrop"></div>
<?php endif; ?>

<script>
// Sidebar Toggle & Dismiss on Mobile
(function() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    function closeSidebar() {
        sidebar?.classList.remove('show');
        backdrop?.classList.remove('show');
        document.body.style.overflow = '';
    }

    function openSidebar() {
        sidebar?.classList.add('show');
        backdrop?.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    toggle?.addEventListener('click', function(e) {
        e.stopPropagation();
        if (sidebar?.classList.contains('show')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    backdrop?.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar?.classList.contains('show')) {
            closeSidebar();
        }
    });
})();
</script>
</body>
</html>
