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

namespace Zhiyi\Plus\Http\Requests\API2;

use Illuminate\Foundation\Http\FormRequest;
use function Zhiyi\Plus\setting;

class StoreUploadFile extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $configure = setting('file-storage', 'task-create-validate', [
            'file-mime-types' => [],
            'file-max-size'   => 102400,
            'file-min-size'   => 100,
        ]);
        $fileMimeTypes = implode(',', $configure['file-mime-types']);
        $maxSize = $configure['file-max-size'] / 1024;
        $minSize = $configure['file-min-size'] / 1024;
        return [
            'file' => "required|max:{$maxSize}|min:{$minSize}|file|mimetypes:{$fileMimeTypes}",
        ];
    }

    /**
     * Get the validation message that apply to the request.
     *
     * @return array
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function messages()
    : array
    {
        return [
            'file.required' => '没有上传文件或者上传错误',
            'file.max'      => '文件上传超出服务器限制',
            'file.file'     => '文件上传失败',
            'file.mimes'    => '文件上传格式错误',
        ];
    }
}
