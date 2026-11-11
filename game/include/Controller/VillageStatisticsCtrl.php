<?php

namespace Controller;

use Core\Caching\Caching;
use Core\Caching\ProfileCache;
use Core\Config;
use Core\Database\DB;
use Core\Session;
use Core\Village;
use Game\AllianceBonus\AllianceBonus;
use Game\Formulas;
use Game\ResourcesHelper;
use Game\TrainingHelper;
use function getGame;
use function getGameSpeed;
use Model\ArtefactsModel;
use Model\ResourceFieldsModel;
use Model\MarketModel;
use Core\Helper\WebService;
use Model\VillageModel;
use Model\VillageOverviewModel;
use Model\WonderOfTheWorldModel;
use resources\View\GameView;
use resources\View\PHPBatchView;
use function getDisplay;
use function nanoseconds;

class VillageStatisticsCtrl extends GameCtrl
{
    public function __construct()
    {
        parent::__construct();
        $this->view = new GameView();
        $this->view->vars['bodyCssClass'] = 'perspectiveResources village3';
        $this->view->vars['showHeaderBar'] = TRUE;
        $this->view->vars['showCloseButton'] = TRUE;
        $this->view->vars['titleInHeader'] = T("villageOverview", "villageOverview");
        if (!$this->allowed() && isset($_GET['s']) && is_numeric($_GET['s']) && (int)$_GET['s'] !== 0) {
            // The locked statistics tabs are not routes without Plus; the reference sends the player to the overview.
            WebService::redirect(villageStatisticsUrl(0));
        }
        $selectedTabId = isset($_GET['s']) && is_numeric($_GET['s']) && in_array((int)$_GET['s'], $this->allowed() ? [0, 2, 4, 5] : [0], true) ? (int)$_GET['s'] : ($this->allowed() ? $this->favoriteOverviewTab() : 0);
        if (!$this->allowed()) {
            $this->view->vars['content'] .= PHPBatchView::render('village-statistics/menuDisabled');
        } else {
            $this->view->vars['content'] .= PHPBatchView::render('village-statistics/menuEnabled', ['selectedTabId' => $selectedTabId]);
        }
        $this->view->vars['contentCssClass'] = 'village3 village3' . match ($selectedTabId) {
            2 => 'Resources',
            4 => 'Culturepoints',
            5 => 'Troops',
            default => 'Overview',
        };
        switch ($selectedTabId) {
            case 0:
                $this->showOverview();
                break;
            case 2:
                $this->showResources();
                break;
            case 4:
                $this->showCulturePoints();
                break;
            case 5:
                $this->showTroops();
                break;
        }
    }

    private function favoriteOverviewTab(): int
    {
        $favorite = $this->session->getStoredFavoriteTab('villageOverview');

        return in_array($favorite, [2, 4, 5], true) ? $favorite : 0;
    }

