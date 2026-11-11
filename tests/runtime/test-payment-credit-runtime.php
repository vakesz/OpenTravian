<?php

declare(strict_types=1);

// Payments end to end without real money: signed Stripe and PayPal webhooks are
// forged locally, then credited, replayed, mismatched, raced and refunded against
// the local databases. Touched rows are removed and the balance restored.

require dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require dirname(__DIR__, 2) . '/game/include/bootstrap.php';

use Core\Database\DB;
use Core\Database\GlobalDB;
use Game\Payment\HttpTransport;
use Game\Payment\PaymentConfig;
use Game\Payment\PaymentCredit;
use Game\Payment\PaymentEvent;
use Game\Payment\PaymentEventType;
use Game\Payment\PaymentException;
use Game\Payment\PaymentOrders;
use Game\Payment\PayPalProvider;
use Game\Payment\StripeProvider;
use Game\SocialAuth\HttpResponse;

set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
});
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
mysqli_report(MYSQLI_REPORT_OFF);

$root = dirname(__DIR__, 2);
$account = GlobalDB::getInstance();
$world = DB::getInstance();
$worldFor = static fn (int $id): DB => DB::getInstance();
// Worker mode: one of two racing processes delivering the same event (see below).
if (($argv[1] ?? '') === 'race') {
    $order = PaymentOrders::findByToken($account, (string) $argv[2]);
    echo (new PaymentCredit($account, $worldFor))->apply(new PaymentEvent('stripe', (string) $argv[3], PaymentEventType::Paid, $order->token, 'pi_race', $order->amountMinor, 'USD'));
    exit(0);
}
$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};
$check = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};

// The shop tables are part of the canonical seeds; create them from there when an older install lacks them.
$ensureTables = static function (DB $db, string $sqlFile, array $tables) use ($root, $fail): void {
    $sql = (string) file_get_contents($root . '/database/' . $sqlFile);
    foreach ($tables as $table) {
        if ((int) $db->fetchScalar('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', [$table]) > 0) {
            continue;
        }
        if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`' . $table . '` \(.*?\n\)[^;]*;/s', $sql, $create) !== 1) {
            $fail("Seed for $table not found in $sqlFile.");
        }
        $statements = [preg_replace('/CREATE TABLE (?:IF NOT EXISTS )?/', 'CREATE TABLE IF NOT EXISTS ', $create[0])];
        preg_match_all('/ALTER TABLE `' . $table . '`[^;]*;/s', $sql, $alters);
        $db->mysqli->multi_query(implode("\n", array_merge($statements, $alters[0])));
        while ($db->mysqli->more_results() && $db->mysqli->next_result()) {
        }
    }
};
$ensureTables($account, 'account.sql', ['paymentOrder', 'paymentEvent', 'paymentFlag']);
$ensureTables($world, 'world.sql', ['paymentCredits']);

$uid = (int) $world->fetchScalar('SELECT id FROM users WHERE id>2 ORDER BY id LIMIT 1');
$check($uid > 0, 'Payment runtime check needs an installed player account.');
$originalGold = (int) $world->fetchScalar('SELECT bought_gold FROM users WHERE id=?', [$uid]);
$orderIds = [];
$runId = bin2hex(random_bytes(4));
register_shutdown_function(static function () use ($account, $world, $uid, $originalGold, &$orderIds, $runId): void {
    foreach ($orderIds as $id) {
        $world->run('DELETE FROM paymentCredits WHERE creditKey IN (?, ?)', ["order:$id:credit", "order:$id:reversal"]);
        $world->run('DELETE FROM buyGoldMessages WHERE trackingCode=?', ["order-$id"]);
        $account->run('DELETE FROM paymentFlag WHERE orderId=?', [$id]);
        $account->run('DELETE FROM paymentOrder WHERE id=?', [$id]);
    }
    $account->run('DELETE FROM paymentEvent WHERE eventId LIKE ?', ["%_$runId%"]);
    $world->run('UPDATE users SET bought_gold=? WHERE id=?', [$originalGold, $uid]);
});
$balance = static fn (): int => (int) $world->fetchScalar('SELECT bought_gold FROM users WHERE id=?', [$uid]);
$newOrder = static function (string $provider, int $gold = 100, int $minor = 1999) use ($account, $uid, &$orderIds, $check) {
    $order = PaymentOrders::create($account, 1, $uid, ['id' => 1, 'gold' => $gold, 'priceMinor' => $minor], $provider, 'USD');
    $check($order !== null, 'An order must be created.');
    $orderIds[] = $order->id;

    return $order;
};
$event = static fn (string $id, PaymentEventType $type, $order, ?int $minor = null, string $currency = 'USD', bool $byToken = true) => new PaymentEvent(
    $order->provider,
    "{$id}_$runId",
    $type,
    $byToken ? $order->token : null,
    'pay_' . $order->id,
    $minor ?? $order->amountMinor,
    $currency
);
$credit = new PaymentCredit($account, $worldFor);

