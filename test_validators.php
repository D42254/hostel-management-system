<?php
require_once __DIR__ . '/includes/validators.php';

echo "v_required('a') = " . var_export(v_required('a'), true) . "<br>";
echo "v_email('a@b.co') = " . var_export(v_email('a@b.co'), true) . "<br>";
echo "v_phone_ke('0712345678') = " . var_export(v_phone_ke('0712345678'), true) . "<br>";
echo "v_id('12345678') = " . var_export(v_id('12345678'), true) . "<br>";
echo "v_password('abc123') = " . var_export(v_password('abc123'), true) . "<br>";
echo "OK";