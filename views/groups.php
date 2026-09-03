<section class="app-view" id="view-groups" data-title="Mis grupos" data-subtitle="Organiza tus gastos compartidos por grupos">
  <div class="view-toolbar"><div><strong><?= $summary['group_count'] ?> grupos</strong><span> en total</span></div><button class="button button-primary" data-open-modal="group"><svg><use href="#i-plus"/></svg>Crear grupo</button></div>
  <div class="group-grid expanded">
    <?php foreach ($groups as $index => $group): ?>
    <article class="group-card large searchable" data-search="<?= e(strtolower($group['name'])) ?>" data-group-details="<?= (int) $group['id'] ?>" data-group-name="<?= e($group['name']) ?>" role="button" tabindex="0" aria-label="Ver integrantes de <?= e($group['name']) ?>">
      <div class="group-cover cover-<?= $index + 1 ?>"><span class="category-icon <?= $group['tone'] ?>"><svg><use href="#i-<?= $group['icon'] ?>"/></svg></span></div>
      <div class="group-body"><div class="group-card-head"><div><h3><?= e($group['name']) ?></h3><p><?= count($group['members']) ?> participantes</p></div><?php if (($group['user_role'] ?? '') === 'owner'): ?><button type="button" class="delete-group" data-group-id="<?= (int) $group['id'] ?>" data-group-name="<?= e($group['name']) ?>" title="Eliminar grupo" aria-label="Eliminar el grupo <?= e($group['name']) ?>"><svg><use href="#i-trash"/></svg></button><?php endif; ?></div>
      <div class="group-metrics"><div><span>Gasto total</span><strong><?= money(abs($group['total'])) ?></strong></div><div><span>Presupuesto</span><strong><?= money($group['budget']) ?></strong></div></div>
      <div class="progress-copy"><span>Consumido</span><span><?= $group['progress'] ?>%</span></div><div class="progress"><i style="width:<?= $group['progress'] ?>%"></i></div>
      <div class="group-footer"><div class="mini-avatars"><?php foreach ($group['members'] as $member): ?><i><?= e(initials($member)) ?></i><?php endforeach; ?></div><div class="group-footer-actions"><button type="button" class="text-button show-group-members" data-group-id="<?= (int) $group['id'] ?>" data-group-name="<?= e($group['name']) ?>">Integrantes</button><button type="button" class="text-button add-members" data-group-id="<?= (int) $group['id'] ?>" data-group-name="<?= e($group['name']) ?>">+ Personas</button><button type="button" class="text-button group-detail" data-group="<?= e($group['name']) ?>">Ver gastos →</button></div></div></div>
    </article>
    <?php endforeach; ?>
  </div>
</section>
