<?php
$entityQueryRaw = (string) ($vars['entityQuery'] ?? '');
$entityQuery = htmlspecialchars($entityQueryRaw, ENT_QUOTES, 'UTF-8');
$reportTabPaths = [
    0 => '/report/overview',
    1 => '/report/offensive',
    2 => '/report/defensive',
    3 => '/report/scouting',
    4 => '/report/other',
    5 => '/report/archive',
    6 => '/report/surrounding',
];
$reportPath = $reportTabPaths[(int) $vars['selectedTabId']] ?? '/report/overview';
?>
<div class="navigationSpacer"></div>
<div id="reportsForm">
    <?php
    $selectedTabId = (int) $vars['selectedTabId'];
    $sortFlag = $vars['recursive'] ? 1 : 0;
    $activeTypes = $selectedTabId === 0
        ? \Controller\ReportsCtrl::OVERVIEW_TOKEN_TYPES
        : ($vars['customCategories'][$selectedTabId] ?? []);
    $isCategoryTab = $selectedTabId >= 1 && $selectedTabId <= 4;
    ?>
    <?php if (!empty($vars['allCategories'][$selectedTabId])):?>
    <script>
        Travian.Game.Reports.filter.icons = [<?=implode(',', [...array_diff($vars['allCategories'][$selectedTabId], [\Controller\ReportsCtrl::SETTLING_FILTER_ICON]), ...($selectedTabId === 4 ? \Controller\ReportsCtrl::OTHER_TAB_HIDDEN_TYPES : [])]);?>];
    </script>
    <div class="reportFilter">
        <div>
            <?php foreach ($vars['allCategories'][$selectedTabId] as $category):
                $active = in_array($category, $activeTypes, true);
                $toggled = $active ? array_diff($activeTypes, [$category]) : [...$activeTypes, $category];
                $toggled = array_diff($toggled, [\Controller\ReportsCtrl::SETTLING_FILTER_ICON]);
                sort($toggled);
                $title = htmlspecialchars(T("Reports", "reportFilterTitles.$category"), ENT_QUOTES, 'UTF-8');
                $filterHref = $reportPath . '?opt=' . rawurlencode(\Controller\ReportsCtrl::sortToken($sortFlag, $toggled)) . $entityQueryRaw;
                ?><button type="button" class="iconFilter <?=$active ? 'iconFilterActive' : '';?>" title="<?=$title;?>" onclick="this.disabled = true; window.location.href = '<?=htmlspecialchars($filterHref, ENT_QUOTES, 'UTF-8');?>'; return false;" alt="<?=$title;?>" useicon=""><img src="/img/spacer.gif" class="iReport iReport<?=$category;?>"></button><?php
            endforeach; ?>
        </div>
        <div>
            <input type="text" placeholder="<?=htmlspecialchars(T('Reports', 'playerOrVillage'), ENT_QUOTES, 'UTF-8');?>" id="VillageOrPlayerName" autocomplete="off">
            <ul id="VillageOrPlayerName-autocompleter" class="autocompleter-choices"></ul>
            <script type="text/javascript">
                jQuery(function() {
                    let timer = null;

                    const clear = function() {
                        selector
                            .empty()
                            .append(jQuery('<li></li>').html(Travian.Translation.translate('{cropfinder.keine_ergebnisse}')))
                            .show();
                    };

                    const updateList = function(entries) {
                        if (!entries.length) return clear();

                        const rows = entries.map(function(entry) {
                            const title = entry.type === 'Village' ? entry.name + ' (' + entry.x + '|' + entry.y + ')' : entry.name;
                            const li = jQuery('<li class="autocompleter" data-type="' + entry.type + '" data-id="' + entry.id + '">' + title + '</li>');
                            li.on('mousedown', function(event) {
                                var url = new URL(window.location.href);
                                url.searchParams.delete('villageId');
                                url.searchParams.delete('playerId');
                                url.searchParams.delete('page');
                                url.searchParams.set(jQuery(event.target).data('type') === 'Village' ? 'villageId' : 'playerId', jQuery(event.target).data('id'));
                                window.location = url.toString();
                            });

                            return li;
                        });
                        selector.empty().append(rows).show();
                    };

                    const fetchData = function(value) {
                        Travian.api('autocomplete/villageorplayer', {
                            data: {search: value},
                            success: function(entries) { updateList(Array.isArray(entries) ? entries : []); },
                            error: function() { updateList([]); }
                        });
                    };

                    const onKeyDown = function(event) {
                        if (event.which === 27) {
                            selector.hide();
                            return false;
                        }

                        return true;
                    }

                    const onInputChange = (event) => {
                        if (timer) {
                            clearTimeout(timer);
                        }
                        timer = setTimeout(() => {
                            fetchData(event.target.value);
                            timer = null;
                        }, 333)
                    }

                    const input = jQuery('#VillageOrPlayerName');

                    const selector = jQuery('#VillageOrPlayerName-autocompleter')
                        .css({display: 'none', top: input.outerHeight()})
                    ;

                    input
                        .on('input', onInputChange)
                        .on('keydown', onKeyDown)
                        .on('focusout', function() { selector.hide() })
                    ;

                    input.parent().css({position: 'relative'});
                });
            </script>
        </div>
    </div>
    <?php endif;?>
    <?php
    $markAllLabel = T("Reports", "Mark all as read");
    $markSelectedLabel = T("Reports", "Mark selected as read");
    $markCategoryLabel = T("Reports", "Mark category as read");
    $deleteAllLabel = T("Reports", "Delete all reports");
    $deleteSelectedLabel = T("Reports", "Delete selected reports");
    $deleteCategoryLabel = T("Reports", "Delete all category reports");
    $cancelLabel = T("Reports", "Cancel");
    $dontWarnLabel = T('Reports', 'bulkActions.dontWarnAgain');
    $hugeAmount = T("Reports", "a huge amount");
    $markLabel = $isCategoryTab ? $markCategoryLabel : $markAllLabel;
    $deleteLabel = $isCategoryTab ? $deleteCategoryLabel : $deleteAllLabel;
    $markIntro = $isCategoryTab
        ? T('Reports', 'bulkActions.markCategoryIntro.' . $selectedTabId)
        : T('Reports', 'bulkActions.markAllIntro');
    $deleteIntro = $isCategoryTab
        ? T('Reports', 'bulkActions.deleteCategoryIntro.' . $selectedTabId)
        : T('Reports', 'bulkActions.deleteAllIntro');
    ?>
    <?php if ($vars['hasPermission'] && (int)$vars['selectedTabId'] !== 5): ?>
    <div class="buttonWrapper">
        <button type="button" value="<?=$markLabel;?>" id="<?=get_button_id();?>" class="textButtonV1 green markAsRead" onclick="Travian.Game.Reports.handleMarkAsRead()" data-text-all="<?=$markAllLabel;?>" data-text-selected="<?=$markSelectedLabel;?>" data-text-category="<?=$markCategoryLabel;?>" version="textButtonV1"><?=$markLabel;?></button>
        <button type="button" value="<?=$deleteLabel;?>" id="<?=get_button_id();?>" class="textButtonV1 green delete" title="<?=T("Reports", "Delete");?>" onclick="Travian.Game.Reports.handleDelete(false)" data-text-all="<?=$deleteAllLabel;?>" data-text-selected="<?=$deleteSelectedLabel;?>" data-text-category="<?=$deleteCategoryLabel;?>" version="textButtonV1"><?=$deleteLabel;?></button>
    </div>
    <?php endif; ?>
    <div class="footer">
        <?php if ($vars['hasPermission']):?>
            <div id="markAll">
                <input class="check" type="checkbox" id="sAll1" onclick="Travian.Game.Reports.selectAll(this);">
                <span><label for="sAll1"><?=T("Reports", "select all");?></label></span>
            </div>
        <?php endif;?>
        <?=$vars['navigator'];?>
        <div class="clear"></div>
    </div>
    <table cellpadding="1" cellspacing="1" id="overview" class="row_table_data">
        <thead>
        <tr>
            <th colspan="2"><?=T("Reports", "Subject");?>:</th>
            <th class="dat">
                <a href="<?=$reportPath;?>?page=<?=max(1, (int)$vars['page']);?>&amp;opt=<?=rawurlencode(\Controller\ReportsCtrl::sortToken($vars['recursive'] ? 0 : 1, $activeTypes));?><?=$entityQuery;?>"
                   class="sorting <?=$vars['recursive'] ? "asc" : "desc";?>">
                    <?=T("Reports", "Received");?>&nbsp;
                    <img src="/img/spacer.gif" alt="sort"/>
                </a>
            </th>
        </tr>
        </thead>
        <tbody>
        <?=$vars['reports'];?>
        </tbody>
    </table>
    <div class="footer">
        <?php if ($vars['hasPermission']):?>
            <div id="markAll">
                <input class="check" type="checkbox" id="sAll2" onclick="Travian.Game.Reports.selectAll(this);">
                <span><label for="sAll2"><?=T("Reports", "select all");?></label></span>
            </div>
        <?php endif;?>
        <?=$vars['navigator'];?>
        <div class="clear"></div>
    </div>

    <?php if ($vars['hasPermission']):?>
        <?php if ((int)$vars['selectedTabId'] === 5): ?>
    <div class="buttonWrapper">
		<button type="button" value="<?=T("Reports", "Recover");?>" id="<?=get_button_id();?>" class="textButtonV1 green recover" onclick="Travian.Game.Reports.processRecoverIds(Travian.Game.Reports.selectedReportIds)" version="textButtonV1"><?=T("Reports", "Recover");?></button>
		<button type="button" value="<?=T("Reports", "Delete");?>" id="<?=get_button_id();?>" class="textButtonV1 green delete" onclick="Travian.Game.Reports.processDelete(true)" version="textButtonV1"><?=T("Reports", "Delete");?></button>
    </div>
        <?php else: ?>
    <div class="buttonWrapper">
        <button type="button" value="<?=$markLabel;?>" id="<?=get_button_id();?>" class="textButtonV1 green markAsRead" onclick="Travian.Game.Reports.handleMarkAsRead()" data-text-all="<?=$markAllLabel;?>" data-text-selected="<?=$markSelectedLabel;?>" data-text-category="<?=$markCategoryLabel;?>" version="textButtonV1"><?=$markLabel;?></button>
        <button type="button" value="<?=$deleteLabel;?>" id="<?=get_button_id();?>" class="textButtonV1 green delete" title="<?=T("Reports", "Delete");?>" onclick="Travian.Game.Reports.handleDelete(false)" data-text-all="<?=$deleteAllLabel;?>" data-text-selected="<?=$deleteSelectedLabel;?>" data-text-category="<?=$deleteCategoryLabel;?>" version="textButtonV1"><?=$deleteLabel;?></button>
    </div>
    <div class="dialogTemplates">
        <div class="markAsReadDialog">
            <?=$markIntro;?><br>
            <?=T('Reports', 'bulkActions.markAllCount');?>&nbsp;<span data-total-count="<?=$hugeAmount;?>" class="count"><?=$hugeAmount;?></span>
            <div class="dialogButtons">
                <button type="submit" value="<?=$cancelLabel;?>" id="<?=get_button_id();?>" class="textButtonV1 grey" onclick="Travian.Game.Reports.closeDialog()" version="textButtonV1"><?=$cancelLabel;?></button>
                <button type="submit" value="<?=$markLabel;?>" id="<?=get_button_id();?>" class="textButtonV1 grey confirm negativeAction" onclick="Travian.Game.Reports.processMarkAsRead()" data-text-all="<?=$markAllLabel;?>" data-text-selected="<?=$markSelectedLabel;?>" data-text-category="<?=$markCategoryLabel;?>" version="textButtonV1"><?=$markLabel;?></button>
            </div>
            <label>
                <input type="checkbox" class="hideMarkAsReadDialog" onchange="Travian.Game.Preferences.set('hideMarkAsReadDialog', this.checked)"> <?=$dontWarnLabel;?>
            </label>
        </div>
        <div class="deleteDialog">
            <div class="description">
                <p data-js="dialog-intro" data-text-selected="<?=T('Reports', 'bulkActions.deleteSelectedIntro');?>" data-text-all="<?=T('Reports', 'bulkActions.deleteAllIntro');?>" data-text-category="<?=htmlspecialchars($deleteIntro, ENT_QUOTES, 'UTF-8');?>"><?=$deleteIntro;?></p>
                <p><?=T('Reports', 'bulkActions.deleteCount');?>&nbsp;<span data-total-count="<?=$hugeAmount;?>" class="count"><?=$hugeAmount;?></span></p>
            </div>
            <div class="dialogButtons">
                <button type="submit" value="<?=$cancelLabel;?>" id="<?=get_button_id();?>" class="textButtonV1 grey" onclick="Travian.Game.Reports.closeDialog()" version="textButtonV1"><?=$cancelLabel;?></button>
                <button type="submit" value="<?=$deleteLabel;?>" id="<?=get_button_id();?>" class="textButtonV1 grey confirm negativeAction" onclick="Travian.Game.Reports.processDelete(false)" data-text-all="<?=$deleteAllLabel;?>" data-text-selected="<?=$deleteSelectedLabel;?>" data-text-category="<?=$deleteCategoryLabel;?>" version="textButtonV1"><?=$deleteLabel;?></button>
            </div>
            <label>
                <input type="checkbox" class="hideDeleteDialog" onchange="Travian.Game.Preferences.set('hideDeleteDialog', this.checked)"> <?=$dontWarnLabel;?>
            </label>
        </div>
    </div>
        <?php endif; ?>
    <?php endif;?>
    <?=$vars['inboxSize'];?>
</div>
