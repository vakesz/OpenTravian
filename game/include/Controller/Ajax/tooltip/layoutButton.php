<?php

namespace Controller\Ajax\tooltip;

use Controller\Ajax\AjaxBase;
use Core\Config;
use Core\Database\DB;
use Core\Session;
use Core\Village;
use Game\AllianceBonus\AllianceBonus;
use Game\Formulas;
use Game\QuickLink;
use Model\AdventureModel;
use Model\AllianceModel;
use Model\AuctionModel;
use Model\MarketModel;
use Model\TrainingModel;

class layoutButton extends AjaxBase
{
    public function dispatch()
    {
        if (!Session::getInstance()->isValid()) {
            return;
        }
        $boxId = is_string($_POST['boxId'] ?? null) ? $_POST['boxId'] : '';
        $buttonId = is_string($_POST['buttonId'] ?? null) ? $_POST['buttonId'] : '';
        if ($boxId === 'hero') {
            if ($buttonId === 'adventure') {
                $this->response['result'] = false;
                $this->response['title'] = T("HeroGlobal", "Adventure");
                $m = new AdventureModel();
                $this->response['text'] = T("HeroGlobal", "available_adventures:") . " " . $m->getAdventureCount(Session::getInstance()->getPlayerId());
                $this->response['success'] = true;
            } elseif ($buttonId === 'auction') {
                $this->response['result'] = false;
                $this->response['title'] = T("HeroGlobal", "Auctions");
                $m = new AuctionModel();
                $max = $m->getPlayerMaximumBidCount(Session::getInstance()->getPlayerId());
                $outbid = $m->getPlayerOutBidCount(Session::getInstance()->getPlayerId());
                $this->response['text'] = T("HeroGlobal", "Auctions with maximum bid:") . ' ' . $max;
                $this->response['text'] .= '<br />' . T("HeroGlobal", "Auctions which you got outbid:") . ' ' . $outbid;
                $this->response['success'] = true;
            }
        } elseif ($boxId === 'alliance') {
            if ($buttonId === 'embassy') {
                $this->response['result'] = false;
                $this->response['title'] = T("embassyWhite", "embassy");
                $m = new AllianceModel();
                $this->response['text'] = T("embassyWhite", "invites to you:") . ' ' . $m->getInvitesCount(Session::getInstance()->getPlayerId()) . '<br />';
                $maxLvl = $m->getMaxPlayerEmbassyLvl(Session::getInstance()->getPlayerId())['embassy'];
                if ($maxLvl) {
                    $this->response['text'] .= T("embassyWhite", "max embassy level:") . $maxLvl;
                } else {
                    $this->response['text'] .= '<span class="warning">' . T("embassyWhite", "construct an embassy") . '</span>';
                }
                $this->response['success'] = true;
            }
        } elseif ($boxId == 'activeVillage') {
            if (in_array($buttonId, ['workshop', 'stable', 'barracks', 'market',])) {
                $this->response['result'] = false;
                $this->response['title'] = T("Buildings", ($buttonId == 'workshop' ? 21 : ($buttonId == 'stable' ? 20 : ($buttonId == 'barracks' ? 19 : 17))) . ".title");
                $this->response['text'] = '';
                if ($buttonId == 'market') {
                    $this->response['text'] = QuickLink::merchantsText();
                } else {
                    $TrainingModel = new TrainingModel();
                    if ($buttonId == 'stable') {
                        $trainingTime = $TrainingModel->getTotalTrainingTime(Session::getInstance()->getKid(), 20);
                        if (!Village::getInstance()->isCapital()) {
                            $trainingTime += $TrainingModel->getTotalTrainingTime(Session::getInstance()->getKid(), 30);
                        }
                    } elseif ($buttonId == 'workshop') {
                        $trainingTime = $TrainingModel->getTotalTrainingTime(Session::getInstance()->getKid(), 21);
                    } else {
                        $trainingTime = $TrainingModel->getTotalTrainingTime(Session::getInstance()->getKid(), 19);
                        if (!Village::getInstance()->isCapital()) {
                            $trainingTime += $TrainingModel->getTotalTrainingTime(Session::getInstance()->getKid(), 29);
                        }
                    }
                    if (Config::getProperty("game", "useNanoseconds")) {
                        $trainingTime /= 1e9;
                    } elseif (Config::getProperty("game", "useMilSeconds")) {
                        $trainingTime /= 1000;
                    }
                    if ($trainingTime == 0) {
                        $this->response['text'] = T("inGame", "noTroopInBeingTrained");
                    } else {
                        $this->response['text'] = sprintf(T("inGame", "TrainingTime"), secondsToString((int)$trainingTime));
                    }
                }
                $this->response['success'] = true;
            }
        } elseif (($villageId = $this->resolveIncomingTroopsVillageId()) !== null) {
            // Only the session player's own villages may be inspected.
            $session = Session::getInstance();
            $owner = DB::getInstance()->fetchScalar("SELECT owner FROM vdata WHERE kid=?", $villageId);
            if ($owner === false || $owner === null || (int)$owner !== (int)$session->getPlayerId()) {
                return;
            }
            // Like the reference: no title, one line per kind of incoming hostile movement.
            $counts = [];
            $rows = DB::getInstance()->query("SELECT attack_type, COUNT(*) AS c FROM movement WHERE to_kid=" . (int)$villageId . " AND mode=0 AND attack_type IN(3,4) GROUP BY attack_type");
            while ($rows && ($row = $rows->fetch_assoc())) {
                $counts[(int)$row['attack_type']] = (int)$row['c'];
            }
            $lines = [];
            if (!empty($counts[3])) {
                $lines[] = str_replace('[AMOUNT]', (string)$counts[3], T('inGame', 'incomingAttacksOnVillage'));
            }
            if (!empty($counts[4])) {
                $lines[] = str_replace('[AMOUNT]', (string)$counts[4], T('inGame', 'incomingRaidsOnVillage'));
            }
            $this->response['title'] = null;
            $this->response['text'] = implode('<br />', $lines);
        }
    }

    private function resolveIncomingTroopsVillageId(): ?int
    {
        if (isset($_POST['villageIds']) && is_array($_POST['villageIds'])) {
            $villageId = filter_var($_POST['villageIds'][0] ?? null, FILTER_VALIDATE_INT);

            return is_int($villageId) && $villageId > 0 ? $villageId : null;
        }
        if (isset($_POST['villageId'])) {
            $villageId = filter_var($_POST['villageId'], FILTER_VALIDATE_INT);

            return is_int($villageId) && $villageId > 0 ? $villageId : null;
        }

        return null;
    }
}
