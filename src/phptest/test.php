<html>
<title>test1</title>
<body>
<?php

echo 'testing 123';

$name='John';
$fruits=array('apple','pear','lemon');
$currentFruit='';
foreach($fruits as $fruit){
    echo $name . ' likes ' . $fruit . '\r\n';
}

var_dump( array('CodeIgniter', 'php', 'phpMyAdmin', 'www.lucidar.me') );

var_dump(getenv('NGROK_URL'), $_ENV['NGROK_URL'])
?>
</body>
</html>
