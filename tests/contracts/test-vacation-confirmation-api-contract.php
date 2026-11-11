<?php

declare(strict_types=1);

$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/Ajax/vacationMode/confirmation.php');
$model = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/OptionModel.php');
$optionController = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/OptionCtrl.php');
$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/ajax/vacationConfirmation.php');
$vacationPage = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/options/Vacation.php');
$clientFiles = [
    dirname(__DIR__, 2) . '/game/deployment/public/game-client.js',
];

if ($controller === false
    || $model === false
    || $optionController === false
    || $template === false
    || $vacationPage === false
    || !str_contains($controller, 'getVacationConditions')
    || !str_contains($controller, "\$view->vars['checker'] = \$this->session->getChecker()")
    || !str_contains($controller, 'Formulas::remainingVacationDays(')
    || !str_contains($optionController, 'Formulas::remainingVacationDays(')
    || !str_contains($optionController, 'if (!Session::validateChecker())')
    || !str_contains($vacationPage, 'Formulas::remainingVacationDays(')
    || !str_contains($model, 'public function getVacationConditions')
    || !str_contains($optionController, 'min(max((int)($_POST[\'days\'] ?? 0), 1), $remainingVacationDays)')
    || !str_contains($optionController, '$remainingVacationDays > 0')
    || !str_contains($template, 'name="days"')
    || !str_contains($template, '\\Core\\Session::getCheckerName()')
    || !str_contains($template, "'type' => 'submit'")
    || !str_contains($template, "T('Options', 'enter vacation mode now')")) {
    fwrite(STDERR, "Vacation confirmation must recheck entry conditions, preserve the selected duration, and expose a submit action.\n");
    exit(1);
}

$vacationChecker = strpos($optionController, 'if (!Session::validateChecker())');
$vacationMutation = strpos($optionController, '$m->enterVacationMode(');
if ($vacationChecker === false || $vacationMutation === false || $vacationChecker > $vacationMutation) {
    fwrite(STDERR, "Vacation mode must validate its confirmation checker before entering the mode.\n");
    exit(1);
}

foreach ($clientFiles as $clientFile) {
    $client = file_get_contents($clientFile);
    $confirmationStart = is_string($client)
        ? strpos($client, 'openConfirmation: function () {')
        : false;
    $confirmationEnd = $confirmationStart !== false
        ? strpos($client, "\n  (Travian.Game.Arifact = {", $confirmationStart)
        : false;
    $failureFeedback = "error: function (e) {\n"
        . "          new Travian.Dialog.Dialog({ preventFormSubmit: !0 })\n"
        . "            .setContent(e.message)\n"
        . "            .show();\n"
        . "        }";
    $dialogSubmit = "buttonOk: !1,\n"
        . "        submitMethod: \"post\",\n"
        . "        submitUrl: \"/options/vacation\",";
    if (!is_string($client)
        || !str_contains($client, 'Travian.api("vacation-mode/confirmation", {')
        || !str_contains($client, $failureFeedback)
        || !str_contains($client, $dialogSubmit)
        || $confirmationEnd === false
        || !str_contains(substr($client, $confirmationStart, $confirmationEnd - $confirmationStart), 'return !1;')) {
        fwrite(STDERR, "Vacation confirmation must keep the native form submit and visible failure feedback.\n");
        exit(1);
    }
}

if (str_contains($vacationPage, 'onClick=')
    || !str_contains($vacationPage, 'return Travian.Game.Vacation.openConfirmation();')) {
    fwrite(STDERR, "The vacation button must open its confirmation dialog through one click handler.\n");
    exit(1);
}

if (is_file('/travian/game/deployment/include/env.php')) {
    $_SERVER['REQUEST_URI'] = '/options/vacation';
    $_SERVER['QUERY_STRING'] = '';
    $_SERVER['PHP_SELF'] = '/options.php';
    chdir('/travian/game/deployment/public');
    require '/travian/game/deployment/include/env.php';
    require '/travian/game/include/bootstrap.php';
    $view = new \resources\View\PHPBatchView('ajax/vacationConfirmation');
    $view->vars['days'] = 3;
    $html = $view->output();
    if (!str_contains($html, 'name="days" value="3"')
        || !str_contains($html, 'name="mpvt_token"')
        || !str_contains($html, '<button type="submit"')
        || !str_contains($html, 'Enter vacation mode now')) {
        fwrite(STDERR, "Vacation confirmation must render a player-visible submit button with its selected duration.\n");
        exit(1);
    }
}

echo "Vacation-confirmation API contract passed.\n";
