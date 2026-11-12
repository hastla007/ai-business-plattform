<?php
namespace App\Services;
use App\Models\Node;
interface NodeTransport { public function dispatch(Node $node,array $payload): array; }
