<?php

return [
    'llama_endpoint' => env('LLAMA_ENDPOINT', 'http://localhost:11434/v1/completions'),
    'llama_model' => env('LLAMA_MODEL', 'llama3.2'),
    'llama_timeout' => env('LLAMA_TIMEOUT', 10),
];
