<?php

namespace Controller;

use Core\Config;
use Core\Dispatcher;
use Core\Helper\VillagePerspective;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\BuildRequest;
use Game\Formulas;
use Model\ResourceFieldsModel;
use PDO;
use resources\View\GameView;
use resources\View\PHPBatchView;

use function getDifMilisecondsToSeconds;

class ResourceFieldsCtrl extends GameCtrl
{
    /** Stands for "Attack" or "Attacks", chosen by the number of movements. */
    private const ATTACK_LABEL = 'attackLabel';

    /** @var \resources\View\PHPBatchView */
    private $dorf1View;

    public function __construct()
    {
        parent::__construct();
        if (WebService::isPost()) {
            if (!isset($_POST['reCaptchaVerify'])) {
                $this->innerRedirect("LoginCtrl");
            }
        }
        if ($this->session->hasPublicMsg() && !$this->session->isAdminInAnotherAccount()) {
            if (isset($_GET['ok'])) {
                $this->session->updatePublicMsg(0);
            } else {
                $this->innerRedirect("PublicMsgCtrl");
            }
        }

        $this->view = new GameView();
        $this->view->vars['bodyCssClass'] = 'perspectiveResources village1';
        $this->view->vars['contentCssClass'] = 'village1';
        $this->view->vars['containerCssClass'] = null;
        if ($this->session->getPlayerId() <= 0) {
            return;
        }
        VillagePerspective::rememberResourceView();
        if (!$this->proc()) {
            return;
        }
        $m = new ResourceFieldsModel();
        $this->dorf1View = new PHPBatchView("resource-fields/main");
        $this->areaMapsAction();
        $this->unitsAction($m);
        $this->movementsAction($m);
        $session = $this->session;
        $this->dorf1View->vars['onLoadBuildings'] = Dispatcher::getInstance()->dispatch(
            "onLoadBuildingsDorfCtrl",
            false
        );
        $this->dorf1View->vars['production'] = $this->session->village->getProduction();
        $productionBoost = [
            $session->hasProductionBoost(1),
            $session->hasProductionBoost(2),
            $session->hasProductionBoost(3),
            $session->hasProductionBoost(4),
        ];
        $this->dorf1View->vars['productionBoost'] = $productionBoost;
        $this->dorf1View->vars['race'] = $this->session->getRace();
        $this->dorf1View->vars['raceName'] = $this->session->getRaceName();
        // Hide the shop opener once every resource bonus is already running.
        $this->dorf1View->vars['goldProductionBoostButton'] = '';
        if (in_array(false, $productionBoost, true)) {
            $openBoostShop = "Travian.React.openPaymentWizard({activeTab:'advantages'}); return false;";
            // The reference isolates the sign and the number like every formatted figure.
            $boostLabel = "\u{202D}+\u{202D}25\u{202C}%\u{202C}";
            $this->dorf1View->vars['goldProductionBoostButton'] = getButton(
                [
                    "type"  => "button",
                    "class" => "textButtonV1 gold productionBoostButton",
                    "title" => T("Dorf1", 'production.productionBoostButton'),
                    "onclick" => $openBoostShop,
                    "value" => $boostLabel,
                ],
                [
                    "data" => [
                        "type"  => "button",
                        "class" => "textButtonV1 gold productionBoostButton",
                        "title" => T("Dorf1", 'production.productionBoostButton'),
                        "onclick" => $openBoostShop,
                        "confirm" => '',
                        'version' => 'textButtonV1',
                        "value" => $boostLabel,
                    ],
                ],
                $boostLabel
            );
        }
        $this->view->vars['content'] = $this->dorf1View->output();
    }

    private function proc(): bool
    {
        $village = $this->session->village;
        if (BuildRequest::handle($village)) {
            return $this->finishBuildRequest();
        }
        return true;
    }

    private function finishBuildRequest(): bool
    {
        if ($this->session->banned()) {
            new BannedCtrl($this->view->vars['contentCssClass'], $this->view->vars['content']);
            return false;
        }
        if (Config::getInstance()->dynamic->serverFinished) {
            new WinnerCtrl($this->view->vars['contentCssClass'], $this->view->vars['content']);
            return false;
        }

        // Post/redirect/get: a reload or back navigation must not repeat the build action.
        WebService::redirect('/dorf1.php');

        return true;
    }