// Stripe signatures: forged locally with the webhook secret.
$http = new class implements HttpTransport {
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];
    /** @var list<HttpResponse> */
    public array $responses = [];

    public function send(string $method, string $url, array $headers, ?string $body): HttpResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');

        return array_shift($this->responses) ?? new HttpResponse(500, '{}');
    }
};
$now = 1_800_000_000;
$stripe = new StripeProvider('sk_test_x', 'whsec_test', $http, static fn (): int => $now);
$sign = static fn (string $body, int $time, string $secret = 'whsec_test'): array => ['stripe-signature' => "t=$time,v1=" . hash_hmac('sha256', "$time.$body", $secret)];
$body = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'payment_status' => 'paid', 'client_reference_id' => 'tok', 'payment_intent' => 'pi_1', 'amount_total' => 1999, 'currency' => 'usd',
]]]);
$parsed = $stripe->parseWebhook($body, $sign($body, $now));
$check($parsed !== null && $parsed->type === PaymentEventType::Paid && $parsed->amountMinor === 1999 && $parsed->currency === 'USD'
    && $parsed->orderToken === 'tok' && $parsed->paymentRef === 'pi_1', 'A correctly signed Stripe event must parse.');
foreach ([
    'wrong secret' => [$body, $sign($body, $now, 'whsec_other')],
    'tampered body' => [$body . ' ', $sign($body, $now)],
    'stale timestamp' => [$body, $sign($body, $now - 301)],
    'missing header' => [$body, []],
    'garbage header' => [$body, ['stripe-signature' => 't=abc,v1=']],
] as $label => [$candidateBody, $headers]) {
    try {
        $stripe->parseWebhook($candidateBody, $headers);
        $fail("Stripe webhook with $label must be rejected.");
    } catch (PaymentException) {
    }
}

// PayPal: the signature check is PayPal's verify call; anything but SUCCESS is rejected.
$paypal = new PayPalProvider('client', 'secret', 'WH-1', false, $http);
$paypalBody = json_encode(['id' => 'WH-EVT-1', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => [
    'id' => 'CAP1', 'status' => 'COMPLETED', 'custom_id' => 'tok', 'amount' => ['currency_code' => 'USD', 'value' => '19.99'],
]]);
$paypalHeaders = ['paypal-auth-algo' => 'SHA256withRSA', 'paypal-cert-url' => 'https://api-m.paypal.com/cert', 'paypal-transmission-id' => 'tid',
    'paypal-transmission-sig' => 'sig', 'paypal-transmission-time' => '2026-01-01T00:00:00Z'];
$http->requests = [];
$http->responses = [new HttpResponse(200, '{"access_token":"t"}'), new HttpResponse(200, '{"verification_status":"SUCCESS"}')];
$parsed = $paypal->parseWebhook($paypalBody, $paypalHeaders);
$check($parsed !== null && $parsed->amountMinor === 1999 && $parsed->paymentRef === 'CAP1' && $parsed->orderToken === 'tok', 'A verified PayPal event must parse.');
$check(str_contains((string) $http->requests[1]['body'], '"webhook_event":' . $paypalBody), 'PayPal must be sent the exact raw event.');
$http->responses = [new HttpResponse(200, '{"access_token":"t"}'), new HttpResponse(200, '{"verification_status":"FAILURE"}')];
try {
    $paypal->parseWebhook($paypalBody, $paypalHeaders);
    $fail('A PayPal event that fails verification must be rejected.');
} catch (PaymentException) {
}
try {
    $paypal->parseWebhook($paypalBody, []);
    $fail('A PayPal event without signature headers must be rejected.');
} catch (PaymentException) {
}

