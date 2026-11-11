<?php

declare(strict_types=1);

namespace Controller\Ajax;

use Game\Account\DeletionLock;
use Core\Caching\Caching;
use Core\Database\GlobalDB;
use Core\Helper\WebService;
use Core\Session;
use Game\Payment\GoldPackages;
use Game\Payment\PaymentException;
use Game\Payment\PaymentOrders;
use Game\Payment\PaymentShop;
use Game\SocialAuth\OAuthFlowFactory;

use function getWorldUniqueId;
use function throttle_key;

/**
 * Starts a hosted checkout for one package and answers with `redirectTo`, the
 * provider's payment page. The ajax token is the CSRF defence; sitters need the
 * buy-or-spend-gold permission; each player may start 10 checkouts per 10 minutes.
 */
class paymentCheckout extends AjaxBase
{
    public function dispatch(): void
    {
        $session = Session::getInstance();
        if (!WebService::isPost() || ($session->banned() && $session->getPlayerId() > 2)
            || !$session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD)) {
            $this->error(T('PaymentWizard', 'paymentUnAvailable'));

            return;
        }
        $deletion = DeletionLock::refusal((int) $session->getPlayerId(), 'in_loeschung');
        if ($deletion !== null) {
            $this->error($deletion, 403);

            return;
        }
        $account = GlobalDB::getInstance();
        $package = GoldPackages::findActive($account, (int) ($_POST['goldProductId'] ?? 0), time());
        $provider = PaymentShop::providers()[(string) ($_POST['provider'] ?? '')] ?? null;
        $uid = (int) $session->getPlayerId();
        if ($package === null || $provider === null || !PaymentShop::isOpen($account, time())) {
            $this->error(T('PaymentWizard', 'paymentUnAvailable'));

            return;
        }
        if (!Caching::getInstance()->incrementWithinLimit(throttle_key('payment-checkout', 'player', (string) $uid), 10, 600)) {
            $this->error(T('PaymentWizard', 'paymentUnAvailable'), 429);

            return;
        }
        $currency = \Game\Payment\PaymentConfig::fromEnvironment()->currency();
        $order = PaymentOrders::create($account, (int) getWorldUniqueId(), $uid, $package, $provider->id(), $currency);
        if ($order === null) {
            $this->error(T('PaymentWizard', 'paymentUnAvailable'), 500);

            return;
        }
        $base = OAuthFlowFactory::baseUrl() . '/payment/return';
        try {
            $checkout = $provider->createCheckout(
                $order,
                $package['gold'] . ' ' . T('PaymentWizard', 'Gold'),
                $base . '?status=success',
                $base . '?status=cancel'
            );
        } catch (PaymentException $exception) {
            error_log('Checkout for order ' . $order->id . ' failed: ' . $exception->getMessage());
            PaymentOrders::fail($account, $order);
            $this->error(T('PaymentWizard', 'paymentUnAvailable'), 502);

            return;
        }
        PaymentOrders::setProviderRef($account, $order, $checkout['ref']);
        $this->response['redirectTo'] = $checkout['url'];
    }
}
