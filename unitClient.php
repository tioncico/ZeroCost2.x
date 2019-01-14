<?php
/**
 * Created by PhpStorm.
 * User: yangzhenyu
 * Date: 2019/1/14
 * Time: 11:02
 */

$host='127.0.0.1';
$port='8080';
$client_socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);//创建一个tcp的socket
$connection = socket_connect($client_socket,$host,$port);//连接
socket_write($client_socket, "hello socket") or die("Write failed\n"); // 数据传送 向服务器发送消息
while(1){
    $buffer = socket_read($client_socket, 1024, PHP_BINARY_READ);//默认阻塞类型,没有消息会一直阻塞
    if (empty($buffer)){
        die("已断开");
    }

    echo "服务端发送:".$buffer.PHP_EOL;
}