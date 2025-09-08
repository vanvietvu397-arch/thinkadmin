<?php

namespace app\common\service;

use app\common\model\Devices;
use app\common\model\DeviceClassify;
use app\common\model\DeviceGroup;
use app\common\model\DeviceInstruct;
use app\common\model\DeviceInstructMiddle;
use app\common\model\DeviceLog;
use app\common\model\DevicePush;

/**
 * 设备服务类
 * Class DeviceService
 * @package app\common\service
 */
class DeviceService
{
    /**
     * 获取设备统计信息
     */
    public static function getDeviceStats($shopSupplierId = null)
    {
        return [
            'total' => Devices::getTotalCount($shopSupplierId),
            'online' => Devices::getOnlineCount($shopSupplierId),
            'offline' => Devices::getTotalCount($shopSupplierId) - Devices::getOnlineCount($shopSupplierId),
        ];
    }
    
    /**
     * 获取设备列表
     */
    public static function getDeviceList($shopSupplierId = null, $params = [])
    {
        $query = Devices::with(['classify', 'group', 'instructs'])
                      ->where('is_delete', 0);
        
        if ($shopSupplierId) {
            $query->where('shop_supplier_id', $shopSupplierId);
        }
        
        // 按状态筛选
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }
        
        // 按小智状态筛选
        if (isset($params['xiaozhi_status'])) {
            $query->where('xiaozhi_status', $params['xiaozhi_status']);
        }
        
        // 按分类筛选
        if (isset($params['classify_id'])) {
            $query->where('classify_id', $params['classify_id']);
        }
        
        // 按分组筛选
        if (isset($params['group_id'])) {
            $query->where('group_id', $params['group_id']);
        }
        
        return $query->order('id', 'desc')->paginate($params['limit'] ?? 15);
    }
    
    /**
     * 创建设备
     */
    public static function createDevice($data)
    {
        return Devices::create($data);
    }
    
    /**
     * 更新设备
     */
    public static function updateDevice($id, $data)
    {
        $device = Devices::find($id);
        if (!$device) {
            return false;
        }
        
        return $device->save($data);
    }
    
    /**
     * 删除设备
     */
    public static function deleteDevice($id)
    {
        $device = Devices::find($id);
        if (!$device) {
            return false;
        }
        
        return $device->save(['is_delete' => 1]);
    }
    
    /**
     * 为设备设置指令
     */
    public static function setDeviceInstructs($deviceId, $instructIds, $shopSupplierId, $appId)
    {
        return DeviceInstructMiddle::setDeviceInstructs($deviceId, $instructIds, $shopSupplierId, $appId);
    }
    
    /**
     * 获取设备的指令
     */
    public static function getDeviceInstructs($deviceId)
    {
        return DeviceInstructMiddle::getDeviceInstructs($deviceId);
    }
    
    /**
     * 发送设备指令
     */
    public static function sendDeviceInstruct($deviceId, $instructId, $shopSupplierId, $appId)
    {
        // 获取指令信息
        $instruct = DeviceInstruct::find($instructId);
        if (!$instruct) {
            return false;
        }
        
        // 记录日志
        $log = DeviceLog::recordLog($deviceId, $instructId, $instruct->instruct_code, $shopSupplierId, $appId);
        
        // 这里可以添加实际的指令发送逻辑
        // 比如通过 WebSocket 或 HTTP 请求发送到设备
        
        return $log;
    }
    
    /**
     * 获取设备日志
     */
    public static function getDeviceLogs($deviceId, $limit = 20)
    {
        return DeviceLog::getRecentLogs($deviceId, $limit);
    }
    
    /**
     * 获取设备执行统计
     */
    public static function getDeviceExecutedStats($deviceId, $startTime = null, $endTime = null)
    {
        return DeviceLog::getDeviceExecutedStats($deviceId, $startTime, $endTime);
    }
    
    /**
     * 获取分类选项
     */
    public static function getClassifyOptions($shopSupplierId = null)
    {
        return DeviceClassify::getOptions($shopSupplierId);
    }
    
    /**
     * 获取分组选项
     */
    public static function getGroupOptions($shopSupplierId = null)
    {
        return DeviceGroup::getOptions($shopSupplierId);
    }
    
    /**
     * 获取指令选项
     */
    public static function getInstructOptions($shopSupplierId = null)
    {
        return DeviceInstruct::getOptions($shopSupplierId);
    }
    
    /**
     * 创建推送任务
     */
    public static function createPush($data)
    {
        return DevicePush::createPush($data);
    }
    
    /**
     * 获取推送列表
     */
    public static function getPushList($shopSupplierId = null, $status = null)
    {
        return DevicePush::getList($shopSupplierId, $status);
    }
    
    /**
     * 获取推送统计
     */
    public static function getPushStats($shopSupplierId = null)
    {
        return DevicePush::getStats($shopSupplierId);
    }
}
