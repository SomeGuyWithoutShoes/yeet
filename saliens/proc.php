#!/usr/bin/env php
<?php
    set_time_limit(0);
    
/*  Set default timezone.
    - More information found at http://php.net/manual/en/timezones.php */
    date_default_timezone_set("Europe/Helsinki");
    
    $SalienCheat = new class {
        
//      Script config.
        public $Script = [
        
/*      -   What delimeter should the argument parser use for arrays ?
            Default:     [ | ] */
            'argDelim' => '|',
        
/*      -   Where should SalienCheat be installed ?
            Default:    [ src/SalienCheat ] */
            'install' => 'src/SalienCheat',
            
/*      -   What should the instances run ?
            Default:[ SalienCheat-master/cheat.php ]*/
            'run' => 'SalienCheat-master/cheat.php',
            
/*      -   Where do we download SalienCheat ?
            Default:[ https://github.com/SteamDatabase/SalienCheat/archive/master.zip ] */
            'url' => 'https://github.com/SteamDatabase/SalienCheat/archive/master.zip',
            
/*      -   What should run the process ?
            Default:   [ start /B php\php.exe ] for Windows, [ php ] for Unix */
            'daemon' => 'start /B php\php.exe',
            
/*      -   What should be used to remove the old build ?
            Default:  [ del /Q ] for Windows, [ rm -r ] for Unix */
            'clean' => 'del /Q',
            
/*      -   Logging logic; Should we group data [1], prepend [2], or not log at all [0] ?
            Default:    [ 2 ] */
            'logLogic' => 2,
            
/*      -   What log lines should we ignore, if any [ Uses regexp ] ?
            Default:         [ [] ] */
            'logIgnoreList' => [],
            
/*      -   How often should the instance logic tick [ in ms ] ?
            Default:                 [ 256 ] */
            'instanceLogicTickrate' => 256,
            
/*      -   What env flag is the COLOR toggle identified with ?
            Default:         [ DISABLE_COLORS ] */
            'colorEnvFlag' => 'DISABLE_COLORS',
            
/*
        Disclaimer on Auto-Updates:
            
            Before you toggle on [ updateFromLocal ], or [ updateFromSTDOUT ],
            Please keep in mind the dangers of automatic updates.
            
            While [ proc.php ] had them toggled on by default, because that was convenient for me,
            it doesn't mean I should impose the risk that other users may not understand fully.
            
            So before you toggle them on, please, for your own sake, consider the risks.
            
            There's no saying when the source you're receiving updates from [ url ] could be
            compromised. Be it either that the developer for some reason goes rogue,
            or their account gets compromised for one reason or another,
            the risk is always there, and it can be of any cause.
            
            [ proc.php ] itself doesn't update automatically; it merely updates
            the script that it has been configured to run.
            
            By default that is SalienCheat [ github.com/SteamDatabase/SalienCheat ].
            
            Auto-updates can be toggled back on by editing [ proc.php ],
            or by passing [ --updateFromLocal=true --updateFromSTDOUT=true ].
            
            If [ updateFromLocal ] has been disabled, the [ proc.php ] will still check the
            existing download. This is required for the easy-installation [ proc.php ] uses.
            Users may use this feature to update to the latest version of [ url ],
            rather than rely on automatic updates while they're not present.
            By deleting the existing update file, [ proc.php ] will download the latest build.
*/
            
/*      -   Should we check the download file's last modified date? See: [ localFrequency ]
            Default:           [ false ] */
            'updateFromLocal' => false,
            
/*      -   How often should the download file be checked ?
            Default:          [ H * MM * SS ] 2 hours */
            'localFrequency' => 2 * 60 * 60,
            
/*      -   Should we update if STDOUT reads [ update available ] ? See: [ updateNotification ]
            Default:            [ false ] */
            'updateFromSTDOUT' => false,
            
/*      -   What should STDOUT be matched for ?
            Default:               [ Script has been updated on GitHub ] */
            'updateNotification' => "Script has been updated on GitHub",
            
/*      -   Should we wait for something in STDOUT before update ?
            Default:         [ true ] */
            'updateDelayed' => true,
            
/*      -   What should we wait for in STDOUT ?
            Default:          [ Your Score ] */
            'updateWaitFor' => "Your Score"
        ];
        
//      TextStyle presets.
        public $TextStyle = [
            "reset" => "\033[0m",
            "grey_text" => "\033[90m",
            "green_text" => "\033[32m"
        ];
        
//      String templates.
        public $StringTemplate = [
            "NoToken" => "Hello! This seems to be your first run of proc.php.". PHP_EOL
            .   "Please visit [ https://steamcommunity.com/saliengame/gettoken ] to get your token.". PHP_EOL
            .   PHP_EOL ."If you'd prefer to have a name on your token, you can add one to it.". PHP_EOL
            .   "To do so, add an extra \":NAME\" after the token, without the quotes.". PHP_EOL
            .   "For example: [ IVN31FDV:My Instance Name ]". PHP_EOL
            .   PHP_EOL . PHP_EOL,
            "UpdateStart" => "Initializing a new update.". PHP_EOL,
            "UpdateCleanup" => "Cleaning up the old build.". PHP_EOL,
            "UpdateFailed" => "Couldn't perform update. Trying again in {retry}.". PHP_EOL,
            "UpdateDownload" => "Downloading the latest build.". PHP_EOL,
            "UpdateUnzip" => "Unpacking the latest build.". PHP_EOL,
            "UpdateFinish" => "Update complete.". PHP_EOL,
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
            "WaitingUpdate" => "Waiting to update.". PHP_EOL,
            "WaitingRestart" => "Waiting for round to end to restart.". PHP_EOL,
            "WaitComplete" => "Restarting into new update.". PHP_EOL
        ];
        
//      Instance stack.
        public $Instances = ['0' => [0]];
        
//      Update Pending flag.
        public $UpdatePending = false;
        
//      Restarting Instances.
        public $RestartingInstances = 0;
        
//      SalienCheat Initializer.
        public function initialize () {
            
//          Color to Env.
            if ($this -> getStyleSupport())
                putenv("{$this -> Script -> colorEnvFlag}=0");
            
//          Parse the token file.
            if (file_exists("token.txt")) {
                $tokens = fopen("token.txt", "r");
                while (($token = fgets($tokens, 2096)) !== false) {
                    
//                  Check if the token has a name.
                    if (preg_match("/:/", $token)) {
                        
//                      Named token.
                        $token = explode(":", $token);
                        $this -> createInstance(trim($token[0]), trim($token[1]));
                    } else {
                        
//                      Unnamed token.
                        $this -> createInstance(trim($token));
                    }
                }
                fclose($tokens);
                
//          Initialize first time run.
            } else {
                $this -> log(0, $this -> StringTemplate -> NoToken);
                
//              Ask the user for their main token.
                echo "$ ";
                $token = stream_get_line(STDIN, 1024, PHP_EOL);
                
//              Store the main token.
                $tokens = fopen("token.txt", "x+");
                fwrite($tokens, $token);
                fclose($tokens);
                
//              Check if the token has a name.
                if (preg_match("/:/", $token)) {
                    
//                  Named token.
                    $token = explode(":", $token);
                    $this -> createInstance($token[0], $token[1]);
                } else {
                    
//                  Unnamed token.
                    $this -> createInstance($token);
                }
            }
            
//          Initial install check.
            if (!file_exists("download"))
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
                if ($this -> Script -> updateFromLocal && ($this -> fileAge("download") > $this -> Script -> localFrequency)) {
                    var_dump($this -> Script -> updateFromLocal);
                    echo PHP_EOL;
                    $this -> log(-1, "LOCAL UPDATE PROMPTED");
                    $this -> update();
                }
                
//              Instance data.
                $this -> forEach(function($token, &$instance) {
                    
//                  Check if running.
                    if (is_resource($instance -> process)) {
                        
//                      Check if there's anything to read.
                        if ($this -> getSize($instance, 1)) {
                            
//                          Trim the read. [ 4096 to avoid vertical reading ]
                            $read = trim(fgets($instance -> pipes[1], 4096));
                            if ($read) {
                                
//                              Log the data.
                                $this -> log($token, $this -> StringTemplate -> InstanceLog, [
                                    'message' => $read
                                ]);
                                
//                              Check STDOUT.
                                if ($this -> Script -> updateFromSTDOUT) {
                                    
//                                  Check for update notifications.
                                    if (preg_match("/{$this -> Script -> updateNotification}/", $read)) {
                                        if ($this -> Script -> updateDelayed) {
                                            
//                                          Flag for pending update.
                                            if (!($this -> UpdatePending)) {
                                                $this -> log(0, $this -> StringTemplate -> WaitingUpdate);
                                                $this -> UpdatePending = true;
                                            }
                                        } else {
                                            
//                                          Update without delay.
                                            $this -> update();
                                        }
                                    
//                                  Check if the round is complete.
                                    } elseif (preg_match("/{$this -> Script -> updateWaitFor}/", $read)) {
                                        
//                                      Start updating.
                                        if ($this -> Script -> updateDelayed)
                                        if ($this -> UpdatePending)
                                            $this -> update();
                                        
//                                      Tell instance to restart.
                                        if ($instance -> pendingRestart) {
                                            $this -> log($token, $this -> StringTemplate -> WaitComplete);
                                            $this -> stopInstance($token, $instance);
                                        }
                                    
//                                  Check instances for restart in NoDelay situation.
                                    } elseif (!($this -> Script -> updateDelayed) && $instance -> pendingRestart) {
                                        
//                                      Tell instance to restart.
                                        $this -> log($token, $this -> StringTemplate -> WaitComplete);
                                        $this -> stopInstance($token, $instance);
                                    }
                                }
                            }
                        }
                        
//                  Instance is down.
                    } else {
                        
//                      Check if update is finished.
                        if (!($this -> UpdatePending)) {
                            
//                          Start instance up.
                            $this -> startInstance($token, $instance);
                        }
                    }
                });
                
//              Wait for a while before looping again.
                usleep(256 * 1000);
            }
        }
        
