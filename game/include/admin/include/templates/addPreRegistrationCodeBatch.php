<?php

use Core\Helper\TimezoneHelper;
use Core\Session;
use Game\Formulas;
use Game\Hero\HeroItems;

/** @var array $params */
?>
    <br/>
    <h1>Add PreRegistration code</h1>
    <p></p>
    <form method="post" name="snd" action="admin.php">
        <input type="hidden" name="action" value="preRegistrationCodeBatch"/>
        <?= getCheckerInput(); ?>
        <div>
            <table class="adminTable">
                <tbody>
                <tr>
                    <td>
                        <label>Number of codes:</label>
                        <input type="number" name="count" value="<?= \Game\AdminUi::esc($params['count']); ?>"
                               maxlength="100">
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <p class="adminActions"><button class="textButtonV2 buttonFramed rectangle withText green" type="submit" name="submit" value="submit"><div>Send</div></button></p>
        <p class="center"><?= \Game\AdminUi::esc($params['error']); ?></p>
    </form>

