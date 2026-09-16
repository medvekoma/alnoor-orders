# Alnoor Group Order App — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a single-file PHP group ordering app for Alnoor restaurant with shared-password auth, per-day JSON order storage, and four views: login, order, my order, today's summary.

**Architecture:** Everything lives in `index.php` — it serves the full UI and handles all API actions via `?action=` query params. Orders are stored in `orders/order-YYYY-MM-DD.json`, one file per day, one entry per nickname (replaced on resubmit). The frontend is vanilla JS embedded in the PHP file.

**Tech Stack:** PHP 7.4+, native PHP sessions, vanilla JS (no frameworks), JSON file storage, `.htaccess` for directory protection.

**Spec:** `docs/superpowers/specs/2026-09-16-alnoor-order-app-design.md`

## Global Constraints

- PHP 7.4+ compatible (Hostinger shared hosting)
- No Composer, no npm, no build step — single deployable file
- All prices in HUF (integers)
- Shared password stored as PHP constant `SHARED_PASSWORD` at top of `index.php`
- Nickname: strip_tags, max 32 chars, required
- File writes use `flock()` for concurrency safety
- `orders/` protected by `.htaccess` with `Deny from all`
- All API responses: `Content-Type: application/json`, HTTP 401 for unauthenticated

---

### Task 1: Project scaffold and `orders/` directory protection

**Files:**
- Create: `index.php` (skeleton only)
- Create: `orders/.htaccess`

**Interfaces:**
- Produces: `index.php` with `SHARED_PASSWORD` constant, `session_start()`, basic routing skeleton; `orders/` directory protected from direct web access

- [ ] **Step 1: Create `orders/.htaccess`**

```
Deny from all
```

- [ ] **Step 2: Create `index.php` skeleton**

```php
<?php
define('SHARED_PASSWORD', 'change-me');
define('ORDERS_DIR', __DIR__ . '/orders');

session_start();

$action = $_GET['action'] ?? null;

// API routing
if ($action !== null) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['authenticated'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthenticated']);
        exit;
    }
    switch ($action) {
        case 'my_order':    handleMyOrder();    break;
        case 'today_summary': handleTodaySummary(); break;
        case 'save_order':  handleSaveOrder();  break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Unknown action']);
    }
    exit;
}

// UI routing
if (!isset($_SESSION['authenticated'])) {
    renderLogin();
} else {
    renderApp();
}

// --- Helpers ---

function todayFile(): string {
    return ORDERS_DIR . '/order-' . date('Y-m-d') . '.json';
}

function readOrders(): array {
    $file = todayFile();
    if (!file_exists($file)) return [];
    $data = file_get_contents($file);
    return json_decode($data, true) ?? [];
}

function writeOrders(array $orders): void {
    $file = todayFile();
    $fp = fopen($file, 'c');
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($orders, JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function sanitizeNickname(string $nick): string {
    return substr(strip_tags(trim($nick)), 0, 32);
}

// --- Stubs (filled in subsequent tasks) ---
function handleMyOrder(): void    { echo json_encode(['items' => []]); }
function handleTodaySummary(): void { echo json_encode(['dishes' => []]); }
function handleSaveOrder(): void  { echo json_encode(['ok' => true]); }
function renderLogin(): void      { echo '<h1>Login</h1>'; }
function renderApp(): void        { echo '<h1>App</h1>'; }
```

- [ ] **Step 3: Create the `orders/` directory if it doesn't exist**

```bash
mkdir -p orders
```

- [ ] **Step 4: Verify PHP parses without errors**

```bash
php -l index.php
```

Expected: `No syntax errors detected in index.php`

- [ ] **Step 5: Commit**

```bash
git init   # only if not already a git repo
git add index.php orders/.htaccess
git commit -m "feat: scaffold index.php and protect orders directory"
```

---

### Task 2: Authentication — login POST handler

**Files:**
- Modify: `index.php` — add login POST handling before the UI routing block, replace `renderLogin()` stub

