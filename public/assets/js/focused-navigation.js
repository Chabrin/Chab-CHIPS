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
            if (page.dataset.navigationKey) link.dataset.navigationKey = page.dataset.navigationKey;
            if (page.hasAttribute('aria-current')) link.setAttribute('aria-current', 'page');
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
        if (event.key === 'ArrowDown') { const first = results.querySelector('a'); if (first) { event.preventDefault(); first.focus(); } }
        if (event.key === 'Enter') { const first = results.querySelector('a'); if (first) { event.preventDefault(); first.click(); } }
    });
    results.addEventListener('keydown', event => {
        const links = [...results.querySelectorAll('a')];
        const index = links.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' && index >= 0) { event.preventDefault(); links[Math.min(index + 1, links.length - 1)].focus(); }
        if (event.key === 'ArrowUp' && index >= 0) { event.preventDefault(); if (index === 0) search.focus(); else links[index - 1].focus(); }
        if (event.key === 'Escape') { search.value = ''; filter(); search.focus(); }
    });
    const pathname = location.pathname.replace(/\/+$/, '') || '/';
    const dashboard = nav.querySelector('.fn-dashboard');
    const dashboardPath = new URL(dashboard.href, location.href).pathname.replace(/\/+$/, '') || '/';
    // Dashboard takes precedence even if a database menu entry has the same URL.
    // Empty links and fragments resolve to the current URL, so never treat them as pages.
    const parsedURLs = new Map();
    const candidates = pages.filter(page => {
        const href = (page.getAttribute('href') || '').trim();
        if (!href || href.startsWith('#')) return false;
        try {
            const url = new URL(href, location.href);
            if (url.origin !== location.origin || !['http:', 'https:'].includes(url.protocol)) return false;
            parsedURLs.set(page, url);
            return true;
        } catch {
            return false;
        }
    });
    const matchingPath = page => parsedURLs.get(page).pathname.replace(/\/+$/, '') || '/';
    // A query-specific destination is not a match for a different query. A
    // queryless link remains valid when the current page adds filters or paging.
    const matchingQuery = page => !parsedURLs.get(page).search || parsedURLs.get(page).search === location.search;
    const storageKey = 'focused-navigation-selection';
    let previousSelection;
    try {
        previousSelection = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
        if (previousSelection && previousSelection.pathname !== pathname) {
            sessionStorage.removeItem(storageKey);
            previousSelection = null;
        }
    } catch { previousSelection = null; }
    nav.addEventListener('click', event => {
        const link = event.target.closest('a[data-navigation-key]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        try {
            const url = new URL(link.href, location.href);
            if (url.origin === location.origin) sessionStorage.setItem(storageKey, JSON.stringify({
                pathname: url.pathname.replace(/\/+$/, '') || '/', key: link.dataset.navigationKey,
            }));
        } catch { /* Navigation still works if storage is unavailable. */ }
    });
    const unambiguous = matches => {
        if (matches.length === 1) return matches[0];
        if (!previousSelection?.key) return undefined;
        const selected = matches.filter(page => page.dataset?.navigationKey === previousSelection.key);
        return selected.length === 1 ? selected[0] : undefined;
    };
    const exact = candidates.filter(page => matchingPath(page) === pathname);
    const exactQuery = exact.filter(page => parsedURLs.get(page).search && parsedURLs.get(page).search === location.search);
    const genericExact = exact.filter(page => !parsedURLs.get(page).search);
    const nested = candidates.filter(page => {
        const path = matchingPath(page);
        return path !== '/' && path !== dashboardPath && pathname.startsWith(path + '/') && matchingQuery(page);
    }).sort((a, b) => matchingPath(b).length - matchingPath(a).length);
    const deepestNested = nested.length ? nested.filter(page => matchingPath(page).length === matchingPath(nested[0]).length) : [];
    const alias = nav.dataset?.currentDestination;
    const aliasMatches = alias ? candidates.filter(page => matchingPath(page) === alias && matchingQuery(page)) : [];
    const current = pathname === dashboardPath ? dashboard :
        (exactQuery.length ? unambiguous(exactQuery) :
            genericExact.length ? unambiguous(genericExact) :
                exact.length ? undefined : alias ? unambiguous(aliasMatches) : unambiguous(deepestNested));
    if (current) {
        current.setAttribute('aria-current', 'page');
        const group = current.closest('.fn-group');
        if (group) {
            openGroup(group.id);
            openModule(current.closest('.fn-module'));
            nav.querySelector('[aria-controls="' + group.id + '"]').classList.add('fn-current-parent');
            current.closest('.fn-module').querySelector('button').classList.add('fn-current-parent');
        }
        // Keep the selected page visible even when a toolbar overlaps the viewport edge.
        requestAnimationFrame(() => {
            const sidebar = nav.closest('.sidebar-wrapper');
            if (!sidebar) return;
            if (current === dashboard) {
                sidebar.scrollTop = 0;
                return;
            }
            const bounds = sidebar.getBoundingClientRect();
            const selected = current.getBoundingClientRect();
            sidebar.scrollTop += selected.top - bounds.top - (sidebar.clientHeight - selected.height) / 2;
        });
    }
})();
