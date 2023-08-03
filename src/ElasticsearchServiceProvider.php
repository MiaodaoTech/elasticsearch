<?php

namespace MdTech\Elasticsearch;

use Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;


class ElasticsearchServiceProvider extends ServiceProvider
{
    public function boot(){
        $this->publishes(array(
            __DIR__ . '/../../config/config.php' => config_path('md_elasticsearch.php'),
        ));
    }

    public function register(){
//        $this->mergeConfigFrom(
//            __DIR__ . '/../../config/md_elasticsearch.php',
//            'md_elasticsearch'
//        );

        $this->app->singleton('elastic', function($app) {
            return ClientBuilder::create()
                ->setHosts(config('md_elasticsearch.hosts'))
                ->build();
        });
    }

    public function provides()
    {
        return ['elastic'];
    }
}