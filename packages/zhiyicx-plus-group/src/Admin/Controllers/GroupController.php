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

namespace Zhiyi\PlusGroup\Admin\Controllers;

use DB;
use Lvht\GeoHash;
use Illuminate\Http\Request;
use Zhiyi\Plus\Models\UserCount;
use Zhiyi\Plus\FileStorage\Resource;
use Zhiyi\Plus\FileStorage\StorageInterface;
use Zhiyi\PlusGroup\Models\Group as GroupModel;
use Zhiyi\PlusGroup\Models\GroupMember as MemberModel;
use Zhiyi\Plus\Notifications\System as SystemNotification;
use Zhiyi\PlusGroup\Models\GroupRecommend as RecommendModel;
use Zhiyi\PlusGroup\Admin\Requests\CreateGroupRequest as StoreRequest;
use Zhiyi\PlusGroup\Admin\Requests\UpdateGroupRequest as UpdateRequest;

class GroupController
{
    public function index(Request $request)
    {
        $type = $request->query('type');

        if ($type && $type == 'all') {
            $groups = GroupModel::select('id', 'name')
                ->where('audit', 1)
                ->get();

            return response()->json($groups, 200);
        }

        $limit = (int) $request->query('limit', 15);
        $offset = (int) $request->query('offset', 0);

        $data = $request->all();

        $builder = GroupModel::with([
            'user' => function ($query) {
                $query->withTrashed();
            },
            'category',
            'recommend',
        ]);

        foreach ($data as $key => $value) {
            if ($value && in_array($key, ['category_id', 'mode'])) {
                $builder = $builder->where($key, $value);
            }
        }

        $builder = $builder->when(isset($data['name']),
            function ($query) use ($data) {
                return $query->where('name', 'like',
                    sprintf('%%%s%%', $data['name']));
            })
            ->when(isset($data['user_name']), function ($query) use ($data) {
                return $query->whereHas('user', function ($query) use ($data) {
                    return $query->where('name', 'like',
                        sprintf('%%%s%%', $data['user_name']));
                });
            })
            ->when(is_numeric($pinned = $request->query('pinned')),
                function ($query) use ($pinned) {
                    if ($pinned) {
                        return $query->has('recommend');
                    }

                    return $query->doesntHave('recommend');
                })
            ->when($type && $type == 'trash', function ($query) {
                return $query->onlyTrashed();
            })->when(is_numeric($audit = $request->query('audit')),
                function ($query) use ($audit) {
                    return $query->where('audit', $audit);
                });

        $count = (clone $builder)->count();
        $items = (clone $builder)->orderBy('audit', 'asc')->orderBy('id', 'desc')->limit($limit)
            ->offset($offset)->get();

        return response()->json($items, 200, ['x-total' => $count]);
    }

