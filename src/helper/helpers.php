<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 藁城区创新网络电子商务中心 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/以获得更多细节。
// +----------------------------------------------------------------------

define('IA_ROOT', str_replace("\\", '/', dirname(dirname(__FILE__))));

use app\vote\controller\mobile\Common;
use think\facade\Db;
//返回json
function jsonErrCode($msg){
    $result = [
        'code' => 0,
        'msg' => $msg,
    ];
    echo json_encode($result);exit;
}
function jsonSucCode($msg,$data=""){
    $result = [
        'code' => 1,
        'msg' => $msg,
        'data'=>$data
    ];
    echo json_encode($result);exit;
}
/**
 * 获取默认公众号配置
 */
function getDefConfig(){
    $pid = input('pid',1);
    if(!empty($pid)){
        $get = Db::name('app')->alias('p')
            ->field('p.*,a.appid,a.appsecret,mchid,apikey')
            ->join('vote_weixin a ','a.pid=p.id')
            ->where(" p.id={$pid}")->find();
        return $get;
    }else{
        return false;
    }

}

/**
 * 获取公众号配置
 */
function getConfig(){
    $pid = input('pid');
    if(!empty($pid)){
        $get = Db::name('project')->alias('p')
            ->field('p.*,a.appid,a.appsecret,mchid,apikey')
            ->join('vote_weixin a ','a.pid=p.id')
            ->where(" p.id={$pid}")->find();
        return $get;
    }else{
        return false;
    }

}
/**
 * 原生sql
 */

function pdo_execute($sql){
    $mysqlHostname = env('database.hostname');
    $mysqlHostport = env('database.hostport');
    $mysqlUsername = env('database.username');
    $mysqlPassword = env('database.password');
    $dbname = env('database.database');
    try {

        $pdo = new PDO("mysql:host={$mysqlHostname};port={$mysqlHostport};dbname=$dbname", $mysqlUsername, $mysqlPassword, array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ));
        // 开始事务
        $pdo->beginTransaction();
        //新增
        $result = $pdo->exec($sql);
        $pdo->commit();// 提交事务
        return 'ok';
    }catch (PDOException $e){
        $pdo->rollback ();//回滚事务
        return $e->getMessage();
    }

}
//xml验证
function manifest_check($module_name, $manifest) {
    if(is_string($manifest)) {
        return error(1, '模块配置项定义错误, 具体错误内容为: <br />' . $manifest);
    }

    if(!isset($manifest['addons']['name']) || empty($manifest['addons']['name'])) {
        return error(1, '模块名称未定义,请检查xml文件. ');
    }
//    if(!isset($manifest['addons']['identifie']) || empty($manifest['addons']['identifie']) || !preg_match('/^[a-z][a-z\d_]+$/i', $manifest['addons']['identifie'])) {
//        return error(1, '模块标识符未定义或格式错误(仅支持字母和数字, 且只能以字母开头),请检查xml文件.');
//    }
//    if(strtolower($module_name) != strtolower($manifest['addons']['identifie'])) {
//        return error(1, '模块名称定义与模块路径名称定义不匹配,请检查xml文件. ');
//    }
    if(!isset($manifest['addons']['version']) || empty($manifest['addons']['version']) || !preg_match('/^[\d\.]+$/i', $manifest['addons']['version'])) {
        return error(1, '模块版本号未定义(仅支持数字和句点),请检查xml文件. ');
    }
    if(!isset($manifest['addons']['logo']) || empty($manifest['addons']['logo'])) {
        return error(1, '模块logo未定义,请检查xml文件. ');
    }
    if(!isset($manifest['addons']['author']) || empty($manifest['addons']['author'])) {
        return error(1, '模块作者未定义,请检查xml文件. ');
    }
    if(!isset($manifest['menu']) || empty($manifest['menu'])) {
        return error(1, '模块菜单项不存在,请检查xml文件. ');
    }
    if(!isset($manifest['install']) || empty($manifest['install'])) {
        return error(1, '安装sql不存在,请检查xml文件. ');
    }
    return error(0);
}
function ext_module_vitphp($modulename) {

    $filename =  base_path() . $modulename . '/manifest.xml';

    if (!file_exists($filename)) {
        return array();
    }
    $xml = file_get_contents($filename);

    return ext_module_vitphp_parse($xml);
}
function strexists($string, $find) {
    return !(strpos($string, $find) === FALSE);
}
function ext_module_vitphp_parse($xml) {
    if (!strexists($xml, '<vitphp')) {
        $xml = base64_decode($xml);
    }
    if (empty($xml)) {
        return array();
    }
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $root = $dom->getElementsByTagName('vitphp')->item(0);
    if (empty($root)) {
        return array();
    }
    $vcode = explode(',', $root->getAttribute('version'));
    $vitphp['versions'] = array();
    if (is_array($vcode)) {
        foreach ($vcode as $v) {
            $v = trim($v);
            if (!empty($v)) {
                $vitphp['versions'][] = $v;
            }
        }

        $vitphp['versions'] = array_unique($vitphp['versions']);
    }
    //获取安装标签
    $vitphp['install'] = $root->getElementsByTagName('install')->item(0)->textContent;
    //获取卸载标签
    $vitphp['uninstall'] = $root->getElementsByTagName('uninstall')->item(0)->textContent;
    //获取更新标签
    $vitphp['upgrade'] = $root->getElementsByTagName('upgrade')->item(0)->textContent;
    //获取addons 标签
    $addons = $root->getElementsByTagName('addons')->item(0);
    if (empty($addons)) {
        return array();
    }
    $vitphp['addons'] = array(
        'name' => trim($addons->getElementsByTagName('name')->item(0)->textContent),
//        'identifie' => trim($addons->getElementsByTagName('identifie')->item(0)->textContent),
        'version' => trim($addons->getElementsByTagName('version')->item(0)->textContent),
        'logo' => trim($addons->getElementsByTagName('logo')->item(0)->textContent),
        'author' => trim($addons->getElementsByTagName('author')->item(0)->textContent),
    );
    //获取menu 标签
    $menus = $root->getElementsByTagName('menu')->item(0);
    if (!empty($menus)) {
        $vitphp['menu'] = array();
        $vitphp['menu'] = _ext_module_vitphp_entries($menus);
    }

    return $vitphp;
}
//解析menu 子标签 获取属性
function _ext_module_vitphp_entries($elm) {
    $ret = array();
    if (!empty($elm)) {

        $entries = $elm->getElementsByTagName('id');

        for ($i = 0; $i < $entries->length; $i++) {
            $entry = $entries->item($i);
            $row = array(
                'name' => $entry->getAttribute('name'),
                'icon' => $entry->getAttribute('icon'),
                'url' => $entry->getAttribute('url')
            );
            if (!empty($row['name']) && !empty($row['url'])) {
                $ret[] = $row;
            }
        }
    }
    return $ret;
}
//获取用户信息
function getVoteUserInfo(){
    $common = new Common();
    $param = $common->getUserInfo();
    return $param;
}
function createVoteUrl($path,$params = array()){

    $url = "http://tp.05v.cn/".$path;
    $pid = input('pid');

    if(!empty($pid)){
        $url .= "?pid={$pid}&";
    }else{
        $url .= "?pid=";
    }
    if (!empty($params)) {
        $queryString = createParameters($params, '', '&');

        $url .= $queryString;
    }
    return $url;

}
/**
 * @param $path
 * @param array $params
 * @return string|\think\route\Url
 */
