<?php

namespace Game;

use Core\Config;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Village;
use Game\Buildings\BuildingAction;
use Model\AdventureModel;
use Model\FarmListModel;
use Model\TrainingModel;

use function getCustom;
use function getGameElapsedSeconds;
use function property_exists;
use function sprintf;

class ExtraModules
{
    public const ACTION_COMPLETED = 'completed';
    public const ACTION_NOT_ENOUGH_GOLD = 'notEnoughGold';
    public const ACTION_UNAVAILABLE = 'unavailable';
    private const ACTION_METHODS = [
        'addFarms' => 'addFarms',
        'finishTraining' => 'finishTraining',
        'academyResearchAll' => 'academyResearchAll',
        'smithyUpgradeAllToMax' => 'smithyUpgradeAllToMax',
        'smithyMaxLevel' => 'smithyMaxLevel',
        'upgradeToMaxLevel' => 'upgradeToMaxLevel',
        'buyAdventure' => 'buyAdventure',
        'increaseStorage' => 'increaseStorage',
    ];

    /**
     * @param string $action
     * @param mixed $params
     * @return mixed
     */
    public static function showButton($action, $params = null)
    {
        if (!self::actionExists($action)) {
            return null;
        }
        if (!self::isEnabled($action)) {
            return null;
        }
        if (!self::hasPermission()) {
            return self::disabledButton($action);
        }
        $action = $action . 'Button';
        return self::$action($params);
    }

    /**
     * @param mixed $action
     */
    public static function actionExists($action): bool
    {
        return is_string($action) && isset(self::ACTION_METHODS[$action]);
    }

    /**
     * @param mixed $name
     * @return mixed
     */
    public static function isEnabled($name)
    {
        // Gold max shortcuts (building, smithy per unit, smithy all) are on only while speedShortcuts is on.
        if (in_array($name, ['upgradeToMaxLevel', 'smithyMaxLevel', 'smithyUpgradeAllToMax'], true)) {
            return SpeedShortcuts::enabled();
        }
        $config = Config::getInstance();
        $base = $config->extraSettings;
        if (in_array(
            $name,
            ['finishTraining', 'buyAdventure', 'increaseStorage', 'smithyUpgradeAllToMax', 'academyResearchAll']
        )) {
            $base = $config->extraSettings->generalOptions;
        }
        return $base->$name->enabled;
    }

