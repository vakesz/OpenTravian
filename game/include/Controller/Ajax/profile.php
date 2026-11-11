<?php

declare(strict_types=1);

namespace Controller\Ajax;

use Core\Caching\ProfileCache;
use Core\Config;
use Core\Helper\WebService;
use Game\ProfileLanguages;
use Model\ProfileModel;

final class profile extends AjaxBase
{
    private const GENDERS = [
        'NotSpecified' => 0,
        'Female' => 2,
        'Male' => 1,
        'Other' => 3,
    ];

    public function dispatch(): void
    {
        if (!WebService::isPut()) {
            $this->error('Unsupported profile operation.', 405);
            return;
        }
        if (!$this->session->isValid() || $this->session->isSitter() || $this->session->banned()) {
            $this->error('You cannot edit this profile from the current session.', 403);
            return;
        }

        $description1 = $_POST['description1'] ?? null;
        $description2 = $_POST['description2'] ?? null;
        $location = $_POST['location'] ?? null;
        $gender = $_POST['gender'] ?? null;
        if (!is_string($description1)
            || !is_string($description2)
            || !is_string($location)
            || !is_string($gender)
            || !isset(self::GENDERS[$gender])) {
            $this->error('Invalid profile data.');
            return;
        }

        $locale = $this->profileLocale();
        $languages = ProfileLanguages::normalize($_POST['additionalLanguages'] ?? [], $locale);
        $showCountryFlag = !empty($_POST['showCountryFlag']);
        $villageNames = $_POST['villageNames'] ?? [];
        if (!is_array($villageNames)) {
            $this->error('Invalid village names.');
            return;
        }
        $villages = [];
        foreach ($villageNames as $village) {
            if (!is_array($village)
                || !isset($village['id'], $village['name'])
                || !is_scalar($village['id'])
                || !is_string($village['name'])
                || !ctype_digit((string)$village['id'])) {
                $this->error('Invalid village names.');
                return;
            }
            $villages[] = [
                'id' => (int)$village['id'],
                'name' => $village['name'],
            ];
        }

        $uid = (int)$this->session->getPlayerId();
        $model = new ProfileModel();
        if (!$model->updateProfileV2(
            $uid,
            $description1,
            $description2,
            self::GENDERS[$gender],
            $location,
            $showCountryFlag,
            $languages
        )) {
            $this->error('Invalid profile data.');
            return;
        }

        $cache = new ProfileCache((int)$this->session->get('profileCacheVersion'));
        $changedVillages = false;
        foreach ($villages as $village) {
            $model->updateVillageName((int)$village['id'], $village['name']);
            $changedVillages = true;
        }
        if ($changedVillages) {
            $cache->reValidateProfileVillages($uid);
        }

        $this->response['reload'] = true;
    }

    private function profileLocale(): string
    {
        $language = $this->session->getLanguage();
        $available = Config::getProperty('settings', 'availableLanguages');
        return isset($available->{$language}->locale) ? (string)$available->{$language}->locale : 'en-US';
    }
}
