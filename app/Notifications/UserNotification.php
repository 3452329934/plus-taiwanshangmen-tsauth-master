<?php

declare(strict_types=1);

/*
 * +----------------------------------------------------------------------+
 * |                          ThinkSNS Plus                               |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 2016-Present ZhiYiChuangXiang Technology Co., Ltd.     |
 * +----------------------------------------------------------------------+
 * | This source file is subject to enterprise private license, that is   |
 * | bundled with this package in the file LICENSE, and is available      |
 * | through the world-wide-web at the following url:                     |
 * | https://github.com/slimkit/plus/blob/master/LICENSE                  |
 * +----------------------------------------------------------------------+
 * | Author: Slim Kit Group <master@zhiyicx.com>                          |
 * | Homepage: www.thinksns.com                                           |
 * +----------------------------------------------------------------------+
 */

namespace Zhiyi\Plus\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Zhiyi\Plus\Notifications\Channels\JPushChannel;
use Zhiyi\Plus\Notifications\Messages\UserNotificationMessage;
use function Zhiyi\Plus\setting;

class UserNotification extends Notification implements ShouldQueue
{
    use Queueable;
    /**
     * The notification message.
     *
     * @var UserNotificationMessage
     */
    protected $message;
    /**
     * @var \Zhiyi\Plus\Support\any|\Zhiyi\Plus\Support\Setting
     */
    protected static $jpushConfig;

    /**
     * Create a new notification instance.
     *
     * @param  Messages\UserNotificationMessage  $message
     */
    public function __construct(UserNotificationMessage $message)
    {
        $this->message = $message;
        self::$jpushConfig = setting('user', 'vendor:jpush', [
            'app_key' => '',
            'master_secret' => '',
            'apns_production' => false,
            'switch' => false
        ]);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via()
    : array
    {
        return self::$jpushConfig['switch'] ? ['database', JPushChannel::class] : ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array
     */
    public function toArray()
    : array
    {
        return $this->message->toArray();
    }
}
