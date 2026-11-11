<?php

declare(strict_types=1);

namespace Controller\Ajax\marketplace;

use Game\Account\DeletionLock;
use Controller\Ajax\AjaxBase;
use Core\Config;
use Core\Session;
use Core\Village;

/** Normalizes an NPC-exchange distribution without changing village stock. */
final class exchangeResources extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()
            || !$this->session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD)
            || ($this->session->banned() && $this->session->getPlayerId() > 2)
            || $this->session->isInVacationMode()
            || Config::getInstance()->dynamic->serverFinished) {
            $this->error(T('inGame', 'bannedSmallPage'), 403);
            return;
        }

        $deletion = DeletionLock::refusal((int) $this->session->getPlayerId());
        if ($deletion !== null) {
            $this->error($deletion, 403);
            return;
        }

        $village = Village::getInstance();
        $did = $_POST['did'] ?? null;
        if (!is_scalar($did)
            || !ctype_digit((string)$did)
            || (int)$did !== (int)$village->getKid()
            || !$village->hasMarketPlace()
            || $village->isWW()
            || $this->session->getTotalPopulation() < 40) {
            $this->error(T('npc', 'invalid_distribution'));
            return;
        }

        $desired = $this->desiredDistribution($_POST['desired'] ?? null);
        if ($desired === null) {
            $this->error(T('npc', 'invalid_distribution'));
            return;
        }

        $resources = array_map(static fn ($amount): int => max(0, (int)floor($amount)), $village->getCurrentResources());
        $total = array_sum($resources);
        if ($total < 50 || array_sum($desired) < 1) {
            $this->error(T('npc', 'invalid_distribution'));
            return;
        }

        $this->response['resources'] = $resources;
        $this->response['distributed'] = \Game\NpcExchange::scale($desired, $total);
    }

    /** @return array{0: int, 1: int, 2: int, 3: int}|null */
    private function desiredDistribution(mixed $desired): ?array
    {
        if (!is_array($desired) || count($desired) !== 4) {
            return null;
        }

        $result = [];
        foreach (array_values($desired) as $amount) {
            if (!is_scalar($amount) || !preg_match('/^(0|[1-9][0-9]{0,12})$/', (string)$amount)) {
                return null;
            }
            $result[] = (int)$amount;
        }

        return $result;
    }
}
