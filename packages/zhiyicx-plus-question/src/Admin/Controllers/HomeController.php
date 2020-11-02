<?php

/*
 * +----------------------------------------------------------------------+
 * |                          ThinkSNS Plus                               |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 2017 Chengdu ZhiYiChuangXiang Technology Co., Ltd.     |
 * +----------------------------------------------------------------------+
 * | This source file is subject to version 2.0 of the Apache license,    |
 * | that is bundled with this package in the file LICENSE, and is        |
 * | available through the world-wide-web at the following url:           |
 * | http://www.apache.org/licenses/LICENSE-2.0.html                      |
 * +----------------------------------------------------------------------+
 * | Author: Slim Kit Group <master@zhiyicx.com>                          |
 * | Homepage: www.thinksns.com                                           |
 * +----------------------------------------------------------------------+
 */

namespace SlimKit\PlusQuestion\Admin\Controllers;

use Illuminate\Http\Request;
use function Zhiyi\Plus\setting;
use Zhiyi\Plus\Auth\JWTAuthToken;

class HomeController extends Controller
{
    public function index(Request $request, JWTAuthToken $jwt)
    {
        config('jwt.single_auth', false);

        return view('plus-question::admin', [
            'api_token' => $jwt->create($request->user()),
        ]);
    }

    /**
     * Get Question & answer switch.
     *
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function switch()
    {
        return response()->json([
            'switch' => setting('Q&A', 'switch', true),
            'apply_amount' => setting('Q&A', 'apply-amount', 200),
            'onlookers_amount' =>  setting('Q&A', 'onlookers-amount', 100),
            'anonymity_rule' => setting('Q&A', 'anonymity-rule', '匿匿名规则，管理理后台’问答应⽤用-基本信息‘中可配置'),
            'reward_rule' => setting('Q&A', 'reward-rule', '悬赏规则，管理理后台’问答应⽤用-基本信息’中可配置'),
        ]);
    }

    /**
     * Store Question & Answer switch.
     *
     * @param \Illuminate\Http\Request $request
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function store(Request $request)
    {
        setting('Q&A')->set([
            'switch' => (bool) $request->input('switch'),
            'apply-amount' => (int) $request->input('apply_amount'),
            'onlookers-amount' => (int) $request->input('onlookers_amount'),
            'anonymity-rule' => $request->input('anonymity_rule'),
            'reward-rule' => $request->input('reward_rule'),
        ]);

        return response()->json(['message' => '设置成功'], 201);
    }
}
