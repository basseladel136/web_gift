<?php

namespace App\Helpers;

class ApiResponse
{
    public static function success($message, $data = [], $code = 200)
    {
        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $data
        ], $code);
    }

    public static function error($message, $code = 400)
    {
        return response()->json([
            'status'  => false,
            'message' => $message,
        ], $code);
    }
}