// Checkout creation carries the order reference and refuses a non-https redirect.
$order = $newOrder('stripe');
$http->requests = [];
$http->responses = [new HttpResponse(200, '{"id":"cs_1","url":"https://checkout.example/pay"}')];
$checkout = $stripe->createCheckout($order, '100 Gold', 'https://shop.example/ok', 'https://shop.example/no');
$check($checkout === ['url' => 'https://checkout.example/pay', 'ref' => 'cs_1'], 'Stripe checkout must return the hosted page.');
parse_str((string) $http->requests[0]['body'], $sent);
$check($sent['client_reference_id'] === $order->token && $sent['line_items'][0]['price_data']['unit_amount'] === '1999'
    && $sent['line_items'][0]['price_data']['currency'] === 'usd', 'Stripe checkout must carry the order token, amount and currency.');
$http->responses = [new HttpResponse(200, '{"id":"cs_2","url":"http://insecure.example/pay"}')];
try {
    $stripe->createCheckout($order, '100 Gold', 'https://a.example', 'https://b.example');
    $fail('A non-https checkout URL must be refused.');
} catch (PaymentException) {
}

// Crediting: once per order, replay is a no-op.
$base = $balance();
$order = $newOrder('stripe');
$paid = $event('evt_paid', PaymentEventType::Paid, $order);
$check($credit->apply($paid) === PaymentCredit::APPLIED && $balance() === $base + 100, 'A paid event must credit the gold once.');
$check($credit->apply($paid) === PaymentCredit::DUPLICATE && $balance() === $base + 100, 'Replaying the same event must be a no-op.');
$check($credit->apply($event('evt_paid_again', PaymentEventType::Paid, $order)) === PaymentCredit::APPLIED && $balance() === $base + 100,
    'A second event for an already credited order must not credit again.');

// Amount and currency mismatches never credit.
$mismatch = $newOrder('stripe');
$check($credit->apply($event('evt_amount', PaymentEventType::Paid, $mismatch, 1)) === PaymentCredit::REJECTED && $balance() === $base + 100, 'An amount mismatch must be rejected.');
$check($credit->apply($event('evt_currency', PaymentEventType::Paid, $mismatch, null, 'EUR')) === PaymentCredit::REJECTED && $balance() === $base + 100, 'A currency mismatch must be rejected.');
$check($credit->apply($event('evt_unknown', PaymentEventType::Paid, $mismatch, null, 'USD', false) ) === PaymentCredit::REJECTED, 'An event whose payment id matches no order must be rejected.');

// A refund before anything was paid is rejected.
$refundOrder = $newOrder('paypal', 250, 4999);
$check($credit->apply($event('evt_early_refund', PaymentEventType::Refunded, $refundOrder, 1000, 'USD', false)) === PaymentCredit::REJECTED, 'A refund for an unpaid order must be rejected.');
$world->run('UPDATE users SET bought_gold=0 WHERE id=?', [$uid]);
$check($credit->apply($event('evt_refund_paid', PaymentEventType::Paid, $refundOrder)) === PaymentCredit::APPLIED && $balance() === 250, 'The refunded order must first be credited.');

// Partial refunds (PayPal reports each refund alone) deduct ceil(gold x refunded / paid) cumulatively, never above the order's gold.
$world->run('UPDATE users SET bought_gold=50 WHERE id=?', [$uid]);
$check($credit->apply($event('evt_refund_1', PaymentEventType::Refunded, $refundOrder, 1000, 'USD', false)) === PaymentCredit::APPLIED && $balance() === 50 - 51,
    'A 1000 of 4999 refund must deduct ceil(250 x 1000 / 4999) = 51 gold, even below zero.');
$check($credit->apply($event('evt_refund_2', PaymentEventType::Refunded, $refundOrder, 2000, 'USD', false)) === PaymentCredit::APPLIED && $balance() === 50 - 151,
    'A second partial refund must bring the total to ceil(250 x 3000 / 4999) = 151 gold.');
