<?php
/**
 * Created by PhpStorm.
 * User: yangzhenyu
 * Date: 2019/1/14
 * Time: 09:55
 */

class Response
{
    protected $head = [];
    protected $status = 200;

    function setHead($keyOrStr,$val){
        if(empty($val)){
            $this->head[] = $keyOrStr;
        }else{
            $this->head[] = $keyOrStr.':'.$val."\r\n";
        }

    }
    function setStatus($status=200){
        $this->status = $status;
    }

    function send($str){
        $content =
            "HTTP/1.1 {$this->status} OK\r\n
            Server: ZeroCost-2x1.0.0\r\n
            Content-Length: " . strlen($str)."\r\n
            Content-type: application/json;charset=UTF-8\r\n";

        //head
        foreach ($this->head as $v){
            $content .= $v;
        }

        $content .=$str;
        return $content;
    }



}