<?php

use Core\Database\DB;
use Core\Session;
use Game\AdminUi;
use Game\Installer\InstallInput;
use Model\NatarsModel;

/**
 * Seeds more Natar farm villages after install, the same way the installer does
 * (NatarsModel::seedFarmVillages): spread over the map, a small Natar garrison each.
 */
class SeedFarmVillagesCtrl
{
    private ?string $error = null;
    private ?string $saved = null;

    public function __construct()
    {
        if (\Core\Helper\WebService::isPost() && Session::validateChecker()) {
            $this->handlePost();
        }
        $farms = (int) DB::getInstance()->fetchScalar('SELECT COUNT(kid) FROM vdata WHERE isFarm=1');
        $free = (int) DB::getInstance()->fetchScalar('SELECT COUNT(kid) FROM available_villages WHERE occupied=0 AND fieldtype>0');
        Dispatcher::getInstance()->appendContent(AdminUi::page(
            'Seed farm villages',
            'Natar farm villages are raided for resources or conquered. They are spread over the four map quarters and three distance bands, never in the starting area, each with 50 to 300 inhabitants and a small Natar garrison. A farm that is conquered becomes an ordinary village.',
            ($this->error !== null ? AdminUi::notice($this->error, 'error') : '')
            . ($this->saved !== null ? AdminUi::notice($this->saved, 'success') : '')
            . AdminUi::box('Farm villages now', '<p>' . $farms . ' farm villages exist; ' . $free . ' village slots are free on the map.</p>')
            . $this->form()
        ));
    }

    private function handlePost(): void
    {
        $count = $_POST['count'] ?? null;
        if (!is_string($count) || preg_match('/^[0-9]{1,4}$/', $count) !== 1 || (int) $count < 1 || (int) $count > InstallInput::MAX_FARM_VILLAGES) {
            $this->error = 'Enter a number of villages from 1 to ' . InstallInput::MAX_FARM_VILLAGES . '.';

            return;
        }
        set_time_limit(0);
        ignore_user_abort(true);
        $placed = (new NatarsModel())->seedFarmVillages((int) $count);
        \AdminLog::getInstance()->addLog('Seeded ' . $placed . ' of ' . (int) $count . ' requested Natar farm villages.');
        $this->saved = $placed . ' farm villages were added' . ($placed < (int) $count ? ' (' . ((int) $count - $placed) . ' fewer than asked: no free village slot was left in that part of the map)' : '') . '.';
    }

    private function form(): string
    {
        return '<form method="post" action="admin.php?action=seedFarmVillages" class="adminInline">' . Session::getCheckerInput()
            . AdminUi::box('Add farm villages', AdminUi::input('count', 'How many', '40', ['type' => 'number', 'min' => 1, 'max' => InstallInput::MAX_FARM_VILLAGES, 'required' => true], '1 to ' . InstallInput::MAX_FARM_VILLAGES . '. Large numbers take a while.'))
            . AdminUi::confirmButton('Seed villages', 'Add Natar farm villages to the map now?', 'green', ['name' => 'seed', 'value' => '1']) . '</form>';
    }
}
