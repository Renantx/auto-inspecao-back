<?php
echo password_hash('123456', PASSWORD_BCRYPT);
$hash = '$2y$10$CwTycUXWue0Thq9StjUM0uJ8Rk3Z2XcV7zJrR1yQm2F7cF0xY5J9S';
var_dump(password_verify('123456', $hash));