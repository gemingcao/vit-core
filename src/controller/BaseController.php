<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 藁城区创新网络电子商务中心 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/以获得更多细节。
// +----------------------------------------------------------------------

namespace vitphp\admin\controller;
use think\facade\Db;
use app\BaseController as ThinkController;

use think\App;
use think\db\exception\PDOException;
use think\db\Query;
use think\db\Where;
use think\facade\Cache;
use think\facade\View;
use vitphp\admin\middleware\CheckAccess;
use vitphp\admin\model\SystemAuthNode;
use vitphp\admin\traits\Jump;

class BaseController extends ThinkController
{
    use Jump;

    protected $middleware = [
        CheckAccess::class
    ];

    /**
     * 初始化
     */
    protected function initialize()
    {


    }

    /**
     * 构造函数
     * BaseAdmin constructor.
     * @param App|null $app
     */
    public function __construct(App $app = null)
    {
        parent::__construct($app);
        // 面包屑数据
        $module = $this->app->http->getName();
        $controller = parse_name($this->request->controller(),0);
        $action = $this->request->action();

        $ctitie = Cache::remember("{$module}/{$controller}",function ()use($module,$controller){
            return SystemAuthNode::where('path',"{$module}/{$controller}")->value('title');
        },600);
        $atitie = Cache::remember("{$module}/{$controller}/{$action}",function () use ($module,$controller,$action){
            return SystemAuthNode::where('path',"{$module}/{$controller}/{$action}")->value('title');
        },600);

       View::assign(['ctitle'=>$ctitie,'atitle'=>$atitie]);

        // 当前控制器
        $classuri = $this->app->http->getName().'/'.$this->request->controller();
        $name = $this->app->http->getName();
        if($name != 'index'){
            $pid = input('pid');
            if(empty($pid)){
                $this->error("项目不存在");
            }
            $login_id = session('admin.id');
            $project = Db::name("app")->where(['id'=>$pid,'uid'=>$login_id])->find();

            if($project){
                if($login_id != 1){
                    $dq_time = $project['dq_time'];
                    if(time() < $dq_time){
                        $this->error("项目时间已到期");
                    }
                }
                $addons = $project['addons'];

                if($addons != $name){
                    $this->error("项目不匹配");
                }
                $menu = Db::name("menu")->where(['type'=>$name])->select()->toArray();
                $app = Db::name('app')->where(['uid'=>$login_id,'id'=>$pid])->find();
                if($menu){
                    foreach ($menu as $k=>$v){
                        $menu[$k]['pathUrl'] = url($v['type'].'/'.$v['url'].'?pid='.$pid);
                    }
                }
                View::assign(['menu'=>$menu,'app'=>$app]);
            }else{
                if($login_id){
                    $this->error("项目不存在!");
                }else{
                    $this->error('当前请求没有登录','index/login/index');

                }
            }

        }

        View::assign('classuri',$classuri);
    }

    /**
     * 万能列表方法
     * @param $query
     * @param bool $multipage
     * @param array $param
     * @return mixed
     */
    protected function _list($query,$multipage = true,$pageParam = [])
    {
        if ($this->request->isGet()){
            if ($multipage){
                $pageResult = $query->paginate(null,false,['query'=>$pageParam]);
                View::assign('page',$pageResult->render());
                $result = $pageResult->all();
            }else{
                $result = $query->select();
            }
            if (false !== $this->_callback('_list_before', $result, [])) {
               
                View::assign('list',$result);
                return View::fetch();
            }
            return $result;
        }
    }

    /**
     * 表单万能方法
     * @param $query
     * @param string $tpl
     * @param string $pk
     * @param array $where
     * @return array|mixed
     */
    protected function _form(Query $query, $tpl = '', $pk='', $where = []) {
        $pk = $pk?:($query->getPk()?:'id');
        $defaultPkValue = isset($where[$pk])?$where[$pk]:null;
        $pkValue = $this->request->get($pk,$defaultPkValue);

        if ($this->request->isGet()){
            $vo = ($pkValue !== null) ? $query->where($pk,$pkValue)->where(new Where($where))->find():[];
            if (false !== $this->_callback('_form_before', $vo)) {
                return View::fetch($tpl,['vo'=>$vo]);
            }
            return $vo;
        }
        $data = $this->request->post();
        if (false !== $this->_callback('_form_before', $data)) {
            try{
                if (isset($data[$pk])){
                    $where[$pk] = ['=',$data[$pk]];
                    $result = $query->where(new Where($where))->update($data);
                    $last_id = $data[$pk];
                }else{
                    $result = $query->insert($data);
                    $last_id = $query->getLastInsID();
                }
            }catch (PDOException $e){
                $this->error($e->getMessage());
            }
            //手动释放所有查询条件（此处TP有bug  导致数据库链接对象拿到错误的表名）
//            $query->removeOption();
            // 重置查询对象
            $query = $query->newQuery();
            $last_data = $query->find($last_id);
            if (false !== $this->_callback('_form_after',  $last_data)) {
                if ($result !== false) {
                    $this->success('恭喜, 数据保存成功!', '');
                }
                $this->error('数据保存失败, 请稍候再试!');
            }else{
                $this->error("表单后置操作失败，请检查数据！");
            }
        }
    }

    /**
     * @param $ids
     * @throws PDOException
     * @throws \think\Exception
     */
    protected function _del($query, $ids)
    {
        $fields = $query->getTableFields();
        if (in_array('is_deleted',$fields)){
            $res = $query->whereIn('id', $ids)
                ->update(['is_deleted' => 1]);
        }else{
            $res = $query->whereIn('id', $ids)
                ->delete();
        }
        if ($res) {
            $this->success('删除成功！', '');
        } else {
            $this->error("删除失败");
        }
    }

    protected function _change($query, $id, $data)
    {
        $res = $query->where('id', $id)->update($data);
        if ($res) {
            $this->success('切换状态操作成功！');
        } else {
            $this->error('切换状态操作失败！');
        }
    }

    /**
     * 回调唤起
     * @param $method
     * @param $data1
     * @param $data2
     * @return bool
     */
    protected function _callback($method, &$data)
    {
        foreach ([$method, "_" . $this->request->action() . "{$method}"] as $_method) {
            if (method_exists($this, $_method) && false === $this->$_method($data)) {
                return false;
            }
        }
        return true;
    }

}