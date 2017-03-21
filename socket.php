
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
                    // $length = 0;
                    // $buffer = '';
                    // do
                    // {
                    //     $l      =  socket_recv($current, $buf, 1000, 0);
                    //     $length += $l;
                    //     $buffer .= $buf;
                    // }while($l == 1000);

                    $buffer = socket_read($client, 2048, PHP_BINARY_READ);

                    $user = $this->findUser($current);

                    if(!$this->users[$user]['shou'])
                    {
                        $this->HandShake($user, $buffer);
                    }
                    else
                    {
                        $this->_log($buffer);
                        exit();
                    }
                }
                
            }
        }
    }

    public function handShake($user, $buffer)
    {
        $buf  = substr($buffer, strpos($buffer, 'Sec-WebSocket-Key:')+18);
        $key  = trim(substr($buf, 0, strpos($buf, "\r\n")));
        
        $new_key = base64_encode(sha1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));
        $respond  = "HTTP/1.1 101 Switching Protocols\r\n";
        $respond .= "Upgrade: websocket\r\n";
        $respond .= "Sec-WebSocket-Version: 13\r\n";
        $respond .= "Connection: Upgrade\r\n";
        $respond .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
        
        socket_write($this->users[$user]['socket'], $respond, strlen($respond));
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

    public function close()
    {
        socket_close($this->_socket);
    }

    public function _log( $msg )
    {
        $filename = dirname(__FILE__).'\\log.log';
        $fileHandel = fopen($filename, 'a');
        fwrite($fileHandel, $msg . PHP_EOL);
        fclose($fileHandel);
    }
}
