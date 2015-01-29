<?php


class n1_Session_HttpCookie
{
    protected $name;
    protected $value;
    protected $expire;
    protected $path;
    protected $domain;
    protected $secure;
    protected $httpOnly;

    public static function create(array $c = array())
    {
        if (!isset($c['name'])) {
            throw new InvalidArgumentException('A cookie needs a name.');
        }

        if (!isset($c['value'])) {
            $c['value'] = false;
        }

        if (!isset($c['expire'])) {
            //0 is for session expiry, per setcookie(...)
            $c['expire'] = 0;
        }

        if (!isset($c['path'])) {
            $c['path'] = '/';
        }

        if (!isset($c['domain'])) {
            $c['domain'] = '';
        }

        if (!isset($c['secure'])) {
            $c['secure'] = false;
        }

        if (!isset($c['http_only'])) {
            $c['http_only'] = false;
        }

        return new self(
            $c['name'],
            $c['value'],
            $c['expire'],
            $c['path'],
            $c['domain'],
            $c['secure'],
            $c['http_only']
        );
    }

    public function __construct($name, $value, $expire, $path, $domain, $secure, $httpOnly)
    {
        $this->name = $name;
        $this->value = $value;
        $this->expire = $expire;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getExpire()
    {
        return $this->expire;
    }

    /**
     * @param mixed $expire
     */
    public function setExpire($expire)
    {
        $this->expire = $expire;
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param mixed $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return mixed
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @param mixed $domain
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * @return mixed
     */
    public function getSecure()
    {
        return $this->secure;
    }

    /**
     * @param mixed $secure
     */
    public function setSecure($secure)
    {
        $this->secure = $secure;
    }

    /**
     * @return mixed
     */
    public function getHttpOnly()
    {
        return $this->httpOnly;
    }

    /**
     * @param mixed $httpOnly
     */
    public function setHttpOnly($httpOnly)
    {
        $this->httpOnly = $httpOnly;
    }
}
