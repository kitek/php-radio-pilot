<?php
use App\DataModel\User;
use Sunra\PhpSimple\HtmlDomParser;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Class NewsScraper
 */
class NewsScraper
{
    const BASE_URL = "http://radiogdansk.pl/";
    const CACHE_KEY = "news";
    public $log = [];
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
        $this->log[] = 'Started at ' . date('Y-m-d H:i:s');
        $this->scrapMeta();
        $this->scrapBody();
        $this->cacheIt();
    }

    private function scrapMeta()
    {
        $html = HtmlDomParser::str_get_html($this->fetch(self::BASE_URL . 'autopilot?t='.time()));
        foreach ($html->find('.catItemHeader') as $key => $meta) {
            $strDate = $meta->find('.middle-date')[0]->innertext();
            $strLink = $meta->find('.k2ReadMore22')[0]->attr['href'];
            $this->date = $this->parseDate($strDate);
            $this->href = $this->parseLink($strLink);
            if (!empty($this->date) && !empty($this->href)) break;
        }
        $this->log[] = 'Meta: ' . $this->date . ' ' . $this->href;
    }

    private function fetch($url)
    {
        $this->log[] = 'Fetching...';
        $this->log[] = $url;

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Cache-Control: no-cache',
                'Connection: close',
                'Pragma: no-cache'
            ],
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36'
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
        $html = HtmlDomParser::str_get_html($this->fetch($this->href.'?t='.time()));

        foreach ($html->find('.middle-date-hours') as $key => $hour) {
            $hours[] = trim($hour->innertext);
        }
        foreach ($html->find('.catItemTitle') as $key => $header) {
            $headers[] = trim(html_entity_decode($header->innertext));
        }

        foreach ($html->find('.latestItemIntroText') as $key => $description) {
            $text = trim(strip_tags($description->innertext));
            $text = preg_replace("/Podziel się/", "", $text);
            $text = preg_replace("/\s+/", " ", $text);
            $descriptions[] = trim(html_entity_decode($text));
        }
        for ($i = 0; $i < count($hours); $i++) {
            $this->results[$i]['date'] = $this->date . " " . $hours[$i];
            $this->results[$i]['header'] = $headers[$i];
            $this->results[$i]['description'] = $descriptions[$i];
        }

        $this->log[] = 'News found: ' . count($this->results);
        $this->log[] = '------------------------------------------------------';
        foreach ($this->results as $item) {
               $this->log[] = $item['date']." | ".$item['header'];
        }
        $this->log[] = '------------------------------------------------------';
    }

    private function cacheIt()
    {
        $dbCache = new \App\DataModel\Cache();
        $memcache = new Memcache();
        $updatedAt = $this->getUpdatedAt($this->results);
        $news = $dbCache->get();

        $cacheMe = ['items' => $this->results, 'updatedAt' => date('Y-m-d H:i:s')];
        $memcache->set(self::CACHE_KEY, $cacheMe);
        $dbCache->save($cacheMe);

        if (!empty($news)) {
            $lastUpdatedAt = $this->getUpdatedAt($news['items']);
            $requireSend = !empty($lastUpdatedAt) && $lastUpdatedAt != $updatedAt;
            $this->log[] = 'News last updated at: ' . $lastUpdatedAt . ' shipment: ' . ($requireSend ? 'yes' : 'no');
            if ($requireSend) {
                $this->sendNewsletter($lastUpdatedAt);
            }
        } else {
            $this->log[] = 'No previous news. Cant send notifications.';
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
        $this->log[] = 'Sending notification...';

        $news = array_filter($this->results, function ($a) use ($lastUpdatedAt) {
            return $a['date'] > $lastUpdatedAt;
        });
        if (empty($news)) {
            $this->log[] = 'No news to send.';
            return;
        }
        $lastNews = reset($news);
        $this->log[] = $lastNews['date']." | ".$lastNews['header'];

        $repo = new User();
        $users = $repo->findAll();

        $ids = $this->getRecipients($users, $lastNews);
        if (empty($ids)) return;

        $sent = $this->sendNotification($ids, $lastNews['header'], $lastNews['description']);

        $this->log[] = '------------------------------------------------------';
        $this->log[] = 'Newsletter summary:';
        $this->log[] = 'Total: '.count($ids);
        $this->log[] = 'Success: '.$sent['success'];
        $this->log[] = 'Failure: '.$sent['failure'];

        $wrongRecipients = [];
        if (!empty($sent['failure'])) {
            foreach ($sent['results'] as $key => $value) {
                if (array_key_exists('error', $value)) {
                    $wrongRecipients[] = $this->findUser($ids[$key], $users);
                }
            }
        }

        $this->log[] = 'Wrong recipients: ' . count($wrongRecipients);
        $this->log[] = '------------------------------------------------------';

        if (!empty($wrongRecipients)) {
            $repo->remove($wrongRecipients);
        }
    }

    private function getRecipients($users, $lastNews)
    {
        if (empty($users)) {
            $this->log[] = 'No users in db.';
            return [];
        }
        $ids = array_map(function ($a) use ($lastNews) {
            if (empty($a->alertPhrases)) return $a->getKeyName();
            $found = false;
            foreach ($a->alertPhrases as $phrase) {
                if (false !== stripos($lastNews['header'], $phrase) || false !== stripos($lastNews['description'], $phrase)) {
                    $found = true;
                    break;
                }
            }
            return $found ? $a->getKeyName() : '';
        }, $users);

        $ids = array_filter($ids, function ($a) {
            return !empty($a);
        });

        $results = [];
        foreach ($ids as $id) {
            $results[] = $id;
        }

        $this->log[] = 'Recipients: ' . count($users) . ' (total target: ' . count($results).')';
        $this->log[] = '------------------------------------------------------';
        foreach ($results as $id) {
            $this->log[] = $id;    
        }
        $this->log[] = '------------------------------------------------------';
        
        return $results;
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

        $this->log[] = 'FCM message:';
        $this->log[] = json_encode($message);

        $response = $this->post($this->fcmServerUrl, json_encode($message), $this->fcmServerKey);
        $this->log[] = 'Raw response:';
        $this->log[] = $response;
        return json_decode($response, true);
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
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:25.0) Gecko/20100101 Firefox/25.0'
        ));
        $resp = curl_exec($curl);
        curl_close($curl);
        return $resp;
    }

    private function findUser($deviceToken, $users)
    {
        foreach ($users as $user) {
            if ($user->getKeyName() == $deviceToken) {
                return $user;
            }
        }
        return null;
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

$newsScraper->log[] = 'Done.';

foreach ($newsScraper->log as $log) {
    syslog(LOG_INFO, (in_array(gettype($log), ['array', 'object'])) ? json_encode($log) : $log);
}

echo json_encode($newsScraper->log);
