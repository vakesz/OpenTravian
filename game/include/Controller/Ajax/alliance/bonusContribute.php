<?php

declare(strict_types=1);

namespace Controller\Ajax\alliance;

use Controller\Ajax\AjaxBase;
use Game\AllianceBonus\AllianceDonation;

/** "alliance/bonus/contribute": one donation of resources to an alliance bonus. Body {bonus, resources: {lumber, clay, iron, crop}}. */
final class bonusContribute extends AjaxBase
{
    public function dispatch(): void
    {
        $parameters = AllianceDonation::parametersFromRequest($this->body(), (int)$this->session->getKid());
        $result = (new AllianceDonation())->donate($this->session, $parameters, false);
        if (isset($result['error'])) {
            $this->error(self::messageFor($result['error']));
        }
    }

    public static function messageFor(string $error): string
    {
        return match ($error) {
            'sitter' => T('AllianceBonus', 'As sitter you can`t contribute to alliance bonuses'),
            'resources' => T('AllianceBonus', 'Not enough are not available'),
            'limit' => T('AllianceBonus', 'Contribution limit for today is reached'),
            'gold' => T('AllianceBonus', 'The amount of gold could not be subtracted from your account'),
            'invalid' => T('AllianceBonus', 'Invalid resources entered'),
            default => T('AllianceBonus', 'Contribution failed:'),
        };
    }
}
