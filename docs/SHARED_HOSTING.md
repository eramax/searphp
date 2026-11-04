# Shared hosting (cPanel, LiteSpeed, Apache)

SearPHP runs on normal PHP shared hosting. It does **not** need SSH, Docker, MySQL, Composer, Node, Go, or any external service.

## Requirements

- PHP **8.2 or newer** (tested on 8.2, 8.3, and 8.5)
- Extensions: `curl`, `dom`/`libxml`, `mbstring`, `json`, `fileinfo`
- `mod_rewrite` (or LiteSpeed equivalent)
- A writable `var/cache` directory
- HTTPS strongly recommended

Check the host's PHP extensions with cPanel → MultiPHP INI Editor or a temporary `phpinfo()` page. Remove the page afterwards.

## Recommended layout: app outside `public_html`

This is the safest layout. Application files (`src`, `config`, `var`, `.env`) are not inside the web root.

```
/home/USER/
├── searphp/                    <- upload the whole project here
│   ├── bootstrap.php
│   ├── config/
│   ├── public/
│   ├── src/
│   ├── var/
│   └── ...
└── public_html/
    ├── index.php               <- copy from shared-hosting/public_html/
    ├── .htaccess               <- copy from shared-hosting/public_html/
    └── .user.ini               <- copy from shared-hosting/public_html/
```

Steps:

1. Upload the project to `/home/USER/searphp`.
2. Copy the three files from `shared-hosting/public_html/` into `public_html/`.
3. If the app is not at `/home/USER/searphp`, edit `public_html/index.php` or add this to `public_html/.htaccess`:

   ```apache
   SetEnv SEARPHP_ROOT /home/USER/searphp
   ```

4. Create writable cache/log directories:

   ```bash
   mkdir -p /home/USER/searphp/var/cache /home/USER/searphp/var/log
   chmod 775 /home/USER/searphp/var/cache /home/USER/searphp/var/log
   ```

5. Copy `.env.example` to `.env` inside `/home/USER/searphp` and edit it. Keep `.env` outside `public_html`.
6. Open `https://your-domain/health`, then `https://your-domain/search?q=test&format=md`.

`public_html/index.php` requires the app's front controller, so the normal routes (`/search`, `/images`, `/mcp`, `/health`) work.

## Simple layout: everything inside `public_html`

If the host does not allow files outside `public_html`, upload the whole project into `public_html`. The project's root `.htaccess` blocks `src`, `config`, `var`, `tests`, `bin`, `docs`, `docker`, `shared-hosting`, dotfiles, and sensitive root files, then routes requests to `public/index.php`.

After upload, verify these URLs return **403** (not source code, not directory listing):

```
https://your-domain/src/App.php
https://your-domain/config/config.php
https://your-domain/.env
https://your-domain/var/cache/
```

If they return 200, stop and fix `.htaccess` / ask the host to enable `AllowOverride`.

## PHP settings

`public/.user.ini` contains safe defaults:

```
display_errors=0
display_startup_errors=0
log_errors=1
expose_php=0
memory_limit=256M
max_execution_time=30
post_max_size=1M
upload_max_filesize=1M
allow_url_fopen=0
```

`public/.user.ini` is used when the document root is `public/` or when the whole project is in `public_html`. For the recommended layout, copy it to `public_html/.user.ini`.

Some hosts use mod_php and ignore `.user.ini`; use cPanel's MultiPHP INI Editor with the same values.

## `.htaccess` notes

- If `Options -Indexes` causes a 500 error, the host disallows `Options` overrides. Remove only that line and ask the host to disable directory listing globally.
- If clean URLs return 404, `mod_rewrite` is disabled. Either enable it or ask the host to set the document root to the `public/` directory.
- Do not block `.well-known/`; it is needed for Let's Encrypt certificate validation.
- Keep `.env`, `var`, `config`, and `src` outside the web root whenever possible.

## Security on a shared host

There is no API authentication in the app. A public endpoint can be abused and may hit the host's CPU/bandwidth limits. Choose at least one:

- cPanel → **Directory Privacy / Password Protect Directory** on the app path (Basic Auth).
- Cloudflare Access / WAF / rate limiting.
- Firewall or IP allowlist if your agents have fixed IPs.
- Put the app behind a private VPN.

Also:

- Set `CORS_ORIGINS` to your agent/app origins instead of `*`.
- Set `APP_DEBUG=false`.
- Keep `LOG_QUERIES=false`.
- Set `TRUST_PROXY=false` unless you are behind a trusted proxy and direct access is blocked.
- On a small shared plan, lower `RATE_LIMIT` (for example `60`) and consider `ENGINE_BRAVE=false`; Brave is the most rate-limited engine.
- Do not expose `.env`, `config/`, `src/`, or `var/`; verify with the 403 tests above.
- Enable HTTPS and force it from cPanel/Cloudflare.
- Keep PHP and the host panel updated.

For server-wide hardening guidance, see [SECURITY.md](SECURITY.md).

## Caching and disk usage

The file cache is local to the hosting account. It is not shared with other servers.

- Old cache/temp/lock files are cleaned opportunistically after 2 hours.
- Cache writes stop if less than 50 MB is free.
- Logs rotate at 5 MB.
- To clear the cache manually: delete files inside `var/cache` (keep the directory).
- If you use multiple app replicas, each has its own cache and rate counters.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| 500 on every page | `Options`/`.htaccess` unsupported, or PHP version too old |
| 404 on `/search` | `mod_rewrite` disabled |
| 403 on `/src/...` | Correct; the protection is working |
| 200 on `/.env` | **Unsafe**; fix `.htaccess` or move files outside `public_html` |
| `var/cache` not writable | Set directory permissions to 775 or match the PHP user |
| `curl`/`dom` missing | Enable the PHP extensions in cPanel |
| Empty results and `partial: true` | Engine challenge/rate limit; inspect `meta.engines` |

## Minimum test after deployment

```bash
curl -i https://your-domain/health
curl -i "https://your-domain/search?q=php+8.5&format=md&limit=3"
curl -i -X OPTIONS -H "Origin: https://example.com" https://your-domain/search
```

Expect `200`, `text/markdown`, and `204` respectively.
