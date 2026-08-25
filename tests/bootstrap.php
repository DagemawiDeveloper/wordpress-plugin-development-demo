<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'AUTH_KEY', 'test-auth-key-0123456789abcdef0123456789abcdef' );
define( 'SECURE_AUTH_SALT', 'test-secure-salt-0123456789abcdef0123456789abcd' );

require_once dirname( __DIR__ ) . '/includes/class-wpitk-webhook-auth.php';
require_once dirname( __DIR__ ) . '/includes/class-wpitk-crypto.php';
require_once dirname( __DIR__ ) . '/includes/class-wpitk-blocks.php';
