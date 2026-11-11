<?php

declare(strict_types=1);

namespace Core;

class CntrlNotFoundException extends \Exception
{
}

class CntrlPermissionDeniedException extends \Exception
{
}

class Dispatcher
{
    public function dispatch(?string $ctrl = null, bool $print = true): bool|string
    {
        try {
            return $this->dispatcher($ctrl, $print);
        } catch (CntrlNotFoundException $e) {
            try {
                return $this->dispatcher("PageNotFoundCtrl", $print);
            } catch (CntrlNotFoundException $e) {
                die("There were errors in App!!! => " . $e->getMessage());
            } catch (CntrlPermissionDeniedException $e) {
                die("There were errors in App!!! => " . $e->getMessage());
            }
        } catch (CntrlPermissionDeniedException $e) {
            try {
                return $this->dispatcher("PermissionDeniedCtrl", $print);
            } catch (CntrlNotFoundException $e) {
                die("There were errors in App!!! => " . $e->getMessage());
            } catch (CntrlPermissionDeniedException $e) {
                die("There were errors in App!!! => " . $e->getMessage());
            }
        }
    }

    private function dispatcher(?string $ctrl = null, bool $print = true): bool|string
    {
        if ($ctrl === null) {
            $ctrl = basename((string)($_SERVER['PHP_SELF'] ?? ''));
            $ctrl = explode(".php", $ctrl)[0];
            $ctrl .= 'Cntrl';
        }
        $ctrl = 'Controller\\' . ucfirst($ctrl);
        if (!is_file(Autoloader::getFullPath($ctrl))) {
            //show a 404 page here.
            throw new CntrlNotFoundException($ctrl);
        }
        $ctrl = new $ctrl();
        if ($print) {
            echo $ctrl->render();
            return true;
        } else {
            $x = $ctrl->render();
            return $x;
        }
    }

    private static ?self $_self = null;

    public static function getInstance(): self
    {
        return self::$_self ??= new self();
    }
}
