# Alnoor Group Order App — Design Spec

**Date:** 2026-09-16  
**Stack:** PHP (single file), file-based JSON storage  
**Hosting:** Hostinger (shared/PHP hosting)

---

## Overview

A lightweight group food ordering app for friends ordering from Alnoor restaurant. A shared password gates access. Each person picks a nickname per session, selects dishes from the menu, and submits their order. Orders are stored per day and can be viewed as a clean aggregated list for sending to the restaurant.

---

## File Structure

```
/
├── index.php           # entire app — UI + API
├── menu.json           # menu data (already exists)
└── orders/
    ├── .htaccess       # deny direct web access to this directory
    └── order-YYYY-MM-DD.json
```

### Order file format (`orders/order-YYYY-MM-DD.json`)

```json
[
  {
    "nickname": "Alice",
    "timestamp": "2026-09-16T12:34:56+00:00",
    "items": [
      { "dish": "Butter Chicken", "price": 2060, "qty": 1 },
      { "dish": "Basmati Rice", "price": 540, "qty": 2 }
    ]
  }
]
```

One entry per nickname per day. Submitting again replaces the existing entry for that nickname.

---

## Authentication

- Single shared password hardcoded as a PHP constant (`SHARED_PASSWORD`).
- PHP native sessions (`session_start()`). Session stores `authenticated = true` and `nickname`.
- All API actions and the main UI require an active authenticated session; unauthenticated requests redirect to the login form.
- Logout clears the session and redirects to login.

---

## UI Views

### 1. Login
- Fields: **Password**, **Nickname** (nickname is free-text, required)
- On success: session set, redirect to Order view
- On failure: inline error message, form stays

### 2. Order (main view)
- Menu rendered grouped by category (from `menu.json`)
- Each dish row: name, price, quantity stepper (0–9), running subtotal inline
- Footer: **total price** (live-updated via JS), **Submit Order** button
- Submitting POSTs to `?action=save_order`; shows success confirmation
- If a previous order exists for today's date + nickname, it is pre-loaded into the form on page load

### 3. My Order
- Tab/link from the main view
- Shows current user's submitted order for today: items, quantities, per-item subtotal, grand total
- If no order yet: prompt to go place one

### 4. Today's Summary (restaurant list)
- Tab/link accessible to all authenticated users
- Aggregates all orders for today: each dish listed once with total quantity across all users
- Format: `Butter Chicken × 3`, `Basmati Rice × 5`, etc., grouped by category
- No nicknames shown — suitable for copying and sending to the restaurant
- Shows overall total price for the whole group

---

## API Actions (all via `index.php?action=<name>`)

| Action | Method | Description |
|--------|--------|-------------|
| `save_order` | POST | Save/replace current user's order for today. Body: JSON `{items:[...]}` |
| `my_order` | GET | Return current user's order for today |
| `today_summary` | GET | Return aggregated dish list for today (no nicknames) |

All actions return JSON. All require authenticated session; return `401` otherwise.

---

## Data Flow

1. User logs in → session created with `nickname` + `authenticated=true`
2. Order view loads `menu.json` server-side, renders HTML; JS fetches `?action=my_order` to pre-fill existing order
3. On submit: JS POSTs items to `?action=save_order` → PHP reads today's file, replaces or appends entry for this nickname, writes file back
4. My Order view: JS fetches `?action=my_order`, renders items + total
5. Summary view: JS fetches `?action=today_summary`, renders grouped dish list

---

## Security & Robustness

- `orders/` directory protected by `.htaccess` (`Deny from all`) — no direct file access
- File writes use `flock()` for basic concurrency safety
- Nickname sanitized (strip tags, max 32 chars) before storage
- Password comparison uses `hash_equals()` to prevent timing attacks
- CSRF: since this is a low-stakes friend tool and all API calls are same-origin session-authenticated, no CSRF token is added (acceptable for this threat model)

---

## Out of Scope

- Per-user passwords
- Order history beyond the current day's file
- Admin panel
- Push notifications / real-time sync (page refresh is sufficient)
