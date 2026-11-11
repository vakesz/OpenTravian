<?php

use Core\Helper\TimezoneHelper;
use Core\Session;
use Game\Formulas;
use Game\Hero\HeroItems;

/** @var array $params */
?>
    <tbody class="troops">
    <tr>
        <?php
        for ($i = 1; $i <= 11; ++$i) {
            if ($i == 11) {
                $unitId = 'hero';
            } else {
                $unitId = nrToUnitId($i, $params['race']);
            }
            $title = T("Troops", "$unitId.title");
            echo '<td class="center">' . $title . '</td>';
        }
        ?>
    </tr>
    <tr>
        <?php
        $default = false;
        for ($i = 1; $i <= 11; ++$i) {
            if ($params['units'][$i]) {
                $num = $params['units'][$i];
                if ($num >= 1000000 && !$default) {
                    echo '<td class="center">' . round($num / 1000000) . 'm</td>';
                } else if ($num >= 1000 && !$default) {
                    echo '<td class="center">' . round($num / 1000) . 'k</td>';
                } else {
                    echo '<td class="center">' . $num . '</td>';
                }
            } else {
                echo '<td class="none center">0</td>';
            }
        }
        ?>
    </tr>
    </tbody>
