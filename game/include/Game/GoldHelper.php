<?php

namespace Game;

use Core\Config;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Village;
use Game\Buildings\BuildingAction;
use Model\AutoExtendModel;
use Model\DailyQuestModel;
use Model\InfoBoxModel;
use Model\OptionModel;
use Model\SeasonPassModel;
use Model\VillageModel;

use function preg_replace;

class GoldHelper
{
    public function __construct()
    {
    }

    public function buyPlus(): bool
    {
        $config = Config::getInstance();
        $purchase = $this->purchasePremiumFeature(
            1,
            'plus',
            (int)$config->gold->plusAccountDurationSeconds,
            (int)$config->gold->plusGold
        );
        if ($purchase === false) {
            return false;
        }

        Session::getInstance()->setPlusTill($purchase['showTo']);
        InfoBoxModel::invalidateUserInfoBoxCache(Session::getInstance()->getPlayerId());
        return true;
    }

    /**
     * @param mixed $uid
     * @param mixed $num
     */
    public static function decreaseGold($uid, $num, bool $transactionAlreadyOpen = false): bool
    {
        $uid = (int)$uid;
        $num = (int)$num;
        $boughtGold = null;
        $giftGold = null;
        $ownsTransaction = false;
        if (!getCustom("serverIsFreeGold")) {
            if ($uid <= 0 || $num <= 0) {
                return false;
            }
            $db = DB::getInstance();
            $ownsTransaction = !$transactionAlreadyOpen;
            if ($ownsTransaction && !$db->begin_transaction()) {
                return false;
            }

            try {
                $user = $db->query("SELECT bought_gold, gift_gold FROM users WHERE id=$uid FOR UPDATE");
                if (!$user || !$user->num_rows) {
                    if ($ownsTransaction) {
                        $db->rollback();
                    }
                    return false;
                }

                $user = $user->fetch_assoc();
                $boughtGold = max(0, (int)$user['bought_gold']);
                $giftGold = max(0, (int)$user['gift_gold']);
                if ($boughtGold + $giftGold < $num) {
                    if ($ownsTransaction) {
                        $db->rollback();
                    }
                    return false;
                }

                $spentGiftGold = min($giftGold, $num);
                $giftGold -= $spentGiftGold;
                $boughtGold -= $num - $spentGiftGold;

                if ($db->query("UPDATE users SET bought_gold=$boughtGold, gift_gold=$giftGold WHERE id=$uid") === false) {
                    if ($ownsTransaction) {
                        $db->rollback();
                    }
                    return false;
                }
                if ($ownsTransaction && !$db->commit()) {
                    $db->rollback();
                    return false;
                }
            } catch (\Throwable $exception) {
                if ($ownsTransaction) {
                    $db->rollback();
                }
                throw $exception;
            }
        }

        // Nested callers sync after their own commit so a later rollback cannot
        // leave the session showing spent gold that never left the database.
        if (!getCustom('serverIsFreeGold') && $ownsTransaction) {
            self::syncSessionGold($uid, $boughtGold, $giftGold);
        }
        $dailyQuest = new DailyQuestModel();
        $dailyQuest->setQuestAsCompleted($uid, 5);
        return true;
    }

    /**
     * Keep the request-scoped session balances aligned with a committed debit.
     */
    public static function syncSessionGold(int $uid, ?int $boughtGold = null, ?int $giftGold = null): void
    {
        if (getCustom('serverIsFreeGold')) {
            return;
        }
        $session = Session::getInstance();
        if ((int)$session->getPlayerId() !== $uid) {
            return;
        }
        if ($boughtGold === null || $giftGold === null) {
            $row = DB::getInstance()->query(
                "SELECT bought_gold, gift_gold FROM users WHERE id=$uid"
            );
            $user = $row ? $row->fetch_assoc() : null;
            if (!$user) {
                return;
            }
            $boughtGold = (int)$user['bought_gold'];
            $giftGold = (int)$user['gift_gold'];
        }
        $session->set('bought_gold', $boughtGold);
        $session->set('gift_gold', $giftGold);
    }

    /**
     * @param int $resourcesId
     */
    public function buyProductionBoost($resourcesId): bool
    {
        $config = Config::getInstance();
        $resourceId = (int)$resourcesId;
        if ($resourceId < 1 || $resourceId > 4) {
            return false;
        }
        $purchase = $this->purchasePremiumFeature(
            1 + $resourceId,
            'b' . $resourceId,
            (int)$config->gold->productionBoostDurationSeconds,
            (int)$config->gold->productionBoostGold
        );
        if ($purchase === false) {
            return false;
        }

        $session = Session::getInstance();
        $session->setProductionBoostTill($resourceId, $purchase['showTo']);
        if ($purchase['becameActive']) {
            (new VillageModel())->updateUserVillageResources($session->getPlayerId(), false);
        }
        $uid = (int)$session->getPlayerId();
        if ($uid > 2) {
            (new SeasonPassModel())->onMissionEvent($uid, 'resourceBoost');
        }
        InfoBoxModel::invalidateUserInfoBoxCache(Session::getInstance()->getPlayerId());
        return true;
    }

