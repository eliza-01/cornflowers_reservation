<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Berlin');

/**
 * Если true — разрешаем работу из обычного браузера (без Telegram initData).
 * Если false — только внутри Telegram Mini App.
 */
const ALLOW_BROWSER = true;

/**
 * Режим доступа в Telegram:
 * - true  => доступ всем пользователям Telegram
 * - false => доступ только ADMIN_USER_IDS
 */
const ALLOW_ALL_TG_USERS = true;

const BOT_TOKEN = '8530986456:AAFtaoQstnapV4yLygGIJ3kKjtyAFo6es-c';
const ADMIN_USER_IDS = [528858271, 5069751888];

// MySQL
// ВАЖНО: чтобы порт работал, используем 127.0.0.1 (TCP), а не localhost (socket)
const DB_HOST = '127.0.0.1';
const DB_PORT = 3327;

const DB_NAME = 'cornflowers_reservation';
const DB_USER = 'cornflowers';
const DB_PASS = '93125888qQ';
const DB_CHARSET = 'utf8mb4';

// Бронь только с 9 до 21 => последний слот 21:00, значит CLOSE_HOUR = 22
const OPEN_HOUR = 9;
const CLOSE_HOUR = 22;

const MAX_INITDATA_AGE_SECONDS = 24 * 60 * 60;

/**
 * Telegram уведомления (в тему/тред).
 */
const TG_NOTIFY_ENABLED = true;
const TG_NOTIFY_CHAT_ID = -1003548283340;
const TG_NOTIFY_THREAD_ID = 3;
