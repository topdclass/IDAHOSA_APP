<?php
/**
 * Seeder for Chart of Accounts (Income & Expenses)
 */
require_once __DIR__ . '/../config/tenant_manager.php';
$schoolId = isset($argv[1]) ? intval($argv[1]) : 101;
$pdo = TenantManager::getTenantConnection($schoolId);

$incomes = [
    'Tuition / School fees', 'Registration / Admission fees', 'Examination fees',
    'PTA levies', 'ICT / E-learning fees', 'Laboratory fees', 'Library fees',
    'Transport fees', 'Boarding / Hostel fees', 'Feeding / Catering fees',
    'Uniform sales', 'Books & stationery sales', 'Extra-curricular / club fees',
    'After-school / lesson fees', 'Donations & grants', 'Sponsorship income',
    'Event income', 'Fines & penalties', 'Rental income'
];

$expenses = [
    'Teaching staff salaries & wages', 'Part-time / lesson teachers pay',
    'Teaching materials & instructional aids', 'Examination materials & printing',
    'Feeding costs', 'Transport fuel & driver allowances', 'Student activity materials',
    'Non-teaching staff salaries', 'Allowances & bonuses', 'Pension contributions',
    'Staff training & welfare', 'Electricity', 'Water', 'Internet & communication',
    'Generator fuel & maintenance', 'Rent / lease', 'Repairs & maintenance',
    'Office supplies', 'Printing & stationery', 'Software & subscriptions',
    'Audit & professional fees', 'Legal fees', 'Advertising & promotions',
    'Open day / admission marketing', 'Website & social media management',
    'Vehicle maintenance', 'Vehicle insurance', 'Transport licensing',
    'Buildings depreciation', 'Furniture & fittings depreciation', 
    'Computers & ICT equipment depreciation', 'Vehicles depreciation',
    'Bank charges', 'Loan interest', 'Taxes', 'PAYE remittance', 'Pension remittance'
];

$pdo->exec("TRUNCATE TABLE account_categories");

$stmt = $pdo->prepare("INSERT INTO account_categories (category_name, category_type) VALUES (?, ?)");

foreach ($incomes as $inc) {
    $stmt->execute([$inc, 'income']);
}
foreach ($expenses as $exp) {
    $stmt->execute([$exp, 'expense']);
}

// Modify account_transactions table to drop unused columns and add necessary ones if they are missing
$pdo->exec("ALTER TABLE `account_transactions` ADD COLUMN IF NOT EXISTS `category_id` int AFTER `type`");
$pdo->exec("ALTER TABLE `account_transactions` ADD COLUMN IF NOT EXISTS `transaction_month` int AFTER `category_id`");
$pdo->exec("ALTER TABLE `account_transactions` ADD COLUMN IF NOT EXISTS `transaction_year` int AFTER `transaction_month`");

echo "Chart of Accounts Seeded Successfully.\n";
