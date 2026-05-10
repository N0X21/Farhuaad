<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$isEn = farhuaad_lang() === 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(farhuaad_html_lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include __DIR__ . '/../partials/theme_head_script.php'; ?>
  <title><?php echo htmlspecialchars(__('cookies.meta_title'), ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars(__('cookies.meta_desc'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(farhuaad_asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>
<body>
  <div class="app">
    <?php include __DIR__ . '/../partials/header.php'; ?>
    <main class="main">
      <section class="panel legal-page">
        <div class="panel-top">
          <h1 class="panel-title"><?php echo htmlspecialchars(__('footer.cookies'), ENT_QUOTES, 'UTF-8'); ?></h1>
        </div>
        <p class="page-subtitle"><?php echo htmlspecialchars(__('legal.effective'), ENT_QUOTES, 'UTF-8'); ?> <?php echo date('d.m.Y'); ?></p>

        <div class="legal-doc">
          <?php if ($isEn): ?>
          <p>This document explains how Farhuaad uses cookies and similar technologies. For personal data in general, see our <a href="<?php echo htmlspecialchars(farhuaad_url('pages/privacy.php'), ENT_QUOTES, 'UTF-8'); ?>">privacy policy</a>.</p>

          <h3>1. What are cookies</h3>
          <p>Cookies are small text files stored in your browser. They help keep you signed in, protect against abuse, and make the site work reliably.</p>

          <h3>2. Cookies we use</h3>
          <p><strong>Strictly necessary.</strong> PHP session identifiers and related security settings (e.g. SameSite, Secure). Without them, login and core features will not work.</p>
          <p><strong>Preferences.</strong> <code>farhuaad_lang</code> — stores your interface language (Russian or English), typically for up to one year. Not HttpOnly so the site can read it consistently across pages.</p>
          <p><strong>Referral attribution.</strong> <code>farhuaad_ref</code> — stores a short token from a referral link so the first valid referral can be applied when you register; HttpOnly, typically about 90 days, cleared after successful signup. If you delete it before registering, referral attribution may be lost.</p>
          <p><strong>Third-party (bot protection).</strong> On registration, when keys are configured, Google reCAPTCHA may load scripts from Google and set Google cookies; see <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Google’s privacy policy</a>. We do not use third-party advertising cookies as described here; if analytics or ads are added later, this policy will be updated.</p>

          <h3>3. Duration</h3>
          <p>Session lifetime depends on server configuration and your browser. Preference and referral cookies use fixed maximum lifetimes as described above. You can delete cookies at any time in browser settings.</p>

          <h3>4. How to manage cookies</h3>
          <p>Official guides: <a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener noreferrer">Chrome</a>, <a href="https://support.mozilla.org/en-US/kb/clear-cookies-and-site-data-firefox" target="_blank" rel="noopener noreferrer">Firefox</a>, <a href="https://support.apple.com/guide/safari/sfri11471/mac" target="_blank" rel="noopener noreferrer">Safari</a>, <a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener noreferrer">Edge</a>.</p>
          <p>Blocking cookies may prevent sign-in or break some features.</p>

          <h3>5. Contact</h3>
          <p>Questions about cookies or privacy: use the contacts referenced in our <a href="<?php echo htmlspecialchars(farhuaad_url('pages/privacy.php'), ENT_QUOTES, 'UTF-8'); ?>">privacy policy</a>.</p>
          <?php else: ?>
          <p>Настоящий документ описывает, какие файлы cookie и аналогичные технологии используются на сайте Farhuaad и как вы можете ими управлять. Подробнее об обработке персональных данных см. <a href="<?php echo htmlspecialchars(farhuaad_url('pages/privacy.php'), ENT_QUOTES, 'UTF-8'); ?>">политику конфиденциальности</a>.</p>

          <h3>1. Что такое cookie</h3>
          <p>Cookie — небольшие текстовые файлы, которые сайт сохраняет в браузере пользователя. Они помогают сохранить вход в аккаунт, настройки и защиту от злоупотреблений.</p>

          <h3>2. Какие cookie мы используем</h3>
          <p><strong>Необходимые (технические).</strong> Сессия PHP (идентификатор сессии), cookie параметров сессии (например, SameSite, secure). Без них невозможны вход в аккаунт и безопасная работа сайта.</p>
          <p><strong>Настройки интерфейса.</strong> <code>farhuaad_lang</code> — выбранный язык сайта (русский или английский), обычно до одного года. Не помечена как HttpOnly, чтобы интерфейс мог согласованно учитывать язык.</p>
          <p><strong>Реферальная атрибуция.</strong> <code>farhuaad_ref</code> — короткий токен из реферальной ссылки, чтобы при регистрации учесть первого действительного пригласившего; HttpOnly, обычно до 90 дней, очищается после успешной регистрации. Удаление cookie до завершения регистрации может привести к потере учёта реферала.</p>
          <p><strong>Сторонние (защита от ботов).</strong> На странице регистрации при настроенных ключах может подключаться Google reCAPTCHA; Google может устанавливать свои cookie — см. <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">политику конфиденциальности Google</a>. Рекламные cookie сторонних сетей в рамках данного документа не используются; при появлении аналитики или рекламы политика будет обновлена.</p>

          <h3>3. Срок хранения</h3>
          <p>Срок жизни сессионных cookie определяется настройками сервера и вашим браузером. Cookie языка и реферала имеют максимальный срок, указанный выше. Вы можете удалить cookie в любой момент через настройки браузера.</p>

          <h3>4. Как отключить или удалить cookie</h3>
          <p>Инструкции от разработчиков браузеров (официальные справки):</p>
          <ul>
            <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener noreferrer">Google Chrome</a></li>
            <li><a href="https://support.mozilla.org/ru/kb/udalenie-kukov-dlya-udaleniya-informatsii-kotoruyu-s" target="_blank" rel="noopener noreferrer">Mozilla Firefox</a></li>
            <li><a href="https://support.apple.com/ru-ru/guide/safari/sfri11471/mac" target="_blank" rel="noopener noreferrer">Safari (macOS)</a></li>
            <li><a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener noreferrer">Microsoft Edge</a></li>
          </ul>
          <p>Отключение cookie может привести к невозможности войти в аккаунт или к сбоям в работе отдельных функций.</p>

          <h3>5. Законодательство РФ</h3>
          <p>Обработка данных, связанных с использованием сайта, также регулируется <a href="http://www.consultant.ru/document/cons_doc_LAW_61801/" target="_blank" rel="noopener noreferrer">Федеральным законом № 152-ФЗ «О персональных данных»</a> и <a href="http://www.consultant.ru/document/cons_doc_LAW_61798/" target="_blank" rel="noopener noreferrer">Федеральным законом № 149-ФЗ «Об информации, информационных технологиях и о защите информации»</a> (тексты на справочно-правовой системе КонсультантПлюс).</p>

          <h3>6. Контакты</h3>
          <p>Вопросы по cookie и персональным данным — через контакты, указанные в <a href="<?php echo htmlspecialchars(farhuaad_url('pages/privacy.php'), ENT_QUOTES, 'UTF-8'); ?>">политике конфиденциальности</a>.</p>
          <?php endif; ?>
        </div>
      </section>
    </main>
    <?php include __DIR__ . '/../partials/footer.php'; ?>
  </div>
</body>
</html>
