<?php
use App\DataModel\User;
use Sunra\PhpSimple\HtmlDomParser;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/../../vendor/autoload.php';


class NewsScraper
{
    const BASE_URL = "http://m.radiogdansk.pl/";
    const CACHE_KEY = "news";

    private $date = "";
    private $href = "";
    private $results = [];
    private $fcmServerUrl = "";
    private $fcmServerKey = "";

    public function __construct($config)
    {
        $this->fcmServerUrl = $config['fcm_url'];
        $this->fcmServerKey = $config['fcm_server_key'];
    }

    function execute()
    {
        $this->scrapMeta();
        $this->scrapBody();
        $this->cacheIt();
    }

    private function scrapMeta()
    {
        $html = HtmlDomParser::str_get_html($this->fetch(self::BASE_URL . 'autopilot'));
        foreach ($html->find('.catItemHeader') as $key => $meta) {
            $strDate = $meta->find('.middle-date')[0]->innertext();
            $strLink = $meta->find('.k2ReadMore22')[0]->attr['href'];
            $this->date = $this->parseDate($strDate);
            $this->href = $this->parseLink($strLink);
            if (!empty($this->date) && !empty($this->href)) break;
        }
    }

    private function fetch($url)
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

    /**
     * Change "01 czerwca 2017" into "2017-06-01"
     * @param $strDate
     * @return string
     * @throws Exception
     */
    private function parseDate($strDate)
    {
        $months = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września',
            'października', 'listopada', 'grudnia'];
        if (empty($strDate)) throw new Exception('Date cannot be empty.');
        $parts = explode(' ', $strDate);
        if (count($parts) != 3) throw new Exception('Invalid format. Expected 3 items got: ' . count($parts));
        $day = (int)$parts[0];
        $month = array_search($parts[1], $months);
        if ($months == false) throw new Exception('Invalid format. Unknown month name.');
        $month += 1;
        $year = (int)$parts[2];
        if ($day < 10) $day = "0" . $day;
        if ($month < 10) $month = "0" . $month;
        return $year . "-" . $month . "-" . $day;
    }

    private function parseLink($strLink)
    {
        $matches = [];
        preg_match('/\/index.php\/relacje-live\/([a-zA-Z0-9\-]+)\.html/', $strLink, $matches);
        if (count($matches) != 2) return false;
        return self::BASE_URL . 'relacje-live/' . $matches[1];
    }

    private function scrapBody()
    {
        if (empty($this->href)) throw new Exception('Invalid href to scrap body.');
        $hours = $headers = $descriptions = [];
        $html = HtmlDomParser::str_get_html($this->fetch($this->href));
        foreach ($html->find('.middle-date-hours') as $key => $hour) {
            $hours[] = trim($hour->innertext);
        }
        foreach ($html->find('.catItemTitle-live') as $key => $header) {
            $headers[] = trim($header->innertext);
        }
        foreach ($html->find('.latestItemIntroText') as $key => $description) {
            $text = trim(strip_tags($description->innertext));
            $text = preg_replace("/Podziel się/", "", $text);
            $text = preg_replace("/\s+/", " ", $text);
            $descriptions[] = trim($text);
        }
        for ($i = 0; $i < count($hours); $i++) {
            $this->results[$i]['date'] = $this->date . " " . $hours[$i];
            $this->results[$i]['header'] = $headers[$i];
            $this->results[$i]['description'] = $descriptions[$i];
        }
    }

    private function cacheIt()
    {
        $memcache = new Memcache();
        $updatedAt = $this->getUpdatedAt($this->results);

        $news = $memcache->get(self::CACHE_KEY);
        $memcache->set(self::CACHE_KEY, ['items' => $this->results, 'updatedAt' => date('Y-m-d H:i:s')]);

        if (!empty($news)) {
            $lastUpdatedAt = $this->getUpdatedAt($news['items']);
            if (!empty($lastUpdatedAt) && $lastUpdatedAt != $updatedAt) {
                $this->sendNewsletter($lastUpdatedAt);
            }
        }
    }

    private function getUpdatedAt($items)
    {
        if (empty($items)) return '';
        usort($items, function ($a, $b) {
            return $a['date'] < $b['date'];
        });
        return $items[0]['date'];
    }

    private function sendNewsletter($lastUpdatedAt)
    {
        $news = array_filter($this->results, function ($a) use ($lastUpdatedAt) {
            return $a['date'] > $lastUpdatedAt;
        });
        if (empty($news)) return;
        $lastNews = $news[0];

        // find users to send
        $users = new User();
        $rows = $users->findAll();
        if (empty($rows)) return;
        $ids = array_map(function ($a) {
            return $a->getKeyName();
        }, $rows);

        $this->sendNotification($ids, $lastNews['header'], $lastNews['description']);
    }

    private function sendNotification($to, $title, $body)
    {
        $message = [
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default'
            ],
            'time_to_live' => 300,
            'collapse_key' => 'News',
            'registration_ids' => $to
        ];

        return $this->post($this->fcmServerUrl, json_encode($message), $this->fcmServerKey);
    }

    private function post($url, $message, $key)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $message,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: key=$key",
            ],
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:25.0) Gecko/20100101 Firefox/25.0'
        ));
        $resp = curl_exec($curl);
        curl_close($curl);
        return $resp;
    }

    function display()
    {
        header('Content-Type: application/json');
        echo json_encode($this->results);
    }
}

$config = __DIR__ . '/../../config/settings.yml';

$newsScraper = new NewsScraper(Yaml::parse(file_get_contents($config)));
$newsScraper->execute();

echo 'OK ' . date('Y-m-d H:i:s');
