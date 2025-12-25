<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$body = require_json_body();
require_admin_from_body($body);

$rows = db()->query('SELECT id, label FROM restaurant_tables ORDER BY id')->fetchAll();
json_out(['ok' => true, 'tables' => $rows]);
