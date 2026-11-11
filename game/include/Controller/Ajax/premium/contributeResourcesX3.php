<?php

declare(strict_types=1);

namespace Controller\Ajax\premium;

use Controller\Ajax\alliance\bonusContribute;
use Core\Config;
use Game\AllianceBonus\AllianceDonation;

/** Contribute x3: an alliance donation credited three times for gold. Body {bonus, resources: {lumber, clay, iron, crop}}. */
final class contributeResourcesX3 extends InstantPurchase
{
    protected function action(): string
    {
        return 'contribute-resources-x3';
    }

    protected function price(): int
    {
        return (int)Config::getProperty('gold', 'allianceBonus3xGold');
    }

    protected function target(): array|string
    {
        $parameters = AllianceDonation::parametersFromRequest($this->body(), (int)$this->session->getKid());
        $resources = AllianceDonation::resourcesFor($parameters);
        if ($this->session->getAllianceId() <= 0
            || AllianceDonation::typeFor($parameters['bid']) === null
            || $resources === null
            || array_sum($resources) <= 0) {
            return T('AllianceBonus', 'Invalid resources entered');
        }

        return ['bonus' => $parameters['bid'], 'resources' => $resources];
    }

    protected function dialogHtml(array $target): string
    {
        // The reference page confirms in its own "tripled" dialog before it books this purchase.
        return '';
    }

    protected function perform(array $target): ?string
    {
        $parameters = ['bid' => $target['bonus'], 'did' => (int)$this->session->getKid()];
        foreach ($target['resources'] as $index => $amount) {
            $parameters['r' . ($index + 1)] = $amount;
        }
        $result = (new AllianceDonation())->donate($this->session, $parameters, true);
        if (!isset($result['error'])) {
            return null;
        }

        return $result['error'] === 'gold' ? 'notEnoughGold' : bonusContribute::messageFor($result['error']);
    }
}
