<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Income;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\FeePayment;
use App\Models\Student;

class FinanceController {

    /**
     * Expenses Module: Add Expense
     * Selects predefined or custom expense category and enters amount and month roll-up.
     */
    public function addExpenseAction($request) {
        $category_id = $request['category_id'];
        $amount = $request['amount'];
        $title = $request['title']; // e.g., 'Bus Fuel Purchase'
        $date = $request['date']; // Y-m-d format
        $notes = $request['notes'] ?? '';
        
        // Month and Year for monthly roll-up calculation
        $month = date('m', strtotime($date));
        $year = date('Y', strtotime($date));

        // Create the individual expense entry
        Expense::create([
            'category_id' => $category_id,
            'amount' => $amount,
            'title' => $title,
            'date' => $date,
            'notes' => $notes,
            'month' => $month,
            'year' => $year
        ]);

        return ['status' => 'success', 'message' => 'Expense logged and rolled up successfully.'];
    }

    /**
     * Expenses Dashboard: Gets filtered monthly summary
     * Comparision: Shows previous month vs current month
     */
    public function getMonthlyExpenseDashboard($month, $year) {
        // Automatically sum totals for the current month
        $currentMonthTotals = Expense::getTotalsByCategoryForMonth($month, $year);
        
        // Previous month comparison
        $prevMonth = ($month == 1) ? 12 : $month - 1;
        $prevYear = ($month == 1) ? $year - 1 : $year;
        $prevMonthTotals = Expense::getTotalsByCategoryForMonth($prevMonth, $prevYear);

        return [
            'current_month' => $currentMonthTotals,
            'previous_month' => $prevMonthTotals,
            'comparison_chart_data' => "Data ready for Pie Chart/Bar Graph rendering."
        ];
    }

    /**
     * Income Module: Add Income directly via predefined lists
     * Eliminates manual description entry
     */
    public function addIncomeAction($request) {
        Income::create([
            'category_id' => $request['category_id'], // Tuition, Boarding, Asset Sales, etc.
            'amount' => $request['amount'],
            'date' => $request['date'],
            'month' => date('m', strtotime($request['date'])),
            'year' => date('Y', strtotime($request['date'])),
            'student_id' => $request['student_id'] ?? null // Traceable to a student if it's a fee
        ]);
        return ['status' => 'success', 'message' => 'Income entered via centralised item picker.'];
    }

    /**
     * Student Fee Reporting & Debtors
     */
    public function getDebtorsReport() {
        // Fetch all students, their total fee expectations, and subtract amount paid to find balance
        $students = Student::all();
        $debtors = [];

        foreach ($students as $student) {
            $expected = FeePayment::getExpectedFees($student->id, $student->class_id);
            $paid = FeePayment::getTotalPaid($student->id);
            $balance = $expected - $paid;

            if ($balance > 0) {
                $debtors[] = [
                    'student_name' => $student->name,
                    'class_name' => $student->class_name,
                    'amount_owed' => $balance
                ];
            }
        }

        return $debtors; // Output cleanly arrayed list of debters for export
    }
}
