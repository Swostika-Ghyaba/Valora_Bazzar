<?php
session_start();
session_unset();
session_destroy();
header("Location:admin_page.php");
exit();

?>