<?php

namespace Session;

final class Redis
{
    public $expire = '';
    protected $prefix = 'sess_';

    public function __construct($registry)
    {
        $this->redis = new \Redis();

        if (!$this->redis->pconnect('127.0.0.1')) {
            throw new \Exception("Permissions denied session storage");
        }

        $this->expire = ini_get('session.gc_maxlifetime');
    }

    public function read($session_id)
    {
        if ($this->redis->exists($this->prefix . $session_id)) {
            return json_decode( $this->redis->get($this->prefix . $session_id), true );
        }

        return false;
    }

    public function write($session_id, $data)
    {
        if ($session_id) {
            $this->redis->psetex($this->prefix . $session_id, $this->expire * 1000, json_encode($data));
        }

        return true;
    }

    public function destroy($session_id)
    {
        if ($this->redis->exists($this->prefix . $session_id)) {
            $this->redis->delete($this->prefix . $session_id);
        }

        return true;
    }

    public function gc($expire) {
        return true;
    }

}