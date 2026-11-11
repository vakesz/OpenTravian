<?php

declare(strict_types=1);

namespace Controller\Ajax\premium;

use Controller\Ajax\AjaxBase;
use Controller\Ajax\premium;
use Core\Config;
use Core\Village;
use Game\Gold\PurchaseNonce;

/**
 * The reference's two-step gold purchase: PUT signs it (answers the confirmation dialog as
 * {html} plus an X-Nonce header), POST with that X-Nonce executes it. The nonce is bound to
 * the action, the player, the village, the price and the target the dialog showed, and is
 * spent by the first execute attempt, so a replayed or stale POST changes nothing.
 */
abstract class InstantPurchase extends AjaxBase
{
    /** Route name, e.g. "instant-completion". */
    abstract protected function action(): string;

    abstract protected function price(): int;

    /**
     * What the confirmation dialog shows and the purchase acts on, or the refusal message.
     *
     * @return array<string, mixed>|string
     */
    abstract protected function target(): array|string;

    /** @param array<string, mixed> $target */
    abstract protected function dialogHtml(array $target): string;

    /**
     * Applies the purchase and spends the gold in one transaction.
     *
     * @param array<string, mixed> $target
     * @return string|null null on success, otherwise the refusal message or 'notEnoughGold'
     */
    abstract protected function perform(array $target): ?string;

    public function dispatch(): void
    {
        $session = $this->session;
        if (!$session->isValid()) {
            $this->error('You must be logged in.', 403);
            return;
        }
        if (!$session->checkSitterPermission(\Core\Session::SITTER_CAN_BUY_OR_SPEND_GOLD)) {
            $this->error(T('inGame', 'sitterNoPermForGold'), 403);
            return;
        }
        if (($session->banned() && $session->getPlayerId() > 2) || Config::getInstance()->dynamic->serverFinished) {
            $this->error(T('inGame', 'bannedSmallPage'), 403);
            return;
        }

        $target = $this->target();
        if (is_string($target)) {
            $this->error($target);
            return;
        }
        $price = $this->price();
        $binding = [
            'action' => $this->action(),
            'uid' => (int)$session->getPlayerId(),
            'kid' => (int)Village::getInstance()->getKid(),
            'price' => $price,
            'target' => $target,
        ];

        switch ($_SERVER['REQUEST_METHOD'] ?? '') {
            case 'PUT':
                $this->sign($binding, $target, $price);
                return;
            case 'POST':
                $this->execute($binding, $target);
                return;
            default:
                $this->error('Unsupported request.', 405);
        }
    }

    /**
     * @param array<string, mixed> $binding
     * @param array<string, mixed> $target
     */
    private function sign(array $binding, array $target, int $price): void
    {
        $gold = (int)$this->session->getAvailableGold();
        if ($price > 0 && !getCustom('serverIsFreeGold') && $gold < $price) {
            $this->response = array_merge($this->response, premium::renderBuyPackageSmallestPackageDialog($price - $gold));
            $this->error(T('Auction', 'notEnoughGold'));
            $this->response['error'] = 'notEnoughGold';
            return;
        }

        header('X-Nonce: ' . PurchaseNonce::issue($this->action(), $binding));
        $this->response['html'] = $this->dialogHtml($target);
    }

    /**
     * @param array<string, mixed> $binding
     * @param array<string, mixed> $target
     */
    private function execute(array $binding, array $target): void
    {
        $submitted = $_SERVER['HTTP_X_NONCE'] ?? null;
        if (!PurchaseNonce::consume($this->action(), $binding, is_string($submitted) ? $submitted : null)) {
            $this->error(T('map', 'please_resend_all_data'));
            return;
        }

        $refusal = $this->perform($target);
        if ($refusal === 'notEnoughGold') {
            $this->error(T('Auction', 'notEnoughGold'));
            $this->response['error'] = 'notEnoughGold';
        } elseif ($refusal !== null) {
            $this->error($refusal);
        }
    }
}
