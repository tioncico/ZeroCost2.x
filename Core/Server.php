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
        $this->request = new Request();
    }

    protected function getResponse(): Response
    {
        return $this->response;
    }

    protected function getRequest():Request
    {
        return $this->request;
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
        $fdFk = '@@##--##!!';
        while (1){
            $data = $this->shmReq->pop();
            if(!empty($data)){
                $data = explode($fdFk,$data);
                if(intval($data[0])!=0){
                    if(!empty($data[1])){
                        $this->getRequest()->setRequest($data[1]);
                        $this->getRequest()->_init();
                        $this->shmRes->push($data[0].$fdFk.'hello word');
                        $this->getRequest()->gc();
                    }
                }
            }
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
                $client_list[intval($connection)] = $connection;
                stream_set_blocking($connection, 0);//设置客户端非阻塞
            }
            foreach ($client_list as $key=>$client){
                $data = fread($client, 65535);
                if($data!='') {
                    $pushData = $key.'@@##--##!!'.$data;
                    $this->shmReq->push($pushData);
                }
                while ( $pop = $this->shmRes->pop()){
                    $list = explode('@@##--##!!',$pop);
                    if(intval($list[0])!=0){
                        if(!empty($client_list[$list[0]])&&!empty($list[1])){
                            fwrite($client_list[$list[0]],$this->response->send($list[1]));//发送给客户端
                            if ( feof($client_list[$list[0]])) {
                                unset($client_list[$list[0]]);
                            }
                        }
                    }
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