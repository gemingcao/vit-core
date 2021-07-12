<?php
namespace vitphp\admin\auth;
use \ReflectionClass;
use \ReflectionMethod;
use think\facade\Db;
use vitphp\admin\model\SystemMenu;

/**
 * Parses the PHPDoc comments for metadata. Inspired by Documentor code base
 * @category   Framework
 * @package    restler
 * @subpackage helper
 * @author     Murray Picton <info@murraypicton.com>
 * @author     R.Arul Kumaran <arul@luracast.com>
 * @copyright  2010 Luracast
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @link       https://github.com/murraypicton/Doqumentor
 */
class DocParser
{
    private $params = array();

    function parse($doc = '')
    {
        if ($doc == '') {
            return $this->params;
        }
        // Get the comment
        if (preg_match('#^/\*\*(.*)\*/#s', $doc, $comment) === false)
            return $this->params;
        $comment = trim($comment [1]);
        // Get all the lines and strip the * from the first character
        if (preg_match_all('#^\s*\*(.*)#m', $comment, $lines) === false)
            return $this->params;
        $this->parseLines($lines [1]);
        return $this->params;
    }

    private function parseLines($lines)
    {
        $desc = [];
        foreach ($lines as $line) {
            $parsedLine = $this->parseLine($line); // Parse the line

            if ($parsedLine === false && !isset ($this->params ['description'])) {
                if (isset ($desc)) {
                    // Store the first line in the short description
                    $this->params ['description'] = implode(PHP_EOL, $desc);
                }
                $desc = array();
            } elseif ($parsedLine !== false) {
                $desc [] = $parsedLine; // Store the line in the long description
            }
        }
        $desc = implode(' ', $desc);
        if (!empty ($desc))
            $this->params ['long_description'] = $desc;
    }

    private function parseLine($line)
    {
        // trim the whitespace from the line
        $line = trim($line);

        if (empty ($line))
            return false; // Empty line

        if (strpos($line, '@') === 0) {
            if (strpos($line, ' ') > 0) {
                // Get the parameter name
                $param = substr($line, 1, strpos($line, ' ') - 1);
                $value = substr($line, strlen($param) + 2); // Get the value
            } else {
                $param = substr($line, 1);
                $value = '';
            }
            // Parse the line and return false if the parameter is valid
            if ($this->setParam($param, $value))
                return false;
        }

        return $line;
    }

    private function setParam($param, $value)
    {
        if ($param == 'param' || $param == 'return')
            $value = $this->formatParamOrReturn($value);
        if ($param == 'class')
            list ($param, $value) = $this->formatClass($value);

        if (empty ($this->params [$param])) {
            $this->params [$param] = $value;
        } else if ($param == 'param') {
            $arr = array(
                $this->params [$param],
                $value
            );
            $this->params [$param] = $arr;
        } else {
//            dump($value, $this->params[$param], $this->params);
//            echo "111";$value +
//            $this->params [$param] = $this->params [$param];
        }
        return true;
    }

    private function formatClass($value)
    {
        $r = preg_split("[\(|\)]", $value);
        if (is_array($r)) {
            $param = $r [0];
            parse_str($r [1], $value);
            foreach ($value as $key => $val) {
                $val = explode(',', $val);
                if (count($val) > 1)
                    $value [$key] = $val;
            }
        } else {
            $param = 'Unknown';
        }
        return array(
            $param,
            $value
        );
    }

    private function formatParamOrReturn($string)
    {
        $pos = strpos($string, ' ');

        $type = substr($string, 0, $pos);
        return '(' . $type . ')' . substr($string, $pos + 1);
    }
}
/**
 * 解析doc
 * 下面的DocParserFactory是对其的进一步封装，每次解析时，可以减少初始化DocParser的次数
 *
 * @param $php_doc_comment
 * @return array
 */
function parse_doc($php_doc_comment) {
    $p = new DocParser ();
    return $p->parse ( $php_doc_comment );
}
/**
 * Class DocParserFactory 解析doc
 *
 * @example
 *      DocParserFactory::getInstance()->parse($doc);
 */
class DocParserFactory{
    private static $p;
    private function __construct()
    {
    }
    public static function getInstance($new = true){
        if(self::$p == null || $new){
            self::$p = new DocParser ();
        }
        return self::$p;
    }

}


