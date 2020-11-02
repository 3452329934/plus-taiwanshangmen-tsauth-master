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

namespace Zhiyi\Plus\FileStorage\Filesystems\AliyunOss;

use Cache;
use Closure;
use Storage;
use OSS\Core\OssException;
use OSS\OssClient;
use OSS\Core\MimeTypes;
use Zhiyi\Plus\Models\User;
use Zhiyi\Plus\FileStorage\ImageDimension;
use Zhiyi\Plus\FileStorage\FileMetaAbstract;
use Zhiyi\Plus\FileStorage\Pay\PayInterface;
use Zhiyi\Plus\FileStorage\ResourceInterface;
use Zhiyi\Plus\FileStorage\Traits\HasImageTrait;
use Zhiyi\Plus\FileStorage\ImageDimensionInterface;
use function Zhiyi\Plus\setting;

class FileMeta extends FileMetaAbstract
{
    use HasImageTrait;

    protected $oss;
    protected $resource;
    protected $bucket;
    protected $dimension;
    protected $metaData;

    /**
     * Create a file meta.
     * @param \OSS\OssClient $oss
     * @param \Zhiyi\Plus\FileStorage\ResourceInterface $resource
     * @param string $bucket
     * @throws OssException
     */
    public function __construct(OssClient $oss, ResourceInterface $resource, string $bucket)
    {
        $this->oss = $oss;
        $this->resource = $resource;
        $this->bucket = $bucket;
        $this->getSize();
    }

    /**
     * Has the file is image.
     * @return bool
     */
    public function hasImage(): bool
    {
        return $this->hasImageType(
            $this->getMimeType()
        );
    }

    /**
     * Get image file dimension.
     * @return \Zhiyi\Plus\FileStorage\ImageDimensionInterface
     * @throws \OSS\Core\OssException
     */
    public function getImageDimension(): ImageDimensionInterface
    {
        if (! $this->hasImage()) {
            throw new \Exception('调用的资源并非图片或者是不支持的图片资源');
        } elseif ($this->dimension instanceof ImageDimensionInterface) {
            return $this->dimension;
        }

        $meta = $this->getFileMeta();

        return new ImageDimension(
            (float) $meta->ImageWidth->value,
            (float) $meta->ImageHeight->value
        );
    }

    /**
     * Get the file size (Byte).
     * @return int
     * @throws OssException
     */
    public function getSize(): int
    {
        $meta = $this->getFileMeta();

        return (int) ($meta->FileSize->value ?? $meta->{'content-length'});
    }

    /**
     * Get the resource mime type.
     * @return string
     */
    public function getMimeType(): string
    {
        return MimeTypes::getMimetype($this->resource->getPath()) ?: 'application/octet-stream';
    }

    /**
     * Get the storage vendor name.
     * @return string
     */
    public function getVendorName(): string
    {
        return 'aliyun-oss';
    }

    /**
     * Get the resource pay info.
     * @param \Zhiyi\Plus\Models\User $user
     * @return \Zhiyi\Plus\FileStorage\Pay\PayInterface
     */
    public function getPay(User $user): ?PayInterface
    {
        return null;
    }

    /**
     * Get the resource url.
     * @return string
     */
    public function url(): string
    {
        return route('storage:get', [
            'channel' => $this->resource->getChannel(),
            'path' => base64_encode($this->resource->getPath()),
        ]);
    }

    /**
     * Custom using MIME types.
     * @return Closure|null
     */
    protected function useCustomTypes(): ?Closure
    {
        return function () {
            return [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/bmp',
                'image/tiff',
                'image/webp',
            ];
        };
    }


    /**
     * @return object
     * @throws OssException
     */
    protected function getFileMeta()
    : object
    {
        if (!$this->metaData) {
            $this->metaData = Cache::rememberForever((string) $this->resource, function () {
                return !$this->hasImage() ?
                    (object) $this->oss->getObjectMeta($this->bucket, $this->resource->getPath()) :
                    json_decode(file_get_contents($this->oss->signUrl($this->bucket, $this->resource->getPath(), 3600, 'GET', [
                        OssClient::OSS_PROCESS => 'image/info',
                    ])));
            });
            // 检测文件是否存在bucket，如果不存在
            if (!$this->metaData) {
                if (!$this->oss->doesObjectExist($this->bucket, $this->resource->getPath())) {
                    $disk = setting('file-storage', 'filesystems.local', ['disk' => 'local', 'timeout' => '3600']);
                    $filePath = Storage::disk($disk['disk'])->path($this->resource->getPath());
                    file_exists($filePath) && $this->oss->uploadFile($this->bucket, $this->resource->getPath(), $filePath);
                    $this->metaData = Cache::forever((string) $this->resource, function () {
                        return !$this->hasImage() ?
                            (object) $this->oss->getObjectMeta($this->bucket, $this->resource->getPath()) :
                            json_decode(file_get_contents($this->oss->signUrl($this->bucket, $this->resource->getPath(), 3600, 'GET', [
                                OssClient::OSS_PROCESS => 'image/info',
                            ])));
                    });
                } else {
                    $this->metaData = Cache::forever((string) $this->resource, function () {
                        return !$this->hasImage() ?
                            (object) $this->oss->getObjectMeta($this->bucket, $this->resource->getPath()) :
                            json_decode(file_get_contents($this->oss->signUrl($this->bucket, $this->resource->getPath(), 3600, 'GET', [
                                OssClient::OSS_PROCESS => 'image/info',
                            ])));
                    });
                }
            }
        }

        return $this->metaData;
    }
}
