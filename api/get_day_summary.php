<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$body = require_json_body();
require_admin_from_body($body);

$date = (string)($body['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  json_out(['ok' => false, 'error' => 'Invalid date'], 400);
}

$counts = [];
for ($i = 1; $i <= 27; $i++) $counts[(string)$i] = 0;

$stmt = db()->prepare(
  'SELECT table_id, COUNT(*) AS cnt
   FROM bookings
   WHERE booking_date = :d
   GROUP BY table_id'
);
$stmt->execute([':d' => $date]);
$rows = $stmt->fetchAll();

foreach ($rows as $r) {
  $tid = (int)$r['table_id'];
  $cnt = (int)$r['cnt'];
  if ($tid >= 1 && $tid <= 27) $counts[(string)$tid] = $cnt;
}

json_out(['ok' => true, 'counts' => $counts]);
