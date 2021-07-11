<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 藁城区创新网络电子商务中心 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/以获得更多细节。
// +----------------------------------------------------------------------

namespace vitphp\admin\WeChat;
use think\facade\Request;
use vitphp\admin\controller\BaseController;
use vitphp\admin\WeChat\Oauth;
use think\facade\Session;
use think\facade\Db;

use think\facade\Cookie;
 


class WeUserInfo extends BaseController
{ 
    public $config = [];

    public function __construct(array $options)
    {
        $this->config = $options;
    }


    /**
     * 获取微信信息
     * @param string $scope
     * @param string $expired
     * @param string $url 借权域名
     * @param string $p_url 跳转的炮灰域名
     * @return array|mixed
     * @throws Exceptions\InvalidResponseException
     * @throws Exceptions\LocalCacheException
     */
    public function getWxInfo($scope = 'snsapi_base',$expired='600',$url='',$p_url=''){

        //获取当前页面地址
        $domain =  Oauth::instance($this->config)->get_url();
        //获取参数
        $params = request()->query();

        //重定向地址（跳转的原地址）
        if($p_url){
            if($params){
                $p_url = $p_url."?".$params;
            }
            $forWard = $p_url;
        }else{
            $forWard = $domain;

        }

        //判断是否借权 ---------$redirectUrl 重定向域名，就是获取微信code时的域名
        if($url){
            $redirectUrl = "http://".$url.'/index/api.wxredirect?pid='.input('pid');
        }else{
            $redirectUrl = $domain;
        }
        //获取sessionid;
        $session_id = Session::getId();
        Session::setId($session_id);
        //手动初始化session
        Session::init([
            'prefix'         => 'module',
            'type'           => '',
            'auto_start'     => true,
        ]);
       //获取重定向传回的sessionid
        $sid = input('sid');
        
        //回调重定向去掉sid
        if($sid){
            Session::setId($sid);
            //手动初始化session
            Session::init([
                'prefix'         => 'module',
                'type'           => '',
                'auto_start'     => true,
            ]);
            $resdomain =  request()->url(true);
//            dump($domain); exit;
            if(strpos($resdomain,'&sid=') !== false){
                $url = explode('&sid=', $resdomain);
                $resdomain = $url[0];
            }
            if(strpos($resdomain,'?sid=') !== false){
                $url = explode('?sid=', $resdomain);
                $resdomain = $url[0];
            }
            
            $this->redirect($resdomain);
        }

        $wxuser = Session::get($this->config['appid']."_userInfo");

        //把授权成功后的重定向地址存入缓存
        if(strpos($forWard,'&code=') !== false){
            $url = explode('&code=', $forWard);
            $forWard = $url[0];
        }
        //缓存重定向地址

        session($this->config['appid']."_".$session_id,$forWard);
        Session::save();
//        dump($this->config['appid']."_userInfo");  exit;
//        dump($wxuser);
        if(empty($wxuser)){
            //通过code获得openid 如果没有code参数，先获取code
            if (!isset($_GET['code'])){
                $url = Oauth::instance($this->config)->getOauthRedirect($redirectUrl,$session_id,'snsapi_userinfo');

                Header("Location: $url");
                exit();
            }else{

//以下是不借权使用------------------------------------------------------------------------------------------------------------------------
                //获取code码，以获取openid
                $tokenParam =  Oauth::instance($this->config)->getOauthAccessToken();

                //当调用出现错误，重新获取code
                if (!(empty($tokenParam['errcode'])) && (($tokenParam['errcode'] == '40029') || ($tokenParam['errcode'] == '40163')))
                {
                    $url = Oauth::instance($this->config)->getOauthRedirect($redirectUrl,$session_id,$scope);
                    Header("Location: $url");
                    exit();
                }
                //获取成功
                else{
                    //获取用户信息
                    $userInfo =  Oauth::instance($this->config)->getUserInfo($tokenParam['access_token'],$tokenParam['openid']);

                    //获取回调参数 sessionid
                    $session_id = input('state');

                    if($session_id){
                        Session::setId($session_id);
                        //手动初始化session
                        Session::init([
                            'prefix'         => 'module',
                            'type'           => '',
                            'auto_start'     => true,
                        ]);
                        Session::set($this->config['appid']."_userInfo", $userInfo);
                        Session::save();//保存session

                    }
                    $forwardUrl = session($this->config['appid']."_".$session_id);

                    //重定向跳转问题
//                    header('Location: ' . $forwardUrl);exit;

                    return $userInfo;
                }
            }
        }else{
            return $wxuser;
        }

    }
//    /**
//     * 获取openid
//     * @return array|bool
//     * @throws Exceptions\InvalidResponseException
//     * @throws Exceptions\LocalCacheException
//     */
//    public function getOpenid(){
//        $isToken = Session::has($this->config['appid']."_tokenParam");
//        //判断session 是否存在
//        if(!$isToken){
//            $tokenParam =  $this->getTokenAndOpenid();
//        }else{
//            $tokenParam =  session($this->config['appid']."_tokenParam");
//        }
//         return $tokenParam['openid'];
//    }

    /**
     * 获取用户信息
     */
//    public function getUserInfo($scope = 'snsapi_base'){
//        //判断用户信息是否存在
//        $tokenParam = $this->getTokenAndOpenid($scope);
//
//        $isUserInfo = Session::has($this->config['appid']."_userInfo");
//        if(!$isUserInfo){
//            $tokenParam = $this->getTokenAndOpenid();
//            $userInfo =  Oauth::instance($this->config)->getUserInfo($tokenParam['access_token'],$tokenParam['openid']);
//            //存入session
//            session($this->config['appid']."_userInfo",$userInfo);
//            isetcookie($this->config['appid']."_userInfo", $userInfo, $expired);
//        }else{
//            $userInfo =  session($this->config['appid']."_userInfo");
//
//        }
//        return $userInfo;
//    }


}