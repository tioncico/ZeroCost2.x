<?php
/**
 * Created by PhpStorm.
 * User: yangzhenyu
 * Date: 2019/1/14
 * Time: 09:55
 */

class Request
{
    protected $req = [];
    protected $request = '';

    function getGet(){
        return $this->req[0];
    }
    function getPost(){
        return $this->req[1];
    }

    function getCookie(){
        return $this->req[2];
    }

    function getServer(){
        return $this->req[3];
    }

    function getFile(){
        return $this->req[4];
    }

    function getRequest(){
        return $this->req;
    }

    function setRequest($str){
        $this->request.=$str;
    }

    function isOver(){
        $list = explode("\r\n\r\n",$this->request);

        $len = explode('content-length:',$list[0]);

        $len = explode("\r\n",$len[1]);
        $len = intval($len[0]);
        $dataLen = 0;
        foreach ($list as $key =>$item) {
            if($key!=0){
                $dataLen+=strlen($item);
            }
        }
        $dataLen += (count($list)-2)*4;
        if($len<=$dataLen){
            return true;
        }
        else{
            return false;
        }
    }

    function gc(){
        $this->request = '';
        $this->req = [];
    }

    function _init()
    {
        $http = $this->request;
        // 初始化
        $_POST = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES =  array();
        $GLOBALS['HTTP_RAW_POST_DATA'] = '';
        // 需要设置的变量名
        $_SERVER = array (
            'QUERY_STRING' => '',
            'REQUEST_METHOD' => '',
            'REQUEST_URI' => '',
            'SERVER_PROTOCOL' => '',
            'SERVER_SOFTWARE' => '',
            'SERVER_NAME' => '',
            'HTTP_HOST' => '',
            'HTTP_USER_AGENT' => '',
            'HTTP_ACCEPT' => '',
            'HTTP_ACCEPT_LANGUAGE' => '',
            'HTTP_ACCEPT_ENCODING' => '',
            'HTTP_COOKIE' => '',
            'HTTP_CONNECTION' => '',
            'REMOTE_ADDR' => '',
            'REMOTE_PORT' => '0',
        );

        // 将header分割成数组
        list($http_header, $http_body) = explode("\r\n\r\n", $http, 2);

        $header_data = explode("\r\n", $http_header);

        list($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL']) = explode(' ', $header_data[0]);

        unset($header_data[0]);
        foreach($header_data as $content)
        {
            // \r\n\r\n
            if(empty($content))
            {
                continue;
            }
            list($key, $value) = explode(':', $content, 2);
            $key = strtolower($key);
            $value = trim($value);
            switch($key)
            {
                // HTTP_HOST
                case 'host':
                    $_SERVER['HTTP_HOST'] = $value;
                    $tmp = explode(':', $value);
                    $_SERVER['SERVER_NAME'] = $tmp[0];
                    if(isset($tmp[1]))
                    {
                        $_SERVER['SERVER_PORT'] = $tmp[1];
                    }
                    break;
                // cookie
                case 'cookie':
                    $_SERVER['HTTP_COOKIE'] = $value;
                    //将字符串解析成多个变量
                    parse_str(str_replace('; ', '&', $_SERVER['HTTP_COOKIE']), $_COOKIE);
                    break;
                // user-agent
                case 'user-agent':
                    $_SERVER['HTTP_USER_AGENT'] = $value;
                    break;
                // accept
                case 'accept':
                    $_SERVER['HTTP_ACCEPT'] = $value;
                    break;
                // accept-language
                case 'accept-language':
                    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $value;
                    break;
                // accept-encoding
                case 'accept-encoding':
                    $_SERVER['HTTP_ACCEPT_ENCODING'] = $value;
                    break;
                // connection
                case 'connection':
                    $_SERVER['HTTP_CONNECTION'] = $value;
                    break;
                case 'referer':
                    $_SERVER['HTTP_REFERER'] = $value;
                    break;
                case 'if-modified-since':
                    $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $value;
                    break;
                case 'if-none-match':
                    $_SERVER['HTTP_IF_NONE_MATCH'] = $value;
                    break;
                case 'content-type':
                    // 不同的请求类型：application/x-www-form-urlencoded application/json multipart/form-data text/xml
                    //demo: Content-Type:multipart/form-data; boundary=----WebKitFormBoundaryrGKCBY7qhFd3TrwA
                    if(!preg_match('/boundary="?(\S+)"?/', $value, $match))
                    {
                        $_SERVER['CONTENT_TYPE'] = $value;
                    }
                    else
                    {
                        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
                        $http_post_boundary = '--'.$match[1];
                    }
                    break;
            }
        }

        // 需要解析$_POST
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            //上传文件处理
            if(isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'multipart/form-data')
            {
                $this->upload_file($http_body, $http_post_boundary);
            }
            else
            {
                parse_str($http_body, $_POST);
                // $GLOBALS['HTTP_RAW_POST_DATA']
                $GLOBALS['HTTP_RAW_POST_DATA'] = $http_body;
            }
        }

        // QUERY_STRING
        $_SERVER['QUERY_STRING'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        if($_SERVER['QUERY_STRING'])
        {
            // $GET
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        }
        else
        {
            $_SERVER['QUERY_STRING'] = '';
        }

        // REQUEST
        // $_REQUEST = array_merge($_GET, $_POST);

        $this->req = array($_GET, $_POST, $_COOKIE, $_SERVER, $_FILES);
    }

    function upload_file($http_body,$http_post_boundary){
        //去除最后一行boundary
        $http_body = substr($http_body, 0, strlen($http_body) - (strlen($http_post_boundary) + 4));
        $boundary_data_array = explode($http_post_boundary."\r\n", $http_body);
        if($boundary_data_array[0] === '')
        {
            unset($boundary_data_array[0]);
        }
        foreach($boundary_data_array as $boundary_data_buffer)
        {
            /**
            Content-Disposition: form-data; name="text"

            title
            ------WebKitFormBoundaryrGKCBY7qhFd3TrwA
            Content-Disposition: form-data; name="file"; filename="chrome.png"
            Content-Type: image/png

            PNG ... content of chrome.png ...
             */
            list($boundary_header_buffer, $boundary_value) = explode("\r\n\r\n", $boundary_data_buffer, 2);
            // 去掉末尾\r\n
            $boundary_value = substr($boundary_value, 0, -2);
            foreach (explode("\r\n", $boundary_header_buffer) as $item)
            {
                list($header_key, $header_value) = explode(": ", $item);
                $header_key = strtolower($header_key);
                switch ($header_key)
                {
                    case "content-disposition":
                        // 是文件
                        if(preg_match('/name=".*?"; filename="(.*?)"$/', $header_value, $match))
                        {
                            $_FILES[] = array(
                                'file_name' => $match[1],
                                'file_data' => $boundary_value,
                                'file_size' => strlen($boundary_value),
                            );
                            $continue = 1;
                            break;
                        }
                        // 是post field
                        else
                        {
                            // 收集post
                            if(preg_match('/name="(.*?)"$/', $header_value, $match))
                            {
                                $_POST[$match[1]] = $boundary_value;
                            }
                        }
                        break;
                }
                if(!empty($continue)){
                    continue;
                }
            }
        }
    }


}