<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 语音模板变量关联模型
 * Class VoiceTemplateVariableRelModel
 * @package app\common\model
 */
class VoiceTemplateVariableRelModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_shop_voice_template_variable_rel';
    
    // 指定主键
    protected $pk = 'id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = false; // 该表没有更新时间字段
    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'app_id' => 'integer',
        'template_id' => 'integer',
        'variable_id' => 'integer',
        'create_time' => 'integer',
    ];
    
    /**
     * 关联应用
     */
    public function app()
    {
        return $this->belongsTo(AppModel::class, 'app_id', 'app_id');
    }
    
    /**
     * 关联模板
     */
    public function template()
    {
        return $this->belongsTo(VoiceTemplateModel::class, 'template_id', 'template_id');
    }
    
    /**
     * 关联变量
     */
    public function variable()
    {
        return $this->belongsTo(VoiceVariableModel::class, 'variable_id', 'variable_id');
    }
    
    /**
     * 创建模板变量关联
     */
    public static function createRelation($templateId, $variableId, $appId)
    {
        // 检查是否已存在关联
        $exists = self::where('template_id', $templateId)
            ->where('variable_id', $variableId)
            ->find();
        
        if ($exists) {
            return $exists;
        }
        
        return self::create([
            'app_id' => $appId,
            'template_id' => $templateId,
            'variable_id' => $variableId,
            'create_time' => time(),
        ]);
    }
    
    /**
     * 批量创建模板变量关联
     */
    public static function createBatchRelations($templateId, $variableIds, $appId)
    {
        $data = [];
        foreach ($variableIds as $variableId) {
            // 检查是否已存在关联
            $exists = self::where('template_id', $templateId)
                ->where('variable_id', $variableId)
                ->find();
            
            if (!$exists) {
                $data[] = [
                    'app_id' => $appId,
                    'template_id' => $templateId,
                    'variable_id' => $variableId,
                    'create_time' => time(),
                ];
            }
        }
        
        if (!empty($data)) {
            return self::insertAll($data);
        }
        
        return true;
    }
    
    /**
     * 删除模板的所有变量关联
     */
    public static function deleteByTemplateId($templateId)
    {
        return self::where('template_id', $templateId)->delete();
    }
    
    /**
     * 删除变量的所有模板关联
     */
    public static function deleteByVariableId($variableId)
    {
        return self::where('variable_id', $variableId)->delete();
    }
    
    /**
     * 获取模板的变量ID列表
     */
    public static function getTemplateVariableIds($templateId)
    {
        return self::where('template_id', $templateId)
            ->column('variable_id');
    }
    
    /**
     * 获取变量的模板ID列表
     */
    public static function getVariableTemplateIds($variableId)
    {
        return self::where('variable_id', $variableId)
            ->column('template_id');
    }
    
    /**
     * 更新模板变量关联
     */
    public static function updateTemplateVariables($templateId, $variableIds, $appId)
    {
        // 先删除现有关联
        self::deleteByTemplateId($templateId);
        
        // 创建新关联
        if (!empty($variableIds)) {
            return self::createBatchRelations($templateId, $variableIds, $appId);
        }
        
        return true;
    }
    
    /**
     * 获取模板变量关联详情
     */
    public static function getTemplateVariableDetails($templateId)
    {
        return self::alias('r')
            ->join('jjjshop_shop_voice_variable v', 'r.variable_id = v.variable_id')
            ->where('r.template_id', $templateId)
            ->where('v.status', VoiceVariableModel::STATUS_ENABLED)
            ->field('v.*')
            ->order('v.create_time desc')
            ->select();
    }
    
    /**
     * 获取模板的所有变量值
     */
    public static function getTemplateVariableValues($templateId)
    {
        $variables = self::getTemplateVariableDetails($templateId);
        $values = [];
        
        foreach ($variables as $variable) {
            $values[$variable->variable_identifier] = $variable->variable_value;
        }
        
        return $values;
    }
}
