<div class="a2b">
    <form method="post" action="/build.php?gid=16&amp;tt=2">
        <input type="hidden" name="timestamp" value="<?php use Core\Session;
        echo $vars['timestamp'];?>"/>
        <input type="hidden" name="timestamp_checksum" value="<?=$vars['timestamp_checksum'];?>"/>
        <input type="hidden" name="b" value="1"/>
        <input type="hidden" name="currentDid" value="<?=$vars['village']['did'];?>"/>
        <?=getCheckerInput();?>
        <table id="troops" cellpadding="1" cellspacing="1">
            <?=$vars['troopsTable'];?>
        </table>

        <div class="destination">
            <table cellpadding="0" cellspacing="0" class="transparent compact">
                <tbody>
                <tr>
                    <td><span><?=T("RallyPoint", "Village");?>:</span></td>
                    <td class="compactInput"><input type="text" id="enterVillageName"
                                                    class="text village" name="villagename" value="<?=$vars['to']['dname'];?>"
                                                    maxlength="25" autocomplete="off"/></td>
                </tr>
                </tbody>
            </table>

            <table cellpadding="0" cellspacing="0" class="transparent compact">
                <tbody>
                <tr>
                    <td><span class="or"><?=T("RallyPoint", "or");?></span>

                        <div class="coordinatesInput">
                            <div class="xCoord">
                                <label for="xCoordInput">X:</label>
                                <input type="text" inputmode="numeric" maxlength="4" value="<?=$vars['to']['x'];?>" name="x" id="xCoordInput"
                                       class="text coordinates x "
                                       onkeyup="Travian.Formatter.Filter.aNumber(this)"
                                       onpaste="var cih = new Travian.Game.RallyPoint.CoordinatesInputHelper({coordinateXInputId: 'xCoordInput', coordinateYInputId: 'yCoordInput'}); cih.insertCoordinates(event);"/>
                            </div>
                            <div class="yCoord">
                                <label for="yCoordInput">Y:</label>
                                <input type="text" inputmode="numeric" maxlength="4" value="<?=$vars['to']['y'];?>" name="y" id="yCoordInput"
                                       class="text coordinates y "
                                       onkeyup="Travian.Formatter.Filter.aNumber(this)"
                                       onpaste="var cih = new Travian.Game.RallyPoint.CoordinatesInputHelper({coordinateXInputId: 'xCoordInput', coordinateYInputId: 'yCoordInput'}); cih.insertCoordinates(event);"/>
                            </div>
                            <div class="clear"></div>
                        </div>
                        <div class="clear"></div>
                    </td>
                </tr>
                </tbody>
            </table>

            <script type="text/javascript">
                jQuery(function() {
                    new Travian.Game.AutoCompleter.VillageName(jQuery('#enterVillageName'));
                });
            </script>
        </div>

        <div class="option">
            <label> <input type="radio" class="radio" name="eventType" value="5"
                    <?=$vars['attackTypes'][2]['checked'] ? 'checked="checked"' : '';?>
                    <?=$vars['attackTypes'][2]['disabled'] ? 'disabled="disabled"' : '';?>
                    />
                <?=T("RallyPoint", "reinforcement");?>        </label>
            <br/> <label> <input type="radio" class="radio" name="eventType"
                    <?=$vars['attackTypes'][3]['checked'] ? 'checked="checked"' : '';?>
                    <?=$vars['attackTypes'][3]['disabled'] ? 'disabled="disabled"' : '';?>
                                 value="3"/>
                <?=T("RallyPoint", "attack");?>: <?=T("RallyPoint", "normal");?>        </label> <br/> <label> <input
                    type="radio" class="radio" name="eventType"
                    value="4"
                    <?=$vars['attackTypes'][4]['checked'] ? 'checked="checked"' : '';?>
                    <?=$vars['attackTypes'][4]['disabled'] ? 'disabled="disabled"' : '';?>
                    />
                <?=T("RallyPoint", "attack");?>: <?=T("RallyPoint", "raid");?>        </label>

        </div>
        <?php if($vars['showHeroChangeHomeCheckBox']):?>
            <div class="redeployHero">
                <label><input class="redeployHeroSetting check"
                              type="checkbox" <?=$vars['process']['settings']['redeployHero'] ? 'checked="checked"' : '';?>
                              name="redeployHero" value="1"/><?=T("RallyPoint", "changeHeroHomeVillage");?></label>
            </div>
        <?php endif;?>
        <div class="clear"></div>
        <?=getButton(
            [
                'type' => 'submit',
                'value' => 'ok',
                'name' => 'ok',
                'id' => 'ok',
                'class' => 'textButtonV1 green ',
            ],
            ['data' => ['type' => 'submit', 'value' => 'ok', 'name' => 'ok']],
            T('RallyPoint', 'send')
        );?>
    </form>
    <?php if($vars['process']['beforeError']):?>
        <p class="error"><?=$vars['process']['beforeErrorMsg'];?></p>
    <?php endif;?>

</div>
