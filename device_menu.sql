-- 设备管理菜单配置
-- 请将此SQL导入到您的数据库中

-- 插入设备管理主菜单
INSERT INTO `system_menu` (`id`, `pid`, `title`, `icon`, `url`, `params`, `node`, `sort`, `status`, `create_time`, `update_time`) VALUES
(NULL, 0, '设备管理', 'layui-icon layui-icon-cellphone', '', '', '', 100, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 获取刚插入的主菜单ID（假设为 @device_menu_id）
SET @device_menu_id = LAST_INSERT_ID();

-- 插入设备列表子菜单
INSERT INTO `system_menu` (`id`, `pid`, `title`, `icon`, `url`, `params`, `node`, `sort`, `status`, `create_time`, `update_time`) VALUES
(NULL, @device_menu_id, '设备列表', 'layui-icon layui-icon-list', 'zhzp/device/index', '', 'zhzp/device/index', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 插入设备分类子菜单
INSERT INTO `system_menu` (`id`, `pid`, `title`, `icon`, `url`, `params`, `node`, `sort`, `status`, `create_time`, `update_time`) VALUES
(NULL, @device_menu_id, '设备分类', 'layui-icon layui-icon-template-1', 'zhzp/device/classify', '', 'zhzp/device/classify', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 插入设备分组子菜单
INSERT INTO `system_menu` (`id`, `pid`, `title`, `icon`, `url`, `params`, `node`, `sort`, `status`, `create_time`, `update_time`) VALUES
(NULL, @device_menu_id, '设备分组', 'layui-icon layui-icon-group', 'zhzp/device/group', '', 'zhzp/device/group', 3, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 插入设备指令子菜单
INSERT INTO `system_menu` (`id`, `pid`, `title`, `icon`, `url`, `params`, `node`, `sort`, `status`, `create_time`, `update_time`) VALUES
(NULL, @device_menu_id, '设备指令', 'layui-icon layui-icon-console', 'zhzp/device/instruct', '', 'zhzp/device/instruct', 4, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 插入设备推送子菜单
INSERT INTO `system_menu` (`id`, `pid`, `title`, `icon`, `url`, `params`, `node`, `sort`, `status`, `create_time`, `update_time`) VALUES
(NULL, @device_menu_id, '设备推送', 'layui-icon layui-icon-notice', 'zhzp/device/push', '', 'zhzp/device/push', 5, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 插入设备日志子菜单
INSERT INTO `system_menu` (`id`, `pid`, `title`, `icon`, `url`, `params`, `node`, `sort`, `status`, `create_time`, `update_time`) VALUES
(NULL, @device_menu_id, '设备日志', 'layui-icon layui-icon-log', 'zhzp/device/log', '', 'zhzp/device/log', 6, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 插入设备统计子菜单
INSERT INTO `system_menu` (`id`, `pid`, `title`, `icon`, `url`, `params`, `node`, `sort`, `status`, `create_time`, `update_time`) VALUES
(NULL, @device_menu_id, '设备统计', 'layui-icon layui-icon-chart', 'zhzp/device/stats', '', 'zhzp/device/stats', 7, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