    /**
     * @return mixed
     */
    private static function hasPermission()
    {
        return Session::getInstance()->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD);
    }

    /**
     * @param mixed $name
     * @param string|null $title
     * @param bool $auto
     * @param bool $warning
     * @return mixed
     */
    private static function disabledButton($name, $title = null, $auto = true, $warning = false)
    {
        $extra = null;
        if (!$auto && $warning) {
            $title = '<span class="warning">' . $title . '</span>';
        }
        return getButton(
            [
            "type"    => "button",
            "class"   => "textButtonV1 gold disabled",
            "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
            "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
            "title"   => htmlspecialchars($auto ? ('<span class="warning">' . ($title == null ? (T(
                "inGame",
                "sitterNoPermForGold"
            )) : (T(
                "ExtraModules",
                'Errors.' . $title
            ))) . '</span>') : $title),
            "coins"   => self::getCoins($name),
        ],
            ["data" => ['title' => htmlspecialchars($title), "type" => "button",],],
            self::translate($name) . $extra
        );
    }

    /**
     * @param mixed $name
     * @return mixed
     */
    public static function getCoins($name)
    {
        $config = Config::getInstance();
        if ($name == 'finishTraining') {
            $coins = ((new TrainingModel())->calculatePriceForInstantTraining(Session::getInstance()->getSelectedVillageID()));
            return $coins;
        }
        $base = $config->extraSettings;
        if (in_array($name, ['buyAdventure', 'increaseStorage', 'smithyUpgradeAllToMax', 'academyResearchAll'])) {
            $base = $config->extraSettings->generalOptions;
        }
        if (!property_exists($base, $name) || !property_exists($base->$name, 'coins')) {
            return false;
        }
        return $base->$name->coins;
    }

    /**
     * @param mixed $name
     * @return mixed
     */
    private static function translate($name)
    {
        return T("ExtraModules", $name);
    }

    /**
     * @param string $action
     * @param mixed $response
     * @param mixed $params
     */
    public static function runAction($action, &$response, $params): string
    {
        if (!self::actionExists($action)) {
            return self::ACTION_UNAVAILABLE;
        }
        if (!self::isEnabled($action)) {
            return self::ACTION_UNAVAILABLE;
        }
        if (!self::hasPermission()) {
            return self::ACTION_UNAVAILABLE;
        }
        $method = self::ACTION_METHODS[$action];
        return self::$method($response, $params);
    }

    /**
     * @return mixed
     */
    public static function buyAdventureButton()
    {
        return self::enabledButton('buyAdventure');
    }

    /**
     * @param mixed $name
     * @param array<mixed>|array<string, mixed>|array<string, string> $extraData
     * @param string $onclick
     * @return mixed
     */
    private static function enabledButton($name, $extraData = [], $onclick = '')
    {
        $wayOfPayment = ['featureKey' => $name, 'context' => 'ExtraModules'];
        $wayOfPayment = array_merge($wayOfPayment, $extraData);
        $extra = null;
        $value = self::translate($name);
        $title = htmlspecialchars(self::translate($name));
        return getButton(
            [
            "type"  => "button",
            "class" => "textButtonV1 gold $name",
            'title' => $title,
            'coins' => self::getCoins($name),
        ],
            [
                "data" => [
                    "type"         => "button",
                    'value'        => $value,
                    'confirm'      => '',
                    'onclick'      => '',
                    'wayOfPayment' => $wayOfPayment,
                    'onClick'      => $onclick,
                ],
            ],
            self::translate($name) . $extra
        );
    }

    /**
     * @return mixed
     */
    public static function finishTrainingButton()
    {
        $config = Config::getInstance();
        if (!$config->extraSettings->generalOptions->finishTraining->enabled) {
            return null;
        }
        return self::enabledButton('finishTraining');
    }

    /**
     * @param mixed $i
     * @return mixed
     */
    /**
     * Gold "Upgrade to max level" on a building page: instant max for gold, no resources and no queue.
     *
     * @param mixed $building_field
     * @return bool|string|null
     */
    public static function upgradeToMaxLevelButton($building_field)
    {
        $village = Village::getInstance();
        $building = $village->getField((int)$building_field);
        if (!is_array($building)) {
            return null;
        }
        $itemId = (int)$building['item_id'];
        if (!FinishConstruction::canFinishWithGold($itemId, $village->isWW())) {
            return $village->isWW()
                ? '<br /><br />' . self::disabledButton('upgradeToMaxLevel', 'WWDisabled')
                : false;
        }
        if ($itemId === 40) {
            return '<br /><br />' . self::disabledButton('upgradeToMaxLevel', 'WWDisabled');
        }
        if ($itemId === 0 || ($itemId > 4 && (int)$building['level'] === 0)) {
            return null;
        }
        if ($village->checkArtifactDependencies($itemId) === 1) {
            return '<br /><br />' . self::disabledButton(
                'upgradeToMaxLevel',
                '<span class="warning">' . T('Buildings', 'errors.noGreatArtefact') . '</span>',
                false
            );
        }
        $lvl = (int)$building['level'] + (int)$building['upgrade_state'];
        foreach ($village->onLoadBuildings['master'] as $row) {
            if ((int)$building_field === (int)$row['building_field']) {
                $lvl++;
            }
        }
        $maxLvl = Formulas::buildingMaxLvl($itemId, $village->isCapital());
        if ((int)$building_field <= 18 && $village->isCapital()) {
            $maxLvl = 20;
        }
        if ($lvl >= $maxLvl) {
            return false;
        }
        $queueField = $itemId === 16 ? 39 : (in_array($itemId, Formulas::WALL_BUILDINGS, true) ? 40 : (int)$building_field);
        if ($village->getOnDemolishBuildingFieldId() === $queueField) {
            return false;
        }

        return '<br /><br />' . self::enabledButton('upgradeToMaxLevel', ['dataCallback' => 'getGidX']);
    }

    public static function smithyMaxLevelButton($i)
    {
        return self::enabledButton('smithyMaxLevel', ['dataCallback' => 'getNr' . $i]);
    }

    /**
     * @return mixed
     */
    public static function smithyUpgradeAllToMaxButton()
    {
        return self::enabledButton('smithyUpgradeAllToMax');
    }

    /**
     * @return mixed
     */
    public static function addFarmsButton()
    {
        $enableAfter = Config::getProperty("extraSettings", "addFarms", "enableAfter");
        if (Session::getInstance()->hasGoldClub() && getGameElapsedSeconds() > $enableAfter) {
            return self::enabledButton('addFarms');
        }
        $title = sprintf(
            T("ExtraModules", "addFarmsIsDisabledTill"),
            TimezoneHelper::autoDateString(time() + $enableAfter, true)
        );
        return self::disabledButton('addFarms', $title, false, true);
    }

    /**
     * @return mixed
     */
    public static function academyResearchAllButton()
    {
        return self::enabledButton('academyResearchAll');
    }

    /**
     * @param mixed $callback
     * @return mixed
     */
    public static function increaseStorageButton($callback)
    {
        return self::enabledButton('increaseStorage', ['dataCallback' => $callback]);
    }

    /**
     * @param mixed $response
     */
    public static function addFarms(&$response): string
    {
        $enableAfter = Config::getProperty("extraSettings", "addFarms", "enableAfter");
        if (!Session::getInstance()->hasGoldClub() || getGameElapsedSeconds() <= $enableAfter) {
            return self::ACTION_UNAVAILABLE;
        }
        $db = DB::getInstance();
        $db->begin_transaction();
        $f = new FarmListModel();
        $count = $f->addUniqueFarms(
            Session::getInstance()->getPlayerId(),
            Session::getInstance()->getSelectedVillageID(),
            100
        );
        if (!$count) {
            $db->rollback();
            return self::ACTION_UNAVAILABLE;
        }
        if (!self::reduceGold(__FUNCTION__, 1, true)) {
            $db->rollback();
            return self::ACTION_NOT_ENOUGH_GOLD;
        }
        $db->commit();
        return self::ACTION_COMPLETED;
    }

    /**
     * @param string $action
     * @param float|int $times
     */
    private static function reduceGold($action, $times = 1, bool $transactionAlreadyOpen = false): bool
    {
        $unitCost = self::getCoins($action);
        if ($unitCost === false) {
            return false;
        }
        $coins = $unitCost * $times;
        return GoldHelper::decreaseGold(Session::getInstance()->getPlayerId(), $coins, $transactionAlreadyOpen);
    }

    /**
     * @param mixed $response
     */
    public static function finishTraining(&$response): string
    {
        $config = Config::getInstance();
        if (!$config->extraSettings->generalOptions->finishTraining->enabled) {
            return self::ACTION_UNAVAILABLE;
        }
        $db = DB::getInstance();
        $uid = (int)Session::getInstance()->getPlayerId();
        $kid = (int)Session::getInstance()->getKid();
        if (!$db->begin_transaction()) {
            return self::ACTION_UNAVAILABLE;
        }
        try {
            $completed = (new TrainingModel())->completeAllTrainingInOpenTransaction($kid);
            if ($completed < 1) {
                $db->rollback();
                return self::ACTION_UNAVAILABLE;
            }
            if (!self::reduceGold(__FUNCTION__, 1, true)) {
                $db->rollback();
                return self::ACTION_NOT_ENOUGH_GOLD;
            }
            if (!$db->commit()) {
                $db->rollback();
                return self::ACTION_UNAVAILABLE;
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            throw $exception;
        }
        GoldHelper::syncSessionGold($uid);
        $response['reload'] = true;
        return self::ACTION_COMPLETED;
    }

    /**
     * @param mixed $response
     */
    public static function academyResearchAll(&$response): string
    {
        $kid = Session::getInstance()->getSelectedVillageID();
        $db = DB::getInstance();
        $db->begin_transaction();
        $db->query("DELETE FROM research WHERE mode=1 AND kid={$kid}");
        $db->query("UPDATE tdata SET u2=1, u3=1, u4=1, u5=1, u6=1, u7=1, u8=1, u9=1 WHERE kid=" . Session::getInstance()->getSelectedVillageID());
        if (!$db->affectedRows()) {
            $db->rollback();
            return self::ACTION_UNAVAILABLE;
        }
        if (!self::reduceGold(__FUNCTION__, 1, true)) {
            $db->rollback();
            return self::ACTION_NOT_ENOUGH_GOLD;
        }
        $db->commit();
        $response['reload'] = true;
        return self::ACTION_COMPLETED;
    }

    /**
     * @param mixed $response
     */
    public static function smithyUpgradeAllToMax(&$response): string
    {
        $db = DB::getInstance();
        $kid = Session::getInstance()->getSelectedVillageID();
        $db->begin_transaction();
        $db->query("DELETE FROM research WHERE mode=0 AND kid={$kid}");
        $db->query("UPDATE smithy SET u1=20, u2=20, u3=20, u4=20, u5=20, u6=20, u7=20, u8=20 WHERE kid=" . Session::getInstance()->getSelectedVillageID());
        if (!$db->affectedRows()) {
            $db->rollback();
            return self::ACTION_UNAVAILABLE;
        }
        if (!self::reduceGold(__FUNCTION__, 1, true)) {
            $db->rollback();
            return self::ACTION_NOT_ENOUGH_GOLD;
        }
        $db->commit();
        $response['reload'] = true;
        return self::ACTION_COMPLETED;
    }

    /**
     * WayOfPayment posts the dataCallback return value under additionalData. Callbacks that
     * themselves return {additionalData: {...}} therefore arrive nested; accept both shapes.
     *
     * @param mixed $additionalData
     * @return array<mixed>|null
     */
    private static function unwrapAdditionalData(mixed $additionalData): ?array
    {
        if (!is_array($additionalData)) {
            return null;
        }
        if (isset($additionalData['additionalData']) && is_array($additionalData['additionalData'])) {
            return $additionalData['additionalData'];
        }

        return $additionalData;
    }

    /**
     * @param mixed $response
     * @param mixed $additionalData
     */
    public static function smithyMaxLevel(&$response, $additionalData): string
    {
        $additionalData = self::unwrapAdditionalData($additionalData);
        if ($additionalData === null || !isset($additionalData['nr'])) {
            return self::ACTION_UNAVAILABLE;
        }
        $kid = Session::getInstance()->getSelectedVillageID();
        $nr = (int)$additionalData['nr'];
        if ($nr > 8 || $nr < 1) {
            return self::ACTION_UNAVAILABLE;
        }
        $db = DB::getInstance();
        $level = $db->fetchScalar("SELECT u{$nr} FROM smithy WHERE kid=" . Session::getInstance()->getSelectedVillageID());
        if ($level >= 20) {
            return self::ACTION_UNAVAILABLE;
        }
        $db->begin_transaction();
        $db->query("DELETE FROM research WHERE mode=0 AND kid={$kid} AND nr=$nr");
        $db->query("UPDATE smithy SET u{$nr}=20 WHERE kid=" . Session::getInstance()->getSelectedVillageID());
        if (!$db->affectedRows()) {
            $db->rollback();
            return self::ACTION_UNAVAILABLE;
        }
        if (!self::reduceGold(__FUNCTION__, 1, true)) {
            $db->rollback();
            return self::ACTION_NOT_ENOUGH_GOLD;
        }
        $db->commit();
        $response['reload'] = true;
        return self::ACTION_COMPLETED;
    }

    /**
     * Instantly sets a building to its max level for gold: no resources, no construction queue.
     *
     * @param mixed $response
     * @param mixed $additionalData
     */
    public static function upgradeToMaxLevel(&$response, $additionalData): string
    {
        $additionalData = self::unwrapAdditionalData($additionalData);
        if ($additionalData === null || !isset($additionalData['gid'])) {
            return self::ACTION_UNAVAILABLE;
        }
        if (Session::getInstance()->isReallyAdmin()) {
            return self::ACTION_UNAVAILABLE;
        }
        $buildingField = (int)$additionalData['gid'];
        if ($buildingField > 40 || $buildingField < 1) {
            return self::ACTION_UNAVAILABLE;
        }
        $village = Village::getInstance();
        $building = $village->getField($buildingField);
        if (!is_array($building)) {
            return self::ACTION_UNAVAILABLE;
        }
        $itemId = (int)$building['item_id'];
        if (!FinishConstruction::canFinishWithGold($itemId, $village->isWW()) || $itemId === 40) {
            return self::ACTION_UNAVAILABLE;
        }
        if ($itemId === 0 || ($itemId > 4 && (int)$building['level'] === 0)) {
            return self::ACTION_UNAVAILABLE;
        }
        if ($village->checkArtifactDependencies($itemId) === 1) {
            return self::ACTION_UNAVAILABLE;
        }
        $lvl = (int)$building['level'] + (int)$building['upgrade_state'];
        foreach ($village->onLoadBuildings['master'] as $row) {
            if ($buildingField === (int)$row['building_field']) {
                $lvl++;
            }
        }
        $maxLvl = Formulas::buildingMaxLvl($itemId, $village->isCapital());
        if ($buildingField <= 18 && $village->isCapital()) {
            $maxLvl = 20;
        }
        if ($lvl >= $maxLvl) {
            return self::ACTION_UNAVAILABLE;
        }
        $queueField = $itemId === 16 ? 39 : (in_array($itemId, Formulas::WALL_BUILDINGS, true) ? 40 : $buildingField);
        if ($village->getOnDemolishBuildingFieldId() === $queueField) {
            return self::ACTION_UNAVAILABLE;
        }
        if (!self::reduceGold(__FUNCTION__)) {
            return self::ACTION_NOT_ENOUGH_GOLD;
        }
        BuildingAction::upgrade($village->getKid(), $buildingField, $maxLvl - (int)$village->getField($buildingField)['level']);
        $db = DB::getInstance();
        $db->query("DELETE FROM building_upgrade WHERE kid={$village->getKid()} AND building_field={$buildingField}");
        $db->query("DELETE FROM demolition WHERE kid={$village->getKid()} AND building_field={$buildingField}");
        $response['data'] = [];
        $response['redirectTo'] = 'dorf' . ($buildingField <= 18 ? 1 : 2) . '.php';

        return self::ACTION_COMPLETED;
    }

    /**
     * @param mixed $response
     */
    public static function buyAdventure(&$response): string
    {
        if (!self::reduceGold(__FUNCTION__)) {
            return self::ACTION_NOT_ENOUGH_GOLD;
        }
        $total_adventures = Session::getInstance()->get('total_adventures');
        $adventure = new AdventureModel();
        $adventure->addAdventure(
            Session::getInstance()->getPlayerId(),
            $total_adventures,
            time(),
            true
        );
        $response['reload'] = true;
        return self::ACTION_COMPLETED;
    }

    /**
     * @param mixed $response
     * @param mixed $additionalData
     */
    public static function increaseStorage(&$response, $additionalData = null): string
    {
        $storage = Formulas::storeCAP(20);
        $db = DB::getInstance();
        //$gid = Village::getInstance()->getField($additionalData['gid'])['item_id'];
        /*
        $storage = Formulas::storeCAP(20);
        if ($gid == 10 || $gid == 38) {
            //warehouse
            $db->query("UPDATE vdata SET extraMaxstore=extraMaxstore+1, maxstore=maxstore+$storage WHERE kid=" . Session::getInstance()->getSelectedVillageID());
        } else {
            //granny
            $db->query("UPDATE vdata SET extraMaxcrop=extraMaxcrop+1, maxcrop=maxcrop+$storage WHERE kid=" . Session::getInstance()->getSelectedVillageID());
        }*/

        $times = abs(is_array($additionalData) && isset($additionalData['times']) ? (int)$additionalData['times'] : 1);
        $coins = self::getCoins(__FUNCTION__);

        $times = min(floor(Session::getInstance()->getGold() / $coins), $times);
        if ($times < 1) {
            return self::ACTION_NOT_ENOUGH_GOLD;
        }

        $storage *= $times;
        $db->begin_transaction();
        $db->query("UPDATE vdata SET extraMaxstore=extraMaxstore+$times, maxstore=maxstore+$storage WHERE kid=" . Session::getInstance()->getSelectedVillageID());
        $db->query("UPDATE vdata SET extraMaxcrop=extraMaxcrop+$times, maxcrop=maxcrop+$storage WHERE kid=" . Session::getInstance()->getSelectedVillageID());
        if (!$db->affectedRows()) {
            $db->rollback();
            return self::ACTION_UNAVAILABLE;
        }
        if (!self::reduceGold(__FUNCTION__, $times, true)) {
            $db->rollback();
            return self::ACTION_NOT_ENOUGH_GOLD;
        }
        $db->commit();
        $response['reload'] = true;
        return self::ACTION_COMPLETED;
    }
}
