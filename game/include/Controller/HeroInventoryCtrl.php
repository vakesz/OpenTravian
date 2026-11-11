<?php

namespace Controller;

use Core\Config;
use Core\Database\DB;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Game\GoldHelper;
use Game\Hero\HeroHelper;
use Game\Hero\SessionHero;
use Model\AuctionModel;
use Model\HeroInventoryModel;
use Model\HeroResourceInventory;
use resources\View\GameView;
use resources\View\PHPBatchView;

use function number_format_x;

use const JSON_PRETTY_PRINT;

class HeroInventoryCtrl extends GameCtrl
{
    public function __construct()
    {
        parent::__construct();
        $this->applyAppearanceUpdates();
        $this->view = new GameView();
        $this->view->vars['titleInHeader'] = '';
        $this->view->vars['containerCssClass'] = 'contentPage heroPageShell';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        $this->view->vars['titleInHeader'] = Session::getInstance()->getName() . ' - ' . T("HeroInventory", "level") . ' ';
        $hero = $this->session->hero;
        $this->view->vars['titleInHeader'] .= number_format_x(Formulas::heroLevel($hero->getHeroExp()));
        $config = Config::getInstance();
        $isReviveRequest = WebService::isPost()
            && isset($_POST['a'])
            && $_POST['a'] == 1
            && $hero->getHeroStatus() == SessionHero::STATUS_DEAD;
        if ($isReviveRequest && Session::getInstance()->banned()) {
            $this->innerRedirect("InGameBannedPage");
        }
        if ($isReviveRequest && Session::claimRequest()) {
            //revive Hero
            $costs = Formulas::heroRegenerateCost($hero->getLevel(), Session::getInstance()->getRace());
            if ($config->dynamic->serverFinished) {
                $this->innerRedirect("InGameWinnerPage");
            } elseif (Village::getInstance()->isResourcesAvailable($costs)) {
                $village = Village::getInstance();
                $village->transaction(static function () use ($village, $hero, $costs): bool {
                    if (!$village->spend($costs)) {
                        return false;
                    }
                    $hero->trainHero();

                    return true;
                });
            }
        }
        $heroView = isset($_GET['view']) && $_GET['view'] === 'attributes' ? 'attributes' : 'inventory';
        // Inventory/Appearance → body.hero_editor; Attributes → body.hero_inventory.
        $this->view->vars['bodyCssClass'] = $heroView === 'attributes'
            ? 'hero hero_inventory'
            : 'hero hero_editor';
        // The V2 hero navigation owns its favourite action. Keeping a second
        // copy in the legacy title chrome produces duplicate IDs and places
        // the control differently from the current client.
        // Shows the contextual help control on Attributes only; here it opens the manual overview.
        if ($heroView === 'attributes') {
            $this->view->vars['contextualHelpBuildingId'] = -1;
        }
        $this->view->vars['contextualHelpRestartableGroup'] = 'Hero resources in inventory - Hero page v2';
        $this->view->vars['contentCssClass'] = $heroView === 'attributes'
            ? 'heroV2 heroV2Attributes'
            : 'heroV2 heroV2Inventory';
        $navigation = new PHPBatchView('hero/navigation');
        $navigation->vars['selectedTab'] = $heroView === 'attributes' ? 2 : 1;
        $view = new PHPBatchView('hero/hero_inventory');
        $view->vars['heroView'] = $heroView;
        $db = DB::getInstance();
        $uid = Session::getInstance()->getPlayerId();
        $claimStatus = $this->claimHeroResources((int)$uid);
        if ($claimStatus !== null) {
            $this->redirect('/hero/inventory?resources=' . $claimStatus);
        }
        $heroResourceInventory = new HeroResourceInventory();
        try {
            $heroResourceInventory->syncInventoryItems((int)$uid);
        } catch (\Throwable $exception) {
            logError('Hero resource inventory sync failed for uid %d: %s', [(int)$uid, $exception->getMessage()]);
        }
        $heroResources = $heroResourceInventory->get((int)$uid);
        $inventory = $db->query("SELECT * FROM inventory WHERE uid=" . $uid)->fetch_assoc();
        $face = $db->query("SELECT * FROM face WHERE uid=" . $uid)->fetch_assoc();
        $horseType = (int)$db->fetchScalar(
            'SELECT type FROM items WHERE id=' . (int)$inventory['horse'] . ' AND uid=' . (int)$uid
        );

        $m = new AuctionModel();
        $helper = new HeroHelper();
        $tribe = (int)Session::getInstance()->getRace();
        $view->vars['hero'] = array_merge(
            $hero->hero,
            [
                'health' => $hero->getHeroHealth(),
                'expPercent' => $hero->getHeroExpPercent(),
                "gender" => $face['gender'],
                "beard" => $face['beard'],
                "headProfile" => $face['headProfile'],
                "hairColor" => $face['hairColor'],
                "hairStyle" => $face['hairStyle'],
                "ears" => $face['ears'],
                "eyebrow" => $face['eyebrow'],
                "eyes" => $face['eyes'],
                "nose" => $face['nose'],
                "mouth" => $face['mouth'],
                "leftHand" => $inventory['leftHand'],
                "rightHand" => $inventory['rightHand'],
                "shoes" => $inventory['shoes'],
                "body" => $inventory['body'],
                "points" => $hero->getAvailablePoints(),
                "heroStatusMessage" => $hero->getInventoryStatus(),
                "heroStatusIcon" => match ($hero->getHeroStatus()) {
                    SessionHero::STATUS_HOME => 'statusHome_medium',
                    SessionHero::STATUS_DEAD => 'statusDead_medium',
                    SessionHero::STATUS_REVIVING => 'statusRevive_medium',
                    SessionHero::STATUS_TRAPPED => 'statusCaptured_medium',
                    SessionHero::STATUS_IN_ENFORCEMENT => 'statusSupport_medium',
                    default => 'statusRunning_medium',
                },
                "vname" => $hero->hero['kid'] == Village::getInstance()->getKid() ? Village::getInstance()->getName() : $db->fetchScalar("SELECT name FROM vdata WHERE kid={$hero->hero['kid']}"),
                "allResourcesProduct" => $hero->hero['production'] * Formulas::heroProductionPerPoint($tribe, false),
                "eachResourceProduct" => $hero->hero['production'] * Formulas::heroProductionPerPoint($tribe, true),
                "totalPower" => $helper->calcTotalPower(
                    Session::getInstance()->getRace(),
                    $hero->hero['power'],
                    $inventory['rightHand'],
                    $inventory['leftHand'],
                    $inventory['body']
                ),
                "status" => (string)$hero->getHeroStatus(),
                "horse" => $inventory['horse'],
                "horseType" => $horseType,
                "helmet" => $inventory['helmet'],
                "regenerating" => $hero->getHeroStatus() == SessionHero::STATUS_REVIVING,
                "regeneratedHealth" => $hero->getRegeneratedHealth(),
                "reviveEndTime" => $hero->getReviveEndTime(),
                "fsperpoint" => Session::getInstance()->getRace() == 1 ? 100 : 80,
                "itemfs" => $helper->calcItemPower($inventory['rightHand'], $inventory['leftHand'], $inventory['body']),
                "speed" => $helper->calcTotalSpeed(
                    Session::getInstance()->getRace(),
                    $inventory['horse'],
                    $inventory['shoes']
                ),
                'r0' => $hero->hero['productionType'] == 0,
                'r1' => $hero->hero['productionType'] == 1,
                'r2' => $hero->hero['productionType'] == 2,
                'r3' => $hero->hero['productionType'] == 3,
                'r4' => $hero->hero['productionType'] == 4,
            ]
        );
        $view->vars['session'] = [
            "checker" => Session::getInstance()->getChecker(),
            "cp" => Session::getInstance()->getCP(),
        ];
        $view->vars['inventorySize'] = $m->getVisibleInventorySize($uid);
        $view->vars['heroResources'] = $heroResources;
        $view->vars['heroResourcesTotal'] = array_sum($heroResources);
        $view->vars['resourceClaimStatus'] = in_array($_GET['resources'] ?? '', ['collected', 'empty', 'unavailable'], true)
            ? $_GET['resources']
            : '';
        if ($hero->getHeroStatus() == SessionHero::STATUS_DEAD) {
            $helper = new GoldHelper();
            $costs = Formulas::heroRegenerateCost($hero->getLevel(), Session::getInstance()->getRace());
            $contract = Village::getInstance()->contractResourcesLink($costs);
            $village = Village::getInstance();
            $villageName = $hero->hero['kid'] == $village->getKid() ? $village->getName() : $db->fetchScalar("SELECT name FROM vdata WHERE kid={$hero->hero['kid']}");
            $npcButton = $helper->getExchangeResourcesButtonByCost($costs);
            if (!Village::getInstance()->isResourcesAvailable($costs)) {
                $npcButton .= Village::getInstance()->calcWhenResourcesAreAvailable($costs, true);
            }
            $view->vars['heroRevive'] = [
                "neededTime" => Formulas::heroRegenerationTime($hero->getLevel()),
                "costs" => $costs,
                "npc" => $npcButton,
                "showButton" => $contract['code'] == 0,
                'description' => sprintf(
                    T("HeroInventory", "HeroReviveInVillageDescription"),
                    $hero->hero['kid'],
                    $villageName,
                    $village->getKid(),
                    $village->getName()
                ),
            ];
        }
        $view->vars['heroTips'] = [
            'health' => $hero->getHealthTitle($inventory),
            'experience' => $hero->getExperienceTitle($inventory),
            'speed' => $hero->getSpeedTitle($inventory),
            'production' => $hero->getHeroProductionTitle(),
            'power' => $hero->getHeroFightingStrengthTitle($inventory),
            'offBonus' => $hero->getHeroOffBonusTitle(),
            'defBonus' => $hero->getHeroDefBonusTitle(),
            'productionPoints' => $hero->getHeroProductBonusTitle(),
        ];
        // The legacy inventory widget renders equipped items from the same
        // collection as bag items. Keep the separate equipment payload for
        // attribute bonuses, but include both sets in the draggable item list.
        $heroEquipments = $hero->getHeroEquipments();
        $heroItems = array_merge($hero->getHeroItems(), $heroEquipments);
        $view->vars['HeroItems'] = json_encode($heroItems, JSON_PRETTY_PRINT);
        $view->vars['HeroEquipments'] = json_encode($heroEquipments, JSON_PRETTY_PRINT);
        // The reference Hero V2 shell owns both the tab navigation and the
        // selected view.  Keeping the navigation outside of this padded
        // container made local tabs twenty pixels wider than the page body.
        $this->view->vars['content'] .= '<div id="heroV2" class="contentV2">'
            . $navigation->output()
            . $view->output()
            . '</div>';
    }

