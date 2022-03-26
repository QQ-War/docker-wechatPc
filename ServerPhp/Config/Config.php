<?php
/**
 * 公共配置
 */

return [
    /**
     * 是否开启debug模式
     */
    'debug' => true,
    'log_max_length' => 0,
    /**
     * app_id & app_key
     * expire: 10*60*1000 ms, hash (token) 过期时间
     */
    'app_id' => '1234567890ABCDEFGHIJKLMNOPQRSTUV',
    'app_key' => '1234567890ABCDEFGHIJKLMNOPQRSTUV',
    'expire' => 10*60*1000,
    /**
     * 服务端websocket配置
     */
    'listen'=>[
        'ipaddress'=>'0.0.0.0',
        'port'=>8686
    ],
    /**
     * 浏览器端websocket配置
     */
    'web_listen'=>[
        'ipaddress'=>'0.0.0.0',
        'port'=>5678
    ],
    /**
     * 基础配置
     */
    'worker'=>[
        'daemonize' => false,  //是否后台运行
        'worker_num' => 1,  //工作进程数量
        'name' => 'Wechat', //服务名称
        'log_file' => ROOT_PATH . '/Storage/logs/wechat.log',  //日记文件
        'pid_file' => ROOT_PATH . '/Storage/pid/wechat.pid',  //服务PID文件
        'stdout_file' => '',  //屏幕打印输出到文件，不设置或者为空则打印到频幕
    ],
];
