<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 智慧屏离线消息模型
 * Class VoiceOfflineMessageModel
 * @package app\common\model
 */
class VoiceOfflineMessageModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_shop_voice_offline_message';
    
    // 指定主键
    protected $pk = 'id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = false; // 该表没有更新时间字段
    
    // 日志类型
    protected $oplogType = '离线消息管理';
    
    // 日志名称
    protected $oplogName = '离线消息';
    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'device_id' => 'integer',
        'message_type' => 'string',
        'message_content' => 'string',
        'status' => 'integer',
        'create_time' => 'integer',
        'push_time' => 'integer',
    ];
    
    // 消息类型常量
    const MESSAGE_TYPE_TEXT = 'text';           // 文本消息
    const MESSAGE_TYPE_VOICE = 'voice';         // 语音消息
    const MESSAGE_TYPE_IMAGE = 'image';         // 图片消息
    const MESSAGE_TYPE_VIDEO = 'video';        // 视频消息
    const MESSAGE_TYPE_NOTIFICATION = 'notification'; // 通知消息
    const MESSAGE_TYPE_COMMAND = 'command';     // 指令消息
    
    // 状态常量
    const STATUS_UNPUSHED = 0;  // 未推送
    const STATUS_PUSHED = 1;    // 已推送
    
    /**
     * 关联设备
     */
    public function device()
    {
        return $this->belongsTo(DeviceModel::class, 'device_id', 'device_id');
    }
    
    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        return $data['status'] ? '已推送' : '未推送';
    }
    
    /**
     * 获取消息类型文本
     */
    public function getMessageTypeTextAttr($value, $data)
    {
        $types = [
            self::MESSAGE_TYPE_TEXT => '文本消息',
            self::MESSAGE_TYPE_VOICE => '语音消息',
            self::MESSAGE_TYPE_IMAGE => '图片消息',
            self::MESSAGE_TYPE_VIDEO => '视频消息',
            self::MESSAGE_TYPE_NOTIFICATION => '通知消息',
            self::MESSAGE_TYPE_COMMAND => '指令消息',
        ];
        return $types[$data['message_type']] ?? '未知';
    }
    
    /**
     * 获取消息内容数组
     */
    public function getMessageContentArrayAttr($value, $data)
    {
        if (empty($data['message_content'])) {
            return [];
        }
        return json_decode($data['message_content'], true) ?: [];
    }
    
    /**
     * 设置消息内容
     */
    public function setMessageContentAttr($value)
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return $value;
    }
    
    /**
     * 获取设备离线消息列表
     */
    public static function getDeviceMessages($deviceId, $status = null, $limit = 50)
    {
        $query = self::where('device_id', $deviceId)
            ->order('create_time desc');
        
        if ($status !== null) {
            $query->where('status', $status);
        }
        
        return $query->limit($limit)->select();
    }
    
    /**
     * 获取未推送的消息
     */
    public static function getUnpushedMessages($deviceId = null, $limit = 100)
    {
        $query = self::where('status', self::STATUS_UNPUSHED)
            ->order('create_time asc');
        
        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }
        
        return $query->limit($limit)->select();
    }
    
    /**
     * 创建离线消息
     */
    public static function createMessage($deviceId, $messageType, $messageContent, $appId = null)
    {
        $data = [
            'device_id' => $deviceId,
            'message_type' => $messageType,
            'message_content' => is_array($messageContent) ? json_encode($messageContent, JSON_UNESCAPED_UNICODE) : $messageContent,
            'status' => self::STATUS_UNPUSHED,
            'create_time' => time(),
            'push_time' => 0,
        ];
        
        return self::create($data);
    }
    
    /**
     * 标记消息为已推送
     */
    public function markAsPushed()
    {
        return $this->save([
            'status' => self::STATUS_PUSHED,
            'push_time' => time(),
        ]);
    }
    
    /**
     * 批量标记消息为已推送
     */
    public static function batchMarkAsPushed($ids)
    {
        return self::whereIn('id', $ids)->update([
            'status' => self::STATUS_PUSHED,
            'push_time' => time(),
        ]);
    }
    
    /**
     * 清理过期消息
     */
    public static function cleanExpiredMessages($days = 30)
    {
        $expireTime = time() - ($days * 24 * 3600);
        return self::where('create_time', '<', $expireTime)->delete();
    }
}
