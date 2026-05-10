<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$isEn = farhuaad_lang() === 'en';
$contactEmail = __('footer.contact_email');
$contactMailHref = htmlspecialchars('mailto:' . $contactEmail, ENT_QUOTES, 'UTF-8');
$contactEmailEsc = htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(farhuaad_html_lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include __DIR__ . '/../partials/theme_head_script.php'; ?>
  <title><?php echo htmlspecialchars(__('privacy.meta_title'), ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars(__('privacy.meta_desc'), ENT_QUOTES, 'UTF-8'); ?>" />
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
          <h1 class="panel-title"><?php echo htmlspecialchars(__('footer.privacy'), ENT_QUOTES, 'UTF-8'); ?></h1>
        </div>
        <p class="page-subtitle"><?php echo htmlspecialchars(__('legal.effective'), ENT_QUOTES, 'UTF-8'); ?> <?php echo date('d.m.Y'); ?></p>

        <div class="legal-doc">
          <?php if ($isEn): ?>
          <p>This Privacy Policy describes how Farhuaad (“we”, “us”) collects, uses, and protects information when you use our experimental prediction-market website.</p>

          <h3>1. Data we process</h3>
          <p>We may process: email address and account identifiers; technical data (IP address, browser type, session and security tokens); in-platform activity such as virtual balance, bet history, and settlement of demo markets; public EVM wallet addresses if you connect a wallet; referral data (e.g. who invited whom, a non-secret referral code tied to your account for sharing links); interface language preference; messages you send in in-product chat related to markets, including content processed or stored for moderation and service operation; and, where enabled, verification signals from bot-protection services.</p>

          <h3>2. Purposes</h3>
          <p>We use this information to provide and secure the service, authenticate users, run the prediction-market demo (including payouts and optional referral rewards), prevent abuse (including bot checks on registration when configured), operate chat and dispute-related features, and improve the platform. Some features may use external AI services to assist with content (e.g. market-related text); inputs are sent only as needed for that function. The environment is demo-oriented; no real-money investment advice is provided.</p>

          <h3>3. Legal bases</h3>
          <p>Depending on your jurisdiction, processing may rely on your consent, performance of a contract (terms of use), or our legitimate interests in operating and securing the service, balanced against your rights.</p>

          <h3>4. Retention and security</h3>
          <p>We keep data only as long as needed for these purposes and apply reasonable technical and organizational safeguards. No method of transmission over the Internet is completely secure.</p>

          <h3>5. Sharing</h3>
          <p>We do not sell your personal data. We may use processors who process data on our instructions and only as needed to provide the service, including: email delivery (e.g. one-time codes); Google reCAPTCHA on registration when enabled (see Google’s terms and privacy notice for data they receive when the script runs); and, where configured, AI providers (e.g. Anthropic) for automated or assisted processing of market-related content. Blocking third-party scripts may limit sign-up or certain features.</p>

          <h3>6. Cookies</h3>
          <p>We use cookies and similar technologies for sessions and security, to remember your language choice, and (when you open a referral link) to attribute a signup to the referrer until registration completes. The registration page may load Google reCAPTCHA when enabled, which can cause Google to set its own cookies. See our <a href="<?php echo htmlspecialchars(farhuaad_url('pages/cookies.php'), ENT_QUOTES, 'UTF-8'); ?>">cookie policy</a> for details.</p>

          <h3>7. Your rights</h3>
          <p>Depending on applicable law, you may have rights to access, correct, delete, or restrict processing of your personal data, or to object to certain processing. For privacy requests and questions about this policy, email us at <a href="<?php echo $contactMailHref; ?>"><?php echo $contactEmailEsc; ?></a>.</p>

          <h3>8. Changes</h3>
          <p>We may update this policy; the current version is always posted on this page.</p>

          <h3>9. Contact</h3>
          <p>Operator contact for data-protection questions: <a href="<?php echo $contactMailHref; ?>"><?php echo $contactEmailEsc; ?></a>.</p>
          <?php else: ?>
          <p>Настоящая Политика конфиденциальности и обработки персональных данных (далее — Политика) определяет порядок обработки и защиты персональных данных пользователей сервиса Farhuaad в соответствии с законодательством Российской Федерации.</p>

          <h3>1. Общие положения</h3>
          <p>1.1. Используя сайт, пользователь подтверждает согласие с условиями настоящей Политики.</p>
          <p>1.2. Оператор обрабатывает персональные данные в соответствии с:</p>
          <p>— <a href="http://www.consultant.ru/document/cons_doc_LAW_61801/" target="_blank" rel="noopener noreferrer">Федеральным законом от 27.07.2006 № 152-ФЗ «О персональных данных»</a>;</p>
          <p>— <a href="http://www.consultant.ru/document/cons_doc_LAW_61798/" target="_blank" rel="noopener noreferrer">Федеральным законом от 27.07.2006 № 149-ФЗ «Об информации, информационных технологиях и о защите информации»</a>;</p>
          <p>— иными применимыми нормативными правовыми актами РФ.</p>
          <p>1.3. Оператором персональных данных является администрация сервиса Farhuaad (далее — Оператор).</p>

          <h3>2. Какие данные мы обрабатываем</h3>
          <p>2.1. Регистрационные данные: адрес электронной почты, технические данные аккаунта, идентификатор пользователя.</p>
          <p>2.2. Технические данные: IP-адрес, user-agent, cookie/сессионные идентификаторы, данные о действиях в интерфейсе, предпочтение языка интерфейса.</p>
          <p>2.3. Финансовые и игровые данные внутри платформы: виртуальный баланс, история ставок и операций в сервисе, расчёты по демо-рынкам.</p>
          <p>2.4. Данные кошелька: при подключении EVM-кошелька — публичный адрес кошелька, необходимый для привязки к учётной записи.</p>
          <p>2.5. Реферальная программа: связь «кого пригласил пользователь» (идентификатор пригласившего), публичный реферальный код/ссылка для приглашений; сами по себе не являются персональными данными третьих лиц, но относятся к учётным записям.</p>
          <p>2.6. Сообщения в чатах по спорам и иной пользовательский контент, который вы вводите в сервисе, включая обработку и хранение в целях работы чата, модерации и сопутствующих функций (в т.ч. с привлечением внешних сервисов на основе ИИ, если такая интеграция включена).</p>

          <h3>3. Цели обработки данных</h3>
          <p>3.1. Регистрация, авторизация и поддержка аккаунта пользователя.</p>
          <p>3.2. Обеспечение работы функционала платформы, включая расчеты результатов, статистику, демо-выплаты и опциональные реферальные начисления.</p>
          <p>3.3. Обеспечение безопасности, предотвращение злоупотреблений, фрода, спама и иных нарушений (включая проверки с использованием сервисов защиты от ботов при регистрации, если интеграция включена).</p>
          <p>3.4. Обратная связь с пользователем по вопросам использования сервиса.</p>
          <p>3.5. Работа функций на основе ИИ (например, генерация или обработка текстов, связанных с рынками/спорами), в объёме, необходимом для заявленной функции.</p>

          <h3>4. Правовые основания обработки (ст. 6, 9, 10.1 152-ФЗ)</h3>
          <p>4.1. Согласие субъекта персональных данных на обработку его персональных данных.</p>
          <p>4.2. Необходимость обработки для исполнения договора (пользовательского соглашения), стороной которого является пользователь.</p>
          <p>4.3. Необходимость обработки для осуществления прав и законных интересов Оператора при условии, что не нарушаются права и свободы субъекта персональных данных.</p>

          <h3>5. Хранение, локализация и защита данных</h3>
          <p>5.1. Мы применяем организационные и технические меры для защиты данных от несанкционированного доступа, изменения, раскрытия и уничтожения.</p>
          <p>5.2. Персональные данные граждан РФ подлежат хранению и первичной обработке с соблюдением требований о локализации, установленных законодательством РФ.</p>
          <p>5.3. Данные хранятся не дольше, чем это необходимо для целей обработки, если иное не предусмотрено законодательством РФ.</p>

          <h3>6. Передача данных третьим лицам и трансграничная передача</h3>
          <p>6.1. Мы не продаем персональные данные пользователей.</p>
          <p>6.2. Передача третьим лицам возможна только при наличии законных оснований, либо для работы сервисных компонентов в объеме, необходимом для оказания услуги, в частности: доставка электронной почты (например, одноразовые коды входа); Google reCAPTCHA на странице регистрации при включённой интеграции (к обработчику передаются данные в соответствии с политикой Google при загрузке и работе скрипта); при включённой интеграции — поставщики облачных сервисов на основе ИИ (например, Anthropic) для обработки текстов, связанных с рынками и функциями сервиса. Отключение сторонних сценариев в браузере может ограничить регистрацию или отдельные функции.</p>
          <p>6.3. Трансграничная передача персональных данных осуществляется только при соблюдении требований законодательства РФ.</p>

          <h3>7. Cookie и аналогичные технологии</h3>
          <p>7.1. Сервис использует cookie и сессии для авторизации, безопасности и корректной работы интерфейса, для запоминания выбранного языка, а также (при переходе по реферальной ссылке) для атрибуции регистрации пригласившему до завершения регистрации. На странице регистрации при включённой интеграции может загружаться Google reCAPTCHA, в результате чего могут устанавливаться cookie Google. Подробнее — в <a href="<?php echo htmlspecialchars(farhuaad_url('pages/cookies.php'), ENT_QUOTES, 'UTF-8'); ?>">политике использования cookie</a>.</p>
          <p>7.2. Управление cookie в браузере: <a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener noreferrer">Chrome</a>, <a href="https://support.mozilla.org/ru/kb/udalenie-kukov-dlya-udaleniya-informatsii-kotoruyu-s" target="_blank" rel="noopener noreferrer">Firefox</a>, <a href="https://support.apple.com/ru-ru/guide/safari/sfri11471/mac" target="_blank" rel="noopener noreferrer">Safari</a>, <a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener noreferrer">Edge</a>.</p>

          <h3>8. Права субъекта персональных данных (ст. 14, 15, 21 152-ФЗ)</h3>
          <p>8.1. Пользователь вправе получать сведения об обработке его персональных данных, требовать уточнения, блокирования или уничтожения данных, если данные являются неполными, устаревшими, неточными, незаконно полученными или не являются необходимыми для заявленной цели обработки.</p>
          <p>8.2. Пользователь вправе отозвать согласие на обработку персональных данных и направить Оператору обращение на электронную почту <a href="<?php echo $contactMailHref; ?>"><?php echo $contactEmailEsc; ?></a>.</p>
          <p>8.3. Пользователь вправе обжаловать действия (бездействие) Оператора в уполномоченный орган по защите прав субъектов персональных данных (<a href="https://rkn.gov.ru/" target="_blank" rel="noopener noreferrer">Роскомнадзор</a>) или в судебном порядке.</p>

          <h3>9. Изменение политики</h3>
          <p>9.1. Администрация вправе вносить изменения в настоящую Политику.</p>
          <p>9.2. Актуальная версия Политики всегда размещается на этой странице.</p>

          <h3>10. Контакты оператора</h3>
          <p>По вопросам обработки персональных данных и реализации прав субъекта персональных данных направляйте обращения Оператору на адрес <a href="<?php echo $contactMailHref; ?>"><?php echo $contactEmailEsc; ?></a>.</p>
          <?php endif; ?>
        </div>
      </section>
    </main>
    <?php include __DIR__ . '/../partials/footer.php'; ?>
  </div>
</body>
</html>
