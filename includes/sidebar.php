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

<nav class="mobile-tabbar" aria-label="Navegaci&oacute;n m&oacute;vil">
  <button class="mobile-tab active" type="button" data-view="dashboard" aria-label="Resumen">
    <svg><use href="#i-grid"/></svg><span>Inicio</span>
  </button>
  <button class="mobile-tab" type="button" data-view="groups" aria-label="Mis grupos">
    <svg><use href="#i-users"/></svg><span>Grupos</span>
  </button>
  <button class="mobile-tab mobile-tab-action" type="button" data-open-modal="expense" aria-label="A&ntilde;adir gasto">
    <span class="mobile-tab-action-icon"><svg><use href="#i-plus"/></svg></span><span>Gasto</span>
  </button>
  <button class="mobile-tab" type="button" data-view="balances" aria-label="Saldos">
    <svg><use href="#i-wallet"/></svg><span>Saldos</span>
  </button>
  <button class="mobile-tab" id="mobileMoreToggle" type="button" aria-label="Abrir m&aacute;s opciones" aria-controls="sidebar" aria-expanded="false">
    <svg><use href="#i-menu"/></svg><span>M&aacute;s</span>
  </button>
</nav>
