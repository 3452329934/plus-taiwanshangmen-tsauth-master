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

use Illuminate\Http\Request;
use Zhiyi\PlusGroup\Models\Group as  GroupModel;
use Zhiyi\PlusGroup\Models\GroupMember as MemberModel;
use Zhiyi\Plus\Notifications\System as SystemNotification;

class GroupMemberController
{
    public function members(Request $request, GroupModel $group)
    {
        $role = $request->query('role');
        $user = $request->query('user');
        $audit = $request->query('audit');
        $disable = $request->query('disable');

        $limit = (int) $request->query('limit', 15);
        $offset = (int) $request->query('offset', 0);

        $query = $group->members()
        ->when(! is_null($audit), function ($query) use ($audit) {
            return $query->where('audit', $audit);
        })
        ->when($role, function ($query) use ($role) {
            return $query->where('role', $role);
        })
        ->when(! is_null($disable), function ($query) use ($disable) {
            return $query->where('disabled', $disable);
        })
        ->when($user, function ($query) use ($user) {
            return $query->whereHas('user', function ($query) use ($user) {
                return $query->where('name', 'like', sprintf('%%%s%%', $user));
            });
        });

        $count = $query->count();
        $items = $query->with(['user', 'group'])
        ->limit($limit)
        ->offset($offset)
        ->get();

        return response()->json($items, 200, ['x-total' => $count]);
    }

    /**
     * 设置圈子角色.
     *
     * @param  Request     $request
     * @param  GroupMember $member
     * @return mixed
     */
    public function role(Request $request, MemberModel $member)
    {
        $role = $request->input('role');

        if (! $role || ! in_array($role, ['member', 'founder', 'administrator'])) {
            return response()->json(['message' => '错误的参数'], 422);
        }

        if ($member->audit != 1 || $member->disabled == 1) {
            return response()->json(['message' => '申请审核未通过或已被拉黑,不能设置职位'], 422);
        }

        if ($role == 'administrator'
         && MemberModel::where('group_id', $member->group_id)->where('role', 'administrator')->count() >= 5) {
            return response()->json(['message' => '最多只能设置5个管理员'], 403);
        }

        $user = $member->user;
        $group = $member->group;

        $memberModel = MemberModel::where('group_id', $member->group_id)
        ->where('role', $role)
        ->first();

        // 判断该成员是否已是该职位
        if ($memberModel && $memberModel->id == $member->id) {
            return response()->json(['mssage' => '你已被设置成该职位'], 403);
        }

        // 如果设置成员和管理员为圈主
        if ($role == 'founder') {
            $founder = $group->founder;
            $founder->role = 'member';
            $founder->save();

            $founder->user->notify(new SystemNotification(sprintf('系统管理员将你设置成"%s"圈子的成员', $group->name), [
                'type' => 'group:menbers',
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                ],
                'message' => '系统管理员将你设置成%s的成员',
            ]));
        }

        $member->role = $role;
        $member->save();

        $user->notify(new SystemNotification(sprintf('系统管理员将你设置成%s的%s', $group->name, $this->getNameByRole($role)), [
            'type' => 'group:menbers',
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
            ],
            'role_name' => $this->getNameByRole($role),
            'message' => '系统管理员将你设置成%s的%s',
        ]));

        return response()->json(['message' => '设置成功', 'member' => $member], 201);
    }

    /**
     * 通过角色获取角色名.
     *
     * @param  string $role
     * @return string
     */
    public function getNameByRole(string $role)
    {
        switch ($role) {
            case 'administrator':
                return '管理员';
                break;
            case 'founder':
                return '圈主';
                break;
            default:
                return '成员';
                break;
        }
    }

    /**
     * 踢出圈子.
     *
     * @param  MemberModel $member
     * @return mixed
     */
    public function delete(MemberModel $member)
    {
        $user = $member->user;
        $group = $member->group;

        if ($member->role == 'founder') {
            return response()->json(['message' => '圈主不能被踢出'], 403);
        }

        $member->group()->decrement('users_count');

        $user->notify(new SystemNotification(sprintf('管理员已将你移出%s', $group->name), [
            'type' => 'group:menbers',
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
            ],
            'message' => '管理员已将你移出%s',
        ]));

        $member->delete();

        return response()->json(null, 204);
    }

    /**
     * 拉黑.
     *
     * @param  MemberModel $member
     * @return mixed
     */
    public function disable(Request $request, MemberModel $member)
    {
        $disable = $request->input('disable');

        if (! in_array($disable, [0, 1])) {
            return response()->json(['message' => '参数错误'], 422);
        }
        if ($member->role == 'founder') {
            return response()->json(['message' => '圈主不能被拉黑'], 403);
        }

        $member->disabled = $disable;
        $member->save();

        $user = $member->user;
        $group = $member->group;

        return response()->json(['message' => $disable ? '加入黑名单成功' : '解除黑名单成功'], 201);
    }
}
