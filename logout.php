<?php
require 'includes/auth_class.php';
session_start();

Auth::cerrarSesion();
header('Location: index.php');
exit();