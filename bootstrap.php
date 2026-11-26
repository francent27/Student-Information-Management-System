<?php

declare(strict_types=1);

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Manila');

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_NAME') ?: 'sims';
        $password = getenv('DB_PASSWORD');
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
            getenv('DB_USER') ?: 'sims',
            $password === false ? 'local-sims-password' : $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
        $clock = new DateTimeImmutable();
        $pdo->prepare('SET time_zone = ?')->execute([$clock->format('P')]);
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page = 'dashboard', array $params = []): string
{
    return '?' . http_build_query(['page' => $page] + $params);
}

function redirect(string $page, array $params = []): never
{
    header('Location: ' . url($page, $params), true, 303);
    exit;
}

function input(array $source, string $key, string $fallback = ''): string
{
    return isset($source[$key]) && is_string($source[$key]) ? trim($source[$key]) : $fallback;
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = [$message, $type];
}

function user(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    return query('SELECT id, name, email, role FROM users WHERE id = ?', [$_SESSION['uid']])->fetch() ?: null;
}

function can_manage(array $user): bool
{
    return $user['role'] === 'admin';
}

function valid_date(string $value, string $format = 'Y-m-d'): bool
{
    if (str_contains($value, "\0")) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
    return $date && $date->format($format) === $value
        && (int) $date->format('Y') >= 1000;
}

function formatted_date(?string $value, string $format = 'M j, Y'): string
{
    return $value ? (new DateTimeImmutable($value))->format($format) : '—';
}

function amount(mixed $value): string
{
    $currency = getenv('APP_CURRENCY') ?: 'PHP';
    return ($currency === 'PHP' ? '₱' : $currency . ' ') . number_format((float) $value, 2);
}

function initials(string $name): string
{
    return implode('', array_map(
        static fn($word) => ucfirst(preg_replace('/^(.).*/us', '$1', $word) ?? ''),
        array_slice(preg_split('/\s+/u', trim($name)) ?: [], 0, 2)
    ));
}
