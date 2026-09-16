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
function renderApp(): void        { echo '<h1>App</h1>'; }
