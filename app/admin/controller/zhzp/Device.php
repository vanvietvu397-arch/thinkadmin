<?php

// +----------------------------------------------------------------------
// | Admin Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2025 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/think-plugs-admin
// | github 代码仓库：https://github.com/zoujingli/think-plugs-admin
// +----------------------------------------------------------------------

namespace app\admin\controller\zhzp;

use think\admin\Controller;
use think\admin\helper\QueryHelper;
use app\common\model\Devices;
use app\common\model\DeviceClassify;
use app\common\model\DeviceGroup;
use app\common\model\DeviceInstruct;
use app\common\model\DeviceInstructMiddle;
use app\common\model\App;
use app\common\model\ShopUser;
use app\common\model\Supplier;
use app\common\service\DeviceService;

/**
 * 设备管理
 * @class DeviceController
 * @package app\admin\controller\zhzp
 */
class Device extends Controller
{
    /**
     * 设备管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->type = $this->get['type'] ?? 'index';
        Devices::mQuery()->layTable(function () {
            $this->title = '设备管理';
            $this->classifies = DeviceClassify::getEnabledList();
            $this->groups = DeviceGroup::getEnabledList();
        }, function (QueryHelper $query) {
            // 加载对应数据列表
            if ($this->type === 'index') {
                $query->where(['is_delete' => 0]);
            } else {
                $query->where(['is_delete' => 1]);
            }
            
            // 搜索条件
            $query->like('device_name,device_number,device_name');
            $query->equal('classify_id,group_id,spec,xiaozhi_status,enable_xiaozhi');
            $query->dateBetween('create_time');
            
            // 关联查询
            $query->with(['classify', 'group']);
        });
    }

    /**
     * 添加设备
     * @auth true
     * @menu false
     */
    public function add()
    {
        Devices::mForm('form');
    }

    /**
     * 编辑设备
     * @auth true
     * @menu false
     */
    public function edit()
    {
        Devices::mForm('form');
    }

    /**
     * 表单数据处理
     * @param array $data
     * @throws \think\db\exception\DbException
     */
    protected function _form_filter(&$data)
    {
        if ($this->request->isGet()) {
            // 获取应用数据
            $this->apps = App::getEnabledList();
            
            // 获取分类、分组和指令数据
            $this->classifies = DeviceClassify::getEnabledList();
            $this->groups = DeviceGroup::getEnabledList();
            $this->instructs = DeviceInstruct::getEnabledList();
            
            // 如果是编辑，获取设备已关联的指令和相关数据
            if (isset($data['id']) && $data['id']) {
                $deviceInstructs = DeviceInstructMiddle::getDeviceInstructs($data['id']);
                $this->deviceInstructs = array_column($deviceInstructs->toArray(), 'instruct_id');
                
                // 获取供应商数据
                if (!empty($data['app_id'])) {
                    $this->suppliers = Supplier::getSupplierList($data['app_id']);
                }
            }
        }
        
        if ($this->request->isPost()) {
            // 验证必填字段
            if (empty($data['device_name'])) {
                $this->error('设备名称不能为空');
            }
            if (empty($data['device_number'])) {
                $this->error('设备编号不能为空');
            }
            
            // 检查设备编号是否重复
            $where = [['device_number', '=', $data['device_number']], ['is_delete', '=', 0]];
            if (!empty($data['id'])) {
                $where[] = ['id', '<>', $data['id']];
            }
            if (Devices::where($where)->find()) {
                $this->error('设备编号已存在');
            }
            
            // 处理小智AI配置
            if (isset($data['enable_xiaozhi']) && $data['enable_xiaozhi']) {
                if (empty($data['xiaozhi_mcp_url'])) {
                    $this->error('启用小智AI时，MCP地址不能为空');
                }
            } else {
                $data['xiaozhi_mcp_url'] = '';
                $data['xiaozhi_status'] = 'disconnected';
            }
            
            // 设置默认值
            $data['shop_supplier_id'] = $data['shop_supplier_id'] ?? 1;
            $data['app_id'] = $data['app_id'] ?? 10001;
            $data['status'] = $data['status'] ?? 1;
            $data['spec'] = $data['spec'] ?? 1;
            $data['enable_xiaozhi'] = $data['enable_xiaozhi'] ?? 0;
        }
    }

