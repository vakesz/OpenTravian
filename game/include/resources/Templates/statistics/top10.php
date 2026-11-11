<?php

use Core\Config;

if (!$vars['isAlliance']): ?>
    <?php
    $config = Config::getInstance();
    $size = 0;
    $max = 0;
    $arr = ['topAttacker', 'topDefender', 'topClimber', 'topRaider'];
    foreach ($arr as $name) {
        $bonus = Config::getProperty("bonus", "top10", $name);
        if (!$bonus->enabled) {
            continue;
        }
        $size++;
        $x = max(array_keys($bonus->ranks));
        if ($x > $max) {
            $max = $x;
        }
    }
    if ($size): ?>
        <div id="allianceBonusWrapper">
            <div class="roundedCornersBox bonusBox" id="bonusInfo0">
                <h4>
                    <button type="button" class="icon bonusCollapse" ref="bonusInfo0">
                        <img src="img/spacer.gif" class="openedClosedSwitch switchClosed"
                             alt="openedClosedSwitch switchClosed">
                    </button>
                    <span style="font-size: 14px;"><strong><?= T("Statistics", "Prizes for top 10"); ?> 😍</strong></span>
                </h4>
                <div class="bonusInfo collapsed bonusInfo0 hide">
                        <table cellpadding="1" cellspacing="1">
                            <thead>
                            <tr>
                                <th><?= T("Statistics", "Bonus name"); ?></th>
                                <?php for ($i = 1; $i <= $max; ++$i): ?>
                                    <th><?= $i; ?></th>
                                <?php endfor; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $translation = [
                                'topAttacker' => T("Statistics", "top10.attackers of the week"),
                                'topDefender' => T("Statistics", "top10.defenders of the week"),
                                'topClimber'  => T("Statistics", "top10.climbers of the week"),
                                'topRaider'   => T("Statistics", "top10.robbers of the week"),
                            ];
        foreach ($arr as $name):
            $bonus = Config::getProperty("bonus", "top10", $name);
            if (!$bonus->enabled) {
                continue;
            }
            ?>
                                <tr>
                                    <td><b><?= $translation[$name]; ?></b></td>
                                    <?php for ($i = 1; $i <= $max; ++$i): ?>
                                        <?php if (isset($bonus->ranks[$i]) && $bonus->ranks[$i] > 0): ?>
                                            <td style="background-color: palegoldenrod">
                                                <b><?= $bonus->ranks[$i]; ?></b> <img src="img/spacer.gif" class="gold"
                                                                                      alt="gold">
                                            </td>
                                        <?php else: ?>
                                            <td>-</td>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($size === 0): ?>
                                <tr>
                                    <td class="noData" colspan="11"><?= T("Statistics",
                                        "No prize is declared for top10"); ?></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        <p>
                            <?= sprintf(T("inGame", "infoBox.MedalsWillBeGivenIn"),
                                appendTimer((($config->dynamic->lastMedalsGiven + $config->game->medals_interval)) - time()))
    ?>
                        </p>
                        <p class="warning" style="font-weight: bold"><?= T("Statistics",
                            "top10 prize distribution desc"); ?></p>
                        <div class="clear"></div>
                    </div>
                </div>
            </div>
        <script type="text/javascript">
            jQuery(function() {
                Travian.Game.AllianceBonus.initBonusOverview();
            });
        </script>
    <br/>
    <?php endif; ?>
<?php endif; ?>
<div class="top10Wrapper">
    <?php foreach ($vars['tables'] as $table): ?>
    <h4 class="<?= $table['h4Class']; ?>"><?= $table['title']; ?></h4>
    <table cellpadding="1" cellspacing="1" id="<?= $table['id']; ?>" class="top10 row_table_data borderGap">
        <thead>
        <?= $table['head']; ?>
        </thead>
        <tbody class="hoverable">
        <?= $table['body']; ?>
        </tbody>
    </table>
    <?php endforeach; ?>
</div>
