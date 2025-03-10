<?php
namespace Wechat\App\Service\Listener;

use Wechat\App\Library\Package;
use Wechat\App\Library\Tools;
use Wechat\App\Service\Transit;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;
// use Workerman\WebServer;
use Workerman\Worker;

/**
 * 进程启动完成
 */
class WorkerStart extends AbstractListener
{
    /** @var null|Worker */
    private $worker = null;

    /**
     * WorkerStart constructor.
     */
    public function __construct()
    {
        $config = Tools::config('web_listen');
        Transit::$webListenAddress = $config['ipaddress'] ?? null;
        Transit::$webListenPort = $config['port'] ?? null;
        parent::__construct();
    }

    /**
     * 处理事件
     */
    public function handle()
    {
        // 打印日志
        Tools::log('Web Worker Started. ');

        // 开启浏览器端的websocket监听，浏览器端发送过来的数据做转发给微信客户端
        $this->worker = new Worker("websocket://" . Transit::$webListenAddress . ":" . Transit::$webListenPort);
        $this->worker->reusePort = true;  // 允许不同进程共同监听
        $this->worker->onMessage = array($this, 'onMessage') ;
        $this->worker->onClose = array($this, 'onClose');
        $this->worker->onConnect = array($this, 'onConnect');
        $this->worker->onError = array($this, 'onError');
        $this->worker->listen();

        // // 监听WebServer
        // $webserver = new WebServer('http://0.0.0.0:80');
        // $webserver->onMessage = function($connection, $info) use ($webserver) {
        //     // 文件访问
        //     $webserver->onMessage($connection);
        // };
        // $webserver->addRoot('*', ROOT_PATH . '/Public/');
        // $webserver->initMimeTypeMap();
        // $webserver->listen();
    }

    /**
     * 接收消息
     * @param TcpConnection $connection
     * @param $message
     */
    public function onMessage($connection, $message)
    {
        try {
            Tools::log('Recv Web Data:  Conn:' . $connection->id . ', Data: ' . $message);
            $json = json_decode($message, true);
            if (
                !isset($json['wechatId']) || !$json['wechatId'] ||
                !isset($json['opCode']) || !is_numeric($json['opCode']) ||
                !isset($json['body']) || is_null($json['body'])
            ) {
                return Tools::log('Recv Web Error Format Message: ' . 'ConnectId=' . $connection->id . ', Message=' . $message);
            }
            // 构造消息包
            $package = new Package();
            $package->setConnection($connection);
            $package->setWechatId($json['wechatId']);
            $package->setOpCode($json['opCode']);
            $package->setBody($json['body']);
            // 把消息扔进中转站
            Transit::webMessage($package);
        } catch (\ErrorException $exception) {
            echo $exception->getMessage()."\n";
            return Tools::log('Recv Web Not Json Message: ' . 'ConnectId=' . $connection->id . ', Message=' . $message);
        }
    }

    /**
     * 连接成功
     * via: https://www.workerman.net/doc/workerman/appendices/about-websocket.html
     * @param TcpConnection $connection
     */
    public function onConnect($connection)
    {
        $connection->onWebSocketConnect = function($connection , $http_header)
        {
            // 可以在这里判断连接来源是否合法，不合法就关掉连接
            // Tools::log("new connection from ip " . $connection->getRemoteIp());
            // client side data
            $app_id = $_GET['app_id'];
            $timestamp = $_GET['timestamp'];
            $hash = $_GET['hash'];
            // server side data
            $local_timestamp = Tools::timestamp();
            $local_app_id = Tools::config('app_id');
            $local_app_key = Tools::config('app_key');
            $expire = Tools::config('expire');
            
            if (abs($local_timestamp - $timestamp) < $expire) {
                if ($app_id == $local_app_id) {
                    $query = "app_id=" . $local_app_id . "&timestamp=" . $timestamp . "&app_key=" . $local_app_key;
                    $local_hash = hash("sha256", $query, false);
                    if ($local_hash == $hash) {
                        Transit::webConnect($connection);
                    } else {
                        $query_old_bug = "app_id=" . $local_app_id . "&timestamp=" . $timestamp . "&app_key" . $local_app_key;
                        $local_hash_old_bug = hash("sha256", $query_old_bug, false);
                        if ($local_hash_old_bug == $hash) {
                            Transit::webConnect($connection);
                        } else {
                            $connection->close();
                            Tools::log("[Error] hash not match!");
                        }
                    }
                } else {
                    $connection->close();
                    Tools::log("[Error] app_id not match!");
                }
            } else {
                $connection->close();
                Tools::log("[Error] timestamp expired!");
            }
        };
    }

    /**
     * 关闭连接
     * @param TcpConnection $connection
     */
    public function onClose($connection)
    {
        Transit::webClose($connection);
    }

    /**
     * 报错信息
     * @param TcpConnection $connection
     * @param int $code
     * @param string $msg
     */
    public function onError($connection, $code, $msg)
    {
        // 打印日记
        Tools::log('Web Error: ' . 'ConnectId=' . $connection->id . ', Message=' . $code . ' - ' . $msg);
    }
}
