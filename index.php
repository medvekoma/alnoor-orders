<?php
require_once __DIR__ . '/config.php';
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

// Logout route
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
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
    $decoded = json_decode($data, true);
    return is_array($decoded) ? $decoded : [];
}

function writeOrders(array $orders): void {
    $file = todayFile();
    $fp = fopen($file, 'c');
    if ($fp === false) {
        throw new RuntimeException('Could not open order file for writing');
    }
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($orders, JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function sanitizeNickname(string $nick): string {
    return substr(strip_tags(trim($nick)), 0, 32);
}

// --- API Handlers ---
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
    $nickname = $_SESSION['nickname'];
    $orders = readOrders();
    if (empty($clean)) {
        // Remove the user's order entry
        $orders = array_values(array_filter($orders, fn($e) => $e['nickname'] !== $nickname));
    } else {
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
    }
    try {
        writeOrders($orders);
    } catch (RuntimeException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save order. Please try again.']);
        return;
    }
    echo json_encode(['ok' => true]);
}

function handleTodaySummary(): void {
    $orders = readOrders();
    $aggregate = [];
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
    $users = array_map(fn($e) => [
        'nickname' => $e['nickname'],
        'items'    => $e['items'],
        'total'    => array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $e['items'])),
    ], $orders);
    echo json_encode(['dishes' => $dishes, 'total' => $total, 'users' => $users]);
}
function renderLogin(string $error = ''): void {
    $errorHtml = $error ? '<p class="error">' . htmlspecialchars($error) . '</p>' : '';
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Al Noor — Login</title>
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
      <h1>Al Noor Order</h1>
      <form method="post">
        <label>Nickname (make sure it's unique)
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
function renderApp(): void {
    $menuJson = file_get_contents(__DIR__ . '/menu.json');
    if ($menuJson === false) {
        echo '<h1>Menu unavailable. Please contact the administrator.</h1>';
        return;
    }
    $menu = json_decode($menuJson, true);
    $nickname = htmlspecialchars($_SESSION['nickname']);
    $menuJs = json_encode($menu); // safe to embed in <script>
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Al Noor Order</title>
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
        .category { margin-bottom: 1.5rem; border-radius: 8px; overflow: hidden; }
        .category h2 { font-size: 1.05rem; font-weight: bold; padding: .5rem .75rem; margin: 0 0 .25rem 0; background: #b03; color: #fff; letter-spacing: .03em; }
        .dish-row { padding: .35rem .75rem; }
        .category:nth-child(2n) h2 { background: #1a6fa8; }
        .category:nth-child(3n) h2 { background: #2a7a3b; }
        .category:nth-child(4n) h2 { background: #7a3a9a; }
        .category:nth-child(5n) h2 { background: #b06020; }
        .dish-row { display: flex; align-items: center; gap: .5rem; padding: .3rem 0; font-size: 1.05rem; }
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
        <h1>Al Noor — Hi, $nickname</h1>
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

      function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
      }

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
            const escaped = esc(dish);
            row.innerHTML =
              '<span class="dish-name">' + dish + '</span>' +
              '<span class="dish-price">' + price + ' HUF</span>' +
              '<div class="qty-ctrl">' +
                '<button onclick="changeQty(\'' + escaped + '\', -1)">−</button>' +
                '<span id="qty-' + escaped + '">' + qty[dish] + '</span>' +
                '<button onclick="changeQty(\'' + escaped + '\', 1)">+</button>' +
              '</div>';
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

      async function apiFetch(url, opts) {
        const res = await fetch(url, opts);
        if (res.status === 401) { location.href = location.pathname; return null; }
        return res;
      }

      async function loadExistingOrder() {
        const res = await apiFetch('?action=my_order');
        if (!res) return;
        const data = await res.json();
        if (data.items) {
          data.items.forEach(item => { qty[item.dish] = item.qty; });
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
        btn.disabled = true;
        msg.textContent = '';
        const res = await apiFetch('?action=save_order', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ items })
        });
        if (!res) return;
        const data = await res.json();
        btn.disabled = false;
        if (data.ok) {
          msg.style.color = 'green';
          msg.textContent = items.length === 0 ? 'Order cleared.' : 'Order saved!';
        } else {
          msg.style.color = 'red';
          msg.textContent = data.error || 'Error saving order.';
        }
      }

      async function loadMyOrder() {
        const container = document.getElementById('myorder-container');
        container.innerHTML = 'Loading...';
        const res = await apiFetch('?action=my_order');
        if (!res) return;
        const data = await res.json();
        if (!data.items) {
          container.innerHTML = '<p class="empty-msg">You have no order for today. Go to the Order tab to place one.</p>';
          return;
        }
        let rows = data.items.map(i =>
          '<tr><td>' + escHtml(i.dish) + '</td><td>' + i.qty + '</td><td>' + (i.price * i.qty).toLocaleString('hu-HU') + ' HUF</td></tr>'
        ).join('');
        container.innerHTML =
          '<table>' +
          '<thead><tr><th>Dish</th><th>Qty</th><th>Subtotal</th></tr></thead>' +
          '<tbody>' + rows + '</tbody>' +
          '<tfoot><tr class="total-row"><td colspan="2">Total</td><td>' + data.total.toLocaleString('hu-HU') + ' HUF</td></tr></tfoot>' +
          '</table>';
      }

      async function loadSummary() {
        const container = document.getElementById('summary-container');
        const dateEl = document.getElementById('summaryDate');
        container.innerHTML = 'Loading...';
        dateEl.textContent = 'Date: ' + new Date().toLocaleDateString('hu-HU');
        const res = await apiFetch('?action=today_summary');
        if (!res) return;
        const data = await res.json();
        if (!data.dishes || data.dishes.length === 0) {
          container.innerHTML = '<p class="empty-msg">No orders placed yet today.</p>';
          return;
        }
        const orderText = data.dishes.map(d => d.qty + 'x ' + d.dish).join(String.fromCharCode(10));
        let html =
          '<pre id="order-text" style="background:#1a1a1a;color:#f0f0f0;border:1px solid #333;border-radius:6px;padding:1rem;white-space:pre-wrap;font-family:monospace;margin-bottom:1rem;cursor:pointer" title="Click to copy">' + escHtml(orderText) + '</pre>' +
          '<button onclick="copyOrder()" style="margin-bottom:1.5rem">Copy order</button>';
        for (const user of data.users) {
          const rows = user.items.map(i =>
            '<tr><td>' + escHtml(i.dish) + '</td><td>' + i.qty + '</td><td>' + (i.price * i.qty).toLocaleString('hu-HU') + ' HUF</td></tr>'
          ).join('');
          html +=
            '<h3 style="margin:1.25rem 0 .4rem">' + escHtml(user.nickname) + '</h3>' +
            '<table>' +
            '<thead><tr><th>Dish</th><th>Qty</th><th>Subtotal</th></tr></thead>' +
            '<tbody>' + rows + '</tbody>' +
            '<tfoot><tr class="total-row"><td colspan="2">Total</td><td>' + user.total.toLocaleString('hu-HU') + ' HUF</td></tr></tfoot>' +
            '</table>';
        }
        container.innerHTML = html;
        document.getElementById('order-text').addEventListener('click', copyOrder);
      }

      function copyOrder() {
        const el = document.getElementById('order-text');
        if (!el) return;
        navigator.clipboard.writeText(el.textContent).then(() => {
          const btn = document.querySelector('#view-summary button');
          if (btn) { btn.textContent = 'Copied!'; setTimeout(() => btn.textContent = 'Copy order', 1500); }
        });
      }

      // Init
      buildMenu();
      loadExistingOrder();
      </script>
    </body>
    </html>
    HTML;
}
