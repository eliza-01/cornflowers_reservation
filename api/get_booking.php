<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$body = require_json_body();
require_admin_from_body($body);

$id = (int)($body['id'] ?? 0);
if ($id <= 0) json_out(['ok' => false, 'error' => 'Invalid id'], 400);

$stmt = db()->prepare(
  'SELECT id, table_id, booking_date, TIME_FORMAT(booking_time, "%H:%i") AS booking_time,
          customer_name, customer_phone, people_count, comment
   FROM bookings WHERE id = :id'
);
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) json_out(['ok' => false, 'error' => 'Not found'], 404);

json_out(['ok' => true, 'booking' => $row]);
