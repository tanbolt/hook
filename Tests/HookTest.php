<?php

use Tanbolt\Hook\Hook;
use Tanbolt\Hook\Event;
use Tanbolt\Hook\HookException;
use Tanbolt\Hook\HookTriggerException;
use PHPUnit\Framework\TestCase;

class HookTest extends TestCase
{
    public function testBindMethod()
    {
        $hook = new Hook();
        $defaultPriority = Hook::PRIORITY;
        $priority1 = $defaultPriority + 2;
        $priority2 = $defaultPriority + 4;

        // normal
        static::assertSame($hook, $hook->bind('foo', 'foo'));
        $hook->bind('foo', 'aa', ['a' => 'a'], $priority1)
            ->bind('foo', 'bb')
            ->bind('foo', 'cc', ['b' => 'b'], $priority2)
            ->bind('foo', 'dd', ['a' => 'a','b' => 'b']);
        static::assertCount(5, $hook->queue('foo'));
        static::assertCount(0, $hook->queue('bar'));
        static::assertCount(0, $hook->queue('foo', Hook::ON));
        static::assertCount(0, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(0, $hook->queue('foo', Hook::AFTER));

        /**
         * 测试 优先顺序
         * @var Event[] $events
         */
        $events = $hook->queue('foo');

        $event = $events[0];
        static::assertInstanceOf(Event::class, $event);
        static::assertEquals(Hook::WHEN, $event->type);
        static::assertEquals('foo', $event->name);
        static::assertEquals('cc', $event->handler);
        static::assertEquals(['b' => 'b'], $event->bag);
        static::assertEquals($priority2, $event->priority);
        static::assertEquals('foo', $event->trigger);
        static::assertEquals('b', $event->getBag('b'));
        static::assertSame($event, $event->removeBag('b'));
        static::assertNull($event->getBag('b'));
        static::assertEquals('def', $event->getBag('b', 'def'));
        static::assertEquals([], $event->bag);

        $event = $events[1];
        static::assertInstanceOf(Event::class, $event);
        static::assertEquals(Hook::WHEN, $event->type);
        static::assertEquals('foo', $event->name);
        static::assertEquals('aa', $event->handler);
        static::assertEquals(['a' => 'a'], $event->bag);
        static::assertEquals($priority1, $event->priority);
        static::assertEquals('foo', $event->trigger);

        $event = $events[2];
        static::assertInstanceOf(Event::class, $event);
        static::assertEquals(Hook::WHEN, $event->type);
        static::assertEquals('foo', $event->name);
        static::assertEquals('foo', $event->handler);
        static::assertEquals([], $event->bag);
        static::assertEquals($defaultPriority, $event->priority);
        static::assertEquals('foo', $event->trigger);

        $event = $events[3];
        static::assertInstanceOf(Event::class, $event);
        static::assertEquals(Hook::WHEN, $event->type);
        static::assertEquals('foo', $event->name);
        static::assertEquals('bb', $event->handler);
        static::assertEquals([], $event->bag);
        static::assertEquals($defaultPriority, $event->priority);
        static::assertEquals('foo', $event->trigger);

        $event = $events[4];
        static::assertInstanceOf(Event::class, $event);
        static::assertEquals(Hook::WHEN, $event->type);
        static::assertEquals('foo', $event->name);
        static::assertEquals('dd', $event->handler);
        static::assertEquals(['a' => 'a','b' => 'b'], $event->bag);
        static::assertEquals($defaultPriority, $event->priority);
        static::assertEquals('foo', $event->trigger);

        // on (regex)
        static::assertSame($hook, $hook->on('reg/*', 'foo'));
        $hook->on('reg/foo', 'bar', ['a' => 'a'])
            ->on('reg/bar', 'biz', ['a' => 'a','b' => 'b']);

        static::assertCount(2, $hook->queue('reg/foo', Hook::ON));
        static::assertCount(2, $hook->queue('reg/bar', Hook::ON));
        static::assertCount(1, $hook->queue('reg/biz', Hook::ON));
        static::assertCount(0, $hook->queue('reg', Hook::ON));

        /**
         * 测试通配符匹配
         * @var Event[] $events
         */
        $events = $hook->queue('reg/foo', Hook::ON);

        $event = $events[0];
        static::assertInstanceOf(Event::class, $event);
        static::assertEquals(Hook::ON, $event->type);
        static::assertEquals('reg/*', $event->name);
        static::assertEquals('foo', $event->handler);
        static::assertEquals([], $event->bag);
        static::assertEquals($defaultPriority, $event->priority);
        static::assertEquals('reg/foo', $event->trigger);

        $event = $events[1];
        static::assertInstanceOf(Event::class, $event);
        static::assertEquals(Hook::ON, $event->type);
        static::assertEquals('reg/foo', $event->name);
        static::assertEquals('bar', $event->handler);
        static::assertEquals(['a' => 'a'], $event->bag);
        static::assertEquals($defaultPriority, $event->priority);
        static::assertEquals('reg/foo', $event->trigger);

        // before
        static::assertSame($hook, $hook->before('foo', 'bar'));
        static::assertCount(1, $hook->queue('foo', Hook::BEFORE));

        /**
         * 测试 Before + default group
         * @var Event[] $events
         */
        $events = $hook->queue('foo', Hook::BEFORE);

        $event = $events[0];
        static::assertInstanceOf(Event::class, $event);
        static::assertEquals(Hook::BEFORE, $event->type);
        static::assertEquals(Hook::DEFAULT_GROUP, $event->group);
        static::assertEquals('foo', $event->name);
        static::assertEquals('bar', $event->handler);
        static::assertEquals([], $event->bag);
        static::assertEquals($defaultPriority, $event->priority);
        static::assertEquals('foo', $event->trigger);

        // after
        static::assertSame($hook, $hook->after('test@foo', 'biz'));
        static::assertCount(0, $hook->queue('foo', Hook::AFTER));
        static::assertCount(1, $hook->queue('test@foo', Hook::AFTER));

        /**
         * 测试 After + custom group
         * @var Event[] $events
         */
        $events = $hook->queue('test@foo', Hook::AFTER);

        $event = $events[0];
        static::assertInstanceOf(Event::class, $event);
        static::assertEquals(Hook::AFTER, $event->type);
        static::assertEquals('test', $event->group);
        static::assertEquals('foo', $event->name);
        static::assertEquals('biz', $event->handler);
        static::assertEquals([], $event->bag);
        static::assertEquals($defaultPriority, $event->priority);
        static::assertEquals('foo', $event->trigger);
    }

    public function testOffMethod()
    {
        $hook = new Hook();

        $hook->bind('foo', 'foo')->bind('foo', 'bar')
             ->on('foo', 'foo')->on('foo', 'bar')
             ->before('foo', 'foo')->before('foo', 'bar')
             ->after('foo', 'foo')->after('foo', 'bar');

        static::assertCount(2, $hook->queue('foo'));
        static::assertCount(2, $hook->queue('foo', Hook::ON));
        static::assertCount(2, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(2, $hook->queue('foo', Hook::AFTER));

        // off when
        static::assertSame($hook, $hook->off('foo'));
        static::assertCount(0, $hook->queue('foo'));
        static::assertCount(2, $hook->queue('foo', Hook::ON));
        static::assertCount(2, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(2, $hook->queue('foo', Hook::AFTER));

        // off on
        static::assertSame($hook, $hook->off('foo', Hook::ON));
        static::assertCount(0, $hook->queue('foo'));
        static::assertCount(0, $hook->queue('foo', Hook::ON));
        static::assertCount(2, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(2, $hook->queue('foo', Hook::AFTER));

        // off before
        static::assertSame($hook, $hook->off('foo', Hook::BEFORE));
        static::assertCount(0, $hook->queue('foo'));
        static::assertCount(0, $hook->queue('foo', Hook::ON));
        static::assertCount(0, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(2, $hook->queue('foo', Hook::AFTER));

        // off when
        static::assertSame($hook, $hook->off('foo', Hook::AFTER));
        static::assertCount(0, $hook->queue('foo'));
        static::assertCount(0, $hook->queue('foo', Hook::ON));
        static::assertCount(0, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(0, $hook->queue('foo', Hook::AFTER));
    }

    public function testOffHandler()
    {
        $hook = new Hook();

        $hook->bind('foo', 'foo')->bind('foo', 'bar')
             ->on('foo', 'foo')->on('foo', 'bar')
             ->before('foo', 'foo')->before('foo', 'bar')
             ->after('foo', 'foo')->after('foo', 'bar');

        // off when
        static::assertSame($hook, $hook->offHandler('foo', 'foo'));

        static::assertCount(1, $hook->queue('foo'));
        static::assertCount(2, $hook->queue('foo', Hook::ON));
        static::assertCount(2, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(2, $hook->queue('foo', Hook::AFTER));

        /** @var Event[] $events */
        $events = $hook->queue('foo');

        $event = $events[0];
        static::assertInstanceOf(Event::class, $event);
        static::assertEquals(Hook::WHEN, $event->type);
        static::assertEquals('foo', $event->name);
        static::assertEquals('bar', $event->handler);
        static::assertEquals([], $event->bag);
        static::assertEquals(Hook::PRIORITY, $event->priority);
        static::assertEquals('foo', $event->trigger);

        $hook->offHandler('foo', 'bar');
        static::assertCount(0, $hook->queue('foo'));
        static::assertCount(2, $hook->queue('foo', Hook::ON));
        static::assertCount(2, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(2, $hook->queue('foo', Hook::AFTER));

        // off on
        $hook->offHandler('foo', 'foo', Hook::ON);
        static::assertCount(0, $hook->queue('foo'));
        static::assertCount(1, $hook->queue('foo', Hook::ON));
        static::assertCount(2, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(2, $hook->queue('foo', Hook::AFTER));

        $hook->offHandler('foo', 'bar', Hook::ON);
        static::assertCount(0, $hook->queue('foo'));
        static::assertCount(0, $hook->queue('foo', Hook::ON));
        static::assertCount(2, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(2, $hook->queue('foo', Hook::AFTER));

        // off before
        $hook->offHandler('foo', 'foo', Hook::BEFORE);
        static::assertCount(0, $hook->queue('foo'));
        static::assertCount(0, $hook->queue('foo', Hook::ON));
        static::assertCount(1, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(2, $hook->queue('foo', Hook::AFTER));

        $hook->offHandler('foo', 'bar', Hook::BEFORE);
        static::assertCount(0, $hook->queue('foo'));
        static::assertCount(0, $hook->queue('foo', Hook::ON));
        static::assertCount(0, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(2, $hook->queue('foo', Hook::AFTER));

        // off after
        $hook->offHandler('foo', 'foo', Hook::AFTER);
        static::assertCount(0, $hook->queue('foo'));
        static::assertCount(0, $hook->queue('foo', Hook::ON));
        static::assertCount(0, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(1, $hook->queue('foo', Hook::AFTER));

        static::assertSame($hook, $hook->offHandler('foo', 'biz', Hook::AFTER));
        static::assertCount(0, $hook->queue('foo'));
        static::assertCount(0, $hook->queue('foo', Hook::ON));
        static::assertCount(0, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(1, $hook->queue('foo', Hook::AFTER));

        $hook->offHandler('foo', 'bar', Hook::AFTER);
        static::assertCount(0, $hook->queue('foo'));
        static::assertCount(0, $hook->queue('foo', Hook::ON));
        static::assertCount(0, $hook->queue('foo', Hook::BEFORE));
        static::assertCount(0, $hook->queue('foo', Hook::AFTER));
    }

    public function testOffGroup()
    {
        $hook = new Hook();

        $hook->bind('foo@bar', 'foo')->bind('foo@biz', 'bar')
            ->on('foo@bar', 'foo')->on('foo@biz', 'bar')
            ->before('foo@bar', 'foo')->before('foo@biz', 'bar')
            ->after('foo@bar', 'foo')->after('foo@biz', 'bar');

        // 默认 group 下 hook 应为 0
        static::assertCount(0, $hook->queue('bar'));
        static::assertCount(0, $hook->queue('biz'));

        // off when group
        static::assertCount(1, $hook->queue('foo@bar'));
        static::assertCount(1, $hook->queue('foo@biz'));
        static::assertSame($hook, $hook->offGroup('foo'));
        static::assertCount(0, $hook->queue('foo@bar'));
        static::assertCount(0, $hook->queue('foo@biz'));

        // off on group
        static::assertCount(1, $hook->queue('foo@bar', Hook::ON));
        static::assertCount(1, $hook->queue('foo@biz', Hook::ON));

        // off 默认 group 下 hook 不会对 group(foo) 起作用
        static::assertSame($hook, $hook->off('bar', Hook::ON));
        static::assertCount(1, $hook->queue('foo@bar', Hook::ON));

        // off 当前 group 下 hook
        static::assertSame($hook, $hook->off('foo@bar', Hook::ON));
        static::assertCount(0, $hook->queue('foo@bar', Hook::ON));

        // offGroup
        static::assertSame($hook, $hook->offGroup('foo', Hook::ON));
        static::assertCount(0, $hook->queue('foo@biz', Hook::ON));

        // off before group
        static::assertCount(1, $hook->queue('foo@bar', Hook::BEFORE));
        static::assertCount(1, $hook->queue('foo@biz', Hook::BEFORE));
        static::assertSame($hook, $hook->offGroup('foo', Hook::BEFORE));
        static::assertCount(0, $hook->queue('foo@bar', Hook::BEFORE));
        static::assertCount(0, $hook->queue('foo@biz', Hook::BEFORE));

        // off after group
        static::assertCount(1, $hook->queue('foo@bar', Hook::AFTER));
        static::assertCount(1, $hook->queue('foo@biz', Hook::AFTER));
        static::assertSame($hook, $hook->offGroup('foo', Hook::AFTER));
        static::assertCount(0, $hook->queue('foo@bar', Hook::AFTER));
        static::assertCount(0, $hook->queue('foo@biz', Hook::AFTER));
    }

    public function testTriggerMethodNotCallable()
    {
        $hook = new Hook();
        $hook->bind('foo', 'foo');
        try {
            $hook->trigger('foo');
            static::fail('It should throw exception when handler is not callable');
        } catch (HookException $e) {
            static::assertTrue(true);
        }
    }

    public function testTriggerEvent()
    {
        $hook = new Hook();
        $trigger = false;
        $hook->on('reg/*', function(Event $e, $data) use (&$trigger){
            $trigger = true;

            static::assertEquals('reg/*', $e->name);
            static::assertEquals(['a' => 'a', 'b' => 'b2'], $e->bag);
            static::assertEquals(Hook::PRIORITY, $e->priority);
            static::assertEquals(Hook::ON, $e->type);
            static::assertEquals('reg/foo', $e->trigger);

            static::assertInstanceOf('_HookData', $data);
            static::assertEquals('___', $data->test);

        }, ['a' => 'a', 'b' => 'b2']);


        static::assertSame($hook, $hook->trigger('reg/foo', new _HookData('___'), Hook::ON));
        static::assertTrue($trigger);

        $triggered = $hook->getTriggered(Hook::ON);
        static::assertEquals(1, $triggered['reg/*']);
    }

    public function testTriggerMethodReturn()
    {
        $foo = null;
        $hook = new Hook();
        $hook->bind('foo', function() use (&$foo){
            $foo = 'foo';
            return null;
        });
        $hook->trigger('foo');
        static::assertEquals('foo', $foo);

        $hook->bind('bar', function() use (&$foo){
            $foo = 'bar';
            return 'bar';
        });
        try {
            $hook->trigger('bar');
            static::fail('It should throw exception if hook function return value.');
        } catch (HookTriggerException $e) {
            static::assertEquals('bar', $e->receive);
        }
    }

    public function testTriggerSort()
    {
        $foo = null;

        $hook = new Hook();
        $hook->bind('foo', function() use (&$foo){
            $foo = 'foo';
        })->bind('foo', function() use (&$foo){
            $foo = 'bar';
        });

        $hook->trigger('foo');
        static::assertEquals('bar', $foo);

        $foo = null;

        $hook = new Hook();
        $hook->bind('foo', function() use (&$foo){
            $foo = 'foo';
        })->bind('foo', function() use (&$foo){
            $foo = 'bar';
        }, [], Hook::PRIORITY + 2);

        $hook->trigger('foo');
        static::assertEquals('foo', $foo);
    }

    public function testTriggerMultiple()
    {
        $foo = null;
        $bar = null;

        $hook = new Hook();
        $hook->bind('foo/*', function() use (&$foo){
            $foo = 'foo';
        })->bind('foo/baz', function() use (&$bar){
            $bar = 'bar';
        });

        $hook->bind('bar/*', function() use (&$foo){
            $foo = 'foo2';
        })->bind('bar/baz', function() use (&$bar){
            $bar = 'bar2';
        });

        static::assertSame($hook, $hook->trigger('foo/baz'));
        static::assertEquals('foo', $foo);
        static::assertEquals('bar', $bar);

        static::assertSame($hook, $hook->trigger('bar/baz'));
        static::assertEquals('foo2', $foo);
        static::assertEquals('bar2', $bar);

        static::assertSame($hook, $hook->trigger('foo/que'));
        static::assertEquals('foo', $foo);
        static::assertEquals('bar2', $bar);

        static::assertSame($hook, $hook->trigger('foo/baz'));
        static::assertSame($hook, $hook->trigger('bar/que'));
        static::assertEquals('foo2', $foo);
        static::assertEquals('bar', $bar);

        $triggered = $hook->getTriggered();
        static::assertEquals(3, $triggered['foo/*']);
        static::assertEquals(2, $triggered['foo/baz']);
        static::assertEquals(2, $triggered['bar/*']);
        static::assertEquals(1, $triggered['bar/baz']);
    }

    public function testTriggerEventOff()
    {
        $foo = null;
        $hook = new Hook();
        $hook->bind('foo', function() use (&$foo){
            $foo = 'foo';
        })->bind('foo', function(Event $e) use (&$foo){
            $foo = 'bar';
            $e->off();
        });

        $hook->trigger('foo');
        static::assertEquals('bar', $foo);
        static::assertCount(1, $hook->queue('foo'));

        $foo = null;
        $hook->trigger('foo');
        static::assertEquals('foo', $foo);
    }

    public function testTriggerStop()
    {
        $foo = null;
        $bar = null;
        $biz = null;

        $hook = new Hook();
        $hook->bind('reg/*', function(Event $e) use (&$foo){
            if ('reg/foo' === $e->trigger) {
                $e->stopPropagation();
            }
            $foo = 'foo';

        })->bind('reg/*', function(Event $e) use (&$bar){
            if ('reg/bar' === $e->trigger) {
                $e->stopPropagation();
            }
            $bar = 'bar';

        })->bind('reg/*', function() use (&$biz){
            $biz = 'biz';
        });

        $hook->trigger('reg/foo');
        static::assertEquals('foo', $foo);
        static::assertNull($bar);
        static::assertNull($biz);

        $foo = null;
        $bar = null;
        $biz = null;
        $hook->trigger('reg/bar');
        static::assertEquals('foo', $foo);
        static::assertEquals('bar', $bar);
        static::assertNull($biz);

        $foo = null;
        $bar = null;
        $biz = null;
        $hook->trigger('reg/biz');
        static::assertEquals('foo', $foo);
        static::assertEquals('bar', $bar);
        static::assertEquals('biz', $biz);

        $foo = null;
        $bar = null;
        $biz = null;
        $hook->trigger('reg/other');
        static::assertEquals('foo', $foo);
        static::assertEquals('bar', $bar);
        static::assertEquals('biz', $biz);

        $foo = null;
        $bar = null;
        $biz = null;
        $hook->trigger('none');
        static::assertNull($foo);
        static::assertNull($bar);
        static::assertNull($biz);
    }

    public function testTriggerException()
    {
        $hook = new Hook();

        $foo = 0;
        $bar = 0;
        $biz = 0;
        $hook->bind('foo', function() use (&$foo){
            $foo++;
        })->bind('foo', function() use (&$bar){
            $bar++;
            return 'bar';
        })->bind('foo', function() use (&$biz){
            $biz++;
            return 'biz';
        });

        try {
            $hook->trigger('foo', 'data');
            static::fail('It should throw exception if hook function return value.');
        } catch (HookTriggerException $e) {
            static::assertEquals('bar', $e->receive);
            static::assertSame($hook, $e->hook);
            static::assertEquals('data', $e->data);
            static::assertEquals(1, $e->step);
            static::assertCount(3, $e->events);
            static::assertInstanceOf(Event::class, $e->event);
            static::assertEquals(Hook::WHEN, $e->type);
            static::assertEquals([], $hook->getTriggered());
        }
        static::assertEquals([], $hook->getTriggered());
        static::assertEquals(1, $foo);
        static::assertEquals(1, $bar);
        static::assertEquals(0, $biz);

        try {
            $hook->trigger('foo', 'data');
            static::fail('It should throw exception if hook function return value.');
        } catch (HookTriggerException $e) {
            static::assertEquals(1, $e->step);
            static::assertEquals('bar', $e->receive);
            try {
                $e->continues();
                static::fail('It should throw exception if hook function return value.');
            } catch (HookTriggerException $ee) {
                static::assertEquals(2, $ee->step);
                static::assertEquals('biz', $ee->receive);
                static::assertCount(3, $ee->events);
                static::assertEquals(Hook::WHEN, $ee->type);
                static::assertEquals([], $hook->getTriggered());
            }
        }
        static::assertEquals([], $hook->getTriggered());
        static::assertEquals(2, $foo);
        static::assertEquals(2, $bar);
        static::assertEquals(1, $biz);

        try {
            $hook->trigger('foo', 'data');
            static::fail('It should throw exception if hook function return value.');
        } catch (HookTriggerException $e) {
            $e->continues(true);
        }
        static::assertEquals(['foo' => 1], $hook->getTriggered());
        static::assertEquals(3, $foo);
        static::assertEquals(3, $bar);
        static::assertEquals(2, $biz);
    }
}

class _HookData
{
    public $test;

    public function __construct($test)
    {
        $this->test = $test;
    }
}
