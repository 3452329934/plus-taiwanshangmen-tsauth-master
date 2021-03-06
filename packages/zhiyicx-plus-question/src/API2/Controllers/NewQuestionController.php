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

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;
use Zhiyi\Plus\Utils\Markdown;
use Zhiyi\Plus\Models\User as UserModel;
use Zhiyi\Plus\Concerns\FindMarkdownFileTrait;
use Zhiyi\Plus\Http\Middleware\VerifyUserPassword;
use Zhiyi\Plus\Models\UserCount as UserCountModel;
use SlimKit\PlusQuestion\Models\Topic as TopicModel;
use SlimKit\PlusQuestion\Models\Question as QuestionModel;
use Zhiyi\Plus\Packages\Currency\Processes\User as UserProcess;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use SlimKit\PlusQuestion\API2\Requests\NewUpdateQuestion as UpdateQuestionRequest;
use SlimKit\PlusQuestion\API2\Requests\NewPublishQuestion as PublishQuestionRequest;
use Zhiyi\Plus\Notifications\System as SystemNotification;

class NewQuestionController extends Controller
{
    use FindMarkdownFileTrait;

    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this
            ->middleware(VerifyUserPassword::class)
            ->only(['resetAmount'])
        ;
    }

    /**
     * Publish a question.
     *
     * @param PublishQuestionRequest                        $request
     * @param  ResponseFactoryContract  $response
     * @param  QuestionModel  $question
     * @param  TopicModel  $topicModel
     * @param UserModel                                     $userModel
     *
     * @return mixed
     * @throws Exception
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function store(
        PublishQuestionRequest $request,
        ResponseFactoryContract $response,
        QuestionModel $question,
        TopicModel $topicModel,
        UserModel $userModel
    ) {
        $user = $request->user();

        // Get question base data.
        $subject = $request->input('subject');
        $body = app(Markdown::class)->safetyMarkdown(
            $request->input('body', '')
        );
        $text_body = $request->input('text_body');
        $anonymity = $request->input('anonymity') ? 1 : 0;
        $amount = intval($request->input('amount')) ?: 0;
        $look = $request->input('look') ? 1 : 0;
        $automaticity = $request->input('automaticity') ? 1 : 0;
        $topicsIDs = Arr::pluck((array) $request->input('topics', []), 'id');
        $usersIDs = Arr::pluck((array) $request->input('invitations', []), 'user');

        $images = $this->findMarkdownImageNotWithModels($body ?: '');

        if ($automaticity && ! $amount) {
            return $response->json(['amount' => trans('plus-question::questions.回答自动入账必须设置悬赏总额')], 422);
        } elseif ($automaticity && count($usersIDs) !== 1) {
            return $response->json(['invitations' => trans('plus-question::questions.回答自动入账只能邀请一人')], 422);
        } elseif ($look && ! $automaticity) {
            return $response->json(['automaticity' => trans('plus-question::questions.开启围观必须设置自动入账')], 422);
        } elseif ($look && ! $amount) {
            return $response->json(['amount' => trans('plus-question::question.开启围观必须设置悬赏金额')], 422);
        } elseif ($amount) {
            app(VerifyUserPassword::class)->handle($request, function () {
                // No Code.
            });
        }

        // Find topics.
        $topics = empty($topicsIDs) ? collect() : $topicModel->whereIn('id', $topicsIDs)->get();

        // Find users.
        $users = empty($usersIDs) ? collect() : $userModel->whereIn('id', $usersIDs)->get();

        $question->subject = $subject;
        $question->body = $body;
        $question->text_body = $text_body;
        $question->anonymity = $anonymity;
        $question->amount = $amount;
        $question->automaticity = $automaticity;
        $question->look = $look;
        $question->watchers_count = 1;  // 默认自己关注

        try {
            // Save question.
            $user->questions()->save($question);

            // Save relation.
            $user->getConnection()->transaction(function () use (
                $question,
                $user,
                $topics,
                $users,
                $topicModel,
                $topicsIDs,
                $images
            ) {

                // Sync topics.
                $question->topics()->sync($topics);

                // Watch question.
                $user->watchingQuestions()->attach($question);

                // Topics questions_count +1
                $topicModel->whereIn('id', $topicsIDs)->increment('questions_count', 1);

                // User questions_count +1
                $user->extra()->firstOrCreate([])->increment('questions_count', 1);

                // Sync invitations
                if (! empty($users)) {
                    $question->invitations()->sync($users);
                }

                // Save charage
                if ($question->amount) {
                    $process = new UserProcess();
                    $process->prepayment($user->id, $question->amount, 0, trans('plus-question::questions.发布悬赏问答'), trans('plus-question::questions.发布悬赏问答《%s》', ['subject' => $question->subject]));
                }

                // Update images.
                $images->each(function ($image) use ($question) {
                    $image->channel = 'question:images';
                    $image->raw = $question->id;
                    $image->save();
                });
            });
        } catch (Exception $exception) {
            // Delete Question.
            $question->delete();

            throw $exception;
        }
        // 给用户发送邀请通知.
        $users->each(function (UserModel $item) use ($user, $question) {
            $item->notify(new SystemNotification(trans('plus-question::questions.invitation', [
                'user' => $user->name,
                'question' => $question->subject,
            ]), [
                'type' => 'qa:invitation',
                'sender' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'question' => [
                    'id' => $question->id,
                    'subject' => $question->subject,
                ],
            ]));
        });

        return $response->json([
            'message' => trans('plus-question::messages.success'),
            'question' => $question,
        ], 201);
    }

    /**
     * Update a question.
     *
     * @param UpdateQuestionRequest                         $request
     * @param  ResponseFactoryContract  $response
     * @param  QuestionModel  $question
     *
     * @return mixed
     * @throws Throwable
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function update(
        UpdateQuestionRequest $request,
        ResponseFactoryContract $response,
        QuestionModel $question
    ) {
        $user = $request->user();

        $anonymity = $request->input('anonymity', $question->anonymity) ? 1 : 0;
        foreach (array_filter($request->only(['subject', 'text_body'])) as $key => $value) {
            $question->$key = $value;
        }
        $question->body = app(Markdown::class)->safetyMarkdown(
            $request->input('body', $question->body)
        );
        $images = $this->findMarkdownImageNotWithModels($question->body ?: '');
        $topicsIDs = Arr::pluck((array) $request->input('topics', []), 'id');
        $question->anonymity = $anonymity;
        $amount = $request->input('amount');
        if ($amount) {
            if ($question->user_id !== $user->id) {
                return $response->json(['message' => trans('plus-question::questions.not-owner')], 403);
            }
            if (($question->amount !== 0 && $question->invitations()->first()) || $question->answers()->where('adoption', 1)->first()) {
                return $response->json(['message' => trans('plus-question::questions.该问题无法设置悬赏')], 403);
            }

            app(VerifyUserPassword::class)->handle($request, function () {
                // No Code.
            });
        }

        $question->getConnection()->transaction(function () use ($question, $images, $topicsIDs, $user, $amount) {

            // 设置悬赏金额并扣款
            if ($amount) {
                $question->amount = $amount;
                $user->currency()->decrement('sum', $question->amount);
            }

            $question->save();

            if ($topicsIDs) {
                // Count topic`s questions.
                $topicModel = new TopicModel();
                $topicModel->whereIn('id', $question->topics->pluck('id'))->decrement('questions_count', 1);

                $question->topics()->sync($topicsIDs);
                $topicModel->whereIn('id', $topicsIDs)->increment('questions_count', 1);
            }

            // Update images.
            $images->each(function ($image) use ($question) {
                $image->channel = 'question:images';
                $image->raw = $question->id;
                $image->save();
            });
        });

        return $response->make(null, 204);
    }

    /**
     * Reset amount for a question.
     *
     * @param Request                                       $request
     * @param  ResponseFactoryContract  $response
     * @param QuestionModel  $question
     *
     * @return mixed
     * @throws Throwable
     *@author bs<414606094@qq.com>
     */
    public function resetAmount(
        Request $request,
        ResponseFactoryContract $response,
        QuestionModel $question
    ) {
        $user = $request->user();
        if ($question->user_id !== $user->id) {
            return $response->json(['message' => trans('plus-question::questions.not-owner')], 403);
        }
        if (($question->amount !== 0 && $question->invitations()->first()) || $question->answers()->where('adoption', 1)->first()) {
            return $response->json(['message' => trans('plus-question::questions.该问题无法设置悬赏')], 403);
        }

        $question->amount = $request->input('amount', 0);

        $question->getConnection()->transaction(function () use ($question, $user) {
            $process = new UserProcess();
            $process->prepayment($user->id, $question->amount, 0, trans('plus-question::questions.发布悬赏问答'), trans('plus-question::questions.发布悬赏问答《%s》', ['subject' => $question->subject]));
            $question->save();
        });

        return $response->make(null, 204);
    }

    /**
     * @param Request                                        $request
     * @param  ResponseFactoryContract  $response
     * @param  QuestionModel  $question
     *
     * @return mixed
     *@author bs<414606094@qq.com>
     */
    public function destory(
        Request $request,
        ResponseFactoryContract $response,
        QuestionModel $question
    ) {
        $user = $request->user();
        if ($question->user_id !== $user->id) {
            return $response->json(['message' => trans('plus-question::questions.not-owner')], 403);
        }

        $user->getConnection()->transaction(function () use ($user, $question) {
            // 如果有采纳或是自动入账已完成 则不退款
            if (($question->automaticity && ! $question->answers()->where('invited', 1)->first())
            || ($question->amount > 0 && $question->status != 1 && ! $question->answers()->where('adoption', 1)->first())) {
                $process = new UserProcess();
                $process->reject(0, $question->amount, $question->user_id, trans('plus-question::questions.refund'), trans('plus-question::questions.refund'));
            }

            if ($applylog = $question->applications()->where('status', '!=', 2)->first()) {
                $process = new UserProcess();
                $process->reject(0, $applylog->amount, $question->user_id, trans('plus-question::questions.application.退还问题申精积分'), trans('plus-question::questions.application.退还问题《:subject》的申精积分', ['subject' => $question->subject]));
            }

            $question->delete();
        });

        return $response->json(null, 204);
    }
}
