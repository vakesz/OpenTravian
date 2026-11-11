<?php

namespace Controller\Ajax\paynet;

use Controller\Ajax\AjaxBase;
use Core\Config;
use Core\Session;
use Core\Voucher;

use function isServerFinished;

class redeemVoucher extends AjaxBase
{

    public function dispatch(): void
    {
		$this->response = array();
        if (isServerFinished() || !Config::getAdvancedProperty("voucherEnabled")) {
            $this->response['error'] = TRUE;
			$this->response['code'] = 4001;
            $this->response['message'] = T("PaymentWizard", "This feature is disabled");
            return;
        }
        if (!Session::getInstance()->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD)) {
            $this->response['error'] = TRUE;
			$this->response['code'] = 4002;
            $this->response['message'] = T("PaymentWizard", "paymentUnAvailable");
            return;
        }

		if (isset($_POST['code'])) {
			$voucherCode = is_string($_POST['code']) ? trim($_POST['code']) : '';
			$voucher = Voucher::getVoucherWithCode($voucherCode, (string) Session::getInstance()->getEmail());
			if ($voucher->num_rows) {
				$voucher = $voucher->fetch_assoc();
				$voucherId = $voucher['id'];
				$voucherCode = $voucher['voucherCode'];
				$email = Session::getInstance()->getEmail();
				$voucherUsed = Voucher::useVoucher((int) $voucherId,
					(string) $voucherCode,
					(string) $email,
					(int) Session::getInstance()->getPlayerId(),
					(string) Session::getInstance()->getName());
				if ($voucherUsed !== FALSE) {
					Session::getInstance()->data['bought_gold'] += $voucherUsed;
					$this->response['data']['value'] = $voucherUsed;
					$this->response['data']['message'] = sprintf(T("PaymentWizard", "%s golds was added to your account"), $voucherUsed);
					return;
				}
			} else {
				$this->response['error'] = TRUE;
				$this->response['code'] = 4003;
				$this->response['message'] = T("PaymentWizard", "Unable to use this voucher");
				return;
			}
		}
    }
}
