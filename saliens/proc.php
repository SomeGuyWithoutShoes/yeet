#!/usr/bin/env php
<?php
    set_time_limit(0);
    
/*  Set default timezone.
    - More information found at http://php.net/manual/en/timezones.php */
    date_default_timezone_set("Europe/Helsinki");
    
    $SalienCheat = new class {
        
//      Script config.
        public $Script = [
            
/*      -   Where should SalienCheat be installed?
            Default:    [ src/SalienCheat ] */
            'install' => 'src/SalienCheat',
            
/*      -   What should the instances run?
            Default:[ SalienCheat-master/cheat.php ]*/
            'run' => 'SalienCheat-master/cheat.php',
            
/*      -   Where do we download SalienCheat?
            Default:[ https://github.com/SteamDatabase/SalienCheat/archive/master.zip ] */
            'url' => 'https://github.com/SteamDatabase/SalienCheat/archive/master.zip',
            
/*      -   What should run the process?
            Default:   [ start /B php\php.exe ] for Windows, [ php ] for Unix */
            'daemon' => 'start /B php\php.exe',
            
/*      -   What should be used to remove the old build?
            Default:  [ del /Q ] for Windows, [ rm -r ] for Unix */
            'clean' => 'del /Q',
            
/*      -   Should we check the download file's last modified date? See: [ localFrequency ]
            Default:           [ true ] */
            'updateFromLocal' => true,
            
/*      -   How often should the download file be checked?
            Default:          [ H * MM * SS ] 2 hours */
            'localFrequency' => 2 * 60 * 60,
            
/*      -   Should we update if STDOUT reads [ update available ] ? See: [ updateNotification ]
            Default:            [ true ] */
            'updateFromSTDOUT' => true,
            
/*      -   What should STDOUT be matched for ?
            Default:               [ Script has been updated on GitHub ] */
            'updateNotification' => "Script has been updated on GitHub",
            
/*      -   Should we wait for something in STDOUT before update ?
            Default:         [ true ] */
            'updateDelayed' => true,
            
/*      -   What should we wait for in STDOUT ?
            Default:        [ Your Score ] */
            'updateWaitFor' => "Your Score"
        ];
        
//      Instance stack.
        public $Instances = ['0' => [0]];
        
//      String templates.
        public $StringTemplate = [
            "NoToken" => "Hello! This seems to be your first run of proc.php.". PHP_EOL
            .   "Please visit [ https://steamcommunity.com/saliengame/gettoken ] to get your token.". PHP_EOL
            .   PHP_EOL ."If you'd prefer to have a name on your token, you can add one to it.". PHP_EOL
            .   "To do so, add an extra \":NAME\" after the token, without the quotes.". PHP_EOL
            .   "For example: [ IVN31FDV:My Instance Name ]". PHP_EOL
            .   PHP_EOL . PHP_EOL,
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
            "Construct" => "SalienCheat construction ready.". PHP_EOL,
            "WaitingUpdate" => "Waiting for round to end to update ...". PHP_EOL,
            "WaitComplete" => "We've done our time.". PHP_EOL
        ];
        
//      Update Pending flag.
        public $UpdatePending = false;
        
//      SalienCheat Initializer.
        public function initialize () {
            
//          Get tokens for instances.
            while (count($this -> Instances) <= 1) {
                
//              Parse the token file.
                if (file_exists("token.txt")) {
                    $tokens = fopen("token.txt", "r");
                    while (($token = fgets($tokens, 2096)) !== false) {
                        
//                      Check if the token has a name.
                        if (preg_match("/:/", $token)) {
                            
//                          Named token.
                            $token = explode(":", $token);
                            $this -> createInstance($token[0], $token[1]);
                        } else {
                            
//                          Unnamed token.
                            $this -> createInstance($token);
                        }
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
                    
//                  Check if the token has a name.
                    if (preg_match("/:/", $token)) {
                        
//                      Named token.
                        $token = explode(":", $token);
                        $this -> createInstance($token[0], $token[1]);
                    } else {
                        
//                      Unnamed token.
                        $this -> createInstance($token);
                    }
                }
            }
            
//          Initial update check.
            if ($this -> fileAge("download") > $this -> Script -> localFrequency)
                $this -> update();
            
//          Start up instances.
            $this -> forEach(function($token, &$instance) {
                if (!is_resource($instance -> process))
                    $this -> startInstance($token, $instance);
            });
            
//          Process logic.
            $this -> log(0, $this -> StringTemplate -> Initialized, ['status' => 'OK!']);
            while (true) {
                
//              Check for updates.
                if ($this -> Script -> updateFromLocal && $this -> fileAge("download") > $this -> Script -> localFrequency)
                    $this -> update();
                
//              Instance data.
                $this -> forEach(function($token, &$instance) {
                    if (is_resource($instance -> process)) {
                        
//                      Check if there's anything to read.
                        if ($this -> getSize($instance, 1)) {
//                          Trim the read. [ Read 4096 so we don't run into other issues ]
                            $read = trim(fgets($instance -> pipes[1], 4096));
                            if ($read) {
                                
//                              Log the data.
                                $this -> log($token, $this -> StringTemplate -> InstanceLog, [
                                    'message' => $read
                                ]);
                                
//                              Check if STDOUT has update notification.
                                if ($this -> Script -> updateFromSTDOUT)
                                if (preg_match("/{$this -> Script -> updateNotification}/", $read)) {
                                    if ($this -> Script -> updateDelayed) {
                                        
//                                      Flag for pending update.
                                        if (!($this -> UpdatePending)) {
                                            $this -> log(0, $this -> StringTemplate -> WaitingUpdate);
                                            $this -> UpdatePending = true;
                                        }
                                    } else {
                                        
//                                      Immediate update.
                                        $this -> update();
                                    }
                                }
                                
//                              Check if STDOUT has round end notification.
                                if ($this -> Script -> updateDelayed)
                                if (preg_match("/{$this -> Script -> updateWaitFor}/", $read)) {
                                    if ($this -> UpdatePending) {
                                        $this -> log(0, $this -> StringTemplate -> WaitComplete);
                                        $this -> update();
                                    }
                                }
                            }
                        }
                    }
                });
                
//              Add some usleep so we don't use 100% of CPU. [ Temporary fix, rewriting update ]
                usleep(256 * 1000);
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
                $logGroups = $this -> logGroups;
                $instances = $this -> Instances;
                if (@isset($logGroups -> $token)) {
                    $output .= $logGroups -> $token;
                } else {
                    $instance = $instances[$token];
                    if (@$instance -> name != "") {
                        $output .= $instance -> name;
                    } else {
                        $output .= "Instance ". substr($token, 0, 8);
                    }
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
            
            if (file_exists($this -> Script -> install))
                system("{$this -> Script -> clean} \"{$this -> Script -> install}\"");
        }
        
//      Instance Object Creator.
        public function createInstance ($token, $name = "") {
            if (!isset($this -> Instances[$token])) {
                $this -> Instances[$token] = (object) [
                    'process' => 0,
                    'pipes' => [],
                    'descriptor' => [],
                    'name' => $name
                ];
                $this -> log($token, $this -> StringTemplate -> NewInstance);
            } else {
                $this -> log($token, $this -> StringTemplate -> InstanceExists);
            }
        }
        
//      Instance starter.
        public function startInstance ($token, &$instance) {
            $instance -> descriptors = [
                
//              STDIN
                0 => array("pipe", "r"),
                
//              STDOUT
                1 => array("pipe", "w"),
                
//              STDERR
                2 => array("pipe", "a")
            ];
            
//          Start instance.
            $instance -> process = proc_open(
                "{$this -> Script -> daemon} \"{$this -> Script -> install}/{$this -> Script -> run}\" $token",
                $instance -> descriptors,
                $instance -> pipes
            );
            
//          Set non-blocking.
            stream_set_blocking($instance -> pipes[0], false);
            stream_set_blocking($instance -> pipes[1], false);
            stream_set_blocking($instance -> pipes[2], false);
            
//          Check status.
            if (is_resource($instance -> process)) {
                
//              Instance started.
                $this -> log($token, $this -> StringTemplate -> StartInstance);
            } else {
                
//              Check if there's anything to read.
                if ($this -> getSize($instance, 2)) {
//                  Trim the read.
                    $read = trim(fgets($instance -> pipes[2], 4096));
                    if ($error) {
                        $this -> log($token, $this -> StringTemplate -> InstanceFailed, [
                            'error' => $error
                        ]);
                    } else {
                        $this -> log($token, $this -> StringTemplate -> InstanceFailed, [
                            'error' => 'No clue what went wrong.'
                        ]);
                    }
                }
                
//              Close the instance.
                $this -> stopInstance($token, $instance);
            }
        }
        
//      Instance stopper.
        public function stopInstance ($token, &$instance) {
//          Check if instance is up.
            if (is_resource($instance -> process)) {
                $this -> log($token, $this -> StringTemplate -> StopInstance);
                
//              Close the instance.
                fclose($instance -> pipes[0]);
                fclose($instance -> pipes[1]);
                fclose($instance -> pipes[2]);
                $this -> kill($instance);
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
            
//          Check for existing build.
            if (file_exists("download")) {
                    
//              Close down any running instances.
                $this -> log(0, $this -> StringTemplate -> UpdateStart);
                $this -> forEach(function($token, &$instance) {
                    $this -> stopInstance($token, $instance);
                });
                
//              Clean up the old build.
                $this -> clean($this -> Script -> install);
                
//              Download latest build.
                $this -> download($this -> Script -> url);
                
//              Unpack latest build.
                $this -> unzip("download", $this -> Script -> install);
                
//              Finish and restart processes.
                $this -> log(0, $this -> StringTemplate -> UpdateFinish);
                $this -> forEach(function($token, &$instance) {
                    if (!is_resource($instance -> process))
                        $this -> startInstance($token, $instance);
                });
                $this -> UpdatePending = false;
                
//          No build file found.
            } else {
                
//              Close down any running instances.
                $this -> forEach(function($token, &$instance) {
                    $this -> stopInstance($token, $instance);
                });
                
//              Clean up the old build.
                $this -> clean($this -> Script -> install);
                
//              Download latest build.
                $this -> download($this -> Script -> url);
                
//              Unpack latest build.
                $this -> unzip("download", $this -> Script -> install);
                
//              Finish and restart processes.
                $this -> log(0, $this -> StringTemplate -> UpdateFinish);
                $this -> forEach(function($token, &$instance) {
                    if (!is_resource($instance -> process))
                        $this -> startInstance($token, $instance);
                });
            }
        }
        
//      STDOUT size getter.
        public function getSize($instance, $pipe) {
            
//          Use fstat on WINNT.
            if (PHP_OS == "WINNT") {
                return fstat($instance -> pipes[$pipe])['size'];
                
//          Return 1 on Unix; fstat seems to always return 0 for size.
            } else {
                return 1;
            }
        }
        
//      File age getter.
        public function fileAge($file) {
            return file_exists($file)
                ? time() - filemtime($file)
                : PHP_INT_MAX;
        }
        
//      Instance killer.
        public function kill(&$instance) {
            if (PHP_OS == "WINNT") {
                proc_close($instance -> process);
            } else {
/*              Do note this leaves "ghost processes" running for a while after termination.
                They'll eventually go away. But the only other option is long waits on the master process. */
                proc_terminate($instance -> process, 9);
            }
            
            $instance -> process = null;
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
