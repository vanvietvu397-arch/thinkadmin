<?php

namespace app\common\service;

use GatewayWorker\Lib\Gateway;
use think\facade\Db;
use think\facade\Log;
use app\common\model\DeviceModel;
use app\common\model\DeviceOnlineRecordModel;

/**
 * 设备WebSocket连接管理器
 * 管理智慧屏设备的WebSocket连接和消息推送
 */
class DeviceWebSocketManager
{
    /**
     * @var array 设备连接状态缓存
     */
    private static $deviceConnections = [];

    /**
     * 设备连接时的处理
     *
     * @param string $clientId 客户端连接ID
     * @param array $params 连接参数（包含设备ID等）
     * @return bool
     */
    public static function onDeviceConnect(string $clientId, array $params = []): bool
    {

        try {
            $deviceId = $params['device_id'] ?? null;
            
            if (!$deviceId) {
                Log::warning("设备连接缺少设备ID参数，客户端ID：{$clientId}");
                return false;
            }

            // 验证设备是否存在且启用
            $device = DeviceModel::where('id', $deviceId)
                ->where('status', 1)
                ->where('is_delete', 0)
                ->find();

            if (!$device) {
                Log::warning("无效的设备ID：{$deviceId}，客户端ID：{$clientId}");
                return false;
            }

            // 将客户端加入设备组
            $uid = "device_{$deviceId}";
            Gateway::bindUid($clientId, $uid);
            Gateway::joinGroup($clientId, "device_group_{$deviceId}");

            // 更新设备连接状态
            self::updateDeviceStatus($deviceId, 'connected', $clientId);

            // 缓存连接信息
            self::$deviceConnections[$deviceId] = [
                'client_id' => $clientId,
                'uid' => $uid,
                'connect_time' => time(),
                'last_heartbeat' => time(),
                'device_info' => $device->toArray()
            ];

            // 发送连接确认消息
            $welcomeMessage = [
                'code' => 0,
                'type' => 'connection_confirmed',
                'device_id' => $deviceId,
                'device_name' => $device->device_name,
                'message' => '设备连接成功',
                'timestamp' => time(),
                'server_time' => date('Y-m-d H:i:s')
            ];

            Gateway::sendToClient($clientId, json_encode($welcomeMessage));

            // 检查并推送离线消息
            self::pushOfflineMessages($deviceId);

            Log::info("设备连接成功 - 设备ID：{$deviceId}，设备名称：{$device->device_name}，客户端ID：{$clientId}");

            return true;

        } catch (\Exception $e) {
            Log::error("设备连接处理失败：" . $e->getMessage());
            return false;
        }
    }

    /**
     * 设备断开连接时的处理
     *
     * @param string $clientId 客户端连接ID
     * @return bool
     */
    public static function onDeviceDisconnect(string $clientId): bool
    {
        try {
            // 查找对应的设备ID
            $deviceId = null;
            foreach (self::$deviceConnections as $id => $connection) {
                if ($connection['client_id'] === $clientId) {
                    $deviceId = $id;
                    break;
                }
            }

            if ($deviceId) {
                // 更新设备状态
                self::updateDeviceStatus($deviceId, 'disconnected');

                // 移除连接缓存
                unset(self::$deviceConnections[$deviceId]);

                Log::info("设备断开连接 - 设备ID：{$deviceId}，客户端ID：{$clientId}");
            }

            return true;

        } catch (\Exception $e) {
            Log::error("设备断开连接处理失败：" . $e->getMessage());
            return false;
        }
    }

    /**
     * 处理设备消息
     *
     * @param string $clientId 客户端连接ID
     * @param array $message 消息内容
     * @return bool
     */
    public static function onDeviceMessage(string $clientId, array $message): bool
    {
        try {
            $deviceId = $message['device_id'] ?? null;
            $type = $message['type'] ?? 'unknown';

            if (!$deviceId) {
                Log::warning("收到消息缺少设备ID，客户端ID：{$clientId}");
                return false;
            }

            // 更新心跳时间
            if (isset(self::$deviceConnections[$deviceId])) {
                self::$deviceConnections[$deviceId]['last_heartbeat'] = time();
            }

            switch ($type) {
                case 'heartbeat':
                    // 心跳消息
                    self::handleHeartbeat($clientId, $deviceId);
                    break;

                case 'status_report':
                    // 状态报告
                    self::handleStatusReport($clientId, $deviceId, $message);
                    break;

                case 'interaction_response':
                    // 用户交互响应
                    self::handleInteractionResponse($clientId, $deviceId, $message);
                    break;

                default:
                    Log::info("收到未知类型消息 - 设备ID：{$deviceId}，类型：{$type}");
                    break;
            }

            return true;

        } catch (\Exception $e) {
            Log::error("处理设备消息失败：" . $e->getMessage());
            return false;
        }
    }

