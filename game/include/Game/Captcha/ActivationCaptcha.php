<?php

declare(strict_types=1);

namespace Game\Captcha;

/** The optional CAPTCHA of the activation wizard: present only while the operator has configured keys. */
final class ActivationCaptcha
{
    public const ACTION = 'activation';

    public static function provider(): ?CaptchaProvider
    {
        return CaptchaConfig::fromEnvironment()->provider();
    }

    /** True when no CAPTCHA is configured, otherwise whether the posted token is accepted. */
    public static function accepts(?CaptchaProvider $provider, mixed $token, string $remoteIp): bool
    {
        return $provider === null || (is_string($token) && $provider->verify($token, self::ACTION, $remoteIp));
    }
}
