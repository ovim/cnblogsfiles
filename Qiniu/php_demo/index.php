<?php
namespace app\index\controller;

use operation\Qiniu;

class Index
{
    /**
     * 七牛云上传图片
     * @param $OBjimg 图片信息
     * @return false|string
     */
    public function UpImg($OBjimg)
    {
        //上传图片start
        $fileRoute = ROOT_PATH . 'upload' . DS . 'img';
        // 创建文件夹
        if (!file_exists($fileRoute)) {
            mkdir($fileRoute);
        }
        $imginfo = $OBjimg->move($fileRoute);
        $imgname = $imginfo->getSaveName();
        $image = '/upload' . DS . 'img'.DS. $imgname;

        $per_path = $fileRoute . DS . $imgname;//上传路径
        $pers = explode('\\', $imgname)[1];
        $qiniu = new QiNiu();
        $result = $qiniu->setQiniuUplaod($per_path, $pers);
        if ($result['code']) {
            unset($imginfo);
            unlink($per_path);
            $image = $result['path'];
        } else {
            $data['code'] = 0;
            $data['msg'] = '上传失败';
            return json_encode($data);
        }

        //上传图片end
        return $url = $image;  //上传七牛之后的图片url，直接存入数据库即可
    }

    /**
     * 七牛云删除多张图片
     * @return mixed
     */
    public function DelImg()
    {
        $ImgArr = [];
        foreach ($data as $url)   //$data 为查出来的图片路径 是一个数组
        {
            if(in_array('http',$url))  //判断是否为七牛云的图片
            {
                $img = strstr(strrev($url), '/',true);
                $ImgArr[] = strrev($img);
            }else{
                $path = './'.$val['url'];
                //删除
                if(file_exists($path)){
                    @unlink($path);
                    clearstatcache();
                }
            }
        }
        $qiniu = new Qiniu();
        return $result = $qiniu->delete($ImgArr);        //注意：$ImgArr 必须为一个数组
    }
}