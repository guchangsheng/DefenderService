<?php

namespace DefenderService\DamMicroService;

use DefenderService\DamMicroService\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application as LumenApplication;
use DefenderService\DamMicroService\Commands\ListenCommand;
use DefenderService\DamMicroService\Commands\WorkCommand;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Failed\NullFailedJobProvider;
use Illuminate\Queue\Connectors\BeanstalkdConnector;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Monolog\Handler\StreamHandler;

class DamMicroServiceProvider extends ServiceProvider
{

    protected $commands = [
        'QueueWork'     => 'command.damqueue.work',
        'QueueListen'   => 'command.damqueue.listen',
    ];

    public function boot()
    {
        if($this->app instanceof LumenApplication)
        {
            $this->app->configure('services');
            $this->app->configure('damQueueManager');
        }else{
            $this->publishes([
                dirname(__DIR__).'/config/services.php' => config_path('services.php'), ],
                'dam-services'
            );
        }

        Queue::before(function (JobProcessing $event) //回调队列执行前回调事件
        {
            QueueManager::instance()->MaxWorkTimes($event);
        });

        Queue::after(function (JobProcessed $event) //回调队列执行前后回调事件
        {
            QueueManager::instance()->MaxWorkTimes($event);
        });

        Queue::failing(function (JobFailed $event) //回调失败后处理
        {
            QueueManager::instance()->MaxWorkTimes($event,$this->app['queue.failer']);
        });
    }


    public function register()
    {
        $this->app->singleton('queue.failer', function () {
            $config = $this->app['config']['queue.failed'];

            return isset($config['table'])
                ? $this->databaseFailedJobProvider($config)
                : new NullFailedJobProvider;
        });

        $this->app->bind('Service', function(){
            return $this->app->make('DefenderService\DamMicroService\Service');
        });

        $this->app->bind('DamLogService', function(){
            return $this->app->make('App\Helper\DamLog\DamLogService');
        });

        $this->app->singleton('command.damqueue.listen', function ($app) {  //注册listen
            return new ListenCommand($app['queue.listener']);
        });

        $this->app->singleton('command.damqueue.work', function ($app) {  //注册work
            return new WorkCommand($app['queue.worker']);
        });

        $this->commands(array_values($this->commands));

         if (env('docker') == 1) {
            $this->app->configureMonologUsing(function(\Monolog\Logger $monolog) {
                $handler = (new \Monolog\Handler\StreamHandler(config('logger.lumen.path')))
                    ->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true, true));
                return $monolog->pushHandler($handler);
            });
        } else {
            $this->app->configureMonologUsing(function ($monolog) {
                $maxFiles = config('logger.lumen.days');
                $formatter = new \Monolog\Formatter\LogstashFormatter(null, null, null, 'ctxt_',  \Monolog\Formatter\LogstashFormatter::V1);
                #$LineFormatter = new \Monolog\Formatter\LineFormatter(null, null, true, true);
                $rotatingLogHandler = (new \Monolog\Handler\RotatingFileHandler(config('logger.lumen.path'), $maxFiles))
                    ->setFormatter($formatter);
                $monolog->setHandlers([$rotatingLogHandler]);
                return $monolog;
            });
        }

    }

    /**
     * Create a new database failed job provider.
     *
     * @param  array  $config
     * @return \Illuminate\Queue\Failed\DatabaseFailedJobProvider
     */
    protected function databaseFailedJobProvider($config)
    {
        return new DatabaseFailedJobProvider(
            $this->app['db'], $config['database'], $config['table']
        );
    }
}
