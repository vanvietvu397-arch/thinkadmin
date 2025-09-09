<?php
/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */

//declare(ticks=1);
namespace app\gateway;

use \GatewayWorker\Lib\Gateway;
use Workerman\Lib\Timer;
use Workerman\Worker;
use think\worker\Application;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    /**
     * onWorkerStart 事件回调
     * 当businessWorker进程启动时触发。每个进程生命周期内都只会触发一次
     *
     * @access public
     * @param \Workerman\Worker $businessWorker
     * @return void
     */
    public static function onWorkerStart(Worker $businessWorker)
    {
        $app = new Application;
        $app->initialize();
        // 5秒执行一次定时任务
        Timer::add(5, function () use (&$task) {
            try {
                event('JobScheduler');
            } catch (\Throwable $e) {
                echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
            }
        });
    }

    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        // 向当前client_id发送数据
        $data['client_id'] = $client_id;
        $data['type'] = 'init';
        Gateway::sendToClient($client_id, json_encode($data));
    }

    /**
     * 当websocket连接建立时触发
     * @param int $client_id 连接id
     * @param array $data 握手数据
     */
    public static function onWebSocketConnect($client_id, $data)
    {
        // 从URL参数中获取设备ID
        $params = [];
        if (isset($data['get']['device_id'])) {
            $params['device_id'] = $data['get']['device_id'];
        }
        
        // 处理设备连接
        \app\common\service\DeviceWebSocketManager::onDeviceConnect($client_id, $params);
        if (isset($data['get']['device_id'])) {
            $device_id = $data['get']['device_id'];
            $uid = 'device_' . $device_id;
            
            // 绑定设备ID到client_id
            Gateway::bindUid($client_id, $uid);
            
            // 发送连接成功消息
            $response = [
                'type' => 'connect_success',
                'device_id' => $device_id,
                'client_id' => $client_id,
                'message' => '设备连接成功'
            ];
            Gateway::sendToClient($client_id, json_encode($response));
        }
    }

    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $message)
    {
        $data = json_decode($message, 1);
        
        // 处理设备相关消息
        if (isset($data['type'])) {
            // 使用设备WebSocket管理器处理设备消息
            if (in_array($data['type'], ['device_bind', 'device_heartbeat', 'heartbeat', 'status_report', 'interaction_response'])) {
                \app\common\service\DeviceWebSocketManager::onDeviceMessage($client_id, $data);
                return;
            }
            
            switch ($data['type']) {
                case 'device_bind':
                    // 设备绑定
                    if (isset($data['device_id'])) {
                        $uid = 'device_' . $data['device_id'];
                        Gateway::bindUid($client_id, $uid);
                        
                        $response = [
                            'type' => 'bind_success',
                            'device_id' => $data['device_id'],
                            'message' => '设备绑定成功'
                        ];
                        Gateway::sendToClient($client_id, json_encode($response));
                    }
                    return;
                    
                case 'device_heartbeat':
                    // 设备心跳
                    $response = [
                        'type' => 'heartbeat_response',
                        'timestamp' => time()
                    ];
                    Gateway::sendToClient($client_id, json_encode($response));
                    return;
                    
                case 'ping':
                    // 原有心跳逻辑 - 只有当消息包含必要字段时才处理
                    if (isset($data['to']) && isset($data['from'])) {
                        $data['status'] = 0;
                        $to = 0;
                        $from_id = 0;
                        if (isset($data['msg_type']) && $data['msg_type'] == 2) {
                            $to = 'server_' . $data['to'];
                            $from_id = $data['from'];
                        } else {
                            $to = $data['to'];
                            $from_id = 'server_' . $data['from'];
                        }
                        $data['Online'] = $to && Gateway::isUidOnline($to) ? 'on' : 'off';
                        Gateway::sendToUid($from_id, json_encode($data));
                    } else {
                        // 设备心跳响应
                        $response = [
                            'type' => 'pong',
                            'timestamp' => time()
                        ];
                        Gateway::sendToClient($client_id, json_encode($response));
                    }
                    return;
                    
                case 'close':
                    // 断开连接
                    $from_id = isset($data['from']) ? 'server_' . $data['from'] : null;
                    if ($from_id) {
                        Gateway::unbindUid($client_id, $from_id);
                    }
                    return;
            }
        }
        
        // 原有聊天消息处理逻辑 - 只有当消息包含必要字段时才处理
        if (isset($data['to']) && isset($data['from'])) {

        }
    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        // 处理设备断开连接
        \app\common\service\DeviceWebSocketManager::onDeviceDisconnect($client_id);
        
        // 向所有人发送
        //GateWay::sendToAll("$client_id logout\r\n");
    }
}
