<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 语音模板模型
 * Class VoiceTemplateModel
 * @package app\common\model
 */
class VoiceTemplateModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_shop_voice_template';
    
    // 指定主键
    protected $pk = 'template_id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    
    // 日志类型
    protected $oplogType = '语音模板管理';
    
    // 日志名称
    protected $oplogName = '语音模板';
    
    // 字段类型转换
    protected $type = [
        'template_id' => 'integer',
        'app_id' => 'integer',
        'template_name' => 'string',
        'template_type' => 'string',
        'rich_text_content' => 'string',
        'status' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    // 模板类型常量
    const TEMPLATE_TYPE_TEXT = 'text';       // 文本模板
    const TEMPLATE_TYPE_VOICE = 'voice';     // 语音模板
    const TEMPLATE_TYPE_IMAGE = 'image';     // 图片模板
    const TEMPLATE_TYPE_VIDEO = 'video';     // 视频模板
    
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
     * 关联关键词（多对多）
     */
    public function keywords()
    {
        return $this->belongsToMany(VoiceKeywordModel::class, VoiceTemplateKeywordRelModel::class, 'keyword_id', 'template_id');
    }
    
    /**
     * 关联变量（多对多）
     */
    public function variables()
    {
        return $this->belongsToMany(VoiceVariableModel::class, VoiceTemplateVariableRelModel::class, 'variable_id', 'template_id');
    }
    
    /**
     * 关联模板关键词关系
     */
    public function keywordRelations()
    {
        return $this->hasMany(VoiceTemplateKeywordRelModel::class, 'template_id', 'template_id');
    }
    
    /**
     * 关联模板变量关系
     */
    public function variableRelations()
    {
        return $this->hasMany(VoiceTemplateVariableRelModel::class, 'template_id', 'template_id');
    }
    
    /**
     * 关联对话记录
     */
    public function conversations()
    {
        return $this->hasMany(VoiceConversationModel::class, 'template_id', 'template_id');
    }
    
    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        return $data['status'] ? '启用' : '禁用';
    }
    
    /**
     * 获取模板类型文本
     */
    public function getTemplateTypeTextAttr($value, $data)
    {
        $types = [
            self::TEMPLATE_TYPE_TEXT => '文本',
            self::TEMPLATE_TYPE_VOICE => '语音',
            self::TEMPLATE_TYPE_IMAGE => '图片',
            self::TEMPLATE_TYPE_VIDEO => '视频',
        ];
        return $types[$data['template_type']] ?? '未知';
    }
    
    /**
     * 获取模板列表
     */
    public static function getTemplateList($appId = null, $status = null, $templateType = null)
    {
        $query = self::order('create_time desc');
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        if ($status !== null) {
            $query->where('status', $status);
        }
        
        if ($templateType) {
            $query->where('template_type', $templateType);
        }
        
        return $query->select();
    }
    
    /**
     * 根据名称搜索模板
     */
    public static function searchByName($name, $appId = null)
    {
        $query = self::where('template_name', 'like', '%' . $name . '%')
            ->where('status', self::STATUS_ENABLED)
            ->order('create_time desc');
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        return $query->select();
    }
    
    /**
     * 获取启用的模板
     */
    public static function getEnabledTemplates($appId = null)
    {
        $query = self::where('status', self::STATUS_ENABLED)
            ->order('create_time desc');
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        return $query->select();
    }
    
    /**
     * 根据关键词匹配模板
     */
    public static function matchByKeywords($keywords, $appId = null)
    {
        if (empty($keywords)) {
            return [];
        }
        
        $query = self::alias('t')
            ->join('jjjshop_shop_voice_template_keyword_rel tr', 't.template_id = tr.template_id')
            ->join('jjjshop_shop_voice_keyword k', 'tr.keyword_id = k.keyword_id')
            ->where('t.status', self::STATUS_ENABLED)
            ->where('k.status', VoiceKeywordModel::STATUS_ENABLED);
        
        if ($appId) {
            $query->where('t.app_id', $appId);
        }
        
        if (is_array($keywords)) {
            $query->whereIn('k.keyword_text', $keywords);
        } else {
            $query->where('k.keyword_text', $keywords);
        }
        
        return $query->group('t.template_id')
            ->order('k.weight desc')
            ->select();
    }
    
    /**
     * 处理模板变量
     */
    public function processVariables($variables = [])
    {
        $content = $this->rich_text_content;
        
        if (!empty($variables)) {
            foreach ($variables as $key => $value) {
                $content = str_replace('[' . $key . ']', $value, $content);
            }
        }
        
        return $content;
    }
}
