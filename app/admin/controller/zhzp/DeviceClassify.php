<?php

// +----------------------------------------------------------------------
// | Admin Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2025 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// +----------------------------------------------------------------------

namespace app\admin\controller\zhzp;

use think\admin\Controller;
use think\admin\helper\QueryHelper;
use app\common\model\DeviceClassifyModel;
use app\common\model\AppModel;
use app\common\model\SupplierModel;

/**
 * 设备分类管理
 * @class DeviceClassifyModel
 * @package app\admin\controller\zhzp
 */
class DeviceClassify extends Controller
{
    /**
     * 设备分类管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->type = $this->get['type'] ?? 'index';
        DeviceClassifyModel::mQuery()->layTable(function () {
            $this->title = '设备分类管理';
            $this->apps = AppModel::getEnabledList();
            $this->suppliers = SupplierModel::getEnabledList();
        }, function (QueryHelper $query) {
            // 加载对应数据列表
            if ($this->type === 'index') {
                $query->where(['is_delete' => 0]);
            } else {
                $query->where(['is_delete' => 1]);
            }
            
            // 搜索条件
            $query->like('classify_name,classify_desc#classify_name');
            $query->equal('app_id,shop_supplier_id,status');
            $query->dateBetween('create_time');
            
            // 关联查询
            $query->with(['app', 'supplier']);
        });
    }

    /**
     * 添加设备分类
     * @auth true
     * @menu false
     */
    public function add()
    {
        DeviceClassifyModel::mForm('form');
    }

    /**
     * 编辑设备分类
     * @auth true
     * @menu false
     */
    public function edit()
    {
        DeviceClassifyModel::mForm('form');
    }

    /**
     * 表单数据处理
     * @param array $data
     * @throws \think\db\exception\DbException
     */
    protected function _form_filter(&$data)
    {
        if ($this->request->isGet()) {
            // 获取应用和供应商数据
            $this->apps = AppModel::getEnabledList();
            $this->suppliers = SupplierModel::getEnabledList();
        }
        
        if ($this->request->isPost()) {
            // 验证必填字段
            if (empty($data['classify_name'])) {
                $this->error('分类名称不能为空');
            }
            
            // 检查分类名称是否重复
            $where = [['classify_name', '=', $data['classify_name']], ['is_delete', '=', 0]];
            if (!empty($data['id'])) {
                $where[] = ['id', '<>', $data['id']];
            }
            if (DeviceClassifyModel::where($where)->find()) {
                $this->error('分类名称已存在');
            }
            
            // 设置默认值
            $data['status'] = $data['status'] ?? 1;
            $data['app_id'] = $data['app_id'] ?? 10001;
            $data['shop_supplier_id'] = $data['shop_supplier_id'] ?? 1;
        }
    }

    /**
     * 删除设备分类
     * @auth true
     * @menu false
     */
    public function remove()
    {
        $ids = $this->request->post('id');
        if (empty($ids)) {
            $this->error('请选择要删除的分类');
        }
        
        // 处理ID数组
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        
        // 检查是否有设备使用该分类
        $deviceCount = \app\common\model\DeviceModel::whereIn('classify_id', $ids)
                                               ->where('is_delete', 0)
                                               ->count();
        if ($deviceCount > 0) {
            $this->error('该分类下还有设备，无法删除');
        }
        
        // 执行软删除，将 is_delete 设置为 1
        $result = DeviceClassifyModel::whereIn('id', $ids)->update(['is_delete' => 1]);
        
        if ($result) {
            $this->success('分类删除成功');
        } else {
            $this->error('分类删除失败');
        }
    }

    /**
     * 禁用设备分类
     * @auth true
     * @menu false
     */
    public function forbid()
    {
        DeviceClassifyModel::mSave();
    }

    /**
     * 启用设备分类
     * @auth true
     * @menu false
     */
    public function resume()
    {
        DeviceClassifyModel::mSave();
    }

    /**
     * 分类状态切换
     * @auth true
     * @menu false
     */
    public function state()
    {
        DeviceClassifyModel::mSave();
    }

    /**
     * 分类详情
     * @auth true
     * @menu false
     */
    public function detail()
    {
        $id = $this->request->param('id');
        if (empty($id)) {
            $this->error('分类ID不能为空');
        }

        $classify = DeviceClassifyModel::with(['app', 'supplier'])
                                 ->where('id', $id)
                                 ->where('is_delete', 0)
                                 ->find();

        if (!$classify) {
            $this->error('分类不存在');
        }

        // 获取分类下的设备
        $devices = \app\common\model\DeviceModel::where('classify_id', $id)
                                           ->where('is_delete', 0)
                                           ->with(['app', 'supplier'])
                                           ->limit(10)
                                           ->select();

        $this->classify = $classify;
        $this->devices = $devices;
        $this->title = '分类详情 - ' . $classify->classify_name;
        $this->fetch('detail');
    }

    /**
     * 获取分类列表（AJAX）
     * @auth true
     * @menu false
     */
    public function getClassifyList()
    {
        $appId = $this->request->param('app_id');
        $supplierId = $this->request->param('shop_supplier_id');
        
        $query = DeviceClassifyModel::where('is_delete', 0)->where('status', 1);
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        if ($supplierId) {
            $query->where('shop_supplier_id', $supplierId);
        }
        
        $list = $query->field('id,classify_name')->select();
        
        $this->success('获取成功', $list);
    }
}
