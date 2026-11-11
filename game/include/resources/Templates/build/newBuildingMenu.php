<?php

$escape = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$tabs = [
    1 => ['key' => 'InfrastructureTab', 'class' => 'infrastructure'],
    2 => ['key' => 'MilitaryTab', 'class' => 'military'],
    3 => ['key' => 'ResourcesTab', 'class' => 'resources'],
];
?>
<div class="contentNavi subNavi">
    <button type="button" class="scrollFrom" disabled="disabled"></button>
    <div class="scrollingContainer">
        <?php foreach ($tabs as $category => $tab): ?>
            <?php $definition = $vars[$tab['key']]; ?>
            <div class="content">
                <a id="<?= $escape($definition['id']); ?>"
                   title=""
                   class="tabItem <?= $tab['class']; ?> <?= (int)$vars['activeTab'] === $category ? 'active' : 'normal'; ?>"
                   href="/build.php?id=<?= (int)$vars['selectedId']; ?>&amp;category=<?= $category; ?>">
                    <?= $escape($definition['text']); ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="scrollTo" disabled="disabled"></button>
</div>
<div class="navigationSpacer"></div>
<script type="text/javascript" data-cmp-info="6">
    jQuery(function () {
        Travian.Game.TabScrollNavigation();
    });
</script>