    private function claimHeroResources(int $uid): ?string
    {
        if (!WebService::isPost() || ($_POST['claimHeroResources'] ?? null) !== '1') {
            return null;
        }
        if ($this->session->isSitter() || $this->session->banned() || !Session::claimRequest()) {
            return 'unavailable';
        }

        try {
            $resources = (new HeroResourceInventory())->claim($uid, Village::getInstance()->getKid());
            return array_sum($resources) > 0 ? 'collected' : 'empty';
        } catch (\Throwable $exception) {
            logError('Hero resource collection failed for uid %d: %s', [$uid, $exception->getMessage()]);
            return 'unavailable';
        }
    }

    private function applyAppearanceUpdates(): void
    {
        $updates = HeroInventoryUpdate::fromPost($_POST);
        if ($updates['inventory'] === null && $updates['face'] === null) {
            return;
        }
        if (!WebService::isPost() || !Session::claimRequest()) {
            return;
        }
        if ($this->session->isSitter() || $this->session->banned()) {
            return;
        }

        $db = DB::getInstance();
        $uid = Session::getInstance()->getPlayerId();

        if ($updates['inventory'] !== null) {
            try {
                (new HeroInventoryModel())->applyLegacyEquipment($uid, $updates['inventory']);
            } catch (\DomainException $exception) {
                // Equipment changes are exclusively handled by the inventory API.
                // Keep face updates below independent of a stale legacy form post.
            }
        }

        if ($updates['face'] !== null) {
            $face = $updates['face'];
            $db->run(
                'UPDATE face SET beard=?, headProfile=?, hairColor=?, hairStyle=?, ears=?, eyebrow=?, eyes=?, nose=?, mouth=?, gender=? WHERE uid=?',
                [
                    $face['beard'],
                    $face['headProfile'],
                    $face['hairColor'],
                    $face['hairStyle'],
                    $face['ears'],
                    $face['eyebrow'],
                    $face['eyes'],
                    $face['nose'],
                    $face['mouth'],
                    $face['gender'],
                    $uid,
                ]
            );
        }
    }
}