$check($credit->apply($event('evt_refund_1', PaymentEventType::Refunded, $refundOrder, 1000, 'USD', false)) === PaymentCredit::DUPLICATE && $balance() === 50 - 151, 'Redelivered refunds must not deduct again.');
$check($credit->apply($event('evt_refund_3', PaymentEventType::Refunded, $refundOrder, 1999, 'USD', false)) === PaymentCredit::APPLIED && $balance() === 50 - 250, 'The remainder refund must deduct exactly the order\'s gold in total.');
$check($credit->apply($event('evt_refund_4', PaymentEventType::Refunded, $refundOrder, 500, 'USD', false)) === PaymentCredit::APPLIED && $balance() === 50 - 250, 'Refunds beyond the price must never deduct more than the order\'s gold.');
$check($credit->apply($event('evt_dispute', PaymentEventType::Disputed, $refundOrder, 4999, 'USD', false)) === PaymentCredit::APPLIED && $balance() === 50 - 250, 'A dispute after refunds must not deduct twice.');
$flag = $account->run('SELECT reason, goldDeducted FROM paymentFlag WHERE orderId=?', [$refundOrder->id])->get_result()->fetch_assoc();
$check(is_array($flag) && $flag['reason'] === 'disputed' && (int) $flag['goldDeducted'] === 250, 'The account must be flagged with the total gold deducted.');
$check(PaymentOrders::findByToken($account, $refundOrder->token)->status === 'disputed', 'A disputed order stays disputed.');

// Stripe reports the running refund total: repeating a total adds nothing, a larger one adds the difference.
$stripeOrder = $newOrder('stripe', 100, 1999);
$world->run('UPDATE users SET bought_gold=0 WHERE id=?', [$uid]);
$credit->apply($event('evt_stripe_paid', PaymentEventType::Paid, $stripeOrder));
$cumulative = static fn (string $id, int $total) => new PaymentEvent('stripe', "{$id}_$runId", PaymentEventType::Refunded, null, 'pay_' . $stripeOrder->id, $total, 'USD', true);
$credit->apply($cumulative('evt_s1', 500));
$check($balance() === 100 - 26, 'A 500 of 1999 refund must deduct ceil(100 x 500 / 1999) = 26.');
$credit->apply($cumulative('evt_s2', 500));
$check($balance() === 100 - 26, 'A repeated running total must not deduct again.');
$credit->apply($cumulative('evt_s3', 1000));
$check($balance() === 100 - 51, 'A larger running total must deduct only the difference (51 in total).');
$check(PaymentOrders::findByToken($account, $stripeOrder->token)->status === 'partial', 'A partly refunded order is "partial".');
$credit->apply($cumulative('evt_s4', 1999));
$check($balance() === 0 && PaymentOrders::findByToken($account, $stripeOrder->token)->status === 'refunded', 'A full refund deducts all the gold and marks the order refunded.');

