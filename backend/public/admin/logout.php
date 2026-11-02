<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

Auth::logout();
redirect('/admin/login.php');
