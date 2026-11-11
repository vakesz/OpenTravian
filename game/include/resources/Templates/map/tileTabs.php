<?php
/**
 * Reports and Surrounding tabs of a tile (reference markup: tab links without an href, the click handler
 * drives them).
 *
 * @var array{id: string, reports: string, surrounding: string, reportsId?: string} $vars
 */
$tabs = ['Reports' => ' active', 'Surrounding' => ' normal'];
?>
<div id="<?= $vars['id']; ?>" class="instantTabs">
    <div class="contentNavi tabNavi ">
        <?php foreach ($tabs as $key => $class): $buttonId = get_button_id(); ?>
        <div title="" class="container <?= trim($class); ?>">
            <div class="background-start">&nbsp;</div>
            <div class="background-end">&nbsp;</div>
            <div class="content"><a class="tabItem" id="<?= $buttonId; ?>"><?= T('map', $key); ?></a></div>
        </div>
        <script type="text/javascript">
            jQuery(function() {
                if (jQuery('#<?= $buttonId; ?>').length > 0) {
                    jQuery('#<?= $buttonId; ?>').on('click', function () {
                        jQuery(window).trigger('tabClicked', [this, <?= json_encode([
                            'class' => $class,
                            'title' => false,
                            'target' => false,
                            'id' => $buttonId,
                            'href' => null,
                            'onclick' => false,
                            'enabled' => true,
                            'text' => T('map', $key),
                            'dialog' => false,
                            'plusDialog' => false,
                            'goldclubDialog' => false,
                            'containerId' => '',
                            'buttonIdentifier' => $buttonId,
                        ]); ?>]);
                    });
                }
            });
        </script>
        <?php endforeach; ?>
        <div class="clear"></div>
    </div>
    <div class="tabContainer">
        <table cellpadding="1" cellspacing="1" id="<?= $vars['reportsId'] ?? 'troop_info'; ?>" class="rep transparent"><tbody><?= $vars['reports']; ?></tbody></table>
    </div>
    <div class="tabContainer reports hide">
        <table cellpadding="1" cellspacing="1" id="surrounding_info" class="reportSurround transparent"><?= $vars['surrounding']; ?></table>
    </div>
</div>
<script type="text/javascript">
    Travian.Game.InstantTabs(jQuery('#<?= $vars['id']; ?>'));
</script>
