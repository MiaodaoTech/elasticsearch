<?php


return array(

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    |
    | The index name. Change it to the name of your application or something
    | else meaningful.
    |
    */
    'index' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Auto Index
    |--------------------------------------------------------------------------
    |
    | When enabled, indexes will be set automatically on create, save or delete.
    | Disable it to have manual control over indexes.
    |
    */
    'auto_index' => true,


    'hosts' => [
        env('ES_HOST') . ':' . env('ES_PORT')
    ],


    'connectionClass' => '\Elasticsearch\Connections\GuzzleConnection',
    'connectionFactoryClass' => '\Elasticsearch\Connections\ConnectionFactory',
    'connectionPoolClass' => '\Elasticsearch\ConnectionPool\StaticNoPingConnectionPool',
    'selectorClass' => '\Elasticsearch\ConnectionPool\Selectors\RoundRobinSelector',
    'serializerClass' => '\Elasticsearch\Serializers\SmartSerializer',

    'sniffOnStart' => false,
    'connectionParams' => [],
    'logging' => false,
    'logObject' => null,
    'logPath' => storage_path().'/logs/elasticsearch.log',
    'logLevel' => Monolog\Logger::WARNING,
    'traceObject' => null,
    'tracePath' => storage_path().'/logs/elasticsearch_trace.log',
    'traceLevel' => Monolog\Logger::WARNING,
    'guzzleOptions' => array(),
    'connectionPoolParams' => [
        'randomizeHosts' => true
    ],
    'retries' => null,

);