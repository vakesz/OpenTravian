<?php

declare(strict_types=1);

namespace Game\FarmList;

use Core\Helper\TimezoneHelper;
use Core\Session;

use function get_locale;
use function nrToUnitId;
use function T;

require_once __DIR__ . '/../../resources/Templates/farmlist/icons.php';

/**
 * The Farm List tab of the rally point: the static shell (the sticky bar) and the script that hands the read
 * model's viewData, the strings and the glyphs to the page client (public/farm-list.js), which draws the lists.
 * The strings are the `FarmList` catalogue's, which carries the reference's raidList bundle under its own keys.
 */
final class FarmListPage
{
    /** The strings the page client reads. */
    private const LABELS = [
        'a2b_distance',
        'abandonedFarmList',
        'abandonedFarmLists',
        'abandonedVillage',
        'account_locked',
        'activate',
        'activateAllSlots',
        'activateSelectedSlots',
        'activateSlot',
        'addTarget',
        'alliance',
        'ankunft',
        'attacksOnTheWay',
        'avatar_locked',
        'beginnersProtectionDescriptionShort',
        'beingRaided',
        'bounty',
        'cancelStarting',
        'cancel_with_capital_c',
        'combatResult',
        'coordinateX',
        'coordinateY',
        'create',
        'createFarmList',
        'create_list',
        'dauer',
        'day-1',
        'deactivate',
        'deactivateAllSlots',
        'deactivateSelectedSlots',
        'deactivateSlot',
        'deactivatedTargets',
        'delete',
        'deleteAllSlots',
        'deleteSelectedSlots',
        'deleteSlot',
        'deleteSlotsConfirmation',
        'distance',
        'dorfname',
        'duplicate',
        'duplicateAllSlots',
        'duplicateSelectedSlots',
        'duplicateSlot',
        'edit',
        'editAllSlots',
        'editFarmlist',
        'editSelectedSlots',
        'editSlot',
        'editSlots',
        'errorNoListSelected',
        'errorRaidListSlotLimit',
        'errorVillageAlreadyInRaidListQuestion',
        'error_delete',
        'error_limitReached',
        'error_no_troops',
        'farmListDeleteConfirmation',
        'farmListNameExistsForVillage',
        'farmLists',
        'full',
        'hasNoActive',
        'indeterminateTroopsNotice',
        'koordinaten_eingeben',
        'lastRaid',
        'lastStartOfFarmList',
        'listName',
        'maximumCapacity',
        'nameNotEmpty',
        'newEntryPrefilled',
        'nextArrivalIn',
        'nextAttack',
        'noActiveSelected',
        'noFarmList',
        'noRallyPoint',
        'noTargetSelected',
        'noTargetsFound',
        'noticeRaidListSlotLimitBulk',
        'occupiedOasis',
        'ok',
        'onlyLosses',
        'population',
        'report_icon_1',
        'report_icon_15',
        'report_icon_16',
        'report_icon_17',
        'report_icon_2',
        'report_icon_3',
        'save',
        'selectFarmList',
        'selectVillage',
        'selected',
        'sendingNotAllowedOnVacationMode',
        'setDefaultTroops',
        'setTroops',
        'showMore',
        'sitter_not_allowed_only_with_right',
        'sortBy',
        'spieler',
        'startAllActive',
        'startAllFarmLists',
        'startFromAllVillages',
        'startRaidList',
        'startSelectedActive',
        'startSuccess',
        'startWarning',
        'startingFarmLists',
        'totalBounty',
        'tribeWarningForSourceVillage',
        'troops',
        'unoccupiedOasis',
        'useRaidShips',
        'useRaidShipsNote',
        'village',
        'villageNotEmpty',
    ];

    public function render(): string
    {
        $viewData = (new FarmListData())->viewData();
        $config = ['viewData' => $viewData] + $this->bootstrap();
        $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $total = count($viewData['ownPlayer']['farmLists']);

        return '<div id="rallyPointFarmList" class="contentV2">'
            . '<div id="stickyPin"><div id="stickyWrapper">'
            . '<div class="farmListCount">' . $this->escape($this->label('farmLists')) . ': ' . $this->ratio($total, FarmListData::LIST_LIMIT) . '</div>'
            . '<div class="runningFarmLists">' . $this->escape($this->label('startFromAllVillages')) . '</div>'
            . $this->button('createFarmList')
            . $this->button('startAllFarmLists')
            . '</div></div></div>'
            . '<script type="text/javascript">jQuery(function(){Travian.Game.FarmList.render(' . $json . ');});</script>';
    }

    /**
     * What the client needs besides the page data: strings, glyphs and the player's number and time conventions. The
     * target dialog opened from other pages reads it with GET farm-list.
     *
     * @return array<string, mixed>
     */
    public function bootstrap(): array
    {
        $session = Session::getInstance();
        $race = (int)$session->getRace();
        $timezone = $session->isValid() ? $session->getTimezone() : [];

        return [
            'labels' => $this->labels(),
            'glyphs' => farm_list_client_glyphs(),
            'race' => $race,
            'language' => get_locale(),
            // Ships sail the straight line between two villages (Game\Map\ShipRoute), so every Harbor can send raids by ship.
            'harbours' => ['useDirectPath' => true],
            'unitTitles' => $this->unitTitles($race),
            'timeZone' => TimezoneHelper::getTimezone(),
            'timeFormat' => (int)($timezone[1] ?? 0),
            'limits' => ['lists' => FarmListData::LIST_LIMIT, 'slots' => FarmListData::SLOT_LIMIT, 'units' => FarmListData::MAX_UNITS],
            'types' => [
                'village' => FarmListData::TYPE_VILLAGE,
                'capital' => FarmListData::TYPE_CAPITAL,
                'oasisOccupied' => FarmListData::TYPE_OASIS_OCCUPIED,
                'oasisFree' => FarmListData::TYPE_OASIS_FREE,
            ],
        ];
    }

    private function button(string $class): string
    {
        return '<button class="textButtonV2 buttonFramed ' . $class . ' rectangle withText green" type="button" title=""><div>' . $this->escape($this->label($class)) . '</div></button>';
    }

    /** @return array<string, string> */
    private function labels(): array
    {
        $labels = [];
        foreach (self::LABELS as $key) {
            $labels[$key] = $this->label($key);
        }

        return $labels;
    }

    /** @return array<int, string> unit number => title */
    private function unitTitles(int $race): array
    {
        $titles = [];
        for ($unit = 1; $unit <= 10; $unit++) {
            $titles[$unit] = (string)T('Troops', nrToUnitId($unit, $race) . '.title');
        }

        return $titles;
    }

    private function label(string $key): string
    {
        return (string)T('FarmList', $key);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /** The count in the sticky bar as the reference's ratio component draws it: wrapped numerals around a slash. */
    private function ratio(int $nominator, int $denominator): string
    {
        $format = static fn (int $value): string => "\u{202D}" . number_format($value) . "\u{202C}";
        $arabic = get_locale() === 'ar-AE';

        return ($arabic ? "\u{202E}" : "\u{202D}") . '<span class="nominator">' . $format($nominator) . '</span>' . ($arabic ? '\\' : '/') . '<span class="denominator">' . $format($denominator) . '</span>' . "\u{202C}";
    }
}
