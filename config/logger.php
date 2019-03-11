<?php
/**
 * Created by PhpStorm.
 * User: guchangsheng
 * Date: 2018/8/29
 * Time: 上午11:44
 */

return  [

    'lumen'=>[
        'path'=>'/data/lumen_log/resource/lumen.log',//系统日志
        'days' => 7,
        'driver' =>'daily',
    ],

    'queue_process_manager' => [            //自定义队列日志
        'path' => '/data/lumen_log/resource/queue_process_manager.log',
        'days' => 30,
        'driver' =>'daily',
    ],
];