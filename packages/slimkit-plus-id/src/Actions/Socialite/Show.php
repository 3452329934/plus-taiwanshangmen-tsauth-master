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

namespace SlimKit\PlusID\Actions\Socialite;

use SlimKit\PlusID\Actions\Action;
use SlimKit\PlusID\Support\Message;
use SlimKit\PlusSocialite\Models\UserSocialite;

class Show extends Action
{
    public function getSignAction(): array
    {
        return [
            'app' => $this->client->id,
            'action' => 'socialite/show',
            'time' => (int) $this->request->time,
        ];
    }

    public function check()
    {
        if (($response = parent::check()) !== true) {
            return $response;
        }

        return true;
    }

    public function dispatch()
    {
        $socialite = UserSocialite::where('type', $this->request->type)
                        ->where('union_id', $this->request->union_id)
                        ->first();
        $user_id = $socialite ? $socialite->user_id : '';

        return $this->response(new Message(200, 'success', ['user' => $user_id]));
    }
}
