<?php
return ['ai'=>['base_url'=>env('AI_BASE_URL','http://127.0.0.1:8080'),'api_key'=>env('AI_API_KEY'),'chat_model'=>env('AI_CHAT_MODEL','llama-3.1-8b'),'embedding_model'=>env('AI_EMBEDDING_MODEL','nomic-embed-text'),'timeout'=>(int)env('AI_TIMEOUT',60),'max_retries'=>(int)env('AI_MAX_RETRIES',2)]];
