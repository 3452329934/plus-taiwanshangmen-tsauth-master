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

namespace Zhiyi\Plus\Admin\Controllers\Setting;

use Illuminate\Http\Response;
use Zhiyi\Plus\Admin\Requests\SetJpushConfigure;
use function Zhiyi\Plus\setting;
use Illuminate\Http\JsonResponse;
use Zhiyi\Plus\Admin\Controllers\Controller;
use Zhiyi\Plus\Admin\Requests\SetWeChatMpConfigure as SetWeChatMpConfigureRequest;

class Jpush extends Controller
{
    /**
     * Get configure.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfigure(): JsonResponse
    {
        $settings = setting('user', 'vendor:jpush', [
            'app_key' => '',
            'master_secret' => '',
            'apns_production' => false,
            'switch' => false
        ]);

        return new JsonResponse($settings, Response::HTTP_OK);
    }

    /**
     * set configure.
     * @param  SetJpushConfigure  $request
     * @return \Illuminate\Http\Response
     * @throws \Throwable
     */
    public function setConfigure(SetJpushConfigure $request)
    {
        setting('user')->set('vendor:jpush', [
            'app_key' => $request->input('app_key'),
            'master_secret' => $request->input('master_secret'),
            'apns_production' => (bool) $request->input('apns_production'),
            'switch' => (bool) $request->input('switch')
        ]);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