class AnnotationAuth{
    #  注解权限类
    public static function scandir($dir, $depth = false)
    {
        $files = array();
        $dirs = array();
        if ( $handle = opendir($dir) ) {
            while ( ($file = readdir($handle)) !== false )
            {
                if ( $file != ".." && $file != "." )
                {
                    if ( is_dir($dir . "/" . $file) )
                    {
                        $dirs[] = $file;
                        if($depth){
                            # 向下深度搜索
                            $files[$file] = self::scandir($dir . "/" . $file, $depth);
                        }
                    }
                    else
                    {
                        $files[] = $file;
                    }
                }
            }
            closedir($handle);
            return ['dirs'=>$dirs, 'files'=>$files];
        }
    }
    public static function getControllerAuth($class){
        $reflection  = new ReflectionClass($class);
        $doc22 = $reflection->getDocComment();
        $parase_result =  DocParserFactory::getInstance()->parse ($doc22);
//        $class_metadata = $parase_result;
        //获取类中的方法，设置获取public,protected类型方法
        $methods = $reflection->getMethods();
        $data = [];
        $sysMethods = [
            'initialize','__construct','validate','redirect','getResponseType','success','error','result',
            '_callback','_list','_form','_del','_change','_form_before','_index_list_before'
        ];

        foreach ($methods as $method) {
            if(substr($method->getName(),0,1) == '_') continue;
            if(in_array($method->getName(), $sysMethods)) continue;
            //获取方法的注释
            $doc = $method->getDocComment();
            //获取方法的类型
            $method_flag = $method->isProtected(); //还可能是public,protected类型的

            if($doc === false){
                $call = array(
                    'class'=>$class,
                    'name'=>$method->getName(),
                    'meta'=>[
                        'name'=>'',
                        'auth'=>1,
                        'login'=>1
                    ],
                    'flag'=>$method_flag
                );
                $data[] = $call;
                continue;
            }
            //解析注释
            $metadata = DocParserFactory::getInstance()->parse($doc);
            $call = array(
                'class'=>$class,
                'name'=>$method->getName(),
                'meta'=>$metadata,
                'flag'=>$method_flag
            );
            $data[] = $call;
        }
        foreach ($data as $i=>$v){
            $v['_id'] = "{$v['class']}@{$v['name']}";
            $v['_cs'] = array_merge(['title'=>'','description'=>''],$parase_result, [
                # 强制使用Name
                'name' => empty($parase_result['name']) ? '' : $parase_result['name'],
                'title'=> empty($parase_result['name']) ? '' : $parase_result['name'],
            ]);
            $data[$i] = $v;
        }
//        if($class === "app\index\controller\Menu"){
//            dump($parase_result);
//            dump($reflection->getMethods());
//            dump($methods);
//            dump($class);
//            dump($data);
//        }

        return $data;
    }
    public static function getAnnotationData($auth, $app){
        $_path = "app\\$app\controller\\";
        $_dir = str_replace($_path,"", $auth['class']);

        return [
            '_app'=>$app,
            '_id'  =>$auth['_id'],
            '_api' =>[$auth['class'],$auth['name']],
            // 方法@name
            'name' =>isset($auth['meta']['name']) ? $auth['meta']['name'] :  '',
            // 方法@title
            'title' =>isset($auth['meta']['name']) ? $auth['meta']['name'] :  '',
            'auth' =>isset($auth['meta']['auth']) ? $auth['meta']['auth'] :  '1',
            'login' =>isset($auth['meta']['login']) ? $auth['meta']['login'] : '1',
            'node'  =>"$app/".implode('.', explode('\\',$_dir))."/".$auth['name'],
            // 方法@class注解
            '_cs'   =>isset($auth['_cs']) ? $auth['_cs'] : []
        ];
    }
    public static $cacheData = [];
    public static function getPathAuth($controllerPath){
        if(!is_dir($controllerPath)){
            return [];
        }
        $data = [];
        foreach (self::scandir(root_path().$controllerPath, false)['files'] as $file){

            $className = basename($file,'.php');

            $classString = str_replace('/','\\',$controllerPath.DIRECTORY_SEPARATOR.$className);
            $classString = str_replace('\\\\','\\',$classString);

            if(class_exists($classString)){

                $data[] = self::getControllerAuth($classString);
            }
        }
        foreach (self::scandir(root_path().$controllerPath, false)['dirs'] as $dir){
            $mDir =  self::getPathAuth($controllerPath.$dir);
            $data[] = array_merge(...$mDir);
        }
        return $data;
    }
    public static function getControllerDirAuth($controllerPath, $app){
        $auths = self::getPathAuth($controllerPath);
        $auths = array_merge(...$auths);
        foreach ($auths as $i=>$auth){
            $auths[$i] = self::getAnnotationData($auth, $app);
        }
        return $auths;
    }
    public static function getAddonsAuth($addons = "*", $cr = true, $g = true){
        self::$cacheData = [];
        $dirs = self::scandir(root_path('app'),false)['dirs'];
        $ads = [];
        $list = explode(',',$addons);
        foreach ($dirs as $v){
            if($addons==="*" || in_array($v, $list)){
                $ads[] = $v;
            }
        }
        $apps = [];
        foreach ($ads as $dir){
            $data = self::getControllerDirAuth("app/{$dir}/controller/", $dir);

            if($cr){
                // 未定义筛选
                $new_data = [];
                foreach ($data as $i=>$v){
                    if(!empty($v['name'])){
                        $new_data[] = $v;
                    }
                }
                $data = $new_data;
            }
            foreach ($data as $i=>$v){
                $v['level'] = 3;

                if(empty($v['name'])){
                    $v['name'] = $v['_api'][1];
                }
                $v['title'] = empty($v['title']) ?  $v['name'] :  $v['title'];
                $data[$i] = $v;
            }

            if($g){
                // 聚类分组
                $new_data = [];
                $dir_data = [];
                foreach ($data as $i=>$v){
                    $_app = $v['_app'];
                    $_path = "app\\$_app\controller\\";
                    $_dir = str_replace($_path,"", $v['_api'][0]);
                    if(!isset($dir_data[$_dir])){
                        $dir_data[$_dir] = [
                            'path' =>$_dir,
                            'list' =>[],
                            'level'=>2,
                            'title'=>$v['_cs']['title'] ?: $_dir
                        ];
                    }
                    $dir_data[$_dir]['list'][] = $v;
                }
                $new_data = array_values($dir_data);
                foreach ($new_data as $i=>$v){
                    $v['list'] = self::arraySort($v['list'], 'name');
                    $new_data[$i] = $v;
                }
                $data = $new_data;
            }
            $data = self::arraySort($data, 'path');
            $apps[] = [
                'path' =>$dir,
                'level'=>1,
                'list' =>$data
            ];
        }
        $apps = self::arraySort($apps, 'path');
        return $apps;
    }
    public static function arraySort($array,$keys,$sort='asc')