    /**
     * Debits and activates a player-purchased premium feature atomically.
     *
     * The previous two-step flow committed the Gold debit before writing the
     * feature expiry.  A failed write (or a concurrent renewal) could then
     * charge the player without granting the purchased time.
     *
     * @return array{showTo: int, becameActive: bool}|false
     */
    private function purchasePremiumFeature(int $autoExtendType, string $column, int $duration, int $cost): array|false
    {
        if (!in_array($column, ['plus', 'b1', 'b2', 'b3', 'b4'], true)
            || $duration <= 0
            || $cost <= 0) {
            return false;
        }

        $session = Session::getInstance();
        $uid = (int)$session->getPlayerId();
        $db = DB::getInstance();
        if ($uid <= 0 || !$db->begin_transaction()) {
            return false;
        }

        try {
            $result = $db->query("SELECT $column FROM users WHERE id=$uid FOR UPDATE");
            $user = $result ? $result->fetch_assoc() : null;
            if (!$user) {
                $db->rollback();
                return false;
            }

            $now = time();
            $currentExpiry = (int)$user[$column];
            $showTo = max($now, $currentExpiry) + $duration;
            $autoExtend = new AutoExtendModel();
            if (!self::decreaseGold($uid, $cost, true)
                || $db->query("UPDATE users SET $column=$showTo WHERE id=$uid") === false
                || !$autoExtend->setAutoExtendState(
                    $uid,
                    $autoExtendType,
                    $autoExtend->hasAutoExtend($uid, $autoExtendType),
                    $showTo
                )
                || !$db->commit()) {
                $db->rollback();
                return false;
            }
        } catch (\Throwable $exception) {
            $db->rollback();
            throw $exception;
        }

        return [
            'showTo' => $showTo,
            'becameActive' => $currentExpiry <= $now,
        ];
    }

    /**
     * @param bool $free
     */
    public function buyGoldClub($free = false): bool
    {
        $session = Session::getInstance();
        $db = DB::getInstance();
        $uid = (int)$session->getPlayerId();
        if (!$db->begin_transaction()) {
            return false;
        }

        try {
            $user = $db->query("SELECT goldclub FROM users WHERE id=$uid FOR UPDATE");
            if (!$user || !$user->num_rows) {
                $db->rollback();
                return false;
            }
            if ((int)$user->fetch_assoc()['goldclub'] === 1) {
                if (!$db->commit()) {
                    $db->rollback();
                    return false;
                }
                $session->setGoldClub(1);
                return true;
            }

            $gold = $free ? 0 : Config::getInstance()->gold->goldClubGold;
            if (!$free && !self::decreaseGold($uid, $gold, true)) {
                $db->rollback();
                return false;
            }

            if ($db->query("UPDATE users SET goldclub=1 WHERE id=$uid") === false
                || !$db->commit()
            ) {
                $db->rollback();
                return false;
            }

            $session->setGoldClub(1);
            return true;
        } catch (\Throwable $exception) {
            $db->rollback();
            throw $exception;
        }
    }

