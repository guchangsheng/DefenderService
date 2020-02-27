<?phpnamespace DefenderService\DamMicroService\Traits;use DefenderService\DamMicroService\LogService\DamLog;use Illuminate\Queue\Queue;trait ListenCommandTrait{    private  $PidFile;    //pid文件    private  $PidIds;     //进程ID    private  $counter;    private  $ForkNumber;    private  $childrenIds = [];    /**     * @description 守护进程     * @author changsheng.gu@vcg.com     * @param     * @return void     */    private function demonize()    {        if (!defined('WNOHANG')){            throw new \Exception('Must install pcntl extension first');        }        $pid_t = pcntl_fork();//        if($pid_t == -1){            pcntl_get_last_error();            exit(1);        }elseif ($pid_t>0){            exit(0);  //父进程退出        }        if(posix_setsid() == -1)        {            die('Could not detach');        }        chdir('/');        umask(0);        self::writePid();    }    /**     * @description doFork     * @author changsheng.gu@vcg.com     * @param  $count integer  $number integer     * @return void     */    private function doFork(&$count,&$number)    {        for($count=0;$count<$number;$count++)        {            $cid =pcntl_fork();            if($cid == -1)            {                pcntl_get_last_error();                exit(1);            }elseif ($cid==0){                $this->writePid();                break;            }else{                array_push($this->childrenIds,$cid);            }        }    }    /**     * @description writePid     * @author changsheng.gu@vcg.com     * @param     * @return void     */    private function writePid()    {        $fp = fopen($this->PidFile, 'a');        if(!$fp) throw new \Exception('Try to open the pid file failed');        fwrite($fp, posix_getpid().PHP_EOL);        fclose($fp);    }    /**     * @description 获取进程pid     * @author changsheng.gu@vcg.com     * @param     * @return array     */    private function getPidIds()    {        if (!file_exists($this->PidFile))  return [];        $pidFile    = file_get_contents($this->PidFile);        $pids       = array_filter(explode("\n",$pidFile));        $findPids  = [];        foreach ($pids as $val)        {            if(posix_kill(intval($val), SIG_DFL)) $findPids [] = $val;        }        if (empty($findPids)) {            unlink($this->PidFile);            return [];        } else {            return $findPids;        }    }    /**     * @description work process     * @author changsheng.gu@vcg.com     * @param  $count integer  $number integer     * @return void     */    private function childExec($count,$number)    {        if($count<$number) {            $this->reinstallSignal();            $this->setTitle("child");            $this->listen();        }    }    /**     * @description master process     * @author changsheng.gu@vcg.com     * @param     * @return void     */    private function parentExec()    {        if($this->counter == $this->ForkNumber)        {            self::register_signal(SIGUSR2); //主进程注册信号捕捉            $this->setTitle("master");            $childNum = count($this->childrenIds);            $reloadNum = 0;            while(count($this->childrenIds)>0)            {                pcntl_signal_dispatch();                foreach($this->childrenIds as $key => $pid) {                    $res = pcntl_waitpid($pid,$status, WNOHANG);                    //If the process has already exited                    if($res == -1 || $res > 0){                        unset($this->childrenIds[$key]);                        if(!pcntl_wifexited($status)){                            $stopSig = pcntl_wtermsig($status);                            if($stopSig == SIGUSR1){                                $reloadNum++;                                $this->printInfo($this->queue."工作进程:$pid 平滑停止.");                                if(count($this->childrenIds)){                                    $this->printInfo($this->queue."剩余进程:".implode(',',$this->childrenIds));                                }                            }else{                                DamLog::channel('queue_process_manager')                                    ->info($this->queue."检测到工作进程:$pid 异常中断. 中断信号为:$stopSig");                                $count  = 0;                                $number = 1;                                DamLog::channel('queue_process_manager')->info($this->queue."工作进程:$pid .重启中");                                $this->doFork($count,$number);//重新拉起新的进程                                self::childExec($count,$number);                            }                        }else{                            $exit_status = pcntl_wexitstatus($status);                            DamLog::channel('queue_process_manager')                                ->info($this->queue."工作进程:$pid .退出码:".$exit_status);                        }                    }                }                sleep(1);            }            if($childNum == $reloadNum){                $this->printInfo($this->queue."队列成功平滑停止.");            }            unlink($this->PidFile);            DamLog::channel('queue_process_manager')                ->info($this->queue."所有工作进程已回收完毕");        }    }    /**     * @description process title set     * @author changsheng.gu@vcg.com     * @param  string     * @return void     */    public function setTitle(string $type)    {        $title = $this->getTitle();        if($type =="master")        {            $title = "php artisan damqueue:master --queue=$this->queue";        }elseif ($type =="child"){            $title = "php ".$title;        }        if (function_exists('cli_set_process_title')){            cli_set_process_title($title);        }    }    /**     * @description process title set     * @author changsheng.gu@vcg.com     * @param  string     * @return string     */    public function getTitle()    {        global $argv;        if(is_array($argv)){            return implode(' ',$argv);        }        return false;    }    /**     * @description process     * @author changsheng.gu@vcg.com     * @param  string     * @return void     */    protected function process($param)    {        if($param == 'start'){            $this->start();        }elseif ($param == 'stop'){            $this->stop();        }elseif($param =='status'){            $this->status();        }elseif($param =='reload') {            $this->reload();        }    }    /**     * @description start     * @author changsheng.gu@vcg.com     * @param     * @return void     */    public function start()    {        if (!empty($this->getPidIds()))        {            $this->printInfo('The queue already Running');        } else {            self::demonize();            $this->printInfo('Start Success');            self::doFork($this->counter,$this->ForkNumber);            self::parentExec();            self::childExec($this->counter,$this->ForkNumber);        }    }    /**     * @description run     * @author changsheng.gu@vcg.com     * @param     * @return void     */    protected function stop()    {        $pid = $this->getPidIds();        if (!empty($pid)) {            foreach ($pid as $val){                posix_kill(intval($val), SIGTERM);            }            unlink($this->PidFile);            $this->printInfo('Stop success');        } else            $this->printInfo('Stop Failed. Process Not Running');        return;    }    /**     * @description reload     * @author changsheng.gu@vcg.com     * @param  string     * @return void     */    protected function reload()    {        $pid = $this->getMasterPid();        posix_kill(intval($pid), SIGUSR2);        $this->printInfo(" reload signal send success. ");    }    /**     * @description run     * @author changsheng.gu@vcg.com     * @param  string     * @return void     */    protected function status()    {        if (!empty($this->getPidIds())){            $this->printInfo('Is Running');        } else            $this->printInfo('Not Running');    }    /**     * @description run     * @author changsheng.gu@vcg.com     * @param  string     * @return void     */    private function printInfo($message)    {        echo $message.PHP_EOL;    }    /**     * register signal     * create by changsheng.gu@vcg.com     * @return void     */    public function register_signal($sig)    {        pcntl_signal($sig,array(&$this,"signal_handle"));    }    /**     * reinstall signal     * create by changsheng.gu@vcg.com     * @return void     */    protected function reinstallSignal()    {        #pcntl_signal(SIGUSR1 ,SIG_DFL, false);        pcntl_signal(SIGUSR2 ,SIG_DFL, false);    }    /**     * register signal     * create by changsheng.gu@vcg.com     *     * @return void     */    public function signal_handle($sig)    {        if($sig == SIGUSR1)             //worker进程信号处理        {            $pid = posix_getpid();            posix_kill($pid, SIGUSR2);        } elseif($sig == SIGUSR2){            foreach ($this->childrenIds as $val){                posix_kill(intval($val), SIGUSR1);            }        }    }    /**     * get master id     * create by changsheng.gu@vcg.com     * @return integer     */    public function getMasterPid()    {        $pid = $this->getPidIds();        if(isset($pid[0])){            return $pid[0];        }        throw new \Exception("Get master pid failed .Sure Master is running ?");    }}