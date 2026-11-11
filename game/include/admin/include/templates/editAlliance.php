<?php

use Core\Helper\TimezoneHelper;
use Core\Session;
use Game\Formulas;
use Game\Hero\HeroItems;

/** @var array $params */
?>
    <br/>
    <form action="admin.php?action=editAlliance&section=editAll" method="POST">
        <?= getCheckerInput(); ?>
        <input type="hidden" name="aid" value="<?= $params['allianceId']; ?>">
        <table class="adminTable">
            <thead>
            <tr>
                <th colspan="2">Alliance
                    <a href="admin.php?action=editAlliance&uid=<?= $params['allianceId']; ?>">
                        <?= \Game\AdminUi::esc($params['tag']); ?>
                    </a>
                </th>
            </tr>
            <tr>
                <td>Details</td>
                <td>Description</td>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td class="details">
                    <table class="adminTable">
                        <tr>
                            <th>Tag</th>
                            <td>
                                <input type="text" name="tag" value="<?= \Game\AdminUi::esc($params['tag']); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Name</th>
                            <td>
                                <input type="text" name="name" value="<?= \Game\AdminUi::esc($params['name']); ?>">
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <th>Rank</th>
                            <td>
                                <?= $params['allianceRank']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Points</th>
                            <td>
                                <?= $params['points']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Members</th>
                            <td>
                                <?= $params['Members']; ?>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2"></td>
                        </tr>
                        <?php if ($params['hasPosition']): ?>
                            <?= $params['position']; ?>
                        <?php endif; ?>

                        <tr>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a class="rn3"
                                   href="?action=editAlliance&section=deleteAlliance&aid=<?= $params['allianceId']; ?>&<?= Session::getCheckerForUrl(); ?>"><span class="adminError">&raquo;</span> Delete Alliance</a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="desc2">
                                        <textarea tabindex="8" cols="45" rows="18" name="desc2">
                                            <?= \Game\AdminUi::esc($params['desc2']); ?>
                                        </textarea>
                            </td>
                        </tr>
                    </table>
                <td rowspan="8" class="desc1">
                            <textarea tabindex="7" cols="45" rows="32" name="desc1">
                                <?= \Game\AdminUi::esc($params['desc1']); ?>
                            </textarea>
                </td>
            </tr>
            </tbody>
        </table>
        <table class="adminTable">
            <thead>
            <tr>
                <th>&nbsp;</th>
                <th>Player</th>
                <th>Population:</th>
                <th>Villages</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?= $params['MembersHTML']; ?>
            </tbody>
        </table>
        <p class="adminActions">
            <button class="textButtonV2 buttonFramed rectangle withText green" type="submit" name="submit" value="submit"><div>Save</div></button>
        </p>
    </form>
