<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

   # 'paymob' => [
        #'api_key'        => env('PAYMOB_API_KEY'),
       # 'integration_id' => env('PAYMOB_INTEGRATION_ID'),
      #  'iframe_id'      => env('PAYMOB_IFRAME_ID'),
     #   'hmac_secret'    => env('PAYMOB_HMAC_SECRET'),
    #],

 'vapid' => [
    'public_key'  => env('BFS__FypoPi-6FFF76rtZ0QOZv8XNOP5EHdVzWTH2eZV0gSOzsYIBXgjtNP9Cjl6rnkf-25RAS-4fown58TYUwM'),
    'private_key' => env('-DstsfQaBpSzbUWoBOGatZGDp02Fz5TQl8hj2fLoLYk'),
    'subject'     => env('mailto: <bassel.adel136@gmail.com>'),
],

];