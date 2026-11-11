<?php

use Core\Database\GlobalDB;
use Core\Helper\WebService;
use Core\PackageCode;
use Core\Session;

class GoldPackageCodeGeneratorCtrl
{
    /** @var int */
    private $pageSize = 50;
    const NEXT_SHAPE = '»';
    const PREVIOUS_SHAPE = '«';

    /**
     * @param bool $isGift
     */
    public function __construct($isGift = false)
    {
        $title = $isGift ? 'Gift code generator' : 'Code generator';
        $lead = $isGift
            ? 'Generate gift redeem codes for gold packages.'
            : 'Generate payment redeem codes for gold packages.';
        if (!getCustom('allowInterruptionInGame')) {
            Dispatcher::getInstance()->appendContent(\Game\AdminUi::page(
                $title,
                $lead,
                \Game\AdminUi::notice('Live-world changes are locked. Allow them with the toggle in the footer to use this page.', 'error')
            ));
            return;
        }
        $db = GlobalDB::getInstance();
        if (($_GET['section'] ?? null) === 'del' && is_string($_GET['id'] ?? null) && ctype_digit($_GET['id']) && Session::validateChecker()) {
            $db->run('DELETE FROM package_codes WHERE id=?', [(int)$_GET['id']]);
        }
        $params = [];
        $params['isGift'] = $isGift;
        $params['count'] = 0;
        $params['selectedGoldProductId'] = null;
        $params['packages'] = [];
        $params['currency'] = \Game\Payment\PaymentConfig::fromEnvironment()->currency();
        $products = $db->query("SELECT * FROM `goldProducts` WHERE goldProductPrice>0 ORDER BY goldProductId ASC");
        $valid_product_ids = [];
        while ($prod = $products->fetch_assoc()) {
            $valid_product_ids[] = $prod['goldProductId'];
            $params['packages'][] = $prod;
        }
        $params['selectedGoldProductId'] = is_string($_REQUEST['package'] ?? null) && in_array((int)$_REQUEST['package'], array_map('intval', $valid_product_ids), true) ? (int)$_REQUEST['package'] : null;
        if (WebService::isPost() && isset($_REQUEST['package']) && is_string($_REQUEST['count'] ?? null) && Session::validateChecker()) {
            $params['count'] = min(abs((int)$_REQUEST['count']), 1000);
            if ($params['count'] && $params['selectedGoldProductId']) {
                $codes = PackageCode::generateCode($params['selectedGoldProductId'], $params['count'], $params['isGift']);
                $params['generatedCodes'] = join("\n", $codes);
            }
        }
        $packageId = $params['selectedGoldProductId'];
        $page = max(1, (int)($_REQUEST['p'] ?? 1));
        $params['content'] = null;
        if ($packageId !== NULL) {
            $result = PackageCode::getCodesByPage($packageId, $page, $this->pageSize, $params['isGift']);
            while ($row = $result->fetch_assoc()) {
                $row['product'] = PackageCode::getProduct($row['package_id']);
                $params['content'] .= '<tr>';
                $params['content'] .= '<td class="center"><a href="admin.php?package=' . $packageId . '&'.Session::getCheckerName().'=' . Session::getInstance()->getChecker() . '&action=goldPackageCodeGenerator&id=' . $row['id'] . '&section=del">Delete</a></td>';
                $params['content'] .= '<td class="center">' . $row['id'] . '</td>';
                $params['content'] .= '<td class="center">' . \Game\AdminUi::esc($row['code']) . '</td>';
                $params['content'] .= '<td class="center">' . \Game\AdminUi::esc($row['product']['goldProductName']) . ' - ' . ($row['product']['goldProductGold']) . ' Golds</td>';
                $params['content'] .= '<td class="center">' . ($row['product']['goldProductPrice']) . ' ' . $params['currency'] . '</td>';
                $params['content'] .= '</tr>';
            }
            if (!$result->num_rows) {
                $params['content'] .= '<tr><td colspan="5" class="center adminMuted">No unused codes for this package.</td></tr>';
            }
            if($params['isGift']){
                $params['navigator'] = $this->getNavigator($page, $db->fetchScalar("SELECT COUNT(id) FROM package_codes WHERE used=0 AND isGift=1"), ['action' => 'goldPackageCodeGenerator', 'packageId' => $packageId]);
            } else {
                $params['navigator'] = $this->getNavigator($page, $db->fetchScalar("SELECT COUNT(id) FROM package_codes WHERE used=0"), ['action' => 'goldPackageCodeGenerator', 'packageId' => $packageId]);
            }
        }
        Dispatcher::getInstance()->appendContent(Template::getInstance()->load($params, 'tpl/addPaymentCodeBatch.tpl')->getAsString());
    }

    /**
     * @param mixed $page
     * @param mixed $num_rows
     * @param mixed $prefix
     */
    private function previousPageNavigator($page, $num_rows, $prefix = []): string
    {
        $text = self::PREVIOUS_SHAPE;
        $total_pages = ceil($num_rows / $this->pageSize);
        if (($page - 1) >= 1 && ($page - 1) <= $total_pages) {
            $prefix['p'] = --$page;
            $query = http_build_query($prefix);
            return '<a href="?' . $query . '">' . $text . '</a>';
        }
        return $text;
    }

    /**
     * @param mixed $page
     * @param mixed $num_rows
     * @param mixed $prefix
     */
    private function nextPageNavigator($page, $num_rows, $prefix = []): string
    {
        $text = self::NEXT_SHAPE;
        $total_pages = ceil($num_rows / $this->pageSize);
        if ((1 + $page) <= $total_pages) {
            $prefix['p'] = ++$page;
            $query = http_build_query($prefix);
            return '<a href="?' . $query . '">' . $text . '</a>';
        }
        return $text;
    }

    /**
     * @param int $page
     * @param mixed $num_rows
     * @param array<mixed>|array<string, int|string> $prefix
     */
    private function getNavigator($page, $num_rows, $prefix = []): string
    {
        return $this->previousPageNavigator($page, $num_rows, $prefix) . ' | ' . $this->nextPageNavigator($page, $num_rows, $prefix);
    }
}