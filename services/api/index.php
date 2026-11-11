<?php

declare(strict_types=1);

use Api\ApiDispatcher;

require __DIR__ . '/include/bootstrap.php';

function applyCorsPolicy(): void
{
    $allowedOrigin = getenv('TRAVIAN_CORS_ORIGIN') ?: '';
    $requestOrigin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');

    if ($allowedOrigin === '' || $requestOrigin === '' || !hash_equals($allowedOrigin, $requestOrigin)) {
        return;
    }

    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Authorization, Accept, Client-Security-Token, Accept-Encoding');
}

/**
 * @param array<string, mixed> $response
 */
function sendApiResponse(array $response, int $status): void
{
    applyCorsPolicy();
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function handle_v1_api(?string $section, string $action): void
{
    $response = ['success' => true, 'error' => ['errorType' => null, 'errorMsg' => null]];
    $rawPayload = file_get_contents('php://input');
    $payload = $rawPayload === false || $rawPayload === '' ? [] : json_decode($rawPayload, true);

    if (!is_array($payload)) {
        sendApiResponse([
            'success' => false,
            'error' => ['errorType' => 'badRequest', 'errorMsg' => 'The request payload must be a JSON object.'],
        ], 400);
    }

    try {
        new ApiDispatcher($response, $payload, $section, $action);
        sendApiResponse($response, 200);
    } catch (Throwable $exception) {
        $isClientError = str_starts_with(get_class($exception), 'Exceptions\\');
        error_log(sprintf(
            'OpenTravian API request failed: %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));

        sendApiResponse([
            'success' => false,
            'error' => [
                'errorType' => $isClientError ? 'badRequest' : 'serverError',
                'errorMsg' => $isClientError ? $exception->getMessage() : 'The request could not be completed.',
            ],
        ], $isClientError ? 400 : 500);
    }
}

function handle_v1_api_action(string $action): void
{
    handle_v1_api(null, $action);
}

$httpMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($httpMethod === 'OPTIONS') {
    applyCorsPolicy();
    http_response_code(204);
    exit;
}

$dispatcher = FastRoute\cachedDispatcher(function (FastRoute\RouteCollector $routes): void {
    $routes->addRoute(['GET', 'POST'], '/v1/{section}/{action}', 'handle_v1_api');
    $routes->addRoute(['GET', 'POST'], '/v1/{action}', 'handle_v1_api_action');
}, [
    'cacheFile' => __DIR__ . '/include/route.cache',
    'cacheDisabled' => true,
]);

$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
if (($queryPosition = strpos($uri, '?')) !== false) {
    $uri = substr($uri, 0, $queryPosition);
}

$routeInfo = $dispatcher->dispatch($httpMethod, rawurldecode($uri));
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        sendApiResponse(['success' => false, 'error' => ['errorType' => 'notFound', 'errorMsg' => 'Route not found.']], 404);
        break;

    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        header('Allow: ' . implode(', ', $routeInfo[1]));
        sendApiResponse(['success' => false, 'error' => ['errorType' => 'methodNotAllowed', 'errorMsg' => 'Method not allowed.']], 405);
        break;

    case FastRoute\Dispatcher::FOUND:
        call_user_func_array($routeInfo[1], $routeInfo[2]);
        break;
}
