<?php

namespace Controller;

use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Game\ExtraModules;
use Game\Formulas;
use Game\Hero\AdventurePlace;
use Game\Hero\AdventureTravel;
use Game\Hero\SessionHero;
use Model\AdventureModel;
use Model\MovementsModel;
use Model\Quest;
use resources\View\GameView;
use resources\View\PHPBatchView;

use function appendTimer;

class HeroAdventureCtrl extends GameCtrl
{
    public function __construct()
    {

        parent::__construct();
        $quest = Quest::getInstance();
        $successAdventures = $this->session->get("success_adventures_count");
        if ($successAdventures > 0) {
            if ($successAdventures >= 2) {
                $quest->setQuestBitwise("battle", 1, Quest::QUEST_FINISHED);
            }
        }
        $this->view = new GameView();
        $this->view->vars['titleInHeader'] = T('HeroAdventure', 'Adventures', 'Adventures');
        $this->view->vars['bodyCssClass'] = 'heroAdventure';
        $this->view->vars['containerCssClass'] = 'contentPage heroPageShell';
        $this->view->vars['contentCssClass'] = 'heroAdventure heroAdventureAdventures';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        $this->view->vars['contextualHelpBuildingId'] = -1;
        $view = new PHPBatchView("hero/HeroAdventure");



        $status = $this->session->hero->getHeroStatus();
        $view->vars['status'] = (string)$status;
        $view->vars['heroStatusIcon'] = match ($status) {
            SessionHero::STATUS_HOME => 'statusHome_medium',
            SessionHero::STATUS_DEAD => 'statusDead_medium',
            SessionHero::STATUS_REVIVING => 'statusRevive_medium',
            SessionHero::STATUS_TRAPPED => 'statusCaptured_medium',
            SessionHero::STATUS_IN_ENFORCEMENT => 'statusSupport_medium',
            default => 'statusRunning_medium',
        };
        $view->vars['heroStatusMessage'] = $this->travelStatusMessage($status)
            ?? ($this->session->hero->getInventoryStatus() ?: $this->session->hero->getLongStatus());
        // A hero that is away cannot start another adventure: the reference keeps the list and dims its buttons.
        $view->vars['extraButton'] = ExtraModules::showButton('buyAdventure');
        $m = new AdventureModel();
        $view->vars['noRallyPoint'] = $m->getHeroVillageRallyPoint($this->session->hero->hero['kid']) <= 0;
        $view->vars['tbody'] = '';
        $db = DB::getInstance();

        $adventures = $m->getAdventures($this->session->getPlayerId());

        while ($row = $adventures->fetch_assoc()) {

            $view->vars['tbody'] .= $this->renderAdventure($row, $status !== SessionHero::STATUS_HOME);
        }
        $view->vars['adventureCount'] = $adventures->num_rows;

        if (!$adventures->num_rows) {
            $view->vars['tbody'] = '<tr><td colspan="5" class="noAdventures">' . T("HeroAdventure", "no adventures") . '</td></tr>';
        }
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * The state line of a hero that is on an adventure or on its way back from one, as the reference words it: the
     * status, then when the hero arrives. Null for every other state.
     */
    private function travelStatusMessage(int|string $status): ?string
    {
        $hero = $this->session->hero;
        $heroKid = (int)$hero->hero['kid'];
        $db = DB::getInstance();
        if ($status === SessionHero::STATUS_ON_ADVENTURE) {
            $movement = $db->query(
                'SELECT end_time FROM movement WHERE u11>0 AND mode=0 AND kid=' . $heroKid
                . ' AND attack_type=' . (int)MovementsModel::ATTACKTYPE_ADVENTURE . ' LIMIT 1'
            )->fetch_assoc();
            $text = (string)T('HeroAdventure', 'status50_generic');
        } elseif ($status === SessionHero::STATUS_IN_RETURN) {
            $movement = $db->query(
                'SELECT to_kid, end_time FROM movement WHERE u11>0 AND mode=1 AND to_kid=' . $heroKid . ' LIMIT 1'
            )->fetch_assoc();
            $name = htmlspecialchars((string)$db->fetchScalar('SELECT name FROM vdata WHERE kid=' . $heroKid . ' LIMIT 1', [], ''), ENT_QUOTES, 'UTF-8');
            $text = sprintf((string)T('HeroAdventure', 'status9'), '<a href="/karte.php?d=' . $heroKid . '">' . $name . '</a>');
        } else {
            return null;
        }
        if (!$movement) {
            return null;
        }
        $arrival = (int)ceil(((int)$movement['end_time']) / 1000);
        $when = str_replace(
            ['[ARRIVAL]', '[TIME]'],
            [appendTimer(max(0, $arrival - time())), TimezoneHelper::date('H:i', $arrival)],
            (string)T('HeroAdventure', 'heroArrival')
        );

        return '<span>' . $text . '</span> <span>' . $when . '</span>';
    }

    /**
     * @param array<string, float|int|string|null> $row
     */
    private function renderAdventure($row, bool $away = false): string
    {
        $db = DB::getInstance();
        $need = AdventureTravel::seconds((int)$row['kid']);
        $wdata = $db->query(
            "SELECT w.fieldtype, w.oasistype, w.landscape, w.occupied, u.race AS villageRace "
            . "FROM wdata w "
            . "LEFT JOIN vdata v ON v.kid=w.id AND w.fieldtype>0 "
            . "LEFT JOIN users u ON u.id=v.owner "
            . "WHERE w.id=" . (int)$row['kid']
        )->fetch_assoc();
        $worldData = $wdata ?: [];
        $villageRace = isset($worldData['villageRace']) ? (int)$worldData['villageRace'] : null;
        $place = AdventurePlace::fromWorldData($worldData, $villageRace);
        $placeClass = AdventurePlace::listImageClass($place);
        [$placeNameKey, $placeNameFallback] = match ($place) {
            'forest' => ['wald', 'Forest'],
            'clay' => ['clay', 'Clay'],
            'hill' => ['hill', 'Mountain'],
            'lake' => ['lake', 'Lake'],
            'vulcano' => ['vulcano', 'Volcano'],
            'village-normal' => ['village', 'Village'],
            'village-natars' => ['natars', 'Natarian village'],
            default => ['grassland', 'Plains'],
        };
        $placeName = T('HeroAdventure', $placeNameKey, $placeNameFallback);
        $HTML = '<tr id="adventure' . $row['id'] . '">';
        $HTML .= '<td class="place">';
        $HTML .= '<img class="' . $placeClass . '" alt="' . htmlspecialchars($placeName, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($placeName, ENT_QUOTES, 'UTF-8') . '" src="/img/spacer.gif">';
        $HTML .= '</td>';
        $distance = Formulas::getDistance($this->session->hero->hero['kid'], $row['kid']);
        // Isolates the number (LRO..PDF) and drops trailing zeros ("5.1 fields").
        $HTML .= '<td class="distance">' . "\u{202D}" . rtrim(rtrim(number_format($distance, 2, '.', ''), '0'), '.') . "\u{202C}" . ' ' . T('map', 'fields') . '</td>';
        $HTML .= '<td class="duration" id="walktime' . $row['kid'] . '">';
        $hours = (int)floor($need / 3600);
        $minutes = (int)floor($need % 3600 / 60);
        $seconds = (int)floor($need % 60);
        $duration = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        $HTML .= '<div class="cellWrapper"><div class="iconWrapper"><i class="clock_medium" aria-hidden="true"></i></div><div class="duration">' . $duration . '</div></div>';
        $HTML .= '</td>';
        $HTML .= '<td class="difficulty">';
        $difficulty = (int)$row['dif'] === 1 ? 'hard' : 'normal';
        $HTML .= '<div class="iconWrapper"><i class="difficulty_' . $difficulty . '" title="' . htmlspecialchars((string) T('HeroAdventure', $difficulty), ENT_QUOTES, 'UTF-8') . '"></i></div>';
        $HTML .= '</td>';
        $HTML .= '<td class="button" id="goToAdventure' . $row['kid'] . '">';
        $HTML .= '<input type="hidden" name="adventureKid[]" value="' . (int)$row['kid'] . '">';
        $HTML .= '<input type="hidden" name="adventureWalktimeOriginalVillage[]" value="' . (int)$row['kid'] . '-' . (int)$need . '">';
        $HTML .= '<button type="button" class="textButtonV2 buttonFramed rectangle withText green' . ($away ? ' disabled' : '') . '"><div><div>' . T("HeroAdventure", "gotoAdventure") . '</div></div></button>';

        $HTML .= '</td>';
        $HTML .= '</tr>';
        return $HTML;
    }
}
