<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$pdo = db();
$ver = $pdo->query('SELECT VERSION() AS v')->fetch();
json_out(['ok' => true, 'mysql_version' => $ver['v'] ?? null]);
