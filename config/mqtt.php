<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default MQTT Connection
    |--------------------------------------------------------------------------
    | Konfigurasi default broker MQTT yang digunakan seluruh aplikasi.
    | Bisa di-override per node melalui kolom config di bems_nodes.
    */

    'default_broker'   => env('MQTT_BROKER',   'broker.emqx.io'),
    'default_port'     => env('MQTT_PORT',      1883),
    'default_username' => env('MQTT_USERNAME',  null),
    'default_password' => env('MQTT_PASSWORD',  null),

    /*
    |--------------------------------------------------------------------------
    | Client Settings
    |--------------------------------------------------------------------------
    */
    'connect_timeout'  => env('MQTT_TIMEOUT',   5),
    'client_id_prefix' => env('MQTT_CLIENT_PREFIX', 'chaka_'),

    /*
    |--------------------------------------------------------------------------
    | Topic Prefix
    |--------------------------------------------------------------------------
    | Semua topic akan diawali prefix ini.
    | Format: chaka/{type}/room{id}/{slug}
    */
    'topic_prefix'     => env('MQTT_TOPIC_PREFIX', 'chaka'),

];