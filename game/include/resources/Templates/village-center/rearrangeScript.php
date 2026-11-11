<?php
/** @var array<string, mixed> $vars */
$rearrangeText = static fn(string $key): string => (string)T('Buildings', 'rearrangeMode.' . $key);
$rearrangeJs = static fn($value): string => json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>
<script type="text/javascript">
    jQuery(function () {
        Travian.Game.Village.RearrangeBuildings.checker = <?= json_encode((string)$vars['checker']) ?>;
        // The build page links of the slots stay inert while rearranging.
        jQuery('#villageContent a').on('click', function (event) { event.preventDefault(); });
        jQuery('#villageContent path').removeAttr('onclick');
        Travian.Game.Village.RearrangeBuildings.initialize(<?= (int)\Core\Village::getInstance()->getKid() ?>);
        Travian.Translation.add({
            'gid15.rearrangeBuildings_step2': <?= $rearrangeJs($rearrangeText('step2')) ?>,
            'gid15.rearrangeBuildings_dragAndDrop': <?= $rearrangeJs($rearrangeText('dragAndDrop')) ?>,
            'gid15.rearrangeToContinue': <?= $rearrangeJs('<span class="notice">' . $rearrangeText('toContinue') . '</span>') ?>,
            'gid15.rearrangeBuildings': <?= $rearrangeJs($rearrangeText('title')) ?>,
            'allgemein.onUnloadMessage': <?= $rearrangeJs($rearrangeText('unloadMessage')) ?>
        });
    });
</script>
