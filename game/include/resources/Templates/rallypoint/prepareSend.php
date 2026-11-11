<div class="a2b">
    <form method="post" action="/build.php?gid=16&amp;tt=2">
        <table id="short_info" cellpadding="1" cellspacing="1">
            <tbody>
            <tr>
                <th><?=T("RallyPoint", "target");?>:</th>
                <td><?=$vars['process']['settings']['to']['villageName'];?>
                    <a class="" href="/karte.php?x=<?=$vars['process']['settings']['to']['x'];?>&amp;y=<?=$vars['process']['settings']['to']['y'];?>">
                        <span class="coordinates coordinatesWrapper"><span class="coordinateX">(<?=coordinateNumberHTML($vars['process']['settings']['to']['x'])?></span><span class="coordinatePipe">|</span><span class="coordinateY"><?=coordinateNumberHTML($vars['process']['settings']['to']['y'])?>)</span></span>
                    </a>
                </td>
            </tr>
            <tr>
                <th><?=T("RallyPoint", "player");?>:</th>
                <td><a href="<?= profileUrl((int) $vars['process']['settings']['to']['uid']); ?>"><?=$vars['process']['settings']['to']['playerName'];?></a>
                </td>
            </tr>
            <?php if (!empty($vars['ship'])): ?>
            <tr>
                <th><?=T("RallyPoint", "ships");?>:</th>
                <td class="shipsDisplay">
                    <div class="shipAvailability">
                        <label class="useShip">
                            <input type="checkbox" name="useShip" value="1"<?= $vars['ship']['checked'] ? ' checked="checked"' : ''; ?>/>
                            <span><?=T("RallyPoint", "useShip");?> (<?= T("Troops", $vars['ship']['unitId'] . ".title"); ?><?= $vars['ship']['duration'] !== '' ? ', ' . $vars['ship']['duration'] : ''; ?>)</span>
                        </label>
                        <span class="shipsWrapper">
                            <?php foreach ($vars['ship']['home'] as $type => $count): ?>
                                <?php if ($count > 0): ?>
                                    <span class="inlineIcon"><i class="unit_small <?= $type == 3 ? '' : 'tribe' . $vars['ship']['tribe']; ?> t<?= 900 + $type; ?>"></i><span><?= number_format_x($count); ?></span></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </span>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?=$vars['process']['settings']['troop_details'];?>
        <?php if($vars['process']['settings']['redeployHero']):?>
        <p><?=sprintf(T("RallyPoint", "Changed successfully: %s will be the new home village for hero"), $vars['process']['settings']['to']['villageName']);?></p>
        <?php endif;?>
        <?php if($vars['process']['settings']['redeployHero']):?>
        <input type="hidden" name="redeployHero" value="1"/>
        <?php endif;?>
        <input type="hidden" name="timestamp" value="<?=$vars['process']['settings']['timestamp'];?>"/>
        <input type="hidden" name="timestamp_checksum" value="<?=$vars['process']['settings']['timestamp_checksum'];?>"/>
        <input type="hidden" name="id" value="39"/>
        <input type="hidden" name="a" value="<?=$vars['process']['settings']['a2bId'];?>"/>
        <input type="hidden" name="eventType" value="<?= (int)$vars['eventType']; ?>"/>
        <input type="hidden" name="kid" value="<?=$vars['process']['settings']['to']['kid'];?>"/>
        <input type="hidden" name="troop[t1]" value="<?=$vars['process']['settings']['units'][1];?>"/>
        <input type="hidden" name="troop[t2]" value="<?=$vars['process']['settings']['units'][2];?>"/>
        <input type="hidden" name="troop[t3]" value="<?=$vars['process']['settings']['units'][3];?>"/>
        <input type="hidden" name="troop[t4]" value="<?=$vars['process']['settings']['units'][4];?>"/>
        <input type="hidden" name="troop[t5]" value="<?=$vars['process']['settings']['units'][5];?>"/>
        <input type="hidden" name="troop[t6]" value="<?=$vars['process']['settings']['units'][6];?>"/>
        <input type="hidden" name="troop[t7]" value="<?=$vars['process']['settings']['units'][7];?>"/>
        <input type="hidden" name="troop[t8]" value="<?=$vars['process']['settings']['units'][8];?>"/>
        <input type="hidden" name="troop[t9]" value="<?=$vars['process']['settings']['units'][9];?>"/>
        <input type="hidden" name="troop[t10]" value="<?=$vars['process']['settings']['units'][10];?>"/>
        <input type="hidden" name="troop[t11]" value="<?=$vars['process']['settings']['units'][11];?>"/>
        <input type="hidden" name="sendReally" value="1"/>
        <input type="hidden" name="troopsSent" value="1"/>
        <input type="hidden" name="currentDid" value="<?=$vars['village']['did'];?>"/>
        <input type="hidden" name="b" value="2"/>
        <input type="hidden" name="villagename" value="<?=$vars['process']['settings']['to']['dname'];?>"/>
        <input type="hidden" name="x" value="<?=$vars['process']['settings']['to']['x'];?>"/>
        <input type="hidden" name="y" value="<?=$vars['process']['settings']['to']['y'];?>"/>
        <div id="rallyPointButtonsContainer">
            <?=getButton(
                [
                    'type' => 'submit',
                    'value' => 'edit',
                    'name' => 'edit',
                    'id' => 'btn_edit',
                    'class' => 'textButtonV1 green',
                    'title' => T('RallyPoint', 'edit'),
                ],
                ['data' => ['type' => 'submit', 'value' => 'edit', 'name' => 'edit']],
                T('RallyPoint', 'edit')
            );?>
            <?=getButton(
                [
                    'type' => 'submit',
                    'value' => 'ok',
                    'name' => 'ok',
                    'id' => 'ok',
                    'class' => 'textButtonV1 green ',
                    'title' => T('RallyPoint', 'send'),
                ],
                ['data' => ['type' => 'submit', 'value' => 'ok', 'name' => 'ok']],
                T('RallyPoint', 'send')
            );?>
        </div>
    </form>
    <?php if($vars['process']['error']):?>
        <p class="error"><?=$vars['process']['errorMsg'];?></p>
    <?php endif;?>
</div>
