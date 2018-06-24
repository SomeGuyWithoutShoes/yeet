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
            'daemon' => 'start /B php',
//      -   What should be used to remove the old build?
            'clean' => 'del /Q'
        ];
        
//      Instance stack.
        public $Instances = ['0' => [0]];
        
//      String templates.
        public $StringTemplate = [
            "NoToken" => "You don't seem to have any tokens. Let's get you set up with some!\r\nVisit ( https://steamcommunity.com/saliengame/gettoken ) and copy your token from there, to here.". PHP_EOL,
            "UpdateStart" => "Shutting down all instances for download of the latest build.". PHP_EOL,
            "UpdateCleanup" => "Cleaning up the old build.". PHP_EOL,
            "UpdateFailed" => "Couldn't perform update. Trying again in {retry}.". PHP_EOL,
            "UpdateDownload" => "Downloading the latest build.". PHP_EOL,
            "UpdateUnzip" => "Unpacking the latest build.". PHP_EOL,
            "UpdateFinish" => "Update complete; Resuming.". PHP_EOL,
            "InstanceExists" => "Instance already exists, skipping.". PHP_EOL,
            "NewInstance" => "Instance initialized.". PHP_EOL,
            "StartInstance" => "Starting up.". PHP_EOL,
            "StopInstance" => "Shut down.". PHP_EOL,
            "StoppingInstance" => "Shutting down.". PHP_EOL,
            "InstanceLog" => "{message}". PHP_EOL,
            "InstanceDown" => "Instance is down.". PHP_EOL,
            "InstanceFailed" => "ERROR: {error}". PHP_EOL,
            "Initialize" => "Initializing SalienCheat.". PHP_EOL,
            "Initialized" => "SalienCheat has been initialized (Status: {status}).". PHP_EOL,
            "Construct" => "SalienCheat construction ready.". PHP_EOL
        ];
        
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
                    $this -> log(0, $this -> StringTemplate -> NoToken);
                    
//                  Ask the user for their main token.
                    $this -> log(0, "$ ");
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
            $this -> forEach(function($token, &$instance) {
                if ($instance === 0)
                    $this -> startInstance($token, $instance);
            });
            
//          Process logic.
            $this -> log(0, $this -> StringTemplate -> Initialized, ['status' => 'OK!']);
            while (true) {
                
//              Check for updates.
                $this -> update();
                
//              Instance data.
                $this -> forEach(function($token, &$instance) {
                    if ($instance !== 0) {
                        
//                      Trim the read.
                        $read = trim(fread($instance, 2096)); flush();
                        if ($read && strlen($read) > 0) {
                            
//                          Log the data.
                            $this -> log($token, $this -> StringTemplate -> InstanceLog, [
                                'token' => substr($token,0,8),
                                'message' => $read
                            ]);
                        }
                    }
                });
            }
        }
        
//      Terminal logging.
        public $lastLogGroup = 0;
        public $logGroups = [
            '0' => 'Master',
            '-1' => 'Debug'
        ];
        public function log ($token, $message, $arguments = []) {
            
//          Compose template.
            $template = [[],[]];
            foreach ((object) $arguments as $key => $value) {
                $template[0][] = "{{$key}}";
                $template[1][] = $value;
            }
            
//          Log grouping.
            if ($this -> lastLogGroup == $token) {
                $output = "";
            } else {
                $output = "+ ";
                if (isset($this -> logGroups -> $token)) {
                    $output .= $this -> logGroups -> $token;
                } else {
                    $output .= "Instance ". substr($token, 0, 8);
                }
                $output .= "\r\n";
                $this -> lastLogGroup = $token;
            }
//          Replace matches.
            $output .= str_replace($template[0], $template[1], $message, $count);
            
//          Output.
            echo $output;
        }
        
//      Downloader.
        public function download ($url, $saveAs = "download") {
            $this -> log(0, $this -> StringTemplate -> UpdateDownload);
            
            $error = "";
            $curl = curl_init($url);
            $download = fopen($saveAs, "w+");
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);
            curl_setopt($curl, CURLOPT_FILE, $download);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            if (!curl_exec($curl) && curl_error($curl))
                $error = curl_error($curl);
            curl_close($curl);
            fclose($download);
            return $error? $error: true;
        }
        