**Interfaces:**
- Consumes: `SHARED_PASSWORD` constant, `sanitizeNickname()`
- Produces: `$_SESSION['authenticated'] = true`, `$_SESSION['nickname']` set on successful login; `renderLogin()` renders a real HTML form

- [ ] **Step 1: Add login POST handler in `index.php`**

Insert this block just before the `if (!isset($_SESSION['authenticated']))` UI routing check:

```php
// Login POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['authenticated'])) {
    $password = $_POST['password'] ?? '';
    $nickname = sanitizeNickname($_POST['nickname'] ?? '');
    $error = '';
    if ($nickname === '') {
        $error = 'Nickname is required.';
    } elseif (!hash_equals(SHARED_PASSWORD, $password)) {
        $error = 'Wrong password.';
    } else {
        $_SESSION['authenticated'] = true;
        $_SESSION['nickname'] = $nickname;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    renderLogin($error);
    exit;
}
```

- [ ] **Step 2: Replace the `renderLogin()` stub with a real form**

```php
function renderLogin(string $error = ''): void {
    $errorHtml = $error ? '<p class="error">' . htmlspecialchars($error) . '</p>' : '';
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Alnoor — Login</title>
      <style>
        body { font-family: sans-serif; max-width: 400px; margin: 80px auto; padding: 0 1rem; }
        h1 { margin-bottom: 1.5rem; }
        label { display: block; margin-top: 1rem; font-weight: bold; }
        input { width: 100%; padding: .5rem; margin-top: .25rem; box-sizing: border-box; font-size: 1rem; }
        button { margin-top: 1.5rem; width: 100%; padding: .75rem; font-size: 1rem; cursor: pointer; }
        .error { color: red; margin-top: 1rem; }
      </style>
    </head>
    <body>
      <h1>Alnoor Order</h1>
      <form method="post">
        <label>Nickname
          <input type="text" name="nickname" maxlength="32" required autofocus>
        </label>
        <label>Password
          <input type="password" name="password" required>
        </label>
        $errorHtml
        <button type="submit">Enter</button>
      </form>
    </body>
    </html>
    HTML;
}
```

- [ ] **Step 3: Verify PHP parses without errors**

```bash
php -l index.php
```

- [ ] **Step 4: Manual smoke test**

Start a local PHP server:
```bash
php -S localhost:8000
```
Open `http://localhost:8000` in a browser. Verify:
- Login form renders
- Wrong password shows "Wrong password."
- Empty nickname shows "Nickname is required."
- Correct password + nickname redirects to the app stub (`<h1>App</h1>`)

- [ ] **Step 5: Commit**

```bash
git add index.php
git commit -m "feat: login form and session authentication"
```

---

### Task 3: API handlers — `save_order`, `my_order`, `today_summary`

**Files:**
- Modify: `index.php` — replace the three API stub functions

**Interfaces:**
- Consumes: `readOrders()`, `writeOrders()`, `todayFile()`, `$_SESSION['nickname']`
- Produces:
  - `handleMyOrder()` → `{"nickname":"Alice","items":[...],"total":2600}` or `{"items":null}`
  - `handleSaveOrder()` → `{"ok":true}` or `{"error":"..."}` with HTTP 400
  - `handleTodaySummary()` → `{"dishes":[{"dish":"Butter Chicken","qty":3,"price":2060}],"total":12345}`

- [ ] **Step 1: Replace `handleMyOrder()`**

```php
function handleMyOrder(): void {
    $nickname = $_SESSION['nickname'];
    $orders = readOrders();
    foreach ($orders as $entry) {
        if ($entry['nickname'] === $nickname) {
            $total = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $entry['items']));
            echo json_encode(['nickname' => $nickname, 'items' => $entry['items'], 'total' => $total]);
            return;
        }
    }
    echo json_encode(['nickname' => $nickname, 'items' => null]);
}
```

