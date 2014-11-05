<?php
namespace Opine\ImageResizer;

require __DIR__ . '/../src/Controller.php';
require __DIR__ . '/../src/Service.php';

class config {
}

$config = new config();
$config->auth = ['salt' => 'xxx'];
$service =  new Service($config);

$message = $service->encryptDecrypt('encrypt', 'http://www.somewebsite.com/path/that/is/very/long/to/a/file/that/alsohasalong-name.jpg');
echo 'message: ', $message, "\n\n";
echo $service->encryptDecrypt('decrypt', $message), "\n\n";

echo $service->getPath('/storage/file.jpg', 100, 200), "\n\n";

//$controller = new Controller($service);
//$controller->