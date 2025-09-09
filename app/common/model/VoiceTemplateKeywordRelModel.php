<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 语音模板关键词关联模型
 * Class VoiceTemplateKeywordRelModel
 * @package app\common\model
 */
class VoiceTemplateKeywordRelModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_shop_voice_template_keyword_rel';
    
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
        'keyword_id' => 'integer',
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
     * 关联关键词
     */
    public function keyword()
    {
        return $this->belongsTo(VoiceKeywordModel::class, 'keyword_id', 'keyword_id');
    }
    
    /**
     * 创建模板关键词关联
     */
    public static function createRelation($templateId, $keywordId, $appId)
    {
        // 检查是否已存在关联
        $exists = self::where('template_id', $templateId)
            ->where('keyword_id', $keywordId)
            ->find();
        
        if ($exists) {
            return $exists;
        }
        
        return self::create([
            'app_id' => $appId,
            'template_id' => $templateId,
            'keyword_id' => $keywordId,
            'create_time' => time(),
        ]);
    }
    
    /**
     * 批量创建模板关键词关联
     */
    public static function createBatchRelations($templateId, $keywordIds, $appId)
    {
        $data = [];
        foreach ($keywordIds as $keywordId) {
            // 检查是否已存在关联
            $exists = self::where('template_id', $templateId)
                ->where('keyword_id', $keywordId)
                ->find();
            
            if (!$exists) {
                $data[] = [
                    'app_id' => $appId,
                    'template_id' => $templateId,
                    'keyword_id' => $keywordId,
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
     * 删除模板的所有关键词关联
     */
    public static function deleteByTemplateId($templateId)
    {
        return self::where('template_id', $templateId)->delete();
    }
    
    /**
     * 删除关键词的所有模板关联
     */
    public static function deleteByKeywordId($keywordId)
    {
        return self::where('keyword_id', $keywordId)->delete();
    }
    
    /**
     * 获取模板的关键词ID列表
     */
    public static function getTemplateKeywordIds($templateId)
    {
        return self::where('template_id', $templateId)
            ->column('keyword_id');
    }
    
    /**
     * 获取关键词的模板ID列表
     */
    public static function getKeywordTemplateIds($keywordId)
    {
        return self::where('keyword_id', $keywordId)
            ->column('template_id');
    }
    
    /**
     * 更新模板关键词关联
     */
    public static function updateTemplateKeywords($templateId, $keywordIds, $appId)
    {
        // 先删除现有关联
        self::deleteByTemplateId($templateId);
        
        // 创建新关联
        if (!empty($keywordIds)) {
            return self::createBatchRelations($templateId, $keywordIds, $appId);
        }
        
        return true;
    }
    
    /**
     * 获取模板关键词关联详情
     */
    public static function getTemplateKeywordDetails($templateId)
    {
        return self::alias('r')
            ->join('jjjshop_shop_voice_keyword k', 'r.keyword_id = k.keyword_id')
            ->where('r.template_id', $templateId)
            ->where('k.status', VoiceKeywordModel::STATUS_ENABLED)
            ->field('k.*')
            ->order('k.weight desc')
            ->select();
    }
}
