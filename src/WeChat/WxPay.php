<?php


namespace vitphp\admin\WeChat;

use think\facade\Request;

define('TIMESTAMP', time());
class WxPay
{

    /*
     * 微信支付
     */
    function wechatpay($params,$setting){

        global $_W;
        $app = App('http')->getName();
        $domain = Request::domain();
        $pay_param = array();
        $pay_param['appid'] = trim($setting['appid']);
        $pay_param['mch_id'] = trim($setting['mch_id']);
        $pay_param['nonce_str'] = createNoncestr();
        $pay_param['body'] = $params['title']; //商品简单描述
        $pay_param['attach'] = '2';
        $pay_param['out_trade_no'] = $params['uniontid']; //商户订单号

        $pay_param['openid'] = $params['user'];
        $pay_param['total_fee'] = $params['fee'] * 100; //金额
        $pay_param['spbill_create_ip'] = getip();
        $pay_param['time_start'] = date('YmdHis', TIMESTAMP);
        $pay_param['time_expire'] = date('YmdHis', TIMESTAMP + 600);
        $pay_param['notify_url'] = $domain.'/'.$app.'/wePay/notify'; //异步通知地址
        $pay_param['trade_type'] = 'JSAPI'; //
        //生成签名
//         dump($pay_param);exit;

        ksort($pay_param, SORT_STRING);
        $string1 = '';
        foreach($pay_param as $key => $v) {
            if (empty($v)) {
                continue;
            }
            $string1 .= "{$key}={$v}&";
        }
        $string1 .= "key={$setting['wxapp_key']}";
        $pay_param['sign'] = strtoupper(md5($string1));
//dump($setting['wxapp_key']);exit;
        //转换为xml
        $date =  arrayToxml($pay_param);

        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

        $response =  postXmlCurl($date,$url);

        if (is_error($response)) {
            return $response;
        }
        //转换为数组
        $result =  xmlToArray($response);
        if($result['return_code'] == 'FAIL'){
            return  error(-1, strval($result['return_msg']));
        }
        if (strval($result['result_code']) == 'FAIL') {
            return  error(-1, strval($result['err_code']).': '.strval($result['err_code_des']));
        }

        $parameters = array(
            'appId' => $setting['appid'], //小程序ID
            'timeStamp' => '' . time() . '', //时间戳
            'nonceStr' =>  createNoncestr(), //随机串
            'package' => 'prepay_id=' . $result['prepay_id'], //数据包
            'signType' => 'MD5'//签名方式
        );
        //生成签名
        ksort($parameters, SORT_STRING);
        $string2 = '';
        foreach($parameters as $key => $v) {
            $string2 .= "{$key}={$v}&";
        }
        $string2 .= "key={$setting['wxapp_key']}";
        $parameters['paySign'] = strtoupper(md5($string2));
        return $parameters;

    }

}