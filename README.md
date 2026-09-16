# Alnoor Order App

Single-file PHP group ordering app for Alnoor restaurant. Shared-password auth, per-day JSON order storage, four views: login, order, my order, today's summary.

## Stack

- PHP 7.4+ (Hostinger shared hosting)
- Vanilla JS embedded in `index.php` — no frameworks, no build step
- JSON file storage in `orders/`

## Files

- `index.php` — entire app (auth, API handlers, HTML/CSS/JS)
- `menu.json` — menu data, read server-side and embedded as JS constant `MENU`
- `config.php` — gitignored, holds `SHARED_PASSWORD`; copy from `config.example.php`
- `orders/order-YYYY-MM-DD.json` — daily order storage (one entry per nickname, replaced on resubmit)
- `orders/.htaccess` — blocks direct web access (`Deny from all`)

## API Endpoints (`?action=`)

| Action | Method | Returns |
|--------|--------|---------|
| `save_order` | POST | `{"ok":true}` or `{"error":"..."}` |
| `my_order` | GET | `{"nickname":"...","items":[...],"total":N}` or `{"items":null}` |
| `today_summary` | GET | `{"dishes":[{"dish":"...","qty":N,"price":N}],"total":N}` |

All endpoints require a valid session; return HTTP 401 otherwise.

## Views

1. **Order** — menu with qty steppers, pre-loads existing order on login
2. **My Order** — current user's order for today
3. **Summary** — aggregated dish counts for today (no names)

## Configuration

The shared password lives in `config.php`, which is gitignored. To set it up:

```bash
cp config.example.php config.php
# edit config.php and set SHARED_PASSWORD to your actual secret
```

`index.php` loads it via `require_once __DIR__ . '/config.php'`. Never commit `config.php`.

## Deployment

1. Copy and edit `config.php` with the real shared password (see above)
2. Upload `index.php`, `config.php`, `menu.json`, `orders/.htaccess` to your web root on Hostinger
3. Ensure `orders/` is writable by the web server (chmod 755 or 775)
4. Verify `orders/` returns 403 Forbidden when accessed directly

## Local Dev

```bash
php -S localhost:8000
```
