<?php

namespace App\Http\Controllers;

class EnvConfigController extends Controller
{
    public function index()
    {
        $configs = [
            // Application
            'APP_NAME' => env('APP_NAME'),
            'APP_ENV' => env('APP_ENV'),
            'APP_DEBUG' => env('APP_DEBUG'),
            'APP_URL' => env('APP_URL'),
            'APP_TIMEZONE' => env('APP_TIMEZONE', 'UTC'),
            
            // Database
            'DB_CONNECTION' => env('DB_CONNECTION'),
            'DB_HOST' => env('DB_HOST'),
            'DB_PORT' => env('DB_PORT'),
            'DB_DATABASE' => env('DB_DATABASE'),
            'DB_USERNAME' => env('DB_USERNAME'),
            'DB_PASSWORD' => env('DB_PASSWORD') ? '***hidden***' : null,
            
            // Cache
            'CACHE_DRIVER' => env('CACHE_DRIVER'),
            'CACHE_STORE' => env('CACHE_STORE'),
            'REDIS_HOST' => env('REDIS_HOST'),
            'REDIS_PORT' => env('REDIS_PORT'),
            
            // Queue
            'QUEUE_CONNECTION' => env('QUEUE_CONNECTION'),
            
            // Mail
            'MAIL_DRIVER' => env('MAIL_DRIVER'),
            'MAIL_HOST' => env('MAIL_HOST'),
            'MAIL_PORT' => env('MAIL_PORT'),
            'MAIL_USERNAME' => env('MAIL_USERNAME'),
            'MAIL_PASSWORD' => env('MAIL_PASSWORD') ? '***hidden***' : null,
            'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS'),
            'MAIL_FROM_NAME' => env('MAIL_FROM_NAME'),
            
            // Sanctum
            'SANCTUM_STATEFUL_DOMAINS' => env('SANCTUM_STATEFUL_DOMAINS'),
            
            // Session
            'SESSION_DRIVER' => env('SESSION_DRIVER'),
            'SESSION_LIFETIME' => env('SESSION_LIFETIME'),
            
            // Filesystem
            'FILESYSTEM_DISK' => env('FILESYSTEM_DISK'),
            
            // AWS (dacă este configurat)
            'AWS_ACCESS_KEY_ID' => env('AWS_ACCESS_KEY_ID') ? '***hidden***' : null,
            'AWS_SECRET_ACCESS_KEY' => env('AWS_SECRET_ACCESS_KEY') ? '***hidden***' : null,
            'AWS_DEFAULT_REGION' => env('AWS_DEFAULT_REGION'),
            'AWS_BUCKET' => env('AWS_BUCKET'),
        ];

        return view('env_config', ['configs' => $configs]);
    }
}
