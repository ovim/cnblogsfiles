<?php
/**
 * 七牛上传文件
 * @author : liyuxi
 * @date : 2019.2.12
 * @version : v1.0.0.0
 */
namespace operation;
require_once 'extend/php_sdk/autoload.php';

use app\platform\model\ConfigModel;


use Qiniu\Auth as Auths;
use php_sdk\src\Qiniu\Storage\UploadManager;
use php_sdk\src\Qiniu\Storage\BucketManager;
/**
 * 功能说明：七牛云存储上传
 */

class Qiniu {

    //实例ID
    protected $instance;
    /***********************************************七牛云存储参数*******************************************/
    protected $Accesskey;          //用于签名的公钥
    protected $Secretkey;     //用于签名的私钥
    protected $Bucket;          //存储空间
    protected $Url;     //七牛用户自定义访问域名

    public function index(){
        //防止默认目录错误
    }
    /**
     * 七牛基本设置
     * @return unknown
     */
    public function getQiniuConfig(){
        /**
         * 从数据库中查出对应的七牛云参数并赋值
         */
        $Accesskey=ConfigModel::get(15)->value;
        $Secretkey=ConfigModel::get(16)->value;
        $Bucket=ConfigModel::get(17)->value;
        $QiniuUrl=ConfigModel::get(18)->value;
        //用于签名的公钥
        $qiniu_config['Accesskey']  = $Accesskey;
        //用于签名的私钥
        $qiniu_config['Secretkey']  = $Secretkey;
        //存储空间名称
        $qiniu_config['Bucket']     = $Bucket;
        //七牛用户自定义访问域名
        $qiniu_config['QiniuUrl']   = $QiniuUrl;
        return $qiniu_config;
    }

    /**
     * 设置七牛参数配置
     * @param unknown $filePath  上传图片路径
     * @param unknown $key 上传到七牛后保存的文件名
     */
    public function setQiniuUplaod($filePath, $key){
        $config = $this->getQiniuConfig();
        //Access Key 和 Secret Key
        $accessKey = $config["Accesskey"];
        $secretKey = $config["Secretkey"];
        //构建鉴权对象
        $auth = new Auths($accessKey, $secretKey);
        //要上传的空间
        $bucket = $config["Bucket"];
        $domain = "";
        $token = $auth->uploadToken($bucket);
        // 初始化 UploadManager 对象并进行文件的上传
        $uploadMgr = new UploadManager();
        // 调用 UploadManager 的 putFile 方法进行文件的上传
        list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
        if ($err !== null) {
            return ["code"=>false,"path"=>"","domain"=>"", "bucket"=>""];
        } else {
            //返回图片的完整URL
            return ["code"=>true,"path"=>$config['QiniuUrl']."/". $key,"domain"=>$config['QiniuUrl'], "bucket"=>$this->Bucket];
        }
    }

    /**
     * 删除七牛云图片
     * @param $name 七牛云的图片名称  例：img.jpg
     * @return mixed
     */
    public function delete($name)
    {
        $delFileName = $name;
        if( $delFileName ==null){
            echo "参数不正确";die;
        }
        // 配置
        $configs = $this->getQiniuConfig();
        //构建鉴权对象
        $auth = new Auths($configs["Accesskey"], $configs["Secretkey"]);
        $config = new \Qiniu\Config();
        // 管理资源
        $bucketManager = new \Qiniu\Storage\BucketManager($auth , $config);
        // 删除文件操作
        $ops = $bucketManager->buildBatchDelete($configs["Bucket"],$delFileName);
        list($ret, $err) = $bucketManager->batch($ops);
        //返回成功时$ret返回的数据内容为Array ( [0] => Array ( [code] => 200 ) )
        if ($err) {
            return $ret;
        } else {
            return $ret;
        }
    }

}
