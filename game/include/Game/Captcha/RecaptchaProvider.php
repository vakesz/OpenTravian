<?php

declare(strict_types=1);

namespace Game\Captcha;

use Game\Payment\CurlTransport;
use Game\Payment\HttpTransport;

/** Google reCAPTCHA v3 (score based, no widget), served from recaptcha.net like the reference. */
final class RecaptchaProvider implements CaptchaProvider
{
    private const SCRIPT = 'https://recaptcha.net/recaptcha/api.js';
    private const VERIFY = 'https://www.recaptcha.net/recaptcha/api/siteverify';
    private const MIN_SCORE = 0.5;

    public function __construct(
        private readonly string $siteKey,
        private readonly string $secretKey,
        private readonly HttpTransport $http = new CurlTransport()
    ) {
    }

    public function scriptUrl(): string
    {
        return self::SCRIPT . '?render=' . rawurlencode($this->siteKey);
    }

    public function tokenExpression(string $action): string
    {
        return 'window.grecaptcha.execute(' . json_encode($this->siteKey, JSON_THROW_ON_ERROR) . ', {action: '
            . json_encode($action, JSON_THROW_ON_ERROR) . '})';
    }

    public function verify(string $token, string $action, string $remoteIp): bool
    {
        if ($token === '' || strlen($token) > 4096) {
            return false;
        }
        $body = http_build_query(['secret' => $this->secretKey, 'response' => $token, 'remoteip' => $remoteIp], '', '&');
        try {
            $response = $this->http->send('POST', self::VERIFY, ['Content-Type' => 'application/x-www-form-urlencoded'], $body);
        } catch (\RuntimeException) {
            return false;
        }
        $data = json_decode($response->body, true);

        return $response->status === 200
            && is_array($data)
            && ($data['success'] ?? false) === true
            && ($data['action'] ?? '') === $action
            && (float) ($data['score'] ?? 0) >= self::MIN_SCORE;
    }
}