//      Logging logic.
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
            
//          Style support.
            $cs = $this -> getStyleSupport();
            
//          Check log logic.
            if ($this -> Script -> logLogic !== 0) {
                
//              Initialize data.
                $data = $cs? $this -> TextStyle -> reset: "";
                
//              Group data.
                if ($this -> Script -> logLogic === 1) {
                    if ($this -> lastLogGroup != $token) {
                        $data .= ($cs? $this -> TextStyle -> grey_text: "")
                            . "+ "
                            . ($cs? $this -> TextStyle -> green_text: "")
                            . $this -> getIdentifier($token). PHP_EOL;
                        $this -> lastLogGroup = $token;
                    }
                    $data .= ($cs? $this -> TextStyle -> reset: "")
                    .   $message;
                
//              Prepend data.
                } if ($this -> Script -> logLogic === 2) {
                    $lines = explode(PHP_EOL, $message);
                    foreach ($lines as $line)
                    if (trim($line)) {
                        $data .= ($cs? $this -> TextStyle -> grey_text: "")
                            . "[". date("H:i:s");
                        $data .= "] "
                            . ($cs? $this -> TextStyle -> green_text: "")
                            . $this -> getIdentifier($token);
                        $data .= ($cs? $this -> TextStyle -> reset: ""). ": "
                            . $line;
                        
//                      Check for ending EOL.
                        if (!preg_match("/{PHP_EOL}$/", $data))
                            $data .= PHP_EOL;
                    }
                }
                
//              Replace matches.
                $data = str_replace($template[0], $template[1], $data, $count);
                
//              Check data against ignore list.
                if (count($this -> Script -> logIgnoreList) !== 0) {
                    foreach ($this -> Script -> logIgnoreList as $ignore)
                    if (preg_match($ignore, $data))
                        return;
                }
                
//              Output.
                echo $data . ($cs? $this -> TextStyle -> reset: "");
            }
        }
        
