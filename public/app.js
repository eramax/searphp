(function () {
    'use strict';

    var STORAGE_KEY = 'searphp_api_key';
    var COOKIE_NAME = 'searphp_key';
    var input = document.getElementById('api-key-input');
    var hidden = document.getElementById('api-key-field');
    var status = document.getElementById('api-key-status');
    var searchForm = document.querySelector('form.search');
    var keyPage = document.body.getAttribute('data-key-page') === '1';

    function setCookie(key) {
        var secure = window.location.protocol === 'https:' ? '; Secure' : '';
        if (key) {
            document.cookie = COOKIE_NAME + '=' + encodeURIComponent(key)
                + '; Path=/; Max-Age=31536000; SameSite=Lax' + secure;
        } else {
            document.cookie = COOKIE_NAME + '=; Path=/; Max-Age=0; SameSite=Lax' + secure;
        }
    }

    function readCookie() {
        var prefix = COOKIE_NAME + '=';
        var parts = document.cookie ? document.cookie.split(';') : [];
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].trim();
            if (part.indexOf(prefix) === 0) {
                try {
                    return decodeURIComponent(part.substring(prefix.length));
                } catch (e) {
                    return '';
                }
            }
        }
        return '';
    }

    // If a keyed link was opened, keep the key and remove it from the address bar.
    try {
        var params = new URLSearchParams(window.location.search);
        var urlKey = params.get('key');
        if (urlKey) {
            localStorage.setItem(STORAGE_KEY, urlKey);
            setCookie(urlKey);
            params.delete('key');
            var query = params.toString();
            history.replaceState(null, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
        }
    } catch (e) {
        // ignore storage/history failures
    }

    function getKey() {
        try {
            var key = localStorage.getItem(STORAGE_KEY) || '';
            if (!key) {
                key = readCookie();
                if (key) {
                    localStorage.setItem(STORAGE_KEY, key);
                }
            }
            return key;
        } catch (e) {
            return readCookie();
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
        if (key) {
            setCookie(key);
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
            setCookie(value);
            apply();
            if (keyPage) {
                window.location.reload();
                return;
            }
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
        setCookie('');
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

    // On the key-required page, retry once automatically if this browser
    // already has a key in localStorage.
    if (keyPage && getKey()) {
        try {
            var retryFlag = 'searphp_key_retry:' + window.location.pathname + window.location.search;
            if (!sessionStorage.getItem(retryFlag)) {
                sessionStorage.setItem(retryFlag, '1');
                setCookie(getKey());
                window.location.reload();
            } else {
                setStatus('Saved key was rejected. Enter a valid key.', 'warn');
            }
        } catch (e) {
            setStatus('Press Save and continue.', 'warn');
        }
    }

    // Image preview modal. If <dialog> is unavailable, the normal link opens.
    var imageDialog = document.getElementById('image-modal');
    if (imageDialog && typeof imageDialog.showModal === 'function') {
        var modalImage = document.getElementById('image-modal-img');
        var modalTitle = document.getElementById('image-modal-title');
        var modalSource = document.getElementById('image-modal-source');

        document.querySelectorAll('a[data-image-modal]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                if (modalImage) {
                    modalImage.src = link.getAttribute('data-full') || link.href;
                    modalImage.alt = link.getAttribute('data-title') || '';
                }
                if (modalTitle) {
                    modalTitle.textContent = link.getAttribute('data-title') || '';
                }
                if (modalSource) {
                    var source = link.getAttribute('data-source') || '';
                    if (source) {
                        modalSource.href = source;
                        modalSource.hidden = false;
                    } else {
                        modalSource.hidden = true;
                    }
                }
                imageDialog.showModal();
            });
        });

        imageDialog.addEventListener('click', function (event) {
            if (event.target === imageDialog) {
                imageDialog.close();
            }
        });
    }
})();
