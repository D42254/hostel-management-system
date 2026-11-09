<?php
require 'config/db.php';
$s = $pdo->prepare("SELECT email, password_hash FROM managers WHERE email=?");
$s->execute(['admin@hostel.local']);
$m = $s->fetch();
if (!$m) { die("No row for admin@hostel.local"); }
var_dump($m['password_hash']);
var_dump(password_verify('Admin@123', $m['password_hash']));