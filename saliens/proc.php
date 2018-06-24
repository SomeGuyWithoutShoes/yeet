<?php
    set_time_limit(0);
    
    $process = 0;
    $stdout = "";
    function msg ($msg) {
        if (strlen($msg) > 0) {
            printf("[" . time() . "] " . $msg . PHP_EOL);
        }
    }
    function update () {
        global $process;
        
//      Delete old update file.
        msg("Initializing the update.");
        
//      Close the existing process if found.
        if ($process !== 0) {
            msg("> Shutting down old process.");
            pclose($process);
            $process = 0;
        }
        
//      Clean old install.
        if (file_exists("src\SalienCheat")) {
            msg("> Cleaning old version.");
            system("del /Q src\SalienCheat");
        }
        
//      Download new master.
        msg("> Downloading new version.");
        $channel = curl_init("https://github.com/SteamDatabase/SalienCheat/archive/master.zip");
        $file = fopen("download", "w+");
        curl_setopt($channel, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($channel, CURLOPT_TIMEOUT, 30);
        curl_setopt($channel, CURLOPT_FILE, $file);
        curl_setopt($channel, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($channel);
        if (curl_error($channel)) {
            msg("> > ERROR! " . curl_error($channel));
        }
        curl_close($channel);
        fclose($file);
        
//      Unpack the download.
        msg("> Unpacking archive.");
        $zip = new ZipArchive();
        if ($zip -> open("download") !== true) {
            msg("> > Corrupted/invalid download, retry.");
            return;
        }
        $zip -> extractTo("src\SalienCheat");
        $zip -> close();
        
//      Move token.
        msg("> Copying token.");
        if (file_exists("token.txt")) {
            copy("token.txt", 'src\SalienCheat\SalienCheat-master\token.txt');
        } else {
            msg("> > You don't seem to have a token file.");
            msg("> > Please visit ( https://steamcommunity.com/saliengame/gettoken ) and input your token here.");
            echo "$ ";
            $line = stream_get_line(STDIN, 1024, PHP_EOL);
            $file = fopen("token.txt", "x+");
            fwrite($file, $line);
            fclose($file);
            copy("token.txt", 'src\SalienCheat\SalienCheat-master\token.txt');
        }
        
        msg("> Done!");
    }
    
    while (true) {
//      Check for update (Older than 1 hour cache)
        if (file_exists("download")) {
            if (time() - filemtime("download") > 2 * 3600) {
                msg("Update time.");
                update();
            }
        } else {
            msg("This seems to be your first install. Downloading latest SalienCheat.");
            update();
        }
        if ($process == 0) {
            msg("Starting up the process ...");
            $process = popen('start /B php src\SalienCheat\SalienCheat-master\cheat.php', "r");
            msg("Process up.");
        }
        if ($process) {
            $read = fread($process, 2096);
            if ($read && strlen($read) > 0) {
                echo $read;
            }
        }
        sleep(1);
    }
?>
