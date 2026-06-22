<?php
require_once __DIR__ . '/includes/session_hardening.php';

auth_debug_log('logout_entry');

force_logout_and_redirect('login.php?logged_out=1');
