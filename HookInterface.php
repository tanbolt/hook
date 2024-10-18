<?php
namespace Tanbolt\Hook;

use Closure;

/**
 * Interface HookInterface: 钩子接口
 * @package Tanbolt\Hook
 */
interface HookInterface
{
    /**
     * 绑定一个事件 hook
     * @param string $event  事件名,可使用 * 通配符
     * @param Closure|string|array $handler 回调
     * @param array $bag 附带数据
     * @param int $priority 优先级
     * @param ?string $type 类型
     * @return static
     */
    public function bind(string $event, $handler, array $bag = [], int $priority = 0, string $type = null);

    /**
     * bind 快捷方式: 添加 on 类型的事件 hook
     * @param string $event  事件名,可使用 * 通配符
     * @param Closure|string|array $handler 回调
     * @param array $bag 附带数据
     * @param int $priority 优先级
     * @return static
     */
    public function on(string $event, $handler, array $bag = [], int $priority = 0);

    /**
     * bind 快捷方式: 添加 before 类型的事件 hook
     * @param string $event  事件名,可使用 * 通配符
     * @param Closure|string|array $handler 回调
     * @param array $bag 附带数据
     * @param int $priority 优先级
     * @return static
     */
    public function before(string $event, $handler, array $bag = [], int $priority = 0);

    /**
     * bind 快捷方式: 添加 after 类型的事件 hook
     * @param string $event  事件名,可使用 * 通配符
     * @param Closure|string|array $handler 回调
     * @param array $bag 附带数据
     * @param int $priority 优先级
     * @return static
     */
    public function after(string $event, $handler, array $bag = [], int $priority = 0);

    /**
     * 移除 $type 类型下已绑定的 $event 事件 hook
     * @param string $event 事件
     * @param ?string $type 类型
     * @return static
     */
    public function off(string $event, string $type = null);

    /**
     * 移除 $type 类型下 $event 事件中指定的 hook
     * @param string $event 事件
     * @param Closure|string|array $handler 回调
     * @param ?string $type 类型
     * @return static
     */
    public function offHandler(string $event, $handler, string $type = null);

    /**
     * 移除 $type 类型下 $group 分组的所有 hook
     * @param string $group 分组
     * @param ?string $type 类型
     * @return static
     */
    public function offGroup(string $group, string $type = null);

    /**
     * 获取已绑定的 hook
     * @param string $trigger 触发名称，如 "group@prefix/foo/"，不能包含通配符
     * @param ?string $type hook 类型, 不指定则获取所有类型
     * @return Event[]
     */
    public function queue(string $trigger, string $type = null);

    /**
     * 触发 $type 类型下匹配的 hook
     * @param string $trigger 触发名称，如 "group@prefix/foo/"，不能包含通配符
     * @param mixed $data 传递给触发 Event 额外的数据
     * @param ?string $type hook 类型, 不指定则触发所有类型
     * @return static
     */
    public function trigger(string $trigger, $data = null, string $type = null);

    /**
     * 触发指定的 hook events list, 外部一般用不到, 主要是为了给 HookTriggerException 暴漏接口
     * @param Event[] $events 要出发的 Event 列表
     * @param mixed $data 触发额外携带的数据
     * @param ?string $type 触发的 hook 类型
     * @param int $step 当前触发的 Event 在 $events 中的下标
     * @param bool $throw 如遇异常，是否抛出
     * @return static
     */
    public function triggerEvents(array $events, $data = null, string $type = null, int $step = 0, bool $throw = true);

    /**
     * 获取 $type 类型下 trigger 过 event 次数, 用来做 hook 触发统计
     * @param ?string $type 指定要获取的 hook 类型，不设置则获取所有类型
     * @return array
     */
    public function getTriggered(string $type = null);
}
