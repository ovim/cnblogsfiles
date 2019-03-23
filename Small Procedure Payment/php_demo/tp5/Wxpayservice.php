<?php
namespace app\index\controller;
/**
 * 关于微信退款的说明
 * 1.微信退款要求必传证书，需要到https://pay.weixin.qq.com 账户中心->账户设置->API安全->下载证书，证书路径在第190行和193行修改
 * 2.错误码参照 ：https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_4
 */
use think\Controller;
use think\db;
use app\index\model\Ticket;
use app\index\model\Userticket;  

header('Content-type:text/html; Charset=utf-8');

class Wxpayservice extends Controller
{
    protected $mchid;
    protected $appid;
    protected $apiKey;
    public $data = null;
    public function __construct($mchid, $appid, $key)
    {
        $this->mchid = $mchid; //https://pay.weixin.qq.com 产品中心-开发配置-商户号
        $this->appid = $appid; //微信支付申请对应的公众号的APPID
        $this->apiKey = $key;   //https://pay.weixin.qq.com 帐户设置-安全设置-API安全-API密钥-设置API密钥
    }
    /**
     * 退款
     * @param float $totalFee 订单金额 单位元
     * @param float $refundFee 退款金额 单位元
     * @param string $refundNo 退款单号
     * @param string $wxOrderNo 微信订单号
     * @param string $orderNo 商户订单号
     * @return string
     */
    public function doRefund($totalFee,$refundFee,$refundNo,$wxOrderNo='',$orderNo='')
    {
        $config = array(
            'mch_id' => $this->mchid,
            'appid' => $this->appid,
            'key' => $this->apiKey,
        );
       
        $unified = array(
            'appid' => $config['appid'],
            'mch_id' => $config['mch_id'],
            'nonce_str' => self::createNonceStr(),
            'total_fee' => $totalFee*100,       //订单金额	 单位 转为分
            'refund_fee' => $refundFee*100,       //退款金额 单位 转为分
            'sign_type' => 'MD5',           //签名类型 支持HMAC-SHA256和MD5，默认为MD5
            'transaction_id'=>$wxOrderNo,               //微信订单号
            //'out_trade_no'=>$orderNo,        //商户订单号
            'out_refund_no'=>$refundNo,        //商户退款单号
            'refund_desc'=>'订单退款',     //退款原因（选填）	
        );
        $unified['sign'] = self::getSign($unified, $config['key']);
        $responseXml = $this->curlPost('https://api.mch.weixin.qq.com/secapi/pay/refund', self::arrayToXml($unified));//申请退款
      
       	//$responseXml = $this->curlPost('https://api.mch.weixin.qq.com/pay/orderquery', self::arrayToXml($unified));//查询订单金额
      
     	 //print_r($responseXml);die;
      
        $unifiedOrder = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);
       // print_r($unifiedOrder);
        if ($unifiedOrder === false) {
          	return $unifiedOrder->err_code_des;
            //die('parse xml error');
        }
        if ($unifiedOrder->return_code != 'SUCCESS') {
          	//return 0;
          	return $unifiedOrder->err_code_des;
            die();
        }
        if ($unifiedOrder->result_code != 'SUCCESS') {
          	//return 0;
          	return $unifiedOrder->err_code_des;
            die();
        }
      if($unifiedOrder->return_code == 'SUCCESS' && $unifiedOrder->result_code == 'SUCCESS' )
      {
        Db::startTrans();
      	$myself_orderNo = $unifiedOrder->out_refund_no;   //微信返回的退款订单号（发起退款订单时的自定义的，已存入数据库）
      	$tuikuanqian = ($unifiedOrder->cash_refund_fee)/100;   //退给用户的金额
      	//订单退款完成后的操作
        $order = Db::name('refund')->where('out_refund_no',$myself_orderNo)->field('indentid')->find();  //根据返回的退款单号查出订单号
      	$ordersn=$order['indentid'];
      
      	$datetime = date('Y-m-d H:i:s'); 
      	$r[] = Db::name('refund')->where('indentid',$ordersn)->update(['stoptime'=>$datetime]);	//退款申请表
        //echo db('refund')->getLastSql();
      	$r[] = Db::name('indent')->where('dnumber',$ordersn)->update(['audit'=>0]);  //订单表
		
      	$indentuser = Db::name('indent')->where('dnumber',$ordersn)->field('userid')->find();//根据订单号查出用户id
		
      	$userid = $indentuser['userid'];
      	$r[] = Db::name('userwater')->insert(['userid'=>$userid,'style'=>0,'status'=>1,'amount'=>$tuikuanqian,'adopt'=>1,'comments'=>'订单退款至微信','times'=>$datetime]);//用户流水表

      	$noticeinfo = '交易成功，订单退款'.$tuikuanqian.'元';
      	$r[] = Db::name('notice')->insert(['txt'=>$noticeinfo,'type'=>2,'belongid'=>$userid,'create_time'=>$datetime]);//通知表
        
        
        
         // $indent = new Indent();
          $ticket = new Ticket();
          $userticket = new Userticket();

          
          $dataindent = Db::name('indent')->where('dnumber',$ordersn)->field('ticketid,dnumber')->find();
          $dataticket= $userticket->where('id',$dataindent['ticketid'])->find();
          if($dataindent['ticketid'] != 0){
          		 $arr = array_filter(explode(",",$dataticket['ids']));
                  if(array_search($dataindent['dnumber'],$arr) >= 0){
                    foreach($arr as $k => $v){
                      if($v == $dataindent['dnumber']){
                        unset($arr[$k]);
                      }
                    }
                  }
                  if(empty($arr))
                  {
                    $shixiao = $ticket->where('id',$dataticket['ticketid'])->find();
                    $data=[];
                    if($shixiao['stoptime'] < date('Y-m-d'))
                    {
                    	$data['status'] = 1; 
                    }else{
                    	$data['status'] = 0; 
                    } 
                    $data['ids']='';
                    $r[]=Db::name('userticket')->where('id',$dataindent['ticketid'])->update($data);
                  } else{
                     $str = implode(',',$arr);
                     $r[] = $userticket->where('id',$dataindent['ticketid'])->Update(['ids'=>$str]);
                  } 
          } 
        /*
        //根据订单号查出该订单是否使用优惠券
        $youhuiquan = Db::name('indent')->where('dnumber',$ordersn)->field('ticketid')->find();
        //如果该订单使用优惠券，更改优惠券状态
        if($youhuiquan['ticketid'] != 0)
        {
        	$ticket=Db::name('userticket')->where('id',$youhuiquan['ticketid'])->update(['status'=>0]);
          //echo db('usertickert')->getLastSql();
        }*/
        if(in_array(false,$r,true)){
        	Db::rollback();
          	return $unifiedOrder->err_code_des;
        }else{
			Db::commit();
          	return 1;
        }
      }
        
    }
    public static function curlGet($url = '', $options = array())
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    public function curlPost($url = '', $postData = '', $options = array())
    {
        if (is_array($postData)) {
            $postData = http_build_query($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //第一种方法，cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/cert/apiclient_cert.pem');
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY,getcwd().'/cert/apiclient_key.pem');
        //第二种方式，两个文件合成一个.pem文件
//        curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/all.pem');
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    public static function createNonceStr($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    public static function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
        $xml .= "</xml>";
        return $xml;
    }
    public static function getSign($params, $key)
    {
        ksort($params, SORT_STRING);
        $unSignParaString = self::formatQueryParaMap($params, false);
        $signStr = strtoupper(md5($unSignParaString . "&key=" . $key));
        return $signStr;
    }
    protected static function formatQueryParaMap($paraMap, $urlEncode = false)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if (null != $v && "null" != $v) {
                if ($urlEncode) {
                    $v = urlencode($v);
                }
                $buff .= $k . "=" . $v . "&";
            }
        }
        $reqPar = '';
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }
  
}
?>