- [ ] **Step 2: Replace `handleSaveOrder()`**

```php
function handleSaveOrder(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        return;
    }
    $body = json_decode(file_get_contents('php://input'), true);
    if (!isset($body['items']) || !is_array($body['items'])) {
        http_response_code(400);
        echo json_encode(['error' => 'items array required']);
        return;
    }
    // Validate and sanitize items
    $clean = [];
    foreach ($body['items'] as $item) {
        if (!isset($item['dish'], $item['price'], $item['qty'])) continue;
        $qty = (int)$item['qty'];
        if ($qty < 1 || $qty > 9) continue;
        $clean[] = [
            'dish'  => substr(strip_tags((string)$item['dish']), 0, 128),
            'price' => (int)$item['price'],
            'qty'   => $qty,
        ];
    }
    if (empty($clean)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid items']);
        return;
    }
    $nickname = $_SESSION['nickname'];
    $orders = readOrders();
    $found = false;
    foreach ($orders as &$entry) {
        if ($entry['nickname'] === $nickname) {
            $entry['items'] = $clean;
            $entry['timestamp'] = date('c');
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $orders[] = ['nickname' => $nickname, 'timestamp' => date('c'), 'items' => $clean];
    }
    writeOrders($orders);
    echo json_encode(['ok' => true]);
}
```

- [ ] **Step 3: Replace `handleTodaySummary()`**

```php
function handleTodaySummary(): void {
    $orders = readOrders();
    $aggregate = []; // dish => ['dish'=>..., 'price'=>..., 'qty'=>...]
    foreach ($orders as $entry) {
        foreach ($entry['items'] as $item) {
            $key = $item['dish'];
            if (!isset($aggregate[$key])) {
                $aggregate[$key] = ['dish' => $item['dish'], 'price' => $item['price'], 'qty' => 0];
            }
            $aggregate[$key]['qty'] += $item['qty'];
        }
    }
    $dishes = array_values($aggregate);
    $total = array_sum(array_map(fn($d) => $d['price'] * $d['qty'], $dishes));
    echo json_encode(['dishes' => $dishes, 'total' => $total]);
}
```

- [ ] **Step 4: Verify PHP parses without errors**

```bash
php -l index.php
```

- [ ] **Step 5: Test API endpoints via curl**

With `php -S localhost:8000` running, open a browser, log in, then in terminal:

```bash
# Save an order (copy session cookie from browser devtools as PHPSESSID=xxx)
curl -s -b 'PHPSESSID=YOUR_SESSION_ID' \
  -H 'Content-Type: application/json' \
  -d '{"items":[{"dish":"Butter Chicken","price":2060,"qty":1},{"dish":"Basmati Rice","price":540,"qty":2}]}' \
  'http://localhost:8000/?action=save_order'
# Expected: {"ok":true}

curl -s -b 'PHPSESSID=YOUR_SESSION_ID' 'http://localhost:8000/?action=my_order'
# Expected: {"nickname":"...","items":[...],"total":3140}

curl -s -b 'PHPSESSID=YOUR_SESSION_ID' 'http://localhost:8000/?action=today_summary'
# Expected: {"dishes":[{"dish":"Butter Chicken","price":2060,"qty":1},{"dish":"Basmati Rice","price":540,"qty":2}],"total":3140}

# Test unauthenticated access
curl -s 'http://localhost:8000/?action=my_order'
# Expected: HTTP 401, {"error":"Unauthenticated"}
```

- [ ] **Step 6: Commit**

```bash
git add index.php
git commit -m "feat: implement save_order, my_order, today_summary API handlers"
```

---

### Task 4: Order view UI (menu selection)

**Files:**
- Modify: `index.php` — replace `renderApp()` stub; add `renderOrderView()`, `renderMyOrderView()`, `renderSummaryView()` functions; load `menu.json` server-side