    /**
     * 推送消息到指定设备
     *
     * @param int $deviceId 设备ID
     * @param array $data 推送数据
     * @param bool $saveOffline 如果设备离线是否保存离线消息
     * @return bool
     */
    public static function pushToDevice(int $deviceId, array $data, bool $saveOffline = true): bool
    {
        try {
            $uid = "device_{$deviceId}";
            
            // 构造推送消息
            $message = [
                'type' => $data['type'] ?? 'content_push',
                'device_id' => $deviceId,
                'timestamp' => time(),
                'data' => $data
            ];

            // 检查设备是否在线
            if (Gateway::isUidOnline($uid)) {
                Gateway::sendToUid($uid, json_encode($message));
                Log::info("推送消息到设备成功 - 设备ID：{$deviceId}");
                return true;
            } else {
                Log::warning("设备离线，推送失败 - 设备ID：{$deviceId}");
                
                // 保存离线消息
                if ($saveOffline) {
                    self::saveOfflineMessage($deviceId, $message);
                }
                
                return false;
            }

        } catch (\Exception $e) {
            Log::error("推送消息到设备失败 - 设备ID：{$deviceId}，错误：" . $e->getMessage());
            return false;
        }
    }

    /**
     * 批量推送消息到多个设备
     *
     * @param array $deviceIds 设备ID列表
     * @param array $data 推送数据
     * @return array 推送结果
     */
    public static function pushToDevices(array $deviceIds, array $data): array
    {
        $results = [];
        
        foreach ($deviceIds as $deviceId) {
            $results[$deviceId] = self::pushToDevice($deviceId, $data);
        }
        
        return $results;
    }

