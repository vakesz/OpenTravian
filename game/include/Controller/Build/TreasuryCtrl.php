<?php

namespace Controller\Build;

use Controller\AnyCtrl;
use Controller\BuildCtrl;
use Core\Config;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Core\Locale;
use Model\ArtefactsModel;
use resources\View\PHPBatchView;

class TreasuryCtrl extends AnyCtrl
{
    /** @var mixed */
    private $building_index;

    public function __construct(BuildCtrl $build)
    {
        parent::__construct();
        $this->view = new PHPBatchView("build/building/Treasury");
        $this->view->vars['content'] = '';
        $this->view->vars['showTabs'] = Village::getInstance()->getField($build->selectedBuildingIndex)['level'] > 0;
        if(0 == Village::getInstance()->getField($build->selectedBuildingIndex)['level']){
            $this->view->vars['content'] .= $build->getBuildingContract();
            return;
        }
        $this->building_index = $build->selectedBuildingIndex;
        $this->view->vars['index'] = $build->selectedBuildingIndex;
        $this->view->vars['gid'] = (int)Village::getInstance()->getField($build->selectedBuildingIndex)['item_id'];
        $this->view->vars['selectedTab'] = isset($_REQUEST['s']) && in_array($_REQUEST['s'], [0, 5, 1, 2,]) ? (int)$_REQUEST['s'] : Session::getInstance()->getFavoriteTab("buildingTreasury");
        $this->view->vars['favorText'] = T('inGame', 'Set tab');
        $this->view->vars['Management'] = get_button_id();
        $this->view->vars['favor'] = Session::getInstance()->getFavoriteTab("buildingTreasury");
        $this->view->vars['artifactsInYourArea'] = get_button_id();
        $this->view->vars['smallArtifact'] = get_button_id();
        $this->view->vars['largeArtifact'] = get_button_id();
        
        if (isset($_GET['show'])) {
            $this->show((int)$_GET['show']);
            return;
        }
        switch ($this->view->vars['selectedTab']) {
            case 0:
                $this->view->vars['content'] .= $build->getBuildingContract();
                $this->own();
                break;
            case 1:
                $this->small();
                break;
            case 2:
                $this->big();
                break;
            case 5:
                $this->near();
                break;
        }
    }

