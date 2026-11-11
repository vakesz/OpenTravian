<?php

declare(strict_types=1);

namespace Game\Captcha;

/**
 * A CAPTCHA the activation wizard can ask for. A provider supplies the script
 * the page loads and the JavaScript expression that resolves to a token; the
 * server later asks the same provider whether that token is valid.
 */
interface CaptchaProvider
{
    /** HTTPS URL of the provider's client script. */
    public function scriptUrl(): string;

    /** JavaScript expression that evaluates to a promise of the token string. */
    public function tokenExpression(string $action): string;

    /** Whether the provider accepts the token for this action. A transport failure is a refusal. */
    public function verify(string $token, string $action, string $remoteIp): bool;
}
