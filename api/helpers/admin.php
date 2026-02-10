<?php
require "auth.php";
if($user['role']!=="admin") exit;