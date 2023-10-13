<?php

namespace App\Provider;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use eru123\venv\VirtualEnv as A;

class S3
{
    public static function upload(array $config, array $data): bool|string|array
    {
        try {
            $s3 = new S3Client([
                'endpoint' => A::get($config, 'endpoint'),
                'credentials' => [
                    'key' => A::get($config, 'access_key'),
                    'secret' => A::get($config, 'api_key'),
                ],
                'region' => A::get($config, 'region', 'us-east-1'),
                'version' => A::get($config, 'version', 'latest'),
            ]);

            $filepath = A::get($data, 'path');
            $bucket = A::get($config, 'bucket');
            $acl = A::get($data, 'acl', 'public-read');
            $mime = A::get($data, 'mime', 'application/zip');
            $key = A::get($data, 'key', basename($filepath));
            $f = fopen($filepath, 'rb');
            $res = $s3->putObject([
                'Bucket' => $bucket,
                'Key' => $key,
                'Body' => $f,
                'ACL' => $acl,
                'ContentType' => $mime,
            ]);

            fclose($f);
            return $res;
        } catch (S3Exception $e) {
            return $e->getMessage();
        }
    }
}