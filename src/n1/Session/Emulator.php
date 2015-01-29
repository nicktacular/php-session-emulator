<?php

class n1_Session_Emulator
{
    const DEFAULT_SAVE_PATH = '';

    protected static $requiredMap = array(
        'read',
        'close',
        'gc',
        'write',
        'open',
        'destroy'
    );

    /**
     * The name of the session.
     *
     * @var string
     */
    protected $name = 'PHPSESSID';
    protected $path = self::DEFAULT_SAVE_PATH;//@todo what's this for if not using files?

    /**
     * The session data.
     *
     * @var array
     */
    protected $data;

    /**
     * True if the data needs to be written.
     *
     * @var bool
     */
    protected $dirty = false;

    /**
     * An object that represents the save handler.
     *
     * @var SessionHandlerInterface|stdClass
     */
    protected $saveHandler;

    /**
     * @var n1_Session_HttpCookie
     */
    protected $browserCookie;

    /**
     * @var n1_Session_HttpCookie
     */
    protected $cookieToSend;

    /**
     * @var bool
     */
    protected $isOpen = false;

    /**
     * @var bool
     */
    protected $isNew = true;

    /**
     * The current session id.
     *
     * @var string
     */
    protected $currId;

    /**
     * @param string $name The name (defaults to PHPSESSID).
     * @param array $data The data (defaults to empty array).
     * @param n1_Session_HttpCookie|string $browserCookie Either a cookie OR the session id as a string.
     * @param bool $isNewSession Whether new or not (defaults to true).
     */
    public function __construct(
        $name = 'PHPSESSID',
        array $data = array(),
        $browserCookie = null,
        $isNewSession = true
    ) {
        $this->name = $name;
        $this->data = $data;
        $this->browserCookie = $browserCookie;
        $this->isNew = $isNewSession;

        // restore session id if passed back from browser
        if ($browserCookie instanceof n1_Session_HttpCookie && $browserCookie->getName() == $name) {
            $this->currId = $browserCookie->getValue();
        } elseif ($browserCookie !== null) {
            $this->currId = $browserCookie;
        }
    }

    /**
     * Provide an instance of the handler we wish to test.
     *
     * @param $handler SessionHandlerInterface|stdClass
     * @param array $map (optional) A map if using a pre-5.3 method of using handlers.
     *
     * @throws InvalidArgumentException When an argument doesn't validate.
     */
    public function setSaveHandler($handler, array $map = array())
    {
        if (!is_object($handler)) {
            throw new InvalidArgumentException('The handler should be an object.');
        }

        if (interface_exists('SessionHandlerInterface') && $handler instanceof SessionHandlerInterface) {
            $this->saveHandler = $handler;
            return;
        }

        // set the default to be just the names of the methods themselves
        if (!count($map)) {
            $map = array_combine(self::$requiredMap, self::$requiredMap);
        }

        foreach (self::$requiredMap as $type) {
            if (!array_key_exists($type, $map)) {
                throw new InvalidArgumentException("Missing '$type' from map.");
            }

            $method = $map[$type];

            if (!method_exists($handler, $method)) {
                throw new InvalidArgumentException("Missing '$method' from handler for '$type'");
            }
        }

        $this->saveHandler = $handler;
    }

    /**
     * Emulate session_start() call.
     */
    public function sessionStart()
    {
        if ($this->isOpen) {
            throw new RuntimeException('Session is already started.');
        }

        $this->isOpen = true;

        $needCookie = !$this->sessionExists();
        $id = $this->getId();

        if ($needCookie) {
            $this->cookieToSend = n1_Session_HttpCookie::create(array(
                'name' => $this->name,
                'value' => $id
            ));
        }

        $this->saveHandler->open($this->path, $this->name);
        $raw = $this->saveHandler->read($id);
        $this->unserialize($raw);
    }

    /**
     * Emulate session_write_close() call.
     */
    public function sessionWriteClose()
    {
        // session_write_close() doesn't warn if the session is already closed
        if (!$this->isOpen) {
            return;
        }

        if ($this->isNew || $this->dirty) {
            $this->saveHandler->write($this->getId(), $this->serialize());
        }

        $this->dirty = false;
        $this->isNew = false;
        $this->saveHandler->close();
        $this->isOpen = false;
    }

    /**
     * Emulate session_regenerate_id([$destroy = false]) call.
     *
     * @param bool $destroy (optional) Defaults to false.
     */
    public function sessionRegenerateId($destroy = false)
    {
        if (!$this->isOpen) {
            return;
        }

        if ($destroy) {
            $this->saveHandler->destroy($this->getId());
        }

        //new id, should send new cookie
        $this->currId = null;
        $this->getId();
        $this->isNew = true;
        $this->cookieToSend = n1_Session_HttpCookie::create(array(
            'name' => $this->name,
            'value' => $this->getId()
        ));
    }

    public function sessionDestroy()
    {
        if (!$this->isOpen) {
            return false;
        }

        $this->saveHandler->destroy($this->getId());
    }

    /**
     * Emulate what happens when PHP shuts down.
     */
    public function onShutdown()
    {
        $this->sessionWriteClose();
    }

    /**
     * A fake serializer. Real serialization doesn't matter because handlers should
     * __never__ mess with however PHP serializes its sessions. That is all.
     *
     * @return string
     */
    public function serialize()
    {
        //this isn't quite accurate, but doesn't matter since we don't edit this
        return count($this->data)? serialize($this->data) : '';
    }

    /**
     * @return array
     */
    public function unserialize($str)
    {
        if ($str == '') {
            $this->data = array();
        } else {
            $this->data = unserialize($str);
        }

        return $this->data;
    }

    /**
     * Check if a session exists in this current state.
     *
     * @return bool
     */
    public function sessionExists()
    {
        return $this->currId !== null;
    }

    /**
     * It's idempotent and will only generate an id if there is none.
     *
     * @return string
     */
    protected function getId($setTo = null)
    {
        if ($setTo) {
            $this->currId = $setTo;

            $this->cookieToSend = n1_Session_HttpCookie::create(array(
                'name' => $this->name,
                'value' => $this->currId
            ));
        }

        if (!$this->currId) {
            $this->currId = hash('sha1', microtime(true));
        }

        return $this->currId;
    }

    public function sessionId()
    {
        return $this->getId();
    }

    public function getCookieFromBrowser()
    {
        return $this->browserCookie;
    }

    public function getCookieForBrowser()
    {
        return $this->cookieToSend;
    }

    public function sessionName()
    {
        return $this->name;
    }

    public function set($key, $value)
    {
        //if not open, this has no effect
        if (!$this->isOpen) {
            return false;
        }

        $this->dirty = true;
        $this->data[$key] = $value;

        return true;
    }

    public function get($key)
    {
        if (!array_key_exists($key, $this->data)) {
            throw new OutOfBoundsException('No such key: ' . $key);
        }
        return $this->data[$key];
    }
}