//      Unzipper.
        public function unzip($file, $target) {
            $this -> log(0, $this -> StringTemplate -> UpdateUnzip);
            
            $error = "";
            $build = new ZipArchive();
            if ($build -> open($file) !== true) {
                $error = $build -> getStatusString();
            }
            $build -> extractTo($target);
            $build -> close();
            return $error? $error: true;
        }
        
//      Cleaner.
        public function clean($target) {
            $this -> log(0, $this -> StringTemplate -> UpdateCleanup);
            
            system("{$this -> Script -> clean} \"{$this -> Script -> install}\"");
        }
        
//      Instance Object Creator.
        public function createInstance ($token) {
            if (!isset($this -> Instances[$token])) {
                $this -> Instances[$token] = 0;
                $this -> log($token, $this -> StringTemplate -> NewInstance);
            } else {
                $this -> log($token, $this -> StringTemplate -> InstanceExists);
            }
        }
        
//      Instance starter.
        public function startInstance ($token, &$instance) {
            $instance = popen("{$this -> Script -> daemon} \"{$this -> Script -> install}/{$this -> Script -> run}\" $token", "r");
            if ($instance) {
                
//              Instance started.
                $this -> log($token, $this -> StringTemplate -> StartInstance);
            } else {
                
//              Failed to start instance. Try collect error.
                $error = trim(fread($instance, 4096));
                if ($error) {
                    $this -> log($token, $this -> StringTemplate -> InstanceFailed, [
                        'error' => $error
                    ]);
                } else {
                    $this -> log($token, $this -> StringTemplate -> InstanceFailed, [
                        'error' => 'No clue what went wrong.'
                    ]);
                }
                
//              Close the instance.
                pclose($instance);
                $instance = 0;
            }
        }
        
//      Instance stopper.
        public function stopInstance ($token, &$instance) {
            if ($instance !== 0) {
                $this -> log($token, $this -> StringTemplate -> StopInstance);
                
                pclose($instance);
                $instance = 0;
            }
        }
        
//      Instance iterator.
        public function forEach ($handle) {
            foreach ($this -> Instances as $token => &$instance)
            if ($token != "0")
                $handle($token, $instance);
        }
        
//      SalienCheat updater.
        public function update () {
            if (file_exists("download")) {
                if (time() - filemtime("download") > 3600 * 2) {
                    
//                  Close down any running instances.
                    $this -> log(0, $this -> StringTemplate -> UpdateStart);
                    $this -> forEach(function($token, &$instance) {
                        if ($instance !== 0)
                            $this -> stopInstance($token, $instance);
                    });
                    
//                  Clean up the old build.
                    $this -> clean($this -> Script -> install);
                    
//                  Download latest build.
                    $this -> download($this -> Script -> url);
                    
//                  Unpack latest build.
                    $this -> unzip("download", $this -> Script -> install);
                    
//                  Finish and restart processes.
                    $this -> log(0, $this -> StringTemplate -> UpdateFinish);
                    $this -> forEach(function($token, &$instance) {
                        if ($instance === 0)
                            $this -> startInstance($token, $instance);
                    });
                }
            } else {
                
//              Close down any running instances.
                $this -> forEach(function($token, &$instance) {
                    if ($instance !== 0)
                        $this -> stopInstance($token, $instance);
                });
                
//              Download latest build.
                $this -> download($this -> Script -> url);
                
//              Unpack latest build.
                $this -> unzip("download", $this -> Script -> install);
                
//              Finish and restart processes.
                $this -> log(0, $this -> StringTemplate -> UpdateFinish);
                $this -> forEach(function($token, &$instance) {
                    if ($instance === 0)
                        $this -> startInstance($token, $instance);
                });
            }
        }
        
//      SalienCheat Process constructor.
        public function __construct () {
            
//          Convert associative arrays to objects.
            foreach ($this as &$key)
            if (is_array($key))
            if (array_keys($key) !== range(0, count($key) - 1))
                $key = (object) $key;
            
//          Construction done.
            $this -> log(0, $this -> StringTemplate -> Construct);
            
//          Initialize the process.
            $this -> log(0, $this -> StringTemplate -> Initialize);
            $this -> initialize();
        }
    }
?>