//      Log identifier getter.
        public function getIdentifier($token) {
            $logGroups = $this -> logGroups;
            $instances = $this -> Instances;
            if (@isset($logGroups -> $token)) {
                return $logGroups -> $token;
            } else {
                $instance = $instances[$token];
                if (@$instance -> name != "") {
                    return $instance -> name;
                } else {
                    return "Instance ". substr($token, 0, 8);
                }
            }
        }
        
//      Styling support.
        public function getStyleSupport() {
            return (function_exists('sapi_windows_vt100_support') && sapi_windows_vt100_support(STDOUT))
                || (function_exists('stream_isatty') && stream_isatty(STDOUT))
                || (function_exists('posix_isatty') && posix_isatty(STDOUT));
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
                    'workdir' => getcwd(),
                    'environment' => null,
                    'name' => $name,
                    'pendingRestart' => false
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
            
//          Start instance. [ Ignore proc_open Array to String notice ]
            $instance -> process = @proc_open(
                "{$this -> Script -> daemon} \"{$this -> Script -> install}/{$this -> Script -> run}\" $token",
                $instance -> descriptors,
                $instance -> pipes,
                $instance -> workdir,
                $instance -> environment
            );
            
//          Set non-blocking.
            stream_set_blocking($instance -> pipes[0], false);
            stream_set_blocking($instance -> pipes[1], false);
            stream_set_blocking($instance -> pipes[2], false);
            
//          Check status.
            if (is_resource($instance -> process)) {
                
//              Instance started.
                $this -> log($token, $this -> StringTemplate -> StartInstance);
                
//              Reset instance restart flag.
                if ($instance -> pendingRestart) {
                    $instance -> pendingRestart = false;
                    $this -> RestartingInstances --;
                }
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
                
//              Reset instance restart flag.
                if ($instance -> pendingRestart) {
                    $instance -> pendingRestart = false;
                    $this -> RestartingInstances --;
                }
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
            
//          Check no instance is pending for an update.
            if ($this -> RestartingInstances !== 0) {
                $this -> forEach(function($token, &$instance) {
                    if ($instance -> pendingRestart)
                        $this -> log($token, $this -> StringTemplate -> WaitingRestart);
                });
                return;
            }
            
//          Check for existing build.
            if (file_exists("download")) {
                
//              Flag running processes for restart.
                $this -> log(0, $this -> StringTemplate -> UpdateStart);
                $this -> forEach(function($token, &$instance) {
                    if (!($instance -> pendingRestart)) {
                        $instance -> pendingRestart = true;
                        $this -> RestartingInstances ++;
                    }
                });
                
//              Clean up the old build.
                $this -> clean($this -> Script -> install);
                
//              Download latest build.
                $this -> download($this -> Script -> url);
                
//              Unpack latest build.
                $this -> unzip("download", $this -> Script -> install);
                
//              Finish and reset master flag.
                $this -> log(0, $this -> StringTemplate -> UpdateFinish);
                $this -> UpdatePending = false;
                
//          No build file found.
            } else {
                
//              Flag running processes for restart.
                $this -> log(0, $this -> StringTemplate -> UpdateStart);
                $this -> forEach(function($token, &$instance) {
                    if (!($instance -> pendingRestart)) {
                        $instance -> pendingRestart = true;
                        $this -> RestartingInstances ++;
                    }
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
            
//          Register option handlers.
            $optHandler = array(
                'logIgnoreList' => function($v) {
                    if (is_array($v)) return $v;
                    else return explode($this -> Script['argDelim'], $v);
                },
                'updateFromLocal' => function($v) {return (bool) $v;},
                'updateFromSTDOUT' => function($v) {return (bool) $v;},
                'logLogic' => function($v) {return (int) $v;},
                'instanceLogicTickrate' => function($v) {return (int) $v;},
                'localFrequency' => function($v) {return (int) $v;},
            );
            
//          Convert Script config keys to valid options.
            $validOpts = [];
            foreach (array_keys($this -> Script) as $validOption)
                $validOpts[] = "{$validOption}::";
            
//          Parse provided options.
            $options = getopt("", $validOpts);
            foreach ($options as $key => $value) {
                $json = json_decode($value);
                $value = !is_null($json)? $json: $value;
                if (isset($optHandler[$key])) $this -> Script[$key] = $optHandler[$key]($value);
                else $this -> Script[$key] = $value;
            }
            
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
