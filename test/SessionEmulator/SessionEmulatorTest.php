<?php

use Mockery\Mock;

class n1_Session_EmulatorTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    public function testPhp54InterfaceLoadedAndUsable()
    {
        if (!class_exists('FakeSessionHandler')) {
            $this->markTestSkipped('Cannot test SessionHandlerInterface handling.');
            return;
        }

        $emulator = new n1_Session_Emulator();
        $handler = new FakeSessionHandler();
        $emulator->setSaveHandler($handler);
        $emulator->sessionStart();

        //assert we have an id
        $this->assertNotNull($emulator->sessionId());
    }

    public function testClassHandlerNewSessionWillSendCookie()
    {
        $name = 'mySession123';

        $emulator = new n1_Session_Emulator($name);
        $handler = Mockery::mock('FakeHandler');

        $handler
            ->shouldReceive('open')
            ->withArgs(array(n1_Session_Emulator::DEFAULT_SAVE_PATH, $name))
            ->once()
            ->andReturn();
        $handler
            ->shouldReceive('read')
            ->once()
            ->andReturn();

        $emulator->setSaveHandler($handler);
        $emulator->sessionStart();



        $cookie = $emulator->getCookieForBrowser();
        $this->assertNotNull($cookie);
        $this->assertSame($cookie->getName(), $name);
        $this->assertSame($cookie->getValue(), $emulator->sessionId());
    }

    public function testExistingSessionWillNotTriggerCookie()
    {
        $name = 'mySession123';
        $id = 'lolid';
        $cookie = n1_Session_HttpCookie::create(array(
            'name' => $name,
            'value' => $id
        ));
        $raw = '';

        $emulator = new n1_Session_Emulator($name, array(), $cookie, true);

        $handler = Mockery::mock('FakeHandler');
        $handler
            ->shouldReceive('open')
            ->withArgs(array(n1_Session_Emulator::DEFAULT_SAVE_PATH, $name))
            ->once()
            ->andReturn();
        $handler
            ->shouldReceive('read')
            ->withArgs(array($id))
            ->once()
            ->andReturn();
        $handler
            ->shouldReceive('write')
            ->withArgs(array($id, $raw))
            ->once()
            ->andReturn();
        $handler
            ->shouldReceive('close')
            ->withNoArgs()
            ->once()
            ->andReturn();

        $emulator->setSaveHandler($handler);
        $emulator->sessionStart();

        //emulate php shutdown behaviour
        $emulator->onShutdown();

        $this->assertNull($emulator->getCookieForBrowser());
        $this->assertSame($cookie, $emulator->getCookieFromBrowser());
    }

    public function testSessionWriteTriggersWrite()
    {
        $name = 'mySession123';
        $id = 123456;
        $raw = serialize(array('hello' => 'world'));

        $emulator = new n1_Session_Emulator($name, array(), $id);

        //since we passed in a session id, session "exists"
        $this->assertTrue($emulator->sessionExists());

        $handler = Mockery::mock('FakeHandler');
        $handler
            ->shouldReceive('open')
            ->withArgs(array(n1_Session_Emulator::DEFAULT_SAVE_PATH, $name))
            ->once()
            ->andReturn();
        $handler
            ->shouldReceive('read')
            ->withArgs(array($id))
            ->once()
            ->andReturn();
        $handler
            ->shouldReceive('write')
            ->withArgs(array($id, $raw))
            ->once()
            ->andReturn();
        $handler
            ->shouldReceive('close')
            ->withNoArgs()
            ->once()
            ->andReturn();

        $emulator->setSaveHandler($handler);
        $emulator->sessionStart();

        $emulator->set('hello', 'world');

        //emulate php shutdown behaviour
        $emulator->onShutdown();
    }

    public function testExistingSessionWithNoWriteDoesNotTriggerImplicitWrite()
    {
        $name = 'mySession123';
        $data = array('hello' => 'world');
        $id = sha1(rand());

        $fromBrowser = n1_Session_HttpCookie::create(array(
            'name' => $name,
            'value' => $id
        ));

        $emulator = new n1_Session_Emulator($name, $data, $fromBrowser, false);

        $handler = Mockery::mock('FakeHandler');
        $handler
            ->shouldReceive('open')
            ->withArgs(array(n1_Session_Emulator::DEFAULT_SAVE_PATH, $name))
            ->once()
            ->andReturn();
        $handler
            ->shouldReceive('read')
            ->once()
            ->andReturn(serialize($data));
        $handler
            ->shouldReceive('write')
            ->never();
        $handler
            ->shouldReceive('close')
            ->withNoArgs()
            ->once()
            ->andReturn();

        $emulator->setSaveHandler($handler);
        $emulator->sessionStart();

        $this->assertSame('world', $emulator->get('hello'));

        //emulate php shutdown behaviour
        $emulator->onShutdown();

        $cookie = $emulator->getCookieForBrowser();
        $this->assertNull($cookie);
        $this->assertSame($id, $emulator->sessionId());
    }

    public function testRegenerateWithNewIdAndSendsNewCookie()
    {
        $name = 'mySession123';
        $data = array('hello' => 'world', 'yes' => array(1, 2, 3));
        $id = sha1(rand());

        $fromBrowser = n1_Session_HttpCookie::create(array(
            'name' => $name,
            'value' => $id
        ));

        $emulator = new n1_Session_Emulator($name, $data, $fromBrowser, false);

        $handler = Mockery::mock('FakeHandler');
        $handler
            ->shouldReceive('open')
            ->withArgs(array(n1_Session_Emulator::DEFAULT_SAVE_PATH, $name))
            ->once()
            ->andReturn();
        $handler
            ->shouldReceive('read')
            ->once()
            ->andReturn(serialize($data));
        $handler
            ->shouldReceive('write')
            ->once();
        $handler
            ->shouldReceive('destroy')
            ->never();
        $handler
            ->shouldReceive('close')
            ->withNoArgs()
            ->once()
            ->andReturn();

        $emulator->setSaveHandler($handler);
        $emulator->sessionStart();

        $emulator->sessionRegenerateId();

        $this->assertSame(array(1, 2, 3), $emulator->get('yes'));

        $this->assertNotSame($id, $emulator->sessionId());
        $this->assertNotNull($emulator->getCookieForBrowser());
        $this->assertNotSame($emulator->getCookieForBrowser(), $fromBrowser);

        //verify new session id will be sent
        $this->assertSame($emulator->getCookieForBrowser()->getValue(), $emulator->sessionId());

        //emulate php shutdown behaviour
        $emulator->onShutdown();
    }

    public function testRegenerateWithDestroyWillDestroyAndRegenerate()
    {
        $name = 'mySession123';
        $data = array('hello' => 'world', 'yes' => array(1, 2, 3));
        $id = sha1(rand());

        $fromBrowser = n1_Session_HttpCookie::create(array(
            'name' => $name,
            'value' => $id
        ));

        $emulator = new n1_Session_Emulator($name, $data, $fromBrowser, false);

        $handler = Mockery::mock('FakeHandler');
        $handler
            ->shouldReceive('open')
            ->withArgs(array(n1_Session_Emulator::DEFAULT_SAVE_PATH, $name))
            ->once()
            ->andReturn();
        $handler
            ->shouldReceive('read')
            ->once()
            ->andReturn(serialize($data));
        $handler
            ->shouldReceive('write')
            ->once();
        $handler
            ->shouldReceive('destroy')
            ->withArgs(array($id))
            ->once();
        $handler
            ->shouldReceive('close')
            ->withNoArgs()
            ->once()
            ->andReturn();

        $emulator->setSaveHandler($handler);
        $emulator->sessionStart();

        // regenerate AND destroy the old one
        $emulator->sessionRegenerateId(true);

        $this->assertSame(array(1, 2, 3), $emulator->get('yes'));

        $newCookie = $emulator->getCookieForBrowser();
        $this->assertNotSame($id, $emulator->sessionId());
        $this->assertNotNull($newCookie);
        $this->assertNotSame($newCookie, $fromBrowser);
        $this->assertSame($newCookie->getName(), $fromBrowser->getName());

        //verify new session id will be sent
        $this->assertSame($emulator->getCookieForBrowser()->getValue(), $emulator->sessionId());

        //emulate php shutdown behaviour
        $emulator->onShutdown();
    }

    public function testDestroy()
    {
        $name = 'mySession123';
        $data = array('hello' => time());
        $id = sha1(rand());

        $fromBrowser = n1_Session_HttpCookie::create(array(
            'name' => $name,
            'value' => $id
        ));

        $emulator = new n1_Session_Emulator($name, $data, $fromBrowser, false);

        $handler = Mockery::mock('FakeHandler');
        $handler
            ->shouldReceive('open')
            ->withArgs(array(n1_Session_Emulator::DEFAULT_SAVE_PATH, $name))
            ->once()
            ->andReturn();
        $handler
            ->shouldReceive('read')
            ->once()
            ->andReturn(serialize($data));
        $handler
            ->shouldReceive('write')
            ->never();
        $handler
            ->shouldReceive('destroy')
            ->once()
            ->withArgs(array($id));
        $handler
            ->shouldReceive('close')
            ->withNoArgs()
            ->once()
            ->andReturn();

        $emulator->setSaveHandler($handler);
        $emulator->sessionStart();
        $emulator->sessionDestroy();

        //emulate php shutdown behaviour
        $emulator->onShutdown();

        // surprisingly, php does not try to delete the session cookie
        $this->assertNull($emulator->getCookieForBrowser());
    }

    //public function testMultipleCloseAndOpens() {}

    //public function testDestroyAndReOpen(){}

    public function tearDown()
    {
        Mockery::close();
        parent::tearDown();
    }
}
