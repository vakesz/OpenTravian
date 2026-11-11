<?php

declare(strict_types=1);

namespace Controller\Ajax;

use Core\Session;
use Game\GoldHelper;
use resources\View\PHPBatchView;

use function getCustom;

/**
 * Gold club information dialog. The feature the player clicked comes first, the others follow
 * in the canonical order; the farm list is requested as `farmList` but stored as `raidList`.
 */
class goldclub extends AjaxBase
{
    private const ORDER = ['troopEscape', 'raidList', 'tradeThreeTimes', 'tradeRoute', 'cropFinder', 'messageArchive'];

    public function dispatch(): void
    {
        $requested = is_string($_POST['featureKey'] ?? null) ? $_POST['featureKey'] : '';
        $stored = $requested === 'farmList' ? 'raidList' : $requested;
        $keys = in_array($stored, self::ORDER, true)
            ? array_merge([$stored], array_diff(self::ORDER, [$stored]))
            : self::ORDER;
        $features = [];
        foreach ($keys as $key) {
            $features[] = [
                'image' => $key === 'raidList' ? 'farmList' : $key,
                'title' => T('goldClubPopup', $key . '.title'),
                'text' => T('goldClubPopup', $key . '.text'),
            ];
        }
        $view = new PHPBatchView('ajax/premiumDialog');
        $view->vars['title'] = T('goldClubPopup', 'title');
        $view->vars['gold'] = getCustom('serverIsFreeGold') ? T('Global', 'Unlimited') : Session::getInstance()->getAvailableGold();
        $view->vars['features'] = $features;
        $view->vars['subHeadLine'] = T('goldClubPopup', 'In order to use this feature, you need to activate the Gold club!');
        $view->vars['extraFeatures'] = T('goldClubPopup', 'Additionally, you will have access to the following features:');
        $view->vars['package'] = 'goldclub';
        $view->vars['duration'] = ' ' . T('goldClubPopup', 'Bonus duration') . ': <b>' . T('goldClubPopup', 'whole game round') . '</b>';
        $view->vars['button'] = (new GoldHelper())->getGoldClubButton(true);
        $this->response = [
            'title' => T('goldClubPopup', 'title'),
            'html' => $view->output(),
        ];
    }
}
