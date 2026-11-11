<div id="expansionCulturePoints">
    <div id="villageSlotInformation">
        <h4 class="round"><?=T(
                "ResidencePalace", "Controllable villages"
            ); ?></h4>
        <table cellpadding="0" cellspacing="0"
               class="transparent villagesSummary">
            <tr>
                <th><?=T(
                        "ResidencePalace", "Number of your villages"
                    ); ?></th>
                <td><?=$vars['total_villages']; ?></td>
            </tr>
            <tr>
                <th><?=T(
                        "ResidencePalace", "Maximum controllable villages"
                    ); ?></th>
                <td><?=$vars['max_village_count']; ?></td>
            </tr>
        </table>

        <table cellpadding="1" cellspacing="1"
               class="transparent culturePointsSummary">
            <tr>
                <th><?=T(
                        "ResidencePalace", "Culture points produced so far"
                    ); ?></th>
                <td><?=$vars['curCP']; ?></td>
            </tr>
            <tr>
                <th><?=T(
                        "ResidencePalace", "Next village controllable at"
                    ); ?></th>
                <td><?=$vars['nextCP']; ?></td>
            </tr>
            <tr class="totalRow">
                <th><?=T(
                        "ResidencePalace", "Culture points still needed"
                    ); ?></th>
                <td>
                    <div class="speechArrowWrapper">
                        <div class="speechArrowBack"></div>
                    </div>
                    <div><?=$vars['needCP']; ?></div>
                </td>
            </tr>
        </table>
        <div class="fluidSpeechBubble-container">
            <div class="fluidSpeechBubble">
                <div class="bubbleBorder fluidSpeechBubble-tl"></div>
                <div class="bubbleBorder fluidSpeechBubble-tr"></div>
                <div class="bubbleBorder fluidSpeechBubble-tc"></div>
                <div class="bubbleBorder fluidSpeechBubble-ml"></div>
                <div class="bubbleBorder fluidSpeechBubble-mr"></div>
                <div class="bubbleBorder fluidSpeechBubble-mc"></div>
                <div class="bubbleBorder fluidSpeechBubble-bl"></div>
                <div class="bubbleBorder fluidSpeechBubble-br"></div>
                <div class="bubbleBorder fluidSpeechBubble-bc"></div>
                <div class="speechArrowBack"></div>
                <div class="fluidSpeechBubble-contents cf"><?=sprintf(
                        T(
                            "ResidencePalace",
                            "In order to found or conquer further villages, you require culture points An additional village will predictably be controllable on x (±5 minutes)"
                        ), $vars['date'], $vars['time']
                    ); ?></div>
            </div>
        </div>
    </div>

    <div id="culturePointsProductionHint">
        <h4 class="round"><?=T(
                "ResidencePalace", "Culture points per day"
            ); ?></h4>
        <table cellpadding="1" cellspacing="1"
               class="transparent culturePointsProduction">
            <tr>
                <th><?=T("ResidencePalace", "Active village"); ?></th>
                <td><?=$vars['activeVillageCP']; ?></td>
            </tr>
            <tr>
                <th><?=T("ResidencePalace", "Other villages"); ?></th>
                <td><?=$vars['otherVillageCP']; ?></td>
            </tr>
            <tr>
                <th><?=T("ResidencePalace", "Hero"); ?></th>
                <td><?=$vars['heroCP']; ?></td>
            </tr>
            <?php
            $allianceBonus = $vars['allianceBonus']-1;
            $allianceBonusCPCount = round($allianceBonus * ($vars['activeVillageCP'] + $vars['otherVillageCP'] + $vars['heroCP']));
            $allianceBonusPercent = $allianceBonus*100;
            $level = floor($allianceBonusPercent / 2);
            $text = sprintf(T("ResidencePalace", "Alliance bonus level"), $level, '&#x202d;&plus;&#x202d;' . $allianceBonusPercent . '&#x202c;&#37;&#x202c;')
            ?>
            <tr>
                <?php if($vars['hasAlliance']):?>
                    <th><a href="<?= allianceUrl(null, 'bonuses', ['subTab' => 1]); ?>"><?=$text;?></a></th>
                <?php else:?>
                    <th><?=$text;?></th>
                <?php endif;?>
                <td><?=$allianceBonusCPCount; ?></td>
            </tr>
            <tr class="totalRow">
                <th><?=T("ResidencePalace", "Total"); ?></th>
                <td>
                    <div class="speechArrowWrapper">
                        <div class="speechArrowBack"></div>
                    </div>
                    <div><?=$vars['activeVillageCP'] + $vars['otherVillageCP']
                            + $vars['heroCP'] + $allianceBonusCPCount; ?></div>
                </td>
            </tr>
        </table>
        <div class="fluidSpeechBubble-container">
            <div class="fluidSpeechBubble">
                <div class="bubbleBorder fluidSpeechBubble-tl"></div>
                <div class="bubbleBorder fluidSpeechBubble-tr"></div>
                <div class="bubbleBorder fluidSpeechBubble-tc"></div>
                <div class="bubbleBorder fluidSpeechBubble-ml"></div>
                <div class="bubbleBorder fluidSpeechBubble-mr"></div>
                <div class="bubbleBorder fluidSpeechBubble-mc"></div>
                <div class="bubbleBorder fluidSpeechBubble-bl"></div>
                <div class="bubbleBorder fluidSpeechBubble-br"></div>
                <div class="bubbleBorder fluidSpeechBubble-bc"></div>
                <div class="speechArrowBack"></div>
                <div class="fluidSpeechBubble-contents cf"><?=T(
                        "ResidencePalace",
                        "The further the buildings of your villages are upgraded, the more culture points per day they will produce"
                    ); ?></div>
            </div>
        </div>
    </div>
</div>