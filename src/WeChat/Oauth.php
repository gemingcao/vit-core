<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 藁城区创新网络电子商务中心 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/以获得更多细节。
// +----------------------------------------------------------------------

namespace vitphp\admin\WeChat;

use vitphp\admin\WeChat\Contracts\BasicWeChat;
use vitphp\admin\WeChat\Contracts\DataArray;
use vitphp\admin\WeChat\Contracts\Tools;
use vitphp\admin\WeChat\Exceptions\InvalidArgumentException;

/**
 * 微信网页授权
 * Class Oauth
 * @package WeChat
 */
class Oauth extends BasicWeChat
{
    /**
     * 获取当前页面完整URL地址
     */
    function get_url() {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    }
    /**
     * BasicWeChat constructor.
     * @param array $options
     */
    public function __construct(array $options)
    {

        if (empty($options['appid'])) {
            throw new InvalidArgumentException("Missing Config -- [appid]");
        }
        if (empty($options['appsecret'])) {
            throw new InvalidArgumentException("Missing Config -- [appsecret]");
        }
        if (isset($options['GetAccessTokenCallback']) && is_callable($options['GetAccessTokenCallback'])) {
            $this->GetAccessTokenCallback = $options['GetAccessTokenCallback'];
        }
        if (!empty($options['cache_path'])) {
            Tools::$cache_path = $options['cache_path'];
        }

        $this->config = new DataArray($options);
    }

    /**
     * 静态创建对象
     * @param array $config
     * @return static
     */
    public static function instance(array $config)
    {
        $key = md5(get_called_class() . serialize($config));
        if (isset(self::$cache[$key])) return self::$cache[$key];
        return self::$cache[$key] = new static($config);
    }
    /**
     * 第一步：用户同意授权，获取code
     * Oauth 授权跳转接口
     * @param string $redirect_url 授权回跳地址
     * @param string $state 为重定向后会带上state参数（填写a-zA-Z0-9的参数值，最多128字节）
     * @param string $scope 授权类类型(可选值snsapi_base|snsapi_userinfo)
     * @return string
     */
    public function getOauthRedirect($redirect_url, $state = '', $scope = 'snsapi_base')
    {

        $appid = $this->config->get('appid');
        $redirect_uri = urlencode($redirect_url);
        
        return "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appid}&redirect_uri={$redirect_uri}&response_type=code&scope={$scope}&state={$state}#wechat_redirect";
    }

    /**
     * 第二步：通过code换取网页授权access_token
     * 通过 code 获取 AccessToken 和 openid
     * @return bool|array
     * @throws Exceptions\InvalidResponseException
     * @throws Exceptions\LocalCacheException
     */
    public function getOauthAccessToken()
    {
        $appid = $this->config->get('appid');
        $appsecret = $this->config->get('appsecret');

        $code = isset($_GET['code']) ? $_GET['code'] : '';
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appid}&secret={$appsecret}&code={$code}&grant_type=authorization_code";
        return $this->httpGetForJson($url);
    }

    /**
     * 刷新AccessToken并续期
     * @param string $refresh_token
     * @return bool|array
     * @throws Exceptions\InvalidResponseException
     * @throws Exceptions\LocalCacheException
     */
    public function getOauthRefreshToken($refresh_token)
    {
        $appid = $this->config->get('appid');
        $url = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid={$appid}&grant_type=refresh_token&refresh_token={$refresh_token}";
        return $this->httpGetForJson($url);
    }

    /**
     * 检验授权凭证（access_token）是否有效
     * @param string $access_token 网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同
     * @param string $openid 用户的唯一标识
     * @return array
     * @throws Exceptions\InvalidResponseException
     * @throws Exceptions\LocalCacheException
     */
    public function checkOauthAccessToken($access_token, $openid)
    {
        $url = "https://api.weixin.qq.com/sns/auth?access_token={$access_token}&openid={$openid}";
        return $this->httpGetForJson($url);
    }

    /**
     * 第四步：拉取用户信息(需scope为 snsapi_userinfo)
     * 拉取用户信息(需scope为 snsapi_userinfo)
     * @param string $access_token 网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同
     * @param string $openid 用户的唯一标识
     * @param string $lang 返回国家地区语言版本，zh_CN 简体，zh_TW 繁体，en 英语
     * @return array
     * @throws Exceptions\InvalidResponseException
     * @throws Exceptions\LocalCacheException
     */
    public function getUserInfo($access_token, $openid, $lang = 'zh_CN')
    {
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token={$access_token}&openid={$openid}&lang={$lang}";
        return $this->httpGetForJson($url);
    }

}
