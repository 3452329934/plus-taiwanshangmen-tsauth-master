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

use DB;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Zhiyi\Plus\Utils\Markdown;
use Zhiyi\PlusGroup\Models\Post;
use Zhiyi\PlusGroup\Models\Group;
use Zhiyi\PlusGroup\Models\Pinned;
use Illuminate\Database\Eloquent\Model;
use Zhiyi\PlusGroup\Models\GroupMember;
use Zhiyi\Plus\Models\FileWith as FileWithModel;
use Zhiyi\PlusGroup\Repository\Post as PostRepository;
use Zhiyi\PlusGroup\API\Requests\CreateGroupPostRequest;
use Zhiyi\Component\ZhiyiPlus\PlusComponentFeed\Models\Feed as FeedModel;

class PostController
{
    /**
     * Get post list.
     *
     * @param Request        $request
     * @param Group          $group
     * @param PostRepository $repository
     * @return json.
     */
    public function index(Request $request, Group $group, PostRepository $repository)
    {
        $user = $request->user('api')->id ?? 0;
        $limit = $request->query('limit', 15);
        $offset = $request->query('offset', 0);
        $type = $request->query('type');

        $posts = $group
            ->posts()
            ->whereDoesntHave('blacks', function ($query) use ($user) {
                $query->where('user_id', $user);
            })
            ->when($excellent = $request->query('excellent', false), function ($query) {
                return $query->whereNotNull('excellent_at');
            })
            ->select([
                'id',
                'group_id',
                'title',
                'user_id',
                'summary',
                'likes_count',
                'views_count',
                'comments_count',
                'excellent_at',
                'created_at',
            ])
            ->with('images')
            ->orderBy($type === 'latest_reply' ? 'comment_updated_at' : 'id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        $user = $request->user('api') ?? 0;
        $items = $posts->map(function ($post) use ($user, $repository) {
            $repository->formatCommonList($user, $post);
            return $post;
        });
        return response()->json([
            'pinneds' => ($type == 'latest_reply' || $offset > 0) ? [] : $this->pinneds($request, $repository),
            'posts' => $items,
        ], 200);
    }

    /**
     * list pinned posts for a group.
     * @param \Illuminate\Http\Request         $request
     * @param \Zhiyi\PlusGroup\Repository\Post $repository
     * @return array
     */
    public function pinneds(Request $request, PostRepository $repository)
    : array
    {
        $user = $request->user('api')->id ?? null;
        $group = $request->group->id;
        $pinneds = (new Pinned)
            ->query()
            ->whereHas('post.group', function ($query) use ($group) {
                $query->where('id', $group);
            })
            ->where('channel', 'post')
            ->where('expires_at', '>', new Carbon)
            ->get();
        $pinneds->load([
            'post',
            'post.user' => function ($query) {
                $query->withTrashed();
            },
            'post.images',
        ]);

        return $pinneds->map(function (Pinned $pinned) {
            return $pinned->post;
        })->filter()->unique(function (Post $post) {
            return $post->id;
        })->each(function (Post $post) use ($user, $repository) {
            $repository->formatCommonList($user, $post);
        })->values()->all();
    }

    /**
     * Get a post.
     *
     * @param Post $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Group $group, Post $post, PostRepository $repository)
    {
        $user = $request->user('api') ?? 0;

        $post->increment('views_count');

        $repository->formatCommonDetail($user, $post);

        return response()->json($post, 200);
    }

    /**
     * 发布帖子.
     *
     * @param CreateGroupPostRequest $request
     * @param Group                  $group
     * @param PostRepository         $repository
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function store(CreateGroupPostRequest $request, Group $group, PostRepository $repository)
    {
        $user = $request->user();

        if ($group->audit !== 1) {
            return response()->json(['message' => '圈子审核未通过或被拒绝'], 403);
        }

        $member = GroupMember::where('user_id', $user->id)
            ->where('group_id', $group->id)
            ->first();

        if (is_null($member)) {
            return response()->json(['message' => '未加入该圈子,不能进行发帖'], 403);
        }

        if ($member->audit != 1 || $member->disabled == 1) {
            return response()->json(['message' => '审核未通过或已被加入黑名单,不能进行发帖'], 403);
        }

        if (!in_array($member->role, explode(',', $group->permissions))) {
            return response()->json(['message' => '没有发帖权限'], 422);
        }

        $fileWiths = $this->makeFileWith($request);

        DB::beginTransaction();

        try {
            $post = Post::create($this->fillRequestData($request, $group));

            $group->increment('posts_count');

            // save file.
            $this->saveFileWithByModel($post, $fileWiths);

            // sync to feed.
            $this->syncPostToFeed($request, $group, $fileWiths);

            DB::commit();

            return response()->json(['message' => '操作成功', 'post' => $repository->formatCommonDetail($user, $post)], 201);
        }
        catch (\Exception $e) {
            DB::rollback();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a group post.
     *
     * @param CreateGroupPostRequest $request
     * @param Group                  $group
     * @param Post                   $post
     * @param PostRepository         $repository
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function update(CreateGroupPostRequest $request, Group $group, Post $post, PostRepository $repository)
    {
        $user = $request->user();

        if ($post->user_id !== $user->id) {
            return response()->json(['message' => '无权限操作'], 403);
        }

        $fileWiths = $this->makeFileWith($request);

        DB::beginTransaction();

        try {
            if ($fileWiths->count()) {
                FileWithModel::where('raw', $post->id)->where('channel', 'group:post:image')->delete();
            }

            $post->update($this->fillRequestData($request, $group));

            // save file.
            $this->saveFileWithByModel($post, $fileWiths);

            // sync to feed.
            $this->syncPostToFeed($request, $group, $fileWiths);

            DB::commit();

            return response()->json(['message' => '操作成功', 'post' => $repository->formatCommonDetail($user, $post)], 201);
        }
        catch (\Exception $e) {
            DB::rollback();

            return response()->json(['message', $e->getMessage()], 500);
        }
    }

    /**
     * Fill request data.
     *
     * @param CreateGroupPostRequest $request
     * @param Group                  $group
     * @return array
     */
    protected function fillRequestData(CreateGroupPostRequest $request, Group $group)
    {
        return array_merge($request->only('title', 'summary'), [
            'user_id' => $request->user()->id,
            'group_id' => $group->id,
            'body' => app(Markdown::class)->safetyMarkdown(
                $request->input('body', '')
            ),
        ]);
    }

    /**
     * Sync to feed.
     *
     * @param Request $request
     * @param Group   $group
     * @param         $fileWiths
     * @throws \Throwable
     */
    protected function syncPostToFeed(Request $request, Group $group, $fileWiths)
    {
        $sync = (int)$request->input('sync_feed');

        if ($group->allow_feed && $sync == 1) {
            $user = $request->user();
            $feed = $this->fillFeedBaseData($request, new FeedModel());
            $feed->saveOrFail();
            $feed->getConnection()->transaction(function () use ($feed, $fileWiths, $user) {
                $this->saveFileWithByModel($feed, $fileWiths);
                $user->extra()->firstOrCreate([])->increment('feeds_count', 1);
            });
        }
    }

    /**
     * Fill initial feed data.
     *
     * @param \Illuminate\Http\Request                                 $request
     * @param \Zhiyi\Component\ZhiyiPlus\PlusComponentFeed\Models\Feed $feed
     * @author Seven Du <shiweidu@outlook.com>
     * @return FeedModel
     */
    protected function fillFeedBaseData(Request $request, FeedModel $feed)
    : FeedModel
    {
        $feed->feed_from = $request->input('feed_from');
        $feed->feed_mark = $request->user()->id . time();
        $feed->feed_content = $request->input('summary');
        $feed->feed_client_id = $request->ip();
        $feed->audit_status = 1;
        $feed->user_id = $request->user()->id;

        return $feed;
    }

    /**
     * 创建文件使用模型.
     *
     * @param Request $request
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    protected function makeFileWith(Request $request)
    {
        return FileWithModel::whereIn(
            'id',
            array_filter($request->input('images', []))
        )->where('channel', null)
            ->where('raw', null)
            ->where('user_id', $request->user()->id)
            ->get();
    }

    /**
     * By model save file with.
     *
     * @param Model   $model
     * @param         $fileWiths
     */
    protected function saveFileWithByModel(Model $model, $fileWiths)
    {
        if ($model instanceof Post) {
            foreach ($fileWiths as $fileWith) {
                $fileWith->channel = 'group:post:image';
                $fileWith->raw = $model->id;
                $fileWith->save();
            }
        } else {
            foreach ($fileWiths as $fileWith) {
                $fileWithModal = new FileWithModel();
                $fileWithModal->user_id = request()->user()->id;
                $fileWithModal->file_id = $fileWith->file_id;
                $fileWithModal->channel = 'feed:image';
                $fileWithModal->size = $fileWith->size;
                $fileWithModal->raw = $model->id;
                $fileWithModal->save();
            }
        }
    }

    /**
     * Delete post.
     *
     * @param Group $group
     * @param Post  $post
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function delete(Group $group, Post $post)
    {
        $user = request()->user();
        $member = GroupMember::select('role')
            ->where('user_id', $user->id)
            ->where('group_id', $group->id)
            ->first();
        if ($group->id != $post->group_id) {
            return response()->json(['message' => '操作资源不匹配'], 403);
        } elseif (is_null($member)) {
            return response()->json(['message' => '无操作权限'], 403);
        } elseif ($post->user_id != $user->id && !in_array($member->role, ['administrator', 'founder'])) {
            return response()->json(['message' => '无操作权限'], 403);
        }

        if ($post->excellent_at) {
            $group->excellen_posts_count -= 1;
            $group->excellen_posts_count = $group->excellen_posts_count <= 0 ? 0 : $group->excellen_posts_count;
        }

        $group->posts_count -= 1;
        $group->posts_count = $group->posts_count <= 0 ? 0 : $group->posts_count;
        $group->getConnection()->transaction(function () use ($group, $post) {
            $post->delete();
            $group->save();
        });

        return response()->json(null, 204);
    }

    /**
     * 我的帖子列表.
     *
     * @param Request $request
     * @return mixed
     */
    public function userPosts(Request $request, Carbon $datetime, PostRepository $repository)
    {
        $limit = $request->get('limit', 15);
        $offset = $request->get('offset', 0);
        $type = $request->get('type', 1); // 1-发布的 2- 已置顶 3-置顶待审

        $user = $request->user();

        $posts = Post::with([
            'user' => function ($query) {
                $query->withTrashed();
            },
        ])
            ->when($type > 1, function ($query) use ($type, $datetime) {
                switch ($type) {
                    case 2:
                        return $query->whereHas('pinned', function ($query) use ($datetime) {
                            return $query->where('expires_at', '>', $datetime);
                        });
                        break;
                    case 3:
                        return $query->whereHas('pinned', function ($query) use ($datetime) {
                            return $query->whereNull('expires_at');
                        });
                        break;
                }
            })
            ->whereHas('group', function ($query) {
                return $query->where('audit', 1);
            })
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $items = $posts->map(function ($post) use ($user, $repository) {
            $repository->formatCommonList($user, $post);

            return $post;
        });

        return response()->json($items, 200);
    }

    /**
     * 全部帖子.
     *
     * @param Request $request
     * @return mixed
     */
    public function posts(Request $request, PostRepository $repository)
    {
        $userId = $request->user('api')->id ?? 0;

        $keyword = $request->query('keyword');
        $limit = (int)$request->query('limit', 15);
        $offset = (int)$request->query('offset', 0);
        $groupId = (int)$request->query('group_id', 0);

        $builder = Post::with([
            'user' => function ($query) {
                $query->withTrashed();
            },
            'group',
        ]);

        $posts = $builder->when($keyword, function ($query) use ($keyword) {
            return $query->where('title', 'like', sprintf('%%%s%%', $keyword));
        })
            ->when($userId, function ($query) use ($userId) {
                // 登陆状态 可以检索public和已加入圈子的帖子
                return $query->whereHas('group.members', function ($query) use ($userId) {
                    return $query->where('audit', 1)->where('user_id', $userId)
                        ->where('disabled', 0)->whereIn('mode', ['public', 'paid', 'private'])->orWhere('mode', 'public');
                });
            }, function ($query) {
                // 未登陆 只能搜索mode为public下面帖子
                return $query->whereHas('group', function ($query) {
                    return $query->where('mode', 'public');
                });
            })
            ->when($groupId, function ($query) use ($groupId) {
                return $query->where('group_id', $groupId);
            })
            ->whereHas('group', function ($query) {
                return $query->where('audit', 1);
            })
            ->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $user = $request->user('api')->id ?? null;

        $items = $posts->map(function ($post) use ($user, $repository) {
            $repository->formatCommonList($user, $post);

            return $post;
        });

        return response()->json($items, 200);
    }
}
