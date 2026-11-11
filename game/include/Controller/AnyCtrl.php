<?php
namespace Controller;
class AnyCtrl extends AbstractCtrl
{
    /**
     * @return mixed
     */
    public function render()
    {
        $output = '';
        $this->beforeRender();
        if (is_object($this->view) && method_exists($this->view, 'output')) {
            $output = $this->view->output();
        }
        $this->afterRender();
        return $output;
    }

    protected function beforeRender(): void
    {
    }

    protected function afterRender(): void
    {
    }
}