    /**
     * 表单结果处理
     * @param boolean $result
     * @param array $data
     * @throws \think\db\exception\DbException
     */
    protected function _form_result($result, $data)
    {
        if ($result && $this->request->isPost()) {
            // 处理设备指令关联
            $instructIds = $this->request->param('instruct_ids', []);
            if (!empty($instructIds)) {
                DeviceInstructMiddle::setDeviceInstructs(
                    $data['id'],
                    $instructIds,
                    $data['shop_supplier_id'],
                    $data['app_id']
                );
            }
        }
    }


    /**
     * 删除设备
     * @auth true
     * @menu false
     */
    public function remove()
    {
        $ids = $this->request->post('id');
        if (empty($ids)) {
            $this->error('请选择要删除的设备');
        }
        
        // 处理ID数组
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        
        // 执行软删除，将 is_delete 设置为 1
        $result = Devices::whereIn('id', $ids)->update(['is_delete' => 1]);
        
        if ($result) {
            $this->success('设备删除成功');
        } else {
            $this->error('设备删除失败');
        }
    }

    /**
     * 禁用设备
     * @auth true
     * @menu false
     */
    public function forbid()
    {
        Devices::mSave();
    }

    /**
     * 启用设备
     * @auth true
     * @menu false
     */
    public function resume()
    {
        Devices::mSave();
    }

    /**
     * 设备状态切换
     * @auth true
     * @menu false
     */
    public function state()
    {
        Devices::mSave();
    }

    /**
     * 设备详情
     * @auth true
     * @menu false
     */
    public function detail()
    {
        $id = $this->request->param('id');
        if (empty($id)) {
            $this->error('设备ID不能为空');
        }

        $device = Devices::with(['classify', 'group'])
                       ->where('id', $id)
                       ->where('is_delete', 0)
                       ->find();

        if (!$device) {
            $this->error('设备不存在');
        }

        // 获取设备指令
        $deviceInstructs = DeviceInstructMiddle::getDeviceInstructs($id);

        // 获取设备日志
        $deviceLogs = DeviceService::getDeviceLogs($id, 10);

        // 获取设备执行统计
        $executedStats = DeviceService::getDeviceExecutedStats($id);

        $this->device = $device;
        $this->deviceInstructs = $deviceInstructs;
        $this->deviceLogs = $deviceLogs;
        $this->executedStats = $executedStats;
        $this->title = '设备详情 - ' . $device->device_name;
        $this->fetch('detail');
        
    }

    /**
     * 发送指令
     * @auth true
     * @menu false
     */
    public function sendInstruct()
    {
        if ($this->request->isPost()) {
            $deviceId = $this->request->param('device_id');
            $instructId = $this->request->param('instruct_id');
            
            if (empty($deviceId) || empty($instructId)) {
                $this->error('设备ID和指令ID不能为空');
            }
            
            $device = Devices::where('id', $deviceId)->where('is_delete', 0)->find();
            if (!$device) {
                $this->error('设备不存在');
            }
            
            if ($device->status != 1) {
                $this->error('设备已禁用，无法发送指令');
            }
            
            $result = DeviceService::sendDeviceInstruct($deviceId, $instructId, $device->shop_supplier_id, $device->app_id);
            
            if ($result) {
                $this->success('指令发送成功');
            } else {
                $this->error('指令发送失败');
            }
        }
    }

    /**
     * 获取商家用户列表
     * @auth true
     * @menu false
     */
    public function getShopUsers()
    {
        $appId = $this->request->param('app_id');
        if (empty($appId)) {
            $this->error('应用ID不能为空');
        }
        
        $users = ShopUser::getUserList($appId);
        $this->success('获取成功', $users);
    }

    /**
     * 获取供应商列表
     * @auth true
     * @menu false
     */
    public function getSuppliers()
    {
        $appId = $this->request->param('app_id');
        if (empty($appId)) {
            $this->error('应用ID不能为空');
        }
        
        // 根据应用ID获取对应的供应商
        $suppliers = Supplier::where('app_id', $appId)
                            ->where('is_delete', 0)
                            ->field('shop_supplier_id,name')
                            ->select();
        
        $this->success('获取成功', $suppliers);
    }

    /**
     * 获取设备统计
     * @auth true
     * @menu false
     */
    public function stats()
    {
        $stats = DeviceService::getDeviceStats();
        $this->success('获取成功', $stats);
    }
}
