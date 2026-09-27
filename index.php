<?php require __DIR__ . '/config/bootstrap.php'; ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#075a45">
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
  <title>Splitly · Gastos compartidos</title>
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/app.css') ?>">
</head>
<body>
  <?php require __DIR__ . '/includes/icons.php'; ?>
  <div class="app-shell">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <main class="main-content">
      <?php require __DIR__ . '/includes/header.php'; ?>
      <div class="view-stack">
        <?php require __DIR__ . '/views/dashboard.php'; ?>
        <?php require __DIR__ . '/views/groups.php'; ?>
        <?php require __DIR__ . '/views/expenses.php'; ?>
        <?php require __DIR__ . '/views/balances.php'; ?>
        <?php require __DIR__ . '/views/activity.php'; ?>
        <?php require __DIR__ . '/views/stats.php'; ?>
        <?php require __DIR__ . '/views/settings.php'; ?>
      </div>
    </main>
  </div>
  <?php require __DIR__ . '/includes/modal.php'; ?>
  <div class="toast" id="toast" role="status"><svg><use href="#i-check"/></svg><span>Gasto guardado correctamente</span></div>
  <?php $groupMembers = []; foreach ($groups as $group) { $groupMembers[(string) $group['id']] = $group['member_options'] ?? array_map(fn($name) => ['id' => $currentUserId, 'name' => $name], $group['members']); } ?>
  <script>window.SplitlyData = <?= json_encode(['monthlyEvolution' => $monthlyEvolution, 'databaseConnected' => $databaseConnected, 'currentUserId' => $currentUserId, 'groupMembers' => $groupMembers], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script src="assets/vendor/chartjs/chart.umd.min.js"></script>
  <script src="assets/js/app.js?v=<?= (int) filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
