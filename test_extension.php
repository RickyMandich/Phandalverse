<?php

echo "<pre>";

$extensions = [
    "mbstring",
    "openssl",
    "sodium",
    "zip",
    "ctype",
    "pdo_mysql",
    "tokenizer",
];

foreach ($extensions as $ext) {
    echo $ext . ": " . (extension_loaded($ext) ? "OK" : "MISSING") . "\n";
}
