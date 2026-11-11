<?php

use Core\Helper\TimezoneHelper;
use Core\Session;
use Game\Formulas;
use Game\Hero\HeroItems;

/** @var array $params */
?>
    <form method="post" action="?action=upgradeVillage&kid=<?= $params['kid']; ?>">
        <?= getCheckerInput(); ?>
        <table class="adminTable">
            <thead>
            <tr>
                <th colspan="2">Player
                    <a href="admin.php?action=editPlayer&uid=<?= $params['playerId']; ?>">
                        <?= \Game\AdminUi::esc($params['playerName']); ?>
                    </a>
                </th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>Village</td>
                <td>
                    <a href="admin.php?action=editVillage&kid=<?= (int) $params['kid']; ?>"><?= \Game\AdminUi::esc($params['villageName']); ?>
                        (<?= implode("|", Formulas::kid2xy($params['kid'])); ?>)</a>
                </td>
            </tr>
            <tr>
                <td>Target population</td>
                <td>
                    <input type="text" name="pop" value="<?= (int) $params['pop']; ?>"
                           id="pop" maxlength="20">
                </td>
            </tr>
            </tbody>
        </table>
        <p class="adminActions">
            <button class="textButtonV2 buttonFramed rectangle withText green" type="submit" name="submit" value="submit"><div>Save</div></button>
        </p>
        <a href="?action=editVillage&kid=<?= $params['kid']; ?>">« go back</a>
    </form>
