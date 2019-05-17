<?php
/**
 * Created by PhpStorm.
 * User: Wang YuLong
 * Date: 2019/4/15
 * Time: 14:57
 */

namespace app\admin\controller;

use think\Db;

class Excel  extends Permissions
{
    public function index(){
        return $this->fetch();
    }

    /**
     * Excel上传
     */
    public function up_excel(){
        $this->up($_FILES);
    }
    /**
     * Excel上传(创建新表)
     */
    public function up_excel_create(){
        //判断是否选择了要上传的表格
        /*if (empty($_FILES['myfile']['tmp_name'])) {
            return $this->error('您未选择表格');
        }*/
        $this->up_create($_FILES);
    }
    /**
     * Excel下载
     */
    public function down_excel(){
        $ExcelModel=new ExcelModel();
        $data=$ExcelModel->GetAllData();
        $table=['name','sex','iphone','email',"memo"];
        $name=['姓名','性别','手机号','邮箱','备注'];
        $this->down('测试Excel下载',$data,$name,$table);
    }
    /**
     * Excel下载
     * 变量  作用   数据类型
     * $xls 文件名 string
     * $data 需要打印的数据 array
     * $table 需要打印的信息在数据库中数据的字段名（顺序同下） array
     * $name Excel表格显示时的名字（顺序同上） array
     */
    public function down($xls,$data,$name,$table)
    {
        //调用类库,路径是基于vendor文件夹的
        Vendor('PHPExcel.PHPExcel');
        Vendor('PHPExcel.PHPExcel.Worksheet.Drawing');
        Vendor('PHPExcel.PHPExcel.Writer.Excel2007');
        $objExcel = new \PHPExcel();
        //set document Property
        $objWriter = \PHPExcel_IOFactory::createWriter($objExcel, 'Excel2007');

        $objActSheet = $objExcel->getActiveSheet();
        $key = ord("A");
        $letter =explode(',',"A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T");//列数，不够自己加
        $arrHeader = $name;
        //填充表头信息
        $lenth =  count($arrHeader);
        $objActSheet->setCellValue($letter[0]."1","序号");
        for($i = 0;$i < $lenth;$i++) {
            $objActSheet->setCellValue($letter[$i+1]."1","$arrHeader[$i]");
        };
        //填充表格信息
        //定义序号
        $j=1;
        foreach($data as $k=>$v){
            $k +=2;
            // 表格内容
            $t=0;
            $objActSheet->setCellValue($letter[0].$k,$j++);
            for($i=0;$i<count($name);$i++){
                $objActSheet->setCellValue($letter[$t+1].$k,$v[$table[$t++]]);
            }
            // 表格高度
            $objActSheet->getRowDimension($k)->setRowHeight(20);
        }
        //表格名字
        $outfile = $xls.date('Y-m-d H时m分s秒',time()).".xls";
        //其他配置信息
        ob_end_clean();
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header('Content-Disposition:inline;filename="'.$outfile.'"');
        header("Content-Transfer-Encoding: binary");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Pragma: no-cache");
        $objWriter->save('php://output');
    }
    /**
     * 将Excel数据导入数据库（列数不超过702列）
     * @param $file             需要导入的文件
     * @param $TableModel       需要保存的表的实例化模型
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     */
    private function up($file){
        //判断表格是否上传成功
        if (is_uploaded_file($file['file']['tmp_name'])) {
            //加载phpExcel的类
            Vendor('PHPExcel.PHPExcel');
            Vendor('PHPExcel.PHPExcel.Worksheet.Drawing');
            Vendor('PHPExcel.PHPExcel.Writer.Excel2007');
            //use excel2007 for 2007 format
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            //接收存在缓存中的excel表格
            $file_name = $file['file']['tmp_name'];
            //$filename可以是上传的表格，或者是指定的表格
            $objPHPExcel = $objReader->load($file_name);
            $sheet = $objPHPExcel->getSheet(0);
            $highestRow = $sheet->getHighestRow(); // 取得总行数
            $highestColumn = $sheet->getHighestColumn(); // 取得总列数号码
            $highestColumn=$this->getColumnNumber($highestColumn);//计算数字列数
            //循环读取第三行的表头信息（作为创建表格的备注）
            $memo_name=[];//储存表头（备注）
            for ($y=1;$y<=$highestColumn;$y++){
                $memo_name[$y]=$objPHPExcel->getActiveSheet()->getCell($this->getColumnCode($y)."2")->getValue();
            }
            //循环读取第三行的字段名信息
            $field_name=[];//储存字段
            for ($x=1;$x<=$highestColumn;$x++){
                $field_name[$x]=$objPHPExcel->getActiveSheet()->getCell($this->getColumnCode($x)."3")->getValue();
            }
            //读取第一行表名
            $table_name=$objPHPExcel->getActiveSheet()->getCell("B1")->getValue();
            //创建表格
            //字段信息
            $file_text="";
            for ($w=1;$w<=$highestColumn;$w++){
                $file_text.="`".$field_name[$w]."` varchar(255) DEFAULT NULL COMMENT '".$memo_name[$w]."',";
            }
            //创建数据库sql
            $sql="CREATE TABLE `".$table_name."` (`id` int(11) NOT NULL AUTO_INCREMENT COMMENT '编号',".$file_text."PRIMARY KEY (`id`),UNIQUE KEY `指标` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            //删除原先的数据库
            $delete_table_sql = "DROP TABLE ".$table_name;
            //读取excel表的数据表备注
            $table_name_value=$objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
            $create_time = time();
            //执行管理数据表的sql
            $sql_table = "INSERT INTO `tplay_table`(`id`, `tablename`,`tableinfo`, `create_time`, `update_time`) VALUES (null,  "."'$table_name'".','."'$table_name_value'".", "."$create_time".", NULL);";
            //j表示从哪一行开始读取  从第三行开始读取，因为第一行是表头，第二行是数据库字段
//            $list=[];//储存要导入的数据
            //循环行，读取数据
            $add_sql="";
            for($j=4;$j<=$highestRow;$j++)
            {
                //循环读取数据并储存
                $sql_value="";
//                $sql_value = "null,";
                for ($z=1;$z<=$highestColumn;$z++){
                    $list_one_data=$objPHPExcel->getActiveSheet()->getCell($this->getColumnCode($z).$j)->getValue();
                    //存储数据
                    $sql_value.="'$list_one_data',";
                }
                $sql_value=rtrim($sql_value,',');
                $add_sql.=",  (".$sql_value.")";
            }
            $add_sql = substr($add_sql,1);
            $field_name = implode(',',$field_name);
            //添加数据sql
            $add_sql="INSERT ignore INTO `$table_name`($field_name) VALUES $add_sql;"; //若重复数据可以添加，请添加数据库索引
            $drop = Db::execute($delete_table_sql);//删除原先的数据库
            if(!$drop){
                Db::execute($sql);//创建数据库sql
                $a = Db::execute($add_sql);//添加数据sql
                if($a)
                {
                    return $this->success('上传成功','admin/Excel/index');
                }
            }else{
                return $this->error('上传失败！');
            }
        }
    }
    /**
     * 将Excel数据导入数据库,建立新表（列数不超过702列）
     * @param $file             需要导入的文件
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     */
    private function up_create($file){

        //判断表格是否上传成功
        if (is_uploaded_file($file['file']['tmp_name'])) {
            //加载phpExcel的类
            Vendor('PHPExcel.PHPExcel');
            Vendor('PHPExcel.PHPExcel.Worksheet.Drawing');
            Vendor('PHPExcel.PHPExcel.Writer.Excel2007');
            //use excel2007 for 2007 format
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            //接收存在缓存中的excel表格
            $file_name = $file['file']['tmp_name'];
            //$filename可以是上传的表格，或者是指定的表格
            $objPHPExcel = $objReader->load($file_name);
            $sheet = $objPHPExcel->getSheet(0);
            $highestRow = $sheet->getHighestRow(); // 取得总行数
            $highestColumn = $sheet->getHighestColumn(); // 取得总列数号码
            $highestColumn=$this->getColumnNumber($highestColumn);//计算数字列数
            //循环读取第三行的表头信息（作为创建表格的备注）
            $memo_name=[];//储存表头（备注）
            for ($y=1;$y<=$highestColumn;$y++){
                $memo_name[$y]=$objPHPExcel->getActiveSheet()->getCell($this->getColumnCode($y)."2")->getValue();
            }
            //循环读取第三行的字段名信息
            $field_name=[];//储存字段
            for ($x=1;$x<=$highestColumn;$x++){
                $field_name[$x]=$objPHPExcel->getActiveSheet()->getCell($this->getColumnCode($x)."3")->getValue();
            }
            //读取第一行表名
            $table_name=$objPHPExcel->getActiveSheet()->getCell("B1")->getValue();
            //创建表格
            //字段信息
            $file_text="";
            for ($w=1;$w<=$highestColumn;$w++){
                $file_text.="`".$field_name[$w]."` varchar(255) DEFAULT NULL COMMENT '".$memo_name[$w]."',";
            }
            $sql="CREATE TABLE `".$table_name."` (`id` int(11) NOT NULL AUTO_INCREMENT COMMENT '编号',".$file_text."PRIMARY KEY (`id`),UNIQUE KEY `指标` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            //执行创建表格
            Db::execute($sql);
            //读取excel表的数据表备注
            $table_name_value=$objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
            $create_time = time();
            $sql_table = "INSERT INTO `tplay_table`(`id`, `tablename`,`tableinfo`, `create_time`, `update_time`) VALUES (null,  "."'$table_name'".','."'$table_name_value'".", "."$create_time".", NULL);";
          //执行管理数据表的sql
            Db::execute($sql_table);
            //j表示从哪一行开始读取  从第三行开始读取，因为第一行是表头，第二行是数据库字段
//            $list=[];//储存要导入的数据
            //循环行，读取数据
            $add_sql="";
            for($j=4;$j<=$highestRow;$j++)
            {
                //循环读取数据并储存
                $sql_value="";
//                $sql_value = "null,";
                for ($z=1;$z<=$highestColumn;$z++){
                    $list_one_data=$objPHPExcel->getActiveSheet()->getCell($this->getColumnCode($z).$j)->getValue();
                    //存储数据
                    $sql_value.="'$list_one_data',";
                }
                $sql_value=rtrim($sql_value,',');
                $add_sql.=",  (".$sql_value.")";
            }
            $add_sql = substr($add_sql,1);
            $field_name = implode(',',$field_name);
            $add_sql="INSERT ignore INTO `$table_name`($field_name) VALUES $add_sql;"; //若重复数据可以添加，请添加数据库索引
            $res=  Db::execute($add_sql);
            if(!$res){
                addlog();//写入日志
                return $this->error('上传失败！');
            }else{
                return $this->success('上传成功','admin/Excel/index');
            }
        }
    }
    /**
     * 根据数字列号  获取Excel列的字母代码（最多702列）
     * @param $highestColumn    第几列
     * @return mixed|string     对应列的字母代码
     */
    public function getColumnCode($highestColumn=1){
        $colum=['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        if ($highestColumn<27){
            $getColumn=$colum[$highestColumn-1];
        }else{
            $getColumn=$colum[floor($highestColumn%26==0?($highestColumn/26-1):$highestColumn/26)-1].$colum[$highestColumn%26-1==-1?25:$highestColumn%26-1==-1];
        }
        return  $getColumn;
    }
    /**
     * 根据字母获取Excel数字列数
     * @param string $code       字母代码
     * @return float|int          数字列数
     */
    public function getColumnNumber($code="A"){
        if (strlen($code)==1){
            return ord($code)-64;
        }else{
            return (ord(substr($code,0,1))-64)*26+ord(substr($code,1,1))-64;
        }
    }


}