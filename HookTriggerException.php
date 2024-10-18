<?php
namespace Tanbolt\Hook;

use Exception;
use RuntimeException;

/**
 * Class HookTriggerException: Hook Trigger 返回值
 * @package Tanbolt\Hook
 *
 * 以下为只读属性
 * @property-read HookInterface $hook Hook对象实例
 * @property-read string $type 本次触发的 hook 类型
 * @property-read Event[] $events 当前正在触发的 hook 队列
 * @property-read int $step   本次触发执行的 Event 在 $events 中的下标
 * @property-read mixed $receive Hook回调执行时的返回值
 * @property-read mixed $data  本次触发的额外数据
 * @property-read Event $event 当前调用的 Event（即 $events[$step]）
 */
class HookTriggerException extends RuntimeException
{
    /**
     * @var HookInterface
     */
    private $hook;

    /**
     * @var string
     */
    private $type;

    /**
     * @var Event[]
     */
    private $events;

    /**
     * @var int
     */
    private $step;

    /**
     * @var mixed
     */
    private $receive;

    /**
     * @var mixed
     */
    private $data;

    /**
     * HookTriggerException constructor.
     * @param HookInterface $hook
     * @param string $type
     * @param Event[] $events
     * @param int $step
     * @param mixed $receive
     * @param mixed $data
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct(
        HookInterface $hook,
        string $type,
        array $events,
        int $step,
        $receive,
        $data = null,
        int $code = 0,
        Exception $previous = null
    ) {
        $this->hook = $hook;
        $this->type = $type;
        $this->events = $events;
        $this->step = $step;
        $this->receive = $receive;
        $this->data = $data;
        parent::__construct('Hook function must be return void or null.', $code, $previous);
    }

    /**
     * 继续执行本次 trigger 未执行的动作
     * @param bool $untilEnd   (不但忽略本次错误, 且忽略之后所有错误)
     */
    public function continues(bool $untilEnd = false)
    {
        $this->hook->triggerEvents($this->events, $this->data, $this->type, $this->step + 1, !$untilEnd);
    }

    /**
     * 获取当前 hook 异常的属性值
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        if ('event' === $name) {
            if (!isset($this->events[$this->step])) {
                throw new HookException('Current event is not exist');
            }
            return $this->events[$this->step];
        }
        return $this->{$name};
    }
}
