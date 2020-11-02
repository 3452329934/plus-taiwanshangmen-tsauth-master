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

namespace SlimKit\PlusQuestion\API2\Controllers;

use function Zhiyi\Plus\setting;

class QuestionConfigController extends Controller
{
    /**
     * 获取问答相关配置.
     *
     * @return mixed
     * @author BS <414606094@qq.com>
     */
    public function get()
    {
        return response()->json([
            'apply_amount' => setting('Q&A', 'apply-amount', 200),
            'onlookers_amount' => setting('Q&A', 'onlookers-amount', 100),
            'anonymity_rule' => setting('Q&A', 'anonymity-rule', '匿匿名规则，管理理后台’问答应⽤用-基本信息‘中可配置'),
            'reward_rule' => setting('Q&A', 'reward-rule', '悬赏规则，管理理后台’问答应⽤用-基本信息’中可配置'),
        ], 200);
    }
}
