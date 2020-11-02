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

namespace Zhiyi\PlusGroup\API\Controllers;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Zhiyi\PlusGroup\CacheNames;
use Zhiyi\Plus\Models\CurrencyType;
use \Illuminate\Routing\Controller;
use Zhiyi\Plus\Http\Middleware\VerifyUserPassword;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Zhiyi\Plus\Packages\Currency\Processes\User as UserProcess;
use Zhiyi\Plus\Notifications\System as SystemNotification;
use Zhiyi\PlusGroup\Models\Post;

class NewPostRewardController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this
            ->middleware(VerifyUserPassword::class)
            ->only(['store']);
    }

    /**
     * 打赏操作.
     *
     * @param  Request  $request
     * @param  int  $post
     * @param  UserProcess  $process
     * @param  ConfigRepository  $config
     *
     * @return mixed
     * @throws \Exception
     * @author BS <414606094@qq.com>
     */
    public function store(Request $request, int $post, UserProcess
    $process, ConfigRepository $config)
    {
        if (!$config->get('plus-group.group_reward.status')) {
            return response()->json(['message' => '打赏功能已关闭'], 422);
        }
        $user = $request->user();
        // 判断锁
        if (Cache::has(sprintf(CacheNames::REWARD_POST_LOCK, $post,
            $user->id))
        ) {
            return response('请求太频繁了', 429);
        }
        // 加锁
        Cache::forever(sprintf(CacheNames::REWARD_POST_LOCK, $post,
        $user->id), true);
        /* 获取帖子内容 */
        $post = Post::query()->with([
            'user' => function (HasOne $belongs_to) {
                $belongs_to->withTrashed();
            }
        ])->find($post);
        $currencyName = CurrencyType::current('name');
        if ($post->user_id) {
            $amount = (int)$request->input('amount');
        }
        if (!$amount || $amount < 0) {
            Cache::forget(sprintf(CacheNames::REWARD_POST_LOCK, $post->id,
                $user->id));
            return response()->json([
                'message' => '请输入正确的' . $currencyName . '数量',
            ], 422);
        }

        if ($post->user_id === $user->id) {
            Cache::forget(sprintf(CacheNames::REWARD_POST_LOCK, $post->id,
                $user->id));
            return response()->json(['message' => '不能打赏自己发布的帖子'], 422);
        }

        $target = $post->user;

        if (!$user->currency || $user->currency->sum < $amount) {
            Cache::forget(sprintf(CacheNames::REWARD_POST_LOCK, $post->id,
                $user->id));
            return response()->json(['message' => $currencyName . '不足'], 403);
        }
        $pay = $process->prepayment($user, $amount, $target->id, sprintf('打赏“%s”的帖子', $target->name, $post->title, $amount), sprintf('打赏“%s”的帖子，%s扣除%s', $target->name, $currencyName, $amount));

        $paid = $process->receivables($target, $amount, $user->id, sprintf('“%s”打赏了你的帖子', $user->name), sprintf('“%s”打赏了你的帖子，%s增加%s', $user->name, $currencyName, $amount));

        if ($pay && $paid) {
            // 打赏记录
            $post->rewards()->create([
                'user_id' => $user->id,
                'target_user' => $target->id,
                'amount' => $amount,
            ]);
            $notice = sprintf('“%s”打赏了你的帖子', $user->name);
            $target->notify(new SystemNotification($notice, [
                'type' => 'group:post-reward',
                'post' => [
                    'id' => $post->id,
                    'title' => $post->title,
                ],
                'group_id' => $post->group_id,
                'sender' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
            ]));
            Cache::forget(sprintf(CacheNames::REWARD_POST_LOCK, $post->id,
                $user->id));

            return response()->json(['message' => '打赏成功'], 201);
        } else {
            Cache::forget(sprintf(CacheNames::REWARD_POST_LOCK, $post->id,
                $user->id));

            return response()->json(['message' => '打赏失败'], 500);
        }
    }
}
