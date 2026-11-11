<?php

namespace Controller\Ajax;

use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Core\Security\Password;
use Core\Village;
use Game\Buildings\BuildingAction;
use Game\Buildings\BuildingRearranger;
use Game\ExtraModules;
use Game\FinishConstruction;
use Game\Formulas;
use Game\GoldHelper;
use Game\Payment\GoldPackages;
use Game\Payment\PaymentConfig;
use Game\Payment\PaymentShop;
use Game\Hero\SilverExchange;
use Game\ResourcesHelper;
use Model\AutoExtendModel;
use Model\DailyQuestModel;
use Model\InfoBoxModel;
use Model\MovementsModel;
use Model\OptionModel;
use Model\TrainingModel;
use Model\VillageModel;
use Model\BattleSetter;
use resources\View\PHPBatchView;

use function array_key_exists;
use function array_search;
use function array_sum;
use function array_values;
use function miliseconds;

class premium extends AjaxBase
{
    private const DIRECT_ACTIONS = [
        'plus' => 'plus',
        'productionboostWood' => 'productionboostWood',
        'productionboostClay' => 'productionboostClay',
        'productionboostIron' => 'productionboostIron',
        'productionboostCrop' => 'productionboostCrop',
        'goldclub' => 'goldclub',
        'exchangeSilver' => 'exchangeSilver',
        'marketplace' => 'marketplace',
        'rearrangeBuildings' => 'rearrangeBuildings',
        'ChangeAccountName' => 'ChangeAccountName',
        'constructionMaster' => 'constructionMaster',
        'VacationModeAbort' => 'VacationModeAbort',
        'cancelTrainingQueue' => 'cancelTrainingQueue',
        'increaseStorage' => 'increaseStorage',
        'fasterTraining' => 'fasterTraining',
        'academyResearchAll' => 'academyResearchAll',
        'smithyUpgradeAllToMax' => 'smithyUpgradeAllToMax',
        'oneHourOfProduction' => 'oneHourOfProduction',
    ];

