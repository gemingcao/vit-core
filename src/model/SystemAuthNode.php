<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 藁城区创新网络电子商务中心 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/以获得更多细节。
// +----------------------------------------------------------------------

namespace vitphp\admin\model;

use think\Model;

class SystemAuthNode extends Model
{
    protected $name = "auth_node";

    public function Admins()
    {
        return $this->belongsToMany(SystemAdmin::class,SystemAuthMap::class, 'admin_id','role_id');
    }
}