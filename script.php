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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);

    if ($httpCode / 100 != 2)
    {
        echo "Getting $url returned a $httpCode, exiting.\n";
        exit(-1);
    }
    if (preg_match('#PHPSESSID=([^;]+)#', $header, $matches))
        $cookie = $matches[1];

    curl_close($ch);

    return $body;
}

function main()
{
    $cookie = trim(file_get_contents('cookie'));
    $json = array();
    $users = getUsers('https://intra.etna-alternance.net/report/trombi/list/term/Master%20-%20Mars/year/2017', $cookie);
    var_dump($users);
}

function getUsers($url, &$cookie)
{
    $matches = array();
    $etna2017 = get($url, $cookie);
    /* Pattern to get login from promotion page */
    if (preg_match_all('#photo-name\">([^<]+)#', $etna2017, $matches) == 0 || !sizeof($matches[1]))
    {
        echo "Failed to preg_match_all login on promotion page\n";
        exit(-1);
    }
    $tmp = $matches[1];
    if (preg_match_all('#summary\/id\/([^\"]+)#', $etna2017, $matches) == 0 || !sizeof($matches[1]))
    {
        echo "Failed to preg_match_all ids on promotion page\n";
        exit(-1);
    }
    $users = array();
    for ($i = 0; $i < sizeof($tmp); $i++)
        $users[$tmp[$i]] = $matches[1][$i + 1];
    return $users;
}

main();