    private function showOverview(): void
    {
        $view = new PHPBatchView("village-statistics/overview");
        $content = &$view->vars['content'];
        $m = new VillageOverviewModel();
        $d1 = new ResourceFieldsModel();

        $alliance_bonus = AllianceBonus::getTradersBonus($this->session->getAllianceId(), $this->session->getAllianceJoinTime());
        $market = new MarketModel();

        $echoAttack = function ($kid, $count, $class, $title) {
            if (!$count) {
                return null;
            }
            $content = null;
            $number = $this->number_format($count);
            $content .= '<a href="/build.php?newdid=' . $kid . '&amp;id=39&amp;tt=1#at">';
            $content .= '<img class="' . $class . '" src="img/spacer.gif" title="' . $number . 'x ' . $title . '" alt="' . $number . 'x ' . $title . '"></a>';
            return $content;
        };

        $playerVillages = $this->getPlayerVillages();
        foreach ($playerVillages as $row) {
            $content .= '<tr class="' . ($row['kid'] == $this->session->getKid() ? 'hl' : 'hover') . '">';
            $content .= '<td class="vil fc"><a href="/dorf1.php?newdid=' . $row['kid'] . '">' . $row['name'] . '</a></td>';
            $content .= '<td class="att">';
            if ($this->allowed()) {
                $oases = $d1->getOasesAsString($row['kid']);
                $data = [
                    'totalMovements'            => 0,
                    'oasesAttacks'              => $d1->getIncomingAttacksToMyOases($oases),
                    'oasesReinforcements'       => $d1->getIncomingReinforcementToMyOases($oases),
                    'inComingAttacksToMe'       => $d1->getIncomingAttacksToMe($row['kid']),
                    'inComingReinforcementToMe' => $d1->getIncomingReinforcementToMe($row['kid']),
                    'outGoingAttacks'           => $d1->getOutgoingAttacks($row['kid']),
                    'outGoingReinforcements'    => $d1->getOutGoingReinforcements($row['kid']),
                ];

                $data['totalMovements'] += $data['inComingAttacksToMe']['count'];
                $data['totalMovements'] += $data['inComingReinforcementToMe']['count'];
                $data['totalMovements'] += $data['outGoingAttacks']['count'];
                $data['totalMovements'] += $data['outGoingReinforcements']['count'];
                $data['totalMovements'] += $data['oasesAttacks']['count'];
                $data['totalMovements'] += $data['oasesReinforcements']['count'];

                $content .= $echoAttack(
                    $row['kid'],
                    $data['oasesAttacks']['count'],
                    'att3',
                    T("villageOverview", "Oasis attacking troops")
                );
                $content .= $echoAttack(
                    $row['kid'],
                    $data['inComingAttacksToMe']['count'],
                    'att1',
                    T("villageOverview", "Other attacking troops")
                );
                $content .= $echoAttack(
                    $row['kid'],
                    $data['oasesReinforcements']['count'],
                    'def3',
                    T("villageOverview", "Oasis reinforcing troops")
                );
                $content .= $echoAttack(
                    $row['kid'],
                    $data['inComingReinforcementToMe']['count'],
                    'def1',
                    T("villageOverview", "Village reinforcing troops")
                );
                $content .= $echoAttack(
                    $row['kid'],
                    $data['outGoingAttacks']['count'],
                    'att2',
                    T("villageOverview", "Own attacking troops")
                );
                $content .= $echoAttack(
                    $row['kid'],
                    $data['outGoingReinforcements']['count'],
                    'def2',
                    T("villageOverview", "Own Reinforcing troops")
                );


                if ($data['totalMovements'] == 0) {
                    $content .= '<span class="none">-</span>';
                }
            } else {
                $content .= '<span class="none">?</span>';
            }
            $content .= '</td>';
            $content .= '<td class="bui">';
            if ($this->allowed()) {
                $upgrades = $m->getVillageUpgradeBuildings($row['kid']);
                while ($build = $upgrades->fetch_assoc()) {
                    $item_id = $m->getFieldItemId($row['kid'], $build['building_field']);
                    $content .= '<a href="/dorf' . ($build['building_field'] <= 18 ? 1 : 2) . '.php?newdid=' . $row['kid'] . '"><img class="bau" src="/img/spacer.gif" title="' . T(
                        "Buildings",
                            "{$item_id}.title"
                    ) . '" alt="' . T("Buildings", "{$item_id}.title") . '"></a>';
                }
                if (!$upgrades->num_rows) {
                    $content .= '<span class="none">-</span>';
                }
            } else {
                $content .= '<span class="none">?</span>';
            }
            $content .= '</td>';
            $content .= '<td class="tro">';
            if ($this->allowed()) {
                $training = $m->getVillageTroopsTraining($row['kid']);
                if (!sizeof($training)) {
                    $content .= '<span class="none">-</span>';
                } else {
                    foreach ($training as $item_id => $a) {
                        foreach ($a as $nr => $v) {
                            $unitId = nrToUnitId($nr, $this->session->getRace());
                            if (getDisplay("seperateLargeAndSmallTrainingBuildingsSummaryInDorf3")) {
                                $title = $this->number_format($v['num']) . 'x ' . T("Troops", "{$unitId}.title");
                            } else {
                                $trainingTime = $v['end_time'];
                                if (Config::getProperty("game", "useNanoseconds")) {
                                    $trainingTime -= nanoseconds();
                                } elseif (Config::getProperty("game", "useMilSeconds")) {
                                    $trainingTime -= miliseconds();
                                } else {
                                    $trainingTime -= time();
                                }
                                $title = $this->number_format($v['num']) . 'x ' . T("Troops", "{$unitId}.title");
                                if ($trainingTime > 0) {
                                    $title .= '<br />';
                                    $title .= sprintf(T("inGame", "TrainingTime"), secondsToString(trainingTimeDisplaySeconds($trainingTime)));
                                    $title = htmlspecialchars($title);
                                }
                            }
                            $content .= '<a href="/build.php?newdid=' . $row['kid'] . '&amp;gid=' . $item_id . '"><img class="unit u' . $unitId . '" src="/img/spacer.gif" alt="' . $this->number_format($v['num']) . 'x ' . T(
                                "Troops",
                                    "{$unitId}.title"
                            ) . '" title="' . $title . '"></a>';
                        }
                    }
                }
            } else {
                $content .= '<span class="none">?</span>';
            }
            $content .= '</td>';
            $mo = $market->getMarketAndTradeOfficeLevel($row['kid']);

            $total_merchants = $mo[17];
            $merchant_cap = Formulas::merchantCAP($this->session->getRace(), $mo[28], $alliance_bonus);
            $merchants_on_the_way = $market->getOnTheWayMerchantsCount($row['kid'], $merchant_cap);
            $merchants_offering = $market->getOfferingMerchantsCount($row['kid'], $merchant_cap);
            $free_merchants = $total_merchants - $merchants_on_the_way - $merchants_offering;

            $content .= '<td class="tra lc"><a href="/build.php?newdid=' . $row['kid'] . '&gid=17&t=5">&#x202d;&#x202d;'
                . $free_merchants . '&#x202c;/&#x202d;' . $total_merchants . '&#x202c;&#x202c;</a></td>';
            $content .= '</tr>';
        }
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getPlayerVillages()
    {
        $cache = new ProfileCache($this->session->get("profileCacheVersion"));
        return $cache->getProfileVillages($this->session->getPlayerId());
    }

    /**
     * @param mixed $x
     * @return mixed
     */
    private function number_format($x)
    {
        return number_format_x($x, 0);
    }

    /** Resolves the Resources row tab: explicit `sr`, else the stored favourite. */
    private function resourceSubTab(): int
    {
        if (isset($_GET['sr']) && is_numeric($_GET['sr']) && (int)$_GET['sr'] >= 0 && (int)$_GET['sr'] <= 3) {
            return (int)$_GET['sr'];
        }

        return $this->session->getStoredFavoriteTab('villageStatisticsResources') ?? 0;
    }

    /** Resolves the Troops row tab: explicit `su`, else the stored favourite. */
    private function troopSubTab(): int
    {
        if (isset($_GET['su']) && is_numeric($_GET['su']) && (int)$_GET['su'] >= 0 && (int)$_GET['su'] <= 4) {
            return (int)$_GET['su'];
        }

        return $this->session->getStoredFavoriteTab('villageOverviewTroops') ?? 0;
    }

    /**
     * @param list<list<string>> $definitions
     */
    private function subTabsHtml(string $favorName, array $definitions, int $active): string
    {
        $tabs = [];
        foreach ($definitions as $index => [$slug, $label, $href]) {
            $tabs[] = ['slug' => $slug, 'label' => T('villageOverview', $label), 'href' => $href, 'active' => $index === $active];
        }
        $keys = \Controller\Ajax\favouriteTab::NESTED_KEYS[$favorName];
        $stored = $this->session->getStoredFavoriteTab($favorName);

        return PHPBatchView::render('village-statistics/subTabs', [
            'tabs' => $tabs,
            'favorName' => $favorName,
            'favorSlug' => $stored === null ? null : ($keys[$stored] ?? null),
        ]);
    }

    private function resourceTabsHtml(int $active): string
    {
        return $this->subTabsHtml('villageStatisticsResources', [
            ['resources', 'Resources', '/village/statistics/resources/resources'],
            ['warehouse', 'Warehouse', '/village/statistics/resources/warehouse'],
            ['production', 'Production', '/village/statistics/resources/production'],
            ['capacity', 'Capacity', '/village/statistics/resources/capacity'],
        ], $active);
    }

    private function troopTabsHtml(int $active): string
    {
        return $this->subTabsHtml('villageOverviewTroops', [
            ['own', 'Own Troops', '/village/statistics/troops/own'],
            ['support', 'Troops in villages', '/village/statistics/troops/support'],
            ['smithy', 'Smithy', '/village/statistics/troops/smithy'],
            ['hospital', 'Hospital', '/village/statistics/troops/hospital'],
            ['training', 'Training', '/village/statistics/troops/training'],
        ], $active);
    }

    private static function ltr(string $text): string
    {
        return '&#x202d;' . $text . '&#x202c;';
    }

    private function showResourceEconomy(int $tab): void
    {
        $m = new VillageOverviewModel();
        foreach ($this->getPlayerVillages() as $row) {
            ResourcesHelper::updateVillageResources($row['kid']);
        }
        $rows = [];
        $result = $m->getPlayerVillageEconomy($this->session->getPlayerId());
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $view = new PHPBatchView('village-statistics/resourceEconomy');
        $view->vars['subTabs'] = $this->resourceTabsHtml($tab);
        $view->vars['tab'] = $tab;
        $view->vars['selectedKid'] = (int)$this->session->getKid();
        $view->vars['rows'] = array_map(function (array $row) use ($tab): array {
            $kid = (int)$row['kid'];
            $stock = [(float)$row['wood'], (float)$row['clay'], (float)$row['iron'], (float)$row['crop']];
            $production = [(int)$row['woodp'], (int)$row['clayp'], (int)$row['ironp'], (int)round($row['cropnet'])];
            $capacity = [(int)$row['maxstore'], (int)$row['maxstore'], (int)$row['maxstore'], (int)$row['maxcrop']];
            $out = ['kid' => $kid, 'name' => $row['name'], 'production' => $production, 'capacity' => $capacity];
            if ($tab === 1) {
                $percent = [];
                foreach ($stock as $i => $value) {
                    $percent[$i] = $capacity[$i] > 0 ? (int)floor(min(max($value, 0), $capacity[$i]) / $capacity[$i] * 100) : 0;
                }
                $out['percent'] = $percent;
                $out['warehouseFull'] = $this->timeToFull($stock, $production, $capacity, [0, 1, 2]);
                $out['granaryFull'] = $this->timeToFull($stock, $production, $capacity, [3]);
            }

            return $out;
        }, $rows);
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * Seconds until the first of the given resources fills its store, or null when none will.
     *
     * @param list<float> $stock
     * @param list<int> $production
     * @param list<int> $capacity
     * @param list<int> $types
     */
    private function timeToFull(array $stock, array $production, array $capacity, array $types): ?int
    {
        $best = null;
        foreach ($types as $i) {
            if ($production[$i] <= 0 || $stock[$i] >= $capacity[$i]) {
                continue;
            }
            $seconds = (int)ceil(($capacity[$i] - $stock[$i]) / $production[$i] * 3600);
            $best = $best === null ? $seconds : min($best, $seconds);
        }

        return $best;
    }

    private function showHospital(): string
    {
        $race = (int)$this->session->getRace();
        $building = \Game\Wounded::buildingFor($race);
        $queues = (new VillageOverviewModel())->getPlayerTrainingQueues($this->session->getPlayerId());
        $units = [];
        for ($nr = 1; $nr <= 6; $nr++) {
            $unitId = nrToUnitId($nr, $race);
            $units[$nr] = ['id' => $unitId, 'title' => htmlspecialchars((string)T('Troops', "$unitId.title"), ENT_QUOTES, 'UTF-8')];
        }
        $rows = [];
        foreach ($this->getPlayerVillages() as $row) {
            $rows[] = [
                'kid' => (int)$row['kid'],
                'name' => $row['name'],
                'healing' => $queues[(int)$row['kid']][$building] ?? 0,
                'wounded' => \Game\Wounded::all((int)$row['kid']),
            ];
        }
        $view = new PHPBatchView('village-statistics/hospital');
        $view->vars += ['race' => $race, 'building' => $building, 'units' => $units, 'rows' => $rows, 'selectedKid' => (int)$this->session->getKid()];

        return $view->output();
    }

    private function showTrainingQueues(): string
    {
        $race = (int)$this->session->getRace();
        $queues = (new VillageOverviewModel())->getPlayerTrainingQueues($this->session->getPlayerId());
        $rows = [];
        foreach ($this->getPlayerVillages() as $row) {
            $rows[] = ['kid' => (int)$row['kid'], 'name' => $row['name'], 'queue' => $queues[(int)$row['kid']] ?? []];
        }
        $view = new PHPBatchView('village-statistics/training');
        $view->vars += [
            'race' => $race,
            'buildings' => [19, 20, 21, \Game\Wounded::buildingFor($race)],
            'rows' => $rows,
            'selectedKid' => (int)$this->session->getKid(),
        ];

        return $view->output();
    }

    private function showResources(): void
    {
        $subTab = $this->resourceSubTab();
        if ($subTab !== 0) {
            $this->showResourceEconomy($subTab);

            return;
        }
        $m = new VillageOverviewModel();
        $result = $this->getPlayerVillages();
        foreach ($result as $row) {
            ResourcesHelper::updateVillageResources($row['kid']);
        }
        $view = new PHPBatchView("village-statistics/resources");
        $view->vars['subTabs'] = $this->resourceTabsHtml(0);
        $content = &$view->vars['content'];
        $result = $m->getPlayerVillageResources($this->session->getPlayerId());
        $summary = ['r1' => 0, 'r2' => 0, 'r3' => 0, 'r4' => 0, 'merchants_available' => 0, 'merchants_total' => 0,];
        while ($row = $result->fetch_assoc()) {
            $row['wood'] = floor($row['wood']);
            $row['clay'] = floor($row['clay']);
            $row['iron'] = floor($row['iron']);
            $row['crop'] = floor(max($row['crop'], 0));
            $content .= '<tr class="' . ($row['kid'] == $this->session->getKid() ? 'hl' : 'hover') . '">';
            $content .= '<td class="vil fc"><a href="/dorf1.php?newdid=' . $row['kid'] . '">' . $row['name'] . '</a></td>';
            $content .= '<td class="lum">&#x202d;' . number_format($row['wood'], 0, ',', ',') . '&#x202c;</td>';
            $content .= '<td class="clay">&#x202d;' . number_format($row['clay'], 0, ',', ',') . '&#x202c;</td>';
            $content .= '<td class="iron">&#x202d;' . number_format($row['iron'], 0, ',', ',') . '&#x202c;</td>';
            $content .= '<td class="crop">&#x202d;' . number_format($row['crop'], 0, ',', ',') . '&#x202c;</td>';
            $summary['r1'] += $row['wood'];
            $summary['r2'] += $row['clay'];
            $summary['r3'] += $row['iron'];
            $summary['r4'] += $row['crop'];
            $market = new MarketModel();
            $mo = $market->getMarketAndTradeOfficeLevel($row['kid']);
            $session = Session::getInstance();
            $alliance_bonus = 1;
            if ($session->getAllianceId() > 0) {
                $alliance_bonus = AllianceBonus::getTradersBonus(
                    $session->getAllianceId(),
                    $session->getAllianceJoinTime()
                );
            }
            $merchant_cap = Formulas::merchantCAP($this->session->getRace(), $mo[28], $alliance_bonus);
            $totalMerchants = $mo[17];
            $available = ($totalMerchants - $market->getOnTheWayMerchantsCount(
                Village::getInstance()->getKid(),
                    $merchant_cap
            ) - $market->getOfferingMerchantsCount(
                        Village::getInstance()->getKid(),
                    $merchant_cap
                    ));
            $summary['merchants_available'] += $available;
            $summary['merchants_total'] += $totalMerchants;
            $content .= '<td class="tra lc"><a href="/build.php?newdid=' . $row['kid'] . '&gid=17">&#x202d;&#x202d;' . $available . '&#x202c;/&#x202d;' . $totalMerchants . '&#x202c;&#x202c;</a></td>';
            $content .= '</tr>';
        }
        $summary['r1'] = number_format($summary['r1'], 0, ',', ',');
        $summary['r2'] = number_format($summary['r2'], 0, ',', ',');
        $summary['r3'] = number_format($summary['r3'], 0, ',', ',');
        $summary['r4'] = number_format($summary['r4'], 0, ',', ',');
        $view->vars['summary'] = $summary;
        $this->view->vars['content'] .= $view->output();
    }

    private function showCulturePoints(): void
    {
        $m = new VillageOverviewModel();
        $view = new PHPBatchView("village-statistics/culturePoints");
        $content = &$view->vars['content'];
        $result = $m->getPlayerVillagesCulturePointsData($this->session->getPlayerId());
        $summary = [
            'totalCP'              => 0,
            'units'                => ['chiefs' => 0, 'settlers' => 0],
            'slots'                => ['filled' => 0, 'total' => 0],
            'totalCelebrationTime' => [0],
        ];

        $cache = Caching::getInstance();

        $TrainingHelper = new TrainingHelper();
        while ($row = $result->fetch_assoc()) {
            if(!($data = $cache->get("dorf3:showCulturePoints:{$row['kid']}"))){
                $data = [];
                $data['th_lvl'] = $m->getTHLevel($row['kid']);
                $data['units'] = $TrainingHelper->getExpansionUnits($row['kid']);
                $data['slots'] = $TrainingHelper->getSlots($row['kid']);
                $cache->set("dorf3:showCulturePoints:{$row['kid']}", $data, 600);
            }
            $summary['slots']['total'] += $data['slots']['maxSlots'];
            $summary['slots']['filled'] += $data['slots']['filledSlots'];

            $summary['units']['settlers'] += $data['units']['settlers'];
            $summary['units']['chiefs'] += $data['units']['chiefs'];

            if ($data['th_lvl'] > 0 && $row['celebration'] >= time()) {
                $summary['totalCelebrationTime'][] = $row['celebration'];
            }
            $cp = Formulas::isGrayArea($row['kid']) ? 0 : $row['cp'];
            $summary['totalCP'] += $cp;
            $content .= '<tr class="' . ($row['kid'] == $this->session->getKid() ? 'hl' : 'hover') . '">';
            $content .= '<td class="vil fc"><a href="/dorf1.php?newdid=' . $row['kid'] . '">' . $row['name'] . '</a></td>';
            $content .= '<td class="cps">' . $cp . '</td>';
            $content .= '<td class="cel">';
            $content .= $data['th_lvl'] > 0 ? '<a href="/build.php?newdid=' . $row['kid'] . '&amp;gid=24">' . ($row['celebration'] >= time() ? appendTimer($row['celebration'] - time()) : '<span class="dot">●</span>') . '</a>' : '<span class="none">-</span>';
            $content .= '</td>';
            $content .= '<td class="tro">';
            for ($i = 1; $i <= $data['units']['settlers']; ++$i) {
                $unitId = nrToUnitId(10, $this->session->getRace());
                $content .= '<img class="unit u' . $unitId . '" src="/img/spacer.gif" alt="' . T("Troops", "{$unitId}.title") . '" title="' . T("Troops", "{$unitId}.title") . '">';
            }
            for ($i = 1; $i <= $data['units']['chiefs']; ++$i) {
                $unitId = nrToUnitId(9, $this->session->getRace());
                $content .= '<img class="unit u' . $unitId . '" src="/img/spacer.gif" alt="' . T("Troops", "{$unitId}.title") . '" title="' . T("Troops", "{$unitId}.title") . '">';
            }
            if (array_sum($data['units']) == 0) {
                $content .= '<span class="">-</span>';
            }
            $content .= '</td>';

            $content .= '<td class="slo lc">&#x202d;&#x202d;' . $data['slots']['filledSlots'] . '&#x202c;/&#x202d;' . $data['slots']['maxSlots'] . '&#x202c;&#x202c;</td>';
            $content .= '</tr>';
        }
        $view->vars['summary'] = $summary;
        $view->vars['nextSlotDate'] = $this->nextSettlementSlotDate($summary['totalCP']);
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * Estimated date the next settlement slot unlocks via culture points, or
     * null when there is nothing to estimate (no CP income or already reached).
     */
    private function nextSettlementSlotDate(int $cpPerDay): ?string
    {
        if ($cpPerDay <= 0) {
            return null;
        }
        $needed = Formulas::newVillageCP(count($this->getPlayerVillages()) + 1);
        $missing = $needed - $this->session->getCP();
        if ($missing <= 0) {
            return null;
        }

        return date('d.m.y, H:i', time() + (int)ceil($missing / $cpPerDay * 86400));
    }

    private function showTroops(): void
    {
        $view = new PHPBatchView("village-statistics/troops");
        $view->vars['tooLargeTroops'] = getGameSpeed() > 500;
        $view->vars['race'] = $this->session->getRace();
        $content = &$view->vars['content'];
        $view->vars['selectedTab'] = $this->troopSubTab();
        $view->vars['subTabs'] = $this->troopTabsHtml($view->vars['selectedTab']);
        $m = new VillageOverviewModel();
        if ($view->vars['selectedTab'] == 0) {
            $summary = [];
            $view->vars['units'] = '';
            for ($i = 1; $i <= 11; ++$i) {
                if ($i == 11) {
                    $unitId = 'hero';
                } else {
                    $unitId = nrToUnitId($i, $this->session->getRace());
                }
                $title = htmlspecialchars((string)T("Troops", "$unitId.title"), ENT_QUOTES, 'UTF-8');
                $view->vars['units'] .= '<th class="unit"><img class="unit u' . $unitId . '" src="/img/spacer.gif" alt="' . $title . '"></th>';
            }
            $result = $this->getPlayerVillages();
            foreach ($result as $row) {
                $content .= '<tr class="' . ($row['kid'] == $this->session->getKid() ? 'hl' : 'hover') . '">';
                $content .= '<td class="villageName"><a href="/build.php?newdid=' . $row['kid'] . '&amp;id=39#td">' . $row['name'] . '</a></td>';
                $totalUnits = [];
                $unitsHome = $m->getUnits($row['kid']);
                for ($i = 1; $i <= 11; $i++) {
                    if (!isset($totalUnits[$i])) {
                        $totalUnits[$i] = 0;
                    }
                    if (!isset($summary[$i])) {
                        $summary[$i] = 0;
                    }
                    $totalUnits[$i] += $unitsHome['u' . $i];
                    $summary[$i] += $unitsHome['u' . $i];
                }
                $unitsOnTheWayGo = $m->getGoingUnits($row['kid']);
                while ($b = $unitsOnTheWayGo->fetch_assoc()) {
                    for ($i = 1; $i <= 11; $i++) {
                        $totalUnits[$i] += $b['u' . $i];
                        $summary[$i] += $b['u' . $i];
                    }
                }
                $unitsOnTheWayBack = $m->getReturningUnits($row['kid']);
                while ($b = $unitsOnTheWayBack->fetch_assoc()) {
                    for ($i = 1; $i <= 11; $i++) {
                        $totalUnits[$i] += $b['u' . $i];
                        $summary[$i] += $b['u' . $i];
                    }
                }
                $enforcedUnits = $m->getOutEnforcement($row['kid']);
                while ($b = $enforcedUnits->fetch_assoc()) {
                    for ($i = 1; $i <= 11; $i++) {
                        $totalUnits[$i] += $b['u' . $i];
                        $summary[$i] += $b['u' . $i];
                    }
                }
                $trappedUnits = $m->getOutTrappedUnits($row['kid']);
                while ($b = $trappedUnits->fetch_assoc()) {
                    for ($i = 1; $i <= 11; $i++) {
                        $totalUnits[$i] += $b['u' . $i];
                        $summary[$i] += $b['u' . $i];
                    }
                }
                for ($i = 1; $i <= 11; ++$i) {
                    if ($totalUnits[$i]) {
                        $content .= '<td' . $this->getStyle() . '>' . $this->number_format($totalUnits[$i]) . '</td>';
                    } else {
                        $content .= '<td class="none">0</td>';
                    }
                }
                $content .= '</tr>';
            }
            $view->vars['summary'] = '';
            $view->vars['summaryLarge'] = false;
            for ($i = 1; $i <= 11; ++$i) {
                if (!$view->vars['summaryLarge']) {
                    $view->vars['summaryLarge'] = $summary[$i] > 100000;
                }
                if ($summary[$i]) {
                    $view->vars['summary'] .= '<td>' . $this->number_format($summary[$i]) . '</td>';
                } else {
                    $view->vars['summary'] .= '<td class="none">0</td>';
                }
            }
        } elseif ($view->vars['selectedTab'] == 1) {
            $result = $this->getPlayerVillages();
            foreach ($result as $row) {
                $hdp = $this->getHDP($row['kid']);
                $effect = 1;
                if (Village::getInstance()->isWW() && ArtefactsModel::wwPlansReleased()) {
                    $effect = 1 / 2;
                }
                $effect *= ArtefactsModel::getArtifactEffectByType($this->session->getPlayerId(), $row['kid'], ArtefactsModel::ARTIFACT_DIET);
                $content .= $this->renderVillageTroops(
                    $row['kid'],
                    $this->session->getRace(),
                    array_filter_units($m->getUnits($row['kid'])),
                    $hdp,
                    $effect
                );
                $units = $m->getVillageEnforcements($row['kid']);
                while ($b = $units->fetch_assoc()) {
                    $content .= $this->renderVillageTroops(
                        $b['kid'],
                        $b['race'],
                        array_filter_units($b),
                        $hdp,
                        $effect
                    );
                }
            }
        } elseif ($view->vars['selectedTab'] == 2) {
            $armory = new PHPBatchView("village-statistics/armory");
            $armory->vars['curKid'] = $this->session->getSelectedVillageID();
            $armory->vars['villages'] = [];
            $result = $this->getPlayerVillages();
            foreach ($result as $row) {
                $armory->vars['villages'][] = [
                    'kid'                 => $row['kid'],
                    'name'                => $row['name'],
                    'research_level'      => $m->getArmory($row['kid']),
                    'research_inProgress' => $m->getArmoryProgress($row['kid']),
                ];
            }
            $content .= $armory->output();
        } elseif ($view->vars['selectedTab'] == 3) {
            $content .= $this->showHospital();
        } elseif ($view->vars['selectedTab'] == 4) {
            $content .= $this->showTrainingQueues();
        }
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * @return string|null
     */
    private function getStyle()
    {
        if (!getDisplay("smallTroopsNumFontSize")) {
            return null;
        }
        return ' style="font-size: 11px;"';
    }

    /**
     * @param mixed $kid
     * @return mixed
     */
    public function getHDP($kid)
    {
        $db = DB::getInstance();
        $buildings = $db->query("SELECT `f18t`, `f19`, `f19t`, `f20`, `f20t`, `f21`, `f21t`, `f22`, `f22t`, `f23`, `f23t`, `f24`, `f24t`, `f25`, `f25t`, `f26`, `f26t`, `f27`, `f27t`, `f28`, `f28t`, `f29`, `f29t`, `f30`, `f30t`, `f31`, `f31t`, `f32`, `f32t`, `f33`, `f33t`, `f34`, `f34t`, `f35`, `f35t`, `f36`, `f36t`, `f37`, `f37t`, `f38`, `f38t` FROM fdata WHERE kid={$kid}");
        if (!$buildings->num_rows) {
            return 0;
        }
        $buildings = $buildings->fetch_assoc();
        for ($i = 18; $i <= 38; ++$i) {
            if ($buildings['f' . $i . 't'] == 41) {
                return $buildings['f' . $i];
            }
        }
        return 0;
    }

    /**
     * @param mixed $kid
     * @param mixed $race
     * @param mixed $units
     * @param mixed $hdp
     * @param float|int $effect
     * @return mixed
     */
    public function renderVillageTroops($kid, $race, $units, $hdp, $effect)
    {
        return PHPBatchView::render(
            "village-statistics/vil_troops",
            [
                'kid'    => $kid,
                'name'   => (new VillageOverviewModel())->getVillageName($kid),
                'race'   => $race,
                'units'  => $units,
                // Collapses an entirely empty support row to eleven
                // columns; populated rows retain the labelled twelve-column
                // layout.
                'hasUnits' => (bool) array_filter(
                    $units,
                    static fn($amount): bool => (int)$amount > 0
                ),
                'upkeep' => $this->getTotalCropConsumption($race, $units, $hdp, $effect),
            ]
        );
    }

    /**
     * @param mixed $race
     * @param mixed $units
     * @param mixed $hdp
     * @param mixed $effect
     * @return (float|int)|int
     */
    public function getTotalCropConsumption($race, $units, $hdp, $effect = 1)
    {
        if ($race == 4) {
            return 0;
        }
        $total = 0;
        foreach ($units as $nr => $amount) {
            if ($nr <= 10) {
                $unitId = nrToUnitId($nr, $race);
            } else {
                $unitId = 98;
            }
            $total += $amount * Formulas::uUpkeep($unitId, $hdp);
        }

        return $total * $effect;
    }

    private function allowed(): bool
    {
        return $this->session->hasPlus() || $this->isAdmin;
    }
}
