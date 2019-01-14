<?php
/**
 * Created by PhpStorm.
 * User: yangzhenyu
 * Date: 2019/1/14
 * Time: 09:12
 */

class Server
{
    protected $workList = [];
    protected $serverPid = 0;
    protected $mainPid = 0;
    protected $serverSock = null;

    function __construct()
    {
        $this->daemon(
            function () {
                $this->init();
            }
        );
        $this->mainPid = getmypid();
    }

    private function init()
    {
        //server
        $this->serverPid = $this->process(function () {
            $this->start();
            $this->run();
            $this->end();
        });

        //work
        for ($i = 0; $i < 3; $i++) {
            if ($pid = $this->process(function () {
                    $this->work();
            })) {
                $this->workList[] = $pid;
            }
        }
    }

    function start()
    {

    }

    function end()
    {

    }

    function work()
    {

    }

    function run()
    {
        // 创建一个socket
        $servsock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (FALSE === $servsock) {
            $errcode = socket_last_error();
            throw new Exception("socket create fail: " . socket_strerror($errcode));
        }

        if (!socket_bind($servsock, '127.0.0.1', 8888))    // 绑定ip地址及端口
        {
            $errcode = socket_last_error();
            throw new Exception("socket bind fail: " . socket_strerror($errcode));
        }

        if (!socket_listen($servsock, 128))      // 允许多少个客户端来排队连接
        {
            $errcode = socket_last_error();
            throw new Exception("socket listen fail: " . socket_strerror($errcode));
        }
        $servsock = $servsock;
        /* 要监听的三个sockets数组 */
        $read_socks = array();
        $write_socks = array();
        $except_socks = NULL;  // 注意 php 不支持直接将NULL作为引用传参，所以这里定义一个变量

        $read_socks[] = $servsock;

        while (1) {
            $tmp_reads = $read_socks;
            $tmp_writes = $write_socks;
            $count = socket_select($tmp_reads, $tmp_writes, $except_socks, 30);  // timeout 传 NULL 会一直阻塞直到有结果返回
            foreach ($tmp_reads as $read) {

                if ($read == $servsock) {
                    /* 有新的客户端连接请求 */
                    $connsock = socket_accept($servsock);  //响应客户端连接， 此时不会造成阻塞
                    if ($connsock) {
                        socket_getpeername($connsock, $addr, $port);  //获取远程客户端ip地址和端口
                        echo "client connect server: ip = $addr, port = $port" . PHP_EOL;

                        // 把新的连接sokcet加入监听
                        $read_socks[] = $connsock;
                        $write_socks[] = $connsock;
                    }
                } else {
                    /* 客户端传输数据 */
                    $data = socket_read($read, 1024);  //从客户端读取数据, 此时一定会读到数组而不会产生阻塞

                    if ($data === '') {
                        //移除对该 socket 监听
                        foreach ($read_socks as $key => $val) {
                            if ($val == $read) unset($read_socks[$key]);
                        }

                        foreach ($write_socks as $key => $val) {
                            if ($val == $read) unset($write_socks[$key]);
                        }


                        socket_close($read);
                        echo "client close" . PHP_EOL;

                    } else {
                        socket_getpeername($read, $addr, $port);  //获取远程客户端ip地址和端口

                        echo "read from client # $addr:$port # " . $data;

                        $data = strtoupper($data);  //小写转大写

                        if (in_array($read, $tmp_writes)) {
                            //如果该客户端可写 把数据回写给客户端
                            socket_write($read, $data);
                        }
                    }
                }
            }
        }
    }

    ###############tools###################

    public function daemon(callable $callback)
    {
        $this->process($callback, function () {
            exit(0);
        });
    }

    private function process(callable $callback = null, callable $main = null)
    {
        $pid = pcntl_fork();
        if ($pid == 0) {
            if (is_callable($callback)) {
                $callback();
            }
            return false;
        } else {
            if (is_callable($main)) {
                $main();
            }
            return $pid;
        }
    }

    function kill($pid)
    {
        posix_kill($pid, SIGKILL);
    }

}