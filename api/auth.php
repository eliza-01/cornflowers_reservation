<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Проверка Telegram WebApp initData.
 */
function tg_validate_init_data(string $initData): array {
  parse_str($initData, $params);

  if (!isset($params['hash'])) {
    return ['ok' => false, 'error' => 'initData has no hash'];
  }
  $hash = (string)$params['hash'];
  unset($params['hash']);

  // Проверка возраста
  if (isset($params['auth_date'])) {
    $authDate = (int)$params['auth_date'];
    if ($authDate > 0 && (time() - $authDate) > MAX_INITDATA_AGE_SECONDS) {
      return ['ok' => false, 'error' => 'initData expired'];
    }
  }

  ksort($params);
  $pairs = [];
  foreach ($params as $k => $v) {
    $pairs[] = $k . '=' . $v;
  }
  $dataCheckString = implode("\n", $pairs);

  $secretKey = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);
  $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

  if (!hash_equals($calculatedHash, $hash)) {
    return ['ok' => false, 'error' => 'initData hash mismatch'];
  }

  $user = null;
  if (isset($params['user'])) {
    $user = json_decode((string)$params['user'], true);
  }

  return ['ok' => true, 'user' => $user, 'params' => $params];
}

/**
 * Защита API:
 * - Если initData пустой:
 *   - ALLOW_BROWSER=true => пропускаем (браузерный режим)
 *   - иначе 401
 * - Если initData есть — проверяем Telegram и (если надо) список админов.
 */
function require_admin_from_body(array $body): array {
  $initData = (string)($body['initData'] ?? '');

  // Открыли не внутри Telegram Mini App
  if ($initData === '') {
    if (!ALLOW_BROWSER) {
      json_out(['ok' => false, 'error' => 'initData required'], 401);
    }
    return ['user_id' => 0, 'user' => ['id' => 0, 'username' => 'browser']];
  }

  $res = tg_validate_init_data($initData);
  if (!$res['ok']) json_out(['ok' => false, 'error' => $res['error']], 401);

  $user = $res['user'];
  $uid = is_array($user) && isset($user['id']) ? (int)$user['id'] : 0;
  if ($uid <= 0) json_out(['ok' => false, 'error' => 'No user in initData'], 401);

  if (!ALLOW_ALL_TG_USERS) {
    if (!in_array($uid, ADMIN_USER_IDS, true)) {
      json_out(['ok' => false, 'error' => 'Access denied'], 403);
    }
  }

  return ['user_id' => $uid, 'user' => $user];
}
