<?php

namespace Controller\Ajax\silver;

use Game\Account\DeletionLock;
use Controller\Ajax\AjaxBase;
use Core\Config;
use Core\Session;
use Game\Hero\SilverExchange;

final class exchange extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error(T('inGame', 'bannedSmallPage'), 403);
            return;
        }

        if (!$this->session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD)) {
            $this->error(T('inGame', 'sitterNoPermForGold'), 403);
            return;
        }

        if (($this->session->banned() && $this->session->getPlayerId() > 2)
            || Config::getInstance()->dynamic->serverFinished) {
            $this->error(T('inGame', 'bannedSmallPage'), 403);
            return;
        }

        $deletion = DeletionLock::refusal((int) $this->session->getPlayerId());
        if ($deletion !== null) {
            $this->error($deletion, 403);
            return;
        }

        if (Config::getInstance()->auction->fakeAuction->SpreadOutRandomlyBetweenPlayers) {
            $this->error(T('PaymentWizard', 'This feature is disabled'), 403);
            return;
        }

        $direction = $_POST['exTyp'] ?? null;
        $amount = $direction === SilverExchange::GOLD_TO_SILVER
            ? ($_POST['g'] ?? null)
            : ($_POST['s'] ?? null);
        $result = (new SilverExchange())->exchange($this->session, $direction, $amount);

        if (isset($result['error'])) {
            $this->respondWithExchangeError($result['error']);
            return;
        }

        $this->respondWithExchange($result, $direction);
    }

    private function respondWithExchangeError(string $error): void
    {
        $message = match ($error) {
            'notEnoughGold' => T('Auction', 'notEnoughGold'),
            'notEnoughSilver' => T('Auction', 'notEnoughSilver'),
            'invalid' => T('Auction', 'autoCorrect'),
            default => T('npc', 'exchange_failed'),
        };
        $balance = [
            'oldGold' => $this->session->getAvailableGold(),
            'oldSilver' => $this->session->getAvailableSilver(),
            'newGold' => $this->session->getAvailableGold(),
            'newSilver' => $this->session->getAvailableSilver(),
        ];
        $this->respondWithExchange($balance, null, 'error', $message);
    }

    /**
     * @param array{oldGold: int, oldSilver: int, newGold: int, newSilver: int, exchangedFrom?: int, exchangedTo?: int} $balance
     */
    private function respondWithExchange(array $balance, ?string $direction, string $messageType = 'info', ?string $message = null): void
    {
        foreach (['oldGold', 'oldSilver', 'newGold', 'newSilver'] as $key) {
            $value = $balance[$key];
            $this->response[$key] = $value;
        }

        if ($message === null && $direction === SilverExchange::GOLD_TO_SILVER) {
            $message = $this->exchangeMessage('gold', $balance['exchangedFrom'] ?? 0, 'silver', $balance['exchangedTo'] ?? 0);
        } elseif ($message === null) {
            $message = $this->exchangeMessage('silver', $balance['exchangedFrom'] ?? 0, 'gold', $balance['exchangedTo'] ?? 0);
        }

        $this->response['message'] = [
            'type' => $messageType,
            'message' => $message,
        ];
    }

    private function exchangeMessage(string $from, int $fromAmount, string $to, int $toAmount): string
    {
        $fromLabel = T('Global', $from);
        $toLabel = T('Global', $to);

        return '<img src="img/spacer.gif" class="' . $from . '" alt="' . $fromLabel . '" title="' . $fromLabel . '" /> '
            . $fromAmount . ' ' . T('Global', 'convertedTo')
            . ' <img src="img/spacer.gif" class="' . $to . '" alt="' . $toLabel . '" title="' . $toLabel . '" /> '
            . $toAmount;
    }
}