    public function dispatch(): void
    {
        $featureKey = $_POST['featureKey'] ?? null;
        if (!is_string($featureKey) || $featureKey === '') {
            $this->error('Invalid featureKey.');
            return;
        }
        if (!Session::getInstance()->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD)) {
            $this->response['error'] = true;
            $this->response['message'] = T("inGame", 'sitterNoPermForGold');
            return;
        }
        if ((Session::getInstance()->banned() && Session::getInstance()->getPlayerId() > 2) || Config::getInstance()->dynamic->serverFinished) {
            $this->response['error'] = true;
            $this->response['message'] = T("inGame", 'bannedSmallPage');
            return;
        }
        $methodKey = $featureKey;
        if (substr($featureKey, 0, 14) == 'moreProtection') {
            $methodKey = 'moreProtection';
        } elseif (substr($featureKey, 0, 9) == 'buyAnimal') {
            $methodKey = 'buyAnimal';
        } elseif (substr($featureKey, 0, 12) == 'buyResources') {
            $methodKey = 'buyResources';
        } elseif (substr($featureKey, 0, 9) == 'buyTroops') {
            $methodKey = 'buyTroops';
        } elseif (substr($featureKey, 0, 12) == 'buyBuildings') {
            $methodKey = 'buyBuildings';
        }
        if ($methodKey !== $featureKey || isset(self::DIRECT_ACTIONS[$featureKey])) {
            if ($this->checkGold($featureKey)) {
                if ($featureKey != 'exchangeSilver') {
                    $this->setContext();
                }
                if (substr($featureKey, 0, 14) == 'moreProtection') {
                    $this->moreProtection($featureKey);
                } elseif (substr($featureKey, 0, 9) == 'buyAnimal') {
                    $this->buyAnimal($featureKey);
                } elseif (substr($featureKey, 0, 12) == 'buyResources') {
                    $this->buyResources($featureKey);
                } elseif (substr($featureKey, 0, 9) == 'buyTroops') {
                    $this->buyTroops($featureKey);
                } elseif (substr($featureKey, 0, 12) == 'buyBuildings') {
                    $this->buyBuildings($featureKey);
                } else {
                    $method = self::DIRECT_ACTIONS[$featureKey];
                    $this->$method();
                }
            }
        } elseif (ExtraModules::actionExists($featureKey)) {
            if ($this->checkGold($featureKey)) {
                $result = ExtraModules::runAction(
                    $featureKey,
                    $this->response,
                    isset($_POST['additionalData']) ? $_POST['additionalData'] : null
                );
                $this->completeExtraModuleAction($result);
            }
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Invalid featureKey.';
        }
    }

    /**
     * @param string $featureKey
     */
    private function checkGold($featureKey): bool
    {
        $config = Config::getInstance();
        $paymentGolds = [
            'exchangeSilver'          => 0,
            'transferGold'            => 0,
            'goldclub'                => $config->gold->goldClubGold,
            'plus'                    => $config->gold->plusGold,
            'productionboostWood'     => $config->gold->productionBoostGold,
            'productionboostClay'     => $config->gold->productionBoostGold,
            'productionboostIron'     => $config->gold->productionBoostGold,
            'productionboostCrop'     => $config->gold->productionBoostGold,
            'rearrangeBuildings'      => $config->gold->rearrangeBuildingsGold,
            'ChangeAccountName'       => $config->gold->accountChangeNameGold,
            'constructionMaster'      => $config->gold->masterBuilderGold,
            'marketplace'             => $config->gold->exchangeResourcesGold,
            'VacationModeAbort'       => $config->gold->vacationAbortGold,
            'oneHourOfProduction'     => $config->extraSettings->generalOptions->oneHourOfProduction->coins,
            'fasterTraining'          => $config->extraSettings->generalOptions->fasterTraining->coins,
            'academyResearchAll'      => $config->extraSettings->generalOptions->academyResearchAll->coins,
            'smithyUpgradeAllToMax'   => $config->extraSettings->generalOptions->smithyUpgradeAllToMax->coins,
            'cancelTrainingQueue'     => $config->extraSettings->generalOptions->cancelTrainingQueue->coins,
            'AllianceBonusDonation'   => Config::getProperty("gold", "allianceBonus3xGold"),

            //'upgradeAllResourcesTo20' => Village::getInstance()->isCapital() ? $config->extraSettings->generalOptions->upgradeAllResourcesTo20->coinsCapital : $config->extraSettings->generalOptions->upgradeAllResourcesTo20->coinsNoNCapital,
            //'upgradeAllResourcesTo30' => $config->extraSettings->generalOptions->upgradeAllResourcesTo30->coins,
            //'rallyPointTo20'          => $config->extraSettings->generalOptions->rallyPointTo20->coins,

        ];
        foreach ($config->extraSettings->moreProtection->packages as $index => $package) {
            $paymentGolds['moreProtection' . $index] = $package['coins'];
        }
        foreach ($config->extraSettings->buyAnimal['packages'] as $index => $package) {
            $paymentGolds['buyAnimal' . $index] = $package['coins'];
        }
        foreach ($config->extraSettings->buyTroops['packages'] as $tid =>  $troop) {
            foreach ($troop as $index => $package) {
                $paymentGolds['buyTroops' .$tid. $index] = $package['coins'];
            }
        }
        foreach ($config->extraSettings->buyBuildings['packages'] as $index => $package) {
            $paymentGolds['buyBuildings' . $index] = (Village::getInstance()->isCapital() && isset($package->coinsCapital)) ? $package->coinsCapital : $package->coins;
        }
        foreach ($config->extraSettings->buyResources['packages'] as $index => $package) {
            $paymentGolds['buyResources' . $index] = $package['coins'];
        }
        if (!isset($paymentGolds[$featureKey])) {
            $paymentGolds[$featureKey] = ExtraModules::getCoins($featureKey);
        }
        if ($paymentGolds[$featureKey] === false) {
            return true;
        }
        if (Session::getInstance()->getAvailableGold() >= $paymentGolds[$featureKey]) {
            return true;
        }
        $features = [
            0 => 'plus',
            1 => 'productionboostWood',
            2 => 'productionboostClay',
            3 => 'productionboostIron',
            4 => 'productionboostCrop',
        ];
        if (isset($_POST['toggleAutoprolong']) && in_array($featureKey, $features)) {
            if ($featureKey == 'plus') {
                $end = Session::getInstance()->plusTill();
            } else {
                $end = Session::getInstance()->productionBoostTill(array_search($featureKey, $features));
            }
            if ($end > 0) {
                return true;
            }
        }
        $neededGold = $paymentGolds[$featureKey] - Session::getInstance()->getGold();
        $this->response = array_merge(
            $this->response,
            self::renderBuyPackageSmallestPackageDialog($neededGold)
        );
        return false;
    }

    /**
     * @param (float|int) $neededGold
     * @return array<string, array<string, mixed>|string>|array<string, mixed>
     */
    public static function renderBuyPackageSmallestPackageDialog($neededGold)
    {
        $account = GlobalDB::getInstance();
        $packages = PaymentShop::isOpen($account, time()) ? GoldPackages::active($account, time()) : [];
        if ($packages === []) {
            return ['error' => true, 'message' => T('PaymentWizard', 'notEnoughGoldForThisOption')];
        }
        $package = $packages[0];
        foreach ($packages as $candidate) {
            if ($candidate['gold'] >= $neededGold) {
                $package = $candidate;
                break;
            }
        }
        $data = [];
        //package here.
        $data['functionToCall'] = 'renderDialog';
        $data['options']['dialogOptions']['saveOnUnload'] = false;
        $data['options']['dialogOptions']['draggable'] = false;
        $data['options']['dialogOptions']['buttonOk'] = false;
        $data['options']['dialogOptions']['version'] = 2;
        $data['options']['dialogOptions']['cssClass'] = "paymentShopV5 smallestPackage";
        $data['options']['dialogOptions']['context'] = 'smallestPackage';
        $view = new PHPBatchView("payment/showLowGold");
        $view->vars['goldNum'] = $package['gold'];
        $view->vars['goldProductId'] = $package['id'];
        $view->vars['goldProductImageName'] = $package['image'];
        $view->vars['goldProductName'] = $package['name'];
        $view->vars['bestSeller'] = (bool)($package['bestSeller'] ?? false);
        $view->vars['bestValue'] = (bool)($package['bestValue'] ?? false);
        $view->vars['currency'] = PaymentConfig::fromEnvironment()->currency();
        $view->vars['priceMinor'] = $package['priceMinor'];
        $data['options']['html'] = $view->output();
        return $data;
    }

    private function setContext(): void
    {
        if (!isset($_POST['context'])) {
            return;
        }
        $context = ['productionBoost', 'paymentWizard', 'infobox', 'plus', 'goldclub',];
        $contextFound = in_array($_POST['context'], $context);
        if ($_POST['context'] == 'infobox' || !$contextFound) {
            $this->response['functionToCall'] = 'reloadUrl';
        } else {
            $this->response['functionToCall'] = 'reloadDialog';
        }
        if ($contextFound) {
            $this->response['context'] = $_POST['context'];
        }
    }

    /**
     * @param mixed $coins
     */
    private function spendGold($coins, bool $transactionAlreadyOpen = false): bool
    {
        if (GoldHelper::decreaseGold(
            Session::getInstance()->getPlayerId(),
            (int)$coins,
            $transactionAlreadyOpen
        )) {
            return true;
        }

        $this->error(T('Auction', 'notEnoughGold'));
        return false;
    }

    private function completeExtraModuleAction(string $result): bool
    {
        if ($result === ExtraModules::ACTION_COMPLETED) {
            $this->setContext();
            return true;
        }

        $this->error($result === ExtraModules::ACTION_NOT_ENOUGH_GOLD
            ? T('Auction', 'notEnoughGold')
            : T('PaymentWizard', 'This feature is disabled'));
        return false;
    }

    private function unavailablePremiumAction(): void
    {
        $this->error(T('PaymentWizard', 'This feature is disabled'));
    }

    /**
     * @param string $featureKey
     */
    private function moreProtection($featureKey): void
    {
        $config = Config::getInstance();
        $number = preg_replace('/[^0-9]/', '', $featureKey);
        if (!isset($config->extraSettings->moreProtection->packages[$number])) {
            $this->unavailablePremiumAction();
            return;
        }
        $feature = $config->extraSettings->moreProtection->packages[$number];
        $allowWithArtifact = $config->extraSettings->moreProtection->allowWithArtifact;
        $maxDuration = $config->extraSettings->moreProtection->maxPerDay;
        if (!$feature['enabled']) {
            $this->unavailablePremiumAction();
            return;
        }
        $db = DB::getInstance();
        $session = Session::getInstance();
        if ((time() - $session->get("protectionLastExtend")) > 86400) {
            $session->set("protectionLastExtend", 0);
            $session->set("protectionBoughtHours", 0);
            $db->query("UPDATE users SET protectionLastExtend=0, protectionBoughtHours=0 WHERE id={$session->getPlayerId()}");
        }
        if ((time() - $session->get("protectionLastExtend")) < 86400 && ($feature['duration'] + $session->get("protectionBoughtHours")) > $maxDuration) {
            $this->response['functionToCall'] = 'reloadDialog';
            $this->response['context'] = 'paymentWizard';
            $this->response['e'] = 'Protection limit exceeded.';
            return;
        }
        $m = new OptionModel();
        if ($m->hasPlayerWWVillage(Session::getInstance()->getPlayerId()) <> 0 || (!$allowWithArtifact && $m->hasPlayerArtifact(Session::getInstance()->getPlayerId()) <> 0)) {
            $this->response['functionToCall'] = 'reloadDialog';
            $this->response['context'] = 'paymentWizard';
            $this->response['e'] = 'Has artifact or WW.';
            return;
        }
        $duration = $feature['duration'] * 3600;
        if ($session->get("protection") > time()) {
            $duration = $session->get("protection") + $duration;
        } else {
            $duration += time();
        }
        $baseProtection = Formulas::getProtectionBasicTime($session->get("signupTime"));
        $usedProtection = ($session->protectionTill() - $session->get("signupTime"));
        $protectionExtendingUsed = $usedProtection > $baseProtection;
        if (!$protectionExtendingUsed && $session->protectionTill() == ($session->get("signupTime") + $baseProtection)) {
            $duration += Formulas::getProtectionExtendTime($session->get("signupTime"));
        }
        $coins = $feature['coins'];
        $db->begin_transaction();
        if (!$this->spendGold($coins, true)) {
            $db->rollback();
            return;
        }
        $infoBox = new InfoBoxModel();
        if ($infoBox->hasInfoByType($session->getPlayerId(), 6)) {
            $db->query("UPDATE infobox SET showTo=" . $duration . " WHERE uid={$session->getPlayerId()} AND type=6");
        } else {
            $infoBox->addInfo($session->getPlayerId(), false, 6, '', time() - 1, $duration);
        }
        $protectionExtend = time();
        $db->query("UPDATE users SET protection=$duration, protectionBoughtHours=protectionBoughtHours+{$feature['duration']}, protectionLastExtend=" . $protectionExtend . " WHERE id=" . $session->getPlayerId());
        $db->commit();
        InfoBoxModel::invalidateUserInfoBoxCache($session->getPlayerId());
        $this->response['functionToCall'] = 'reloadUrl';
        $this->response['context'] = 'paymentWizard';
    }

    /**
     * @param string $featureKey
     */
    private function buyAnimal($featureKey): void
    {
        $packageId = preg_replace('/[^0-9]/', '', $featureKey);
        $config = Config::getInstance();
        if (Village::getInstance()->isWW() || !$config->extraSettings->buyAnimal['enabled']) {
            $this->unavailablePremiumAction();
            return;
        }
        if (!isset($config->extraSettings->buyAnimal['packages'][$packageId])) {
            $this->unavailablePremiumAction();
            return;
        }
        $buyInterval = $config->extraSettings->buyAnimal['buyInterval'];
        if ($buyInterval > 0 && (time() - Session::getInstance()->get("lastBuyAnimals")) < $buyInterval) {
            $timeUnit = TimezoneHelper::getIntervalUnit($buyInterval - (time() - Session::getInstance()->get("lastBuyAnimals")));
            $this->response['error'] = true;
            $this->response['message'] = sprintf(
                T(
                    "PaymentWizard",
                    'You need to wait %s %s before buying this package'
                ),
                $timeUnit['time'],
                $timeUnit['unit']
            );
            return;
        }
        $db = DB::getInstance();
        $kid = $db->fetchScalar("SELECT kid FROM odata WHERE did=0 LIMIT 1");
        if (!$kid) {
            $this->unavailablePremiumAction();
            return;
        }

        $modifier = $config->extraSettings->buyAnimal['packages'][$packageId]['modifier'];
        $rate = Config::getProperty("game", "useNanoseconds") ? 1e9 : (Config::getProperty("game", "useMilSeconds") ? 1e3 : 1);
        $model = new BattleSetter();
        $units = [];

        $legionaire = Formulas::$data['units'][0][0];
        $legionaire_id = nrToUnitId(1, 1);
        $legionaire_train =  round(($modifier * 3600 * $rate) / Formulas::uTrainingTime($legionaire_id, 20));
        $legionaire_def = $model->stat_with_upg($legionaire['def_i'], $legionaire['cu'], 1) + $model->stat_with_upg($legionaire['def_c'], $legionaire['cu'], 1);
        $baseLineDef = ($legionaire_train * $legionaire_def) / 10;

        for ($k = 0; $k <= 9; $k++) {
            $unit = Formulas::$data['units'][3][$k];
            $def = $model->stat_with_upg($unit['def_i'], $unit['cu'], 1) + $model->stat_with_upg($unit['def_c'], $unit['cu'], 1);
            $units[$k + 1] = floor($baseLineDef / $def);
        }

        $move = new MovementsModel();
        $delivery = $config->extraSettings->buyAnimal['packages'][$packageId]['delivery'];
        $db->begin_transaction();
        $move->addMovement(
            $kid,
            Village::getInstance()->getKid(),
            4,
            $units,
            0,
            0,
            0,
            0,
            0,
            MovementsModel::ATTACKTYPE_REINFORCEMENT,
            miliseconds(),
            miliseconds() + $delivery * 60 * 1000
        );
        $coins = $config->extraSettings->buyAnimal['packages'][$packageId]['coins'];
        if (!$this->spendGold($coins, true)) {
            $db->rollback();
            return;
        }
        $db->query("UPDATE users SET lastBuyAnimals=" . time() . " WHERE id=" . Session::getInstance()->getPlayerId());
        $db->commit();
        $this->response = [];
        $this->response['functionToCall'] = 'productPurchased';
        $this->response['options']['type'] = 'animal';
        $this->response['options']['newGold'] = Session::getInstance()->getGold() - $coins;
    }

    /**
     * @param string $featureKey
     */
    private function buyResources($featureKey): void
    {
        //TODO: check if resources are full or not
        $config = Config::getInstance();
        $packageId = preg_replace('/[^0-9]/', '', $featureKey);
        $packages = $config->extraSettings->buyResources['packages'];
        //$realPackageId = array_search($packageId, array_column($packages, 'id'));
        $realPackage = $packages[$packageId] ?? null;

        if (Village::getInstance()->isWW() || !$config->extraSettings->buyResources['enabled']) {
            $this->unavailablePremiumAction();
            return;
        }
        if (!isset($realPackage)) {
            $this->unavailablePremiumAction();
            return;
        }
        $buyInterval = $config->extraSettings->buyResources['buyInterval'];
        if ($buyInterval > 0 && (time() - Session::getInstance()->get("lastBuyResources")) < $buyInterval) {
            $timeUnit = TimezoneHelper::getIntervalUnit($buyInterval - (time() - Session::getInstance()->get("lastBuyResources")));
            $this->response['error'] = true;
            $this->response['message'] = sprintf(
                T(
                    "PaymentWizard",
                    'You need to wait %s %s before buying this package'
                ),
                $timeUnit['time'],
                $timeUnit['unit']
            );
            return;
        }
        $db = DB::getInstance();

        $resources = array(0,0,0,0);
        $productionAvg = (Formulas::fieldProduction(20) * 4);
        for ($i = 0; $i < 4;  $i++) {
            $resources[$i] = 0;
            if (!$realPackage['is_single'] || $realPackage['resource'] == $i + 1) {
                $resources[$i] = ($realPackage['hours']) *  $productionAvg;
            }
        }

        $db->begin_transaction();
        $db->query($q = "UPDATE vdata SET wood=wood+{$resources[0]}, clay=clay+{$resources[1]}, iron=iron+{$resources[2]}, crop=crop+{$resources[3]} WHERE kid=" . Session::getInstance()->getSelectedVillageID());
        ResourcesHelper::updateVillageResources(Village::getInstance()->getKid(), true);
        $coins = $realPackage['coins'];
        if (!$this->spendGold($coins, true)) {
            $db->rollback();
            return;
        }
        $db->query("UPDATE users SET lastBuyResources=" . time() . " WHERE id=" . Session::getInstance()->getPlayerId());
        $db->commit();
        $this->response = [];
        $this->response['functionToCall'] = 'productPurchased';
        $this->response['options']['type'] = 'resources';
        $this->response['options']['newGold'] = Session::getInstance()->getGold() - $coins;
    }

    /**
     * @param string $featureKey
     */
    private function buyTroops($featureKey): void
    {
        $config = Config::getInstance();
        $race = Session::getInstance()->getRace();
        $rate = Config::getProperty("game", "useNanoseconds") ? 1e9 : (Config::getProperty("game", "useMilSeconds") ? 1e3 : 1);

        if (!preg_match('/^buyTroops([1-9])([0-9]+)$/', $featureKey, $featureMatch)) {
            $this->unavailablePremiumAction();
            return;
        }
        $troopId = (int)$featureMatch[1];
        $packageId = (int)$featureMatch[2];
        $unitId = nrToUnitId($troopId, $race);

        $packages = $config->extraSettings->buyTroops['packages'][$troopId] ?? null;
        //$realPackageId = array_search($packageId, array_column($packages, 'id'));
        $realPackage = is_array($packages) ? ($packages[$packageId] ?? null) : null;

        if (Village::getInstance()->isWW() || !$config->extraSettings->buyTroops['enabled']) {
            $this->unavailablePremiumAction();
            return;
        }
        if (!isset($realPackage)) {
            $this->unavailablePremiumAction();
            return;
        }
        $buyInterval = $config->extraSettings->buyTroops['buyInterval'];
        if ($buyInterval > 0 && (time() - Session::getInstance()->get("lastBuyTroops")) < $buyInterval) {
            $timeUnit = TimezoneHelper::getIntervalUnit($buyInterval - (time() - Session::getInstance()->get("lastBuyTroops")));
            $this->response['error'] = true;
            $this->response['message'] = sprintf(
                T(
                    "PaymentWizard",
                    'You need to wait %s %s before buying this package'
                ),
                $timeUnit['time'],
                $timeUnit['unit']
            );
            return;
        }
        $db = DB::getInstance();

        $unitAmt =  round(($realPackage['hours'] * 3600 * $rate) / Formulas::uTrainingTime($unitId, 20));
        $coins = $realPackage['coins'];

        $db->begin_transaction();
        if (!$this->spendGold($coins, true)) {
            $db->rollback();
            return;
        }
        $db->query("UPDATE users SET lastBuyTroops=" . time() . " WHERE id=" . Session::getInstance()->getPlayerId());
        //$db->query("UPDATE units SET u1=u1+{$units[0]}, u2=u2+{$units[1]}, u3=u3+{$units[2]}, u4=u4+{$units[3]}, u5=u5+{$units[4]}, u6=u6+{$units[5]}, u7=u7+{$units[6]}, u8=u8+{$units[7]} WHERE kid=" . Session::getInstance()->getKid());
        $db->query("UPDATE units SET u{$troopId}=u{$troopId}+{$unitAmt} WHERE kid=" . Session::getInstance()->getKid());
        ResourcesHelper::updateVillageResources(Village::getInstance()->getKid(), false);
        $db->commit();
        $this->response = [];
        $this->response['functionToCall'] = 'productPurchased';
        $this->response['options']['type'] = 'troops';
        $this->response['options']['newGold'] = Session::getInstance()->getGold() - $coins;
    }

    /**
     * @param string $featureKey
     */
    private function buyBuildings($featureKey): void
    {
        $config = Config::getInstance();
        $village = Village::getInstance();
        $packageId = preg_replace('/buyBuildings/', '', $featureKey);

        $packages = $config->extraSettings->buyBuildings['packages'];
        $realPackage = $packages[$packageId] ?? null;
        if (!$realPackage) {
            $this->unavailablePremiumAction();
            return;
        }
        if (!$config->extraSettings->buyBuildings['enabled'] || !$realPackage->enabled) {
            $this->unavailablePremiumAction();
            return;
        }
        $coins = ($village->isCapital() && isset($realPackage->coinsCapital)) ? $realPackage->coinsCapital : $realPackage->coins;
        $bid = $realPackage->bid;
        $buildingGid = preg_match('/^[0-9]+$/D', (string)$bid) === 1 ? (int)$bid : 0;
        if (!FinishConstruction::canFinishWithGold($buildingGid, $village->isWW())) {
            $this->unavailablePremiumAction();
            return;
        }

        //if (Session::getInstance()->isReallyAdmin()) return;

        if (substr($bid, 0, 3) == 'res') {
            //$this->response = [];
            //$this->response['redirectTo'] = '/dorf1.php';

            $resourceLevel = preg_replace('/[^0-9]/', '', $bid);
            if ($resourceLevel >= 25 && !$village->isCapital()) {
                $this->unavailablePremiumAction();
                return;
            }
            $done = true;
            $max = $resourceLevel;
            for ($i = 1; $i <= 18; ++$i) {
                if ($village->getField($i)['level'] >= $max) {
                    $done &= true;
                } else {
                    $done &= false;
                }
            }
            if ($done) {
                $this->unavailablePremiumAction();
                return;
            }

            if (!$this->spendGold($coins)) {
                return;
            }
            for ($i = 1; $i <= 18; ++$i) {
                $up = max($resourceLevel - $village->getField($i)['level'], 0);
                if ($up) {
                    $village->buildings[$i]['level'] += $up;
                    (new BuildingAction())->upgrade($village->getKid(), $i, $up, true);
                }
            }
        } else {
            $db = DB::getInstance();
            //$this->response = [];
            //$this->response['redirectTo'] = '/dorf2.php';

            if (in_array($bid, array(10,11,38,39))) {
                $alreadyBuilt = $village->findBuildingsByGid($bid);
                $fieldId = 0;
                foreach ($alreadyBuilt as $index => $building) {
                    if ($building['level'] != 20) {
                        $fieldId = $index;
                        continue;
                    }
                }

            } else {
                $fieldId = $village->findBuildingByGid($bid);
                if ($fieldId != 0 && $village->getField($fieldId)['level'] == 20) {
                    $this->unavailablePremiumAction();
                    return;
                }
            }
            if ($fieldId == 0 && empty($village->getEmptyFields())) {
                $this->unavailablePremiumAction();
                return;
            }


            if (!$this->spendGold($coins)) {
                return;
            }
            if ($fieldId == 0) {
                if ($bid == 16) {
                    $fieldId = 39;
                } else {
                    $fieldId = $village->getEmptyFields()[0];
                }
                $db->query("UPDATE fdata SET f".$fieldId."t=".$bid." WHERE kid=" . $village->getKid());
                BuildingAction::upgrade($village->getKid(), $fieldId, 20);
            } else {
                $db->query("DELETE FROM building_upgrade WHERE kid={$village->getKid()} AND building_field=".$fieldId);
                $db->query("DELETE FROM demolition WHERE kid={$village->getKid()} AND building_field=".$fieldId);
                BuildingAction::upgrade($village->getKid(), $fieldId, 20 - $village->getField($fieldId)['level']);
            }
        }
        $this->response['options']['type'] = 'building';
        $this->response['options']['newGold'] = Session::getInstance()->getGold() - $coins;
    }

    public function VacationModeAbort(): void
    {
        if (!Session::getInstance()->isInVacationMode()) {
            $this->unavailablePremiumAction();
            return;
        }
        $db = DB::getInstance();
        $remainingSeconds = Session::getInstance()->getVacationTil() - time();
        $remainingDays = floor((Session::getInstance()->getVacationTil() - time()) / 86400);
        if ($remainingSeconds <= 0) {
            $this->unavailablePremiumAction();
            return;
        }
        if (!$this->spendGold(Config::getInstance()->gold->vacationAbortGold)) {
            return;
        }
        (new OptionModel())->abortVacation(Session::getInstance()->getPlayerId());
        if ($remainingDays >= 1) {
            $db->query("UPDATE users SET vacationUsedDays=vacationUsedDays-$remainingDays WHERE id=" . Session::getInstance()->getPlayerId());
        }
        $this->response['functionToCall'] = 'reloadUrl';
        $this->response['options']['html'] = '';
        $this->response['context'] = 13;
    }

    private function marketplace(): void
    {
        $session = Session::getInstance();
        $village = Village::getInstance();
        $failure = T('npc', 'exchange_failed');
        if (!$session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD)
            || ($session->banned() && $session->getPlayerId() > 2)
            || Config::getInstance()->dynamic->serverFinished) {
            $this->response['error'] = true;
            $this->response['message'] = T('inGame', 'bannedSmallPage');
            return;
        }
        if ($session->getTotalPopulation() < 40 || !$village->hasMarketPlace() || $village->isWW()) {
            $this->response['error'] = true;
            $this->response['message'] = $village->isWW()
                ? T('npc', 'disabled_in_ww')
                : T('GoldHelper', $session->getTotalPopulation() < 40
                    ? 'exchangeResources.error_low_pop'
                    : 'exchangeResources.error_no_marketplace');
            return;
        }

        $additionalData = $_POST['additionalData'] ?? [];
        $desiredInput = is_array($additionalData) ? ($additionalData['desired'] ?? null) : null;
        if (!is_array($desiredInput) || count($desiredInput) !== 4) {
            $this->response['error'] = true;
            $this->response['message'] = T('npc', 'invalid_distribution');
            return;
        }
        $desired = [];
        foreach (array_values($desiredInput) as $amount) {
            if (!is_scalar($amount) || !preg_match('/^(0|[1-9][0-9]{0,12})$/', (string)$amount)) {
                $this->response['error'] = true;
                $this->response['message'] = T('npc', 'invalid_distribution');
                return;
            }
            $desired[] = (int)$amount;
        }

        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            $this->response['error'] = true;
            $this->response['message'] = $failure;
            return;
        }

        try {
            $uid = (int)$session->getPlayerId();
            $userStatement = $db->run(
                'SELECT bought_gold, gift_gold FROM users WHERE id=? FOR UPDATE',
                [$uid]
            );
            $userResult = $userStatement ? $userStatement->get_result() : false;
            $user = $userResult ? $userResult->fetch_assoc() : false;
            if (!$user) {
                throw new \DomainException($failure);
            }
            $goldCost = (int)Config::getInstance()->gold->exchangeResourcesGold;
            if (!getCustom('serverIsFreeGold') && (int)$user['bought_gold'] + (int)$user['gift_gold'] < $goldCost) {
                throw new \DomainException(T('npc', 'not_enough_gold'));
            }

            $kid = (int)$village->getKid();
            $villageStatement = $db->run(
                'SELECT owner, wood, clay, iron, crop, woodp, clayp, ironp, cropp, maxstore, maxcrop, pop, upkeep, lastmupdate FROM vdata WHERE kid=? FOR UPDATE',
                [$kid]
            );
            $villageResult = $villageStatement ? $villageStatement->get_result() : false;
            $stock = $villageResult ? $villageResult->fetch_assoc() : false;
            if (!$stock) {
                throw new \DomainException($failure);
            }

            $now = miliseconds();
            $elapsed = max(0, $now - (int)$stock['lastmupdate']);
            $rawResources = [
                min((float)$stock['wood'] + $elapsed * (int)$stock['woodp'] / 3600000, (int)$stock['maxstore']),
                min((float)$stock['clay'] + $elapsed * (int)$stock['clayp'] / 3600000, (int)$stock['maxstore']),
                min((float)$stock['iron'] + $elapsed * (int)$stock['ironp'] / 3600000, (int)$stock['maxstore']),
                min((float)$stock['crop'] + $elapsed * ((int)$stock['cropp'] - (int)$stock['pop'] - (int)$stock['upkeep']) / 3600000, (int)$stock['maxcrop']),
            ];
            $current = array_map(static function ($amount) {
                return max(0, (int)floor($amount));
            }, $rawResources);
            $total = array_sum($current);
            $limits = [(int)$stock['maxstore'], (int)$stock['maxstore'], (int)$stock['maxstore'], (int)$stock['maxcrop']];
            if ($total < 50 || array_sum($desired) <= 0) {
                throw new \DomainException(T('npc', 'invalid_distribution'));
            }
            // Stock can grow while the dialog is open. Preserve the selected
            // proportions and normalize them to the locked, current total.
            $desired = \Game\NpcExchange::scale($desired, $total);
            foreach ($desired as $index => $amount) {
                if ($amount > $limits[$index]) {
                    throw new \DomainException(T('npc', 'invalid_distribution'));
                }
            }
            if ($desired === $current) {
                throw new \DomainException(T('npc', 'choose_new_distribution'));
            }

            $newResources = [];
            foreach ($desired as $index => $amount) {
                $newResources[] = $rawResources[$index] - $current[$index] + $amount;
            }
            $update = $db->run(
                'UPDATE vdata SET wood=?, clay=?, iron=?, crop=?, lastmupdate=? WHERE kid=?',
                [$newResources[0], $newResources[1], $newResources[2], $newResources[3], $now, $kid]
            );
            if (!$update || $update->affected_rows < 0) {
                throw new \DomainException($failure);
            }
            if (!GoldHelper::decreaseGold($uid, $goldCost, true)) {
                throw new \DomainException(T('npc', 'not_enough_gold'));
            }
            if (!$db->commit()) {
                throw new \DomainException($failure);
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $this->response['error'] = true;
            $this->response['message'] = $exception instanceof \DomainException
                ? $exception->getMessage()
                : $failure;
            return;
        }

        GoldHelper::syncSessionGold($uid);
        $this->response['functionToCall'] = 'reloadUrl';
        $this->response['options']['html'] = "";
        $this->response['context'] = 8;
    }

    private function constructionMaster(): void
    {
        $this->unavailablePremiumAction();
    }

    private function completeGoldPurchase(bool $purchased): void
    {
        if (!$purchased) {
            $this->error(T('Auction', 'notEnoughGold'));
            return;
        }

        // Premium benefits update session and village state. Refresh the page so
        // the balance, active flags, and related controls reflect the purchase.
        $this->response['reload'] = true;
    }

    private function productionboostWood(): void
    {
        $session = Session::getInstance();
        $resourceId = 1;
        if (isset($_POST['toggleAutoprolong']) && $session->hasProductionBoost($resourceId)) {
            $m = new AutoExtendModel();
            $m->setAutoExtendState(
                $session->getPlayerId(),
                $resourceId + 1,
                $m->hasAutoExtend($session->getPlayerId(), $resourceId + 1) ? 0 : 1,
                $session->productionBoostTill($resourceId)
            );
            InfoBoxModel::invalidateUserInfoBoxCache(Session::getInstance()->getPlayerId());
            $this->response['functionToCall'] = 'reloadDialog';
            $this->response['options']['html'] = null;
            $this->response['context'] = null;
            return;
        }
        $m = new GoldHelper();
        $this->completeGoldPurchase($m->buyProductionBoost(1));
    }

    private function productionboostClay(): void
    {
        $session = Session::getInstance();
        $resourceId = 2;
        if (isset($_POST['toggleAutoprolong']) && $session->hasProductionBoost($resourceId)) {
            $m = new AutoExtendModel();
            $m->setAutoExtendState(
                $session->getPlayerId(),
                $resourceId + 1,
                $m->hasAutoExtend($session->getPlayerId(), $resourceId + 1) ? 0 : 1,
                $session->productionBoostTill($resourceId)
            );
            InfoBoxModel::invalidateUserInfoBoxCache(Session::getInstance()->getPlayerId());
            $this->response['functionToCall'] = 'reloadDialog';
            $this->response['options']['html'] = null;
            $this->response['context'] = null;
            return;
        }
        $m = new GoldHelper();
        $this->completeGoldPurchase($m->buyProductionBoost(2));
    }

    private function productionboostIron(): void
    {
        $session = Session::getInstance();
        $resourceId = 3;
        if (isset($_POST['toggleAutoprolong']) && $session->hasProductionBoost($resourceId)) {
            $m = new AutoExtendModel();
            $m->setAutoExtendState(
                $session->getPlayerId(),
                $resourceId + 1,
                $m->hasAutoExtend($session->getPlayerId(), $resourceId + 1) ? 0 : 1,
                $session->productionBoostTill($resourceId)
            );
            InfoBoxModel::invalidateUserInfoBoxCache(Session::getInstance()->getPlayerId());
            $this->response['functionToCall'] = 'reloadDialog';
            $this->response['options']['html'] = null;
            $this->response['context'] = null;

            return;
        }
        $m = new GoldHelper();
        $this->completeGoldPurchase($m->buyProductionBoost(3));
    }

    private function productionboostCrop(): void
    {
        $session = Session::getInstance();
        $resourceId = 4;
        if (isset($_POST['toggleAutoprolong']) && $session->hasProductionBoost($resourceId)) {
            $m = new AutoExtendModel();
            $m->setAutoExtendState(
                $session->getPlayerId(),
                $resourceId + 1,
                $m->hasAutoExtend($session->getPlayerId(), $resourceId + 1) ? 0 : 1,
                $session->productionBoostTill($resourceId)
            );
            InfoBoxModel::invalidateUserInfoBoxCache(Session::getInstance()->getPlayerId());

            $this->response['functionToCall'] = 'reloadDialog';
            $this->response['options']['html'] = null;
            $this->response['context'] = null;
            return;
        }
        $m = new GoldHelper();
        $this->completeGoldPurchase($m->buyProductionBoost(4));
    }

    private function plus(): void
    {
        $session = Session::getInstance();
        if (isset($_POST['toggleAutoprolong']) && $session->plusTill() > time()) {
            $m = new AutoExtendModel();
            $m->setAutoExtendState(
                $session->getPlayerId(),
                1,
                $m->hasAutoExtend($session->getPlayerId(), 1) ? 0 : 1,
                $session->plusTill()
            );
            InfoBoxModel::invalidateUserInfoBoxCache(Session::getInstance()->getPlayerId());
            $this->response['functionToCall'] = 'reloadDialog';
            $this->response['options']['html'] = null;
            $this->response['context'] = null;
            return;
        }
        $m = new GoldHelper();
        $this->completeGoldPurchase($m->buyPlus());
    }

    private function rearrangeBuildings(): void
    {
        $session = Session::getInstance();
        $village = Village::getInstance();
        $data = $_POST['additionalData'] ?? null;
        $did = is_array($data) && isset($data['did']) && is_scalar($data['did']) ? (int)$data['did'] : 0;
        $checker = is_array($data) && isset($data['c']) && is_string($data['c']) ? $data['c'] : '';
        $positions = is_array($data) ? ($data['buildingPositions'] ?? null) : null;
        if ($session->isInVacationMode() || $session->isSitter() || $village->isWW()) {
            $this->response['error'] = true;
            $this->response['message'] = T('Buildings', 'rearrangeMode.error');
            return;
        }
        if ($did !== (int)$village->getKid() || !is_array($positions) || !Session::claimAction($checker)) {
            $this->response['error'] = true;
            $this->response['message'] = T('Buildings', 'rearrangeMode.error');
            return;
        }
        $plan = BuildingRearranger::plan($positions, BuildingRearranger::busySlots($did), BuildingRearranger::slotTypes($did));
        if ($plan['error'] !== null) {
            $this->response['error'] = true;
            $this->response['message'] = T('Buildings', 'rearrangeMode.error');
            return;
        }
        // Gold and the swap commit together: a failed swap gives the gold back.
        $db = DB::getInstance();
        $db->begin_transaction();
        if (!GoldHelper::decreaseGold($session->getPlayerId(), Config::getInstance()->gold->rearrangeBuildingsGold, true)) {
            $db->rollback();
            $this->response['error'] = true;
            $this->response['message'] = T('Auction', 'notEnoughGold');
            return;
        }
        if (!BuildingRearranger::apply($did, $plan['target'])) {
            $db->rollback();
            $this->response['error'] = true;
            $this->response['message'] = T('Buildings', 'rearrangeMode.error');
            return;
        }
        $db->commit();
        $this->response['redirectTo'] = '/dorf2.php';
    }

    private function ChangeAccountName(): void
    {
        $m = new OptionModel();
        $config = Config::getInstance();
        if (Session::getInstance()->getTotalPopulation() >= $config->gold->changeName->impossibleAfterPopulation) {
            $this->unavailablePremiumAction();
            return;
        }
        $newName = is_string($_POST['accountNewName'] ?? null)
            ? strip_tags($_POST['accountNewName'])
            : '';
        $password = isset($_POST['accountPassword']) && is_string($_POST['accountPassword'])
            ? $_POST['accountPassword']
            : '';
        if (empty($newName) || empty($password)) {
            $this->response['error'] = true;
            $this->response['message'] = T("Options", "Please enter a new account name and confirmation password");
        } elseif (!Password::verify($password, (string)$_SESSION[Session::getInstance()->fixSessionPrefix('pw')])) {
            $this->response['error'] = true;
            $this->response['message'] = T("Options", "Confirmation password does not match");
        } else {
            $error = $m->doesNameMeetRequirements(Session::getInstance()->getName(), $newName);
            if ($error === 0) {
                if (!$this->spendGold(Config::getInstance()->gold->accountChangeNameGold)) {
                    return;
                }
                $_SESSION[Session::getInstance()->fixSessionPrefix('user')] = $newName;
                $m->changeName(Session::getInstance()->getPlayerId(), $newName);
                Session::getInstance()->setName($newName);
                Session::getInstance()->setTotalNameChanges(Session::getInstance()->getTotalNameChanges() + 1);
                $this->response['functionToCall'] = 'reloadUrl';
                $this->response['options']['html'] = '';
                $this->response['context'] = 7;
            } elseif ($error & OptionModel::NAME_BLACKLISTED) {
                $this->response['error'] = true;
                $this->response['message'] = T("Options", "Name black listed");
            } elseif ($error & OptionModel::NAME_EXISTS) {
                $this->response['error'] = true;
                $this->response['message'] = T("Options", "Name exists");
            } elseif ($error & OptionModel::NAME_SHORT) {
                $this->response['error'] = true;
                $this->response['message'] = T("Options", "Name too short");
            } elseif ($error & OptionModel::NAME_LONG) {
                $this->response['error'] = true;
                $this->response['message'] = T("Options", "Name too long");
            }
        }
    }

    private function goldclub(): void
    {
        if (Session::getInstance()->hasGoldClub()) {
            $this->unavailablePremiumAction();
            return;
        }
        $helper = new GoldHelper();
        $this->completeGoldPurchase($helper->buyGoldClub());
    }

    private function exchangeSilver(): void
    {
        $session = Session::getInstance();
        if (Config::getInstance()->auction->fakeAuction->SpreadOutRandomlyBetweenPlayers) {
            $this->response['error'] = true;
            $this->response['message'] = T("PaymentWizard", "This feature is disabled");
            return;
        }
        $additionalData = $_POST['additionalData'] ?? [];
        $coins = is_array($additionalData) ? ($additionalData['coins'] ?? null) : null;
        $coins = $coins ?? ($_POST['coins'] ?? null);
        $result = (new SilverExchange())->exchange($session, SilverExchange::GOLD_TO_SILVER, $coins);
        if (isset($result['error'])) {
            $this->response['error'] = true;
            $this->response['message'] = $result['error'] === 'notEnoughGold'
                ? T('Auction', 'notEnoughGold')
                : T('Auction', 'autoCorrect');
            return;
        }

        $this->response['functionToCall'] = 'showFinishedExchangeGoldToSilver';
        $this->response['options'] = $result;
        $this->response['options']['result'] = true;
        $this->response['options']['message'] = [
            'type' => 'info',
            'message' => '<img src="img/spacer.gif" class="gold" alt="' . T('Global', 'gold') . '" title="' . T('Global', 'gold') . '" /> '
                . $result['exchangedFrom'] . ' ' . T('Global', 'convertedTo')
                . ' <img src="img/spacer.gif" class="silver" alt="' . T('Global', 'silver') . '" title="' . T('Global', 'silver') . '" /> '
                . $result['exchangedTo'],
        ];
        $this->response['context'] = 17;
    }

    private function cancelTrainingQueue(): void
    {
        $config = Config::getInstance();
        if (!$config->extraSettings->generalOptions->cancelTrainingQueue->enabled) {
            $this->unavailablePremiumAction();
            return;
        }
        $session = Session::getInstance();
        $db = DB::getInstance();
        $db->begin_transaction();
        $db->query("DELETE FROM training WHERE kid={$session->getSelectedVillageID()}");
        if (!$db->affectedRows()) {
            $db->rollback();
            $this->unavailablePremiumAction();
            return;
        }
        $coins = $config->extraSettings->generalOptions->cancelTrainingQueue->coins;
        if (!$this->spendGold($coins, true)) {
            $db->rollback();
            return;
        }
        $db->commit();
        $this->response['functionToCall'] = 'reloadUrl';
        $this->response['context'] = 'paymentWizard';
    }

    private function increaseStorage(): void
    {
        $config = Config::getInstance();
        if (!$config->extraSettings->generalOptions->increaseStorage->enabled) {
            $this->unavailablePremiumAction();
            return;
        }
        $result = ExtraModules::runAction(
            'increaseStorage',
            $this->response,
            array_key_exists('additionalData', $_POST) ? $_POST['additionalData'] : []
        );
        $this->completeExtraModuleAction($result);
    }

    private function fasterTraining(): void
    {
        $config = Config::getInstance();
        if (!$config->extraSettings->generalOptions->fasterTraining->enabled) {
            $this->unavailablePremiumAction();
            return;
        }
        $db = DB::getInstance();
        $session = Session::getInstance();
        $duration = $config->extraSettings->generalOptions->fasterTraining->duration * 3600;
        if ($session->get("fasterTraining") > time()) {
            $duration = $session->get("fasterTraining") + $duration;
        } else {
            $duration += time();
        }
        $coins = $config->extraSettings->generalOptions->fasterTraining->coins;
        if (!$this->spendGold($coins)) {
            return;
        }
        $db->query("UPDATE users SET fasterTraining=$duration WHERE id=" . $session->getPlayerId());
        $this->response['functionToCall'] = 'reloadDialog';
        $this->response['context'] = 'paymentWizard';
    }

    private function academyResearchAll(): void
    {
        $this->completeExtraModuleAction(
            ExtraModules::runAction('academyResearchAll', $this->response, null)
        );
    }

    private function smithyUpgradeAllToMax(): void
    {
        $this->completeExtraModuleAction(
            ExtraModules::runAction('smithyUpgradeAllToMax', $this->response, null)
        );
    }

    /*private function rallyPointTo20()
    {
        $config = Config::getInstance();
        if (!$config->extraSettings->generalOptions->rallyPointTo20->enabled) {
            return;
        }
        if (Session::getInstance()->isReallyAdmin()) return;
        $this->response = [];
        $this->response['redirectTo'] = '/dorf2.php';
        $village = Village::getInstance();
        $db = DB::getInstance();
        if (!($village->getField(39)['item_id'] == 0 || $village->getField(39)['level'] < 20)) return;
        $coins = $config->extraSettings->generalOptions->rallyPointTo20->coins;
        if (GoldHelper::decreaseGold(Session::getInstance()->getPlayerId(), $coins)) {
            if ($village->getField(39)['item_id'] == 0) {
                $db->query("UPDATE fdata SET f39t=16 WHERE kid=" . $village->getKid());
                BuildingAction::upgrade($village->getKid(), 39, 20);
            } else if ($village->getField(39)['level'] < 20) {
                $db->query("DELETE FROM building_upgrade WHERE kid={$village->getKid()} AND building_field=39");
                $db->query("DELETE FROM demolition WHERE kid={$village->getKid()} AND building_field=39");
                BuildingAction::upgrade($village->getKid(), 39, 20 - $village->getField(39)['level']);
            }
        }
    }

    private function upgradeAllResourcesTo20()
    {
        $config = Config::getInstance();
        if (!$config->extraSettings->generalOptions->upgradeAllResourcesTo20->enabled) {
            return;
        }
        if (Session::getInstance()->isReallyAdmin()) return;
        $this->response = [];
        $this->response['redirectTo'] = '/dorf1.php';
        $village = Village::getInstance();
        $done = FALSE;
        $max = Formulas::buildingMaxLvl(1, $village->isCapital(), false);
        for ($i = 1; $i <= 18; ++$i) {
            $up = max($max - $village->getField($i)['level'], 0);
            if ($up) {
                $done = TRUE;
            }
        }
        if (!$done) {
            return;
        }
        $coins = $village->isCapital() ? $config->extraSettings->generalOptions->upgradeAllResourcesTo20->coinsCapital : $config->extraSettings->generalOptions->upgradeAllResourcesTo20->coinsNoNCapital;
        if (GoldHelper::decreaseGold(Session::getInstance()->getPlayerId(), $coins)) {
            for ($i = 1; $i <= 18; ++$i) {
                $up = max($max - $village->getField($i)['level'], 0);
                if ($up) {
                    $village->buildings[$i]['level'] += $up;
                    (new BuildingAction())->upgrade($village->getKid(), $i, $up);
                }
            }
        }
    }

    private function upgradeAllResourcesTo30()
    {
        $config = Config::getInstance();
        if (!$config->extraSettings->generalOptions->upgradeAllResourcesTo30->enabled) {
            return;
        }
        if (Session::getInstance()->isReallyAdmin()) return;
        $this->response = [];
        $this->response['redirectTo'] = '/dorf1.php';
        $village = Village::getInstance();
        if (!$village->isCapital()) return;
        $done = FALSE;
        $max = 30;
        for ($i = 1; $i <= 18; ++$i) {
            $up = max($max - $village->getField($i)['level'], 0);
            if ($up) {
                $done = TRUE;
            }
        }
        if (!$done) {
            return;
        }
        $coins = $config->extraSettings->generalOptions->upgradeAllResourcesTo30->coins;
        if (GoldHelper::decreaseGold(Session::getInstance()->getPlayerId(), $coins)) {
            for ($i = 1; $i <= 18; ++$i) {
                $up = max($max - $village->getField($i)['level'], 0);
                if ($up) {
                    $village->buildings[$i]['level'] += $up;
                    (new BuildingAction())->upgrade($village->getKid(), $i, $up, true);
                }
            }
        }
    }*/

    private function oneHourOfProduction(): void
    {
        $config = Config::getInstance();
        if (Village::getInstance()->isWW() || !$config->extraSettings->generalOptions->oneHourOfProduction->enabled) {
            $this->unavailablePremiumAction();
            return;
        }
        $village = Village::getInstance();
        $resources = $village->getCurrentResources();
        $prod = $village->getProduction();
        unset($prod[4]);
        $prod[3] = max($prod[3], 0); //- crop
        for ($i = 0; $i <= 3; ++$i) {
            if ($i <= 2) {
                $prod[$i] = $resources[$i] + $prod[$i] > $village->get("maxstore") ? $village->get("maxstore") - $resources[$i] : $prod[$i];
            } else {
                $prod[$i] = $resources[$i] + $prod[$i] > $village->get("maxcrop") ? $village->get("maxcrop") - $resources[$i] : $prod[$i];
            }
        }
        $coins = $config->extraSettings->generalOptions->oneHourOfProduction->coins;
        if (!array_sum($prod)) {
            $this->unavailablePremiumAction();
            return;
        }
        if (!$this->spendGold($coins)) {
            return;
        }
        $village->refund($prod);
        $this->response = [];
        $this->response['redirectTo'] = '/dorf1.php';
    }
}
