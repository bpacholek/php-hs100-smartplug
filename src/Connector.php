<?php

namespace IDCT\Hs100Plug;

use Exception;

/**
 * PHP Connector for Tp-Link HS100 Smartplug.
 */
class Connector
{
    const ERR_HOST_INVALID = 100;
    const ERR_PORT_INVALID = 101;
    const ERR_CANNOT_CONNECT = 102;
    const ERR_CANNOT_CREATE_SOCKET = 103;
    const ERR_CANNOT_SEND = 104;
    const ERR_CANNOT_RECV = 104;
    const ERR_CANNOT_CLOSE = 105;
    const ERR_INVALID_DATA = 106;

    /**
     * Host: ip or hostname of the smartplug.
     *
     * @var string
     */
    protected $host = null;

    /**
     * Port on which smartplug operates. By default 9999.
     *
     * @var int
     */
    protected $port = 9999;

    /**
     * Gets host value.
     *
     * @return $this
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Sets smartplug's hostname.
     *
     * @param string $host
     * @return $this
     */
    public function setHost($host)
    {
        if (strlen($host) < 1) {
            throw new \Exception("Host must be a valid string: hostname or IP.", static::ERR_HOST_INVALID);
        }

        $this->host = $host;
        return $this;
    }

    /**
     * Gets port.
     *
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Sets smartplug's port.
     *
     * @param int $port
     * @throws Exception Port must be integer between 1 and 65535.
     * @return $this
     */
    public function setPort($port)
    {
        if (!is_int($port) || $port < 1 || $port > 65535) {
            throw new \Exception("Port must be integer between 1 and 65535.", static::ERR_PORT_INVALID);
        }

        $this->port = $port;
        return $this;
    }

    /**
     * Encrypts the payload data to a format accepted by HS100 Smartplug.
     *
     * @param string $string
     * @return string
     */
    protected function encrypt($string)
    {
        $key = 171;
        $result = "\0\0\0\0";
        foreach (str_split($string) as $char) {
            $a = $key ^ ord($char);
            $key = $a;
            $result .= chr($a);
        }
        return $result;
    }

    /**
     * Decrypts the payload from a format sent by HS100 Smartplug into plain text.
     *
     * @param string $string
     * @return string
     */
    protected function decrypt($string)
    {
        $string = substr($string, 4);
        $key = 171;
        $result = "";
        foreach (str_split($string) as $char) {
            $a = $key ^ ord($char);
            $key = ord($char);
            $result .= chr($a);
        }
        return $result;
    }

    /**
     * Sends the payload data to HS100 Smartplug.
     *
     * Should be encrypted using `encrypt` method.
     *
     * @param string $payload
     * @throws Exception Invalid host.
     * @throws Exception Invalid port.
     * @throws Exception Cannot create socket.
     * @throws Exception Cannot connect.
     * @throws Exception Cannot send data.
     * @throws Exception Cannot receive data.
     * @return $this
     */
    protected function send($payload)
    {
        $host = $this->getHost();
        $port = $this->getPort();

        if ($host === null) {
            throw new Exception("Invalid host.", static::ERR_HOST_INVALID);
        }

        if (!is_int($port) || $port < 1 || $port > 65535) {
            throw new Exception("Invalid port.", static::ERR_PORT_INVALID);
        }

        if (!($socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
            throw new Exception("Cannot create socket.", static::ERR_CANNOT_CREATE_SOCKET);
        }

        if (!@socket_connect($socket, $host, $port)) {
            throw new Exception("Cannot connect.", static::ERR_CANNOT_CONNECT);
        }

        if (!@socket_send($socket, $payload, strlen($payload), MSG_EOF)) {
            throw new Exception("Cannot send data.", static::ERR_CANNOT_SEND);
        }

        $response = "";

        if (!@socket_recv($socket, $response, 2048, MSG_WAITALL)) {
            throw new Exception("Cannot receive data.", static::ERR_CANNOT_RECV);
        }

        return $response;
    }

    /**
     * Sends the payload of a command. Encrypts it into acceptable format and
     * tries to decrypt response.
     *
     * @param string $payload
     * @throws Exception Invalid data.
     * @return $this
     */
    protected function processCommand($payload)
    {
        $data = $this->encrypt($payload);
        $response = json_decode($this->decrypt($this->send($data)));
        if ($response === false || $response === null) {
            throw new Exception("Invalid data.", static::ERR_INVALID_DATA);
        }

        return $response;
    }

    /**
     * Turns ON the Smartplug.
     *
     * @return $this
     */
    public function turnOn()
    {
        $this->processCommand('{"system":{"set_relay_state":{"state":1}}}');
        return $this;
    }

    /**
     * Turns OFF the Smartplug.
     *
     * @return $this
     */
    public function turnOff()
    {
        $this->processCommand('{"system":{"set_relay_state":{"state":0}}}');
        return $this;
    }

    /**
     * Returns if smartplug is ON.
     *
     * @return boolean
     */
    public function isOn()
    {
        $data = $this->processCommand('{"system":{"get_sysinfo":{}}}');
        if (!isset($data->system) || !isset($data->system->get_sysinfo) || !isset($data->system->get_sysinfo->relay_state)) {
            throw new \Exception("Invalid data.", static::ERR_INVALID_DATA);
        }

        return ($data->system->get_sysinfo->relay_state === 1);
    }

    /**
     * Retruns smartplug's alias.
     *
     * @return $this
     */
    public function retrieveAlias()
    {
        $data = $this->processCommand('{"system":{"get_sysinfo":{}}}');
        if (!isset($data->system) || !isset($data->system->get_sysinfo) || !isset($data->system->get_sysinfo->alias)) {
            throw new \Exception("Invalid data.", static::ERR_INVALID_DATA);
        }

        return $data->system->get_sysinfo->alias;
    }
}
