<?php

use Core\Helper\TimezoneHelper;
use Core\Session;
use Game\Formulas;
use Game\Hero\HeroItems;

/** @var array $params */
?>
    <br/>
    <h1>Add Pre registration code</h1>
    <p></p>
    <form method="post" name="snd" action="admin.php">
        <input type="hidden" name="action" value="preRegistrationByEmail"/>
        <?= getCheckerInput(); ?>
        <div>
            <table class="adminTable">
                <tbody>
                <tr>
                    <td>
                        <label>Email:</label>
                        <input type="text" name="email" value="<?= \Game\AdminUi::esc($params['email']); ?>"
                               maxlength="100">
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <p class="adminActions"><button class="textButtonV2 buttonFramed rectangle withText green" type="submit" name="submit" value="submit"><div>Send</div></button></p>
        <p class="center"><?= \Game\AdminUi::esc($params['error']); ?></p>
    </form>
