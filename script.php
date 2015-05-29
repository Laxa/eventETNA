<?php

function get($url, &$cookie)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    $response = curl_exec($ch);

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    if (preg_match('#PHPSESSID=([^;]+)#', $header, $matches))
        $cookie = $matches[1];
    curl_close($ch);

    return $body;
}

function main()
{
    $cookie = trim(file_get_contents('cookie'));
    echo get('https://intra.etna-alternance.net/report/trombi/list/term/Bachelor%20-%20Mars/year/2017', $cookie);
}

main();
