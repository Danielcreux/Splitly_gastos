<section class="app-view active" id="view-dashboard" data-title="Hola, <?= e($currentUser['first_name'] ?? 'Usuario') ?>! 👋" data-subtitle="Aquí tienes el resumen de tus gastos compartidos">
  <div class="period-row"><strong>Resumen actualizado</strong></div>
  <div class="dashboard-top">
    <div class="summary-grid">
      <article class="summary-card primary"><span>Balance total</span><strong><?= ($summary['net_balance'] >= 0 ? '+' : '-') . money($summary['net_balance']) ?></strong><small>Saldo neto actual</small></article>
      <article class="summary-card positive"><span>Te deben</span><strong>+<?= money($summary['owed_to_user']) ?></strong><small><?= count(array_filter($balances, fn($item) => $item['amount'] > 0)) ?> saldos a favor</small></article>
      <article class="summary-card negative"><span>Debes</span><strong>-<?= money($summary['user_owes']) ?></strong><small><?= count(array_filter($balances, fn($item) => $item['amount'] < 0)) ?> saldos pendientes</small></article>
      <article class="summary-card neutral"><span>Gastos registrados</span><strong><?= money($summary['total_spent']) ?></strong><small><?= $summary['expense_count'] ?> movimientos</small></article>
    </div>
    <article class="panel chart-card">
      <div class="panel-header"><strong>Evolución mensual del gasto</strong></div>
      <div class="chart-canvas-wrap">
        <canvas id="expenseEvolutionChart" role="img" aria-label="Gráfico interactivo de evolución del gasto"></canvas>
      </div>
    </article>
  </div>

  <div class="section-title"><h2>Tus grupos</h2><button class="text-button" data-view="groups">Ver todos →</button></div>
  <div class="group-grid compact">
    <?php foreach ($groups as $group): ?>
      <article class="group-card searchable" data-search="<?= e(strtolower($group['name'])) ?>">
        <div class="group-card-head"><span class="category-icon <?= $group['tone'] ?>"><svg><use href="#i-<?= $group['icon'] ?>"/></svg></span></div>
        <h3><?= e($group['name']) ?></h3><p><?= count($group['members']) ?> participantes</p>
        <div class="amount-row"><span>Total gasto</span><strong class="<?= $group['total'] < 0 ? 'danger' : 'success' ?>"><?= ($group['total'] >= 0 ? '+' : '-') . money($group['total']) ?></strong></div>
        <div class="mini-avatars"><?php foreach ($group['members'] as $member): ?><i><?= e(initials($member)) ?></i><?php endforeach; ?></div>
        <div class="progress-copy"><span>Presupuesto</span><span><?= $group['progress'] ?>%</span></div><div class="progress"><i style="width:<?= $group['progress'] ?>%"></i></div>
        <button class="button button-outline full" data-view="groups">Ver detalles</button>
      </article>
    <?php endforeach; ?>
    <button class="new-group-card" data-open-modal="group"><span><svg><use href="#i-plus"/></svg></span>Crear nuevo<br>grupo</button>
  </div>
  <?php $tableTitle = 'Gastos recientes'; $tableLimit = 5; require __DIR__ . '/../includes/expense-table.php'; ?>
</section>
