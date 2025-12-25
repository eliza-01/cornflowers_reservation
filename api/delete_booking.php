<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/telegram.php';

$body = require_json_body();
require_admin_from_body($body);

$id = (int)($body['id'] ?? 0);
if ($id <= 0) json_out(['ok' => false, 'error' => 'Invalid id'], 400);

// сначала получаем данные (чтобы отправить в Telegram)
$stmt = db()->prepare(
  'SELECT id, table_id, booking_date, TIME_FORMAT(booking_time, "%H:%i") AS booking_time,
          customer_name, customer_phone, people_count, comment
   FROM bookings WHERE id = :id'
);
$stmt->execute([':id' => $id]);
$b = $stmt->fetch();

$del = db()->prepare('DELETE FROM bookings WHERE id = :id');
$del->execute([':id' => $id]);
$deleted = (int)$del->rowCount();

if ($deleted > 0 && is_array($b)) {
  // уведомление в Telegram (не ломает API, если Telegram недоступен)
  tg_notify_booking('Бронь удалена', $b);
}

json_out(['ok' => true, 'deleted' => $deleted]);
