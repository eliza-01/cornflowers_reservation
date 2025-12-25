<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

ini_set('display_errors', ALLOW_BROWSER ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_error_handler(function (int $severity, string $message, string $file, int $line) {
  if (!(error_reporting() & $severity)) return false;
  throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');

  $msg = 'Server error';
  $detail = null;

  if (defined('ALLOW_BROWSER') && ALLOW_BROWSER) {
    $msg = $e->getMessage();
    $detail = ['file' => $e->getFile(), 'line' => $e->getLine()];
  }

  echo json_encode(['ok' => false, 'error' => $msg, 'detail' => $detail], JSON_UNESCAPED_UNICODE);
  exit;
});

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  return $pdo;
}

function json_out(array $data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function require_json_body(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '', true);
  if (!is_array($data)) {
    json_out(['ok' => false, 'error' => 'Invalid JSON body'], 400);
  }
  return $data;
}
