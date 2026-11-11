<?php

use Core\Helper\TimezoneHelper;
use Core\Session;
use Game\Formulas;
use Game\Hero\HeroItems;

/** @var array $params */
?>
    <table class="adminTable">
        <thead>
        <tr>
            <th colspan="2"><?= $params['title']; ?></th>
        </tr>
        </thead>
        <?= $params['content']; ?>
        <tbody>
    </table>
