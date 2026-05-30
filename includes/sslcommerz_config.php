<?php

// SSLCommerz sandbox configuration
// Replace these values with your actual sandbox credentials.

if (!defined('SSLCOMMERZ_STORE_ID')) {
    define('SSLCOMMERZ_STORE_ID', 'codeh6a0d548e5ac03');
}

if (!defined('SSLCOMMERZ_STORE_PASSWD')) {
    define('SSLCOMMERZ_STORE_PASSWD', 'codeh6a0d548e5ac03@ssl');
}

if (!defined('SSLCOMMERZ_SANDBOX')) {
    define('SSLCOMMERZ_SANDBOX', true);
}

if (!defined('SSLCOMMERZ_SUCCESS_URL')) {
    define('SSLCOMMERZ_SUCCESS_URL', 'http://www.test.com/coaching-mgmt/parent/sslcz_success.php');
}

if (!defined('SSLCOMMERZ_FAIL_URL')) {
    define('SSLCOMMERZ_FAIL_URL', 'http://www.test.com/coaching-mgmt/parent/sslcz_fail.php');
}

if (!defined('SSLCOMMERZ_CANCEL_URL')) {
    define('SSLCOMMERZ_CANCEL_URL', 'http://www.test.com/coaching-mgmt/parent/sslcz_cancel.php');
}