**Interfaces:**
- Consumes: `menu.json` (read with `file_get_contents`), `$_SESSION['nickname']`
- Produces: full single-page app HTML with three tabs (Order, My Order, Summary), embedded CSS and JS

- [ ] **Step 1: Add logout route before the UI routing block**

Insert just before the `if (!isset($_SESSION['authenticated']))` check:

```php
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
```

- [ ] **Step 2: Replace `renderApp()` with the full SPA**

Replace the `renderApp()` stub with the following. The function reads `menu.json` server-side, outputs the full HTML/CSS/JS shell. Tab switching and API calls are handled in JS.

```php
function renderApp(): void {
    $menuJson = file_get_contents(__DIR__ . '/menu.json');
    $menu = json_decode($menuJson, true);
    $nickname = htmlspecialchars($_SESSION['nickname']);
    $menuJs = json_encode($menu); // safe to embed in <script>
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Alnoor Order</title>
      <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 1rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        header h1 { margin: 0; font-size: 1.3rem; }
        nav { display: flex; gap: .5rem; margin-bottom: 1.5rem; }
        nav button { flex: 1; padding: .6rem; cursor: pointer; border: 2px solid #ccc; background: #fff; border-radius: 4px; font-size: .95rem; }
        nav button.active { border-color: #b03; background: #b03; color: #fff; }
        .view { display: none; }
        .view.active { display: block; }
        .category { margin-bottom: 1.5rem; }
        .category h2 { font-size: 1rem; border-bottom: 1px solid #eee; padding-bottom: .3rem; margin-bottom: .5rem; }
        .dish-row { display: flex; align-items: center; gap: .5rem; padding: .3rem 0; }
        .dish-name { flex: 1; }
        .dish-price { color: #555; min-width: 60px; text-align: right; }
        .qty-ctrl { display: flex; align-items: center; gap: .3rem; }
        .qty-ctrl button { width: 28px; height: 28px; cursor: pointer; font-size: 1rem; border: 1px solid #ccc; background: #f5f5f5; border-radius: 3px; }
        .qty-ctrl span { min-width: 20px; text-align: center; }
        .order-footer { position: sticky; bottom: 0; background: #fff; border-top: 2px solid #eee; padding: 1rem 0; display: flex; justify-content: space-between; align-items: center; }
        .order-footer strong { font-size: 1.1rem; }
        #submitBtn { padding: .6rem 1.5rem; font-size: 1rem; cursor: pointer; background: #b03; color: #fff; border: none; border-radius: 4px; }
        #submitBtn:disabled { background: #ccc; cursor: default; }
        #submitMsg { color: green; margin-top: .5rem; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { text-align: left; padding: .4rem .5rem; border-bottom: 1px solid #eee; }
        table th { background: #f8f8f8; }
        .total-row td { font-weight: bold; border-top: 2px solid #ccc; }
        .empty-msg { color: #888; margin: 1rem 0; }
        a.logout { font-size: .85rem; color: #666; text-decoration: none; }
        a.logout:hover { text-decoration: underline; }
        #summaryDate { color: #888; font-size: .85rem; margin-bottom: 1rem; }
      </style>
    </head>
    <body>
      <header>
        <h1>Alnoor — Hi, $nickname</h1>
        <a class="logout" href="?logout=1">Logout</a>
      </header>
      <nav>
        <button class="active" onclick="showTab('order')">Order</button>
        <button onclick="showTab('myorder')">My Order</button>
        <button onclick="showTab('summary')">Summary</button>
      </nav>

      <!-- ORDER VIEW -->
      <div id="view-order" class="view active">
        <div id="menu-container"></div>
        <div class="order-footer">
          <strong>Total: <span id="totalPrice">0</span> HUF</strong>
          <button id="submitBtn" onclick="submitOrder()">Submit Order</button>
        </div>
        <div id="submitMsg"></div>
      </div>

      <!-- MY ORDER VIEW -->
      <div id="view-myorder" class="view">
        <div id="myorder-container"></div>
      </div>

      <!-- SUMMARY VIEW -->
      <div id="view-summary" class="view">
        <div id="summaryDate"></div>
        <div id="summary-container"></div>
      </div>

      <script>
      const MENU = $menuJs;
      // qty map: dish name -> qty
      const qty = {};

      function showTab(name) {
        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
        document.querySelectorAll('nav button').forEach(b => b.classList.remove('active'));
        document.getElementById('view-' + name).classList.add('active');
        event.target.classList.add('active');
        if (name === 'myorder') loadMyOrder();
        if (name === 'summary') loadSummary();
      }

      function buildMenu() {
        const container = document.getElementById('menu-container');
        container.innerHTML = '';
        for (const [cat, dishes] of Object.entries(MENU)) {
          const sec = document.createElement('div');
          sec.className = 'category';
          sec.innerHTML = '<h2>' + cat + '</h2>';
          for (const [dish, price] of Object.entries(dishes)) {
            if (!qty[dish]) qty[dish] = 0;
            const row = document.createElement('div');
            row.className = 'dish-row';
            row.innerHTML = `
              <span class="dish-name">${dish}</span>
              <span class="dish-price">${price} HUF</span>
              <div class="qty-ctrl">
                <button onclick="changeQty('${esc(dish)}', -1)">−</button>
                <span id="qty-${esc(dish)}">${qty[dish]}</span>
                <button onclick="changeQty('${esc(dish)}', 1)">+</button>
              </div>`;
            sec.appendChild(row);
          }
          container.appendChild(sec);
        }
        updateTotal();
      }

      function esc(s) { return s.replace(/'/g, "\\'"); }

      function changeQty(dish, delta) {
        qty[dish] = Math.max(0, Math.min(9, (qty[dish] || 0) + delta));
        const el = document.getElementById('qty-' + dish);
        if (el) el.textContent = qty[dish];
        updateTotal();
      }

      function updateTotal() {
        let total = 0;
        for (const [cat, dishes] of Object.entries(MENU)) {
          for (const [dish, price] of Object.entries(dishes)) {
            total += (qty[dish] || 0) * price;
          }
        }
        document.getElementById('totalPrice').textContent = total.toLocaleString('hu-HU');
      }

      async function loadExistingOrder() {
        const res = await fetch('?action=my_order');
        const data = await res.json();
        if (data.items) {
          data.items.forEach(item => { qty[item.dish] = item.qty; });
          // Re-render qty displays
          for (const dish of Object.keys(qty)) {
            const el = document.getElementById('qty-' + dish);
            if (el) el.textContent = qty[dish];
          }
          updateTotal();
        }
      }

      async function submitOrder() {
        const btn = document.getElementById('submitBtn');
        const msg = document.getElementById('submitMsg');
        const items = [];
        for (const [cat, dishes] of Object.entries(MENU)) {
          for (const [dish, price] of Object.entries(dishes)) {
            if ((qty[dish] || 0) > 0) {
              items.push({ dish, price, qty: qty[dish] });
            }
          }
        }
        if (items.length === 0) { msg.textContent = 'Add at least one dish.'; msg.style.color='red'; return; }
        btn.disabled = true;
        msg.textContent = '';
        const res = await fetch('?action=save_order', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ items })
        });
        const data = await res.json();
        btn.disabled = false;
        if (data.ok) {
          msg.style.color = 'green';
          msg.textContent = 'Order saved!';
        } else {
          msg.style.color = 'red';
          msg.textContent = data.error || 'Error saving order.';
        }
      }

      async function loadMyOrder() {
        const container = document.getElementById('myorder-container');
        container.innerHTML = 'Loading...';
        const res = await fetch('?action=my_order');
        const data = await res.json();
        if (!data.items) {
          container.innerHTML = '<p class="empty-msg">You have no order for today. Go to the Order tab to place one.</p>';
          return;
        }
        let rows = data.items.map(i =>
          `<tr><td>${i.dish}</td><td>${i.qty}</td><td>${(i.price * i.qty).toLocaleString('hu-HU')} HUF</td></tr>`
        ).join('');
        container.innerHTML = `
          <table>
            <thead><tr><th>Dish</th><th>Qty</th><th>Subtotal</th></tr></thead>
            <tbody>${rows}</tbody>
            <tfoot><tr class="total-row"><td colspan="2">Total</td><td>${data.total.toLocaleString('hu-HU')} HUF</td></tr></tfoot>
          </table>`;
      }

      async function loadSummary() {
        const container = document.getElementById('summary-container');
        const dateEl = document.getElementById('summaryDate');
        container.innerHTML = 'Loading...';
        dateEl.textContent = 'Date: ' + new Date().toLocaleDateString('hu-HU');
        const res = await fetch('?action=today_summary');
        const data = await res.json();
        if (!data.dishes || data.dishes.length === 0) {
          container.innerHTML = '<p class="empty-msg">No orders placed yet today.</p>';
          return;
        }
        let rows = data.dishes.map(d =>
          `<tr><td>${d.dish}</td><td>${d.qty}</td><td>${(d.price * d.qty).toLocaleString('hu-HU')} HUF</td></tr>`
        ).join('');
        container.innerHTML = `
          <table>
            <thead><tr><th>Dish</th><th>Qty</th><th>Subtotal</th></tr></thead>
            <tbody>${rows}</tbody>
            <tfoot><tr class="total-row"><td colspan="2">Total</td><td>${data.total.toLocaleString('hu-HU')} HUF</td></tr></tfoot>
          </table>`;
      }

      // Init
      buildMenu();
      loadExistingOrder();
      </script>
    </body>
    </html>
    HTML;
}
```

