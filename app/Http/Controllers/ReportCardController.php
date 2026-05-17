<?php
namespace App\Http\Controllers;

require_once __DIR__ . '/../../../config/tenant_manager.php';
use TenantManager;
use PDO;

/**
 * ReportCardController
 *
 * Core PHP implementation of Report Card management.
 */
class ReportCardController {
    
    protected $db;

    public function __construct($school_id = null) {
        $this->db = \TenantManager::getTenantConnection($school_id);
    }

    /**
     * Helper to return JSON response
     */
    protected function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Find many report cards with role-based filtering
     */
    public function find($request) {
        $user = $request['user'] ?? null;
        
        $whereFilters = ["is_deleted = 0"];
        $params = [];

        if ($user) {
            $roleName = $user['role']['name'] ?? '';
            
            if ($roleName === 'Student' || $roleName === 'Parent') {
                $whereFilters[] = "status = 'published'";
                if ($roleName === 'Student') {
                    $whereFilters[] = "student_id = ?";
                    $params[] = $user['id'];
                } else {
                    $whereFilters[] = "student_id IN (SELECT student_id FROM institute_students WHERE family_id = (SELECT family_id FROM institute_parents WHERE parent_id = ?))";
                    $params[] = $user['id'];
                }
            } elseif ($roleName === 'Subject Teacher') {
                // Teachers see report cards they have contributed to or for their classes
                $whereFilters[] = "class_id IN (SELECT class_id FROM class_subjects WHERE teacher_id = ?)";
                $params[] = $user['id'];
            } elseif ($roleName === 'Class Teacher') {
                $whereFilters[] = "class_id IN (SELECT id FROM classes WHERE teacher_id = ?)";
                $params[] = $user['id'];
            }
        }

        $whereSql = "WHERE " . implode(" AND ", $whereFilters);
        $sql = "SELECT * FROM report_cards $whereSql ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->jsonResponse(['data' => $results, 'meta' => []]);
    }

