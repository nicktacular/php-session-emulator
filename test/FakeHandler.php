<?php

class FakeHandler
{
    public function read($id){}

    public function close(){}

    public function gc($maxLife){}

    public function write($id, $data){}

    public function open($savePath, $name){}

    public function destroy($id){}
}
