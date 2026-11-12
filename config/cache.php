<?php
return ['default'=>env('CACHE_STORE','redis'),'stores'=>['array'=>['driver'=>'array','serialize'=>false],'redis'=>['driver'=>'redis','connection'=>'cache']],'prefix'=>'business_engine_cache'];