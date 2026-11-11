<?php

declare(strict_types=1);

namespace Controller\Ajax;

use Core\Config;
use Core\Session;
use Game\GoldHelper;
use resources\View\PHPBatchView;

use function getCustom;

/**
 * Data source for the OpenTravian Plus information dialog.
 *
 * The client-side PlusDialog builds the dialog from this payload before the
 * player chooses to buy the feature.  Keeping that confirmation step separate
 * from the premium purchase endpoint prevents a queue-advert click from
 * spending Gold immediately.
 */
class plus extends AjaxBase
{
    public function dispatch(): void
    {
        $helper = new GoldHelper();
        $durationDays = round((int) Config::getInstance()->gold->plusAccountDurationSeconds / 86400, 1);

        $payload = [
            'title' => $this->label('title', 'OpenTravian Plus'),
            'gold' => getCustom('serverIsFreeGold')
                ? T('Global', 'Unlimited')
                : Session::getInstance()->getAvailableGold(),
            'subHeadLine' => $this->label(
                'subHeadLine',
                'In order to use this feature, you need to activate OpenTravian Plus.'
            ),
            'goldButton' => $helper->getPlusButton(false, false, true),
            'buttonSubtitle' => sprintf(
                $this->label('buttonSubtitle', 'Bonus duration in days: %s'),
                '<span class="bold">' . $durationDays . '</span>'
            ),
            'plusPopupButtonExtraFeatures' => $this->label(
                'plusPopupButtonExtraFeatures',
                'Additionally, you will have access to the following features:'
            ),
            'features' => [
                'fullScreen' => $this->feature('fullScreen', 'Larger map', 'For a better overview, the map can be extended to cover the whole screen and display a larger area.'),
                'attackWarning' => $this->feature('attackWarning', 'Attack warning', 'Incoming attacks will be displayed in the village list.'),
                'buildingQueue' => $this->feature('buildingQueue', 'Building queue', 'The building queue allows you to queue one further construction order.'),
                'directLinks' => $this->feature('directLinks', 'Quick links', 'Enhance your gameplay with personalized quick links. Easily set unique shortcuts for each village, ensuring quick access to your most crucial buildings.'),
                'linkList' => $this->feature('linkList', 'Link list', 'Allows for freely definable links, which allow you to reach every page in the game with one simple click.'),
                'villageStatistics' => $this->feature('villageStatistics', 'Village overview', 'Overview of production, stock, culture points and troops for all your villages.'),
                'tradeMulti' => $this->feature('tradeMulti', 'Merchants can run twice', 'Merchants can automatically repeat resource deliveries for a second time.'),
            ],
            'furtherInfos' => sprintf(
                $this->label(
                    'furtherInfos',
                    'OpenTravian Plus enables the above mentioned features for %s days and can be extended at any time. If you select the automatic extension of this feature, it will be extended one day before it runs out and the gold will also be deducted then. For further information, click the "i".'
                ),
                $durationDays
            ),
        ];
        $this->response = $payload + ['html' => $this->html($payload)];
    }

    /**
     * Version 2 dialogs take their markup from the server: the clicked feature first, the
     * others in the reference order.
     *
     * @param array<string, mixed> $payload
     */
    private function html(array $payload): string
    {
        $requested = is_string($_POST['featureKey'] ?? null) ? $_POST['featureKey'] : '';
        $order = ['attackWarning', 'buildingQueue', 'directLinks', 'linkList', 'villageStatistics', 'fullScreen', 'tradeMulti'];
        $keys = in_array($requested, $order, true) ? array_merge([$requested], array_diff($order, [$requested])) : $order;
        $features = [];
        foreach ($keys as $key) {
            $features[] = ['image' => $key] + $payload['features'][$key];
        }
        $view = new PHPBatchView('ajax/premiumDialog');
        $view->vars['title'] = $payload['title'];
        $view->vars['gold'] = $payload['gold'];
        $view->vars['features'] = $features;
        $view->vars['subHeadLine'] = $payload['subHeadLine'];
        $view->vars['extraFeatures'] = $payload['plusPopupButtonExtraFeatures'];
        $view->vars['package'] = 'plus';
        $view->vars['duration'] = '<span>' . $payload['buttonSubtitle'] . '</span>';
        $view->vars['button'] = $payload['goldButton'];

        return $view->output();
    }

    /** @return array{title: string, text: string} */
    private function feature(string $key, string $title, string $text): array
    {
        return [
            'title' => $this->label("features.{$key}.title", $title),
            'text' => $this->label("features.{$key}.text", $text),
        ];
    }

    private function label(string $key, string $fallback): string
    {
        return (string) T('plusPopup', $key, $fallback);
    }
}
