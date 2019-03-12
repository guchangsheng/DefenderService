<?php

namespace DefenderService\DamMicroService\Commands;

use DefenderService\DamMicroService\Traits\ListenCommandTrait;
use Illuminate\Queue\Listener;
use Illuminate\Console\Command;
use Illuminate\Queue\ListenerOptions;

class ListenCommand extends Command
{
    use ListenCommandTrait;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'damqueue:listen
                            {connection? : The name of connection}
                            {--delay=0 : The number of seconds to delay failed jobs}
                            {--force : Force the worker to run even in maintenance mode}
                            {--memory=128 : The memory limit in megabytes}
                            {--queue= : The queue to listen on}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=0 : Number of times to attempt a job before logging it failed}
                            {--r= : run type}
                            {--n= : Numbers of process}'; //进程数量
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to a given queue';

    /**
     * The queue listener instance.
     *
     *
     * @var \Illuminate\Queue\Listener
     */
    protected $listener;

    /**
     * Create a new queue listen command.
     *
     * @param  \Illuminate\Queue\Listener  $listener
     * @return void
     */
    public function __construct(Listener $listener)
    {
        parent::__construct();

        $this->setOutputHandler($this->listener = $listener);
    }


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $run = $this->option('r'); //start stop  status
        if(empty($run)){
            $this->printInfo('必须选择执行方式 --r=start --r=stop --r==status');
            exit(0);
        }else{
            $queue = $this->option('queue');
            if($run =='start')
            {
                $this->ForkNumber = $this->option('n')?$this->option('n'):1; //进程数量

            }elseif($run == 'stop'||$run == 'status') {

                if(!$queue) $this->printInfo("必须先指定一个要".$run."的队列");
            }else{
                $this->printInfo('无效run命令');
            }
        }
        $this->PidFile = '/tmp/'.$this->option('queue')."_Pid_File";
        $this->process($run);
        // We need to get the right queue for the connection which is set in the queue
        // configuration file for the application. We will pull it based on the set
        // connection being run for the queue operation currently being executed.
    }


    protected function listen()
    {
        $queue = $this->getQueue(
            $connection = $this->input->getArgument('connection')
        );

        $this->listener->listen(
            $connection, $queue, $this->gatherOptions()
        );
    }

    /**
     * Get the name of the queue connection to listen on.
     *
     * @param  string  $connection
     * @return string
     */
    protected function getQueue($connection)
    {
        $connection = $connection ?: $this->laravel['config']['queue.default'];

        return $this->input->getOption('queue') ?: $this->laravel['config']->get(
            "queue.connections.{$connection}.queue", 'default'
        );
    }

    /**
     * Get the listener options for the command.
     *
     * @return \Illuminate\Queue\ListenerOptions
     */
    protected function gatherOptions()
    {
        return new ListenerOptions(
            $this->option('env'), $this->option('delay'),
            $this->option('memory'), $this->option('timeout'),
            $this->option('sleep'), $this->option('tries'),
            $this->option('force')
        );
    }
    /**
     * Set the options on the queue listener.
     *
     * @param  \Illuminate\Queue\Listener  $listener
     * @return void
     */
    protected function setOutputHandler(Listener $listener)
    {
        $listener->setOutputHandler(function ($type, $line) {
            $this->output->write($line);
        });
    }
}
