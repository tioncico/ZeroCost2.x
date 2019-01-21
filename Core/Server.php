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
    protected $response = null;
    protected $request = null;
    protected $shmReq = null;
    protected $shmRes = null;
    protected $host = '0.0.0.0';
    protected $port = '8888';


    function __construct()
    {

        $this->daemon(
            function () {
                $this->onStart();
            }
        );
        $this->mainPid = getmypid();
        require 'Response.php';
        require 'Request.php';
        require 'ShmQueue.php';
        $this->shmReq = new ShmQueue('q');
        $this->shmRes = new ShmQueue('s');
        $this->response = new Response();
        $this->request = [];
        //最高支持10个并发
        for ($i=0;$i<10;$i++){
            $this->request[$i] = new Request();
        }
    }

    protected function getResponse(): Response
    {
        return $this->response;
    }

    protected function getRequest($index):Request{
        return $this->request[$index];
    }
    public function init()
    {
        //server
        $this->serverPid = $this->process(function () {
            $this->run();
        });

        //work
        for ($i = 0; $i < 3; $i++) {
            if ($pid = $this->process(function () {
                $this->work();
            })) {
                $this->workList[] = $pid;
            }
        }
        $this->end();
    }

    function onStart()
    {

    }

    function end()
    {

    }

    function work()
    {
        while (1){
            $this->shmReq->pop();
            usleep(1);
        }

    }

    function onError($error){
        if(!empty($error[4])&&$error[4]['errno']!=0){
            var_dump($error);
        }
    }


    function run()
    {
        $tcp_socket = stream_socket_server("tcp://{$this->host }:{$this->port}", $errno, $errstr);
        $tcp_socket || die("$errstr ($errno)\n");
        stream_set_blocking($tcp_socket, 0);//设置非阻塞
        $client_list = [];
        while (1) {
            set_error_handler(function(...$err){
                $this->onError($err);
            });
            $connection = stream_socket_accept($tcp_socket,0,$remote_address);
            restore_error_handler();
            if ($connection) {
                $fd = intval($connection);
                $client_list[intval($connection)] = $connection;
                if($this->getRequest($fd%10)->getRequestStr()!=''){
                    while (1){
                        sleep(0.1);
                        if($this->getRequest($fd%10)->getRequestStr()==''){
                            break;
                        }
                    }
                }
                $this->getRequest($fd%10)->gc();
                stream_set_blocking($connection, 0);//设置客户端非阻塞
            }
            foreach ($client_list as $key=>$client){
                $data = fread($client, 65535);
                $power = false;

                if ($data === '' || $data === false) {
                    if ( feof($client)) {//客户端关闭
                        unset($client_list[$client]);
                    }
                }

                if($data!=''){
                    $this->shmReq->push($data);
                    $this->getRequest($key%10)->setRequest($data);
                    $power = $this->getRequest($key%10)->isOver();
                }


                if($power){
                    fwrite($client,$this->response->send('hello word'));//发送给客户端
                    $this->getRequest($key%10)->gc();
                }else{
                    continue;
                }
            }
            usleep(1);
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