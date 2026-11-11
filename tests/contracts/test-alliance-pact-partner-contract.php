<?php

declare(strict_types=1);

// The diplomacy options list existing pacts by the other alliance's tag and link, whichever
// side proposed the pact (aid1 is the proposer); listing aid1 showed a player their own tag.

$source = (string)file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/AllianceCtrl.php');
$start = strpos($source, "\$view->vars['exiting'] = '';");
$end = $start === false ? false : strpos($source, "\$this->view->vars['content'] .= \$view->output();", $start);
$block = $start === false || $end === false ? '' : substr($source, $start, $end - $start);
$partner = 'IF(d.aid1={$this->selectedAllianceID}, d.aid2, d.aid1)';

if ($block === ''
    || !str_contains($block, $partner . ' AS partner')
    || !str_contains($block, 'LEFT JOIN alidata a ON a.id=' . $partner)
    || !str_contains($block, "<a href=\"/alliance/' . (int) \$row['partner'] . '\">")
    || str_contains($block, "\$row['aid1']")) {
    fwrite(STDERR, "Existing pacts must show and link the partner alliance, not the proposer.\n");
    exit(1);
}

echo "Alliance pact partner contract passed.\n";