    /**
     * Completes eligible construction, research and demolition in one purchase
     * transaction. Gold is debited only after the locked queue effects apply so
     * a mid-loop failure cannot charge the player for a partial village update.
     * Research and demolition are finished in-request (same effect bodies as
     * Automation::processResearchTask / processDemolitionTask) instead of
     * deferring with end_time=0.
     *
     * @return 'completed'|'noOrders'|'worldWonder'|'notEnoughGold'
     */
    public function finishImmediatelyBuildings(): string
    {
        $db = DB::getInstance();
        $session = Session::getInstance();
        $village = Village::getInstance();
        if ($village->isWW()) {
            return 'worldWonder';
        }
        $kid = (int)$village->getKid();
        $uid = (int)$session->getPlayerId();
        $cost = (int)Config::getInstance()->gold->finishNowGold;

        if (!$db->begin_transaction()) {
            return 'noOrders';
        }

        try {
            $buildingResult = $db->query(
                "SELECT id, building_field FROM building_upgrade WHERE kid=$kid AND isMaster=0 ORDER BY id ASC FOR UPDATE"
            );
            if ($buildingResult === false) {
                throw new \RuntimeException('Unable to lock building queue for finishNow.');
            }
            $buildings = [];
            while ($row = $buildingResult->fetch_assoc()) {
                $fieldId = (int)$row['building_field'];
                $itemId = (int)$village->getField($fieldId)['item_id'];
                if (!FinishConstruction::canFinishWithGold($itemId, $village->isWW())) {
                    continue;
                }
                $buildings[] = [
                    'id' => (int)$row['id'],
                    'building_field' => $fieldId,
                ];
            }

            $researchResult = $db->query("SELECT * FROM research WHERE kid=$kid ORDER BY id ASC FOR UPDATE");
            if ($researchResult === false) {
                throw new \RuntimeException('Unable to lock research queue for finishNow.');
            }
            $researchRows = [];
            while ($row = $researchResult->fetch_assoc()) {
                $researchRows[] = $row;
            }

            $demolitionResult = $db->query("SELECT * FROM demolition WHERE kid=$kid ORDER BY id ASC FOR UPDATE");
            if ($demolitionResult === false) {
                throw new \RuntimeException('Unable to lock demolition queue for finishNow.');
            }
            $demolitionRows = [];
            while ($row = $demolitionResult->fetch_assoc()) {
                $demolitionRows[] = $row;
            }

            if ($buildings === [] && $researchRows === [] && $demolitionRows === []) {
                $db->rollback();
                return 'noOrders';
            }

            foreach ($buildings as $onload) {
                BuildingAction::upgrade($kid, $onload['building_field']);
                $db->query('DELETE FROM building_upgrade WHERE id=' . $onload['id']);
                if ($db->affectedRows() !== 1) {
                    throw new \RuntimeException('Building queue row disappeared during finishNow.');
                }
                unset($village->onLoadBuildings['normal'][$onload['id']]);
            }

            foreach ($researchRows as $row) {
                $nr = (int)$row['nr'];
                $mode = (int)$row['mode'];
                if ($mode === 1 && $nr >= 2 && $nr <= 9) {
                    $db->query("UPDATE tdata SET u$nr=1 WHERE kid=$kid");
                } elseif ($mode === 0 && $nr >= 1 && $nr <= 8) {
                    $db->query("UPDATE smithy SET u$nr=IF(u$nr+1>20, 20, u$nr+1) WHERE kid=$kid");
                } else {
                    throw new \RuntimeException('Malformed research task during finishNow.');
                }
                $db->query('DELETE FROM research WHERE id=' . (int)$row['id']);
                if ($db->affectedRows() !== 1) {
                    throw new \RuntimeException('Research queue row disappeared during finishNow.');
                }
            }

            foreach ($demolitionRows as $row) {
                BuildingAction::downgrade(
                    $kid,
                    (int)$row['building_field'],
                    1,
                    (bool)$row['complete']
                );
                $remaining = $db->query('SELECT id FROM demolition WHERE id=' . (int)$row['id'] . ' FOR UPDATE');
                if ($remaining === false) {
                    throw new \RuntimeException('Unable to verify demolition completion during finishNow.');
                }
                if ($remaining->num_rows) {
                    $db->query('DELETE FROM demolition WHERE id=' . (int)$row['id']);
                    if ($db->affectedRows() !== 1) {
                        throw new \RuntimeException('Demolition queue row disappeared during finishNow.');
                    }
                }
            }

            if (!self::decreaseGold($uid, $cost, true)) {
                $db->rollback();
                return 'notEnoughGold';
            }
            if (!$db->commit()) {
                $db->rollback();
                return 'notEnoughGold';
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            throw $exception;
        }

        self::syncSessionGold($uid);
        $village->recalculateBuildingTimes();
        return 'completed';
    }

    public function finishNowButton(): string
    {
        $village = Village::getInstance();
        if (!FinishConstruction::hasEligibleFinishNowOrder($village)) {
            return '';
        }
        $session = Session::getInstance();
        $config = Config::getInstance();
        $hasPerm = $session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD);
        if (!$hasPerm) {
            return '<div class="finishNow">' . getButton(
                [
                    "type"    => "button",
                    "class"   => "textButtonV1 gold disabled",
                    "coins"   => $config->gold->finishNowGold,
                    "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                    "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                    "title"   => htmlspecialchars('<span class="warning">' . T(
                        "inGame",
                        "sitterNoPermForGold"
                    ) . '</span>'),
                ],
                ["data" => ["type" => "button"]],
                T("Buildings", "finishNow.finishNow")
            ) . '</div>';
        }
        if ($village->isWW()) {
            return '<div class="finishNow">' . getButton(
                [
                    "type"    => "button",
                    "class"   => "textButtonV1 gold disabled",
                    "coins"   => $config->gold->finishNowGold,
                    "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                    "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                    "title"   => htmlspecialchars('<span class="warning">' . T(
                        "inGame",
                        "finishNowWWDisabled"
                    ) . '</span>'),
                ],
                ["data" => ["type" => "button"]],
                T("Buildings", "finishNow.finishNow")
            ) . '</div>';
        }

        return '<div class="finishNow">' . getButton(
            [
                "type"  => "button",
                "class" => "textButtonV1 gold ",
                "value" => T("Buildings", "finishNow.finishNow"),
                "onclick" => "Travian.Game.PremiumFeature.InstantCompletion.confirmDialog(); return false;",
            ],
            ["data" => ["type" => "button"]],
            T("Buildings", "finishNow.finishNow")
        ) . '</div>';
    }

