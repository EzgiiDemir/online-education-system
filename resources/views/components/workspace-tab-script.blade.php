{{-- Generic client-side tab switcher shared by workspace pages (Course
     Workspace, Manager dashboard): a <ul id="{{ $id }}"> holding
     [data-tab="key"] links, sibling [data-tab-panel="key"] panels, and any
     number of [data-tab-jump="key"] buttons elsewhere on the page. Hash-based
     so a link like "#curriculum" deep-links straight to a tab. --}}
@php($id = $id ?? 'workspace-tabs')
<script>
(function(){
    var tabs = document.getElementById('{{ $id }}');
    if (!tabs) return;
    var links = tabs.querySelectorAll('[data-tab]');
    var panels = document.querySelectorAll('[data-tab-panel]');
    function activate(name){
        if (!name) return;
        links.forEach(function(l){ l.classList.toggle('is-active', l.dataset.tab === name); });
        panels.forEach(function(p){ p.hidden = p.dataset.tabPanel !== name; });
    }
    tabs.addEventListener('click', function(e){
        var link = e.target.closest('[data-tab]');
        if (!link) return;
        e.preventDefault();
        activate(link.dataset.tab);
        history.replaceState(null, '', '#' + link.dataset.tab);
    });
    document.querySelectorAll('[data-tab-jump]').forEach(function(btn){
        btn.addEventListener('click', function(){
            activate(btn.dataset.tabJump);
            history.replaceState(null, '', '#' + btn.dataset.tabJump);
        });
    });
    var initial = window.location.hash ? window.location.hash.slice(1) : (tabs.querySelector('[data-tab]')?.dataset.tab);
    if (!document.querySelector('[data-tab-panel="' + initial + '"]')) initial = tabs.querySelector('[data-tab]')?.dataset.tab;
    activate(initial);
})();
</script>
