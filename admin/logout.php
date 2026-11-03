<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$auth->logout();
flash('info', 'You have been signed out.');
redirect('login.php');