    /**
     * Find single report card with details
     */
    public function findOne($request, $id) {
        $user = $request['user'] ?? null;

        $stmt = $this->db->prepare("SELECT rc.*, s.student_no, u.full_name as student_name 
                                   FROM report_cards rc 
                                   JOIN institute_students s ON rc.student_id = s.student_id 
                                   JOIN users u ON s.student_id = u.id 
                                   WHERE rc.id = ? AND rc.is_deleted = 0");
        $stmt->execute([$id]);
        $entity = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entity) {
            return $this->jsonResponse(['error' => 'Report card not found'], 404);
        }

        // Roles check
        if ($user) {
            $roleName = $user['role']['name'] ?? '';
            if (($roleName === 'Student' || $roleName === 'Parent') && $entity['status'] !== 'published') {
                return $this->jsonResponse(['error' => 'Report card is not published yet'], 403);
            }
        }

        // Fetch academic performances (subject scores)
        $perfStmt = $this->db->prepare("SELECT sg.*, sub.subject_name 
                                       FROM subject_grades sg 
                                       JOIN subjects sub ON sg.subject_id = sub.id 
                                       WHERE sg.student_id = ? AND sg.term = ? AND sg.academic_year = ?");
        $perfStmt->execute([$entity['student_id'], $entity['term'] ?? 'First Term', $entity['academic_year'] ?? '2025/2026']);
        $entity['academic_performances'] = $perfStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch psycho-behavioral analysis
        $psychoStmt = $this->db->prepare("SELECT * FROM psycho_beh_analysis WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
        $psychoStmt->execute([$entity['student_id']]);
        $entity['psycho_behavioral_traits'] = $psychoStmt->fetch(PDO::FETCH_ASSOC);

        // Fetch comments
        $commentStmt = $this->db->prepare("SELECT * FROM report_card_comments WHERE student_id = ? AND class_id = ? AND term = ?");
        $commentStmt->execute([$entity['student_id'], $entity['class_id'], $entity['term'] ?? 'First Term']);
        $entity['comments'] = $commentStmt->fetch(PDO::FETCH_ASSOC);

        return $this->jsonResponse(['data' => $entity]);
    }

    /**
     * Subject Teacher Update Action (Scores Entry)
     */
    public function subjectTeacherUpdate($request, $id) {
        $user = $request['user'] ?? null;
        $data = $request['body']['data'] ?? [];
        $subject_id = $data['subject_id'] ?? null;
        $scores = $data['scores'] ?? []; // ['objective' => 10, 'theory' => 45]

        if (!$user) return $this->jsonResponse(['error' => 'Authentication required'], 401);

        // Verify report card exists
        $stmt = $this->db->prepare("SELECT * FROM report_cards WHERE id = ?");
        $stmt->execute([$id]);
        $rc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rc) return $this->jsonResponse(['error' => 'Report card not found'], 404);

        $total = ($scores['objective'] ?? 0) + ($scores['theory'] ?? 0);
        $grade = $this->calculateGrade($total);
        $gp = $this->calculateGradePoint($total);

        // Update or Insert subject_grades
        $upsertSql = "INSERT INTO subject_grades (student_id, class_id, subject_id, teacher_id, objective_score, theory_score, total_score, grade, grade_point, term, academic_year) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE objective_score = VALUES(objective_score), theory_score = VALUES(theory_score), total_score = VALUES(total_score), grade = VALUES(grade), grade_point = VALUES(grade_point)";
        
        $upsertStmt = $this->db->prepare($upsertSql);
        $upsertStmt->execute([
            $rc['student_id'], $rc['class_id'], $subject_id, $user['id'], 
            $scores['objective'] ?? 0, $scores['theory'] ?? 0, $total, $grade, $gp,
            $rc['term'] ?? 'First Term', $rc['academic_year'] ?? '2025/2026'
        ]);

        return $this->jsonResponse(['data' => 'Scores updated successfully']);
    }

    /**
     * Class Teacher Submit Action (Comments & Traits)
     */
    public function classTeacherSubmit($request, $id) {
        $user = $request['user'] ?? null;
        $data = $request['body']['data'] ?? [];

        $stmt = $this->db->prepare("SELECT * FROM report_cards WHERE id = ?");
        $stmt->execute([$id]);
        $rc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rc) return $this->jsonResponse(['error' => 'Report card not found'], 404);

        // Update comments
        $commentSql = "INSERT INTO report_card_comments (student_id, class_id, term, academic_year, class_teacher_comment) 
                       VALUES (?, ?, ?, ?, ?) 
                       ON DUPLICATE KEY UPDATE class_teacher_comment = VALUES(class_teacher_comment)";
        $this->db->prepare($commentSql)->execute([$rc['student_id'], $rc['class_id'], $rc['term'], $rc['academic_year'], $data['class_teacher_comment'] ?? '']);

        // Update traits
        $traitSql = "INSERT INTO psycho_beh_analysis (student_id, discipline, neatness, politeness, self_control, relationship_with_others) 
                     VALUES (?, ?, ?, ?, ?, ?) 
                     ON DUPLICATE KEY UPDATE discipline=VALUES(discipline), neatness=VALUES(neatness), politeness=VALUES(politeness), self_control=VALUES(self_control), relationship_with_others=VALUES(relationship_with_others)";
        $t = $data['traits'] ?? [];
        $this->db->prepare($traitSql)->execute([$rc['student_id'], $t['discipline']??0, $t['neatness']??0, $t['politeness']??0, $t['self_control']??0, $t['relationship_with_others']??0]);

        // Update status
        $this->db->prepare("UPDATE report_cards SET status = 'pending_principal_review' WHERE id = ?")->execute([$id]);

        return $this->jsonResponse(['data' => 'Report submitted for review']);
    }

    /**
     * Principal Approve Action
     */
    public function principalApprove($request, $id) {
        $user = $request['user'] ?? null;
        $data = $request['body']['data'] ?? [];

        $stmt = $this->db->prepare("SELECT * FROM report_cards WHERE id = ?");
        $stmt->execute([$id]);
        $rc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rc) return $this->jsonResponse(['error' => 'Report card not found'], 404);

        // Update principal comment
        $commentSql = "UPDATE report_card_comments SET principal_comment = ?, status = 'Approved' WHERE student_id = ? AND class_id = ? AND term = ?";
        $this->db->prepare($commentSql)->execute([$data['principal_comment'] ?? '', $rc['student_id'], $rc['class_id'], $rc['term']]);

        // Publish report card
        $this->db->prepare("UPDATE report_cards SET status = 'published', authorizedBy_id = ?, authorizedAt = NOW() WHERE id = ?")->execute([$user['id'], $id]);

        return $this->jsonResponse(['data' => 'Report card published successfully']);
    }

    // Helpers
    private function calculateGrade($score) {
        $stmt = $this->db->prepare("SELECT grade_name FROM grade_points WHERE ? BETWEEN min_score AND max_score LIMIT 1");
        $stmt->execute([$score]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row['grade_name'];
        
        // Fallback
        if ($score >= 75) return 'A';
        if ($score >= 60) return 'B';
        if ($score >= 50) return 'C';
        if ($score >= 40) return 'P';
        return 'F';
    }

    private function calculateGradePoint($score) {
        $stmt = $this->db->prepare("SELECT grade_point FROM grade_points WHERE ? BETWEEN min_score AND max_score LIMIT 1");
        $stmt->execute([$score]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (float)$row['grade_point'] : 0.0;
    }
}
