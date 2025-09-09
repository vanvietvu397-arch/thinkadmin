<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 语音变量模型
 * Class VoiceVariableModel
 * @package app\common\model
 */
class VoiceVariableModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_shop_voice_variable';
    
    // 指定主键
    protected $pk = 'variable_id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    
    // 日志类型
    protected $oplogType = '语音变量管理';
    
    // 日志名称
    protected $oplogName = '变量';
    
    // 字段类型转换
    protected $type = [
        'variable_id' => 'integer',
        'app_id' => 'integer',
        'variable_name' => 'string',
        'variable_identifier' => 'string',
        'variable_type' => 'string',
        'variable_value' => 'string',
        'url_value' => 'string',
        'description' => 'string',
        'status' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    // 变量类型常量
    const VARIABLE_TYPE_NORMAL = 'normal'; // 普通变量
    const VARIABLE_TYPE_URL = 'url';       // URL类型
    
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
        return $this->belongsToMany(VoiceTemplateModel::class, VoiceTemplateVariableRelModel::class, 'template_id', 'variable_id');
    }
    
    /**
     * 关联模板变量关系
     */
    public function templateRelations()
    {
        return $this->hasMany(VoiceTemplateVariableRelModel::class, 'variable_id', 'variable_id');
    }
    
    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        return $data['status'] ? '启用' : '禁用';
    }
    
    /**
     * 获取变量类型文本
     */
    public function getVariableTypeTextAttr($value, $data)
    {
        $types = [
            self::VARIABLE_TYPE_NORMAL => '普通变量',
            self::VARIABLE_TYPE_URL => 'URL类型',
        ];
        return $types[$data['variable_type']] ?? '未知';
    }
    
    /**
     * 获取变量值（根据类型返回不同的值）
     */
    public function getVariableValueAttr($value, $data)
    {
        if ($data['variable_type'] === self::VARIABLE_TYPE_URL) {
            return $data['url_value'] ?: $value;
        }
        return $value;
    }
    
    /**
     * 获取变量列表
     */
    public static function getVariableList($appId = null, $status = null, $variableType = null)
    {
        $query = self::order('create_time desc');
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        if ($status !== null) {
            $query->where('status', $status);
        }
        
        if ($variableType) {
            $query->where('variable_type', $variableType);
        }
        
        return $query->select();
    }
    
    /**
     * 根据标识符获取变量
     */
    public static function getByIdentifier($identifier, $appId = null)
    {
        $query = self::where('variable_identifier', $identifier)
            ->where('status', self::STATUS_ENABLED);
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        return $query->find();
    }
    
    /**
     * 根据标识符获取变量值
     */
    public static function getValueByIdentifier($identifier, $appId = null)
    {
        $variable = self::getByIdentifier($identifier, $appId);
        return $variable ? $variable->variable_value : null;
    }
    
    /**
     * 获取启用的变量
     */
    public static function getEnabledVariables($appId = null)
    {
        $query = self::where('status', self::STATUS_ENABLED)
            ->order('create_time desc');
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        return $query->select();
    }
    
    /**
     * 批量获取变量值
     */
    public static function getBatchValues($identifiers, $appId = null)
    {
        if (empty($identifiers)) {
            return [];
        }
        
        $query = self::whereIn('variable_identifier', $identifiers)
            ->where('status', self::STATUS_ENABLED);
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        $variables = $query->select();
        $result = [];
        
        foreach ($variables as $variable) {
            $result[$variable->variable_identifier] = $variable->variable_value;
        }
        
        return $result;
    }
    
    /**
     * 创建或更新变量
     */
    public static function createOrUpdate($identifier, $name, $value, $appId, $type = self::VARIABLE_TYPE_NORMAL, $description = '')
    {
        $variable = self::where('variable_identifier', $identifier)
            ->where('app_id', $appId)
            ->find();
        
        $data = [
            'app_id' => $appId,
            'variable_name' => $name,
            'variable_identifier' => $identifier,
            'variable_type' => $type,
            'variable_value' => $value,
            'description' => $description,
            'status' => self::STATUS_ENABLED,
            'update_time' => time(),
        ];
        
        if ($variable) {
            return $variable->save($data);
        } else {
            $data['create_time'] = time();
            return self::create($data);
        }
    }
}