    {
        $newArr = $valArr = array();

        foreach ($array as $key=>$value) {
            $valArr[$key] = isset($value[$keys]) ? $value[$keys] : '';
        }
        ($sort == 'asc') ? asort($valArr) : arsort($valArr);//先利用keys对数组排序，目的是把目标数组的key排好序
        reset($valArr); //指针指向数组第一个值
        foreach($valArr as $key=>$value) {
            $newArr[$key] = $array[$key];

        }
        return $newArr;
     }
    public static function checkAuth($class, $path){
        if(!class_exists($class)){
            return false;
        }
        $auths = self::getControllerAuth($class);
        // 找到当前的方法
        $activeAuth = [];
        foreach ($auths as $item){
            if($item['name'] == $path[2]){
                $app = $path[0];
                $item['node'] = "$app/".implode('.', explode('\\',$path[1]))."/".$item['name'];
                $activeAuth = $item;
                break;
            }
        }
        return $activeAuth;
    }

    public static function getMenu($userId, $app){
        $model = SystemMenu::order('sort', 'asc')
            ->where(['status'=>'1','type'=>$app])
            ->select();

        if($userId != 1){
            $role_nodes = Db::table('vit_auth_nodes')
                ->whereIn('rule_id',  Db::table('vit_auth_map')
                    ->where('admin_id', $userId)
                    ->column('role_id'))
                ->group('node')
                ->select()->column('node');
            $user_nodes = Db::table('vit_auth_nodes')
                ->where('uid', $userId)
                ->select()->column('node');
            $nodes = array_map(function ($d){
                return strtolower($d);
            },array_merge($role_nodes, $user_nodes));

            $menus = $model->toArray();
            $data = [];
            $s = AnnotationAuth::getAddonsAuth($app,false,false)[0]['list'];
            $nodes_codes = [];
            $nodes_keys = [];
            foreach ($s as $v){
                $node = strtolower($v['node']);
                $nodes_codes[] = $node;
                $nodes_keys[$node] = $v;
            }
            foreach ($menus as $v){
                $v['url'] = $app."/".$v['url'];
                if(isset($nodes_keys[$v['url']])){
                    $n = $nodes_keys[$v['url']];
                    if(!isset($n['auth'])){
                        $n['auth'] = 0;
                    }
                    if($n['auth'] === 1){
                        if(in_array(strtolower($v['url']), $nodes)){
                            $data[] = $v;
                            continue;
                        }else{
                            continue;
                        }
                    }
                }
                $data[] = $v;
            }
            $menus = $data;
        }else{
            $menus = $model->toArray();
        }
        foreach ($menus as $i=>$v){
            $v['url'] = url($app.'/'.strtolower($v['url']))->build();
            $menus[$i] = $v;
        }
        return $menus;
    }
}