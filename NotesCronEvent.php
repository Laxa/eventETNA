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
    $config = Etna::getConfigFile('config');
    /* !SCRIPT SETTINGS */

    if (!(file_exists('notes') && is_dir('notes')))
        shell_exec('mkdir notes');

    if (!file_exists('notes/'.$config['refUser']))
    {
        echo "Looks like it's the first time that the script is running, fetching all notes from promotions\n";
        Etna::updateNotes($config, true);
    }
    else
    {
        /* $current = json_decode(file_get_contents('toto'), true); */
        $current = Etna::getNotesForUser($config['refUser'], $config, false);
        $old = json_decode(file_get_contents('notes/'.$config['refUser']), true);
        /* If there is a diff, we need to udpdate our datas to be accurate */
        if (($array = Etna::diff($current, $old)) != false)
        {
            Etna::updateNotes($config);
            /* Get all notes for the diff */
            $users = Etna::getUsersListForPromos($config);
            $msg = Etna::getSpecificNotesForUsers($users, $array);
            Etna::slack($msg);
        }
    }

    Etna::setConfigFile('config', $config);
}
catch (Exception $e)
{
    echo "Something unexpected happened!\n";
    echo 'In file '.$e->getFile().' line '.$e->getLine().' : '.$e->getMessage()."\n";
}
/* ---END OF SCRIPT--- */
