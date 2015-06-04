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
    /* THIS SCRIPT IS SUPPOSED TO BE RUNNING WITH CRONTAB WE NEED TO CHANGE CWD */
    chdir(__DIR__);
    /* !THIS SCRIPT IS SUPPOSED TO BE RUNNING WITH CRONTAB WE NEED TO CHANGE CW */

    /* SCRIPT SETTINGS */
    $cookie = trim(file_get_contents('cookie'));
    /* Reference User ID : script will check on this user notes if anything has changed */
    /* you should check only promotion with same notes as this user or things are going to be wrong */
    $refUserId = 6384;
    /* !SCRIPT SETTINGS */

    if (!(file_exists('notes') && is_dir('notes')))
        shell_exec('mkdir notes');

    if (!file_exists('notes/'.$refUserId))
    {
        echo "Looks like it's the first time that the script is running, fetching all notes from promotions\n";
        getNotesByPromo('https://intra.etna-alternance.net/report/trombi/list/term/Master%20-%20Mars/year/2017', $cookie, true);
        /* getNotesByPromo('https://intra.etna-alternance.net/report/trombi/list/term/Master%20ED%20-%20Mars/year/2017', $cookie, true); */
    }
    else
    {
        /* $current = getNotesForUser($refUserId, $cookie); */
        $current = json_decode(file_get_contents('toto'), true);
        $old = json_decode(file_get_contents('notes/'.$refUserId), true);
        /* If there is a diff, we need to udpdate our datas to be accurate */
        if (($msg = diff($current, $old)) != false)
        {
            echo $msg;
            exit(-1);
            /* do something with $msg here */
            getNotesByPromo('https://intra.etna-alternance.net/report/trombi/list/term/Master%20-%20Mars/year/2017', $cookie);
            /* getNotesByPromo('https://intra.etna-alternance.net/report/trombi/list/term/Master%20ED%20-%20Mars/year/2017', $cookie); */
        }
        /* TODO : remove that */
        else
            echo "No diff found\n";
        /* !TODO */
    }

    file_put_contents('cookie', $cookie);
}
catch (Exception $e)
{
    echo "Something unexpected happened!\n";
    echo 'In file '.$e->getFile().' line '.$e->getLine().' : '.$e->getMessage()."\n";
}
/* getNotesForUser(42, $toto = 2); */
/* ---END OF SCRIPT--- */

function diff($current, $old)
{
    $msg = '';

    foreach ($current as $key => $value)
    {
        if (!isset($old[$key]))
        {
            $msg .= sprintf("Nouveau module detecte : %s\n", $key);
            foreach ($value as $k => $v)
            {
                if (isset($v['intitule']))
                    $msg .= sprintf("Nouveau intitule detecte : %s\n", $v['intitule']);
                if (isset($v['note']) && $v['note'] != 'NYD')
                    $msg .= sprintf("Nouvelle note disponible pour : %s\n", $v['intitule']);
            }
        }
    }
    if (empty($msg))
        return false;
    return $msg;
}

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

    if ((int)($httpCode / 100) != 2)
    {
        echo "Getting $url returned a $httpCode, exiting\n";
        exit(-1);
    }
    if (preg_match('#PHPSESSID=([^;]+)#', $header, $matches))
        $cookie = $matches[1];

    curl_close($ch);

    return $body;
}

function getNotesByPromo($url, &$cookie, $verbose = false)
{
    $users = getUsers($url, $cookie);
    foreach ($users as $user => $id)
    {
        if ($verbose)
            echo "Getting notes for $id:$user...";
        getNotesForUser($id, $cookie);
        if ($verbose)
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
            $notes[$i]['date'] = trim(strip_tags($noteRow[1][0]));
            $notes[$i]['intitule'] = trim(strip_tags($noteRow[1][1]));
            $note = trim($noteRow[1][2]);
            if ($note[0] == '/')
                $notes[$i]['note'] = 'NYD';
            else
                $notes[$i]['note'] = (float)trim(strip_tags($noteRow[1][2]));
            $notes[$i]['moyenne'] = trim(strip_tags($noteRow[1][3]));
            $notes[$i]['commentaire'] = trim(strip_tags($noteRow[1][4]));
            if (preg_match("#ref='([^']*)#", $noteRow[1][4], $link))
                $notes[$i]['link'] = 'https://intra.etna-alternance.net'.$link[1];
        }
        catch (Exception $e)
        {
            echo "L'intranet est en carton, rien de nouveau jusqu'ici...\n";
            echo 'In file '.$e->getFile().' line '.$e->getLine().' : '.$e->getMessage()."\n";
            return false;
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
