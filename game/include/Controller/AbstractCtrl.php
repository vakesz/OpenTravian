<?php

namespace Controller;

use Core\Dispatcher;
use Core\Session;

abstract class AbstractCtrl
{
    /** @var mixed */
    protected $session;

    public function __construct()
    {
        $this->session = Session::getInstance();
    }


    /**
     * a class of View to render output.
     * @var \resources\View\GameView|\resources\View\OutOfGameView|\resources\View\PHPBatchView|null
     */
    public $view;

    /**
     * renders the view and will return it as html output.
     *
     * @return mixed
     */
    abstract public function render();

    /**
     * before render things like checking session accesses and others..
     *
     * @return mixed
     */
    abstract protected function beforeRender();

    /**
     * actions to be done after rendering completed.
     *
     * @return mixed
     */
    abstract protected function afterRender();

    /**
     * this redirects inner MVC pattern actually it will just change the Controller.
     *
     * @param string $controller
     * @param bool $print
     */
    final protected function innerRedirect($controller, $print = TRUE): void
    {
        $dispatcher = new Dispatcher();
        $dispatcher->dispatch($controller, $print);
        exit(0);
    }

    /**
     * @param int $code
     *
     * outer redirect function.
     * @param string $url
     */
    final protected function redirect($url, $code = 302): void
    {
        if (!$url) {
            return;
        }
        // Close any db connections before exit
        // Clear out any previously sent content
        if (!in_array($code, [301, 302])) {
            $code = 302;
        }
        header("Location: " . \normalizeRedirectTarget($url), TRUE, $code);
        exit();
    }
}
