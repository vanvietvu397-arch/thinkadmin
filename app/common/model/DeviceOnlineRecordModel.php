<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 设备在线状态记录模型
 * Class DeviceOnlineRecord
 * @package app\common\model
 */
class DeviceOnlineRecordModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_device_online_record';
    
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
        'device_id' => 'integer',
        'start_time' => 'integer',
        'end_time' => 'integer',
        'duration' => 'integer',
        'ip_address' => 'string',
        'status' => 'integer',
        'is_delete' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    // 状态枚举
    const STATUS_ONLINE = 1;  // 在线中
    const STATUS_OFFLINE = 0; // 已离线
    
    // 删除状态枚举
    const DELETE_NO = 0;  // 未删除
    const DELETE_YES = 1; // 已删除
    
    /**
     * 关联设备
     */
    public function device()
    {
        return $this->belongsTo(DeviceModel::class, 'device_id', 'id');
    }
    
    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $statusMap = [
            self::STATUS_ONLINE => '在线中',
            self::STATUS_OFFLINE => '已离线',
        ];
        return $statusMap[$data['status']] ?? '未知';
    }
    
    /**
     * 获取删除状态文本
     */
    public function getIsDeleteTextAttr($value, $data)
    {
        $deleteMap = [
            self::DELETE_NO => '未删除',
            self::DELETE_YES => '已删除',
        ];
        return $deleteMap[$data['is_delete']] ?? '未知';
    }
    
    /**
     * 获取在线时长文本（格式化显示）
     */
    public function getDurationTextAttr($value, $data)
    {
        if (empty($data['duration'])) {
            return '计算中...';
        }
        
        $duration = $data['duration'];
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;
        
        if ($hours > 0) {
            return sprintf('%d小时%d分钟%d秒', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%d分钟%d秒', $minutes, $seconds);
        } else {
            return sprintf('%d秒', $seconds);
        }
    }
    
    /**
     * 开始在线记录
     * @param int $deviceId 设备ID
     * @param string $ipAddress IP地址
     * @return DeviceOnlineRecordModel|false
     */
    public static function startOnlineRecord($deviceId, $ipAddress = '')
    {
        // 先结束该设备之前的在线记录
        self::endOnlineRecord($deviceId);
        
        // 创建新的在线记录
        $record = new self();
        $record->device_id = $deviceId;
        $record->start_time = time();
        $record->end_time = null;
        $record->duration = null;
        $record->ip_address = $ipAddress;
        $record->status = self::STATUS_ONLINE;
        $record->is_delete = self::DELETE_NO;
        
        return $record->save() ? $record : false;
    }
    
    /**
     * 结束在线记录
     * @param int $deviceId 设备ID
     * @return bool
     */
    public static function endOnlineRecord($deviceId)
    {
        $record = self::where('device_id', $deviceId)
                     ->where('status', self::STATUS_ONLINE)
                     ->where('is_delete', self::DELETE_NO)
                     ->find();
        
        if ($record) {
            $endTime = time();
            $duration = $endTime - $record->start_time;
            
            $record->end_time = $endTime;
            $record->duration = $duration;
            $record->status = self::STATUS_OFFLINE;
            
            return $record->save();
        }
        
        return true;
    }
    
    /**
     * 获取设备当前在线记录
     * @param int $deviceId 设备ID
     * @return DeviceOnlineRecordModel|null
     */
    public static function getCurrentOnlineRecord($deviceId)
    {
        return self::where('device_id', $deviceId)
                  ->where('status', self::STATUS_ONLINE)
                  ->where('is_delete', self::DELETE_NO)
                  ->find();
    }
    
    /**
     * 获取设备在线记录列表
     * @param int $deviceId 设备ID
     * @param int $limit 限制数量
     * @return \think\Collection
     */
    public static function getDeviceOnlineRecords($deviceId, $limit = 20)
    {
        return self::where('device_id', $deviceId)
                  ->where('is_delete', self::DELETE_NO)
                  ->order('start_time', 'desc')
                  ->limit($limit)
                  ->select();
    }
    
    /**
     * 获取设备总在线时长（秒）
     * @param int $deviceId 设备ID
     * @param int $startTime 开始时间戳
     * @param int $endTime 结束时间戳
     * @return int
     */
    public static function getTotalOnlineDuration($deviceId, $startTime = null, $endTime = null)
    {
        $query = self::where('device_id', $deviceId)
                    ->where('is_delete', self::DELETE_NO)
                    ->where('status', self::STATUS_OFFLINE)
                    ->whereNotNull('duration');
        
        if ($startTime) {
            $query->where('start_time', '>=', $startTime);
        }
        
        if ($endTime) {
            $query->where('end_time', '<=', $endTime);
        }
        
        return $query->sum('duration');
    }
    
    /**
     * 获取在线设备数量
     * @return int
     */
    public static function getOnlineDeviceCount()
    {
        return self::where('status', self::STATUS_ONLINE)
                  ->where('is_delete', self::DELETE_NO)
                  ->count();
    }
    
    /**
     * 获取指定时间范围内的在线记录
     * @param int $startTime 开始时间戳
     * @param int $endTime 结束时间戳
     * @param int $limit 限制数量
     * @return \think\Collection
     */
    public static function getOnlineRecordsByTimeRange($startTime, $endTime, $limit = 100)
    {
        return self::where('start_time', '>=', $startTime)
                  ->where('start_time', '<=', $endTime)
                  ->where('is_delete', self::DELETE_NO)
                  ->with('device')
                  ->order('start_time', 'desc')
                  ->limit($limit)
                  ->select();
    }
    
    /**
     * 软删除记录
     * @return bool
     */
    public function softDelete()
    {
        $this->is_delete = self::DELETE_YES;
        return $this->save();
    }
    
    /**
     * 批量软删除记录
     * @param array $ids 记录ID数组
     * @return bool
     */
    public static function batchSoftDelete($ids)
    {
        if (empty($ids)) {
            return false;
        }
        
        return self::whereIn('id', $ids)
                  ->update(['is_delete' => self::DELETE_YES]);
    }
}
