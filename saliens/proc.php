<?php
    set_time_limit(0);
/*  Set default timezone.
    - More information found at http://php.net/manual/en/timezones.php */
    date_default_timezone_set("Europe/Helsinki");
    
    $SalienCheat = new class {
//      Script config.
        public $Script = [
//      -   Where should SalienCheat be installed?
            'install' => 'src/SalienCheat',
//      -   What should the instances run?
            'run' => 'SalienCheat-master/cheat.php',
//      -   Where do we download SalienCheat?
            'url' => 'https://github.com/SteamDatabase/SalienCheat/archive/master.zip',
//      -   What should run the process?
            'daemon' => 'start /B php'
        ];
//      Instance stack.
        public $Instances = ['0' => 0];
//      SalienCheat Initializer.
        public function initialize () {
//          Get tokens for instances.
            while (count($this -> Instances) <= 1) {
//              Parse the token file.
                if (file_exists("token.txt")) {
                    $tokens = fopen("token.txt", "r");
                    while (($token = fgets($tokens, 2096)) !== false) {
//                      Initialize instances for each token.
                        $this -> createInstance($token);
                    }
                    fclose($tokens);
//              Initialize first time run.
                } else {
                    $this -> log($this -> StringTemplate -> NoToken);
//                  Ask the user for their main token.
                    $this -> log("$ ");
                    $token = stream_get_line(STDIN, 1024, PHP_EOL);
//                  Store the main token.
                    $tokens = fopen("token.txt", "x+");
                    fwrite($tokens, $token);
                    fclose($tokens);
//                  Initialize an instance with given token.
                    $this -> createInstance($token);
                }
            }
//          Update SalienCheat, if necessary.
            $this -> update();
//          Start up instances.
            foreach ($this -> Instances as $token => &$instance)
            if ($token != "0")
            if ($instance === 0) {
                $this -> startInstance($token, $instance);
            }
//          Instance logic.
            $this -> log($this -> StringTemplate -> Initialized, ['status' => 'OK!']);
            while (true) {
//              Check for updates.
                switch ($this -> update()) {
                    case -1:
                    case -2:
                        $this -> log($this -> StringTemplate -> UpdateFailed, [
                            'retry' => "30 seconds"
                        ]);
                        sleep(30);
                        break;
                }
//              Log instance data.
                foreach ($this -> Instances as $token => &$instance) {
                    if ($token != "0")
                    if ($instance !== 0) {
                        $read = trim(fread($instance, 2096));
                        if ($read && strlen($read) > 0) {
                            $this -> log($this -> StringTemplate -> InstanceLog, [
                                'token' => substr($token,0,8),
                                'message' => $read
                            ]);
                        }
                    }
                }
                sleep(1);
            }
        }
//      Instance Object Creator.
        public function createInstance ($token) {
            if (!isset($this -> Instances[$token])) {
                $this -> Instances[$token] = 0;
                $this -> log($this -> StringTemplate -> NewInstance, [
                    'token' => substr($token,0,8)
                ]);
            } else {
                $this -> log($this -> StringTemplate -> InstanceExists, [
                    'token' => substr($token,0,8)
                ]);
            }
        }
//      Instance starter.
        public function startInstance ($token, &$instance) {
            $instance = popen("{$this -> Script -> daemon} \"{$this -> Script -> install}/{$this -> Script -> run}\" $token", "r");
            if ($instance) {
                $this -> log($this -> StringTemplate -> StartInstance, [
                    'token' => substr($token,0,8)
                ]);
            } else {
//              Failed to start instance. Try collect error.
                $error = trim(fread($instance, 4096));
                if ($error) {
                    $this -> log($this -> StringTemplate -> InstanceFailed, [
                        'token' => substr($token,0,8),
                        'error' => $error
                    ]);
                } else {
                    $this -> log($this -> StringTemplate -> InstanceFailed, [
                        'token' => substr($token,0,8),
                        'error' => 'No clue what went wrong.'
                    ]);
                }
                pclose($instance);
                $instance = 0;
            }
        }
//      SalienCheat updater.
        public function update () {
//          Check if update is necessary.
            if (file_exists("download"))
            if (time() - filemtime("download") < 3600 * 2) {
                return false;
            }
//          Close down any running instances.
            $this -> log($this -> StringTemplate -> UpdateStart);
            foreach ($this -> Instances as $token => &$instance)
            if ($token != "0")
            if ($instance !== 0) {
                pclose($instance);
                $instance = 0;
                $this -> log($this -> StringTemplate -> StopInstance, [
                    'token' => substr($token,0,8)
                ]);
            } else {
                $this -> log($this -> StringTemplate -> InstanceDown, [
                    'token' => substr($token,0,8)
                ]);
            }
//          Download the latest build.
            $this -> log($this -> StringTemplate -> UpdateDownload);
            $curl = curl_init($this -> Script -> url);
            $download = fopen("download", "w+");
//     [[!! PLEASE NOTE -- SSL Peer Verification is disabled for the lack of a proper way to implement SSL at this time. !!]]
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);
            curl_setopt($curl, CURLOPT_FILE, $download);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($curl);
            if (curl_error($curl)) {
                return -1;
            }
            curl_close($curl);
            fclose($download);
//          Unzip the latest build.
            $this -> log($this -> StringTemplate -> UpdateUnzip);
            $build = new ZipArchive();
            if ($build -> open("download") !== true) {
                return -2;
            }
            $build -> extractTo($this -> Script -> install);
            $build -> close();
//          Finalize.
            $this -> log($this -> StringTemplate -> UpdateFinish);
            return true;
        }
