<script>
(function(){
    try {
        if (localStorage.getItem('sidebar') === 'collapsed') {
            document.documentElement.dataset.sidebar = 'collapsed';
        }
    } catch (e) {}
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var html = document.documentElement;
    document.querySelectorAll('[data-sidebar-collapse]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var collapsed = html.dataset.sidebar === 'collapsed';
            if (collapsed) { delete html.dataset.sidebar; } else { html.dataset.sidebar = 'collapsed'; }
            try { localStorage.setItem('sidebar', collapsed ? 'expanded' : 'collapsed'); } catch (e) {}
        });
    });
    function closeMobileSidebar() { delete html.dataset.sidebarMobile; }
    document.querySelectorAll('[data-sidebar-mobile-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            html.dataset.sidebarMobile = html.dataset.sidebarMobile === 'open' ? '' : 'open';
        });
    });
    var backdrop = document.querySelector('[data-sidebar-backdrop]');
    if (backdrop) backdrop.addEventListener('click', closeMobileSidebar);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMobileSidebar();
    });
    document.querySelectorAll('.app-sidebar .sidebar-link').forEach(function (link) {
        link.addEventListener('click', closeMobileSidebar);
    });
});
</script>