function ToUrl($path,$params = array()){

    $url = url($path);
    $pid = input('pid');

    if(!empty($pid)){
        $url .= "?pid={$pid}&";
    }else{
        $url .= "?pid=";
    }
    if (!empty($params)) {
        $queryString = createParameters($params, '', '&');

        $url .= $queryString;
    }
    return $url;

}
if (!function_exists('createParameters')) {
    function createParameters($formdata, $numeric_prefix = null, $arg_separator = null) {
        if (!is_array($formdata))
            return false;
        if ($arg_separator == null)
            $arg_separator = '&';
        return parameters_recursive($formdata, $arg_separator);
    }
    function parameters_recursive($formdata, $separator, $key = '', $prefix = '') {
        $rlt = '';
        foreach ($formdata as $k => $v) {
            if (is_array($v)) {
                if ($key)
                    $rlt .= parameters_recursive($v, $separator, $key . '[' . $k . ']', $prefix);
                else
                    $rlt .= parameters_recursive($v, $separator, $k, $prefix);
            } else {
                if ($key)
                    $rlt .= $prefix . $key . '[' . urlencode($k) . ']=' . urldecode($v) . '&';
                else
                    $rlt .= $prefix . urldecode($k) . '=' . urldecode(strval($v)) . '&';
            }
        }
        return $rlt;
    }
}
function arrayToxml($arr, $level = 1) {
    $s = $level == 1 ? "<xml>" : '';
    foreach ($arr as $tagname => $value) {
        if (is_numeric($tagname)) {
            $tagname = $value['TagName'];
            unset($value['TagName']);
        }
        if (!is_array($value)) {
            $s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
        } else {
            $s .= "<{$tagname}>" . array2xml($value, $level + 1) . "</{$tagname}>";
        }
    }
    $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
    return $level == 1 ? $s . "</xml>" : $s;
}
//xml转换成数组
function xmlToArray($xml) {
    //禁止引用外部xml实体
    libxml_disable_entity_loader(true);
    $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    $val = json_decode(json_encode($xmlstring), true);
    return $val;
}
function postXmlCurl($xml, $url, $second = 30)
{
    $ch = curl_init();
    //设置超时
    curl_setopt($ch, CURLOPT_TIMEOUT, $second);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
    //设置header
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    //要求结果为字符串且输出到屏幕上
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    //post提交方式
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    set_time_limit(0);
    //运行curl
    $data = curl_exec($ch);
    //返回结果
    if ($data) {
        curl_close($ch);
        return $data;
    } else {
        $error = curl_errno($ch);
        curl_close($ch);
        throw new WxPayException("curl出错，错误码:$error");
    }
}