    /**
     * @return mixed
     */
    /**
     * @param bool $dialog true in the information dialog: the button calls Travian.Game.PremiumFeature.GoldClub() like
     *                     the reference, which starts the same way of payment the payment wizard button starts
     */
    public function getGoldClubButton($dialog = false)
    {
        $session = Session::getInstance();
        $config = Config::getInstance();
        if ($session->hasGoldClub()) {
            return getButton(
                [
                "type"  => "button",
                "class" => "textButtonV2 buttonFramed prosButton goldclub rectangle withText gold disabled",
                "title" => htmlspecialchars(T("goldClubPopup", "Gold Club is active.")),
            ],
                ["data" => ["type" => "button"]],
                T("goldClubPopup", "active")
            );
        }
        if (!$session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD)) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV2 buttonFramed prosButton goldclub rectangle withText gold disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => htmlspecialchars('<span class="warning">' . T(
                    "inGame",
                    "sitterNoPermForGold"
                ) . '</span>'),
            ],
                ["data" => ["type" => "button"]],
                T("inGame", "plus." . ('activate'))
            );
        }

        $label = T("inGame", "plus." . ('activate'));
        $data = [
            "type"    => "button",
            'value'   => $label,
            "name" => "",
            "class" => "textButtonV2 buttonFramed prosButton goldclub rectangle withText gold",
            "title" => $label,
            'confirm' => '',
            'onclick' => '',
            "version" => "textButtonV2",
        ];
        $attributes = [
            "type"  => "button",
            "class" => "textButtonV2 buttonFramed prosButton goldclub rectangle withText gold",
            // The shop's button says what the Gold club lasts, as the reference tooltip does; the dialog keeps its label.
            'title' => $dialog ? $label : htmlspecialchars(T("inGame", "goldClub.activate now")),
            'coins' => $config->gold->goldClubGold,
        ];
        if ($dialog) {
            $attributes['value'] = $label;
            $attributes['onclick'] = $data['onclick'] = 'Travian.Game.PremiumFeature.GoldClub(); return false;';
        } else {
            $data['wayOfPayment'] = json_decode('{"featureKey":"goldclub","context":"goldclub"}');
        }

        return getButton($attributes, ["data" => $data], $label);
    }

    /**
     * @param bool $infoBox
     * @param bool $paymentWizard
     * @param bool $dialog true in the information dialog: the button calls Travian.Game.PremiumFeature.PlusAccount()
     *                     like the reference, which starts the same way of payment as the other Plus buttons
     * @return mixed
     */
    public function getPlusButton($infoBox = false, $paymentWizard = false, $dialog = false)
    {
        $session = Session::getInstance();
        $config = Config::getInstance();
        $hasPlus = $session->hasPlus();
        $autoExtend = new AutoExtendModel();
        if ($infoBox && $session->getAvailableGold() < $config->gold->plusGold && $autoExtend->hasAutoExtend($session->getPlayerId(), 1)) {
            return $this->renderBuyGoldButton();
        }
        $hasPerm = $session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD);
        $extra = $paymentWizard ? 'prosButton plus' : '';
        if (!$hasPerm) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV2 buttonFramed prosButton plus rectangle withText gold {$extra} disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => htmlspecialchars('<span class="warning">' . T(
                    "inGame",
                    "sitterNoPermForGold"
                ) . '</span>'),
                'coins'   => $config->gold->plusGold,
            ],
                [
                    "data" => [
                        "type"    => "button",
                        'value'   => T("inGame", "plus." . ($hasPlus ? 'extend' : 'activate')),
                        'confirm' => '',
                        'onclick' => '',
                    ],
                ],
                T("inGame", "plus." . ($hasPlus ? 'extend' : 'activate'))
            );
        }
        $label = T("inGame", "plus." . ($hasPlus ? 'extend' : 'activate'));
        $attributes = [
            "type"  => "button",
            "class" => "textButtonV2 buttonFramed prosButton plus rectangle withText gold {$extra}",
            'title' => htmlspecialchars(sprintf(T("inGame", "plus." . ($hasPlus ? 'extend now' : 'activate now')), $this->getDurationDays($config->gold->plusAccountDurationSeconds))),
            'coins' => $config->gold->plusGold,
        ];
        $data = [
            "type"    => "button",
            "class" => "textButtonV2 buttonFramed prosButton plus rectangle withText gold",
            'value'   => $label,
            'confirm' => '',
            'onclick' => '',
            "version" => "textButtonV2",
        ];
        if ($dialog) {
            $attributes['value'] = $label;
            $attributes['onclick'] = $data['onclick'] = 'Travian.Game.PremiumFeature.PlusAccount(); return false;';
        } elseif ($infoBox) {
            $data['wayOfPayment'] = ["featureKey" => "plus", "context" => "infobox"];
        } elseif ($paymentWizard) {
            $data['wayOfPayment'] = ["featureKey" => "plus", "context" => "paymentWizard"];
        } else {
            $data['wayOfPayment'] = json_decode('{"featureKey":"plus","context":"plus"}');
        }

        return getButton($attributes, ["data" => $data], $label);
    }

    /**
     * @return mixed
     */
    public function renderBuyGoldButton()
    {
        $session = Session::getInstance();
        $hasPerm = $session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD);
        $text = T("inGame", "Navigation.Buy gold");
        if (!$hasPerm) {
            return getButton(
                [
                "type"  => "button",
                "class" => "textButtonV1 green disabled",
            ],
                [
                    "data" => [
                        "type"    => "button",
                        'value'   => $text,
                        'confirm' => '',
                        'onclick' => '',
                    ],
                ],
                $text
            );
        }
        return getButton(
            [
            "type"  => "button",
            "class" => "textButtonV1 green",
        ],
            [
                "data" => [
                    'onclick' => 'jQuery(window).trigger(\'startPaymentWizard\', {}); return false;',
                    "type"    => "button",
                    'value'   => $text,
                    'confirm' => '',
                ],
            ],
            $text
        );
    }

    /**
     * @param mixed $time
     */
    public function getTime($time): string
    {
        if ($time >= 86400) {
            return '<span class="bold">' . round($time / 86400, 1) . '</span> ' . T("PaymentWizard", "Days");
        }
        return '<span class="bold">' . round($time / 3600, 1) . '</span> ' . T("PaymentWizard", "hour");
    }

    /**
     * @param mixed $time
     */
    /**
     * The duration of a bonus as the shop prints it, in whole hours ("72 hours" at x2), with the
     * number isolated. A bonus lasts at least a day, so this is never a single hour.
     */
    public function getDurationText(int|float $seconds): string
    {
        return "\u{202D}" . (int)round($seconds / 3600) . ' ' . T('Statistics', 'hours') . "\u{202C}";
    }

    /** The duration in days for the "Bonus duration in days: %s" texts: a number with at most one decimal. */
    public function getDurationDays(int|float $seconds): string
    {
        return (string)round($seconds / 86400, 1);
    }

    /**
     * @param mixed $featureKey
     * @param mixed $coins
     * @param mixed $wwInAvailable
     * @param bool $disabled
     * @return mixed
     */
    public function renderBuyButton($featureKey, $coins, $wwInAvailable, $disabled = false)
    {
        $title = '';
        $text = T("PaymentWizard", "Buy");
        $isMoreProtection = substr($featureKey, 0, 14) == 'moreProtection';
        $isBuyResources = substr($featureKey, 0, 12) == 'buyResources';
        $isBuyAnimal = substr($featureKey, 0, 9) == 'buyAnimal';
        $isBuyTroops = substr($featureKey, 0, 9) == 'buyTroops';
        $config = Config::getInstance();
        $m = new OptionModel();
        if ($isMoreProtection) {
            $allowWithArtifact = $config->extraSettings->moreProtection->allowWithArtifact;
            if (($m->hasPlayerWWVillage(Session::getInstance()->getPlayerId()) <> 0 || (!$allowWithArtifact && $m->hasPlayerArtifact(Session::getInstance()->getPlayerId()) <> 0))) {
                $disabled = true;
                $title = T("PaymentWizard", "You have a WW village or Artifact So you cannot use this feature");
            } else {
                $number = preg_replace('/[^0-9]/', '', $featureKey);
                $db = DB::getInstance();
                $session = Session::getInstance();
                if ((time() - $session->get("protectionLastExtend")) > 86400) {
                    $session->set("protectionLastExtend", 0);
                    $session->set("protectionBoughtHours", 0);
                    $db->query("UPDATE users SET protectionLastExtend=0, protectionBoughtHours=0 WHERE id={$session->getPlayerId()}");
                }
                if (!isset($config->extraSettings->moreProtection->packages[$number])) {
                    $disabled = true;
                    $title = 'Package not found';
                } else {
                    $feature = $config->extraSettings->moreProtection->packages[$number];
                    $maxDuration = $config->extraSettings->moreProtection->maxPerDay;
                    if ((time() - $session->get("protectionLastExtend")) < 86400 && ($feature['duration'] + $session->get("protectionBoughtHours")) > $maxDuration) {
                        $disabled = true;
                        $title = T("PaymentWizard", "Protection daily limit reached");
                    }
                }
            }
        } elseif ($isBuyResources || $isBuyTroops || $isBuyAnimal) {
            $waitTime = $buyInterval = 0;
            if ($isBuyTroops) {
                $buyInterval = $config->extraSettings->buyTroops['buyInterval'];
                $waitTime = $buyInterval - (time() - Session::getInstance()->get("lastBuyTroops"));
            } elseif ($isBuyAnimal) {
                $buyInterval = $config->extraSettings->buyAnimal['buyInterval'];
                $waitTime = $buyInterval - (time() - Session::getInstance()->get("lastBuyAnimals"));
            } elseif ($isBuyResources) {
                $buyInterval = $config->extraSettings->buyResources['buyInterval'];
                $waitTime = $buyInterval - (time() - Session::getInstance()->get("lastBuyResources"));
            }
            if ($buyInterval > 0 && $waitTime > 0) {
                $timeUnit = TimezoneHelper::getIntervalUnit($waitTime);
                $title = sprintf(
                    T("PaymentWizard", 'You need to wait %s %s before buying this package'),
                    $timeUnit['time'],
                    $timeUnit['unit']
                );
                $disabled = true;
            }
        }
        if ($disabled) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV2 buttonFramed prosButton rectangle withText gold " . $featureKey . " disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => $title,
                'coins'   => $coins,
            ],
                [
                    "data" => [
                        "type"    => "button",
                        'value'   => $text,
                        'confirm' => '',
                        'onclick' => '',
                    ],
                ],
                $text
            );
        }
        $session = Session::getInstance();
        $hasPerm = $session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD);
        if (!$hasPerm) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV2 buttonFramed prosButton rectangle withText gold " . $featureKey . " disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => htmlspecialchars('<span class="warning">' . T(
                    "inGame",
                    "sitterNoPermForGold"
                ) . '</span>'),
                'coins'   => $coins,
            ],
                [
                    "data" => [
                        "type"    => "button",
                        'value'   => $text,
                        'confirm' => '',
                        'onclick' => '',
                    ],
                ],
                $text
            );
        }
        if ($wwInAvailable && Village::getInstance()->isWW()) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV2 buttonFramed prosButton rectangle withText gold  " . $featureKey . " disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => htmlspecialchars('<span class="warning">' . T("PaymentWizard", "WWDisabled") . '</span>'),
                'coins'   => $coins,
            ],
                [
                    "data" => [
                        "type"    => "button",
                        'value'   => $text,
                        'confirm' => '',
                        'onclick' => '',
                    ],
                ],
                $text
            );
        }
        //supress ww here.
        $data = [
            'wayOfPayment' => [
                "featureKey" => $featureKey,
                "context"    => "paymentWizard",
            ],
        ];

        return getButton(
            [
            "type"  => "button",
            "class" => "textButtonV2 buttonFramed prosButton rectangle withText gold  " . $featureKey,
            'title' => $text,
            'coins' => $coins,
        ],
            [
                "data" => array_merge(
                    [
                    "type"    => "button",
                    'value'   => $text,
                    'confirm' => '',
                    'onclick' => '',
                ],
                    $data
                ),
            ],
            $text
        );
    }

    /**
     * @param mixed $resourcesId
     * @param bool $infoBox
     * @param bool $paymentWizard
     * @param bool $production
     * @return mixed
     */
    public function getProductionBoostButton($resourcesId, $infoBox = false, $paymentWizard = false, $production = false)
    {
        $session = Session::getInstance();
        $config = Config::getInstance();
        $m = new AutoExtendModel();
        $resClass = ['wood', 'clay', 'iron', 'crop'][$resourcesId - 1];
        if ($infoBox && $session->getAvailableGold() < $config->gold->productionBoostGold) {
            if ($m->hasAutoExtend($session->getPlayerId(), $resourcesId + 1)) {
                return $this->renderBuyGoldButton();
            }
        }
        $hasBoost = $session->hasProductionBoost($resourcesId);
        $hasPerm = $session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD);
        $extra = $paymentWizard ? 'prosButton' : '';
        if ($paymentWizard) {
            $resClass = ucfirst($resClass);
        }
        if (!$hasPerm) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV2 buttonFramed {$extra} " . ($paymentWizard ? 'productionboost' : 'productionBoostButton ') . "{$resClass} rectangle withText gold disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => htmlspecialchars('<span class="warning">' . T(
                    "inGame",
                    "sitterNoPermForGold"
                ) . '</span>'),
                'coins'   => $config->gold->productionBoostGold
            ],
                [
                    "data" => [
                        "type"    => "button",
                        'value'   => T("inGame", "productionBoost." . ($hasBoost ? 'extend' : 'activate')),
                        'confirm' => '',
                        'onclick' => '',
                    ],
                ],
                T("inGame", "productionBoost." . ($hasBoost ? 'extend' : 'activate'))
            );
        }
        //supress ww here.
        $data = [];
        if ($infoBox) {
            $data['wayOfPayment'] = [
                "featureKey" => "productionboost" . ucfirst($resClass),
                "context"    => "infobox",
            ];
        } elseif ($production) {
            $data['wayOfPayment'] = [
                "featureKey" => "productionboost" . ucfirst($resClass),
                "context"    => "productionBoost",
            ];
        } elseif ($paymentWizard) {
            $data['wayOfPayment'] = [
                "featureKey" => "productionboost" . ucfirst($resClass),
                "context"    => "paymentWizard",
            ];
        }

        $buttonLabel = $production
            ? T("productionOverview", $hasBoost ? 'extendNow' : 'activeNow')
            : T("inGame", "productionBoost." . ($hasBoost ? 'extend' : 'activate'));

        if ($production) {
            // The production overview shows a plain V2 text button: label, coin and price share one div.
            require SVG_COMMON_TEMPLATES;
            /** @var array<string, string> $svg */
            $buttonId = get_button_id();
            $payload = json_encode(array_merge(
                ['type' => 'button', 'value' => $buttonLabel, 'confirm' => '', 'onclick' => '', 'coins' => $config->gold->productionBoostGold, 'id' => $buttonId],
                $data
            ));

            return '<button class="textButtonV2 buttonFramed rectangle withText gold" type="button" id="' . $buttonId . '"><div>'
                . htmlspecialchars((string)$buttonLabel, ENT_QUOTES, 'UTF-8') . $svg['gold_button_small'] . '&nbsp;<span class="goldValue">'
                . (int)$config->gold->productionBoostGold . '</span></div></button>'
                . '<script type="text/javascript" id="' . $buttonId . '_script">jQuery(function() { jQuery(\'#' . $buttonId
                . '\').click(function (event) { jQuery(window).trigger(\'buttonClicked\', [this, ' . $payload . ']); }); });</script>';
        }

        return getButton(
            [
            "type"  => "button",
            "class" => "textButtonV2 buttonFramed {$extra} " . ($paymentWizard ? 'productionboost' : 'productionBoostButton ') . "{$resClass} rectangle withText gold",
            'title' => htmlspecialchars(sprintf(
                T(
                    "inGame",
                    "productionBoost." . ($hasBoost ? 'extend now' : 'activate now')
                ),
                $this->getDurationDays($config->gold->productionBoostDurationSeconds)
            )),
            'coins' => $config->gold->productionBoostGold,
        ],
            [
                "data" => array_merge(
                    [
                    "type"    => "button",
                    'value'   => $buttonLabel,
                    'confirm' => '',
                    'onclick' => '',
                ],
                    $data
                ),
            ],
            $buttonLabel
        );
    }

    /**
     * @return mixed
     */
    public function getCompleteDemolishButton()
    {
        if (Village::getInstance()->isWW() && !getGame("allowDemolishNowInWW")) {
            return null;
        }
        $session = Session::getInstance();
        $hasPerm = $session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD);
        $config = Config::getInstance();
        if (!$hasPerm) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV1 gold disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => htmlspecialchars('<span class="warning">' . T(
                    "inGame",
                    "sitterNoPermForGold"
                ) . '</span>'),
                "coins"   => $config->gold->completeDemolishGold,
            ],
                ["data" => ["type" => "button",]],
                T("Buildings", "mainBuilding.Demolish completely")
            );
        }

        return getButton(
            [
            "type"  => "button",
            "class" => "textButtonV1 gold ",
            "title" => T("Buildings", "mainBuilding.complete_demolish_title"),
            "coins" => $config->gold->completeDemolishGold,
            "onclick" => "Travian.Game.PremiumFeature.InstantDemolition.confirmDialog(getDemolishData()); return false;",
        ],
            [
                "data" => [
                    "type" => "button",
                ],
            ],
            T("Buildings", "mainBuilding.Demolish completely")
        );
    }

    /**
     * @param mixed $fieldId
     * @param mixed $item_id
     * @param (array<mixed>|float|int) $lvl
     * @param string $link
     * @return mixed
     */
    public function getMasterBuilderButton($fieldId, $item_id, $lvl, $link)
    {
        $village = Village::getInstance();
        $session = Session::getInstance();
        $config = Config::getInstance();
        $gold = $village->isWW() ? 0 : $config->gold->masterBuilderGold;
        $hasPerm = $session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD);
        $class = $item_id == 40 ? 'green' : 'gold';
        if (!$hasPerm) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV1 $class builder disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => htmlspecialchars('<span class="warning">' . T(
                    "inGame",
                    "sitterNoPermForGold"
                ) . '</span>'),
                "coins"   => $gold,
            ],
                ["data" => ["type" => "button"]],
                T("Buildings", "construct_with_master_builder")
            );
        }
        if (!$village->isWW() && $session->getAvailableGold() < $gold) {
            return getButton(
                [
                "type"  => "button",
                "class" => "textButtonV1 $class builder",
                "coins" => $gold,
            ],
                [
                    "data" => [
                        "type"         => "button",
                        "value"        => T("Buildings", "construct_with_master_builder"),
                        "wayOfPayment" => [
                            "featureKey" => 'constructionMaster',
                            'context'    => '',
                        ],
                        "title"        => '',
                        "confirm"      => '',
                    ],
                ],
                T("Buildings", "construct_with_master_builder")
            );
        }
        if (($village->isWW() && sizeof($village->onLoadBuildings['master']) >= $config->masterBuilder->maxTasksInWonder) || sizeof($village->onLoadBuildings['master']) >= $config->masterBuilder->maxTasksInNoneWonder) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV1 $class builder disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "coins"   => $gold,
                "onfocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => htmlspecialchars('<span class="warning">' . sprintf(
                    T(
                        "Buildings",
                        "maxMasterBuilderReached"
                    ),
                    $village->isWW() ? $config->masterBuilder->maxTasksInWonder : $config->masterBuilder->maxTasksInNoneWonder
                ) . '</span>'),
            ],
                ["data" => ["type" => "button"]],
                T("Buildings", "construct_with_master_builder")
            );
        }
        $needUpgradeType = $village->checkArtifactDependencies($item_id);
        if ($needUpgradeType <> 0) {
            switch ($needUpgradeType) {
                case 1:
                    $text = '<span class="warning">' . T("Buildings", "errors.noGreatArtefact") . '</span>';
                    break;
                case 2:
                case 3:
                    $text = '<span class="warning">' . T("Buildings", "errors.wwPlans") . '</span>';
                    break;
                default:
                    $text = '';
                    break;
            }

            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV1 $class builder disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "coins"   => $gold,
                "title"   => htmlspecialchars($text),
            ],
                ["data" => ["type" => "button"]],
                T("Buildings", "construct_with_master_builder")
            );
        }
        foreach ($village->onLoadBuildings['master'] as $k => $v) {
            if ($fieldId == $v['building_field']) {
                $lvl++;
            }
        }
        $needUpgradeType = $village->checkDependencies($item_id, $lvl);
        if ($needUpgradeType <> 0) {
            switch ($needUpgradeType) {
                case 1:
                    $text = "<span class=\"warning\">" . T("Buildings", "errors.foodShortage") . "</span>";
                    break;
                case 2:
                    $text = "<span class=\"warning\">" . T("Buildings", "errors.upgradeWareHouse") . "</span>";
                    break;
                case 22:
                    $text = "<span class=\"warning\">" . T("Buildings", "errors.constructWarehouse") . "</span>";
                    break;
                case 3:
                    $text = "<span class=\"warning\">" . T("Buildings", "errors.upgradeGranny") . "</span>";
                    break;
                case 33:
                    $text = "<span class=\"warning\">" . T("Buildings", "errors.constructGranny") . "</span>";
                    break;
                default:
                    $text = 'error';
                    break;
            }

            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV1 $class builder disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "coins"   => $gold,
                "title"   => htmlspecialchars($text),
            ],
                ["data" => ["type" => "button"]],
                T("Buildings", "construct_with_master_builder")
            );
        }
        if ($lvl >= Formulas::buildingMaxLvl($item_id, $village->isCapital())) {
            $text = "<span class=\"warning\">" . T("Buildings", $item_id . ".title") . " " . T(
                "Buildings",
                "upgradeNotices.reachedMaxLvL"
            ) . "</span>";

            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV1 $class builder disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "coins"   => $gold,
                "title"   => htmlspecialchars($text),
            ],
                ["data" => ["type" => "button"]],
                T("Buildings", "construct_with_master_builder")
            );
        } elseif ($lvl >= Formulas::buildingMaxLvl($item_id, $village->isCapital())) {
            $text = "<span class=\"warning\">" . sprintf(
                T("Buildings", "upgradeNotices.currentlyReachingMaxLevel"),
                T("Buildings", $item_id . ".title")
            ) . "</span>";

            return getButton(
                [
                "type"    => "button ",
                "class"   => "textButtonV1 $class builder disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "coins"   => $gold,
                "title"   => htmlspecialchars($text),
            ],
                ["data" => ["type" => "button"]],
                T("Buildings", "construct_with_master_builder")
            );
        } elseif ($village->getOnDemolishBuildingFieldId() == $fieldId) {
            $text = "<span class=\"warning\">" . T("Buildings", "upgradeNotices.buildingIsOnDemolition") . "</span>";

            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV1 $class builder disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "coins"   => $gold,
                "title"   => htmlspecialchars($text),
            ],
                ["data" => ["type" => "button"]],
                T("Buildings", "construct_with_master_builder")
            );
        }

        $label = T("Buildings", "construct_with_master_builder");
        $onclick = "this.disabled = true; window.location.href = '{$link}'; return false;";

        return '<button type="button" value="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '" id="' . get_button_id()
            . '" class="textButtonV1 ' . $class . ' builder" onclick="'
            . htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8')
            . '" coins="' . $gold . '" version="textButtonV1">' . "\n\t"
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '<i class="goldIcon"></i><span class="goldValue">' . $gold . '</span></button>';
    }

    /**
     * @param mixed $cost
     * @return mixed
     */
    public function getExchangeResourcesButtonByCost($cost)
    {
        $village = Village::getInstance();
        if ($cost !== -1) {
            if ($village->isResourcesAvailable($cost)) {
                return null;
            }
            $err = $village->contractResourcesLink($cost);
            if ($err['code'] > 0) {
                return null;
            }
        }
        if ($error = $this->ExchangeResourcesError()) {
            return $error;
        }
        $disabled = false;
        if ($cost !== -1) {
            $disabled = array_sum($village->getCurrentResources()) < array_sum($cost);
        }
        $defaults = $cost !== -1 ? [
            'r1' => $cost[0],
            'r2' => $cost[1],
            'r3' => $cost[2],
            'r4' => $cost[3],
        ] : [];

        return $this->getExchangeResourcesButton($defaults, $disabled);
    }

    /**
     * @param array<string, (float|int)>|array<string, mixed> $defaultValues
     * @param bool $disabled
     * @return mixed
     */
    private function getExchangeResourcesButton(array $defaultValues, $disabled = false)
    {
        $villageId = (int)Village::getInstance()->getKid();

        return getButton([
            "type"  => "button",
            "value" => T("GoldHelper", "exchangeResources.exchangeResources"),
            "class" => "textButtonV1 gold exchange" . ($disabled ? ' disabled' : ''),
            "title" => T("GoldHelper", "exchangeResources.exchangeResourcesClick"),
            "did"   => $villageId,
        ], [
            "data" => [
                "dialog" => [
                    "draggable" => false,
                    "overlayCancel" => true,
                    "buttonOk" => false,
                    // Local Dialog.Ajax still routes this legacy cmd; markup matches
                    // marketplace/exchange-resources HTML.
                    "cmd" => "exchange-resources-popup",
                    "data" => [
                        "defaultValues" => $defaultValues,
                        "did" => $villageId,
                    ],
                    "preventFormSubmit" => true,
                ],
            ],
        ], T("GoldHelper", "exchangeResources.exchangeResources"));
    }

    /**
     * @return mixed
     */
    private function ExchangeResourcesError()
    {
        $village = Village::getInstance();
        $session = Session::getInstance();
        $hasPerm = $session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD);
        if (!$hasPerm) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV1 gold exchange disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => htmlspecialchars('<span class="warning">' . T(
                    "inGame",
                    "sitterNoPermForGold"
                ) . '</span>'),
            ],
                [],
                T("GoldHelper", "exchangeResources.exchangeResources")
            );
        }
        if ($village->isWW()) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV1 gold exchange disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => T("GoldHelper", "exchangeResources.error_in_ww"),
            ],
                [],
                T("GoldHelper", "exchangeResources.exchangeResources")
            );
        }
        if (!$village->hasMarketPlace()) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV1 gold exchange disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => T("GoldHelper", "exchangeResources.error_no_marketplace"),
            ],
                [],
                T("GoldHelper", "exchangeResources.exchangeResources")
            );
        }
        if ($session->getTotalPopulation() < 40) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV1 gold exchange disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => T("GoldHelper", "exchangeResources.error_low_pop"),
            ],
                [],
                T("GoldHelper", "exchangeResources.exchangeResources")
            );
        }
        if (array_sum($village->getCurrentResources(-1, true)) < 50) {
            return getButton(
                [
                "type"    => "button",
                "class"   => "textButtonV1 gold exchange disabled",
                "onClick" => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
                "onFocus" => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
                "title"   => T("GoldHelper", "exchangeResources.error_low_resources"),
            ],
                [],
                T("GoldHelper", "exchangeResources.exchangeResources")
            );
        }

        return false;
    }

    /**
     * @param mixed $nr
     * @param bool $great
     * @return mixed
     */
    public function getExchangeResourcesButtonByNr($nr, $great)
    {
        if ($error = $this->ExchangeResourcesError()) {
            return $error;
        }
        $village = Village::getInstance();
        $helper = new TrainingHelper();
        $max = $helper->getMaxUnitByNr2($nr, $great);
        $cost = Formulas::uTrainingCost(nrToUnitId($nr, Session::getInstance()->getRace()), $great);
        $cur_res = $village->getCurrentResources();
        $disabled = array_sum($cur_res) < array_sum($cost);
        $defaults = [
            'r1' => $max * $cost[0],
            'r2' => $max * $cost[1],
            'r3' => $max * $cost[2],
            'r4' => $max * $cost[3],
        ];

        return $this->getExchangeResourcesButton($defaults, $disabled);
    }
}
