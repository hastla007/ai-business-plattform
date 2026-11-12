<?php
return [
'default'=>env('DB_CONNECTION','pgsql'),
'connections'=>[
'pgsql'=>['driver'=>'pgsql','url'=>env('DB_URL'),'host'=>env('DB_HOST','127.0.0.1'),'port'=>env('DB_PORT','5432'),'database'=>env('DB_DATABASE','business_engine'),'username'=>env('DB_USERNAME','business_engine'),'password'=>env('DB_PASSWORD',''),'charset'=>'utf8','prefix'=>'','prefix_indexes'=>true,'search_path'=>'public','sslmode'=>env('DB_SSLMODE','prefer')],
],
'redis'=>['client'=>'phpredis','options'=>['cluster'=>env('REDIS_CLUSTER','redis'),'prefix'=>env('REDIS_PREFIX','engine:')],'default'=>['host'=>env('REDIS_HOST','127.0.0.1'),'password'=>env('REDIS_PASSWORD'),'port'=>env('REDIS_PORT',6379),'database'=>0],'cache'=>['host'=>env('REDIS_HOST','127.0.0.1'),'password'=>env('REDIS_PASSWORD'),'port'=>env('REDIS_PORT',6379),'database'=>1]],
'migrations'=>['table'=>'migrations','update_date_on_publish'=>true]
];