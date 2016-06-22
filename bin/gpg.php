<?php

if (isset($app))
{
    $app = include __DIR__ . '/../src/bootstrap.php';
}

if (null !== $app['gpg.url'])
{
    $pub = json_decode(file_get_contents(sprintf($app['gpg.url'], 'pub')));
    $sec = json_decode(file_get_contents(sprintf($app['gpg.url'], 'sec')));

    echo 'Fetching public key' . PHP_EOL;
    file_put_contents(sys_get_temp_dir(). DIRECTORY_SEPARATOR . 'pub.gpg', base64_decode($pub->content));
    echo 'Fetching private key' . PHP_EOL;
    file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sec.gpg', base64_decode($sec->content));
    echo 'Importing public key' . PHP_EOL;
    passthru('gpg --import ' . sys_get_temp_dir(). DIRECTORY_SEPARATOR . 'pub.gpg');
    echo 'Importing private key' . PHP_EOL;
    passthru('gpg --allow-secret-key-import --import ' . sys_get_temp_dir(). DIRECTORY_SEPARATOR . 'sec.gpg');
}
