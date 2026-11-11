<?php

namespace Controller\Ajax;

use Core\Session;
use function http_response_code;

abstract class AjaxBase
{
    /** @var array<mixed> */
    protected $response;
    /** @var mixed */
    protected $session;

    /**
     * @param mixed $response
     */
    public function __construct(&$response)
    {
        $this->response =& $response;
        $this->session = Session::getInstance();
    }

    /**
     * @return mixed
     */
    abstract function dispatch();

    /** The HTTP verb of the request (POST, PUT, DELETE, ...), as routed by api_legacy.php. */
    protected function method(): string
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /**
     * The decoded JSON body (AjaxCtrl puts it in $_POST for every verb); named route
     * parameters such as the id of "PUT village-group/<id>" are in $_GET.
     *
     * @return array<mixed>
     */
    protected function body(): array
    {
        return $_POST;
    }

    /**
     * @param mixed $errorMsg
     * @param int $status
     */
    protected function error($errorMsg, $status = 400): bool
    {
        http_response_code($status);
        $this->response['error'] = true;
        $this->response['message'] = $errorMsg;
        return true;
    }
}