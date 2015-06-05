<?php

require_once __DIR__.'/Etna.php';

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
        Etna::getNotesByPromo('https://intra.etna-alternance.net/report/trombi/list/term/Master%20-%20Mars/year/2017', $cookie, true);
        Etna::getNotesByPromo('https://intra.etna-alternance.net/report/trombi/list/term/Master%20ED%20-%20Mars/year/2017', $cookie, true);
    }
    else
    {
        /* $current = json_decode(file_get_contents('toto'), true); */
        $current = Etna::getNotesForUser($refUserId, $cookie, false);
        $old = json_decode(file_get_contents('notes/'.$refUserId), true);
        /* If there is a diff, we need to udpdate our datas to be accurate */
        if (($array = Etna::diff($current, $old)) != false)
        {
            Etna::slack($array['msg']);
            Etna::getNotesByPromo('https://intra.etna-alternance.net/report/trombi/list/term/Master%20-%20Mars/year/2017', $cookie);
            Etna::getNotesByPromo('https://intra.etna-alternance.net/report/trombi/list/term/Master%20ED%20-%20Mars/year/2017', $cookie);
            /* Get all notes for the diff */
            $users = Etna::getUsers('https://intra.etna-alternance.net/report/trombi/list/term/Master%20-%20Mars/year/2017', $cookie);
            $users = array_merge(Etna::getUsers('https://intra.etna-alternance.net/report/trombi/list/term/Master%20ED%20-%20Mars/year/2017', $cookie), $users);
            $msg = Etna::getSpecificNotesForUsers($users, $array);
            Etna::slack($msg);
        }
    }

    file_put_contents('cookie', $cookie);
}
catch (Exception $e)
{
    echo "Something unexpected happened!\n";
    echo 'In file '.$e->getFile().' line '.$e->getLine().' : '.$e->getMessage()."\n";
}
/* ---END OF SCRIPT--- */
