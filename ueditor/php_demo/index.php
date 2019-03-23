<?php
namespace app\index\controller;

use think\Controller;

class Index extends Controller
{
    public function Add()
    {
        if(request()->isAjax())
        {
            return $content = request()->post("content");
            //如果不是将图片存入七牛，把接收到的 $content 直接存入数据库即可

        }else{
            return view();
        }
    }

    public function Mod()
    {
        if (request()->isAjax()) {
            $newContent = request()->post("content");
            /*
             * 思路：
             * 1、需要先查出数据库中原本的数据
             * 2、将原来的数据与新传过来的数据作对比 进行处理
             * */
            // $oldContent 为数据库里查出的原数据   $newContent 为新编辑的数据
            $content = $this->Contrast($oldContent,$newContent);
            //将content存入数据库即可

        } else {
            /*查出存在编辑器的内容并映射到视图*/
            return view();
        }
    }

    /**
     * 对比两个富文本编辑器中的内容
     * @param $oldContent 原数据
     * @param $newContent 新数据
     * 返回需要存入数据库的数据
     */
    public function Contrast($oldContent,$newContent)
    {
        $matches = $this->getContentimg($oldContent); //现在内容里有的图片start
        $contentimg = $this->getContentimg($newContent); //修改内容里有的图片start
        //删除部分图片start
        $DelImg = array_diff($matches, $contentimg);
        foreach ($DelImg as $val) {
            /*
             * $val 为图片路径
             * 执行删除图片的操作
             */
            $path = ROOT_PATH .$val;      //删除失败时注意查看路径是否正确 ========
            if(file_exists($path)){
                @unlink($path);
                clearstatcache();
            }
        }
        //删除部分图片end
        //将新增的图片加入到新内容中start
        $ed = array_diff($contentimg, $matches);
        foreach ($ed as $value) {  //$value即为图片名称
            $conpath = substr($value, 1);
            $per_path = ROOT_PATH . $conpath;//上传路径
            $pers = explode('/', $per_path)[5];//获取图片名
            $fileName = $per_path . '/' . $pers;
            $content = str_replace($value, $fileName, $oldContent);
        }
        //将新增的图片加入到新内容中end
        return $content;
    }

    /**
     * @param $content
     * @return 图片路径集合
     * 获取富文本编辑器内容里的图片路径
     */
    public function getContentimg($content){
        $content_01 = $content;//从数据库获取富文本content
        $content_02 = htmlspecialchars_decode($content_01);//把一些预定义的 HTML 实体转换为字符
        $content_03 = str_replace(" ","",$content_02);//将空格替换成空
        $contents = strip_tags($content_03);//函数剥去字符串中的 HTML、XML 以及 PHP 的标签,获取纯文本内容
        $insert['content'] = $contents;
        //拼装图片
        $imgs = $content_03;
        $imgs = strip_tags($imgs, '<img>');
        preg_match_all('/\<img\s+src\=\"([\w:\/\.]+)\"/', $imgs, $matches);  //$matches[1] 为图片路径数组
        return $matches[1];
    }

}
