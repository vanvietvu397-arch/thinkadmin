<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 语音关键词模型
 * Class VoiceKeywordModel
 * @package app\common\model
 */
class VoiceKeywordModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_shop_voice_keyword';
    
    // 指定主键
    protected $pk = 'keyword_id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    
    // 日志类型
    protected $oplogType = '语音关键词管理';
    
    // 日志名称
    protected $oplogName = '关键词';
    
    // 字段类型转换
    protected $type = [
        'keyword_id' => 'integer',
        'app_id' => 'integer',
        'keyword_text' => 'string',
        'keyword_type' => 'string',
        'weight' => 'integer',
        'status' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    // 关键词类型常量
    const KEYWORD_TYPE_NORMAL = 'normal';     // 普通关键词
    const KEYWORD_TYPE_SYNONYM = 'synonym';   // 同义词
    const KEYWORD_TYPE_ALIAS = 'alias';       // 别名
    
    // 状态常量
    const STATUS_DISABLED = 0;  // 禁用
    const STATUS_ENABLED = 1;   // 启用
    
    /**
     * 关联应用
     */
    public function app()
    {
        return $this->belongsTo(AppModel::class, 'app_id', 'app_id');
    }
    
    /**
     * 关联模板（多对多）
     */
    public function templates()
    {
        return $this->belongsToMany(VoiceTemplateModel::class, VoiceTemplateKeywordRelModel::class, 'template_id', 'keyword_id');
    }
    
    /**
     * 关联模板关键词关系
     */
    public function templateRelations()
    {
        return $this->hasMany(VoiceTemplateKeywordRelModel::class, 'keyword_id', 'keyword_id');
    }
    
    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        return $data['status'] ? '启用' : '禁用';
    }
    
    /**
     * 获取关键词类型文本
     */
    public function getKeywordTypeTextAttr($value, $data)
    {
        $types = [
            self::KEYWORD_TYPE_NORMAL => '普通',
            self::KEYWORD_TYPE_SYNONYM => '同义词',
            self::KEYWORD_TYPE_ALIAS => '别名',
        ];
        return $types[$data['keyword_type']] ?? '未知';
    }
    
    /**
     * 获取关键词列表
     */
    public static function getKeywordList($appId = null, $status = null, $keywordType = null)
    {
        $query = self::order('weight desc, create_time desc');
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        if ($status !== null) {
            $query->where('status', $status);
        }
        
        if ($keywordType) {
            $query->where('keyword_type', $keywordType);
        }
        
        return $query->select();
    }
    
    /**
     * 根据文本搜索关键词
     */
    public static function searchByText($text, $appId = null)
    {
        $query = self::where('keyword_text', 'like', '%' . $text . '%')
            ->where('status', self::STATUS_ENABLED)
            ->order('weight desc');
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        return $query->select();
    }
    
    /**
     * 获取启用的关键词
     */
    public static function getEnabledKeywords($appId = null)
    {
        $query = self::where('status', self::STATUS_ENABLED)
            ->order('weight desc, create_time desc');
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        return $query->select();
    }
    
    /**
     * 批量创建关键词
     */
    public static function createBatch($keywords, $appId, $keywordType = self::KEYWORD_TYPE_NORMAL, $weight = 1)
    {
        $data = [];
        foreach ($keywords as $keyword) {
            $data[] = [
                'app_id' => $appId,
                'keyword_text' => $keyword,
                'keyword_type' => $keywordType,
                'weight' => $weight,
                'status' => self::STATUS_ENABLED,
                'create_time' => time(),
                'update_time' => time(),
            ];
        }
        
        return self::insertAll($data);
    }
}
