<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * digits only
 */
function tg_digits_only(string $s): string
{
  $d = preg_replace('/\D+/', '', $s);
  return is_string($d) ? $d : '';
}

/**
 * формат: 8 (029) 111-11-11  (11 цифр: 80291111111)
 */
function tg_format_phone(string $raw): string
{
  $d = tg_digits_only($raw);

  if (strlen($d) === 11 && $d[0] === '8') {
    $a = substr($d, 1, 3); // 029
    $b = substr($d, 4, 3); // 111
    $c = substr($d, 7, 2); // 11
    $e = substr($d, 9, 2); // 11
    return '8 (' . $a . ') ' . $b . '-' . $c . '-' . $e;
  }

  return $d !== '' ? $d : $raw;
}

function tg_html(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * YYYY-MM-DD -> dd.mm
 */
function tg_format_date_ddmm_dot(string $isoDate): string
{
  $isoDate = trim($isoDate);

  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $isoDate)) {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $isoDate);
    if ($dt instanceof DateTimeImmutable) {
      return $dt->format('d.m');
    }
  }

  return $isoDate !== '' ? $isoDate : '—';
}

function tg_title_with_emoji(string $title): string
{
  $t = trim($title);

  if ($t === 'Новая бронь') return '✅ Новая бронь';
  if ($t === 'Бронь изменена') return '⚠️ Бронь изменена';
  if ($t === 'Бронь удалена') return '❌ Бронь удалена';

  return $t !== '' ? $t : '—';
}

/**
 * Отправка сообщения в Telegram (в тему/тред).
 * Ошибки НЕ должны ломать API — поэтому наружу не бросаем исключения.
 */
function tg_send_topic_message(string $text): bool
{
  if (!defined('TG_NOTIFY_ENABLED') || !TG_NOTIFY_ENABLED) return true;

  $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';

  $payload = [
    'chat_id' => TG_NOTIFY_CHAT_ID,
    'message_thread_id' => TG_NOTIFY_THREAD_ID,
    'text' => $text,
    'parse_mode' => 'HTML',
    'disable_web_page_preview' => true,
  ];

  try {
    // curl предпочтительнее
    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
      ]);

      $resp = curl_exec($ch);
      $err  = curl_error($ch);
      $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($resp === false || $err) return false;
      if ($code < 200 || $code >= 300) return false;

      $data = json_decode((string)$resp, true);
      return is_array($data) && !empty($data['ok']);
    }

    // fallback на file_get_contents
    $opts = [
      'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($payload),
        'timeout' => 6,
      ],
    ];
    $ctx = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return false;

    $data = json_decode((string)$resp, true);
    return is_array($data) && !empty($data['ok']);
  } catch (Throwable $e) {
    return false;
  }
}

/**
 * Красивый текст для уведомления.
 * $b ожидает ключи: id, table_id, booking_date, booking_time, customer_name, customer_phone, people_count, comment
 */
function tg_build_booking_text(string $title, array $b): string
{
  $dateIso = (string)($b['booking_date'] ?? '');
  $date = tg_format_date_ddmm_dot($dateIso);

  $time = trim((string)($b['booking_time'] ?? ''));
  $time = $time !== '' ? $time : '—';

  $name = trim((string)($b['customer_name'] ?? ''));
  $name = $name !== '' ? $name : '—';

  $peopleInt = isset($b['people_count']) ? (int)$b['people_count'] : 0;
  $people = ($peopleInt > 0 ? (string)$peopleInt : '—') . ' чел.';

  $comment = (string)($b['comment'] ?? '');
  $comment = trim($comment);
  $comment = $comment !== '' ? $comment : '—';

  $fullTitle = tg_title_with_emoji($title);

  $lines = [];
  $lines[] = '<b>' . tg_html($fullTitle) . '</b>';
  $lines[] = '📅 <b>' . tg_html($date) . '</b>';
  $lines[] = '🕞 <b>' . tg_html($time) . '</b>';
  $lines[] = '👋🏼 <b>' . tg_html($name) . '</b>';
  $lines[] = '👥 <b>' . tg_html($people) . '</b>';
  $lines[] = '📝 <b>' . tg_html($comment) . '</b>';

  return implode("\n", $lines);
}

/**
 * Уведомление по брони (не ломает API, если Telegram недоступен).
 */
function tg_notify_booking(string $title, array $booking): void
{
  $text = tg_build_booking_text($title, $booking);
  tg_send_topic_message($text);
}