    private function areaMapsAction(): void
    {
        $village = $this->session->village;
        $this->dorf1View->vars['fieldType'] = $village->getFieldType();
        for ($i = 1; $i <= 18; ++$i) {
            $max = Formulas::buildingMaxLvl($village->getField($i)['item_id'], $village->isCapital());
            $this->dorf1View->vars['areas'][] = [
                "title" => $village->getFieldTitleAsString($i),
            ];
            if ($village->getField($i)['level'] >= $max || ($village->getField($i)['level'] + $village->getField($i)['upgrade_state']) == $max) {
                $color = 'maxLevel';
            } else {
                $item_id = $village->getField($i)['item_id'];
                $lvl = $village->getField($i)['level'] + $village->getField($i)['upgrade_state'] + 1;
                $cost = Formulas::buildingUpgradeCosts($item_id, $lvl);

                if ($village->checkArtifactDependencies($item_id) <> 0 || $village->checkDependencies($item_id, $lvl) <> 0) {
                    $color = 'dependencies';
                } elseif ($village->isWorkersBusy(true)['isBusy'] || !$village->isResourcesAvailable($cost)) {
                    $color = 'notNow';
                } else {
                    $color = 'good';
                }
            }
            $this->dorf1View->vars['maps'][] = [
                "color"        => $color,
                "item_id"      => $village->getField($i)['item_id'],
                "level"        => $village->getField($i)['level'],
                "upgradeState" => $village->getField($i)['upgrade_state'],
            ];
        }
    }

    private function unitsAction(ResourceFieldsModel $m): void
    {
        $assoc_units = [];
        $animals = [];
        $kid = $this->session->village->getKid();
        $unitsSummary = 0;
        $units = $m->getUnits($kid);
        if (!$units) {
            return;
        }
        foreach ($units['units'] as $i => $num) {
            if ($num <= 0) {
                continue;
            }
            $unitsSummary += $num;
            if (!isset($assoc_units[nrToUnitId($i, $units['race'])])) {
                $assoc_units[nrToUnitId($i, $units['race'])] = 0;
            }
            $assoc_units[nrToUnitId($i, $units['race'])] += $num;
        }
        $enf = $m->getEnforcements($kid);
        while ($row = $enf->fetch(PDO::FETCH_ASSOC)) {
            for ($i = 1; $i <= 11; ++$i) {
                if (!$row['u' . $i]) {
                    continue;
                }
                $unitsSummary += $row['u' . $i];
                if ($row['race'] == 4) {
                    if (!isset($animals[nrToUnitId($i, $row['race'])])) {
                        $animals[nrToUnitId($i, $row['race'])] = 0;
                    }
                    $animals[nrToUnitId($i, $row['race'])] += $row['u' . $i];
                    continue;
                }
                if (!isset($assoc_units[nrToUnitId($i, $row['race'])])) {
                    $assoc_units[nrToUnitId($i, $row['race'])] = 0;
                }
                $assoc_units[nrToUnitId($i, $row['race'])] += $row['u' . $i];
            }
        }
        ksort($animals, SORT_NUMERIC);
        ksort($assoc_units, SORT_NUMERIC);
        if (isset($assoc_units[98])) {
            $temp = [98 => $assoc_units[98]];
            unset($assoc_units[98]);
            $assoc_units = $temp + $assoc_units;
        }
        $assoc_units = $assoc_units + $animals;
        $this->dorf1View->vars['unitsSummary'] = $unitsSummary;
        $this->dorf1View->vars['units'] = $assoc_units;
    }

