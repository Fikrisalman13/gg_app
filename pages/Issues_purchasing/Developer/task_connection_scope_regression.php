<?php

$connection = (object) ['marker' => 'db-connection'];

$handler = static function ($connection): bool {
    return isset($connection->marker) && $connection->marker === 'db-connection';
};

assert($handler($connection) === true);
assert($handler(null) === false);

echo "Connection scope regression passed.\n";