    public function store(StoreRequest $request, StorageInterface $storage)
    {
        $avatar = $request->file('avatar');
        $resource = new Resource(
            'public',
            $storage->makePath(
                sprintf(
                    '%s.%s',
                    $avatar->path(),
                    $avatar->guessClientExtension()
                )
            )
        );
        $storage->put($resource, $avatar->get());
        $data = $this->getRequestOnly($request);

        DB::beginTransaction();

        try {
            $group = new GroupModel;
            foreach ($data as $key => $value) {
                $group->{$key} = $value;
            }
            $group->avatar = $resource;

            // 地理位置
            if (isset($data['location'])) {
                $group->geo_hash = GeoHash::encode($data['latitude'],
                    $data['longitude']);
            }

            // 发帖权限
            $permissions = (int) $request->input('permissions');

            if (in_array($permissions, [1, 2, 3])) {
                $default = 'member,administrator,founder';
                if ($permissions == 2) {
                    $default = 'administrator,founder';
                }
                if ($permissions == 1) {
                    $default = 'founder';
                }
                $group->permissions = $default;
            }

            $group->save();

            $group->tags()->sync(explode(',', $request->input('tags')));

            if ((int) $request->input('recommend')) {
                RecommendModel::create([
                    'group_id' => $group->id,
                    'sort_by'  => 1000,
                    'referrer' => $request->user()->id,
                ]);
            }

            $member = new MemberModel();
            $member->group_id = $group->id;
            $member->user_id = $data['user_id'];
            $member->audit = 1;
            $member->role = 'founder';
            $member->save();

            $group->increment('users_count');

            DB::commit();

            return response()->json(['message' => '添加成功'], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getRequestOnly(Request $request)
    {
        return $request->only(
            'name',
            'category_id',
            'user_id',
            'mode',
            'money',
            'audit',
            'summary',
            'notice',
            'location',
            'latitude',
            'longitude',
            'allow_feed'
        );
    }

    public function show(int $group)
    {
        $group = GroupModel::withTrashed()->find($group);
        $group->load('tags', 'recommend', 'category');
        if ($group->audit === 1) {
            $group->group_founder = $group->founder->user;
        } else {
            // 未通过审核时， 默认是创建者的创始人
            $group->group_founder = $group->user;
        }

        return response()->json($group, 200);
    }

    public function update(
        UpdateRequest $request,
        StorageInterface $storage,
        GroupModel $group
    ) {
        $avatar = $request->file('avatar');
        if ($avatar) {
            $resource = new Resource(
                'public',
                $storage->makePath(
                    sprintf(
                        '%s.%s',
                        $avatar->path(),
                        $avatar->guessClientExtension()
                    )
                )
            );
            $storage->put($resource, $avatar->get());
            $group->avatar = $resource;
        }

        $data = $this->getRequestOnly($request);

        DB::beginTransaction();

        try {
            foreach ($data as $key => $value) {
                $group->{$key} = $value;
            }

            // 地理位置
            if (isset($data['location'])) {
                $group->geo_hash = GeoHash::encode($data['latitude'],
                    $data['longitude']);
            }

            // 发帖权限
            $permissions = (int) $request->input('permissions');
            if (in_array($permissions, [1, 2, 3])) {
                $default = 'member,administrator,founder';
                if ($permissions == 2) {
                    $default = 'administrator,founder';
                }
                if ($permissions == 1) {
                    $default = 'founder';
                }
                $group->permissions = $default;
            }

            $group->save();
            $group->tags()->sync(explode(',', $request->input('tags')));

            $recommend = (int) $request->input('recommend');
            if ($recommend
                && ! RecommendModel::where('group_id', $group->id)->count()
            ) {
                RecommendModel::create([
                    'group_id' => $group->id,
                    'sort_by'  => 1000,
                    'referrer' => $request->user()->id,
                ]);
            } else {
                $group->recommend()->delete();
            }

            DB::commit();

            return response()->json(['message' => '修改成功'], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * 解散圈子(软删除).
     *
     * @param  GroupModel  $group
     *
     * @return mixed
     * @throws \Exception
     */
    public function delete(GroupModel $group)
    {
        $group->delete();

        return response()->json(null, 204);
    }

    /**
     * 圈子推荐.
     *
     * @param  GroupModel  $group
     *
     * @return mixed
     */
    public function recommend(Request $request, GroupModel $group)
    {
        if (! $group->recommend) {
            RecommendModel::create([
                'group_id' => $group->id,
                'sort_by'  => 1000,
                'referrer' => $request->user()->id,
            ]);
        } else {
            $group->recommend()->delete();
        }

        return response()->json(null, 204);
    }

    /**
     * 圈子启动和关闭.
     *
     * @param  Request  $request
     * @param  GroupModel  $group
     *
     * @return mixed
     */
    public function audit(Request $request, GroupModel $group)
    {
        $audit = $request->input('audit');

        if (! in_array($audit, [1, 2, 3])) {
            return response()->json(['message' => '参数错误'], 403);
        }
        $user = $group->user;
        $message = sprintf('%s,你创建的《%s》圈子审核已通过', $user->name,
            $group->name);

        if ($audit == 1 && $group->audit != 3) {
            $member = new MemberModel();
            $member->user_id = $group->user_id;
            $member->group_id = $group->id;
            $member->disabled = 0;
            $member->audit = 1;
            $member->role = 'founder';
            $member->save();

            $group->increment('users_count');
        }

        if ($audit == 1 && $group->audit == 3) {
            $message = sprintf('%s,你创建的《%s》圈子被系统管理员开启', $user->name,
                $group->name);
        }

        if ($audit == 2) {
            $message = sprintf('%s,你创建的《%s》圈子审核未通过', $user->name,
                $group->name);
        }

        if ($audit == 3) {
            $message = sprintf('%s,你创建的《%s》圈子被系统管理员关闭', $user->name,
                $group->name);
        }

        $group->audit = $audit;
        $group->save();
        $message = sprintf($message, $user->name, $group->name);
        $user->notify(new SystemNotification(sprintf($message,
            $user->name, $group->name), [
            'type'     => 'group:audit',
            'contents' => $message,
            'group'    => [
                'id'   => $group->id,
                'name' => $group->name,
            ],
        ]));
        // 新版未读消息
        $userUnreadCount = $user->unreadNotifications()
            ->count();
        $userCount = UserCount::firstOrNew([
            'user_id' => $user->id,
            'type'    => 'user-system',
        ]);
        $userCount->total = $userUnreadCount;
        $userCount->save();

        return response()->json(['message' => '操作成功'], 201);
    }

    /**
     * 恢复圈子.
     *
     * @param  GroupModel  $group
     *
     * @return mixed
     */
    public function restore(int $id)
    {
        GroupModel::withTrashed()->find($id)->restore();

        return response()->json(['message' => '恢复成功'], 201);
    }
}
