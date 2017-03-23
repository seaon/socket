<?php

error_reporting(E_ALL ^ E_NOTICE);
ob_implicit_flush();

$socket = new Socket();
$socket->run();

class Socket{

    public $address  = '127.0.0.1';
    public $port     = '8000';
    public $domain   = AF_INET;
    public $type     = SOCK_STREAM;
    public $protocol = SOL_TCP;
    public $users    = array();
    private $_socket = false;
    private $_list   = array();

    public function __construct()
    {
        $this->createSocket();
        $this->_list[] = $this->_socket;
    }

    public function createSocket()
    {
        $this->_socket = socket_create($this->domain, $this->type, $this->protocol);
        socket_bind($this->_socket, $this->address, $this->port);
        socket_listen($this->_socket);
        $this->_log('Server Started : '. date('Y-m-d H:i:s'));
        $this->_log('Listening on   : '. $this->address . ' port : ' . $this->port);
    }

    public function run()
    {
        while(true)
        {
            $clients = $this->_list;
            $write  = NULL;
            $except = NULL;
            $tv_sec = 0;
            // 查找当前socket连接
            socket_select($clients, $write, $except, $tv_sec);
            foreach ($clients as $current)
            {
                if ($current == $this->_socket)
                {
                    $client = socket_accept($this->_socket);
                    $key    = uniqid();
                    $this->_list[]   = $client;
                    $this->users[$key] = array(
                        'socket' => $client,
                        'shou'   => false
                    );
                }
                else
                {
                    $buffer = socket_read($client, 2048, PHP_BINARY_READ);
                    $user = $this->findUser($current);
                    if(!$this->users[$user]['shou'])
                    {
                        $this->HandShake($user, $buffer);
                    }
                    else
                    {
                        $request = $this->decode($buffer);
                        $response = $this->encode('ok');
                        socket_write($this->users[$user]['socket'], $response, strlen($response));
                    }
                }
                
            }
        }
    }

    private function handShake($user, $buffer)
    {
        preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $buffer, $match);

        // 258EAFA5-E914-47DA-95CA-C5AB0DC85B11 magic key
        $new_key = base64_encode(sha1($match[1]."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));
        $response  = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Sec-WebSocket-Version: 13\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";

        socket_write($this->users[$user]['socket'], $response, strlen($response));
        $this->users[$user]['shou'] = true;
    }

    public function findUser( $socket )
    {
        foreach ($this->users as $key => $user)
        {
            if($socket == $user['socket']) return $key;
        }
        return false;
    }

    public function decode( $buffer )
    {
        $len = $masks = $data = $decoded = null;
        $len = ord($buffer[1]) & 127;

        if ($len === 126)
        {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        }
        else if ($len === 127)
        {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        }
        else
        {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++)
        {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }

        return $decoded;

    }

    public function encode( $buffer )
    {
        $len = strlen($buffer);

        if($len<=125)
        {
            $result = "\x81".chr($len).$buffer;
        }
        else if($len<=65535)
        {
            $result = "\x81".chr(126).pack("n", $len).$buffer;
        }
        else
        {
            $result = "\x81".char(127).pack("xxxxN", $len).$buffer;
        }

        return $result;
    }

    public function close()
    {
        socket_close($this->_socket);
    }

    public function _log( $msg )
    {
        $filename = dirname(__FILE__).'\\log.log';
        file_put_contents($filename, $msg."\n\n", FILE_APPEND);
    }
}
