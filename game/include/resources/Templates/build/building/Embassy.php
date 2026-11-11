<?php
$escape = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<?php if ($vars['showTabs']): ?>
<div class="contentNavi tabNavi ">
    <?php foreach ($vars['tabs'] as $tab => $label): ?>
        <?php
        $buttonId = get_button_id();
        $state = $vars['action'] === $tab ? 'active' : 'normal';
        $url = embassyTabUrl($tab);
        ?>
        <div title="" class="container <?= $state; ?>">
            <div class="background-start">&nbsp;</div>
            <div class="background-end">&nbsp;</div>
            <div class="content favor <?= $vars['favorTab'] === $tab ? 'favorActive' : ''; ?> favorKey<?= $tab; ?>">
                <a class="tabItem" id="<?= $escape($buttonId); ?>" href="<?= $escape($url); ?>"><?= $escape($label); ?></a>
            </div>
        </div>
        <script type="text/javascript">
            jQuery(function () {
                if (jQuery('#<?= $buttonId; ?>').length > 0) {
                    jQuery('#<?= $buttonId; ?>').on('click', function () {
                        jQuery(window).trigger('tabClicked', [this, <?= json_encode([
                            'class' => ' ' . $state,
                            'title' => false,
                            'target' => false,
                            'id' => $buttonId,
                            'href' => $url,
                            'onclick' => false,
                            'enabled' => true,
                            'text' => $label,
                            'dialog' => false,
                            'plusDialog' => false,
                            'goldclubDialog' => false,
                            'containerId' => '',
                            'buttonIdentifier' => $buttonId,
                        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>]);
                    });
                }
            });
        </script>
    <?php endforeach; ?>
    <a type="submit" id="nestedTabFavorButton" class="icon nestedTabRowButton "
       title="<?= $escape(T('inGame', 'Set tab')); ?>"
       onclick="Travian.api('favourite-tab', {data: {name: 'embassyBuildingSubTabs',key: '<?= $escape($vars['action']); ?>'},success: function(data) {jQuery('.tabNavi .favor').removeClass('favorActive');jQuery('.favor.favorKey<?= $escape($vars['action']); ?>').addClass('favorActive');}});return false;"><img src="/img/spacer.gif" class="&nbsp;" alt=""></a>
    <div class="clear"></div>
</div>
<?php endif; ?>
<?= $vars['content']; ?>
