<section class="app-view" id="view-balances" data-title="Quién debe a quién" data-subtitle="Soluciona deudas con menos transferencias">
  <div class="balance-layout">
    <div>
      <div class="balance-summary">
        <article><span>Te deben</span><strong class="success">+<?= money($summary['owed_to_user']) ?></strong><small><?= count(array_filter($balances, fn($item) => $item['amount'] > 0)) ?> saldos</small></article>
        <article><span>Debes</span><strong class="danger">-<?= money($summary['user_owes']) ?></strong><small><?= count(array_filter($balances, fn($item) => $item['amount'] < 0)) ?> saldos</small></article>
        <article><span>Balance neto</span><strong><?= ($summary['net_balance'] >= 0 ? '+' : '-') . money($summary['net_balance']) ?></strong><small><?= $summary['net_balance'] >= 0 ? 'A tu favor' : 'Pendiente' ?></small></article>
      </div>
      <article class="panel balance-list"><div class="panel-header"><div><h2>Balances</h2><p>Saldos calculados a partir de gastos y pagos reales</p></div></div>
        <?php foreach ($balances as $i => $balance): ?>
          <div class="balance-row searchable" data-search="<?= e(mb_strtolower($balance['name'] . ' ' . $balance['detail'])) ?>">
            <div class="avatar <?= $i % 2 ? 'peach' : '' ?>"><?= e(initials($balance['name'])) ?></div>
            <div class="balance-person"><strong><?= e($balance['name']) ?></strong><span><?= e($balance['detail']) ?></span></div>
            <strong class="<?= $balance['amount'] > 0 ? 'success' : 'danger' ?>"><?= ($balance['amount'] > 0 ? '+' : '-') . money($balance['amount']) ?></strong>
            <button class="button <?= $balance['amount'] > 0 ? 'button-blue' : 'button-primary' ?> record-settlement" data-group-id="<?= (int) $balance['group_id'] ?>" data-counterparty-id="<?= (int) $balance['user_id'] ?>" data-amount="<?= abs((float) $balance['amount']) ?>" data-direction="<?= $balance['amount'] > 0 ? 'paid_to_me' : 'paid_by_me' ?>"><?= $balance['amount'] > 0 ? 'Marcar recibido' : 'Marcar pagado' ?></button>
          </div>
        <?php endforeach; ?>
        <?php if ($balances === []): ?><div class="settled-empty"><span>✓</span><strong>Todo está saldado</strong><p>No tienes pagos pendientes.</p></div><?php endif; ?>
      </article>
    </div>
    <aside class="panel smart-settle"><span class="category-icon mint"><svg><use href="#i-check"/></svg></span><h2>Liquidación inteligente</h2><p>Los balances descuentan automáticamente las transferencias completadas y se recalculan después de cada operación.</p><div class="settle-facts"><div><strong><?= count($balances) ?></strong><span>Saldos abiertos</span></div><div><strong><?= money($summary['owed_to_user'] + $summary['user_owes']) ?></strong><span>Por liquidar</span></div></div></aside>
  </div>
</section>
