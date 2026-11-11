<?php

use Core\Database\DB;
use Game\Formulas;

class MinimapCtrl
{
    public function __construct()
    {
        $params = ['content' => null];
        $params['content'] = $this->renderMinimap();
        $dispatcher = Dispatcher::getInstance();
        $dispatcher->appendContent(Template::getInstance()->load($params, 'tpl/map.tpl')->getAsString());
    }

    /**
     * @return string|null
     */
    private function renderMinimap()
    {
        $content = null;
        $db = DB::getInstance();
        $array_tribe = [1 => 'Romans', 'Teutons', 'Gauls', 'Nature', 'Natars', 'Egyptians', 'Huns', 'Spartans', 'Vikings'];
        $villages = $db->query("SELECT v.kid, v.isWW, v.isFarm, v.owner, v.name villageName, v.capital, v.pop, u.name, u.race, u.access, w.x, w.y FROM vdata AS v LEFT JOIN users AS u ON v.owner=u.id LEFT JOIN wdata AS w ON v.kid=w.id");
        $cap_kid = Formulas::xy2kid(0, 0);
        while ($row = $villages->fetch_assoc()) {
            if (isset($_GET['onlyArtifacts'])) {
                $type = $db->fetchScalar('SELECT type FROM artefacts WHERE kid=?', [(int)$row['kid']]);
                if ($type === false) {
                    continue;
                }
                if (is_string($_GET['type'] ?? null) && (int)$type !== (int)$_GET['type']) {
                    continue;
                }
            }
            $dot = $row['kid'] == $cap_kid ? 0 : (int)$row['race'];
            $title = sprintf(
                '%s - %s (%s|%s) - population %d - %s',
                $row['name'],
                $row['villageName'],
                $row['x'],
                $row['y'],
                (int)$row['pop'],
                $array_tribe[(int)$row['race']] ?? 'Unknown'
            );
            // Positions are percentages of the square map so it scales with its box:
            // the world runs MAP_SIZE tiles from the centre to each edge, drawn inside a 2% margin.
            $left = round(50 + $row['x'] / MAP_SIZE * 48, 2);
            $top = round(50 - $row['y'] / MAP_SIZE * 48, 2);
            $content .= '<a class="mapDot tribe' . $dot . '" style="left:' . $left . '%;top:' . $top . '%"'
                . ' href="admin.php?action=editVillage&amp;kid=' . (int)$row['kid'] . '" target="_blank" rel="noopener"'
                . ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"></a>';
        }
        return $content;
    }
}