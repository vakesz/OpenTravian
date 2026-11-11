<?php

use Core\Caching\Caching;
use Core\Helper\WebService;
use Core\Session;

class FilteredUrlsCtrl
{
    /** @var string */
    private $cacheKey = 'filteredUrlsCache';
    /** @var string */
    private $fileName = 'filteredUrls.txt';
    public function __construct()
    {
        $params = [
            'urls' => is_string($_POST['urls'] ?? null) ? trim($_POST['urls']) : null
        ];
        if(!isServerFinished() && WebService::isPost() && Session::validateChecker()){
            ;
            if(!empty($params['urls'])){
                $current = $this->getFromCache();
                $add = preg_split('/[\r\n,]+/', $params['urls'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($add as $value) {
                    $current[] = trim($value);
                }
                $current = array_map('trim', $current);
                $current = array_map('strtolower', $current);
                $current = array_unique($current);
                $this->insertToFile(implode(",", $current));
                $params['error'] = 'URLs added.';
            } else {
                $params['error'] = 'No URLs to filter.';
            }
        } else {
            if(!isServerFinished() && is_string($_GET['del'] ?? null) && Session::validateChecker()){
                ;
                $current = $this->getFromCache();
                $_GET['del'] = (int)$_GET['del'];
                if(sizeof($current) > 1){
                    if(isset($current[$_GET['del']])){
                        unset($current[$_GET['del']]);
                    }
                    $this->insertToFile(implode(",", $current));
                }
            }
        }
        $params['content'] = $this->getFromCache();
        $params['total'] = sizeof($params['content']);
        Dispatcher::getInstance()->appendContent(Template::getInstance()->load($params, 'tpl/filteredUrls.tpl')->getAsString());
    }
    /**
     * @return mixed
     */
    public function getFromCache(){
        $cache = Caching::getInstance();
        if(!($cached = $cache->get($this->cacheKey))){
            $cached = explode(",", file_get_contents(FILTERING_PATH . $this->fileName));
            $cache->set($this->cacheKey, $cached, 2*86400);
        }
        return $cached;
    }
    /**
     * @param string $content
     */
    public function insertToFile($content): void{
        fclose(fopen(FILTERING_PATH . $this->fileName, 'w'));
        if(!empty($content)){
            file_put_contents(FILTERING_PATH . $this->fileName, $content) or die("File write failed.");
        }
        $this->expireCache();
    }
    public function expireCache(): void{
        Caching::getInstance()->delete($this->cacheKey);
        $this->getFromCache();
    }
}