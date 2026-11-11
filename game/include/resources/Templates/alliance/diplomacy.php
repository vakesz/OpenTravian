<?php
/** @var array<string, mixed> $vars */
$action = htmlspecialchars((string) ($vars['formAction'] ?? '/alliance/options/diplomacy'), ENT_QUOTES, 'UTF-8');
$sendLabel = htmlspecialchars(T("Alliance", "Send"), ENT_QUOTES, 'UTF-8');
?>
<div class="diplomacy">
    <h4 class="round"><?=T("Alliance", "Alliance diplomacy");?></h4>
    <form id="offerDiplomacy" method="post" action="<?= $action ?>"><?=\Core\Session::getCheckerInput();?>
        <div class="option offerDiplomacy">
            <table cellpadding="1" cellspacing="1" class="option transparent">
                <tbody>
                <tr>
                    <th><?=T("Alliance", "Alliance");?>:</th>
                    <td><input class="ally text" type="text" name="toAlliance" maxlength="8"></td>
                </tr>
                <?php foreach ([1 => [4, 'offer a confederacy'], 2 => [2, 'offer a NAP'], 3 => [3, 'declare war']] as $radio => [$type, $label]): ?>
                <tr>
                    <td></td>
                    <td>
                        <input class="radio" type="radio" id="dipl<?= $radio ?>" name="diplomacy" value="<?= $type ?>">
                        <label for="dipl<?= $radio ?>"><?=T("Alliance", $label);?></label>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="option">
                <button type="submit" value="<?= $sendLabel ?>" class="textButtonV1 green " version="textButtonV1"> <?= $sendLabel ?></button>
            </p>
        </div>
        <div class="infos">
            <div class="title"><?=T("Alliance", "Hint");?></div>
            <div class="text"><?=T("Alliance", "DiplomacyHint");?></div>
        </div>
        <div class="clear"></div>
        <p class="note option"></p>
        <p class="error option"><?= htmlspecialchars((string) ($vars['error'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </form>
    <form method="post" action="<?= $action ?>">
        <h4 class="round"><?=T("Alliance", "Own offers");?></h4>
        <?php if ($vars['ownOffers'] !== ''): ?>
        <table cellpadding="1" cellspacing="1" class="option own transparent">
            <tbody>
            <?=$vars['ownOffers'];?>
            </tbody>
        </table>
        <?php endif; ?>
        <h4 class="round"><?=T("Alliance", "Foreign offers");?></h4>
        <?php if ($vars['foreign'] !== ''): ?>
        <table cellpadding="1" cellspacing="1" class="option foreign transparent">
            <tbody>
            <?=$vars['foreign'];?>
            </tbody>
        </table>
        <?php endif; ?>
        <h4 class="round"><?=T("Alliance", "Existing relationships");?></h4>
        <table cellpadding="1" cellspacing="1" class="option existing transparent">
            <tbody>
            <?=$vars['exiting'];?>
            </tbody>
        </table>
        <div class="clear"></div>
        <div class="infos">
            <div class="title"><?=T("Alliance", "Tip");?></div>
            <?=T("Alliance", "DiplomacyShowText");?>
        </div>
    </form>
</div>
