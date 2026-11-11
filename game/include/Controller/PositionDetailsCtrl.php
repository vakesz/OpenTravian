<?php

namespace Controller;

/**
 * Like the reference, a tile address opens the map on that tile: the map page shows the tile details
 * dialog for coordinates in its URL.
 */
class PositionDetailsCtrl extends GameCtrl
{
    public function __construct()
    {
        parent::__construct();
        if (isset($_GET['x'], $_GET['y']) && is_numeric($_GET['x']) && is_numeric($_GET['y'])) {
            $this->redirect('/karte.php?x=' . (int)$_GET['x'] . '&y=' . (int)$_GET['y']);
        }
        $this->redirect('/karte.php');
    }
}
