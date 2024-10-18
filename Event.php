<?php
namespace Tanbolt\Hook;

use ArrayAccess;

/**
 * Class Event: 触发 Hook 时的事件信息
 * @package Tanbolt\Hook
 *
 * @property-read string $type  绑定类型 ex: hook.bind() -> $type=Hook::WHEN | hook.on() -> $type=Hook::ON
 * @property-read string $group 绑定分组 ex: hook.on('group@prefix/*')  $group=group
 * @property-read string $name 绑定事件 ex: hook.on('group@prefix/*')  $group=prefix/*
 * @property-read callable|string|array $handler  绑定回调 ex: hook.on('event', 'function')  $handler='function'
 * @property-read array  $bag      绑定数据 ex: hook.on('event', 'function', ['foo' => 'foo'])  $bag=['foo' => 'foo']
 * @property-read int    $priority 绑定等级 ex: hook.on('event', 'function', [], 20) -> $priority=20
 * @property-read string $trigger  当前触发名称 ex: hook.trigger('prefix/foo') -> $trigger=prefix/foo
 * @property-read bool $propagationStopped  是否已中断执行
 * @property-read bool $propagationOff  是否已解除绑定
 */
class Event implements ArrayAccess
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $group;

    /**
     * @var string
     */
    private $name;

    /**
     * @var callable|string|array
     */
    private $handler;

    /**
     * @var array
     */
    private $bag;

    /**
     * @var int
     */
    private $priority;

    /**
     * @var string
     */
    private $trigger;

    /**
     * @var bool
     */
    private $propagationStopped;

    /**
     * @var bool
     */
    private $propagationOff;

    /**
     * Event constructor.
     * @param string $type
     * @param string $group
     * @param string $name
     * @param callable|string|array $handler
     * @param array $bag
     * @param int $priority
     */
    public function __construct(string $type, string $group, string $name, $handler, array $bag, int $priority) {
        $this->type = $type;
        $this->group = $group;
        $this->name = $name;
        $this->handler = $handler;
        $this->bag = $bag;
        $this->priority = $priority;
        // 可通过 __setProperty 设置
        $this->trigger = null;
        $this->propagationStopped = false;
        $this->propagationOff = false;
    }

    /**
     * 终止 hook，比如绑定了三个钩子：
     *      hook.on("group@prefix/*", 'function');
     *      hook.on("group@prefix/foo", 'function');
     *      hook.on("group@prefix/foo/*", 'function');
     * 触发钩子：
     *      hook.trigger("group@prefix/foo/bar");
     *
     * 根据规则，三个钩子应该按顺序触发，但若任何一个钩子内部调用了 stopPropagation()，在他之后的钩子便不再执行
     * @return $this
     */
    public function stopPropagation()
    {
        $this->propagationStopped = true;
        return $this;
    }

    /**
     * 解绑钩子，一旦调用，本次执行之后，该钩子再也不会被触发
     * @return bool
     */
    public function off()
    {
        return $this->propagationOff = true;
    }

    /**
     * Bag: 获取指定 bag 值
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getBag(string $name, $default = null)
    {
        return $this->bag[$name] ?? $default;
    }

    /**
     * Bag: 设置指定 bag 值
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setBag(string $name, $value)
    {
        $this->bag[$name] = $value;
        return $this;
    }

    /**
     * Bag: 移除指定 bag 值
     * @param string $name
     * @return $this
     */
    public function removeBag(string $name)
    {
        unset($this->bag[$name]);
        return $this;
    }

    /**
     * Bag: 是否存在 bag 值
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->bag[$offset]);
    }

    /**
     * Bag: 获取 bag 值
     * @param mixed $offset
     * @return array|null
     */
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            $trace = debug_backtrace();
            trigger_error('Undefined index: '.$offset.' in '.$trace[0]['file'].' on line '.$trace[0]['line']);
        }
        return $this->getBag($offset);
    }

    /**
     * Bag: 设置 bag 值
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->setBag($offset,$value);
    }

    /**
     * Bag: 移除 bag 值
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->removeBag($offset);
    }

    /**
     * 给 Hook 对象提供一个设置当前对象属性值的接口。
     * 用于 Hook 组件内部, 在执行 Hook Handler 时, 不应使用该接口
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function __setProperty(string $key, $value)
    {
        $this->{$key} = $value;
        return $this;
    }

    /**
     * 获取 Event 只读属性值
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->{$name};
    }
}
