<?php

namespace Controller\Ajax;

use Game\Payment\GoldPackages;
use Game\Payment\PaymentConfig;
use Game\Payment\PaymentShop;
use Core\Database\GlobalDB;
use Core\Session;
use Model\Quest;
use Model\TasksModel;
use resources\View\PHPBatchView;
use Core\Helper\WebService;

use function getCustom;

class paymentWizard extends AjaxBase
{
    public function dispatch(): void
    {
        if ((Session::getInstance()->banned() && Session::getInstance()->getPlayerId() > 2)/* || Config::getInstance()->dynamic->serverFinished*/) {
            $this->response['error'] = true;
            $this->response['message'] = T("inGame", 'bannedSmallPage');
            return;
        }

        if (WebService::isPost()) {
            Quest::getInstance()->setQuestBitwise('world', 7, 1);
            (new TasksModel())->markFreeGoldShopOpened((int)Session::getInstance()->getPlayerId());
            $view = new PHPBatchView("payment/paymentWizardLayout");
            $defaultTab = 'buyGold';
            $_POST['activeTab'] = in_array(
                $_POST['activeTab'] ?? null,
                [
                    'buyGold',
                    'specialOffers',
                    'vouchers',
                    'advantages',
                ],
                true
            ) ? $_POST['activeTab'] : $defaultTab;

            $view->vars['class'] = $_POST['activeTab'];
            $view->vars['content'] = null;
            $view->vars['initMethod'] = '';
            $view->vars['gold'] = (getCustom("serverIsFreeGold") ? T("Global", "Unlimited") : Session::getInstance()->getAvailableGold());

            if (!Session::getInstance()->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD)) {
                $this->response['error'] = true;
                $this->response['message'] = T("PaymentWizard", "paymentUnAvailable");

                return;
            }
            switch ($_POST['activeTab']) {
                case 'buyGold':
                    $view->vars['purchaseAvailable'] = PaymentShop::isOpen(GlobalDB::getInstance(), time());
                    $view->vars['content'] = $view->vars['purchaseAvailable'] ? $this->renderBuyGold() : null;
                    break;
                case 'advantages':
                    $view->vars['content'] = $this->renderPros();
                    break;
                case 'vouchers':
                    $view->vars['content'] = $this->renderVoucher();
                    break;
                case 'specialOffers':
                    $view->vars['content'] = $this->renderSpecialOffers();
                    break;
            }
            $this->response['html'] = $view->output() . '<div class="clear">&nbsp;</div>';
        }
    }

    private function renderBuyGold(): string
    {
        $config = PaymentConfig::fromEnvironment();
        $view = new PHPBatchView("payment/buyGold");
        $view->vars['packages'] = GoldPackages::active(GlobalDB::getInstance(), time());
        $view->vars['currency'] = $config->currency();
        // Provider names are brand names, shown as the providers write them.
        $view->vars['providers'] = array_map(
            static fn (string $id): string => $id === 'paypal' ? 'PayPal' : 'Stripe',
            array_combine(array_keys(PaymentShop::providers()), array_keys(PaymentShop::providers()))
        );

        return $view->output();
    }

    private function renderPros(): string
    {
        $view = new PHPBatchView("payment/pros");
        return $view->output();
    }

    private function renderVoucher(): string
    {
        $view = new PHPBatchView("payment/vouchers");
        return $view->output();
    }

    private function renderSpecialOffers(): string
    {
        $view = new PHPBatchView("payment/specialOffers");
        return $view->output();
    }
}
