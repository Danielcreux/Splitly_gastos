<?php
$navItems = [
    ['dashboard', 'grid', 'Resumen'], ['groups', 'users', 'Mis grupos'],
    ['expenses', 'receipt', 'Gastos'], ['balances', 'wallet', 'Saldos'],
    ['activity', 'activity', 'Actividad'], ['stats', 'chart', 'Estadísticas'],
    ['settings', 'settings', 'Configuración'],
];
?>
<aside class="sidebar" id="sidebar">
  <div class="brand"><span class="brand-mark">S</span><span>Splitly</span></div>
  <nav class="main-nav" aria-label="Navegación principal">
    <?php foreach ($navItems as [$target, $icon, $label]): ?>
      <button class="nav-item <?= $target === 'dashboard' ? 'active' : '' ?>" data-view="<?= $target ?>">
        <svg><use href="#i-<?= $icon ?>"/></svg><span><?= $label ?></span>
      </button>
    <?php endforeach; ?>
  </nav>
  <div class="user-card">
    <div class="avatar avatar-photo"><?= e(initials($currentUser['first_name'] ?? 'Usuario')) ?></div>
    <div class="user-copy"><strong><?= e($currentUser['first_name'] ?? 'Usuario') ?></strong><small><?= e($currentUser['email'] ?? '') ?></small></div>
    <button class="icon-button logout" title="Cerrar sesión"><svg><use href="#i-log-out"/></svg></button>
  </div>
</aside>
