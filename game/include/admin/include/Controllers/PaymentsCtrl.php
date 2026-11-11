<?php

use Core\Database\GlobalDB;
use Core\Session;
use Game\AdminUi;
use Game\Payment\PaymentConfig;
use Game\SocialAuth\OAuthFlowFactory;

/**
 * Operator page for gold purchases: provider keys (write-only), test/live mode,
 * the one shop currency, the package editor, the payment log and the accounts
 * flagged after a refund or dispute. Credentials are kept in protected
 * deployment state (payments.local.php), never in SQL.
 */
class PaymentsCtrl
{
    private ?string $error = null;
    private ?string $saved = null;

    public function __construct()
    {
        $dispatcher = Dispatcher::getInstance();
        if (\Core\Helper\WebService::isPost() && Session::validateChecker()) {
            $this->handlePost();
        }
        $dispatcher->appendContent(AdminUi::page(
            'Payments',
            'Stripe and PayPal gold purchases, packages, orders and flagged refunds. Credentials stay in protected state.',
            ($this->error !== null ? AdminUi::notice($this->error, 'error') : '')
            . ($this->saved !== null ? AdminUi::notice($this->saved, 'success') : '')
            . AdminUi::box('Settings', $this->settingsForm())
            . AdminUi::box('Packages', $this->packagesForm())
            . AdminUi::box('Flagged accounts', $this->flagged())
            . AdminUi::box('Orders', $this->orders())
            . AdminUi::box('Provider events', $this->events())
        ));
    }

    private function handlePost(): void
    {
        $db = GlobalDB::getInstance();
        if (isset($_POST['saveSettings'])) {
            $config = PaymentConfig::fromEnvironment();
            $parsed = $config->fromSubmission($_POST);
            if ($parsed['config'] === null) {
                $this->error = implode(' ', $parsed['errors']);
            } elseif (!$config->save($parsed['config'])) {
                $this->error = 'Unable to save the protected payment configuration.';
            } else {
                $enabled = isset($_POST['purchasesEnabled']) ? 1 : 0;
                if ($db->run('INSERT INTO paymentConfig (id, active) VALUES (1, ?) ON DUPLICATE KEY UPDATE active=VALUES(active)', [$enabled]) === false) {
                    $this->error = 'The purchases switch could not be saved.';
                } else {
                    AdminLog::getInstance()->addLog('Changed payment settings.');
                    $this->saved = 'Payment settings saved.';
                }
            }
        } elseif (isset($_POST['savePackages']) && is_array($_POST['pkg'] ?? null)) {
            $this->savePackages($db, $_POST['pkg']);
        } elseif (isset($_POST['clearFlag'])) {
            $db->run('UPDATE paymentFlag SET clearedAt=? WHERE id=? AND clearedAt IS NULL', [time(), (int) $_POST['clearFlag']]);
            AdminLog::getInstance()->addLog('Cleared a payment flag.');
        }
    }

