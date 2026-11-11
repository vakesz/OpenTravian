<?php
namespace resources\View;
class PHPBatchView
{
	/** @var array<mixed> */
	public $vars = [];
	/** @var string */
	public $filename;
	/** @var mixed */
	private $name;

	/**
	 * @param mixed $name
	 */
	public function __construct($name)
	{
		$this->name = $name;
		$this->filename = TEMPLATES_PATH.$name.'.php';
	}

	/**
	 * @param string $name
	 * @param mixed $vars
	 * @return mixed
	 */
	public static function render($name, $vars = NULL)
	{
		$view = new PHPBatchView($name);

		return $view->output($vars);
	}

	/**
	 * @param mixed $vars
	 * @return bool|string
	 */
	public function output($vars = NULL)
	{
		ob_start();
		$this->display($vars);

		return ob_get_clean();
	}

	/**
	 * @param mixed $vars
	 */
	public function display($vars = NULL): void
	{
		$vars = is_null($vars) ? $this->vars : $vars;
		$vars['batch_file_name'] = $this->name;
		if($this->name == 'empty') {
			echo $vars['content'];

			return;
		}
		require($this->filename);
	}
} 