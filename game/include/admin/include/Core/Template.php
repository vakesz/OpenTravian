<?php

class Template
{
    /** @var \Template|null */
    private static $_self;

    /**
     * @return \Template
     */
    public static function getInstance()
    {
        if (!(self::$_self instanceof self)) {
            self::$_self = new self();
        }
        return self::$_self;
    }

    /**
     * @param mixed $params
     * @param mixed $templateName
     * @return \TemplateSecond
     */
    public function load($params, $templateName)
    {
        $name = preg_replace('/^tpl\/|\.tpl$/', '', (string)$templateName);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Invalid admin template name.');
        }
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $name . '.php';
        if (!is_file($file)) {
            throw new InvalidArgumentException('Unknown admin template.');
        }
        ob_start();
        require $file;
        return new TemplateSecond(ob_get_clean());
    }
}

class TemplateSecond
{
    /** @var mixed */
    private $html;

    /**
     * @param bool|string $html
     */
    public function __construct($html)
    {
        $this->html = $html;
    }

    public function display(): void
    {
        echo $this->html;
    }

    /**
     * @return mixed
     */
    public function getAsString()
    {
        return $this->html;
    }
}