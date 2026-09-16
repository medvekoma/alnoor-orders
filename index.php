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