    private function movementsAction(ResourceFieldsModel $m): void
    {
        $movements = [
            "numIncomingTroops" => 0,
            "inComingContent"   => '',
            "numOutGoingTroops" => 0,
            "outGoingContent"   => '',
        ];
        $oases = $m->getOasesAsString($this->session->village->getKid());
        if (!empty($oases)) {
            $result = $m->getIncomingAttacksToMyOases($oases);
            $movements['inComingContent'] .= $this->getMovementHTML(
                $result['count'],
                self::ATTACK_LABEL,
                T("Dorf1", "movements.incomingAttacksToMe"),
                "att3",
                "a3",
                getDifMilisecondsToSeconds($result['end_time'])
            );
        }
        $kid = $this->session->village->getKid();
        $result = $m->getIncomingAttacksToMe($kid);
        $movements['inComingContent'] .= $this->getMovementHTML(
            $result['count'],
            self::ATTACK_LABEL,
            T("Dorf1", "movements.incomingAttacksToMe"),
            "att1",
            "a1",
            getDifMilisecondsToSeconds($result['end_time'])
        );
        if (!is_null($oases)) {
            $result = $m->getIncomingReinforcementToMyOases($oases);
            $movements['inComingContent'] .= $this->getMovementHTML(
                $result['count'],
                T("Dorf1", "movements.reinforcement"),
                T("Dorf1", "movements.incomingReinforcementsToMyOases"),
                "def3",
                "d3",
                getDifMilisecondsToSeconds($result['end_time'])
            );
        }
        $result = $m->getIncomingReinforcementToMe($kid);
        $movements['inComingContent'] .= $this->getMovementHTML(
            $result['count'],
            T("Dorf1", "movements.reinforcement"),
            T("Dorf1", "movements.incomingReinforcements"),
            "def1",
            "d1",
            getDifMilisecondsToSeconds($result['end_time'])
        );
        $result = $m->getOutgoingAttacks($kid);
        $movements['outGoingContent'] .= $this->getMovementHTML(
            $result['count'],
            self::ATTACK_LABEL,
            T("Dorf1", "movements.outGoingAttacks"),
            "att2",
            "a2",
            getDifMilisecondsToSeconds($result['end_time'])
        );
        $result = $m->getOutGoingReinforcements($kid);
        $movements['outGoingContent'] .= $this->getMovementHTML(
            $result['count'],
            T("Dorf1", "movements.reinforcement"),
            T("Dorf1", "movements.outGoingReinforcements"),
            "def2",
            "d2",
            getDifMilisecondsToSeconds($result['end_time'])
        );
        $result = $m->getOutgoingAdventure($kid);
        $movements['outGoingContent'] .= $this->getMovementHTML(
            $result['count'],
            T("Dorf1", "movements.adventure"),
            T("Dorf1", "movements.outGoingAdventure"),
            "hero_on_adventure",
            "adventure",
            getDifMilisecondsToSeconds($result['end_time'])
        );
        $result = $m->getOutgoingEvasion($kid);
        $movements['outGoingContent'] .= $this->getMovementHTML(
            $result['count'],
            T("Dorf1", "movements.evasion"),
            T("Dorf1", "movements.outGoingEvasion"),
            "def2",
            "d2",
            getDifMilisecondsToSeconds($result['end_time'])
        );
        $result = $m->getOutgoingSettlers($kid);
        $movements['outGoingContent'] .= $this->getMovementHTML(
            $result['count'],
            T("Dorf1", "movements.settlers"),
            T("Dorf1", "movements.settlersOnTheWay"),
            "settlersOnTheWay",
            "settle",
            getDifMilisecondsToSeconds($result['end_time'])
        );
        if (!empty($movements['outGoingContent'])) {
            $movements['numOutGoingTroops'] = 1;
        }
        if (!empty($movements['inComingContent'])) {
            $movements['numIncomingTroops'] = 1;
        }
        if (empty($movements['inComingContent']) && empty($movements['outGoingContent'])) {
            $this->dorf1View->vars['movements'] = '';
        } else {
            $this->dorf1View->vars['movements'] = PHPBatchView::render("resource-fields/movements", $movements);
        }
    }

    /**
     * @param int $count
     * @param mixed $shortTitle
     * @param mixed $typTitle
     * @param string $attackType
     * @param string $movementType
     * @param mixed $duration
     * @return bool|string
     */
    private function getMovementHTML($count, $shortTitle, $typTitle, $attackType, $movementType, $duration)
    {
        if (!$count) {
            return false;
        }
        $seconds = (int)$duration;
        $duration = secondsToString($duration);
        if ($shortTitle === self::ATTACK_LABEL) {
            $shortTitle = T('Dorf1', (int)$count === 1 ? 'movements.attack' : 'movements.attacks');
        }
        // overview filters: 1=incoming (sub 1 attacks / 2–3 reinforce),
        // 2=outgoing (sub 4 attacks / 5 reinforce / adventures share outgoing).
        [$filter, $subfilter] = match ($attackType) {
            'att1', 'att3' => [1, 1],
            'def1', 'def3' => [1, 3],
            'att2' => [2, 4],
            'def2' => [2, 5],
            'hero_on_adventure', 'settlersOnTheWay' => [2, 4],
            default => [2, 4],
        };
        $href = '/build.php?gid=16&amp;tt=1&amp;filter=' . $filter . '&amp;subfilters=' . $subfilter;
        $hour = T('Dorf1', "movements.hour");
        $in = T('Dorf1', "movements.in");
        $title = T('Dorf1', 'movementTitles.' . ($attackType === 'settlersOnTheWay' ? 'settler' : $attackType));
        return <<<HTML
<tr>
						<td class="typ">
							<a href="{$href}"><img class="{$attackType}" title="{$title}" src="/img/spacer.gif"></a>
						</td>
						<td>
							<div class="mov">
								<span class="{$movementType}">{$count} {$shortTitle}</span>
							</div>
							<div class="dur_r">
								{$in}<span class="timer" counting="down" value="{$seconds}">{$duration}</span> {$hour}.
							</div>
						</td>
					</tr>
HTML;
    }
}
