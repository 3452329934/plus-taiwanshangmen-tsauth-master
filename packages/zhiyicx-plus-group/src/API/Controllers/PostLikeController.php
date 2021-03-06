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

use Illuminate\Http\Request;
use Zhiyi\PlusGroup\Models\GroupMember;
use Zhiyi\PlusGroup\Models\Post as GroupPostModel;
use Zhiyi\Plus\Notifications\Like as LikeNotification;

class PostLikeController
{
    /**
     * list of likes.
     *
     * @param Request        $request
     * @param GroupPostModel $post
     * @return mixed
     * @throws \Throwable
     * @author BS <414606094@qq.com>
     */
    public function index(Request $request, GroupPostModel $post)
    {
        $limit = $request->query('limit', 15);
        $after = $request->query('after', 0);
        $user_id = $request->user('api')->id ?? 0;
        $likes = $post->likes()
            ->with([
                'user' => function ($query) {
                    $query->withTrashed();
                },
            ])
            ->when($after, function ($query) use ($after) {
                return $query->where('id', '<', $after);
            })
            ->limit($limit)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(
            $post->getConnection()->transaction(function () use ($likes, $user_id) {
                return $likes->map(function ($like) use ($user_id) {
                    if (!$like->relationLoaded('user')) {
                        return $like;
                    }

                    $like->user->following = false;
                    $like->user->follower = false;

                    if ($user_id && $like->user_id !== $user_id) {
                        $like->user->following = $like->user->hasFollwing($user_id);
                        $like->user->follower = $like->user->hasFollower($user_id);
                    }

                    return $like;
                });
            })
        )->setStatusCode(200);
    }

    /**
     * like a post.
     *
     * @param  Request  $request
     * @param  GroupPostModel  $post
     * @param  GroupMember  $member
     * @return mixed
     * @author BS <414606094@qq.com>
     */
    public function store(Request $request, GroupPostModel $post, GroupMember $member)
    {
        $user = $request->user();

        if ($post->liked($user)) {
            return response()->json(['message' => '已经赞过，请勿重复操作'], 422);
        }

        $group = $post->group;
        $member = $group->members()
            ->where('user_id', $user->id)
            ->where('audit', 1)
            ->first();
        if ($group->mode != 'public' && !$member) {
            return response()->json(['message' => '你没有点赞权限'], 403);
        }

        if ($member && $member->disabled == 1) {
            return response()->json(['message' => '您已被该圈子拉黑，无法进行该操作'], 403);
        }

        if ($post->user && $user) {
            $like = $post->like($user);
            $post->user->notify(new LikeNotification('圈子帖子', $like, $user));
        }

        return response()->json(['message' => '操作成功'])->setStatusCode(201);
    }

    /**
     * cacel like a post.
     *
     * @param Request        $request
     * @param GroupPostModel $post
     * @return mixed
     * @author BS <414606094@qq.com>
     */
    public function cancel(Request $request, GroupPostModel $post)
    {
        $user = $request->user();
        if (!$post->liked($user)) {
            return response()->json(['message' => '尚未点赞'], 422);
        }

        $post->unlike($user);

        return response()->json()->setStatusCode(204);
    }
}
