<?php
namespace Tanbolt\Hook;

use Tanbolt\Container\ContainerInterface;

/**
 * Class Hook
 * @package Tanbolt\Hook
 */
class Hook implements HookInterface
{
    const WHEN = 'when';
    const ON = 'on';
    const BEFORE = 'before';
    const AFTER = 'after';
    const PRIORITY = 100;
    const DEFAULT_GROUP = 'system';

    /**
     * @var ContainerInterface
     */
    private $container = null;

    /**
     * 绑定 hook 容器
     * @var array
     */
    private $hooks = [];

    /**
     * 正则表达式缓存
     * @var array
     */
    private $regexps = [];

    /**
     * 触发过的 hook event 统计
     * @var array
     */
    private $triggered = [];

    /**
     * 调用 trigger 触发的 event (去重, 一次性)
     * @var array
     */
    private $triggeredOnce = [];

    /**
     * 从 event 名称获取其 group 并去除
     * @param string $event
     * @return string
     */
    protected static function groupName(string &$event)
    {
        if (strpos($event, '@') !== false) {
            list($group, $event) = explode('@', $event, 2);
        } else {
            $group = static::DEFAULT_GROUP;
        }
        return $group;
    }

    /**
     * 绑定 IOC 对象，这样在执行匹配 hook 所绑定的回调函数时，就会通过 IOC 执行
     * @param ContainerInterface|null $container
     * @return $this
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
        return $this;
    }

    /**
     * 绑定一个事件 hook, 缺省 $type 为 self::WHEN
     * @inheritdoc
     */
    public function bind(string $event, $handler, array $bag = [], int $priority = self::PRIORITY, string $type = null) {
        $type = $type ?: self::WHEN;
        $group = static::groupName($event);
        if (!isset($this->hooks[$group])) {
            $this->hooks[$group] = [];
        }
        if (!isset($this->hooks[$group][$type])) {
            $this->hooks[$group][$type] = [];
        }
        if (!isset($this->hooks[$group][$type][$event])) {
            $this->hooks[$group][$type][$event] = [];
        }
        $this->hooks[$group][$type][$event][] = [
            'handler' => $handler,
            'bag' => $bag,
            'priority' => $priority,
        ];
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function on(string $event, $handler, array $bag = [], int $priority = self::PRIORITY)
    {
        return $this->bind($event, $handler, $bag, $priority, self::ON);
    }

    /**
     * @inheritdoc
     */
    public function before(string $event, $handler, array $bag = [], int $priority = self::PRIORITY)
    {
        return $this->bind($event, $handler, $bag, $priority, self::BEFORE);
    }

    /**
     * @inheritdoc
     */
    public function after(string $event, $handler, array $bag = [], int $priority = self::PRIORITY)
    {
        return $this->bind($event, $handler, $bag, $priority, self::AFTER);
    }

    /**
     * @inheritdoc
     */
    public function off(string $event, string $type = null)
    {
        $type = $type ?? self::WHEN;
        $group = static::groupName($event);
        if (!isset($this->hooks[$group]) ||
            !isset($this->hooks[$group][$type]) ||
            !isset($this->hooks[$group][$type][$event])
        ) {
            return $this;
        }
        unset($this->hooks[$group][$type][$event]);
        if (!count($this->hooks[$group][$type])) {
            unset($this->hooks[$group][$type]);
        }
        if (!count($this->hooks[$group])) {
            unset($this->hooks[$group]);
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function offHandler(string $event, $handler, string $type = null)
    {
        $type = $type ?? self::WHEN;
        $group = static::groupName($event);
        if (!$handler || !isset($this->hooks[$group]) || !isset($this->hooks[$group][$type]) ||
            !isset($this->hooks[$group][$type][$event])
        ) {
            return $this;
        }
        foreach ($this->hooks[$group][$type][$event] as $key => $listener) {
            if (($listener instanceof Event && $handler === $listener->handler) ||
                (is_array($listener) && $handler === $listener['handler'])
            ) {
                unset($this->hooks[$group][$type][$event][$key]);
            }
        }
        if (!count($this->hooks[$group][$type][$event])) {
            unset($this->hooks[$group][$type][$event]);
        }
        if (!count($this->hooks[$group][$type])) {
            unset($this->hooks[$group][$type]);
        }
        if (!count($this->hooks[$group])) {
            unset($this->hooks[$group]);
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function offGroup(string $group, string $type = null)
    {
        $type = $type ?? self::WHEN;
        if (isset($this->hooks[$group][$type])) {
            unset($this->hooks[$group][$type]);
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function queue(string $trigger, string $type = null)
    {
        $type = $type ?? self::WHEN;
        $group = static::groupName($trigger);
        if (!isset($this->hooks[$group]) || !isset($this->hooks[$group][$type]) ||  !$this->hooks[$group][$type]) {
            return [];
        }
        $count = 0;
        $order = [];
        $priority = [];
        $queues = [];
        foreach ($this->hooks[$group][$type] as $name => $listeners) {
            if (!$this->isMatch($name, $trigger)) {
                continue;
            }
            foreach ($listeners as $key => $listener) {
                if (!$listener instanceof Event) {
                    $listener = new Event(
                        $type,
                        $group,
                        $name,
                        $listener['handler'],
                        $listener['bag'],
                        $listener['priority']
                    );
                } elseif ($listener->propagationOff) {
                    unset($this->hooks[$group][$type][$name][$key]);
                    continue;
                }
                /** @var $listener Event */
                $listener->__setProperty('trigger', $trigger)->__setProperty('propagationStopped', false);
                $priority[] = $listener->priority;
                $order[] = ++$count;
                $queues[] = $listener;
                $this->hooks[$group][$type][$name][$key] = $listener;
            }
        }
        if ($queues) {
            array_multisort($priority, SORT_DESC, $order, SORT_ASC, $queues);
        }
        return $queues;
    }

    /**
     * 匹配 event 名称
     * @param string $name
     * @param string $trigger
     * @return bool
     */
    protected function isMatch(string $name, string $trigger)
    {
        if ($name === $trigger) {
            return true;
        }
        if (false === strpos($name, '*')) {
            return false;
        }
        if (!isset($this->regexps[$name])) {
            $this->regexps[$name] =
                '#^' .
                str_replace('~m~', '([\s\S]*?)', preg_quote(str_replace('*', '~m~', $name), '/')) .
                '$#';
        }
        return preg_match($this->regexps[$name], $trigger);
    }

    /**
     * @inheritdoc
     */
    public function trigger(string $trigger, $data = null, string $type = null)
    {
        return $this->triggerEvents($this->queue($trigger, $type), $data, $type);
    }

    /**
     * @inheritdoc
     */
    public function triggerEvents(array $events, $data = null, string $type = null, int $step = 0, bool $throw = true)
    {
        if (!$step) {
            $this->triggeredOnce = [];
        }
        // $listeners 回调执行完毕, 终止返回
        $type = $type ?: self::WHEN;
        if (!isset($events[$step])) {
            return $this->addTriggered($type);
        }
        $event = $events[$step];
        // 记录本次触发, 若同一个 eventName 监听多次, 只记录一次
        $eventName = (self::DEFAULT_GROUP === $event->group ? '' : $event->group.'@').$event->name;
        if (!in_array($eventName, $this->triggeredOnce)) {
            $this->triggeredOnce[] = $eventName;
        }
        // 调用第 $step 个 $event, 若 $receive !== null, 意味着 hook event 回调中抛出了中断异常
        $receive = $this->handlerTriggered($event, $data);
        if ($throw && null !== $receive) {
            throw new HookTriggerException($this, $type, $events, $step, $receive, $data);
        }
        // 确认 hook event 是否阻止了继续执行
        if ($event->propagationStopped) {
            return $this->addTriggered($type);
        }
        return $this->triggerEvents($events, $data, $type, ++$step, $throw);
    }

    /**
     * 触发 hook 绑定的回调函数, 参数为 $event,$data
     * @param Event $event
     * @param mixed $data
     * @return mixed
     */
    protected function handlerTriggered(Event $event, $data)
    {
        if ($this->container) {
            return $this->container->call($event->handler, $event, $data);
        }
        if (!is_callable($event->handler)) {
            throw new HookException(
                'Hook trigger handler not found or invalid callable'
            );
        }
        return call_user_func($event->handler, $event, $data);
    }

    /**
     * 记录本次 listener hook, 以便统计不同 event 的触发次数
     * @param string $type
     * @return $this
     */
    protected function addTriggered(string $type)
    {
        if (!isset($this->triggered[$type])) {
            $this->triggered[$type] = [];
        }
        foreach ($this->triggeredOnce as $name) {
            if (!isset($this->triggered[$type][$name])) {
                $this->triggered[$type][$name] = 1;
            } else {
                $this->triggered[$type][$name]++;
            }
        }
        $this->triggeredOnce = [];
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTriggered(string $type = null)
    {
        $type = $type ?: self::WHEN;
        return $this->triggered[$type] ?? [];
    }

    /**
     * @return $this
     */
    public function __destruct()
    {
        $this->triggered = [];
        $this->triggeredOnce = [];
        return $this;
    }
}
