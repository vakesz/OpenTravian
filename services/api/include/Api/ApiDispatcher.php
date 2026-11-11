<?php

declare(strict_types=1);

namespace Api;

use Core\Translator;
use Exceptions\ActionNotFoundException;
use Exceptions\CtrlNotFoundException;
use Exceptions\MissingParameterException;

use function is_string;
use function method_exists;
use function ucfirst;

class ApiDispatcher
{
    /**
     * @param array<array-key, mixed> $response
     * @param array<array-key, mixed> $payload
     */
    public function __construct(array &$response, array $payload, ?string $section, string $action)
    {
        $response['data'] = [];
        $data = &$response['data'];
        if ($section === null || $section === '') {
            $section = 'config';
        }
        $controller = ucfirst($section) . 'Ctrl';
        if (preg_match('/^[A-Za-z0-9]+$/D', $controller) !== 1 || !is_file(__DIR__ . '/Ctrl/' . $controller . '.php')) {
            throw new CtrlNotFoundException("Ctrl \"$controller\" not found.");
        }
        $loader = "\\Api\\Ctrl\\$controller";
        $dispatchedCtrl = new $loader($data, $payload);
        if (!$dispatchedCtrl instanceof ApiAbstractCtrl) {
            throw new CtrlNotFoundException("Ctrl \"$controller\" not found.");
        }
        if (str_starts_with($action, '__') || !method_exists($dispatchedCtrl, $action) || !is_callable([$dispatchedCtrl, $action])) {
            throw new ActionNotFoundException("Action \"$action\" does not exists in Ctrl \"$controller\".");
        }
        if ($section !== 'config') {
            $lang = $payload['lang'] ?? null;
            if (!is_string($lang)) {
                throw new MissingParameterException('lang');
            }
            Translator::setLanguage($lang);
        }
        $dispatchedCtrl->{$action}();
    }
}
