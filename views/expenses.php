<section class="app-view" id="view-expenses" data-title="Todos los gastos" data-subtitle="Controla y filtra cada gasto compartido">
  <div class="view-toolbar"><div class="stat-inline"><strong><?= money($summary['total_spent']) ?></strong><span> en gastos registrados</span></div><button class="button button-primary" data-open-modal="expense"><svg><use href="#i-plus"/></svg>Añadir gasto</button></div>
  <?php $tableTitle = 'Historial de gastos'; $tableLimit = count($expenses); $showTableControls = true; require __DIR__ . '/../includes/expense-table.php'; ?>
</section>
