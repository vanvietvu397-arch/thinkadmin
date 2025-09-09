<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 设备日志模型
 * Class DeviceLogModel
 * @package app\common\model
 */
class DeviceLogModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_device_log';
    
    // 指定主键
    protected $pk = 'id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'shop_supplier_id' => 'integer',
        'device_id' => 'integer',
        'instruct_id' => 'integer',
        'send_time' => 'integer',
        'receive_time' => 'integer',
        'executed_status' => 'integer',
        'is_delete' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    // 执行状态常量
    const EXECUTED_STATUS_NO = 0;  // 未执行
    const EXECUTED_STATUS_YES = 1; // 已执行
    
    /**
     * 关联设备
     */
    public function device()
    {
        return $this->belongsTo(DeviceModel::class, 'device_id', 'id');
    }
    
    /**
     * 关联指令
     */
    public function instruct()
    {
        return $this->belongsTo(DeviceInstructModel::class, 'instruct_id', 'id');
    }
    
    /**
     * 获取执行状态文本
     */
    public function getExecutedStatusTextAttr($value, $data)
    {
        $statusMap = [
            self::EXECUTED_STATUS_NO => '未执行',
            self::EXECUTED_STATUS_YES => '已执行',
        ];
        return $statusMap[$data['executed_status']] ?? '未知';
    }
    
    /**
     * 记录设备指令日志
     */
    public static function recordLog($deviceId, $instructId, $instructCode, $shopSupplierId, $appId)
    {
        return self::create([
            'device_id' => $deviceId,
            'instruct_id' => $instructId,
            'instruct_code' => $instructCode,
            'shop_supplier_id' => $shopSupplierId,
            'app_id' => $appId,
            'send_time' => time(),
            'executed_status' => self::EXECUTED_STATUS_NO,
        ]);
    }
    
    /**
     * 更新指令执行状态
     */
    public static function updateExecutedStatus($id, $status = self::EXECUTED_STATUS_YES)
    {
        return self::where('id', $id)->update([
            'executed_status' => $status,
            'receive_time' => time(),
        ]);
    }
    
    /**
     * 获取设备指令执行统计
     */
    public static function getDeviceExecutedStats($deviceId, $startTime = null, $endTime = null)
    {
        $query = self::where('device_id', $deviceId)->where('is_delete', 0);
        
        if ($startTime) {
            $query->where('create_time', '>=', $startTime);
        }
        
        if ($endTime) {
            $query->where('create_time', '<=', $endTime);
        }
        
        $total = $query->count();
        $executed = $query->where('executed_status', self::EXECUTED_STATUS_YES)->count();
        
        return [
            'total' => $total,
            'executed' => $executed,
            'pending' => $total - $executed,
            'executed_rate' => $total > 0 ? round($executed / $total * 100, 2) : 0,
        ];
    }
    
    /**
     * 获取指令执行统计
     */
    public static function getInstructExecutedStats($instructId, $startTime = null, $endTime = null)
    {
        $query = self::where('instruct_id', $instructId)->where('is_delete', 0);
        
        if ($startTime) {
            $query->where('create_time', '>=', $startTime);
        }
        
        if ($endTime) {
            $query->where('create_time', '<=', $endTime);
        }
        
        $total = $query->count();
        $executed = $query->where('executed_status', self::EXECUTED_STATUS_YES)->count();
        
        return [
            'total' => $total,
            'executed' => $executed,
            'pending' => $total - $executed,
            'executed_rate' => $total > 0 ? round($executed / $total * 100, 2) : 0,
        ];
    }
    
    /**
     * 获取最近的设备日志
     */
    public static function getRecentLogs($deviceId, $limit = 10)
    {
        return self::with(['instruct'])
                   ->where('device_id', $deviceId)
                   ->where('is_delete', 0)
                   ->order('create_time', 'desc')
                   ->limit($limit)
                   ->select();
    }
}
