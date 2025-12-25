<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram.php';

$body = require_json_body();
require_admin_from_body($body);

$id = (int)($body['id'] ?? 0);
$time = (string)($body['time'] ?? ''); // "HH:MM"
$name = trim((string)($body['name'] ?? ''));

// телефон: принимаем и "8 (029) 111-11-11", и "80291111111" -> храним цифры
$phoneRaw = (string)($body['phone'] ?? '');
$phone = preg_replace('/\D+/', '', $phoneRaw);
$phone = trim(is_string($phone) ? $phone : '');

$people = (int)($body['people'] ?? 0);
$comment = trim((string)($body['comment'] ?? ''));

if ($id <= 0) json_out(['ok' => false, 'error' => 'Invalid id'], 400);
if (!preg_match('/^\d{2}:\d{2}$/', $time)) json_out(['ok' => false, 'error' => 'Invalid time'], 400);
if ($name === '') json_out(['ok' => false, 'error' => 'Name required'], 400);

if ($phone === '') json_out(['ok' => false, 'error' => 'Phone required'], 400);
// 11 цифр: 80XXXXXXXXX (пример: 80291111111 => 8 (029) 111-11-11)
if (!preg_match('/^80\d{9}$/', $phone)) json_out(['ok' => false, 'error' => 'Invalid phone (example: 8 (029) 111-11-11)'], 400);

if ($people < 1 || $people > 99) json_out(['ok' => false, 'error' => 'Invalid people_count'], 400);

$stmt = db()->prepare('SELECT id, table_id, booking_date, booking_time FROM bookings WHERE id = :id');
$stmt->execute([':id' => $id]);
$cur = $stmt->fetch();
if (!$cur) json_out(['ok' => false, 'error' => 'Not found'], 404);

$date = (string)$cur['booking_date'];
$tableId = (int)$cur['table_id'];

// Запрет редактирования в прошлых датах (по Europe/Berlin)
$today = (new DateTimeImmutable('now'))->format('Y-m-d');
if ($date < $today) json_out(['ok' => false, 'error' => 'Past dates are not allowed'], 400);

[$hh, $mm] = array_map('intval', explode(':', $time));
if ($mm !== 0) json_out(['ok' => false, 'error' => 'Time must be on the hour'], 400);
if ($hh < OPEN_HOUR || $hh >= CLOSE_HOUR) json_out(['ok' => false, 'error' => 'Time outside working hours'], 400);
$timeSql = sprintf('%02d:00:00', $hh);

try {
  $upd = db()->prepare(
    'UPDATE bookings
     SET booking_time = :tm, customer_name = :n, customer_phone = :ph, people_count = :p, comment = :c
     WHERE id = :id'
  );
  $upd->execute([
    ':tm' => $timeSql,
    ':n' => $name,
    ':ph' => $phone,
    ':p' => $people,
    ':c' => ($comment === '' ? null : $comment),
    ':id' => $id,
  ]);
} catch (PDOException $e) {
  if (($e->getCode() ?? '') === '23000') {
    json_out(['ok' => false, 'error' => 'This time is already booked'], 409);
  }
  json_out(['ok' => false, 'error' => 'DB error'], 500);
}

// уведомление в Telegram (не ломает API, если Telegram недоступен)
tg_notify_booking('Бронь изменена', [
  'id' => $id,
  'table_id' => $tableId,
  'booking_date' => $date,
  'booking_time' => $time,
  'customer_name' => $name,
  'customer_phone' => $phone,
  'people_count' => $people,
  'comment' => ($comment === '' ? null : $comment),
]);

json_out(['ok' => true]);