    /**
     * @param int $id
     */
    private function show($id): void
    {
        $db = DB::getInstance();
        $art = $db->query("SELECT * FROM artefacts WHERE id=$id ORDER BY conquered");
        if (!$art->num_rows) {
            return;
        }
        $row = $art->fetch_assoc();
        $view = new PHPBatchView("build/building/TreasuryArtefactShow");
        $time = time() - ArtefactsModel::getArtifactActivationTime();
        $view->vars['name'] = self::getArtifactName($row['type'], $row['size'], $row['num']);
        $view->vars['desc'] = $this->getArtDesc($row['type'], $row['effecttype'], $row['effect']);
        $view->vars['owner'] = $this->getPlayerName($row['uid']);
        $view->vars['village'] = $this->getVillageName($row['kid']);
        $view->vars['alliance'] = $this->getAllianceTag($this->getPlayerAllianceId($row['uid']));
        $view->vars['size'] = $row['aoe'];
        $view->vars['bonus'] = $this->float2rat($row['type'], $row['effect']);
        $view->vars['conquer'] = $this->getConquerTime($row['conquered']);
        $view->vars['type'] = $row['type'];
        if ($row['status'] == 2) {
            $view->vars['active'] = T("Treasury", "ArtifactIsDisabled");
        } else {
            $view->vars['active'] = $row['active'] ? T("Treasury",
                "Active") : $this->getConquerTime($row['conquered'] + ArtefactsModel::getArtifactActivationTime());
        }
        $details = $db->query("SELECT * FROM artlog WHERE artId={$row['id']} ORDER BY id ASC");
        $view->vars['showDetails'] = $details->num_rows > 0;
        $view->vars['details'] = '';
        while ($row = $details->fetch_assoc()) {
            $view->vars['details'] .= '<tr>';
            $view->vars['details'] .= '<td><a href="' . profileUrl((int) $row['uid']) . '">' . $row['name'] . '</a></td>';
            $view->vars['details'] .= '<td><a href="/karte.php?d=' . $row['kid'] . '">' . $this->getVillageName($row['kid']) . '</a></td>';
            $view->vars['details'] .= '<td>' . $this->getConquerTime($row['time']) . '</td>';
            $view->vars['details'] .= '</tr>';
        }
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * @param mixed $type
     * @param mixed $size
     * @param mixed $number
     * @param bool $includeNumber
     * @return mixed
     */
    public static function getArtifactName($type, $size, $number, $includeNumber = true)
    {
        if ($type == 12) {
            return T("Artefacts", "12.name");
        }
        if ($size == 3) {
            return T("Artefacts", $type . ".names." . $size);
        }
        return sprintf(
            T("Artefacts", $type . ".names." . $size),
            $number > 0 || $includeNumber ? T("Artefacts", "numbers." . $number) : null
        );
    }

    /**
     * @param mixed $type
     * @param mixed $effecttype
     * @param mixed $effect
     */
    private function getArtDesc($type, $effecttype, $effect): string
    {
        if ($type == 11) {
            if ($effect == 0) $effect = 1;
            return sprintf(T("Artefacts", $effecttype . ".desc"), $this->float2rat($type, $effect));
        }
        return sprintf(T("Artefacts", $type . ".desc"), $this->float2rat($type, $effect));
    }

    /**
     * @param mixed $type
     * @param mixed $n
     * @param mixed $tolerance
     * @return int|string
     */
    function float2rat($type, $n, $tolerance = 1.e-6)
    {
        if($n == 0){
            return 0;
        }
        if ($type == 11 || $type == 6 || $type == 8) {
            $h1 = 1;
            $h2 = 0;
            $k1 = 0;
            $k2 = 1;
            @$b = 1 / $n;
            do {
                @$b = 1 / $b;
                $a = floor($b);
                $aux = $h1;
                $h1 = $a * $h1 + $h2;
                $h2 = $aux;
                $aux = $k1;
                $k1 = $a * $k1 + $k2;
                $k2 = $aux;
                $b = $b - $a;
            } while (abs($n - $h1 / $k1) > $n * $tolerance);
            return "$h1" . ($k1 != 1 ? "/$k1" : '&times;');
        }
        return $n . '&times;‬';
    }

    /**
     * @param mixed $uid
     * @param mixed $useKid
     */
    private function getPlayerName($uid, $useKid = false): string
    {
        $db = DB::getInstance();
        $name = $db->fetchScalar("SELECT name FROM users WHERE id=$uid");
        if ($name) {
            if ($useKid) {
                return '<a href="/karte.php?d=' . $useKid . '">' . $name . '</a>';
            }
            return '<a href="' . profileUrl((int) $uid) . '">' . $name . '</a>';
        }
        return '<span class="errorMessage">[?]</span>';
    }

    /**
     * @param mixed $kid
     */
    private function getVillageName($kid): string
    {
        $db = DB::getInstance();
        $name = $db->fetchScalar("SELECT name FROM vdata WHERE kid=$kid");
        if ($name) {
            return '<a href="/karte.php?d=' . $kid . '">' . $name . '</a>';
        }
        return '<span class="errorMessage">[?]</span>';
    }

    /**
     * @param mixed $aid
     */
    private function getAllianceTag($aid): string
    {
        if ((int)$aid == 0) {
            return '-';
        }
        $db = DB::getInstance();
        $tag = $db->fetchScalar("SELECT tag FROM alidata WHERE id=$aid");
        if ($tag) {
            return '<a href="' . allianceUrl((int) $aid) . '">' . $tag . '</a>';
        }
        return '<span class="errorMessage">[?]</span>';
    }

    /**
     * @param mixed $uid
     */
    private function getPlayerAllianceId($uid): int
    {
        $db = DB::getInstance();
        return (int) $db->fetchScalar("SELECT aid FROM users WHERE id=$uid");
    }

    /**
     * @param mixed $time
     */
    private function getConquerTime($time): string
    {
        return TimezoneHelper::autoDateString($time, TRUE);
    }

    private function own(): void
    {
        $view = new PHPBatchView("build/building/TreasuryStoredArtifact");
        $view->vars['content'] = '';
        $db = DB::getInstance();
        $own = $db->query("SELECT * FROM artefacts WHERE uid=" . Session::getInstance()->getPlayerId() . " ORDER BY conquered");
        while ($row = $own->fetch_assoc()) {
            $view->vars['content'] .= '<tr>';
            $view->vars['content'] .= '<td class="icon"><img class="artefact_icon_' . self::getArtifactIcon($row['type']) . '" title="' . self::getArtifactName($row['type'],
                    $row['size'],
                    $row['num']) . '" src="/img/spacer.gif" alt=""></td>';
            $view->vars['content'] .= $this->getBonAndInfo($row, true);
            $view->vars['content'] .= '<td class="vil">' . $this->getVillageName($row['kid']) . '</td>';
            $view->vars['content'] .= '<td class="con">' . $this->getConquerTime($row['conquered']) . '</td>';
            $view->vars['content'] .= '</tr>';
        }
        if (!$own->num_rows) {
            $view->vars['content'] = '<tr><td colspan="4" class="none">' . T("Treasury",
                    "you dont own any artefacts") . '</td></tr>';
        }
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * @param mixed $type
     * @return mixed
     */
    private static function getArtifactIcon($type)
    {
        if ($type == ArtefactsModel::ART_FOOL) {
            return 'fool';
        }
        return $type == 12 ? 11 : $type;
    }

    /**
     * @param mixed $row
     * @param bool $own
     * @param bool $showInactive
     */
    private function getBonAndInfo($row, $own = false, $showInactive = false): string
    {
        $content = '<td class="nam ' . (($own || $showInactive) && $row['status'] == 2 ? 'inactive' : '') . '"><a href="/build.php?id=' . $this->building_index . '&amp;s=' . $this->view->vars['selectedTab'] . '&amp;show=' . $row['id'] . '">';
        $content .= self::getArtifactName($row['type'], $row['size'], $row['num']);
        $content .= '</a>';
        $content .= '<span class="bon">' . $this->getEffect($row['type'],
                $row['effecttype'],
                $row['effect']) . '</span>';
        $content .= '<div class="info">' . T("Buildings",
                "27.title") . ' <b>' . ($row['size'] == 1 ? 10 : 20) . '</b>, ' . T("Treasury",
                "Effect") . ' <b>' . ($row['size'] == 1 ? T("Treasury", "Village") : T("Treasury",
                "Account")) . '</b></div>';
        $content .= '</td>';
        return $content;
    }

    /**
     * @param mixed $type
     * @param mixed $effecttype
     * @param mixed $effect
     * @return string
     */
    private function getEffect($type, $effecttype, $effect)
    {
        $x = [
            ArtefactsModel::ARTIFACT_INCREASE_BUILDINGS_STABILITY,
            ArtefactsModel::ARTIFACT_INCREASE_SPEED,
            ArtefactsModel::ARTIFACT_SPY,
            ArtefactsModel::ARTIFACT_CRANNY,
            ArtefactsModel::ARTIFACT_DIET,
            ArtefactsModel::ARTIFACT_INCREASE_TRAINING_SPEED,
        ];
        if (in_array($effecttype, $x)) {
            return ' (‎&#x202d;&#x202d;' . $this->float2rat($type, $effect) . '&#x202c;&#x202c;‎)';
        }
        return ''; //!!! no eff.
    }

    /**
     * @return bool|null
     */
    private function small()
    {
        if (!Config::getInstance()->dynamic->ArtifactsReleased) {
            $this->view->vars['content'] .= T("Treasury", "No Artefact");
            return FALSE;
            //no art.
        }
        $size = 1;
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(12, $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_INCREASE_BUILDINGS_STABILITY,
            $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_INCREASE_SPEED, $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_SPY, $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_DIET, $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_INCREASE_TRAINING_SPEED,
            $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_GREAT_STORE, $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_CRANNY, $size);

        $this->view->vars['content'] .= $this->getArtifactFoolByTypeSize(2, $size);
        $this->view->vars['content'] .= $this->getArtifactFoolByTypeSize(4, $size);
        $this->view->vars['content'] .= $this->getArtifactFoolByTypeSize(5, $size);
        $this->view->vars['content'] .= $this->getArtifactFoolByTypeSize(6, $size);
        $this->view->vars['content'] .= $this->getArtifactFoolByTypeSize(8, $size);
        $this->view->vars['content'] .= $this->getArtifactFoolByTypeSize(10, $size);
        return null;
    }

    /**
     * @param int $type
     * @param int $size
     * @return mixed
     */
    private function getArtifactByTypeSize($type, $size)
    {
        $view = new PHPBatchView("build/building/TreasuryArtTable");
        $view->vars['content'] = '';
        $db = DB::getInstance();
        $art = $db->query("SELECT * FROM artefacts WHERE type=$type AND " . ($size == 1 ? 'size=1' : 'size!=1') . " ORDER BY id");
        while ($row = $art->fetch_assoc()) {
            $view->vars['content'] .= $this->renderArtifactOverview($row);
        }
        if (!$art->num_rows) {
            return NULL;
        }
        return $view->output();
    }

    /**
     * @param mixed $row
     * @param int $type
     */
    private function renderArtifactOverview($row, $type = 0): string
    {
        $content = '';
        $content .= '<tr>';
        $content .= '<td class="icon"><img class="artefact_icon_' . self::getArtifactIcon($row['type']) . '" title="' . self::getArtifactName($row['type'],
                $row['size'],
                $row['num']) . '" src="/img/spacer.gif" alt=""></td>';
        $content .= $this->getBonAndInfo($row, false, true);
        $content .= '<td class="pla">' . $this->getPlayerName($row['uid'], $row['kid']) . '</td>';
        if ($type == 0) {
            $content .= '<td class="al">' . $this->getAllianceTag($this->getPlayerAllianceId($row['uid'])) . '</td>';
            $content .= '<td class="dist">' . round(Formulas::getDistance(Village::getInstance()->getKid(),
                    $row['kid']),
                    1) . '</td>';
        } else {
            $content .= '<td class="dist">' . round($row['distance'], 1) . '</td>';
        }
        $content .= '</tr>';
        return $content;
    }

    /**
     * @param int $type
     * @param int $size
     * @return mixed
     */
    private function getArtifactFoolByTypeSize($type, $size)
    {
        $view = new PHPBatchView("build/building/TreasuryArtTable");
        $view->vars['content'] = '';
        $db = DB::getInstance();
        $art = $db->query("SELECT * FROM artefacts WHERE type=11 AND effecttype=$type AND " . ($size == 1 ? 'size=1' : 'size=3') . " ORDER BY id");
        while ($row = $art->fetch_assoc()) {
            $view->vars['content'] .= $this->renderArtifactOverview($row);
        }
        if (!$art->num_rows) {
            return NULL;
        }
        return $view->output();
    }

    /**
     * @return bool|null
     */
    private function big()
    {
        if (!Config::getInstance()->dynamic->ArtifactsReleased) {
            $this->view->vars['content'] .= T("Treasury", "No Artefact");
            return FALSE;
            //no art.
        }
        $size = 2;
        //$this->view->vars['content'] .= $this->getArtifactByTypeSize(12, $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_INCREASE_BUILDINGS_STABILITY,
            $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_INCREASE_SPEED, $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_SPY, $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_DIET, $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_INCREASE_TRAINING_SPEED,
            $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_GREAT_STORE, $size);
        $this->view->vars['content'] .= $this->getArtifactByTypeSize(ArtefactsModel::ARTIFACT_CRANNY, $size);
        $this->view->vars['content'] .= $this->getArtifactFoolByTypeSize(2, $size);
        $this->view->vars['content'] .= $this->getArtifactFoolByTypeSize(4, $size);
        $this->view->vars['content'] .= $this->getArtifactFoolByTypeSize(5, $size);
        $this->view->vars['content'] .= $this->getArtifactFoolByTypeSize(6, $size);
        $this->view->vars['content'] .= $this->getArtifactFoolByTypeSize(8, $size);
        $this->view->vars['content'] .= $this->getArtifactFoolByTypeSize(10, $size);
        return null;
    }

    private function near(): void
    {
        if (!Config::getInstance()->dynamic->ArtifactsReleased) {
            $this->view->vars['content'] .= T("Treasury", "No Artefact");
            return;
            //no art.
        }
        $xy = Formulas::kid2xy(Village::getInstance()->getKid());
        $view = new PHPBatchView("build/building/TreasuryArtTable");
        $view->vars['content'] = '';
        $view->vars['dist'] = TRUE;
        $db = DB::getInstance();
        $max_distance = 50;//default is 50
        $totalCoordinate = 1 + (2 * MAP_SIZE);
        $totalCoordinate2 = 1 + (3 * MAP_SIZE);
        $distance = "(SQRT(POW(((w.x-{$xy['x']}+{$totalCoordinate2})%{$totalCoordinate} -" . MAP_SIZE . "), 2) + POW(((w.y-{$xy['y']}+{$totalCoordinate2})%{$totalCoordinate} -" . MAP_SIZE . "), 2)))";
        $art = $db->query("SELECT a.*, $distance AS `distance` FROM artefacts a, wdata w WHERE w.id=a.kid AND a.kid!=" . Village::getInstance()->getKid() . " AND $distance <= $max_distance ORDER BY `distance`");
        while ($row = $art->fetch_assoc()) {
            $view->vars['content'] .= $this->renderArtifactOverview($row, 2);
        }
        $this->view->vars['content'] .= $view->output();
    }
}
