<?php

namespace Controller;

use Core\Session;

use const DIRECTORY_SEPARATOR;

/**
 * @param mixed $response
 * @return mixed
 */
function response($response)
{
    // /api/v1 callers use Travian.api(), whose default transport is JSON.  HTML
    // is therefore data inside that envelope unless a caller explicitly asks
    // for the one legacy raw-HTML endpoint.
    $rawHtml = !empty($response['_rawHtml']);
    unset($response['_rawHtml']);
    if ($rawHtml && isset($response['html']) && is_string($response['html'])) {
        header('Content-Type: text/html; charset=UTF-8');
        echo $response['html'];
        exit(0);
    }
    if (!empty($response['error'])) {
        $response['success'] = false;
        // A handler may have selected a dialog context before discovering that
        // the player state or gold balance changed. Error callbacks must never
        // inherit a success-side navigation instruction from that earlier step.
        unset($response['functionToCall'], $response['redirectTo']);
        $response['reload'] = false;
        $response['reloadOnClose'] = false;
    }
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(!empty($response['error']) ? 400 : 200);
    echo json_encode($response);
    exit(0);
}

class AjaxCtrl extends AnyCtrl
{
    public function __construct()
    {
        parent::__construct();
        $response = ['reload' => false, 'error' => false, 'success' => true, 'message' => '', 'reloadOnClose' => false];
        if (!isset($_GET['cmd']) || !is_string($_GET['cmd']) || !preg_match('/^[A-Za-z0-9-]+$/', $_GET['cmd'])) {
            $response['error'] = true;
            $response['message'] = 'Parameter "cmd" can not be empty or invalid.';
            response($response);
        }

        $cmd = $this->toDispatcherSegment($_GET['cmd']);
        $section = '';
        if (isset($_GET['section'])) {
            if (!is_string($_GET['section']) || !preg_match('/^[A-Za-z0-9-]+$/', $_GET['section'])) {
                $response['error'] = true;
                $response['message'] = 'Parameter "section" is invalid.';
                response($response);
            }
            $section = $this->toDispatcherSegment($_GET['section']);
        }

        if (!in_array($cmd, ['news', 'configuration'], true)) {
            $this->checkAjaxToken($response);
        }

        $controllerFile = __DIR__ . DIRECTORY_SEPARATOR . 'Ajax' . DIRECTORY_SEPARATOR
            . ($section !== '' ? $section . DIRECTORY_SEPARATOR : '') . $cmd . '.php';
        if (!is_file($controllerFile)) {
            $response['error'] = true;
            $response['message'] = "Parameter \"$cmd\" (ajax.php) is not valid in \"cmd\".";
            response($response);
        }

        $controller = '\\Controller\\Ajax\\' . ($section !== '' ? $section . '\\' : '') . $cmd;
        $dispatcher = new $controller($response);
        if (method_exists($dispatcher, 'dispatch')) {
            // Dialog-only endpoints can legitimately be called without fields.
            $rawPayload = file_get_contents('php://input');
            $payload = $rawPayload === '' ? [] : json_decode($rawPayload, true);
            if (!is_array($payload)) {
                $response['error'] = true;
                $response['message'] = 'The request payload must be a JSON object.';
                response($response);
            }

            $_POST = $this->sanitizePayload($payload);
            $dispatcher->dispatch();
        }
        response($response);
    }

    /**
     * @param mixed $value
     */
    private function toDispatcherSegment($value): string
    {
        $segment = str_replace(' ', '', ucwords(str_replace('-', ' ', $value)));
        return lcfirst($segment);
    }

    /**
     * @param mixed $value
     * @return array<mixed>|string
     */
    private function sanitizePayload($value)
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizePayload($item);
            }
            return $sanitized;
        }
        if (!is_scalar($value) && $value !== null) {
            return '';
        }
        return trim(str_replace("\0", '', strip_tags((string)$value)));
    }

    /**
     * @param array<mixed> $response
     */
    private function checkAjaxToken(&$response): bool
    {
        if (!hash_equals((string)$this->session->getAjaxToken(), (string)$this->getBearerToken())) {
            $response['error'] = true;
            $response['message'] = 'Invalid token.';
            response($response);
        }
        return true;
    }

    /**
     * @return string|null
     */
    private function getAuthorizationHeader()
    {
        if (isset($_SERVER['Authorization'])) {
            return trim($_SERVER['Authorization']);
        }
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }
        if (function_exists('apache_request_headers')) {
            $requestHeaders = array_combine(array_map('ucwords', array_keys(apache_request_headers())), array_values(apache_request_headers()));
            if (isset($requestHeaders['Authorization'])) {
                return trim($requestHeaders['Authorization']);
            }
        }
        return null;
    }

    /**
     * @return string|null
     */
    private function getBearerToken()
    {
        $headers = $this->getAuthorizationHeader();
        if ($headers !== null && preg_match('/Bearer\\s+(\\S+)/', $headers, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
