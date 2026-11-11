<?php

namespace Controller\Build;

use Controller\AnyCtrl;
use Controller\BuildCtrl;
use Core\Config;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\AllianceBonus\AllianceBonus;
use Game\ButtonPagination;
use Game\Formulas;
use Game\GoldHelper;
use Core\Locale;
use Model\MarketModel;
use Model\Quest;
use resources\View\PHPBatchView;

class MarketPlaceCtrl extends AnyCtrl
{
    /** Below this account population (or under beginner's protection) only 1:1 trades are allowed. */
    private const EVEN_TRADE_POPULATION = 200;

    /** @var mixed */
    private $building_level;
    /** @var \Controller\BuildCtrl */
    private $build;

    public function __construct(BuildCtrl $build)
    {
        parent::__construct();

        $this->build = $build;
        $this->building_level = Village::getInstance()->getField($build->selectedBuildingIndex)['level'];
        $this->view = new PHPBatchView("build/building/Marketplace");
        $tabs = [0 => 'Management', 5 => 'SendResources', 3 => 'Trade routes', 1 => 'Buy', 2 => 'Offer',];
        foreach ($tabs as $tab) {
            $this->view->vars[$tab] = get_button_id();
        }
        $this->view->vars['index'] = $build->selectedBuildingIndex;
        $this->view->vars['gid'] = (int)Village::getInstance()->getField($build->selectedBuildingIndex)['item_id'];
        $this->view->vars['level'] = $this->building_level;
        $this->view->vars['hasGoldClub'] = Session::getInstance()->hasGoldClub();
        $this->view->vars['content'] = '';
        $this->view->vars['selectedTab'] = isset($_REQUEST['t']) && in_array($_REQUEST['t'], [
            0,
            5,
            3,
            1,
            2,
        ]) ? (int)$_REQUEST['t'] : Session::getInstance()->getFavoriteTab('buildingMarket');
        $this->view->vars['favor'] = Session::getInstance()->getStoredFavoriteTab('buildingMarket');
        switch ($this->view->vars['selectedTab']) {
            case 0:
                $build->view->vars['contentCssClass'] .= ' buildMarket';
                $this->view->vars['content'] .= $build->getBuildingContract();
                $this->Management();
                break;
            case 5:
                if ($this->building_level > 0) {
                    $build->view->vars['contentCssClass'] .= ' buildMarketSendResources';
                    $this->sendResources();
                }
                break;
            case 3:
                if ($this->building_level > 0) {
                    $build->view->vars['contentCssClass'] .= ' buildMarketTradeRoutes';
                    $this->TradeRoutes();
                }
                break;
            case 1:
                if ($this->building_level > 0) {
                    $build->view->vars['contentCssClass'] .= ' buildMarketBuy';
                    $this->Buy();
                }
                break;
            case 2:
                if ($this->building_level > 0) {
                    $build->view->vars['contentCssClass'] .= ' buildMarketOffer';
                    $this->Offer();
                }
                break;
        }
        $navigationView = new PHPBatchView("build/building/MarketplaceNavigation");
        $navigationView->vars = $this->view->vars;
        $build->addContentBeforeBuildingBody($navigationView->output());
    }

