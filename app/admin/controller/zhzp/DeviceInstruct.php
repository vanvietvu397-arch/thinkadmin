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
use app\common\model\DeviceInstructModel;
use app\common\model\AppModel;
use app\common\model\SupplierModel;

/**
 * 设备指令管理
 * @class DeviceInstruct
 * @package app\admin\controller\zhzp
 */
class DeviceInstruct extends Controller
{
    /**
     * 设备指令管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->type = $this->get['type'] ?? 'index';
        DeviceInstructModel::mQuery()->layTable(function () {
            $this->title = '设备指令管理';
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
            $query->like('instruct_name,instruct_code,instruct_desc#instruct_name');
            $query->equal('app_id,shop_supplier_id,status');
            $query->dateBetween('create_time');
            
            // 关联查询
            $query->with(['app', 'supplier']);
        });
    }

    /**
     * 添加设备指令
     * @auth true
     * @menu false
     */
    public function add()
    {
        DeviceInstructModel::mForm('form');
    }

    /**
     * 编辑设备指令
     * @auth true
     * @menu false
     */
    public function edit()
    {
        DeviceInstructModel::mForm('form');
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
            if (empty($data['instruct_name'])) {
                $this->error('指令名称不能为空');
            }
            if (empty($data['instruct_code'])) {
                $this->error('指令编码不能为空');
            }
            
            // 检查指令名称是否重复
            $where = [['instruct_name', '=', $data['instruct_name']], ['is_delete', '=', 0]];
            if (!empty($data['id'])) {
                $where[] = ['id', '<>', $data['id']];
            }
            if (DeviceInstructModel::where($where)->find()) {
                $this->error('指令名称已存在');
            }
            
            // 检查指令编码是否重复
            $where = [['instruct_code', '=', $data['instruct_code']], ['is_delete', '=', 0]];
            if (!empty($data['id'])) {
                $where[] = ['id', '<>', $data['id']];
            }
            if (DeviceInstructModel::where($where)->find()) {
                $this->error('指令编码已存在');
            }
            
            // 设置默认值
            $data['status'] = $data['status'] ?? 1;
            $data['app_id'] = $data['app_id'] ?? 10001;
            $data['shop_supplier_id'] = $data['shop_supplier_id'] ?? 1;
        }
    }

    /**
     * 删除设备指令
     * @auth true
     * @menu false
     */
    public function remove()
    {
        $ids = $this->request->post('id');
        if (empty($ids)) {
            $this->error('请选择要删除的指令');
        }
        
        // 处理ID数组
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        
        // 检查是否有设备使用该指令
        $deviceCount = \app\common\model\DeviceInstructMiddle::whereIn('instruct_id', $ids)->count();
        if ($deviceCount > 0) {
            $this->error('该指令下还有设备关联，无法删除');
        }
        
        // 执行软删除，将 is_delete 设置为 1
        $result = DeviceInstructModel::whereIn('id', $ids)->update(['is_delete' => 1]);
        
        if ($result) {
            $this->success('指令删除成功');
        } else {
            $this->error('指令删除失败');
        }
    }

    /**
     * 禁用设备指令
     * @auth true
     * @menu false
     */
    public function forbid()
    {
        DeviceInstructModel::mSave();
    }

    /**
     * 启用设备指令
     * @auth true
     * @menu false
     */
    public function resume()
    {
        DeviceInstructModel::mSave();
    }

    /**
     * 指令状态切换
     * @auth true
     * @menu false
     */
    public function state()
    {
        DeviceInstructModel::mSave();
    }

    /**
     * 指令详情
     * @auth true
     * @menu false
     */
    public function detail()
    {
        $id = $this->request->param('id');
        if (empty($id)) {
            $this->error('指令ID不能为空');
        }

        $instruct = DeviceInstructModel::with(['app', 'supplier'])
                                      ->where('id', $id)
                                      ->where('is_delete', 0)
                                      ->find();

        if (!$instruct) {
            $this->error('指令不存在');
        }

        // 获取指令关联的设备
        $devices = \app\common\model\DeviceInstructMiddle::where('instruct_id', $id)
                                                         ->with(['device.app', 'device.supplier'])
                                                         ->limit(10)
                                                         ->select();

        $this->instruct = $instruct;
        $this->devices = $devices;
        $this->title = '指令详情 - ' . $instruct->instruct_name;
        $this->fetch('detail');
    }

    /**
     * 获取指令列表（AJAX）
     * @auth true
     * @menu false
     */
    public function getInstructList()
    {
        $appId = $this->request->param('app_id');
        $supplierId = $this->request->param('shop_supplier_id');
        
        $query = DeviceInstructModel::where('is_delete', 0)->where('status', 1);
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        if ($supplierId) {
            $query->where('shop_supplier_id', $supplierId);
        }
        
        $list = $query->field('id,instruct_name,instruct_code')->select();
        
        $this->success('获取成功', $list);
    }
}
