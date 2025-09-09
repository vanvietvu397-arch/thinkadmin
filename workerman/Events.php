<?php
/**
 * Gateway Worker 业务处理类
 * 与TemplateService集成，处理智慧展示屏的WebSocket连接
 * 主要是处理 onConnect onMessage onClose 三个方法
 */

use \GatewayWorker\Lib\Gateway;
use think\facade\Log;

class Events
{
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
        Log::info('onWebSocketConnect', $data);
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
        // 解析消息
        $data = json_decode($message, true);
        
        if (!$data) {
            Gateway::sendToClient($client_id, json_encode([
                'type' => 'error',
                'message' => '消息格式错误'
            ]));
            return;
        }
        // 使用设备WebSocket管理器处理设备消息
        if (in_array($data['type'], ['device_bind', 'device_heartbeat', 'heartbeat', 'status_report', 'interaction_response'])) {
            \app\common\service\DeviceWebSocketManager::onDeviceMessage($client_id, $data);
            return;
        }
        
        // 根据消息类型处理
        switch ($data['type']) {
            case 'ping':
                Gateway::sendToClient($client_id, json_encode([
                    'type' => 'pong',
                    'timestamp' => time()
                ]));
                return;
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
                
            case 'device_register':
                // 设备注册
                $device_id = $data['device_id'] ?? null;
                if ($device_id) {
                    $uid = 'device_' . $device_id;
                    Gateway::bindUid($client_id, $uid);
                    Gateway::sendToClient($client_id, json_encode([
                        'type' => 'device_registered',
                        'device_id' => $device_id,
                        'uid' => $uid,
                        'message' => '设备注册成功'
                    ]));
                }

                return;
            case 'close':
                // 断开连接
                $from_id = isset($data['from']) ? 'server_' . $data['from'] : null;
                if ($from_id) {
                    Gateway::unbindUid($client_id, $from_id);
                }
                return;

            default:
                Gateway::sendToClient($client_id, json_encode([
                    'type' => 'response',
                    'message' => '收到消息: ' . $message
                ]));
                break;
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