// Concurrent delivery of the same event from two processes credits once.
$raceOrder = $newOrder('stripe', 70, 999);
$raceBase = $balance();
$worker = static function () use ($raceOrder, $runId): array {
    $process = proc_open([PHP_BINARY, __FILE__, 'race', $raceOrder->token, "evt_race_$runId"], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    return [$process, $pipes];
};
$running = [$worker(), $worker()];
$outcomes = [];
foreach ($running as [$process, $pipes]) {
    $outcomes[] = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    proc_close($process);
}
$check($balance() === $raceBase + 70, 'Concurrent double delivery must credit exactly once, got ' . ($balance() - $raceBase) . ' (' . implode(',', $outcomes) . ').');

// Secrets: write-only form values, 0600 protected file, never in SQL.
$directory = sys_get_temp_dir() . '/payments-' . $runId;
mkdir($directory, 0700);
$config = new PaymentConfig($directory . '/payments.local.php');
$secretMarker = 'sk_test_SECRETMARKER' . $runId;
$parsedForm = $config->fromSubmission(['pay_mode' => 'test', 'pay_currency' => 'USD', 'pay_stripe_secret_key' => $secretMarker, 'pay_stripe_webhook_secret' => 'whsec_MARKER' . $runId]);
$check($parsedForm['config'] !== null && $config->save($parsedForm['config']), 'A valid configuration must save.');
$check((fileperms($config->path()) & 0777) === 0600, 'payments.local.php must be mode 0600.');
$check(!str_contains(json_encode($config->formValues()), $secretMarker), 'Admin form values must never contain a secret.');
$check($config->credentials('stripe')['secret_key'] === $secretMarker && $config->credentials('paypal') === null, 'Stored credentials must read back.');
$keep = $config->fromSubmission(['pay_mode' => 'test', 'pay_currency' => 'EUR', 'pay_stripe_secret_key' => '', 'pay_stripe_webhook_secret' => '']);
$check($keep['config']['stripe']['secret_key'] === $secretMarker && $keep['config']['currency'] === 'EUR', 'Blank secrets must keep the stored values.');
$liveMismatch = $config->fromSubmission(['pay_mode' => 'live', 'pay_currency' => 'USD', 'pay_stripe_secret_key' => '', 'pay_stripe_webhook_secret' => '']);
$check($liveMismatch['config'] === null, 'A test key must be rejected in live mode.');
$check($config->fromSubmission(['pay_mode' => 'test', 'pay_currency' => 'XXX'])['config'] === null, 'An unsupported currency must be rejected.');
$paypalSubmission = ['pay_mode' => 'test', 'pay_currency' => 'USD', 'pay_paypal_client_id' => 'cid', 'pay_paypal_client_secret' => 'csecret', 'pay_paypal_webhook_id' => 'wh'];
$config->save($config->fromSubmission($paypalSubmission)['config']);
$changed = $config->fromSubmission(['pay_mode' => 'test', 'pay_currency' => 'USD', 'pay_paypal_client_id' => 'other', 'pay_paypal_client_secret' => '', 'pay_paypal_webhook_id' => 'wh']);
$check($changed['config'] === null, 'Changing the PayPal client id must require its secret again.');
// The webhook endpoint maps outcomes to HTTP statuses.
$endpointConfig = new PaymentConfig($directory . '/endpoint.local.php');
$endpointConfig->save($endpointConfig->fromSubmission(['pay_mode' => 'test', 'pay_currency' => 'USD', 'pay_stripe_secret_key' => 'sk_test_e', 'pay_stripe_webhook_secret' => 'whsec_e'])['config']);
$endpoint = new Game\Payment\WebhookEndpoint($endpointConfig, $http);
$realNow = time();
$endpointBody = json_encode(['id' => "evt_http_$runId", 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'payment_status' => 'paid', 'client_reference_id' => 'no-such-token', 'payment_intent' => 'pi_none', 'amount_total' => 100, 'currency' => 'usd']]]);
$signed = ['stripe-signature' => "t=$realNow,v1=" . hash_hmac('sha256', "$realNow.$endpointBody", 'whsec_e')];
$check($endpoint->handle('stripe', 'GET', $endpointBody, $signed)[0] === 405, 'Webhooks must be POST only.');
$check($endpoint->handle('paypal', 'POST', $endpointBody, $signed)[0] === 404, 'An unconfigured provider must answer 404.');
$check($endpoint->handle('stripe', 'POST', $endpointBody, [])[0] === 400, 'An unsigned webhook must answer 400.');
$check($endpoint->handle('stripe', 'POST', $endpointBody, $signed) === [200, PaymentCredit::REJECTED], 'A signed event for an unknown order is acknowledged and rejected.');
$check($endpoint->handle('stripe', 'POST', $endpointBody, $signed) === [200, PaymentCredit::DUPLICATE], 'Redelivery is acknowledged as a duplicate.');
unlink($endpointConfig->path());
foreach (['paymentOrder', 'paymentEvent', 'paymentFlag', 'paymentConfig', 'goldProducts'] as $table) {
    $result = $account->query("SELECT * FROM `$table`");
    while ($result && ($row = $result->fetch_assoc())) {
        $check(!str_contains(json_encode($row), 'SECRETMARKER') && !str_contains(json_encode($row), 'csecret'), "$table must not contain provider secrets.");
    }
}
unlink($config->path());
rmdir($directory);
$schema = (string) file_get_contents($root . '/database/account.sql');
$check(!preg_match('/connectInfo|client_secret|webhook_secret|secret_key/i', $schema), 'The account schema must have no secret columns.');

echo "Payment credit runtime passed.\n";