- [ ] **Step 3: Verify PHP parses without errors**

```bash
php -l index.php
```

- [ ] **Step 4: Manual smoke test — full flow**

```bash
php -S localhost:8000
```

Open `http://localhost:8000` in a browser and verify:
1. Login with correct password + nickname → lands on Order view
2. Menu is grouped by category with +/− quantity steppers
3. Total price updates as quantities change
4. Submit Order → success message appears
5. Switch to "My Order" tab → shows submitted items and total
6. Switch to "Summary" tab → shows aggregated dishes (no names), total
7. Open a second browser tab, log in with a different nickname, place a different order → Summary shows both orders combined
8. Logout link works and returns to login form
9. After login with same nickname → previous order quantities pre-loaded in the form

- [ ] **Step 5: Commit**

```bash
git add index.php
git commit -m "feat: complete SPA UI with order, my-order, and summary views"
```

---

### Task 5: Deployment preparation

**Files:**
- Modify: `index.php` — set real `SHARED_PASSWORD`
- Verify: `orders/` directory exists and is writable

**Interfaces:**
- No new interfaces — this task prepares the app for production deployment

- [ ] **Step 1: Set the real shared password**

Edit the constant at the top of `index.php`:

```php
define('SHARED_PASSWORD', 'your-actual-shared-secret-here');
```

- [ ] **Step 2: Verify `orders/` directory is present**

```bash
ls -la orders/
# Should show .htaccess; directory must be writable by the web server
```

- [ ] **Step 3: Upload to Hostinger via FTP/File Manager**

Upload these files to your public web root (e.g., `public_html/alnoor/`):
- `index.php`
- `menu.json`
- `orders/.htaccess` (upload the directory + file)

Ensure the `orders/` directory has write permission (chmod 755 or 775 depending on Hostinger's setup).

- [ ] **Step 4: Smoke test on production**

Open the hosted URL in a browser. Verify:
1. Login form appears
2. Login works
3. Place an order, check My Order and Summary tabs
4. Direct URL access to `orders/` returns a 403 Forbidden

- [ ] **Step 5: Final commit**

```bash
git add index.php
git commit -m "chore: set production password and deployment notes"
```
