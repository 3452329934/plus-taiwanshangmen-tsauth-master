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

namespace Zhiyi\Plus\API2\Controllers;

use Illuminate\Http\Response;
use SlimKit\PlusQuestion\Models\Question;

class QAQuestionController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Destory a Q&A question.
     * @param \SlimKit\PlusQuestion\Models\Question $question
     * @return mixed
     */
    public function destroy(Question $question)
    {
        $this->authorize('delete', $question);
        $question->delete();

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}