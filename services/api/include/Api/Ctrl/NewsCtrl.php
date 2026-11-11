<?php

declare(strict_types=1);

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Database\DB;
use Exceptions\ErrorException;
use PDO;

class NewsCtrl extends ApiAbstractCtrl
{
    public function getNewsById(): void
    {
        $newsId = $this->requireInt('newsId');
        $db = DB::getInstance();
        $stmt = $db->prepare('SELECT * FROM news WHERE id=:id AND expire > ' . time());
        $stmt->bindValue('id', $newsId, PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->rowCount()) {
            throw new ErrorException('No news found with ID "' . $newsId . '".');
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->response['data'] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'content' => $row['content'],
                'time' => $row['time'],
                'more' => $row['moreLink'],
            ];
        }
    }

    public function loadNews(): void
    {
        $db = DB::getInstance();
        $news = $db->select("SELECT * FROM news WHERE expire > " . time() . " ORDER BY time DESC LIMIT 3");
        $i = 3;
        while ($row = $news->fetch(PDO::FETCH_ASSOC)) {
            $this->response[] = [
                'key' => $i--,
                'id' => $row['id'],
                'title' => $row['title'],
                'content' => $row['content'],
                'time' => $row['time'],
                'more' => $row['moreLink'],
            ];
        }
    }
}