<?php
defined('MOODLE_INTERNAL') || die();

class block_student_evaluations extends block_base {
    public function init() {
        $this->title = get_string('student_evaluations', 'block_student_evaluations');
    }

    private function get_student_evaluations() {
        global $DB, $USER;
        
        try {
            // Array con los IDs de los 7 cursos
            $course_ids = array(1, 2, 3, 4, 5, 6, 7); // Reemplazar con los IDs reales
            
            $sql = "SELECT 
                    c.id as course_id,
                    c.fullname as course_name,
                    q.id as quiz_id,
                    q.name as quiz_name,
                    CASE 
                        WHEN qa.state = 'finished' THEN 'completado'
                        ELSE 'pendiente'
                    END as estado,
                    COALESCE(ROUND((qa.sumgrades/q.grade) * 10, 2), 0) as calificacion,
                    qa.timemodified as ultima_modificacion
                FROM {course} c
                JOIN {enrol} e ON c.id = e.courseid
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                JOIN {quiz} q ON q.course = c.id
                LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.userid = ?
                WHERE c.id IN (" . implode(',', $course_ids) . ")
                AND ue.userid = ?
                AND (
                    LOWER(q.name) LIKE '%evaluación de inducción%'
                    OR 
                    LOWER(q.name) LIKE '%evaluación de reinducción%'
                )
                ORDER BY c.sortorder, q.name";

            return $DB->get_records_sql($sql, [$USER->id, $USER->id]);
        } catch (Exception $e) {
            return [];
        }
    }

    public function get_content() {
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        
        // Obtener las evaluaciones del estudiante
        $evaluations = $this->get_student_evaluations();

        // Agrupar evaluaciones por curso
        $evaluations_by_course = [];
        foreach ($evaluations as $eval) {
            if (!isset($evaluations_by_course[$eval->course_id])) {
                $evaluations_by_course[$eval->course_id] = [
                    'name' => $eval->course_name,
                    'evaluations' => []
                ];
            }
            $evaluations_by_course[$eval->course_id]['evaluations'][] = $eval;
        }

        $this->content->text = '
        <div class="student-evaluations">
            <div class="user-info mb-4">
                <h4>Estudiante: ' . fullname($USER) . '</h4>
                <p>Departamento: ' . ($USER->department ?? 'No especificado') . '</p>
            </div>';

        foreach ($evaluations_by_course as $course_id => $course_data) {
            $this->content->text .= '
            <div class="course-section mb-4">
                <h5 class="course-title">' . $course_data['name'] . '</h5>
                <div class="evaluations-list">
                    <div class="evaluation-grid">';

            foreach ($course_data['evaluations'] as $eval) {
                $status_class = ($eval->estado === 'completado') ? 'status-completado' : 'status-pendiente';
                $fecha = $eval->ultima_modificacion ? date('d/m/Y H:i', $eval->ultima_modificacion) : '-';
                
                $this->content->text .= '
                    <div class="evaluation-card">
                        <div class="eval-title">' . $eval->quiz_name . '</div>
                        <div class="eval-details">
                            <div class="eval-status ' . $status_class . '">' . ucfirst($eval->estado) . '</div>
                            <div class="eval-grade">Nota: ' . $eval->calificacion . '/10</div>
                            <div class="eval-date">Última modificación: ' . $fecha . '</div>
                        </div>
                    </div>';
            }

            $this->content->text .= '
                    </div>
                </div>
            </div>';
        }

        $this->content->text .= '</div>
        
        <style>
        .student-evaluations {
            padding: 15px;
        }
        
        .course-section {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .course-title {
            color: #265281;
            border-bottom: 2px solid #429beb;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        
        .evaluation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .evaluation-card {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 12px;
            transition: transform 0.2s;
        }
        
        .evaluation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .eval-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .eval-details {
            font-size: 0.9em;
            color: #666;
        }
        
        .eval-status {
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .status-completado {
            color: #28a745;
        }
        
        .status-pendiente {
            color: #dc3545;
        }
        
        .eval-grade {
            margin-bottom: 5px;
        }
        
        .eval-date {
            font-size: 0.85em;
            color: #777;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .evaluation-grid {
                grid-template-columns: 1fr;
            }
            
            .student-evaluations {
                padding: 10px;
            }
            
            .course-section {
                padding: 12px;
            }
        }
        </style>';

        return $this->content;
    }

    public function applicable_formats() {
        return array('all' => true);
    }
}