    private function Buy(): void
    {
        $view = new PHPBatchView("build/building/MarketplaceBuy");
        $m = new MarketModel();
        $view->vars['total_merchants'] = $this->building_level;
        $view->vars['s'] = isset($_GET['s']) ? (int)$_GET['s'] : 0;
        $view->vars['r'] = !isset($_GET['v']) || $_GET['v'] == '1:x' ? '1:x' : '1:1';
        $view->vars['b'] = isset($_GET['b']) ? (int)$_GET['b'] : 0;
        $view->vars['al'] = !empty($_GET['al']) ? 1 : 0;
        $view->vars['index'] = $this->view->vars['index'];
        $view->vars['content'] = '';
        $view->vars['offerAccepted'] = false;
        $view->vars['error'] = '';
        $session = Session::getInstance();
        $alliance_bonus = 1;
        if ($session->getAllianceId() > 0) {
            $alliance_bonus = AllianceBonus::getTradersBonus($session->getAllianceId(), $session->getAllianceJoinTime());
        }
        $merchant_cap = max(1, (int)floor(Formulas::merchantCAP(
            Session::getInstance()->getRace(),
            max(Village::getInstance()->getTypeLevel(28)),
            $alliance_bonus
        )));
        $view->vars['merchantCapacity'] = $merchant_cap;
        $view->vars['merchantsAvailable'] = $this->building_level - $m->getOfferingMerchantsCount(Village::getInstance()->getKid(), $merchant_cap) - $m->getOnTheWayMerchantsCount(Village::getInstance()->getKid(), $merchant_cap);
        $speed = Formulas::merchantSpeed(Session::getInstance()->getRace());
        if (isset($_GET['g'])) {
            $offer = $m->getOffer((int)$_GET['g']);
            if ($offer !== false) {
                if ($offer['kid'] <> Village::getInstance()->getKid()) {
                    if ($offer['aid'] == 0 || $offer['aid'] == Session::getInstance()->getAllianceId()) {
                        $duration = Formulas::travelTimeSeconds(Formulas::getDistance($offer['kid'], Village::getInstance()->getKid()) / $speed * 3600);
                        $ratio = round($offer['needValue'] / $offer['giveValue'], 1);
                        if ($this->evenTradeReason() !== null && $offer['needValue'] != $offer['giveValue']) {
                            $view->vars['error'] = T('MarketPlace', "During beginner's protection, you can only accept 1:1 trades. The same applies if you have less than 200 population.");
                        } elseif ($offer['maxtime'] == 0 || $offer['maxtime'] <= $duration) {
                            $res = array_fill(0, 4, 0);
                            $res[$offer['needType'] - 1] += $offer['needValue'];
                            if (Village::getInstance()->isResourcesAvailable($res)) {
                                if (ceil($offer['needValue'] / $merchant_cap) <= $view->vars['merchantsAvailable']) {
                                    if (Session::getInstance()->banned()) {
                                        $this->innerRedirect("InGameBannedPage");
                                    } elseif (Config::getInstance()->dynamic->serverFinished) {
                                        $this->innerRedirect("InGameWinnerPage");
                                    }
                                    $buyerVillage = Village::getInstance();
                                    $buyerKid = $buyerVillage->getKid();
                                    $buyerRace = Session::getInstance()->getRace();
                                    $playerId = $m->getVillageOwner($offer['kid']);
                                    $race = $m->getPlayerRace($playerId);
                                    // Payment, offer removal and both merchant dispatches commit together.
                                    $merchantsShort = false;
                                    $merchantCount = (int)$this->building_level;
                                    $accepted = $buyerVillage->transaction(static function () use ($buyerVillage, $m, $res, $offer, $buyerKid, $buyerRace, $race, $merchant_cap, $merchantCount, &$merchantsShort): bool {
                                        if (ceil($offer['needValue'] / $merchant_cap) > $m->availableMerchantsLocked($buyerKid, $merchant_cap, $merchantCount)) {
                                            $merchantsShort = true;

                                            return false;
                                        }
                                        if (!$buyerVillage->spend($res) || !$m->deleteOffer($offer['id'])) {
                                            return false;
                                        }
                                        $m->sendResources($offer['kid'], $buyerKid, $race, $offer['giveType'] == 1 ? $offer['giveValue'] : 0, $offer['giveType'] == 2 ? $offer['giveValue'] : 0, $offer['giveType'] == 3 ? $offer['giveValue'] : 0, $offer['giveType'] == 4 ? $offer['giveValue'] : 0, 1);
                                        $m->sendResources($buyerKid, $offer['kid'], $buyerRace, $offer['needType'] == 1 ? $offer['needValue'] : 0, $offer['needType'] == 2 ? $offer['needValue'] : 0, $offer['needType'] == 3 ? $offer['needValue'] : 0, $offer['needType'] == 4 ? $offer['needValue'] : 0, 1);

                                        return true;
                                    });
                                    if ($merchantsShort) {
                                        $view->vars['error'] = T("MarketPlace", "not enough merchants");
                                    }
                                    if ($accepted) {
                                        $db = DB::getInstance();
                                        $quest = Quest::getInstance();
                                        $quest->setQuestBitwise('economy', 7, 1);
                                        $playerName = $db->fetchScalar("SELECT name FROM users WHERE id=$playerId");
                                        $view->vars['offerAccepted'] = true;
                                        $view->vars['offerAcceptTitle'] = sprintf(T("MarketPlace", 'x\'s offering has been accepted'), '<a href="' . profileUrl((int) $playerId) . '">' . $playerName . '</a>');
                                        $view->vars['giveType'] = $offer['giveType'];
                                        $view->vars['giveValue'] = $offer['giveValue'];
                                        $view->vars['needType'] = $offer['needType'];
                                        $view->vars['needValue'] = $offer['needValue'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $offersCount = $m->getOffersCount(Session::getInstance()->getAllianceId(), Village::getInstance()->getKid(), Session::getInstance()->getRace(), $view->vars['r'] == '1:1' ? 1 : 0, $view->vars['s'], $view->vars['b'], (bool)$view->vars['al']);
        $page = isset($_REQUEST['page']) ? abs((int)$_REQUEST['page']) : 1;
        $offersPerPage = Session::getInstance()->getMarketplaceOffersPerPage();
        $offers = $m->getOffers($page, $offersPerPage, Session::getInstance()->getAllianceId(), Village::getInstance()->getKid(), Session::getInstance()->getRace(), $view->vars['r'] == '1:1' ? 1 : 0, $view->vars['s'], $view->vars['b'], (bool)$view->vars['al']);
        while ($row = $offers->fetch_assoc()) {
            $ratio = round($row['needValue'] / $row['giveValue'], 1);
            if ($ratio <= 1) {
                $class = 'green';
            } elseif ($ratio > 1 && $ratio < 2) {
                $class = 'orange';
            } else {
                $class = 'red';
            }
            $resourceClass = [1 => 'lumber', 2 => 'clay', 3 => 'iron', 4 => 'crop'];
            $owner = $m->getVillageOwner($row['kid']);
            $player = $m->getPlayerRaceAndName($owner);
            $xy = Formulas::kid2xy($row['kid']);
            $travelSeconds = (int)Formulas::travelTimeSeconds(Formulas::getDistance($row['kid'], Village::getInstance()->getKid()) / $speed * 3600);
            $duration = sprintf('%02d:%02d:%02d', intdiv($travelSeconds, 3600), intdiv($travelSeconds % 3600, 60), $travelSeconds % 60);
            $quality = $ratio <= 1 ? 4 : ($ratio < 1.5 ? 3 : ($ratio < 2 ? 2 : 1));
            $view->vars['content'] .= '<tr>'
                . '<td class="offered"><div class="inlineIcon"><i class="' . $resourceClass[$row['giveType']] . '_small" title="' . T('inGame', 'resources.r' . $row['giveType']) . '"></i><span>' . "\u{202D}" . number_format_x($row['giveValue']) . "\u{202C}" . '</span></div></td>'
                . '<td class="ratio"><div class="offerQuality' . $quality . ' ratioIndicator"><span class="value">' . "\u{202D}" . number_format($ratio, 1, '.', '') . "\u{202C}" . '</span></div></td>'
                . '<td class="wanted"><div class="inlineIcon"><i class="' . $resourceClass[$row['needType']] . '_small" title="' . T('inGame', 'resources.r' . $row['needType']) . '"></i><span class="">' . "\u{202D}" . number_format_x($row['needValue']) . "\u{202C}" . '</span></div></td>'
                . '<td class="merchants"><span class="">' . (int)ceil($row['needValue'] / $merchant_cap) . '</span></td>'
                . '<td class="duration">' . $duration . '</td>'
                . '<td class="tribe"><i class="tribe' . $player['race'] . '_small" title="' . T('Global', 'races.' . $player['race']) . '"></i></td>'
                . '<td class="player"><a href="/karte.php?x=' . $xy['x'] . '&amp;y=' . $xy['y'] . '">' . $player['name'] . '</a></td>'
                . '<td class="alliance"></td>'
                . $this->OfferAction($row, $page, $view->vars['s'], $view->vars['b'], $view->vars['r'], $view->vars['merchantsAvailable'], $merchant_cap, $view->vars['al'])
                . '</tr>';
        }
        if (!$offers->num_rows) {
            $view->vars['content'] .= '<tr class="none">
        <td colspan="8">' . T("MarketPlace", "noOffers") . '</td>
      </tr>';
        }
        $prefix['id'] = $this->view->vars['index'];
        $prefix['s'] = $view->vars['s'];
        $prefix['b'] = $view->vars['b'];
        $prefix['v'] = $view->vars['r'];
        $prefix['al'] = $view->vars['al'];
        $prefix['t'] = 1;
        $view->vars['nav'] = ButtonPagination::render(
            $page,
            (int)ceil($offersCount / $offersPerPage),
            static fn (int $target): string => '/build.php?' . http_build_query(['id' => $prefix['id'], 's' => $prefix['s'], 'b' => $prefix['b'], 'v' => $prefix['v'], 'al' => $prefix['al'], 't' => 1, 'page' => $target], '', '&amp;')
        );
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * @param array<string, float|int|string|null> $row
     * @param int $page
     * @param mixed $s
     * @param mixed $b
     * @param mixed $v
     * @param mixed $available_merchants
     * @param mixed $merchant_cap
     * @param mixed $al
     */
    /**
     * Whether the player may only trade 1:1 right now: below the population limit, or under beginner's
     * protection. The population reason wins when both apply, as on the reference.
     */
    private function evenTradeReason(): ?string
    {
        $session = Session::getInstance();
        if ($session->getTotalPopulation() < self::EVEN_TRADE_POPULATION) {
            return 'population';
        }

        return $session->hasProtection() ? 'protection' : null;
    }

    private function evenTradeTooltip(string $reason): string
    {
        return $reason === 'population'
            ? sprintf((string) T('MarketPlace', 'While below %d account population, you can only accept 1:1 trades'), self::EVEN_TRADE_POPULATION)
            : (string) T('MarketPlace', "During beginner's protection, you can only accept 1:1 trades");
    }

    private function OfferAction($row, $page, $s, $b, $v, $available_merchants, $merchant_cap, $al = 0): string
    {
        $icon = '<svg aria-hidden="true"><svg viewBox="0 0 20 16.38" class="outline"><path d="M20 2.63 7.75 16.38 0 7.04l2.55-2.5 5.14 6.67L17.67 0 20 2.63Z" fill="currentColor"/></svg></svg>';
        $button = static fn (bool $disabled, string $attributes, string $title) => '<td class="accept"><button type="button" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" class="textButtonV2 buttonFramed' . ($disabled ? ' disabled' : '') . ' rectangle withIcon green"' . ($disabled ? ' disabled="disabled"' : '') . $attributes . '>' . $icon . '</button></td>';
        $res = array_fill(0, 4, 0);
        $res[$row['needType'] - 1] += $row['needValue'];
        // Lists every reason the offer cannot be accepted.
        $reasons = [];
        $evenOnly = $this->evenTradeReason();
        if ($evenOnly !== null && $row['needValue'] != $row['giveValue']) {
            $reasons[] = $this->evenTradeTooltip($evenOnly);
        }
        if (ceil($row['needValue'] / $merchant_cap) > $available_merchants) {
            $reasons[] = T("MarketPlace", "Not enough merchants to accept this offer");
        }
        if (!Village::getInstance()->isResourcesAvailable($res)) {
            $reasons[] = T("MarketPlace", "Not enough resources to accept this offer");
        }
        if ($reasons !== []) {
            return $button(true, '', implode('<br />', $reasons));
        }
        $href = "/build.php?id=" . $this->view->vars['index'] . "&t=1&v={$v}&a=" . Village::getInstance()->getKid() . "&g={$row['id']}&b={$b}&s={$s}&al={$al}&page={$page}";
        return $button(false, ' onclick="window.location.href = \'' . $href . '\'; return false;"', '');
    }

    private function Offer(): void
    {
        $view = new PHPBatchView("build/building/MarketplaceOffer");
        $view->vars['m1'] = isset($_POST['m1']) ? abs((int)$_POST['m1']) : '';
        $view->vars['m2'] = isset($_POST['m2']) ? abs((int)$_POST['m2']) : '';
        $view->vars['error'] = '';
        $view->vars['success'] = '';
        $m = new MarketModel();
        $village = Village::getInstance();
        if (isset($_GET['del'])) {
            $cancelKid = $village->getKid();
            $cancelId = (int)$_GET['del'];
            $village->transaction(static function () use ($village, $m, $cancelKid, $cancelId): bool {
                return $village->refund($m->cancelOffer($cancelKid, $cancelId));
            });
        }
        $view->vars['hasAlliance'] = Session::getInstance()->getAllianceId() > 0;
        $view->vars['total_merchants'] = $this->building_level;

        $session = Session::getInstance();
        $alliance_bonus = 1;
        if ($session->getAllianceId() > 0) {
            $alliance_bonus = AllianceBonus::getTradersBonus($session->getAllianceId(), $session->getAllianceJoinTime());
        }

        $merchant_cap = max(1, (int)floor(Formulas::merchantCAP(
            $session->getRace(),
            max(Village::getInstance()->getTypeLevel(28)),
            $alliance_bonus
        )));
        $view->vars['merchantCapacity'] = $merchant_cap;
        $view->vars['merchantsAvailable'] = $this->building_level - $m->getOfferingMerchantsCount(Village::getInstance()->getKid(), $merchant_cap) - $m->getOnTheWayMerchantsCount(Village::getInstance()->getKid(), $merchant_cap);
        $view->vars['resources'] = array_map(
            static fn ($amount): int => max(0, (int)floor((float)$amount)),
            Village::getInstance()->getCurrentResources()
        );
        // create-offer dialog posts m1/m2/rid1/rid2; also accept ownAlliance/maxTime aliases.
        if (!isset($_POST['ally']) && !empty($_POST['ownAlliance'])) {
            $_POST['ally'] = 1;
        }
        if (!isset($_POST['d1']) && !empty($_POST['maxTime'])) {
            $_POST['d1'] = 1;
            if (!isset($_POST['d2']) && isset($_POST['maxTimeHours'])) {
                $_POST['d2'] = (int)$_POST['maxTimeHours'];
            }
        }
        if (isset($_POST['m1']) && $_POST['m1'] > 0 && isset($_POST['m2']) && $_POST['m2'] > 0 && Session::claimAction($_POST['c'] ?? null)) {
            $_POST['rid1'] = max(min((int)$_POST['rid1'], 4), 1);
            $_POST['rid2'] = max(min((int)$_POST['rid2'], 4), 1);
            $res = array_fill(0, 4, 0);
            $res[max((int)$_POST['rid1'] - 1, 0)] = (int)$_POST['m1'];
            if (!$village->isResourcesAvailable($res)) {
                $view->vars['error'] = T("MarketPlace", "not enough resources");
            } elseif ($this->evenTradeReason() !== null && $_POST['m1'] != $_POST['m2']) {
                $view->vars['error'] = T('MarketPlace', "During beginner's protection, you can only offer 1:1 trades. The same applies if you have less than 200 population.");
            } elseif ($_POST['m2'] / $_POST['m1'] > 2 || $_POST['m1'] / $_POST['m2'] > 2) {
                $view->vars['error'] = T("MarketPlace", "Unable to create offer; maximum ratio allowed is 2:1");
            } elseif ($view->vars['merchantsAvailable'] < ceil($_POST['m1'] / $merchant_cap)) {
                $view->vars['error'] = T("MarketPlace", "not enough merchants");
            } else {
                if (Session::getInstance()->banned()) {
                    $this->innerRedirect("InGameBannedPage");
                } elseif (Config::getInstance()->dynamic->serverFinished) {
                    $this->innerRedirect("InGameWinnerPage");
                }
                $allianceOnly = !empty($_POST['ally']) || !empty($_POST['ownAlliance']);
                $maxHours = (!empty($_POST['d1']) || !empty($_POST['maxTime']))
                    ? max(1, (int)($_POST['d2'] ?? $_POST['maxTimeHours'] ?? 1))
                    : 0;
                $offerKid = $village->getKid();
                $offerAid = $allianceOnly ? Session::getInstance()->getAllianceId() : 0;
                $needType = $_POST['rid2'];
                $needValue = (int)$_POST['m2'];
                $giveType = $_POST['rid1'];
                $giveValue = (int)$_POST['m1'];
                $merchantsShort = false;
                $merchantCount = (int)$this->building_level;
                $created = $village->transaction(static function () use ($village, $m, $res, $offerKid, $offerAid, $maxHours, $needType, $needValue, $giveType, $giveValue, $merchant_cap, $merchantCount, &$merchantsShort): bool {
                    if (ceil($giveValue / $merchant_cap) > $m->availableMerchantsLocked($offerKid, $merchant_cap, $merchantCount)) {
                        $merchantsShort = true;

                        return false;
                    }
                    if (!$village->spend($res)) {
                        return false;
                    }
                    $m->addOffer($offerKid, $offerAid, $maxHours, $needType, $needValue, $giveType, $giveValue);

                    return true;
                });
                if ($merchantsShort) {
                    $view->vars['error'] = T("MarketPlace", "not enough merchants");
                }
                if ($created) {
                    $view->vars['success'] = T("MarketPlace", 'offer added successfully');
                    $view->vars['merchantsAvailable'] -= ceil($_POST['m1'] / $merchant_cap);
                    $quest = Quest::getInstance();
                    $quest->setQuestBitwise('economy', 7, 1);
                }
            }
        }
        $view->vars['checker'] = Session::getInstance()->getChecker();
        $offers = $m->getOwnOffers(Village::getInstance()->getKid());
        $view->vars['hasOffers'] = $offers->num_rows > 0;
        $view->vars['content'] = '';
        while ($row = $offers->fetch_assoc()) {
            $ratio = round($row['needValue'] / $row['giveValue'], 1);
            if ($ratio <= 1) {
                $class = 'green';
            } elseif ($ratio > 1 && $ratio < 2) {
                $class = 'orange';
            } else {
                $class = 'red';
            }
            $view->vars['content'] .= '<tr>';
            $view->vars['content'] .= '<td class="abo"><a href="/build.php?gid=17&amp;t=2&amp;a=5&amp;del=' . $row['id'] . '"><img class="del" src="img/spacer.gif" alt="' . htmlspecialchars(T('MarketPlace', 'Delete'), ENT_QUOTES, 'UTF-8') . '"></a></td>';
            $view->vars['content'] .= '<td class="val"><div class="inlineIconList resourceWrapper"><div class="inlineIcon resources"><i class="r' . $row['giveType'] . '"></i><span class="value ">' . $row['giveValue'] . '</span></div></div></td>';
            $view->vars['content'] .= '<td class="ratio">

				<div class="boxes boxesColor ' . $class . '">
	<div class="boxes-tl"></div>
	<div class="boxes-tr"></div>
	<div class="boxes-tc"></div>
	<div class="boxes-ml"></div>
	<div class="boxes-mr"></div>
	<div class="boxes-mc"></div>
	<div class="boxes-bl"></div>
	<div class="boxes-br"></div>
	<div class="boxes-bc"></div>
	<div class="boxes-contents cf">
				' . $ratio . '
					</div>
</div>
				</td>';
            $view->vars['content'] .= '<td class="val"><div class="inlineIconList resourceWrapper"><div class="inlineIcon resources"><i class="r' . $row['needType'] . '"></i><span class="value ">' . $row['needValue'] . '</span></div></div></td>';
            $view->vars['content'] .= '<td class="tra">' . ceil($row['giveValue'] / $merchant_cap) . '</td>';
            $view->vars['content'] .= '<td class="al">' . (T("MarketPlace", $row['aid'] > 0 ? 'yes' : 'no')) . '</td>';
            $view->vars['content'] .= '<td class="dur">' . ($row['maxtime'] > 0 ? ($row['maxtime'] / 3600) . ' ' . T("MarketPlace", "hours") : '-') . '</td>';
            $view->vars['content'] .= '</tr>';
        }
        $this->view->vars['content'] .= $view->output();
    }

    private function sendResources(): void
    {
        $view = new PHPBatchView("build/building/MarketplaceSendResources");
        $m = new MarketModel();
        $view->vars['x'] = '';
        $view->vars['y'] = '';
        $view->vars['dname'] = '';
        $this->build->view->newVillagePrefix['t'] = 5;
        if (isset($_GET['z'])) {
            $xy = Formulas::kid2xy((int)$_GET['z']);
            $view->vars['x'] = $xy['x'];
            $view->vars['y'] = $xy['y'];
            $this->build->view->newVillagePrefix['z'] = (int)$_GET['z'];
        }
        if (isset($_GET['dname'])) {
            $view->vars['dname'] = $_GET['dname'];
        }
        if (isset($_GET['x']) && $_GET['x'] != "") {
            $view->vars['x'] = Formulas::coordinateFixer($_GET['x']);
            $view->vars['y'] = Formulas::coordinateFixer($_GET['y']);
        }
        $session = Session::getInstance();
        $alliance_bonus = 1;
        if ($session->getAllianceId() > 0) {
            $alliance_bonus = AllianceBonus::getTradersBonus($session->getAllianceId(), $session->getAllianceJoinTime());
        }

        $merchant_cap = max(1, (int)floor(Formulas::merchantCAP(
            Session::getInstance()->getRace(),
            max(Village::getInstance()->getTypeLevel(28)),
            $alliance_bonus
        )));
        $view->vars['total_merchants'] = $this->building_level;
        $view->vars['merchantsAvailable'] = $this->building_level - $m->getOfferingMerchantsCount(Village::getInstance()->getKid(), $merchant_cap) - $m->getOnTheWayMerchantsCount(Village::getInstance()->getKid(), $merchant_cap);
        $view->vars['merchantCapacityValue'] = $merchant_cap;
        $tradeShips = \Game\Ships::homeShips((int)Village::getInstance()->getKid())[\Game\Ships::TRADE];
        $view->vars['tradeShips'] = $tradeShips;
        $view->vars['tradeShipCapacity'] = $tradeShips > 0 ? \Game\Ships::tradeCapacity((int)Session::getInstance()->getRace(), (int)max(Village::getInstance()->getTypeLevel(28)), (float)$alliance_bonus) : 0;
        $view->vars['index'] = $this->view->vars['index'];
        $view->vars['hasGoldClub'] = $session->hasGoldClub();
        $view->vars['hasPlus'] = $session->hasPlus();
        $view->vars['resources'] = array_map(
            static fn ($amount): int => max(0, (int)floor((float)$amount)),
            Village::getInstance()->getCurrentResources()
        );
        $view->vars['deliveries'] = $m->getDeliveryGroups((int)Village::getInstance()->getKid());
        $this->view->vars['content'] .= $view->output();
    }

    private function Management(): void
    {
        $session = Session::getInstance();
        $alliance_bonus = 1;
        if ($session->getAllianceId() > 0) {
            $alliance_bonus = AllianceBonus::getTradersBonus($session->getAllianceId(), $session->getAllianceJoinTime());
        }

        $merchant_cap = Formulas::merchantCAP(Session::getInstance()->getRace(), max(Village::getInstance()->getTypeLevel(28)), $alliance_bonus);
        $m = new MarketModel();
        $view = new PHPBatchView("build/building/MarketplaceManagement");
        $helper = new GoldHelper();
        $view->vars['exchangeButton'] = $helper->getExchangeResourcesButtonByCost(-1);
        $view->vars['kid'] = Village::getInstance()->getKid();
        $view->vars['total_on_the_way'] = $m->getOnTheWayMerchantsCount(Village::getInstance()->getKid(), $merchant_cap);
        $view->vars['total_offering'] = $m->getOfferingMerchantsCount(Village::getInstance()->getKid(), $merchant_cap);
        $view->vars['total'] = $this->building_level;
        $view->vars['total_available'] = $this->building_level - $view->vars['total_offering'] - $view->vars['total_on_the_way'];
        $this->view->vars['content'] .= $view->output();
    }

    private function TradeRoutes(): void
    {
        if (!Session::getInstance()->hasGoldClub()) {
            // Without the Gold club the reference leaves the building body empty; the tab opens the premium dialog.
            return;
        }

        $usePeriodicTradeRoutes = Config::getInstance()->game->usePeriodicTradeRoutes;
        $session = Session::getInstance();
        $alliance_bonus = 1;
        if ($session->getAllianceId() > 0) {
            $alliance_bonus = AllianceBonus::getTradersBonus($session->getAllianceId(), $session->getAllianceJoinTime());
        }
        $merchant_cap = Formulas::merchantCAP(
            $session->getRace(),
            max(Village::getInstance()->getTypeLevel(28)),
            $alliance_bonus
        );
        $m = new MarketModel();
        $total_merchants = $this->building_level;
        $total_available_merchants = $total_merchants
            - $m->getOfferingMerchantsCount(Village::getInstance()->getKid(), $merchant_cap)
            - $m->getOnTheWayMerchantsCount(Village::getInstance()->getKid(), $merchant_cap);

        if (Session::getInstance()->isInVacationMode() && WebService::isPost()) {
            $this->redirect("/options/vacation");
        }
        $tradeRoutesInfo = '';
        // Next trade route.
        $next = $m->getIncomingTradeRoute(Village::getInstance()->getKid());
        if ($next !== false) {
            $total = $next['r1'] + $next['r2'] + $next['r3'] + $next['r4'];
            if ($usePeriodicTradeRoutes) {
                $tradeRoutesInfo = sprintf(T("MarketPlace", "nextTradeRoute"), TimezoneHelper::date("H:i", $next['time']), ceil($total / $merchant_cap));
            } else {
                $tradeRoutesInfo = sprintf(T("MarketPlace", "nextTradeRoute"), $next['start_hour'], ceil($total / $merchant_cap));
            }
        }
        if (!isset($_REQUEST['option'])) {
            $this->showTradeRoutes($merchant_cap, $tradeRoutesInfo);
        } elseif ($_REQUEST['option'] == 1) {
            $destination = isset($_REQUEST['show-destination']) && in_array($_REQUEST['show-destination'], ['all', 'only-mine', 'others']) ? $_REQUEST['show-destination'] : 'all';
            if (WebService::isPost() && Session::claimAction($_POST['c'] ?? null) && isset($_POST['did_dest'])) {
                $repeat = max(min((int)$_POST['repeat'], 3), 1);
                if ($usePeriodicTradeRoutes) {
                    $hour = max(min((int)$_POST['hour'], 30 * 86400), 600);
                } else {
                    $hour = max(min((int)$_POST['hour'], 23), 0);
                }
                [$r1, $r2, $r3, $r4] = $this->tradeRouteResourcesFromPost();
                $did_dest = abs((int)$_POST['did_dest']);
                if (($r1 + $r2 + $r3 + $r4) &&
                    $m->isVillageMine($did_dest, Session::getInstance()->getAllianceId(), Session::getInstance()->getPlayerId(), $destination) &&
                    ceil(($r1 + $r2 + $r3 + $r4) / $merchant_cap) <= $total_merchants) {
                    $m->addTradeRoute(Village::getInstance()->getKid(), $did_dest, $r1, $r2, $r3, $r4, $hour, $repeat, true);
                }
                $this->showTradeRoutes($merchant_cap, $tradeRoutesInfo);
                return;
            }
            $view = new PHPBatchView("build/building/MarketplaceTradeRouteAdd");
            $view->vars['total_merchant'] = $total_merchants;
            $view->vars['available_merchants'] = $total_available_merchants;
            $view->vars['cap'] = $merchant_cap;
            $view->vars['index'] = $this->view->vars['index'];
            $view->vars['destination'] = $destination;
            $view->vars['checker'] = Session::getInstance()->getChecker();
            $view->vars['did_dest'] = '';
            $villages = $m->getTradeRouteVillage(Session::getInstance()->getAllianceId(), Session::getInstance()->getPlayerId(), Village::getInstance()->getKid(), $view->vars['destination']);
            while ($row = $villages->fetch_assoc()) {
                $xy = Formulas::kid2xy($row['kid']);
                $view->vars['did_dest'] .= '<option value="' . $row['kid'] . '">' . sprintf('%s (%s | %s)', $row['name'], $xy['x'], $xy['y']) . '</option>';
            }
            $view->vars['edit'] = false;
            $view->vars['r1'] = $view->vars['r2'] = $view->vars['r3'] = $view->vars['r4'] = $view->vars['hour'] = $view->vars['times'] = 0;
            $this->view->vars['content'] .= $view->output();
        } elseif ($_REQUEST['option'] == 2) {
            $destination = isset($_REQUEST['show-destination']) && in_array($_REQUEST['show-destination'], ['all', 'only-mine', 'others',]) ? $_REQUEST['show-destination'] : 'all';
            $find = $m->getTradeRoute(Village::getInstance()->getKid(), (int)($_REQUEST['trid'] ?? 0));
            if ($find === false) {
                $this->showTradeRoutes($merchant_cap, $tradeRoutesInfo);
                return;
            }
            if (WebService::isPost() && Session::claimAction($_POST['c'] ?? null)) {
                $repeat = max(min((int)$_POST['repeat'], 3), 1);
                if ($usePeriodicTradeRoutes) {
                    $hour = max(min((int)$_POST['hour'], 86400), 600);
                } else {
                    $hour = max(min((int)$_POST['hour'], 23), 0);
                }
                [$r1, $r2, $r3, $r4] = $this->tradeRouteResourcesFromPost();
                $did_dest = abs((int)$_POST['did_dest']);
                if (($r1 + $r2 + $r3 + $r4) && $m->isVillageMine($did_dest, Session::getInstance()->getAllianceId(), Session::getInstance()->getPlayerId(), $destination) && ceil(($r1 + $r2 + $r3 + $r4) / $merchant_cap) <= $total_merchants) {
                    $m->updateTradeRoute((int)($_REQUEST['trid'] ?? 0), $did_dest, $r1, $r2, $r3, $r4, $hour, $repeat, true);
                }
                $this->showTradeRoutes($merchant_cap, $tradeRoutesInfo);
                return;
            }
            $view = new PHPBatchView("build/building/MarketplaceTradeRouteAdd");
            $view->vars['total_merchant'] = $total_merchants;
            $view->vars['available_merchants'] = $total_available_merchants;
            $view->vars['cap'] = $merchant_cap;
            $view->vars['index'] = $this->view->vars['index'];
            $view->vars['destination'] = $destination;
            $view->vars['checker'] = Session::getInstance()->getChecker();
            $view->vars['did_dest'] = '';
            $villages = $m->getTradeRouteVillage(Session::getInstance()->getAllianceId(), Session::getInstance()->getPlayerId(), Village::getInstance()->getKid(), $view->vars['destination']);
            while ($row = $villages->fetch_assoc()) {
                $xy = Formulas::kid2xy($row['kid']);
                $view->vars['did_dest'] .= '<option value="' . $row['kid'] . '" ' . ($row['kid'] == $find['to_kid'] ? 'selected="selected"' : '') . '>' . sprintf('%s (%s | %s)', $row['name'], $xy['x'], $xy['y']) . '</option>';
            }
            $view->vars['trid'] = $find['id'];
            $view->vars['edit'] = true;
            $view->vars['r1'] = $find['r1'];
            $view->vars['r2'] = $find['r2'];
            $view->vars['r3'] = $find['r3'];
            $view->vars['r4'] = $find['r4'];
            $view->vars['hour'] = $find['start_hour'];
            $view->vars['times'] = $find['times'];
            $this->view->vars['content'] .= $view->output();
        } elseif ($_REQUEST['option'] == 4 && Session::claimAction($_REQUEST['c'] ?? null)) {
            $m->deleteTradeRoute(Village::getInstance()->getKid(), (int)($_REQUEST['trid'] ?? 0));
            $this->showTradeRoutes($merchant_cap, $tradeRoutesInfo);
        } else {
            $this->showTradeRoutes($merchant_cap, $tradeRoutesInfo);
        }
    }

    /**
     * @param mixed $merchant_cap
     */
    private function showTradeRoutes($merchant_cap, string $tradeRoutesInfo = ''): void
    {
        $usePeriodicTradeRoutes = Config::getInstance()->game->usePeriodicTradeRoutes;
        $buildingIndex = (int)$this->view->vars['index'];
        $view = new PHPBatchView("build/building/MarketplaceTradeRouteShow");
        $view->vars['index'] = $this->view->vars['index'];
        $view->vars['checker'] = Session::getInstance()->getChecker();
        $view->vars['tradeRoutesInfo'] = $tradeRoutesInfo;
        $view->vars['groups'] = [];
        $m = new MarketModel();
        $tradeRoutes = $m->getTradeRoutes(Village::getInstance()->getKid(), 1, false);
        while ($row = $tradeRoutes->fetch_assoc()) {
            if ($usePeriodicTradeRoutes) {
                $start = getTradeRouteTimeText($row['start_hour']);
            } else {
                $start = ($row['start_hour'] < 10 ? '0' . $row['start_hour'] : $row['start_hour']) . ':00';
            }
            $targetKid = (int)$row['to_kid'];
            if (!isset($view->vars['groups'][$targetKid])) {
                $view->vars['groups'][$targetKid] = [
                    'target' => $m->getVillageName($targetKid),
                    'routes' => [],
                ];
            }
            $view->vars['groups'][$targetKid]['routes'][] = [
                'id' => (int)$row['id'],
                'enabled' => (bool)$row['enabled'],
                'resources' => [(int)$row['r1'], (int)$row['r2'], (int)$row['r3'], (int)$row['r4']],
                'start' => $start,
                'deliveries' => (int)$row['times'],
                'merchants' => (int)ceil(($row['r1'] + $row['r2'] + $row['r3'] + $row['r4']) / $merchant_cap),
                'editUrl' => '/build.php?id=' . $buildingIndex . '&t=3&a=1&c=' . rawurlencode(Session::getInstance()->getChecker()) . '&option=2&trid=' . (int)$row['id'] . '&show-destination=all',
                'deleteUrl' => '/build.php?id=' . $buildingIndex . '&t=3&a=1&c=' . rawurlencode(Session::getInstance()->getChecker()) . '&option=4&trid=' . (int)$row['id'],
            ];
        }
        $this->view->vars['content'] .= $view->output();
    }

    /** @return array{0: int, 1: int, 2: int, 3: int} */
    private function tradeRouteResourcesFromPost(): array
    {
        return [
            abs((int)($_POST['lumber'] ?? 0)),
            abs((int)($_POST['clay'] ?? 0)),
            abs((int)($_POST['iron'] ?? 0)),
            abs((int)($_POST['crop'] ?? 0)),
        ];
    }
}
