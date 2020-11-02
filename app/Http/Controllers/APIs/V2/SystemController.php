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

namespace Zhiyi\Plus\Http\Controllers\APIs\V2;

use Carbon\Carbon;
use Zhiyi\Plus\Models\User;
use Illuminate\Http\Request;
use Zhiyi\Plus\Utils\Markdown;
use Zhiyi\Plus\Models\Conversation;
use function Zhiyi\Plus\setting;

class SystemController extends Controller
{
    /* 保存协议 */
    public function saveAgreement(Request $request)
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        if (! $currentUser->administrator()) {
            return response()->json(['message' => '无权操作'], 403);
        }
        /* 类型，用于设置数据 */
        $type = $request->input('type');
        /* 页面地址 */
        $url = $request->input('url');
        /* 内容 */
        $content = $request->input('content');
        if($content && !is_string($content)){
            return response()->json([
                'errors' => ['内容参数类型错误'],
            ])->setStatusCode(403);
        }
        $setting = setting('site');
        $setting->set(sprintf('%s_agreement_content', $type), $content);
        $setting->set(sprintf('%s_agreement_url', $type), $url);

        return response()->json(['message' => '保存成功'], 201);
    }

    /* 获取协议 */
    public function getAgreement(Request $request)
    {
        $type = $request->query('type');
        if($type && !is_string($type)){
            return response()->json([
                'errors' => ['类型参数类型错误'],
            ])->setStatusCode(403);
        }
        return response()->json([
            'url'     => setting('site', sprintf('%s_agreement_url', $type), ''),
            'content' => setting('site', sprintf('%s_agreement_content', $type), ''),
        ], 200);
    }

    public function siteAgreement(Request $request) {
        $type = $request->query('type');
        if($type && !is_string($type)){
            return response()->json([
                'errors' => ['类型参数类型错误'],
            ])->setStatusCode(403);
        }
        $url = setting('site', sprintf('%s_agreement_url', $type), '');
        if ($url) {
            return redirect($url, 302);
        }
        $content = setting('site', sprintf('%s_agreement_content', $type), '');
        $body = preg_replace('/\@\!\[(.*?)\]\((\d+)\)/i', '![$1]('.config('app.url').'/api/v2/files/$2)', $content);
        $content = htmlspecialchars_decode(\Parsedown::instance()->setMarkupEscaped(true)->text($body));

        return view('about', ['content' => $content, 'title' => $type === 'privacy' ? '隐私政策' : '用户协议']);
    }

    /**
     * create a feedback.
     *
     * @param  Request  $request
     *
     * @return mixed
     * @author bs<414606094@qq.com>
     */
    public function createFeedback(Request $request, Conversation $feedback, Carbon $datetime)
    {
        $feedback->type = 'feedback';
        $feedback->content = $request->input('content');
        $feedback->user_id = $request->user()->id;
        $feedback->system_mark = $request->input('system_mark', ($datetime->timestamp) * 1000);
        $feedback->save();

        return response()->json([
            'message' => [trans('messages.feedback_success')],
            'data'    => $feedback,
        ])->setStatusCode(201);
    }

    /**
     * about us.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @author bs<414606094@qq.com>
     */
    public function about()
    {
        if (! is_null(config('site.aboutUs.url'))) {
            return redirect(config('site.aboutUs.url'), 302);
        }
        $body = config('site.aboutUs.content', '');
        $body = preg_replace('/\@\!\[(.*?)\]\((\d+)\)/i', '![$1]('.config('app.url').'/api/v2/files/$2)', $body);
        $content = htmlspecialchars_decode(\Parsedown::instance()->setMarkupEscaped(true)->text($body));

        return view('about', ['content' => $content, 'title' => '关于我们']);
    }

    /**
     * 注册协议.
     *
     * @return html
     * @author Foreach<791477842@qq.com>
     */
    public function agreement(Markdown $markdown)
    {
        $content = setting('user', 'register-setting', [
                'content' => trans('settings.service_rule'),
            ])['content'] ?? trans('settings.service_rule');
        $content = $markdown->toHtml($content);

        return view('agreement', ['content' => $content]);
    }

    /**
     * 获取系统会话列表.
     *
     * @param  Request  $request
     * @param  Conversation  $conversationModel
     *
     * @return mixed
     * @author bs<414606094@qq.com>
     */
    public function getConversations(Request $request, Conversation $conversationModel)
    {
        $uid = $request->user()->id;
        $limit = $request->input('limit', 15);
        $max_id = $request->input('max_id', 0);
        $order = $request->input('order', 0);
        $list = $conversationModel->where(function ($query) use ($uid) {
            $query->where(function ($query) use ($uid) {
                $query->where('type', 'system')->whereIn('to_user_id', [0, $uid]);
            })->orWhere(['type' => 'feedback', 'user_id' => $uid]);
        })
            ->where(function ($query) use ($max_id, $order) {
                if ($max_id > 0) {
                    $query->where('id', $order ? '>' : '<', $max_id);
                }
            })
            ->orderBy('id', 'desc')
            ->take($limit)
            ->get();

        return response()->json($list)->setStatusCode(200);
    }
}
