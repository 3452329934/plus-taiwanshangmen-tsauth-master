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

namespace SlimKit\PlusQuestion\Providers;

use function Zhiyi\Plus\setting;
use Illuminate\Support\ServiceProvider;
use Zhiyi\Plus\Support\ManageRepository;
use Zhiyi\Plus\Support\BootstrapAPIsEventer;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the service provider.
     *
     * @return void
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function boot()
    {
        $this->loadRoutesFrom(
            $this->app->make('path.question').'/router.php'
        );

        // Publish admin menu.
        $this->app->make(ManageRepository::class)->loadManageFrom('问答应用', 'plus-question::admin', [
            'route' => true,
            'icon' => '问',
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function register()
    {
        // Register Bootstraper API event.
        $this->app->make(BootstrapAPIsEventer::class)->listen('v2', function () {
            return [
                'Q&A' => [
                    'switch' => setting('Q&A', 'switch', true),
                    'apply_amount' => (int) setting('Q&A', 'apply-amount', 200),
                    'onlookers_amount' =>  (int) setting('Q&A', 'onlookers-amount', 100),
                    'anonymity_rule' => setting('Q&A', 'anonymity-rule', '匿匿名规则，管理理后台’问答应⽤用-基本信息‘中可配置'),
                    'reward_rule' => setting('Q&A', 'reward-rule', '悬赏规则，管理理后台’问答应⽤用-基本信息’中可配置'),
                ],
            ];
        });
    }
}
