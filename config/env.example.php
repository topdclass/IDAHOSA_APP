<?php
/**
 * RosmonSMS — Environment Configuration TEMPLATE
 * ================================================
 * Copy this file to env.php and fill in your credentials.
 *
 * PRODUCTION EXAMPLE (cPanel shared hosting - writecode.com.ng):
 *
 *   Supervisor DB:
 *     DB_NAME = middlehi_IDAHOSA       (the main hub database)
 *     DB_USER = middlehi_IDAHOSA       (MySQL user with ALL PRIVILEGES)
 *     DB_PASS = middlehi_IDAHOSA       (secure password)
 *
 *   Auto-derived tenant DBs (create these in cPanel):
 *     Slot 1:  middlehi_IDAHOSA_1  / middlehi_IDAHOSA_1  / middlehi_IDAHOSA_1
 *     Slot 2:  middlehi_IDAHOSA_2  / middlehi_IDAHOSA_2  / middlehi_IDAHOSA_2
 *     ...
 *     Slot 50: middlehi_IDAHOSA_50 / middlehi_IDAHOSA_50 / middlehi_IDAHOSA_50
 *
 * CREATE ALL 50 TENANT DATABASES IN CPANEL, then run install.php
 */

return [
    'DB_HOST'          => 'localhost',
    'DB_USER'          => 'middlehi_IDAHOSA',
    'DB_PASS'          => 'YOUR_SECURE_PASSWORD_HERE',
    'DB_NAME'          => 'middlehi_IDAHOSA',
    'DB_PREFIX'        => '',
    'TENANT_DB_HOST'   => 'localhost',
    'TENANT_POOL_SIZE' => 50,
];
