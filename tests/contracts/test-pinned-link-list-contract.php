<?php

declare(strict_types=1);

function failPinnedLinkListContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/View/GameView.php');
$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/layout/sidebarBoxLinklist.php');
if ($source === false || $template === false) {
    failPinnedLinkListContract('Pinned link-list renderer must be readable.');
}

foreach (
    [
        "T('LinkList', 'Farmlist')",
        'renderLinkListItem(',
        'class="link"',
        'class="name"',
        "\$cacheKey = 'links_v3_' . \$this->session->getPlayerId()",
        "\$view->vars['links'] = \$pinnedLinks . \$playerLinks",
        "\$view->vars['noLinks'] = \$view->vars['links'] === ''",
    ]
    as $needle
) {
    if (!str_contains($source, $needle)) {
        failPinnedLinkListContract(
            'Pinned links must render native V2 svg.link + .name rows without legacy ► markers.'
        );
    }
}

if (str_contains($source, 'voucher.php')) {
    failPinnedLinkListContract('Pinned links must not expose the retired full-page voucher route.');
}

if (
    !preg_match('/function renderLinkListItem\(.*?function renderLinkList\(/s', $source, $itemFn) ||
    !preg_match('/function renderLinkList\(\).*?function renderActiveVillageBox\(/s', $source, $listFn)
) {
    failPinnedLinkListContract('Unable to isolate the link-list renderers for legacy-marker checks.');
}
$linkListSource = $itemFn[0] . $listFn[0];
if (
    str_contains($linkListSource, '&#9658;') ||
    str_contains($linkListSource, 'style="color: red') ||
    str_contains($linkListSource, '<img src="img/spacer.gif"')
) {
    failPinnedLinkListContract(
        'Link-list rows must not keep legacy triangle text, inline red styles, or spacer images.'
    );
}

if (
    !str_contains($template, 'class="sidebarBox') ||
    !str_contains($template, 'expanded') ||
    !str_contains($template, '$vars["links"] !== ""') ||
    !str_contains($template, '/linklist.php')
) {
    failPinnedLinkListContract('The link-list sidebar must use the expanded shell and clean /linklist.php edit href.');
}

$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/LinkListCtrl.php');
if ($controller === false || !str_contains($controller, "'links_v3_' . \$playerId")) {
    failPinnedLinkListContract('Editing a personal link must invalidate the current sidebar cache version.');
}

echo "Pinned link-list contract passed.\n";
