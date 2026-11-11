<?php

declare(strict_types=1);

/**
 * Contract: the production overview wraps only the resource tabs' content in a plain div; the Balance tab's
 * .cropBalanceContainer sits directly under the overview, like the reference.
 */

function failProductionBalanceWrapperContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/ProductionCtrl.php');
$menu = file_get_contents($root . '/game/include/resources/Templates/production/menu.php');
if ($controller === false || $menu === false) {
    failProductionBalanceWrapperContract('Unable to read the production controller or menu template.');
}
if (!str_contains($controller, "\$this->view->vars['content'] .= '<div>' . \$view->output() . '</div>';")) {
    failProductionBalanceWrapperContract('The resource tabs must be wrapped in their own plain div.');
}
if (!str_contains($controller, "\$this->view->vars['content'] .= '</div></div>';")) {
    failProductionBalanceWrapperContract('The overview must close only the inner div and #productionOverview.');
}
if (str_contains($menu, "navigationSpacer\"></div></div><div>'")) {
    failProductionBalanceWrapperContract('The menu must not open a content wrapper that the Balance tab does not have.');
}

fwrite(STDOUT, "Production balance wrapper contract passed.\n");