    /**
     * 推送消息到设备组
     *
     * @param int $groupId 设备组ID
     * @param array $data 推送数据
     * @return bool
     */
    public static function pushToDeviceGroup(int $groupId, array $data): bool
    {
        try {
            // 获取组内设备
            $deviceIds = DeviceModel::where('group_id', $groupId)
                ->where('status', 1)
                ->where('is_delete', 0)
                ->column('id');

            if (empty($deviceIds)) {
                Log::warning("设备组为空 - 组ID：{$groupId}");
                return false;
            }

            // 批量推送
            $results = self::pushToDevices($deviceIds, $data);
            
            $successCount = count(array_filter($results));
            $totalCount = count($results);
            
            Log::info("推送到设备组完成 - 组ID：{$groupId}，成功：{$successCount}/{$totalCount}");
            
            return $successCount > 0;

        } catch (\Exception $e) {
            Log::error("推送到设备组失败 - 组ID：{$groupId}，错误：" . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取设备连接状态
     *
     * @param int|null $deviceId 设备ID，为null时返回所有设备状态
     * @return array
     */
    public static function getDeviceStatus(?int $deviceId = null): array
    {
        if ($deviceId !== null) {
            return self::$deviceConnections[$deviceId] ?? [];
        }
        
        return self::$deviceConnections;
    }

    /**
     * 获取在线设备列表
     *
     * @return array
     */
    public static function getOnlineDevices(): array
    {
        return array_keys(self::$deviceConnections);
    }

    // ========== 私有辅助方法 ==========

    /**
     * 更新设备状态
     */
    private static function updateDeviceStatus(int $deviceId, string $status, ?string $clientId = null): void
    {
        try {
            $updateData = ['xiaozhi_status' => $status];
            
            if ($status === 'connected') {
                $updateData['xiaozhi_last_connect_time'] = time();
                
                // 添加设备在线记录 - 开始在线
                $ipAddress = self::getClientIpAddress($clientId);
                DeviceOnlineRecordModel::startOnlineRecord($deviceId, $ipAddress);
                
                Log::info("设备开始在线记录 - 设备ID：{$deviceId}，IP：{$ipAddress}");
                
            } else {
                $updateData['xiaozhi_last_disconnect_time'] = time();
                
                // 添加设备在线记录 - 结束在线
                DeviceOnlineRecordModel::endOnlineRecord($deviceId);
                
                Log::info("设备结束在线记录 - 设备ID：{$deviceId}");
            }
            
            DeviceModel::where('id', $deviceId)->update($updateData);

        } catch (\Exception $e) {
            Log::error("更新设备状态失败 - 设备ID：{$deviceId}，错误：" . $e->getMessage());
        }
    }

    /**
     * 处理心跳消息
     */
    private static function handleHeartbeat(string $clientId, int $deviceId): void
    {
        $response = [
            'type' => 'heartbeat_response',
            'device_id' => $deviceId,
            'timestamp' => time(),
            'status' => 'ok'
        ];

        Gateway::sendToClient($clientId, json_encode($response));
    }

    /**
     * 处理状态报告
     */
    private static function handleStatusReport(string $clientId, int $deviceId, array $message): void
    {
        try {
            $deviceInfo = $message['device_info'] ?? [];
            
            // 更新设备信息
            if (!empty($deviceInfo)) {
                DeviceModel::where('id', $deviceId)->update([
                    //'device_info' => json_encode($deviceInfo),
                    'update_time' => time()
                ]);
            }

            Log::info("收到设备状态报告 - 设备ID：{$deviceId}");

        } catch (\Exception $e) {
            Log::error("处理设备状态报告失败：" . $e->getMessage());
        }
    }

    /**
     * 处理用户交互响应
     */
    private static function handleInteractionResponse(string $clientId, int $deviceId, array $message): void
    {
        try {
            $interactionData = $message['interaction_data'] ?? [];
            
            // 记录用户交互
            Db::connect('mysql2')->name('shop_voice_conversation')->insert([
                'app_id' => $message['app_id'] ?? 10001,
                'device_id' => $deviceId,
                'input_text' => $interactionData['user_input'] ?? '',
                'input_type' => 'interaction',
                'template_id' => $interactionData['template_id'] ?? null,
                'response_content' => json_encode($interactionData),
                'keywords' => json_encode([]),
                'create_time' => time()
            ]);

            Log::info("记录用户交互 - 设备ID：{$deviceId}");

        } catch (\Exception $e) {
            Log::error("处理用户交互响应失败：" . $e->getMessage());
        }
    }

    /**
     * 保存离线消息
     */
    private static function saveOfflineMessage(int $deviceId, array $message): void
    {
        try {
            Db::connect('mysql2')->name('shop_voice_offline_message')->insert([
                'device_id' => $deviceId,
                'message_type' => $message['type'] ?? 'unknown',
                'message_content' => json_encode($message),
                'status' => 0,
                'create_time' => time()
            ]);

            Log::info("保存离线消息 - 设备ID：{$deviceId}");

        } catch (\Exception $e) {
            Log::error("保存离线消息失败：" . $e->getMessage());
        }
    }

    /**
     * 推送离线消息
     */
    private static function pushOfflineMessages(int $deviceId): void
    {
        try {
            // 获取未推送的离线消息
            $offlineMessages = Db::connect('mysql2')->name('shop_voice_offline_message')
                ->where('device_id', $deviceId)
                ->where('status', 0)
                ->order('create_time ASC')
                ->select();

            foreach ($offlineMessages as $offlineMessage) {
                $messageContent = json_decode($offlineMessage['message_content'], true);

                if (self::pushToDevice($deviceId, $messageContent, false)) {
                    // 标记为已推送
                    Db::connect('mysql2')->name('shop_voice_offline_message')
                        ->where('id', $offlineMessage['id'])
                        ->update([
                            'status' => 1,
                            'push_time' => time()
                        ]);
                }
            }

            if (count($offlineMessages) > 0) {
                Log::info("推送离线消息完成 - 设备ID：{$deviceId}，消息数量：" . count($offlineMessages));
            }

        } catch (\Exception $e) {
            Log::error("推送离线消息失败：" . $e->getMessage());
        }
    }

    /**
     * 获取客户端IP地址
     */
    private static function getClientIpAddress(?string $clientId): string
    {
        try {
            if ($clientId && Gateway::isOnline($clientId)) {
                $session = Gateway::getSession($clientId);
                return $session['remote_ip'] ?? '';
            }
        } catch (\Exception $e) {
            Log::warning("获取客户端IP地址失败：" . $e->getMessage());
        }
        
        return '';
    }
}








