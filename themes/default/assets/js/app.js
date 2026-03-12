/* TeenyTinyCMS – app.js */

(function () {
    'use strict';

    // ── Sticky navbar shadow ──────────────────────────────────────────
    var navbar = document.querySelector('.site-navbar');
    if (navbar) {
        var onScroll = function () {
            navbar.classList.toggle('is-scrolled', window.scrollY > 4);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // ── Mobile menu toggle ────────────────────────────────────────────
    var toggle = document.querySelector('.navbar-toggle');
    var drawer = document.querySelector('.navbar-drawer');
    if (toggle && drawer) {
        toggle.addEventListener('click', function () {
            var open = drawer.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // ── Navbar search toggle ──────────────────────────────────────────
    var searchToggle = document.querySelector('.navbar-search__toggle');
    var searchForm   = document.querySelector('.navbar-search__form');
    if (searchToggle && searchForm) {
        searchToggle.addEventListener('click', function () {
            var open = searchForm.classList.toggle('is-open');
            searchToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                searchForm.querySelector('input').focus();
            }
        });
    }

    // ── Client-side search (on the /search page) ──────────────────────
    var searchInput   = document.getElementById('search-input');
    var searchResults = document.getElementById('search-results');
    if (searchInput && searchResults) {
        var lang      = searchResults.getAttribute('data-lang') || 'en';
        var index     = null;
        var debounce  = null;

        // Pre-fill from ?q= query string
        var params = new URLSearchParams(window.location.search);
        if (params.get('q')) {
            searchInput.value = params.get('q');
        }

        function loadIndex(cb) {
            if (index) return cb(index);
            fetch('/' + lang + '/search-index.json')
                .then(function (r) { return r.json(); })
                .then(function (data) { index = data; cb(data); })
                .catch(function () {
                    searchResults.innerHTML = '<p class="search-results__hint">Search index not available.</p>';
                });
        }

        function renderResults(query) {
            if (!query || query.length < 2) {
                var hint = lang === 'de' ? 'Gib einen Suchbegriff ein.' : 'Type to search.';
                searchResults.innerHTML = '<p class="search-results__hint">' + hint + '</p>';
                return;
            }

            loadIndex(function (data) {
                var q       = query.toLowerCase();
                var matches = data.filter(function (item) {
                    var haystack = (item.title + ' ' + item.excerpt + ' ' + item.tags.join(' ')).toLowerCase();
                    var words = q.split(/\s+/).filter(Boolean);
                    return words.every(function (w) { return haystack.indexOf(w) !== -1; });
                });

                if (matches.length === 0) {
                    var none = lang === 'de' ? 'Keine Ergebnisse.' : 'No results found.';
                    searchResults.innerHTML = '<p class="search-results__hint">' + none + '</p>';
                    return;
                }

                var countLabel = lang === 'de'
                    ? matches.length + (matches.length === 1 ? ' Ergebnis' : ' Ergebnisse')
                    : matches.length + (matches.length === 1 ? ' result' : ' results');

                var html = '<p class="search-results__count">' + countLabel + '</p>';
                matches.forEach(function (item) {
                    var url = '/' + lang + '/blog/' + encodeURIComponent(item.slug);
                    html += '<div class="search-result-item">'
                         +  '<h3 class="search-result-item__title"><a href="' + url + '">' + escHtml(item.title) + '</a></h3>'
                         +  '<p class="search-result-item__meta">' + escHtml(item.date);
                    if (item.author) {
                        html += ' &middot; ' + escHtml(item.author);
                    }
                    if (item.tags.length) {
                        html += ' &middot; ' + item.tags.map(escHtml).join(', ');
                    }
                    html += '</p>'
                         +  '<p class="search-result-item__excerpt">' + escHtml(item.excerpt) + '</p>'
                         +  '</div>';
                });
                searchResults.innerHTML = html;
            });
        }

        function escHtml(s) {
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(s));
            return d.innerHTML;
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(function () {
                renderResults(searchInput.value.trim());
            }, 200);
        });

        // Trigger search if ?q= was set
        if (searchInput.value.trim()) {
            renderResults(searchInput.value.trim());
        }
    }
}());
