<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 藁城区创新网络电子商务中心 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/以获得更多细节。
// +----------------------------------------------------------------------

namespace vitphp\admin;

use think\db\Builder;
use think\facade\Session;
use vitphp\admin\model\SystemAdmin;
use vitphp\admin\model\SystemAuthMap;
use vitphp\admin\model\SystemAuthNode;

/**
 * 权限验证类
 * Class Auth
 * @package LiteAdmin
 */
class Auth {

    /**
     * 执行验证
     * @param $path
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function auth($path) {

        $username = Session::get('admin.username');

        if ($username === "admin"){
            return true;
        }

        $admin_id = Session::get('admin.id');

        $node = SystemAuthNode::where('path',$path)->find();

        if (!$node || !in_array($node['auth'],[0,1,2])){
            return false;
//            halt('当前PATH（'.$path.'）没有加入权限管理列表');
        }
        switch (intval($node['auth'])){
            case 0:     // 免登录
                return true;
                break;
            case 1:     // 验证登录
                return !!$admin_id;
                break;
            case 2:     // 验证授权
                $access = self::getAllAcess();
                return in_array($node['id'],$access);
                break;
        }
        return false;
//        halt('当前PATH（'.$path.'出现了错误的授权代码');
    }

    /**
     * 获取当前用户全部权限 节点ID
     * @return array
     */
    public static function getAllAcess(){

        static $access;

        if (empty($access)){

            $admin_id = Session::get('admin.id');

            if (!$admin_id){
                return $access = [];
            }

            $admin = SystemAdmin::with('roles')
                ->where('id',$admin_id)
                ->find();

            $access = [];

            foreach ($admin->roles as $role) {
                if ($role['status'] !== 1){
                    continue;
                }
                $access = array_merge($access,explode(',',$role['access_list']));
            }

            $access = array_unique($access);
        }

        return $access;
    }
}