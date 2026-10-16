<?php
declare(strict_types=1);

function cfg(string $key, $default = null)
{
    $v = $GLOBALS['CFG'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) return $default;
        $v = $v[$k];
    }
    return $v;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', cfg('db.host'), cfg('db.name'));
        $pdo = new PDO($dsn, cfg('db.user'), cfg('db.pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function e($s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string)cfg('base_url'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Session expired. Go back, refresh the page and try again.');
    }
}

/* ---------- Flash ---------- */
function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $msg];
}
function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Settings ---------- */
function setting(string $k, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT k, v FROM settings')->fetchAll() as $r) $cache[$r['k']] = $r['v'];
    }
    return $cache[$k] ?? $default;
}
function set_setting(string $k, string $v): void
{
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
}

/* ---------- Auth ---------- */
function current_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $u = q('SELECT id, name, email, role FROM users WHERE id = ?', [$_SESSION['uid']])->fetch() ?: null;
        }
    }
    return $u;
}
function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}
function require_login(): array
{
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}
function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('Main admins only.');
    }
    return $u;
}
/** Activity IDs the current user may see. Admins: all. */
function allowed_activity_ids(): array
{
    $u = current_user();
    if (!$u) return [];
    if ($u['role'] === 'admin') {
        return array_map('intval', q('SELECT id FROM activities')->fetchAll(PDO::FETCH_COLUMN));
    }
    return array_map('intval', q('SELECT activity_id FROM user_activity WHERE user_id = ?', [$u['id']])->fetchAll(PDO::FETCH_COLUMN));
}
function can_access_activity(int $activityId): bool
{
    return in_array($activityId, allowed_activity_ids(), true);
}
function require_activity(int $activityId): void
{
    if (!can_access_activity($activityId)) {
        http_response_code(403);
        exit('You do not have access to this activity.');
    }
}

/* ---------- Domain helpers ---------- */
function activities(bool $onlyOpen = false): array
{
    $sql = 'SELECT a.*,
              (SELECT COUNT(*) FROM signups s WHERE s.activity_id = a.id AND s.status = \'accepted\') AS accepted_count
            FROM activities a' . ($onlyOpen ? ' WHERE a.is_open = 1' : '') . ' ORDER BY a.sort_order, a.id';
    return q($sql)->fetchAll();
}
function activity(int $id): ?array
{
    $r = q('SELECT a.*, (SELECT COUNT(*) FROM signups s WHERE s.activity_id = a.id AND s.status = \'accepted\') AS accepted_count
            FROM activities a WHERE a.id = ?', [$id])->fetch();
    return $r ?: null;
}
function has_room(array $a): bool
{
    return (int)$a['capacity'] === 0 || (int)$a['accepted_count'] < (int)$a['capacity'];
}
function capacity_label(array $a): string
{
    return (int)$a['capacity'] === 0
        ? $a['accepted_count'] . ' / ∞'
        : $a['accepted_count'] . ' / ' . $a['capacity'];
}
function status_link(array $signup): string
{
    return url('status.php?t=' . $signup['token']);
}
function full_name(array $s): string
{
    return trim($s['first_name'] . ' ' . $s['last_name']);
}
