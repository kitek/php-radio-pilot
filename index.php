<?php
$memcache = new Memcache();
$news = $memcache->get('news');
if (empty($news)) $news = array();

header('Content-Type: application/json');
echo json_encode($news);