function error($errno, $message = '') {
    return array(
        'errno' => $errno,
        'message' => $message,
    );
}
function is_error($data) {
    if (empty($data) || !is_array($data) || !array_key_exists('errno', $data) || (array_key_exists('errno', $data) && $data['errno'] == 0)) {
        return false;
    } else {
        return true;
    }
}
/**
 * 获取ip地址
 * @return mixed|string
 */
function getip() {
    static $ip = '';
    $ip = $_SERVER['REMOTE_ADDR'];
    if(isset($_SERVER['HTTP_CDN_SRC_IP'])) {
        $ip = $_SERVER['HTTP_CDN_SRC_IP'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
        foreach ($matches[0] AS $xip) {
            if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
                $ip = $xip;
                break;
            }
        }
    }
    if (preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $ip)) {
        return $ip;
    } else {
        return '127.0.0.1';
    }
}
//随机32位字符串
if (!function_exists('createNoncestr')) {
    function createNoncestr($length = 32) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
}
/**
 * 保存访客记录
 * @param $param
 */
function setFans($param){
    $res = getFans($param['openid']);
    if($res){
        Db::name('fans')->where(['id'=>$res['id']])->update($param);
    }else{
        Db::name('fans')->insertGetId($param);
    }

}

/**
 * 获取访客记录
 * @param $openid
 * @return array|\think\Model|null
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\DbException
 * @throws \think\db\exception\ModelNotFoundException
 */
function getFans($openid,$wid=''){
    $res = Db::name('fans')->where(['openid'=>$openid])->find();
    return $res;
}
/**
 * 创建二维码
 */
function createQrcode($url){
 
    if($url){
        require  IA_ROOT.'/qrcode/phpqrcode.php';
        $errorCorrectionLevel = 'L';
        $matrixPointSize = '6';
        QRcode::png($url, false, $errorCorrectionLevel, $matrixPointSize);
        die;
    }

}
/**
 * 设置路由规则
 * @param $data
 */
function setVoteRoutIng($data){
    if($data){
        Db::name('routing')->insertGetId($data);
    }

}
/**
 * 保存设置
 * @param $name
 * @param string $value
 * @return int|string
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\DbException
 * @throws \think\db\exception\ModelNotFoundException
 */
  function setSetting($name,$value='',$addons=''){
    $data = [];
    $data = ['name' => $name, 'value' => $value, 'addons' => !empty($addons)?$addons:'setup'];
    $get = Db::name('settings')->where(['name'=>$data['name'],'addons'=>$data['addons']])->find();
    if($get){
       $res = Db::name('settings')->where(['id'=>$get['id']])->update($data);
    }else{
       $res =  Db::name('settings')->insert($data);
    }
    return $res;
 }

/**
 * 获取设置
 * @param $name
 * @return mixed
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\DbException
 * @throws \think\db\exception\ModelNotFoundException
 */
  function getSetting($name){
      
    $sett =  DB::name('settings')->where('1=1')->order('id desc ')->select();
    $data = [];
    if($sett->toArray()){
        $setting = $sett->toArray();
        foreach ($setting as $k=>$v){
            $data[$v['addons']][$v['name']] = $v['value'];
        }
        return isset($data['setup'][$name]) ?  $data['setup'][$name] : "";
    }else{
        return '';
    }


}
/**
 * 生成随机数
 * @param $leng 长度
 * @return bool|string
 */
function redom($leng){
    $randStr = str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz'.time());
    $rand = substr($randStr,0,$leng);
    return $rand;
}
function media($fileUrl){
    if(strpos($fileUrl, "http://") !== false
        || strpos($fileUrl, "https://") !== false
        || strpos($fileUrl, "//") !== false
    ){
        return $fileUrl;
    }else{
        return ROOT_PATH.$fileUrl;
    }

}
/**
 * 密码加密
 * @param $pass
 * @return false|string|null
 */
function pass_en($pass){
    $options =[
        "cost"=>config('admin.cost')
    ];

    return password_hash($pass,PASSWORD_DEFAULT, $options);
}

/**
 * 密码校验
 * @param $pass
 * @param $hash
 * @return bool
 */
function pass_compare($pass, $hash){
    return password_verify($pass, $hash);
}

/**
 * 唯一日期编码
 * @param integer $size
 * @param string $prefix
 * @return string
 */
  function uniqidDate($size = 16, $prefix = '')
{
    if ($size < 14) $size = 14;
    $string = $prefix . date('Ymd') . (date('H') + date('i')) . date('s');
    while (strlen($string) < $size) $string .= rand(0, 9);
    return $string;
}

/**
 * 获取日期编码
 *
 * @return string
 */
function getTDate()
{
    $string =  date('Ymd') . (date('H'));
    return $string;
}
/**
 * 权限校验
 * @param $path
 * @return mixed
 */
function auth($path){
    return \vitphp\admin\Auth::auth($path);
}