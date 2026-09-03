<?php
$topCategory = array_key_first($categoryTotals) ?: 'Sin datos';
$topCategoryAmount = $categoryTotals[$topCategory] ?? 0;
$monthLabels = [];
$monthValues = [];
foreach ($monthlyEvolution as $month) {
    $monthLabels[] = ucfirst((new DateTimeImmutable($month['month_key'] . '-01'))->format('M'));
    $monthValues[] = (float) $month['total'];
}
if ($monthValues === []) {
    $monthLabels = ['Sin datos'];
    $monthValues = [0];
}
$monthMax = max($monthValues) ?: 1;
$palette = ['green', 'blue', 'coral', 'yellow'];
?>
<section class="app-view" id="view-stats" data-title="Estadísticas" data-subtitle="Descubre cómo y dónde gastas tu dinero">
  <div class="stats-grid">
    <article class="stat-card"><span>Gasto total</span><strong><?= money($summary['total_spent']) ?></strong><small><?= $summary['expense_count'] ?> gastos registrados</small></article>
    <article class="stat-card"><span>Promedio por gasto</span><strong><?= money($summary['expense_count'] ? $summary['total_spent'] / $summary['expense_count'] : 0) ?></strong><small>Sobre tus movimientos</small></article>
    <article class="stat-card"><span>Mayor categoría</span><strong><?= e($topCategory) ?></strong><small><?= money($topCategoryAmount) ?></small></article>
    <article class="stat-card"><span>Grupos activos</span><strong><?= $summary['group_count'] ?></strong><small>En tu cuenta</small></article>
  </div>
  <div class="stats-charts">
    <article class="panel bar-chart-card"><div class="panel-header"><h2>Gastos por mes</h2><span class="chart-caption">Datos reales</span></div><div class="bar-chart">
      <?php foreach ($monthValues as $i => $value): ?><div><span style="height:<?= max(2, round($value / $monthMax * 92)) ?>%"><i><?= money($value) ?></i></span><small><?= e($monthLabels[$i]) ?></small></div><?php endforeach; ?>
    </div></article>
    <article class="panel category-stats"><div class="panel-header"><h2>Por categoría</h2></div><div class="donut large"><div><strong><?= money($summary['total_spent']) ?></strong><span>Total</span></div></div><ul>
      <?php $categoryIndex = 0; foreach (array_slice($categoryTotals, 0, 4, true) as $category => $amount): ?><li><i class="dot <?= $palette[$categoryIndex] ?>"></i><span><?= e($category) ?></span><strong><?= money($amount) ?></strong></li><?php $categoryIndex++; endforeach; ?>
      <?php if ($categoryTotals === []): ?><li><span>Aún no hay gastos</span></li><?php endif; ?>
    </ul></article>
  </div>
</section>
