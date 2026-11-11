<?php

use Core\Helper\TimezoneHelper;
use Core\Session;
use Game\Formulas;
use Game\Hero\HeroItems;

/** @var array $params */
?>
    <br/>
    <form action="admin.php?action=editPlayer&section=editAll" method="POST">
        <?= getCheckerInput(); ?>
        <input type="hidden" name="uid" id="uid" value="<?= $params['playerId']; ?>">
        <input type="hidden" name="old_gift_gold" value="<?= $params['gift_gold']; ?>">
        <input type="hidden" name="old_bought_gold" value="<?= $params['bought_gold']; ?>">
        <table class="adminTable">
            <thead>
            <tr>
                <th colspan="2">Player
                    <a href="admin.php?action=editPlayer&uid=<?= $params['playerId']; ?>">
                        <?= $params['playerName']; ?>
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
                            <th>Rank</th>
                            <td>
                                <?= $params['playerRank']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Tribe</th>
                            <td>
                                <?= ['Romans', 'Teutons', 'Gauls', 'Nature', 'Natars', 'Egyptians', 'Huns'][$params['tribeId'] - 1]; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Alliance</th>
                            <td>
                                <?= $params['allianceTag']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Villages</th>
                            <td>
                                <?= $params['total_villages']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Population</th>
                            <td>
                                <?= $params['total_pop']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Username</th>
                            <td>
                                <input type="text" name="username" value="<?= $params['playerName']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td>
                                <input name="email" value="<?= \Game\AdminUi::esc($params['email']); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Email Verified?</th>
                            <td>
                                <select name="email_verified">
                                    <option value="0" <?= ($params['email_verified'] == 0 ? 'selected' : ''); ?>>No</option>
                                    <option value="1" <?= ($params['email_verified'] == 1 ? 'selected' : ''); ?>>Yes</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Location</th>
                            <td>
                                <input type="text" name="location" value="<?= $params['playerLocation']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <th>Access</th>
                            <td>
                                <b><?= ['Banned', 'Normal', 'Admin', 'Natars'][$params['access']]; ?></b>
                            </td>
                        </tr>
                        <tr>
                            <th>Gift Gold</th>
                            <td>
                                <?php if (getCustom('allowInterruptionInGame') || getCustom('allowAdminGoldGrants')): ?>
                                <input type="text" name="gift_gold" value="<?= $params['gift_gold']; ?>">
                                <?php else: ?>
                                <?= $params['gift_gold']; ?> <small>(manual grants disabled)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Bought Gold</th>
                            <td>
                                <?php if (getCustom('allowInterruptionInGame')): ?>
                                <input type="text" name="bought_gold" value="<?= $params['bought_gold']; ?>">
                                <?php else: ?>
                                <?= $params['bought_gold']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Silver</th>
                            <td>
                                <input type="text" name="silver" value="<?= $params['silver']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Culture points</th>
                            <td>
                                <input type="text" name="cp" value="<?= $params['cp']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Success adventures count</th>
                            <td>
                                <input type="text" name="success_adventures_count"
                                       value="<?= $params['success_adventures_count']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Old rank</th>
                            <td>
                                <?= $params['oldRank']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Last Activity</th>
                            <td>
                                <?= $params['lastLoginTimeDate']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Registration time</th>
                            <td>
                                <?= $params['registrationTimeDate']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Last IP</th>
                            <td>
                                <a href="admin.php?action=IPBan&ip=<?= $params['lastIP']; ?>"><?= $params['lastIP']; ?></a>
                            </td>
                        </tr>
                        <tr>
                            <th>Last IP Location</th>
                            <td>
                                <?= $params['lastIPLocation']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>IPs</th>
                            <td>
                                <?=implode("<br />", $params['IPs']);?>
                            </td>
                        </tr>
                        <tr>
                            <th>Week Attack Points</th>
                            <td>
                                <input type="text" name="week_attack_points"
                                       value="<?= $params['week_attack_points']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Week Defence Points</th>
                            <td>
                                <input type="text" name="week_defense_points"
                                       value="<?= $params['week_defense_points']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Week Resources Raided</th>
                            <td>
                                <input type="text" name="week_robber_points"
                                       value="<?= $params['week_robber_points']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Total Attack Points</th>
                            <td>
                                <input type="text" name="total_attack_points"
                                       value="<?= $params['total_attack_points']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Total Defence Points</th>
                            <td>
                                <input type="text" name="total_defense_points"
                                       value="<?= $params['total_defense_points']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>New Password</th>
                            <td>
                                <input type="password" name="password" value="">
                            </td>
                        </tr>
                        <tr>
                            <th><b><span>P</span><span>l</span><span>u</span><span>s</span></b>
                            </th>
                            <td>
                                <input type="text" name="plus" value="<?= $params['plusDate']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>*Protection Bought Hour(s)</th>
                            <td>
                                <input type="text" name="protectionHours" value="<?= $params['protectionHours']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>*Protection last buy</th>
                            <td>
                                <input type="text" name="protectionLastExtend" value="<?= $params['protectionLastExtend']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>**Protection</th>
                            <td>
                                <input type="text" name="protection" value="<?= $params['protectionDate']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Wood bonus</th>
                            <td>
                                <input type="text" name="b1" value="<?= $params['b1Date']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Clay bonus</th>
                            <td>
                                <input type="text" name="b2" value="<?= $params['b2Date']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Iron bonus</th>
                            <td>
                                <input type="text" name="b3" value="<?= $params['b3Date']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Crop bonus</th>
                            <td>
                                <input type="text" name="b4" value="<?= $params['b4Date']; ?>">
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2"></td>
                        </tr>
                        <?php if($params['tribeId'] <> 1):?>
                            <tr>
                                <td colspan="2">
                                    <a class="adminWarn" href="?action=editPlayer&section=changeTribeToRomans&uid=<?= $params['playerId']; ?>">&raquo;
                                        Change Tribe To Romans</a>
                                </td>
                            </tr>
                        <?php endif;?>
                        <?php if($params['tribeId'] <> 2):?>
                            <tr>
                                <td colspan="2">
                                    <a class="adminWarn" href="?action=editPlayer&section=changeTribeToTeutons&uid=<?= $params['playerId']; ?>">&raquo;
                                        Change Tribe To Teutons</a>
                                </td>
                            </tr>
                        <?php endif;?>
                        <?php if($params['tribeId'] <> 3):?>
                            <tr>
                                <td colspan="2">
                                    <a class="adminWarn" href="?action=editPlayer&section=changeTribeToGuals&uid=<?= $params['playerId']; ?>">&raquo;
                                        Change Tribe To Guals</a>
                                </td>
                            </tr>
                        <?php endif;?>
                        <?php if($params['tribeId'] <> 6):?>
                            <tr>
                                <td colspan="2">
                                    <a class="adminWarn" href="?action=editPlayer&section=changeTribeToEgyptians&uid=<?= $params['playerId']; ?>">&raquo;
                                        Change Tribe To Egyptians</a>
                                </td>
                            </tr>
                        <?php endif;?>
                        <?php if($params['tribeId'] <> 7):?>
                            <tr>
                                <td colspan="2">
                                    <a class="adminWarn" href="?action=editPlayer&section=changeTribeToHuns&uid=<?= $params['playerId']; ?>">&raquo;
                                        Change Tribe To Huns</a>
                                </td>
                            </tr>
                        <?php endif;?>
                        <tr>
                            <td colspan="2"></td>
                        </tr>

                        <tr>
                            <td colspan="2">
                                <a class="rn3"
                                   href="?action=editPlayer&section=deletePlayer&uid=<?= $params['playerId']; ?>&<?= Session::getCheckerForUrl(); ?>"><span class="adminError">&raquo;</span> Delete User</a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="?action=bannedList&section=banToggle&uid=<?= $params['playerId']; ?>&<?= Session::getCheckerForUrl(); ?>">&raquo;
                                    Ban/Unban User</a>
                            </td>
                        </tr>
                        <?php if(Session::getInstance()->getPlayerId() > 0):?>
                        <tr>
                                <td colspan="2">
                                    <a href="/messages.php?t=1&id=<?= $params['playerId']; ?>" target="_blank">&raquo;
                                        Send message</a>
                                </td>
                        </tr>
                        <?php endif;?>
                        <tr>
                            <td colspan="2">
                                <a href="?action=editPlayer&section=punishPlayer&uid=<?= $params['playerId']; ?>">&raquo;
                                    Punish player</a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="?action=gifts&giftUid=<?= $params['playerId']; ?>">&raquo;
                                    Gift Gold</a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="?action=editPlayer&section=fillResources&uid=<?= $params['playerId']; ?>">&raquo;
                                    Fill all resources</a>
                            </td>
                        </tr>
                        <?php if ($params['access'] <> 0): ?>
                            <tr>
                                <td colspan="2"></td>
                            </tr>
                            <?php if ($params['access'] == 3): ?>
                                <tr>
                                    <td colspan="2">
                                        <a href="?action=editPlayer&section=setAsNormalUser&uid=<?= $params['playerId']; ?>">&raquo;
                                            Set as normal user</a>
                                    </td>
                                </tr>
                            <?php elseif ($params['access'] == 1): ?>
                                <tr>
                                    <td colspan="2">
                                        <a href="?action=editPlayer&section=setAsFakeUser&uid=<?= $params['playerId']; ?>">&raquo;
                                            Set as fake user</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endif; ?>

                        <tr>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="?action=heroAddItem&uid=<?= $params['playerId']; ?>">&raquo; Add hero item</a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="?action=addVillage&uid=<?= $params['playerId']; ?>">&raquo; Add village</a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="?action=editPlayer&section=killHero&uid=<?= $params['playerId']; ?>">&raquo;
                                    Kill hero</a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="desc2">
                                        <textarea tabindex="8" cols="45" rows="20" name="desc2">
                                            <?= \Game\AdminUi::esc($params['desc2']); ?>
                                        </textarea>
                            </td>
                        </tr>
                    </table>
                <td class="desc1">
                            <textarea tabindex="8" cols="45" rows="70" name="desc1">
                                <?= \Game\AdminUi::esc($params['desc1']); ?>
                            </textarea>
                </td>
            </tr>
            </tbody>
        </table>
        <br/>
        <div>
            <div class="adminCol">
                <table class="adminTable">
                    <thead>
                    <tr>
                        <th colspan="6">Ban History (<?= $params['banHistory']['total']; ?>)</th>
                    </tr>
                    <tr>
                        <td class="head"><b>Start</b></td>
                        <td class="head"><b>End</b></td>
                        <td class="head"><b>Duration</b></td>
                        <td class="head"><b>Reason</b></td>
                    </tr>
                    </thead>
                    <tbody>
                    <?= $params['banHistory']['content']; ?>
                    </tbody>
                </table>
            </div>
            <div class="adminCol">
                <table class="adminTable">
                    <thead>
                    <tr>
                        <th colspan="3">Villages</th>
                    </tr>
                    <tr>
                        <td>Name</td>
                        <td>Inhabitants</td>
                        <td>Coordinates</td>
                    </tr>
                    </thead>
                    <tbody>
                    <?= $params['villages']; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <br/>
        <br/><br/>
        <br/><br/>
        <br/><br/>
        <br/><br/>
        <br/><br/>
        <br/><br/>
        <p class="adminActions">
            <button class="textButtonV2 buttonFramed rectangle withText green" type="submit" name="submit" value="submit"><div>Save</div></button>
        </p>
    </form>
