<?php
/*
Detect Nsfw Photo (Nyckel)

Developer: Abolfazl Kaj (@AbolfazlKaj)
Channel: https://t.me/IRA_Team

License: MIT
*/

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

define('MAX_RETRIES', 5);

// ---- Helpers ----
function respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---- FUNCTION ----
function UserAgent(): string
{
    $pick = function (array $items) {
        $sum = 0;
        foreach ($items as $it) $sum += $it['w'];
        $r = random_int(1, $sum);
        $c = 0;
        foreach ($items as $it) {
            $c += $it['w'];
            if ($r <= $c) return $it['v'];
        }
        return $items[count($items) - 1]['v'];
    };

    // --- Realistic pools ---
    $chromeWin = [
        ['v' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.7632.109 Safari/537.36', 'w' => 40],
        ['v' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.7632.110 Safari/537.36', 'w' => 60],
    ];
    $chromeMac = [
        ['v' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.7632.109 Safari/537.36', 'w' => 45],
        ['v' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.7632.110 Safari/537.36', 'w' => 55],
    ];
    $chromeLinux = [
        ['v' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.7632.109 Safari/537.36', 'w' => 50],
        ['v' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.7632.110 Safari/537.36', 'w' => 50],
    ];

    $ffWin = [
        ['v' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0',   'w' => 35],
        ['v' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0.4', 'w' => 65],
    ];
    $ffMac = [
        ['v' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0',   'w' => 35],
        ['v' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:147.0) Gecko/20100101 Firefox/147.0.4', 'w' => 65],
    ];
    $ffLinux = [
        ['v' => 'Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',   'w' => 35],
        ['v' => 'Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0.4', 'w' => 65],
    ];

    // --- Weights for OS + browser ---
    $os = $pick([
        ['v' => 'win',   'w' => 70],
        ['v' => 'mac',   'w' => 20],
        ['v' => 'linux', 'w' => 10],
    ]);

    $browser = $pick([
        ['v' => 'chrome',  'w' => 85],
        ['v' => 'firefox', 'w' => 15],
    ]);

    if ($browser === 'chrome') {
        if ($os === 'win')   return $pick($chromeWin);
        if ($os === 'mac')   return $pick($chromeMac);
        return $pick($chromeLinux);
    } else {
        if ($os === 'win')   return $pick($ffWin);
        if ($os === 'mac')   return $pick($ffMac);
        return $pick($ffLinux);
    }
}

$url = trim((string)($_GET['url'] ?? $_POST['url'] ?? ''));
if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
    respond(400, ['error' => 'Missing/invalid url']);
}

$tmp = tempnam(sys_get_temp_dir(), 'nyckel_');
if ($tmp === false) respond(500, ['error' => 'Temp file error']);

$fp = fopen($tmp, 'wb');
if ($fp === false) {
    @unlink($tmp);
    respond(500, ['error' => 'Temp file open error']);
}

$maxBytes = 10 * 1024 * 1024; // 10MB
$downloaded = 0;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_FILE           => $fp,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_USERAGENT      => UserAgent(),
]);

curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($fp, &$downloaded, $maxBytes) {
    $len = strlen($data);
    $downloaded += $len;
    if ($downloaded > $maxBytes) return 0; // stop
    return fwrite($fp, $data);
});

$ok = curl_exec($ch);
$dlErr = curl_error($ch);
$dlCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
fclose($fp);

if ($ok === false || $dlCode < 200 || $dlCode >= 300) {
    @unlink($tmp);
    respond(400, ['error' => 'Failed to download image', 'httpCode' => $dlCode, 'curlError' => $dlErr]);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? (finfo_file($finfo, $tmp) ?: 'application/octet-stream') : 'application/octet-stream';
if ($finfo) finfo_close($finfo);

$path = parse_url($url, PHP_URL_PATH);
$name = $path ? basename($path) : 'blob';
if ($name === '' || $name === '.' || $name === '/') $name = 'blob';

// req to Nyckel
$endpoint = 'https://www.nyckel.com/v1/functions/o2f0jzcdyut2qxhu/invoke';

$ua = UserAgent();
$cfile = new CURLFile($tmp, $mime, $name);

$postFields = [
    'file' => $cfile,
];

for ($i = 0; $i < MAX_RETRIES; $i++) {

    $ch2 = curl_init($endpoint);
    curl_setopt_array($ch2, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 60,

        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Referer: https://www.nyckel.com/pretrained-classifiers/nsfw-identifier/',
            'X-Requested-With: XMLHttpRequest',
        ],
        CURLOPT_USERAGENT => $ua,
    ]);

    $body = curl_exec($ch2);
    $err  = curl_error($ch2);
    $code = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if ($code != 402 && $body != "Invoke limit exceeded; Please create an account to get API access.") {
        break;
    }

    sleep(1);
}

@unlink($tmp);

if ($body === false || $code < 200 || $code >= 300) {
    respond(502, ['error' => 'Nyckel request failed', 'httpCode' => $code, 'curlError' => $err, 'body' => $body]);
}

http_response_code(200);
echo $body;
exit;
/*
Detect Nsfw Photo (Nyckel)

Developer: Abolfazl Kaj (@AbolfazlKaj)
Channel: https://t.me/IRA_Team

License: MIT
*/