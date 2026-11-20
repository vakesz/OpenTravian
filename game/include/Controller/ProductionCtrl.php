<?php
/**
 * Created by PhpStorm.
 * User: Amirhossein CH
 * Date: 10/4/14
 * Time: 6:03 PM
 */

namespace Controller;

use Core\Config;
use Core\Database\DB;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Game\Gid;
use Game\Hero\SessionHero;
use Game\Tribes;
use Core\Locale;
use resources\View\GameView;
use resources\View\PHPBatchView;
use Game\Slot;

class ProductionCtrl extends GameCtrl
{
    private const TAB_SLUGS = [
        1 => 'lumber',
        2 => 'clay',
        3 => 'iron',
        4 => 'crop',
        5 => 'balance',
    ];

    /** @var int */
    private $tabIndex;
    /** @var array<mixed> */
    public  $productionFields = [];
    /** @var array<mixed> */
    public  $bonusBuildings   = [5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0];
    /** @var array<mixed> */
    public  $bonusPlus        = [0, 0, 0, 0];
    /** @var array<mixed> */
    public  $oasesBonus       = ["count" => [0, 0, 0, 0], "bonus" => [0, 0, 0, 0]];
    /** @var array<mixed> */
    public  $heroProd         = [0, 0, 0, 0];
    /** @var array<mixed> */
    public  $boost            = [1 => [Gid::SAWMILL], 2 => [Gid::BRICKYARD], 3 => [Gid::IRON_FOUNDRY], 4 => [Gid::GRAIN_MILL, Gid::BAKERY],];

    /**
     * Canonical production overview tab slug for stock-bar and menu links.
     * Accepts legacy numeric tabs 1–5 when resolving inbound requests.
     */
    public static function tabSlug(int $tabIndex): string
    {
        return self::TAB_SLUGS[$tabIndex] ?? self::TAB_SLUGS[1];
    }

    public static function resolveTabIndex(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return 1;
        }

        $key = strtolower(trim((string)$raw));
        $aliases = [
            '1' => 1,
            '2' => 2,
            '3' => 3,
            '4' => 4,
            '5' => 5,
            'lumber' => 1,
            'wood' => 1,
            'clay' => 2,
            'iron' => 3,
            'crop' => 4,
            'balance' => 5,
        ];

        return $aliases[$key] ?? 1;
    }

    public function __construct()
    {
        parent::__construct();
        if (Session::getInstance()->getRace() == Tribes::EGYPTIANS) {
            $this->bonusBuildings[45] = 0;
            $this->boost = [1 => [Gid::SAWMILL, Gid::WATERWORKS], 2 => [Gid::BRICKYARD, Gid::WATERWORKS], 3 => [Gid::IRON_FOUNDRY, Gid::WATERWORKS], 4 => [Gid::GRAIN_MILL, Gid::BAKERY, Gid::WATERWORKS]];
        }
        $this->tabIndex = self::resolveTabIndex($_GET['t'] ?? null);
        $this->view = new GameView();
        // The `<h1 class="titleInHeader">` lives inside #productionOverview (production/menu).
        $this->view->vars['titleInHeader'] = '';
        // Reference body token is `production` (resource perspective is implied by the overview).
        $this->view->vars['bodyCssClass'] = 'production';
        $this->view->vars['contentCssClass'] = 'production';
		$this->view->vars['showHeaderBar'] = TRUE;
		$this->view->vars['showCloseButton'] = TRUE;
		
		
        $view = new PHPBatchView("production/menu");
        $view->vars['tabIndex'] = $this->tabIndex;
        $view->vars['tabSlugs'] = self::TAB_SLUGS;
        $this->view->vars['content'] .= $view->output();
        if ($this->tabIndex < 5) {
            $this->production();
            $view = new PHPBatchView("production/res");
            $view->vars['tabIndex'] = $this->tabIndex;
            $view->vars['boost'] = $this->boost;
            $view->vars['bonusBuilding'] = $this->bonusBuildings;
            $view->vars['oasesBonus'] = $this->oasesBonus;
            $view->vars['productionFields'] = $this->productionFields;
            $view->vars['bonusPlus'] = $this->bonusPlus;
            $view->vars['heroProd'] = $this->heroProd;
            $this->view->vars['content'] .= '<div>' . $view->output() . '</div>';
        } else {
            $this->tabIndex = 4;
            $this->production();
            $this->balance();
        }
        // Closes the wrappers production/menu opened: inner div, #productionOverview.
        $this->view->vars['content'] .= '</div></div>';
    }

    private function balance(): void
    {
        $view = new PHPBatchView("production/balance");
        $view->vars['tabIndex'] = 4;
        $view->vars['boost'] = $this->boost;
        $view->vars['bonusBuilding'] = $this->bonusBuildings;
        $view->vars['oasesBonus'] = $this->oasesBonus;
        $view->vars['productionFields'] = $this->productionFields;
        $view->vars['bonusPlus'] = $this->bonusPlus;
        $view->vars['heroProd'] = $this->heroProd;
        $this->view->vars['content'] .= $view->output();
    }

    private function production(): void
    {
        $db = DB::getInstance();
        $village = Village::getInstance();
        for ($i = Slot::FIRST_FIELD; $i <= Slot::LAST_BUILDING; ++$i) {

            $field = $village->getField($i);

            if (!$field['level'] && $i > Slot::LAST_FIELD) {
                continue;
            }
            if ($i <= Slot::LAST_FIELD && $field['item_id'] == $this->tabIndex) {
                $this->productionFields[] = [
                    "item_id" => $field['item_id'],
                    "level" => $field['level'],
                    "value" => Formulas::fieldProduction($field['level']),
                ];
            } else if (($field['item_id'] <= Gid::BAKERY || $field['item_id'] == Gid::WATERWORKS) && in_array($field['item_id'], $this->boost[$this->tabIndex])) {
                $this->bonusBuildings[$field['item_id']] = $field['level'];
            }
        }
        if (Session::getInstance()->hasProductionBoost(1)) {
            $this->bonusPlus[0] += 25;
        }
        if (Session::getInstance()->hasProductionBoost(2)) {
            $this->bonusPlus[1] += 25;
        }
        if (Session::getInstance()->hasProductionBoost(3)) {
            $this->bonusPlus[2] += 25;
        }
        if (Session::getInstance()->hasProductionBoost(4)) {
            $this->bonusPlus[3] += 25;
        }
        $this->oasesBonus = \Game\ResourcesHelper::oasisBonus($village->getKid());
        $hero = $this->session->hero;
        if ($hero->hero['kid'] == Village::getInstance()->getKid()) {
            // Hero allocation is unavailable while dead, whereas the fixed
            // six crop per hour is still produced by a home hero.
            $this->heroProd[3] += 6;
            if ($hero->getHeroHealth() > 0) {
                switch ($hero->hero['productionType']) {
                    case 0:
                        for ($i = 0; $i <= 3; ++$i) {
                            $this->heroProd[$i] += ($hero->hero['production'] * Formulas::heroProductionPerPoint((int)Session::getInstance()->getRace(), false));
                        }
                        break;
                    default:
                        $this->heroProd[$hero->hero['productionType'] - 1] += $hero->hero['production'] * Formulas::heroProductionPerPoint((int)Session::getInstance()->getRace(), true);
                        break;
                }
            }
        }
    }
}
