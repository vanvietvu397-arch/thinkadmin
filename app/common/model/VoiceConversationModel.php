<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 语音对话记录模型
 * Class VoiceConversationModel
 * @package app\common\model
 */
class VoiceConversationModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_shop_voice_conversation';
    
    // 指定主键
    protected $pk = 'conversation_id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = false; // 该表没有更新时间字段
    
    // 日志类型
    protected $oplogType = '语音对话管理';
    
    // 日志名称
    protected $oplogName = '对话记录';
    
    // 字段类型转换
    protected $type = [
        'conversation_id' => 'integer',
        'app_id' => 'integer',
        'device_id' => 'string',
        'input_text' => 'string',
        'input_type' => 'string',
        'template_id' => 'integer',
        'response_content' => 'string',
        'keywords' => 'string',
        'create_time' => 'integer',
    ];
    
    // 输入类型常量
    const INPUT_TYPE_QUERY = 'query';   // 查询
    const INPUT_TYPE_VOICE = 'voice';   // 语音
    const INPUT_TYPE_TEXT = 'text';     // 文本
    
    /**
     * 关联应用
     */
    public function app()
    {
        return $this->belongsTo(AppModel::class, 'app_id', 'app_id');
    }
    
    /**
     * 关联设备
     */
    public function device()
    {
        return $this->belongsTo(DeviceModel::class, 'device_id', 'device_id');
    }
    
    /**
     * 关联模板
     */
    public function template()
    {
        return $this->belongsTo(VoiceTemplateModel::class, 'template_id', 'template_id');
    }
    
    /**
     * 获取输入类型文本
     */
    public function getInputTypeTextAttr($value, $data)
    {
        $types = [
            self::INPUT_TYPE_QUERY => '查询',
            self::INPUT_TYPE_VOICE => '语音',
            self::INPUT_TYPE_TEXT => '文本',
        ];
        return $types[$data['input_type']] ?? '未知';
    }
    
    /**
     * 获取关键词数组
     */
    public function getKeywordsArrayAttr($value, $data)
    {
        if (empty($data['keywords'])) {
            return [];
        }
        return json_decode($data['keywords'], true) ?: [];
    }
    
    /**
     * 获取响应内容数组
     */
    public function getResponseContentArrayAttr($value, $data)
    {
        if (empty($data['response_content'])) {
            return [];
        }
        return json_decode($data['response_content'], true) ?: [];
    }
    
    /**
     * 设置关键词
     */
    public function setKeywordsAttr($value)
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return $value;
    }
    
    /**
     * 设置响应内容
     */
    public function setResponseContentAttr($value)
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return $value;
    }
    
    /**
     * 获取对话列表
     */
    public static function getConversationList($appId = null, $deviceId = null, $limit = 20)
    {
        $query = self::order('create_time desc');
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }
        
        return $query->limit($limit)->select();
    }
    
    /**
     * 根据关键词搜索对话
     */
    public static function searchByKeywords($keywords, $appId = null, $limit = 20)
    {
        $query = self::order('create_time desc');
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        if (is_array($keywords)) {
            foreach ($keywords as $keyword) {
                $query->where('keywords', 'like', '%' . $keyword . '%');
            }
        } else {
            $query->where('keywords', 'like', '%' . $keywords . '%');
        }
        
        return $query->limit($limit)->select();
    }
}