    /** @param array<mixed> $rows */
    private function savePackages(\Core\Database\DB $db, array $rows): void
    {
        $clean = [];
        foreach ($rows as $key => $row) {
            if (!is_array($row) || ($key !== 'new' && !ctype_digit((string) $key))) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $gold = (int) ($row['gold'] ?? 0);
            $price = (string) ($row['price'] ?? '');
            $image = trim((string) ($row['image'] ?? ''));
            if ($key === 'new' && $name === '' && $gold === 0 && $price === '') {
                continue;
            }
            if ($name === '' || strlen($name) > 100 || $gold < 1 || $gold > 1000000
                || preg_match('/\A\d{1,6}(\.\d{1,2})?\z/', $price) !== 1 || (float) $price <= 0
                || preg_match('/\A[A-Za-z0-9_.-]{1,100}\.png\z/', $image) !== 1) {
                $this->error = 'Each package needs a name, gold (1-1000000), a price like 19.99 and a .png image name.';

                return;
            }
            $clean[$key] = [$name, $gold, $price, $image, isset($row['offer']) ? 1 : 0, isset($row['bestSeller']) ? 1 : 0, isset($row['bestValue']) ? 1 : 0, isset($row['active']) ? 1 : 0];
        }
        foreach ($clean as $key => $values) {
            if ($key === 'new') {
                $db->run('INSERT INTO goldProducts (goldProductName, goldProductGold, goldProductPrice, goldProductImageName, goldProductHasOffer, isBestSeller, isBestValue, isActive) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $values);
            } else {
                $db->run('UPDATE goldProducts SET goldProductName=?, goldProductGold=?, goldProductPrice=?, goldProductImageName=?, goldProductHasOffer=?, isBestSeller=?, isBestValue=?, isActive=? WHERE goldProductId=?', [...$values, (int) $key]);
            }
        }
        AdminLog::getInstance()->addLog('Changed gold packages.');
        $this->saved = 'Packages saved.';
    }

    private function settingsForm(): string
    {
        $config = PaymentConfig::fromEnvironment();
        $values = $config->formValues();
        $base = OAuthFlowFactory::baseUrl();
        $enabled = (int) GlobalDB::getInstance()->fetchScalar('SELECT active FROM paymentConfig LIMIT 1', [], 0) === 1;
        $currencies = array_combine(PaymentConfig::CURRENCIES, PaymentConfig::CURRENCIES);
        $html = '<form method="post" action="admin.php?action=payments" class="adminInline">' . Session::getCheckerInput()
            . AdminUi::checkbox('purchasesEnabled', 'Purchases enabled (needs a configured provider and an active package)', $enabled)
            . AdminUi::select('pay_mode', 'Mode', ['test' => 'Test (sandbox keys, no real money)', 'live' => 'Live (real payments)'], $values['mode'])
            . AdminUi::select('pay_currency', 'Currency (one for the whole shop)', $currencies, $values['currency']);
        $secret = static fn (string $provider, string $field, string $label, bool $set): string => AdminUi::secret(
            'pay_' . $provider . '_' . $field,
            $label,
            ['placeholder' => $set ? 'Set. Leave blank to keep.' : '']
        );
        foreach (['stripe' => 'Stripe', 'paypal' => 'PayPal'] as $id => $label) {
            $set = $values['providers'][$id]['configured'];
            $html .= '<fieldset><legend>' . $label . ($set ? ' (configured)' : '') . '</legend>';
            if ($id === 'stripe') {
                $html .= $secret('stripe', 'secret_key', 'Secret key', $set) . $secret('stripe', 'webhook_secret', 'Webhook signing secret', $set);
            } else {
                $html .= AdminUi::input('pay_paypal_client_id', 'Client ID', $values['providers']['paypal']['values']['client_id'], ['autocomplete' => 'off'])
                    . $secret('paypal', 'client_secret', 'Client secret', $set)
                    . AdminUi::input('pay_paypal_webhook_id', 'Webhook ID', $values['providers']['paypal']['values']['webhook_id'], ['autocomplete' => 'off']);
            }
            $html .= AdminUi::checkbox('pay_' . $id . '_remove', 'Remove ' . $label)
                . '<small>Webhook URL: <code>' . AdminUi::esc($base . '/payments/' . $id . '/webhook') . '</code></small></fieldset>';
        }

        return $html . AdminUi::button('Save', 'green', ['name' => 'saveSettings', 'value' => '1']) . '</form>'
            . '<small>Credentials are stored only in protected deployment state, never in SQL. Blank secrets keep the stored value; '
            . 'changing the PayPal client ID requires its secret again.</small>';
    }

    private function packagesForm(): string
    {
        $currency = PaymentConfig::fromEnvironment()->currency();
        $result = GlobalDB::getInstance()->query('SELECT * FROM goldProducts ORDER BY goldProductId ASC');
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[(string) $row['goldProductId']] = $row;
        }
        $rows['new'] = ['goldProductName' => '', 'goldProductGold' => '', 'goldProductPrice' => '', 'goldProductImageName' => '',
            'goldProductHasOffer' => 0, 'isBestSeller' => 0, 'isBestValue' => 0, 'isActive' => 0];
        $html = '<form method="post" action="admin.php?action=payments">' . Session::getCheckerInput()
            . '<table class="adminTable"><thead><tr><th>Name</th><th>Gold</th><th>Price (' . AdminUi::esc($currency) . ')</th><th>Image</th>'
            . '<th>Offer bonus</th><th>Best seller</th><th>Best value</th><th>Active</th></tr></thead><tbody>';
        foreach ($rows as $key => $row) {
            $n = 'pkg[' . $key . ']';
            $html .= '<tr><td><input type="text" name="' . $n . '[name]" maxlength="100" value="' . AdminUi::esc($row['goldProductName']) . '"></td>'
                . '<td><input type="number" name="' . $n . '[gold]" min="1" max="1000000" value="' . AdminUi::esc($row['goldProductGold']) . '"></td>'
                . '<td><input type="text" name="' . $n . '[price]" inputmode="decimal" value="' . AdminUi::esc($row['goldProductPrice']) . '"></td>'
                . '<td><input type="text" name="' . $n . '[image]" value="' . AdminUi::esc($row['goldProductImageName']) . '"></td>';
            foreach (['offer' => 'goldProductHasOffer', 'bestSeller' => 'isBestSeller', 'bestValue' => 'isBestValue', 'active' => 'isActive'] as $field => $column) {
                $html .= '<td><input type="checkbox" name="' . $n . '[' . $field . ']" value="1"' . ((int) $row[$column] === 1 ? ' checked' : '') . '></td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table>' . AdminUi::button('Save packages', 'green', ['name' => 'savePackages', 'value' => '1'])
            . '</form><small>The last row adds a package. Prices are in the shop currency above; packages are hidden from players until active.</small>';
    }

    private function flagged(): string
    {
        $result = GlobalDB::getInstance()->query('SELECT * FROM paymentFlag WHERE clearedAt IS NULL ORDER BY id DESC LIMIT 100');
        $html = '<form method="post" action="admin.php?action=payments">' . Session::getCheckerInput()
            . '<table class="adminTable"><thead><tr><th>Player</th><th>World</th><th>Order</th><th>Reason</th><th>Gold deducted</th><th>Time</th><th></th></tr></thead><tbody>';
        $count = 0;
        while ($result && ($row = $result->fetch_assoc())) {
            ++$count;
            $html .= '<tr><td>' . (int) $row['uid'] . '</td><td>' . (int) $row['worldUniqueId'] . '</td><td>' . (int) $row['orderId'] . '</td><td>'
                . AdminUi::esc($row['reason']) . '</td><td>' . (int) $row['goldDeducted'] . '</td><td>' . date('Y-m-d H:i', (int) $row['time']) . '</td><td>'
                . AdminUi::button('Clear', 'grey', ['name' => 'clearFlag', 'value' => (int) $row['id']]) . '</td></tr>';
        }

        return $html . ($count === 0 ? '<tr><td colspan="7"><span class="adminMuted">No flagged accounts.</span></td></tr>' : '') . '</tbody></table></form>';
    }

    private function orders(): string
    {
        $result = GlobalDB::getInstance()->query('SELECT * FROM paymentOrder ORDER BY id DESC LIMIT 50');
        $html = '<table class="adminTable"><thead><tr><th>#</th><th>Player</th><th>World</th><th>Provider</th><th>Gold</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead><tbody>';
        $count = 0;
        while ($result && ($row = $result->fetch_assoc())) {
            ++$count;
            $html .= '<tr><td>' . (int) $row['id'] . '</td><td>' . (int) $row['uid'] . '</td><td>' . (int) $row['worldUniqueId'] . '</td><td>'
                . AdminUi::esc($row['provider']) . '</td><td>' . (int) $row['gold'] . '</td><td>'
                . AdminUi::esc(\Game\Payment\Money::format((int) $row['amountMinor'], (string) $row['currency'])) . '</td><td>'
                . AdminUi::esc($row['status']) . '</td><td>' . date('Y-m-d H:i', (int) $row['created']) . '</td></tr>';
        }

        return $html . ($count === 0 ? '<tr><td colspan="8"><span class="adminMuted">No orders yet.</span></td></tr>' : '') . '</tbody></table>';
    }

    private function events(): string
    {
        $result = GlobalDB::getInstance()->query('SELECT * FROM paymentEvent ORDER BY id DESC LIMIT 50');
        $html = '<table class="adminTable"><thead><tr><th>Provider</th><th>Event</th><th>Type</th><th>Order</th><th>Outcome</th><th>Detail</th><th>Time</th></tr></thead><tbody>';
        $count = 0;
        while ($result && ($row = $result->fetch_assoc())) {
            ++$count;
            $html .= '<tr><td>' . AdminUi::esc($row['provider']) . '</td><td>' . AdminUi::esc($row['eventId']) . '</td><td>' . AdminUi::esc($row['type']) . '</td><td>'
                . (int) $row['orderId'] . '</td><td>' . AdminUi::esc($row['outcome']) . '</td><td>' . AdminUi::esc($row['detail']) . '</td><td>'
                . date('Y-m-d H:i', (int) $row['time']) . '</td></tr>';
        }

        return $html . ($count === 0 ? '<tr><td colspan="7"><span class="adminMuted">No events yet.</span></td></tr>' : '') . '</tbody></table>';
    }
}
