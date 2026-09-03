<header class="topbar">
  <button class="icon-button mobile-menu" id="menuToggle" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false"><svg><use href="#i-menu"/></svg></button>
  <div class="page-heading">
    <h1 id="pageTitle">Hola, <?= e($currentUser['first_name'] ?? 'Usuario') ?>! <span>👋</span></h1>
    <p id="pageSubtitle">Aquí tienes el resumen de tus gastos compartidos</p>
  </div>
  <div class="top-actions">
    <label class="search-box"><svg><use href="#i-search"/></svg><input id="globalSearch" type="search" placeholder="Buscar" autocomplete="off"></label>
    <div class="notification-wrap"><button class="icon-button notification" id="notificationToggle" aria-label="Notificaciones" aria-expanded="false"><svg><use href="#i-bell"/></svg><?php if (array_filter($notifications, fn($item) => $item['read_at'] === null)): ?><span></span><?php endif; ?></button>
      <div class="notification-panel" id="notificationPanel"><div class="notification-head"><strong>Notificaciones</strong><small><?= count(array_filter($notifications, fn($item) => $item['read_at'] === null)) ?> nuevas</small></div>
        <?php foreach ($notifications as $notification): ?>
          <?php $isGroupInvitation = $notification['type'] === 'group_invitation' && preg_match('/^#group-invitation:(\d+)$/', (string) $notification['action_url'], $invitationMatch); ?>
          <?php if ($isGroupInvitation): ?>
            <div class="notification-item group-invitation <?= $notification['read_at'] ? '' : 'unread' ?>">
              <i></i><span><strong><?= e($notification['title']) ?></strong><small><?= e($notification['message']) ?></small></span>
              <div class="invitation-actions">
                <button class="invitation-accept" type="button" data-invitation-action="accept" data-group-id="<?= (int) $invitationMatch[1] ?>">Aceptar</button>
                <button class="invitation-decline" type="button" data-invitation-action="decline" data-group-id="<?= (int) $invitationMatch[1] ?>">Rechazar</button>
              </div>
            </div>
          <?php else: ?>
            <button class="notification-item <?= $notification['read_at'] ? '' : 'unread' ?>" data-view="<?= str_contains($notification['action_url'] ?? '', 'balance') ? 'balances' : (str_contains($notification['action_url'] ?? '', 'group') ? 'groups' : 'expenses') ?>"><i></i><span><strong><?= e($notification['title']) ?></strong><small><?= e($notification['message']) ?></small></span></button>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($notifications === []): ?><p class="notification-empty">No tienes notificaciones.</p><?php endif; ?>
      </div>
    </div>
    <button class="button button-primary" data-open-modal="expense"><svg><use href="#i-plus"/></svg>Añadir gasto</button>
  </div>
</header>