//      Terminal logging.
        public function log ($message, $arguments = []) {
//          Compose template.
            $template = [[],[]];
            foreach ((object) $arguments as $key => $value) {
                $template[0][] = "{{$key}}";
                $template[1][] = $value;
            }
//          Replace matches.
            $output = "[". date("H:i:s") ."] ". str_replace($template[0], $template[1], $message, $count);
//          Output.
            echo $output;
        }
//      SalienCheat Process constructor.
        public function __construct () {
//          Convert associative arrays to objects.
            foreach ($this as &$key)
            if (is_array($key))
            if (array_keys($key) !== range(0, count($key) - 1)) {
                $key = (object) $key;
            }
            $this -> log($this -> StringTemplate -> Construct);
//          Initialize the process.
            $this -> log($this -> StringTemplate -> Initialize);
            $this -> initialize();
        }
//      String templates.
        public $StringTemplate = [
            "NoToken" => "You don't seem to have any tokens. Let's get you set up with some!\r\nVisit ( https://steamcommunity.com/saliengame/gettoken ) and copy your token from there, to here.". PHP_EOL,
            "UpdateStart" => "Shutting down all instances for download of the latest build.". PHP_EOL,
            "UpdateFailed" => "Couldn't perform update. Trying again in {retry}.". PHP_EOL,
            "UpdateDownload" => "Downloading the latest build.". PHP_EOL,
            "UpdateUnzip" => "Unpacking the latest build.". PHP_EOL,
            "UpdateFinish" => "Update complete; Resuming.". PHP_EOL,
            "InstanceExists" => "[{token}] Instance already exists, skipping.". PHP_EOL,
            "NewInstance" => "[{token}] Instance initialized.". PHP_EOL,
            "StartInstance" => "[{token}] Starting up.". PHP_EOL,
            "StopInstance" => "[{token}] Shutting down.". PHP_EOL,
            "InstanceLog" => "[{token}] {message}". PHP_EOL,
            "InstanceDown" => "[{token}] Instance is down.". PHP_EOL,
            "InstanceFailed" => "[{token}] ERROR: {error}". PHP_EOL,
            "Initialize" => "Initializing SalienCheat.". PHP_EOL,
            "Initialized" => "SalienCheat has been initialized (Status: {status}).". PHP_EOL,
            "Construct" => "SalienCheat construction ready.". PHP_EOL
        ];
    }
?>
