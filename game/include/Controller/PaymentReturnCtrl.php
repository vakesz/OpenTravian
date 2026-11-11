<?php

declare(strict_types=1);

namespace Controller;

use Core\Database\GlobalDB;
use Game\Payment\CurlTransport;
use Game\Payment\PayPalProvider;
use Game\Payment\PaymentConfig;
use Game\Payment\PaymentException;
use Game\Payment\PaymentOrders;

/**
 * Where the provider's hosted page sends the player back (/payment/return).
 * It never credits gold: only the signed webhook does. For PayPal it captures
 * the order the player just approved, then returns to the village.
 */
final class PaymentReturnCtrl extends GameCtrl
{
    public function __construct()
    {
        parent::__construct();
        $payerOrder = $_GET['token'] ?? null;
        if (($_GET['status'] ?? '') === 'success' && is_string($payerOrder) && preg_match('/\A[A-Za-z0-9]{6,40}\z/', $payerOrder) === 1) {
            $order = PaymentOrders::findForPlayer(GlobalDB::getInstance(), (int) $this->session->getPlayerId(), 'paypal', $payerOrder);
            $paypal = PaymentConfig::fromEnvironment()->providers(new CurlTransport())['paypal'] ?? null;
            if ($order !== null && $paypal instanceof PayPalProvider) {
                try {
                    $paypal->capture($payerOrder);
                } catch (PaymentException $exception) {
                    error_log('PayPal capture for order ' . $order->id . ' failed: ' . $exception->getMessage());
                }
            }
        }
        $this->redirect('/dorf1.php');
    }

    public function render(): string
    {
        return '';
    }

    protected function beforeRender(): void
    {
    }

    protected function afterRender(): void
    {
    }
}
