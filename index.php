<?php
require('lib/simple_html_dom.php');

function fetch($url)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:25.0) Gecko/20100101 Firefox/25.0'
    ));
    $resp = curl_exec($curl);
    curl_close($curl);
    return $resp;
}

$main = fetch('http://m.radiogdansk.pl/autopilot');
$matches = array();
preg_match_all('/href="\/index\.php(\/relacje-live\/[^"]+)/', $main, $matches);
$links = array_unique($matches[1]);
$link = 'http://m.radiogdansk.pl' . $links[0];
$html = file_get_html($link);

$hours = array();
$headers = array();
$descriptions = array();
$table = array();

foreach ($html->find('.middle-date-hours') as $key => $hour) {
    $hours[] = $hour->innertext;
}

foreach ($html->find('.catItemTitle-live') as $key => $header) {
    $headers[] = trim($header->innertext);
}

foreach ($html->find('.latestItemIntroText') as $key => $description) {
	$text = trim(strip_tags($description->innertext));
	$text = str_replace("Podziel się", "", $text);
	$text = preg_replace("/\s+/", " ", $text);
    $descriptions[] = mb_convert_encoding(trim($text), "UTF-8");
}

for ($i = 0; $i < count($hours); $i++) {
        $table[$i]['hour'] = $hours[$i];
        $table[$i]['header'] = $headers[$i];
        $table[$i]['description'] = $descriptions[$i];
}

header('Content-Type: application/json');
echo json_encode($table);
