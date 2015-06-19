<?php

class Etna
{
    public static function getConfigFile($filename)
    {
        $content = file_get_contents($filename);
        if (!$content)
            throw new Exception("Failed to open $filename");
        $config = json_decode($content, true);
        if ($config === NULL)
            throw new Exception("Looks like $filename isn't in JSON format");
        return $config;
    }

    public static function setConfigFile($filename, $config)
    {
        file_put_contents($filename, json_encode($config));
    }

    public static function updateNotes(&$config, $verbose = false)
    {
        foreach ($config['promo'] as $promo)
            self::getNotesByPromo($promo, $config, $verbose);
    }

    public static function diff($current, $old)
    {
        $array = array();

        foreach ($current as $key => $value)
        {
            $msg = '';
            if (!isset($old[$key]))
            {
                $msg .= sprintf("Nouveau module detecte `%s`\n", $key);
                foreach ($value as $k => $v)
                {
                    if (isset($v['intitule']))
                        $msg .= sprintf("Nouveau intitule detecte `%s`\n", $v['intitule']);
                    if (isset($v['note']) && $v['note'] != 'NYD')
                        $msg .= sprintf("Nouvelle note disponible pour `%s`\n", $v['intitule']);
                    $array[] = array('UV' => $key, 'intitule' => $v['intitule'], 'msg' => $msg, 'note' => $v['note']);
                }
            }
            else
            {
                foreach ($value as $k => $v)
                {
                    if ($old[$key][$k]['note'] === 'NYD' && $v['note'] != 'NYD')
                    {
                        $msg .= sprintf("Nouvelle note disponible de l'UV `%s` pour `%s`\n", $key, $v['intitule']);
                        $array[] = array('UV' => $key, 'intitule' => $v['intitule'], 'msg' => $msg, 'note' => $v['note']);
                    }
                }
            }
        }
        if (!sizeof($array))
            return false;
        return $array;
    }

    public static function get($url, &$config)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_COOKIE, $config['cookie']);

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
            $config['cookie'] = $matches[1];

        curl_close($ch);

        return $body;
    }

    public static function getNotesByPromo($url, &$config, $verbose = false)
    {
        $users = self::getUsers($url, $config);
        foreach ($users as $user => $id)
        {
            if ($verbose)
                echo "Getting notes for $id:$user...";
            self::getNotesForUser($id, $config);
            if ($verbose)
                echo "Done!\n";
        }
    }

    public static function getNotesForUser($id, &$config, $save = true)
    {
        $userPage = self::get('https://intra.etna-alternance.net/report/index/summary/id/'.$id, $config);
        /* for offline dev */
        /* $userPage = file_get_contents('report.example'); */
        /* Treating the HTML page */
        $userPage = html_entity_decode($userPage);
        $userPage = utf8_encode($userPage);
        /* $array = explode('<th class="marks_uv" colspan="5">', $userPage); */
        $array = preg_split('#th class="marks_uv" colspan="[5-6]">#', $userPage);
        /* We throw away first elem, cause it's useless one */
        array_shift($array);
        if (sizeof($array) == 0)
        {
            echo "Error while preg_splitting the report page\n";
            exit(-1);
        }
        $notes = array();
        while ($uv = array_shift($array))
        {
            preg_match('#([^<]+)#', $uv, $match);
            $notes[trim($match[1])] = self::getNotesForUv($uv);
        }
        if ($save)
            file_put_contents('notes/'.$id, json_encode($notes));
        return $notes;
    }

    public static function getNotesForUv($uv)
    {
        $notes = array();
        preg_match_all('#<tr[^>]*>(.*?)</tr#s', $uv, $matches);
        $tmp = $matches[1];
        $size = sizeof($tmp);
        for ($i = 0; $i < $size; $i++)
        {
            if (!preg_match_all('#<td[^>]*>(.*?)</td#s', $tmp[$i], $noteRow))
                continue;
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

    public static function getUsersListForPromos(&$config)
    {
        $result = array();
        foreach ($config['promo'] as $promo)
            $result = array_merge(self::getUsers($promo, $config), $result);
        return $result;
    }

    public static function getUsers($url, &$config)
    {
        $matches = array();
        $etna2017 = self::get($url, $config);
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

    public static function getSpecificNotesForUsers($users, $array)
    {
        $msg = '';

        /* array is the differences we need to fetch data for */
        foreach ($array as $value)
        {
            $count = 0;
            $notes = array();
            $msg .= $value['msg'];
            /* if we have a note to fetch for this module */
            if (is_numeric($value['note']))
            {
                foreach ($users as $user => $id)
                {
                    /* Should never happen */
                    if (!file_exists('notes/'.$id))
                    {
                        $msg .= sprintf("%-8s => `Fichier manquant`\n", $user);
                        continue;
                    }
                    $json = json_decode(file_get_contents('notes/'.$id), true);
                    if (isset($json[$value['UV']]))
                    {
                        foreach ($json[$value['UV']] as $uv)
                        {
                            if ($uv['intitule'] === $value['intitule'])
                            {
                                $note = $uv['note'];
                                if ($note != 'NYD' && $note >= 0)
                                {
                                    $count++;
                                    $notes[$user] = $note;
                                }
                            }
                        }
                    }
                    else
                        $msg .= sprintf("%-8s => `No note`\n", $user);
                }
                if (sizeof($notes))
                {
                    arsort($notes);
                    $total = 0;
                    $size = sizeof($notes);
                    foreach ($notes as $user => $note)
                    {
                        $total += $note;
                        $msg .= sprintf("%-8s => `%d`\n", $user, $note);
                    }
                    $average = number_format($total / $count, 2);
                    $msg .= "Average  => `$average`\n";
                }
            }
        }
        return $msg;
    }

    // custom code from : https://gist.github.com/alexstone/9319715
    // (string) $message - message to be passed to Slack
    // (string) $room - room in which to write the message, too
    // (string) $icon - You can set up custom emoji icons to use with each message
    public static function slack($message, &$config, $room = "etna-notes")
    {
        /* This is to be sure we avoid transmitting twice the same message */
        if (empty($message) || !strlen($message)) return;
        if ($config['lastMessage'] === $message) return;
        $data = "payload=" . json_encode(array(
                                             "channel"       =>  "#{$room}",
                                             "text"          =>  $message,
                                             ));

        // You can get your webhook endpoint from your Slack settings
        $ch = curl_init($config['slackHook']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);

        if ($result != 'ok')
            throw new Exception("Failed to sent message to slack `$result`");

        /* message should never be the same, in case of 'error' we don't send last message */
        $config['lastMessage'] = $message;

        curl_close($ch);

        return $result;
    }
}
