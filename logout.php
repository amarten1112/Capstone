<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// logout_user() clears the session, expires the cookie, and redirects to login.php — never returns
logout_user();
