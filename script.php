<?php

/* CUSTOM ERROR HANDLING */
set_error_handler('exceptions_error_handler');

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}
/* !CUSTOM_ERROR_HANDLING */

/* SCRIPT HERE */
try
{
    if (!(file_exists('notes') && is_dir('notes')))
        shell_exec('mkdir notes');

    $cookie = trim(file_get_contents('cookie'));
    /* getNotesByPromo('https://intra.etna-alternance.net/report/trombi/list/term/Master%20-%20Mars/year/2017', $cookie); */
    /* master ED */
    getNotesByPromo('https://intra.etna-alternance.net/report/trombi/list/term/Master%20ED%20-%20Mars/year/2017', $cookie);
}
catch (Exception $e)
{
    echo "Something unexpected happened!\n";
    echo $e->getLine().':'.$e->getMessage()."\n";
}
/* getNotesForUser(42, $toto = 2); */
/* ---END OF SCRIPT--- */

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

function getNotesByPromo($url, &$cookie)
{
    $users = getUsers($url, $cookie);
    foreach ($users as $user => $id)
    {
        echo "Getting notes for $id:$user...";
        getNotesForUser($id, $cookie);
        echo "Done!\n";
    }
}

function getNotesForUser($id, &$cookie)
{
    $userPage = get('https://intra.etna-alternance.net/report/index/summary/id/'.$id, $cookie);
    /* for offline dev */
    /* $userPage = file_get_contents('report.example'); */
    /* Treating the HTML page */
    $userPage = html_entity_decode($userPage);
    $userPage = utf8_encode($userPage);
    /* $array = explode('<th class="marks_uv" colspan="5">', $userPage); */
    $array = preg_split('#th class="marks_uv" colspan="[5-6]">#', $userPage);
    if (!sizeof($array))
    {
        echo "Error while preg_splitting the report page\n";
        exit(-1);
    }
    /* We throw away first elem, cause it's useless one */
    array_shift($array);
    $notes = array();
    while ($uv = array_shift($array))
    {
        preg_match('#([^<]+)#', $uv, $match);
        $notes[trim($match[1])] = getNotesForUv($uv);
    }
    file_put_contents('notes/'.$id, json_encode($notes));
    return $notes;
}

function getNotesForUv($uv)
{
    $notes = array();
    preg_match_all('#<tr[^>]*>(.*?)</tr#s', $uv, $matches);
    $tmp = $matches[1];
    $size = sizeof($tmp);
    for ($i = 0; $i < $size; $i++)
    {
        preg_match_all('#<td[^>]*>(.*?)</td#s', $tmp[$i], $noteRow);
        /* should never happen, but you never know with euteuna... */
        try
        {
            $notes[$i]["date"] = trim(strip_tags($noteRow[1][0]));
            $notes[$i]["intitule"] = trim(strip_tags($noteRow[1][1]));
            if ($noteRow[1][2][0] == '/')
                $notes[$i]["note"] = "NYD";
            else
                $notes[$i]["note"] = (int)trim(strip_tags($noteRow[1][2]));
            $notes[$i]["moyenne"] = trim(strip_tags($noteRow[1][3]));
            $notes[$i]["commentaire"] = trim(strip_tags($noteRow[1][4]));
            if (preg_match("#ref='([^']*)#", $noteRow[1][4], $link))
                $notes[$i]["link"] = 'https://intra.etna-alternance.net'.$link[1];
        }
        catch (Exception $e)
        {
            var_dump($noteRow);
            var_dump($link);
            exit(-1);
            /* echo "L'intranet est en carton, rien de nouveau jusqu'ici...\n"; */
            /* echo $e->getLine().':'.$e->getMessage()."\n"; */
            /* return; */
        }
    }
    return $notes;
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
    /* Pattern to get id from promotion page */
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
