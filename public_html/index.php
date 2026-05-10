<?php require __DIR__ . '/app/init.php'; ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(farhuaad_html_lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include __DIR__ . '/partials/theme_head_script.php'; ?>
  <title><?php echo htmlspecialchars(__('meta.home_title'), ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars(__('meta.home_desc'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(farhuaad_asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>
<body>
  <div class="app">
    <?php include __DIR__ . '/partials/header.php'; ?>

    <main class="main">
      <section class="hero">
        <div class="hero-text">
        <h1><?php echo htmlspecialchars(__('hero.h1_before'), ENT_QUOTES, 'UTF-8'); ?><span class="accent">Farhuaad</span><?php echo htmlspecialchars(__('hero.h1_after'), ENT_QUOTES, 'UTF-8'); ?></h1>
          <p class="hero-subtitle">
            <?php echo htmlspecialchars(__('hero.subtitle'), ENT_QUOTES, 'UTF-8'); ?>
          </p>
          <div class="hero-actions">
            <button class="btn btn-primary" type="button" data-hero-action="start-trading"><?php echo htmlspecialchars(__('hero.start'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button class="btn btn-ghost" type="button" data-hero-action="explore-markets"><?php echo htmlspecialchars(__('hero.explore'), ENT_QUOTES, 'UTF-8'); ?></button>
          </div>
          <div class="hero-metrics">
            <div class="metric">
              <div class="metric-value" id="hero-total-volume">0 A</div>
              <div class="metric-label"><?php echo htmlspecialchars(__('hero.metric_volume'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="metric">
              <div class="metric-value" id="hero-online-users">0</div>
              <div class="metric-label"><?php echo htmlspecialchars(__('hero.metric_online'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="metric">
              <div class="metric-value" id="hero-total-disputes">0</div>
              <div class="metric-label"><?php echo htmlspecialchars(__('hero.metric_disputes'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="metric">
              <div class="metric-value">24/7</div>
              <div class="metric-label"><?php echo htmlspecialchars(__('hero.metric_liquidity'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
          </div>
        </div>
        <div class="hero-image-card" aria-label="<?php echo htmlspecialchars(__('instruction.aria'), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="instruction-carousel" id="instruction-carousel" tabindex="0">
            <figure class="instruction-slide is-active">
              <img
                src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/img/instruction/inst_1.png'), ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars(__('instruction.alt_n', ['n' => '1']), ENT_QUOTES, 'UTF-8'); ?>"
                loading="lazy"
              />
            </figure>
            <figure class="instruction-slide">
              <img
                src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/img/instruction/inst_2.png'), ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars(__('instruction.alt_n', ['n' => '2']), ENT_QUOTES, 'UTF-8'); ?>"
                loading="lazy"
              />
            </figure>
            <figure class="instruction-slide">
              <img
                src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/img/instruction/inst_3.png'), ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars(__('instruction.alt_n', ['n' => '3']), ENT_QUOTES, 'UTF-8'); ?>"
                loading="lazy"
              />
            </figure>

            <button class="instruction-nav instruction-nav-prev" type="button" id="instruction-prev" aria-label="<?php echo htmlspecialchars(__('instruction.prev'), ENT_QUOTES, 'UTF-8'); ?>">‹</button>
            <button class="instruction-nav instruction-nav-next" type="button" id="instruction-next" aria-label="<?php echo htmlspecialchars(__('instruction.next'), ENT_QUOTES, 'UTF-8'); ?>">›</button>
          </div>
          <div class="instruction-dots" id="instruction-dots" aria-label="<?php echo htmlspecialchars(__('instruction.dots'), ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div class="instruction-extra" aria-label="<?php echo htmlspecialchars(__('instruction.extra'), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="instruction-extra-chip"><?php echo htmlspecialchars(__('instruction.chip1'), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="instruction-extra-chip"><?php echo htmlspecialchars(__('instruction.chip2'), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="instruction-extra-chip"><?php echo htmlspecialchars(__('instruction.chip3'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
        </div>
      </section>

      <section class="search-disputes" aria-label="<?php echo htmlspecialchars(__('disputes.title'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="search-disputes-head">
          <h2 class="search-disputes-title"><?php echo htmlspecialchars(__('disputes.title'), ENT_QUOTES, 'UTF-8'); ?></h2>
          <span class="search-disputes-subtitle"><?php echo htmlspecialchars(__('disputes.subtitle'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="search-disputes-scroll" role="region" aria-label="<?php echo htmlspecialchars(__('disputes.title'), ENT_QUOTES, 'UTF-8'); ?>" tabindex="0">
          <div class="search-disputes-list" id="search-disputes-list"></div>
        </div>
      </section>

      <section class="filters">
        <div class="filters-row">
          <label class="filter-label filters-cell filters-cell--search-label" for="market-search"><?php echo htmlspecialchars(__('filters.search'), ENT_QUOTES, 'UTF-8'); ?></label>
          <span class="filter-label filters-cell filters-cell--categories-label"><?php echo htmlspecialchars(__('filters.categories'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="search filters-cell filters-cell--search-controls">
            <input class="input search-input" id="market-search" type="search" placeholder="<?php echo htmlspecialchars(__('search.placeholder'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" />
            <button class="chip chip-outline search-clear" id="market-search-clear" type="button"><?php echo htmlspecialchars(__('search.clear'), ENT_QUOTES, 'UTF-8'); ?></button>
            <span class="search-count" id="market-search-count"></span>
          </div>
          <div class="filter-group filters-cell filters-cell--categories">
            <button type="button" class="chip chip-filled chip-active" data-cat-slug="all"><?php echo htmlspecialchars(__('cat.all'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="chip chip-outline" data-cat-slug="politics"><?php echo htmlspecialchars(__('cat.politics'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="chip chip-outline" data-cat-slug="crypto"><?php echo htmlspecialchars(__('cat.crypto'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="chip chip-outline" data-cat-slug="economy"><?php echo htmlspecialchars(__('cat.economy'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="chip chip-outline" data-cat-slug="sport"><?php echo htmlspecialchars(__('cat.sport'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="chip chip-outline" data-cat-slug="tech"><?php echo htmlspecialchars(__('cat.tech'), ENT_QUOTES, 'UTF-8'); ?></button>
          </div>
          <div class="filters-divider" aria-hidden="true"></div>
          <div class="filter-group filters-cell filters-cell--sort">
            <span class="filter-label"><?php echo htmlspecialchars(__('filters.sort'), ENT_QUOTES, 'UTF-8'); ?></span>
            <button type="button" class="chip chip-outline" data-sort-key="volume"><?php echo htmlspecialchars(__('sort.volume'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="chip chip-filled chip-active" data-sort-key="new"><?php echo htmlspecialchars(__('sort.new'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="chip chip-outline" data-sort-key="closing"><?php echo htmlspecialchars(__('sort.closing'), ENT_QUOTES, 'UTF-8'); ?></button>
          </div>
          <div class="filter-group filters-cell filters-cell--source">
            <span class="filter-label"><?php echo htmlspecialchars(__('filters.source'), ENT_QUOTES, 'UTF-8'); ?></span>
            <button type="button" class="chip chip-filled chip-active" data-creation-source="all"><?php echo htmlspecialchars(__('source.all'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="chip chip-outline" data-creation-source="ai"><?php echo htmlspecialchars(__('source.ai'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="chip chip-outline" data-creation-source="manual"><?php echo htmlspecialchars(__('source.manual'), ENT_QUOTES, 'UTF-8'); ?></button>
          </div>
        </div>
      </section>
      <section class="markets-grid" aria-label="<?php echo htmlspecialchars(__('markets.section_aria'), ENT_QUOTES, 'UTF-8'); ?>">
      </section>
      <div class="empty-state" id="markets-empty" hidden><?php echo htmlspecialchars(__('markets.empty'), ENT_QUOTES, 'UTF-8'); ?></div>

    </main>

    <?php include __DIR__ . '/partials/footer.php'; ?>
  </div>

  <script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/main.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
