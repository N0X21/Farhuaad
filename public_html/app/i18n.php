<?php
declare(strict_types=1);

/**
 * @var array<string, string>|null $FARHUAAD_MESSAGES
 */
$FARHUAAD_MESSAGES = null;

function farhuaad_init_i18n(): void
{
  global $FARHUAAD_MESSAGES;
  $fromGet = isset($_GET['lang']) ? strtolower(trim((string)$_GET['lang'])) : '';
  if ($fromGet === 'en' || $fromGet === 'ru') {
    $lang = $fromGet;
  } else {
    $fromCookie = isset($_COOKIE['farhuaad_lang']) ? (string)$_COOKIE['farhuaad_lang'] : '';
    $lang = ($fromCookie === 'en') ? 'en' : 'ru';
  }
  $path = dirname(__DIR__) . '/lang/' . $lang . '.php';
  if (!is_file($path)) {
    $lang = 'ru';
    $path = dirname(__DIR__) . '/lang/ru.php';
  }
  /** @var array<string, string> $loaded */
  $loaded = require $path;
  $FARHUAAD_MESSAGES = $loaded;
  if (!defined('FARHUAAD_LANG')) {
    define('FARHUAAD_LANG', $lang);
  }
}

function farhuaad_lang(): string
{
  return defined('FARHUAAD_LANG') ? FARHUAAD_LANG : 'ru';
}

function farhuaad_html_lang(): string
{
  return farhuaad_lang() === 'en' ? 'en' : 'ru';
}

function farhuaad_locale_tag(): string
{
  return farhuaad_lang() === 'en' ? 'en-US' : 'ru-RU';
}

/**
 * @param array<string, string> $vars
 */
function __(string $key, array $vars = []): string
{
  global $FARHUAAD_MESSAGES;
  $msg = ($FARHUAAD_MESSAGES[$key] ?? $key);
  foreach ($vars as $k => $v) {
    $msg = str_replace('{' . $k . '}', (string)$v, $msg);
  }
  return $msg;
}

/**
 * JSON object for window.FARHUAAD_I18N in main.js
 *
 * @return array<string, string>
 */
function farhuaad_i18n_js(): array
{
  $keys = [
    'yes', 'no', 'source', 'created_by', 'pool', 'market_prob', 'liquidity_prefix', 'event_fallback',
    'active_market_aria', 'closes', 'events_count', 'hide', 'show_all', 'yes_pct', 'no_pct',
    'bet_yes', 'bet_no', 'open_market_hint', 'sources_loading', 'no_disputes_query', 'disputes_soon',
    'bet_modal_title', 'bet_modal_aria', 'bet_modal_default_title', 'bet_modal_side', 'bet_tokens_rate', 'token_pack_label', 'token_input_suffix',
    'amount', 'amount_placeholder',
    'stepper_aria', 'step_up_aria', 'step_down_aria', 'cancel', 'place_bet', 'sending', 'enter_valid_amount',
    'bet_accepted', 'bet_accepted_detail', 'prompt_bet_amount', 'err_login_required', 'err_insufficient_balance', 'err_dispute_closed',
    'err_bet_failed', 'invalid_market_id', 'market_not_found', 'description_unavailable', 'total_pool',
    'user_bets', 'closing', 'status_label', 'bet_entry_title', 'bet_entry_sub', 'side_tabs_aria', 'bet_amount_label',
    'quick_amount_aria', 'bet_footer_default', 'sources', 'no_sources', 'wallet_connecting', 'wallet_login_default',
    'wallet_connect_fail', 'enter_amount_gt_zero', 'bet_hint_side', 'slide_aria', 'ai', 'manual',
    'chat_title', 'chat_hint_ttl', 'chat_placeholder', 'chat_send', 'chat_loading', 'chat_empty',
    'chat_err_rate', 'chat_err_cooldown', 'chat_err_hourly', 'chat_err_moderation', 'chat_err_closed', 'chat_err_generic',
    'chat_err_load', 'chat_login_hint',
    'chat_delete', 'chat_delete_confirm', 'chat_delete_fail',
    'chat_err_spam_links',
    'ui_confirm', 'ui_ok',
    'copy_attr_line',
  ];
  $out = [];
  foreach ($keys as $k) {
    $out[$k] = __($k);
  }
  return $out;
}

/** @return array<string, string> */
function farhuaad_category_labels(): array
{
  return [
    'Политика' => __('cat.politics'),
    'Крипто' => __('cat.crypto'),
    'Экономика' => __('cat.economy'),
    'Спорт' => __('cat.sport'),
    'Технологии' => __('cat.tech'),
    'Событие' => __('cat.event'),
  ];
}

function farhuaad_category_label(string $dbValue): string
{
  $map = farhuaad_category_labels();
  return $map[$dbValue] ?? $dbValue;
}

/**
 * Подставляет англ. заголовок/описание из колонок title_en / short_description_en (для EN-локали).
 * Удаляет служебные ключи из результата.
 *
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
function farhuaad_dispute_localize_copy_fields(array $item): array
{
  $out = $item;
  unset($out['title_en'], $out['short_description_en']);
  if (farhuaad_lang() !== 'en') {
    return $out;
  }
  $tEn = trim((string)($item['title_en'] ?? ''));
  $dEn = trim((string)($item['short_description_en'] ?? ''));
  if ($tEn !== '') {
    $out['title'] = function_exists('mb_substr') ? mb_substr($tEn, 0, 255) : substr($tEn, 0, 255);
  }
  if ($dEn !== '') {
    $dmax = defined('FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN') ? FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN : 8000;
    $out['short_description'] = function_exists('mb_substr') ? mb_substr($dEn, 0, $dmax) : substr($dEn, 0, $dmax);
  }
  return $out;
}

/**
 * Локализация полей спора для ответа API и карточек: текст + подпись категории для текущего языка.
 *
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
function farhuaad_dispute_localize_public_fields(array $item): array
{
  $out = farhuaad_dispute_localize_copy_fields($item);
  $catRaw = trim((string)($item['category'] ?? ''));
  if ($catRaw !== '') {
    $out['category'] = farhuaad_category_label($catRaw);
  }
  return $out;
}

function farhuaad_lang_switch_url(string $lang): string
{
  return farhuaad_url('pages/set_lang.php') . '?lang=' . rawurlencode($lang);
}
