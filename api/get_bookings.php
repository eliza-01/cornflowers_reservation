<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$body = require_json_body();
require_admin_from_body($body);

$date = (string)($body['date'] ?? '');
$tableId = (int)($body['table_id'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_out(['ok' => false, 'error' => 'Invalid date'], 400);
if ($tableId < 1 || $tableId > 27) json_out(['ok' => false, 'error' => 'Invalid table_id'], 400);

$stmt = db()->prepare(
  'SELECT id, table_id, booking_date, TIME_FORMAT(booking_time, "%H:%i") AS booking_time,
          customer_name, customer_phone, people_count, comment
   FROM bookings
   WHERE booking_date = :d AND table_id = :t
   ORDER BY booking_time'
);
$stmt->execute([':d' => $date, ':t' => $tableId]);
$bookings = $stmt->fetchAll();

json_out(['ok' => true, 'bookings' => $bookings]);
