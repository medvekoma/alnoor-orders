# Alnoor Order App

See `README.md` for full project docs.

## Key constraints for AI

- Single deployable file — no Composer, no npm, no build step
- PHP 7.4+ only (`fn()` arrows OK, no named args, no fibers)
- Prices in HUF (integers only)
- Nickname: `strip_tags` + `htmlspecialchars`, max 32 chars
- File writes must use `flock()` for concurrency safety
