<?php

declare(strict_types=1);

namespace Controller\Ajax\troop;

use Controller\Ajax\AjaxBase;
use Core\Config;
use Core\Database\DB;
use Core\Session;
use Game\Gold\PurchaseNonce;
use Game\Hero\AdventureStarted;
use Game\Hero\AdventureTravel;
use Game\Hero\SessionHero;
use Model\AdventureModel;
use Model\MovementsModel;
use Model\Quest;
use function miliseconds;

/**
 * The reference's troop send, as far as the adventure list uses it: PUT signs the request (an X-Nonce header comes back),
 * POST with the same body and that X-Nonce sends the hero. Body: {action: "troopsSend", eventType: 50,
 * troops: [{t11: 1}], target: {adventureId}}. The answer names the arrival and the village the hero left.
 * The nonce is bound to the player and the signed body, and the first POST spends it.
 */
final class send extends AjaxBase
{
    private const EVENT_TYPE_ADVENTURE = '50';

    public function dispatch(): void
    {
        $session = $this->session;
        if (!$session->isValid()) {
            $this->error('You must be logged in.', 403);
            return;
        }
        $adventureId = $this->adventureId();
        if ($adventureId === null) {
            $this->error(T('map', 'please_resend_all_data'));
            return;
        }
        if ($session->isInVacationMode()) {
            $this->response['redirectTo'] = '/options/vacation';
            return;
        }
        $refusal = $this->refusal($adventureId);
        if ($refusal !== null) {
            $this->error($refusal);
            return;
        }

        $binding = ['action' => 'troop/send', 'uid' => (int) $session->getPlayerId(), 'adventureId' => $adventureId];
        switch ($this->method()) {
            case 'PUT':
                header('X-Nonce: ' . PurchaseNonce::issue('troop/send', $binding));
                return;
            case 'POST':
                $submitted = $_SERVER['HTTP_X_NONCE'] ?? null;
                if (!PurchaseNonce::consume('troop/send', $binding, is_string($submitted) ? $submitted : null)) {
                    $this->error(T('map', 'please_resend_all_data'));
                    return;
                }
                $this->sendHero($adventureId);
                return;
            default:
                $this->error('Unsupported request.', 405);
        }
    }

    /** The adventure of a well-formed hero adventure body, or null. The dispatcher hands every value over as a string. */
    private function adventureId(): ?int
    {
        $body = $this->body();
        $troops = $body['troops'] ?? null;
        $target = $body['target'] ?? null;
        if (($body['action'] ?? null) !== 'troopsSend'
            || ($body['eventType'] ?? null) !== self::EVENT_TYPE_ADVENTURE
            || $troops !== [['t11' => '1']]
            || !is_array($target) || !is_string($target['adventureId'] ?? null) || !ctype_digit($target['adventureId'])) {
            return null;
        }

        return (int) $target['adventureId'];
    }

    /** The reason the hero cannot leave now, or null. */
    private function refusal(int $adventureId): ?string
    {
        $session = $this->session;
        if ($session->banned()) {
            return T('inGame', 'bannedSmallPage');
        }
        if (Config::getInstance()->dynamic->serverFinished) {
            return T('RallyPoint', 'Errors.serverFinished');
        }
        if ((int) DB::getInstance()->fetchScalar('SELECT COUNT(id) FROM adventure WHERE id=? AND uid=? AND end=0', [$adventureId, $session->getPlayerId()], 0) !== 1) {
            return T('map', 'please_resend_all_data');
        }
        $session->hero->findHero();
        if ($session->hero->getHeroStatus() !== SessionHero::STATUS_HOME) {
            return (string) ($session->hero->getInventoryStatus() ?: $session->hero->getLongStatus());
        }
        if (!(new AdventureModel())->getHeroVillageRallyPoint((int) $session->hero->hero['kid'])) {
            return T('HeroAdventure', 'No rallyPoint');
        }

        return null;
    }

    private function sendHero(int $adventureId): void
    {
        $db = DB::getInstance();
        $session = $this->session;
        $heroKid = (int) $session->hero->hero['kid'];
        $kid = (int) $db->fetchScalar('SELECT kid FROM adventure WHERE id=? AND uid=? AND end=0', [$adventureId, $session->getPlayerId()], 0);
        $travel = (int) round(AdventureTravel::seconds($kid) * 1000);
        $start = miliseconds();
        if (!$db->query('UPDATE units SET u11=0 WHERE kid=' . $heroKid)) {
            $this->error(T('map', 'please_resend_all_data'), 500);
            return;
        }
        (new MovementsModel())->addMovement($heroKid, $kid, $session->getRace(), [1 => 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1], 0, 0, 0, 0, 0, MovementsModel::ATTACKTYPE_ADVENTURE, $start, $start + $travel);
        $session->hero->findHero();
        Quest::getInstance()->setQuestBitwise('battle', 1, Quest::QUEST_FINISHED);
        $arrival = (int) ceil(($start + $travel) / 1000);
        $this->response['troops'] = [['timeArrive' => $arrival, 'arrivalIn' => max(0, $arrival - time())]];
        $this->response['sourceVillage'] = ['id' => $heroKid];
        // Not part of the reference answer: the block the list is replaced with, so the page needs no further request.
        $this->response['html'] = AdventureStarted::html($heroKid, $kid, $arrival);
    }
}
