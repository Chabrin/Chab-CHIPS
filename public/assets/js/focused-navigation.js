(() => {
    const nav = document.getElementById('focused-navigation');
    if (!nav) return;
    const categories = nav.querySelector('.fn-categories');
    const groups = [...nav.querySelectorAll('.fn-group')];
    const search = nav.querySelector('input');
    const browse = nav.querySelector('.fn-browse');
    const results = nav.querySelector('.fn-results');
    const pages = [...nav.querySelectorAll('a.fn-page'), nav.querySelector('.fn-dashboard')];
    function openGroup(id) {
        categories.hidden = !!id;
        groups.forEach(group => { group.hidden = group.id !== id; });
        nav.querySelectorAll('[data-group]').forEach(button => button.setAttribute('aria-expanded', String('fn-group-' + button.dataset.group === id)));
    }
    function openModule(module) {
        module.closest('.fn-group').querySelectorAll('.fn-module').forEach(item => {
            item.querySelector('.fn-pages').hidden = item !== module;
            item.querySelector('button').setAttribute('aria-expanded', String(item === module));
        });
    }
    nav.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.dataset.group) {
            openGroup('fn-group-' + button.dataset.group);
            nav.querySelector('.fn-group:not([hidden]) .fn-back').focus();
        } else if (button.classList.contains('fn-back')) {
            const id = button.closest('.fn-group').id;
            openGroup(null);
            nav.querySelector('[aria-controls="' + id + '"]').focus();
        } else if (button.classList.contains('fn-module-toggle')) {
            const module = button.closest('.fn-module');
            if (button.getAttribute('aria-expanded') === 'true') {
                module.querySelector('.fn-pages').hidden = true;
                button.setAttribute('aria-expanded', 'false');
            } else openModule(module);
        }
    });
    const normalize = value => value.toLocaleLowerCase().trim().replace(/\s+/g, ' ');
    function filter() {
        const term = normalize(search.value);
        browse.hidden = !!term;
        results.hidden = !term;
        results.replaceChildren();
        if (!term) return;
        const matches = pages.filter(page => normalize(page.textContent + ' ' + (page.dataset.path || '')).includes(term));
        const count = document.createElement('p');
        count.className = 'fn-caption';
        count.textContent = matches.length ? `${matches.length} destinations` : 'No matching pages';
        results.append(count);
        matches.forEach(page => {
            const link = document.createElement('a');
            link.className = 'fn-page';
            link.href = page.href;
            link.textContent = page.classList.contains('fn-dashboard') ? 'Dashboard' : page.textContent.trim();
            if (page.dataset.path) {
                const path = document.createElement('span');
                path.className = 'fn-result-path';
                path.textContent = page.dataset.path;
                link.append(path);
            }
            results.append(link);
        });
    }
    search.addEventListener('input', filter);
    search.addEventListener('keydown', event => {
        if (event.key === 'Escape') { search.value = ''; filter(); }
        if (event.key === 'Enter') { const first = results.querySelector('a'); if (first) { event.preventDefault(); first.click(); } }
    });
    const pathname = location.pathname.replace(/\/$/, '') || '/';
    const current = pages.filter(page => {
        const url = new URL(page.href, location.href);
        const path = url.pathname.replace(/\/$/, '') || '/';
        return url.origin === location.origin && (path === pathname || (path !== '/' && pathname.startsWith(path + '/')));
    }).sort((a,b) => new URL(b.href).pathname.length - new URL(a.href).pathname.length)[0];
    if (current) {
        current.setAttribute('aria-current', 'page');
        const group = current.closest('.fn-group');
        if (group) { openGroup(group.id); openModule(current.closest('.fn-module')); }
    }
})();
