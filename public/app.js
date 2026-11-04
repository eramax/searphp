(function () {
    'use strict';

    var STORAGE_KEY = 'searphp_api_key';
    var input = document.getElementById('api-key-input');
    var hidden = document.getElementById('api-key-field');
    var status = document.getElementById('api-key-status');
    var searchForm = document.querySelector('form.search');

    function getKey() {
        try {
            return localStorage.getItem(STORAGE_KEY) || '';
        } catch (e) {
            return '';
        }
    }

    function setStatus(message, tone) {
        if (!status) {
            return;
        }
        status.textContent = message;
        status.className = 'key-status' + (tone ? ' ' + tone : '');
    }

    function applyToLinks() {
        var key = getKey();
        document.querySelectorAll('a[data-auth-link]').forEach(function (link) {
            var url = new URL(link.getAttribute('href'), window.location.href);
            if (key) {
                url.searchParams.set('key', key);
            } else {
                url.searchParams.delete('key');
            }
            link.setAttribute('href', url.pathname + url.search + url.hash);
        });
    }

    function apply() {
        var key = getKey();
        if (input && document.activeElement !== input) {
            input.value = key;
        }
        if (hidden) {
            hidden.value = key;
        }
        applyToLinks();
        if (key) {
            setStatus('Key saved in this browser.', 'ok');
        } else {
            setStatus('No key saved.', 'muted');
        }
    }

    document.getElementById('api-key-save')?.addEventListener('click', function () {
        var value = input ? input.value.trim() : '';
        if (!value) {
            setStatus('Enter a key first.', 'warn');
            return;
        }
        try {
            localStorage.setItem(STORAGE_KEY, value);
            apply();
        } catch (e) {
            setStatus('Could not access localStorage.', 'warn');
        }
    });

    document.getElementById('api-key-clear')?.addEventListener('click', function () {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            // ignore
        }
        if (input) {
            input.value = '';
        }
        apply();
    });

    searchForm?.addEventListener('submit', function (event) {
        var key = getKey();
        var required = searchForm.getAttribute('data-auth-required') === '1';
        if (required && !key) {
            event.preventDefault();
            document.querySelector('.key-panel')?.setAttribute('open', '');
            if (input) {
                input.focus();
            }
            setStatus('Enter an API key first.', 'warn');
            return;
        }
        if (hidden) {
            hidden.value = key;
        }
    });

    apply();

    if (searchForm && searchForm.getAttribute('data-auth-required') === '1' && !getKey()) {
        document.querySelector('.key-panel')?.setAttribute('open', '');
        setStatus('Enter an API key to search.', 'warn');
    }
})();
