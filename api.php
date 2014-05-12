<?php
    require_once("../config.php");
    require_once("../socket_open.php");

    // Possible actions
    // 1/ Startup
    
    $baseDataPath = "/var/www/data/";
    $defaultSettingsPath = "/var/www/defaultSettings.json";
    $userSettingsPath = "/var/www/userSettings.json";
    $result = null;

    $action = $_GET["action"];

    switch ($action)
    {
        case "startup":
            $result = getStartupConfig();
            break;
        case "beer_data":
            $result = getBeerData();
            break;
    }

    header("Content-Type: application/json");
    echo json_encode($result);


    function getStartupConfig()
    {
        $config = getBrewPiConfig();
        $scriptStatus = getScriptStatus();
        $beers = getBeers();
        $lcd = getLcd();

        return array("config" => $config, "script" => $scriptStatus, "beers" => $beers, "lcd" => $lcd);
    }

    function getBeerData()
    {
        global $baseDataPath;

        $beer = $_GET["beer"];
        $dates = strlen($_GET["dates"] > 0)
                    ? explode(";", $_GET["dates"])
                    : null;
        sort($dates);

        $beerData = null;

        if ($dates == null)
        {
            foreach(glob($baseDataPath.$beer."/*.json") as $file)
            {
                appendBeerData($file, $beerData);
            }
        }
        else
        {
            foreach($dates as $date)
            {
                foreach(glob($baseDataPath.$beer."/*".$date."*.json") as $file)
                {
                    appendBeerData($file, $beerData);
                }
            }
        }

        return $beerData;
    }

    function appendBeerData($file, &$beerData)
    {
        $fileData = file_get_contents($file);
        if ($fileData !== false)
        {
            $json = json_decode($fileData, true);
            
            if ($beerData == null)
            {
                $beerData = $json;
            }
            else
            {
                $beerData["rows"] = array_merge($beerData["rows"], $json["rows"]);
            }
        }
    }

    function getBeers()
    {
        global $baseDataPath;

        $beers = [];
        foreach (glob($baseDataPath."*", GLOB_ONLYDIR) as $dir)
        {
            $dir = basename($dir);
            if ($dir !== "profiles")
            {
                $beer["name"] = $dir;
                $dates = array();

                $handle = opendir($baseDataPath.$dir);
                while (false !== ($file = readdir($handle)))
                {
                    $matches = array();
                    if (preg_match("/(\d{4}-\d{2}-\d{2})/", $file, $matches)
                        && !in_array($matches[1], $dates))
                    {
                        $dates[] = $matches[1];
                    }
                }
                closedir($handle);

                sort($dates);
                $beer["dates"] = $dates;

                $beers[] = $beer;
             }
       }

        sort($beers);

        return $beers;
    }

    function getBrewPiConfig()
    {
        global $defaultSettingsPath;
        global $userSettingsPath;

        $defaultSettings = file_get_contents($defaultSettingsPath);
        if ($defaultSettings == false)
        {
            die("Cannot open default settings file: defaultSettings.json");
        }

        $settingsArray = json_decode($defaultSettings, true);
        if (is_null($settingsArray))
        {
            die("Cannot decode defaultSettings.json");
        }

        // Overwrite default settings with user settings
        if (file_exists($userSettingsPath))
        {
            $userSettings = file_get_contents($userSettingsPath);
            if ($userSettings == false)
            {
                die("Error opening settings file userSettings.json");
            }

            $userSettingsArray = json_decode($userSettings, true);
            if (is_null($settingsArray))
            {
                die("Cannot decode userSettings.json");
            }

            foreach ($userSettingsArray as $key => $value)
            {
                $settingsArray[$key] = $userSettingsArray[$key];
            }
        }

        return $settingsArray;
    }

    function getLcd()
    {
        $socket = open_socket();
        
        socket_write($socket, "lcd", 4096);
        $lcdText = socket_read($socket, 4096);

        socket_close($socket);

        return $lcdText !== false 
                ? json_decode($lcdText)
                : $lcdText;
    }

    function getScriptStatus()
    {
        $socket = open_socket();
    
        socket_write($socket, "ack", 4096);
        $answer = socket_read($socket, 4096);

        socket_close($socket);

        return $answer == "ack";
    }
?>
