<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [

    // +----------------------------------------------------------------------
    // | Wxpay 设置
    // +----------------------------------------------------------------------
    //'配置项'=>'配置值'
    //小程序的 APPID
    'appid' => 'wx0***75***ad9b1ee',
    //app_secret
    'app_secret' => '0a7xxxxxxxxxxxxxxxxxxxxxxxxxxxxx622',
    // 微信支付MCHID 商户收款账号
    'pay_mchid' => '15****3861',
    // 微信支付KEY
    'pay_apikey' => '1qaxxxxxxxxxxxxxxxxxxxxxhgf5',
    // 接收支付状态的连接
    'notify_url' => 'https://www.mySercver.com/api/Wxpay/notify',
    // 微信使用code换取用户openid及session_key的url地址-------------------不更改
    'login_url' => "https://api.weixin.qq.com/sns/jscode2session?" .
        "appid=%s&secret=%s&js_code=%s&grant_type=authorization_code",
];
