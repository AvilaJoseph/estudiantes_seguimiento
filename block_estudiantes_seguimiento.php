<?php
defined('MOODLE_INTERNAL') || die();
class block_estudiantes_seguimiento extends block_base
{
    public function init()
    {
        $this->title = get_string('estudiantes_seguimiento', 'block_estudiantes_seguimiento');
    }

    private function get_student_evaluations()
    {
        global $DB, $USER;

        // Verificar departamento del usuario
        $department = $USER->department;
        $isPermanent = (strpos($department, 'PERMANENTE') !== false);
        $isTemporary = (strpos($department, 'TEMPORAL') !== false);
        $isContractor = ($department === 'CONTRATISTA');

        if (!$isPermanent && !$isTemporary && !$isContractor) {
            return [];
        }

        try {
            // Array con IDs de los 7 cursos específicos
            $course_ids = array(2, 3, 4, 5, 6, 8, 11);
            $results = [];

            // Obtener cursos
            $courses = $DB->get_records_list('course', 'id', $course_ids, 'sortorder, id');

            foreach ($courses as $course) {
                // Consulta para obtener evaluaciones 
                $sql_evaluaciones = "SELECT DISTINCT q.id, q.name
                FROM {quiz} q
                WHERE q.course = :courseid
                AND (
                    " . ($isTemporary || $isContractor ?
                    "(LOWER(q.name) LIKE '%evaluación de inducción%')" :
                    "(LOWER(q.name) LIKE '%evaluación de reinducción%')") . "
                )
                ORDER BY q.name ASC";

                $evaluaciones = $DB->get_records_sql($sql_evaluaciones, ['courseid' => $course->id]);

                if (!empty($evaluaciones)) {
                    foreach ($evaluaciones as $evaluacion) {
                        // Buscar el quiz completo para obtener la nota de aprobación
                        $quiz = $DB->get_record('quiz', ['id' => $evaluacion->id]);

                        // Valor predeterminado para nota mínima (6.0 sobre 10)
                        $nota_minima_default = 6.0;

                        // Calcular nota mínima escalada
                        if (!empty($quiz->grade) && !empty($quiz->gradepass)) {
                            $nota_minima = ($quiz->gradepass / $quiz->grade) * 10;
                        } else {
                            // Si no hay información de nota mínima, usar el valor predeterminado
                            $nota_minima = $nota_minima_default;
                        }

                        // Comprobar si hay intentos para esta evaluación
                        $intentos = $DB->get_records('quiz_attempts', [
                            'quiz' => $evaluacion->id,
                            'userid' => $USER->id,
                            'state' => 'finished'
                        ], 'attempt DESC');

                        if (!empty($intentos)) {
                            // Hay al menos un intento, tomar el último
                            $intento = reset($intentos);

                            // Calcular la calificación escalada a 10
                            // Obtener directamente la calificación ya escalada
                            $quiz_grade = $DB->get_record('quiz_grades', [
                                'quiz' => $evaluacion->id,
                                'userid' => $USER->id
                            ]);

                            if ($quiz_grade && isset($quiz_grade->grade)) {
                                $calificacion = $quiz_grade->grade;
                            } else {
                                // Usar cálculo alternativo si no hay registro en quiz_grades
                                $calificacion = !empty($quiz->grade) ? ($intento->sumgrades / $quiz->grade) * $this->NOTA_MAXIMA : 0;
                            }

                            $fecha = $intento->timefinish;

                            // Verificar si aprobó o no (usando nota mínima calculada o predeterminada)
                            if ($calificacion >= $nota_minima) {
                                $estado = 'aprobado';
                            } else {
                                $estado = 'reprobado';
                            }
                        } else {
                            // No hay intentos
                            $estado = 'pendiente';
                            $calificacion = 0;
                            $fecha = null;
                        }

                        // Crear objeto de evaluación
                        $user_eval = new stdClass();
                        $user_eval->quiz_id = $evaluacion->id;
                        $user_eval->quiz_name = $evaluacion->name;
                        $user_eval->estado = $estado;
                        $user_eval->calificacion = $calificacion;
                        $user_eval->nota_minima = $nota_minima;
                        $user_eval->ultima_modificacion = $fecha;
                        $user_eval->course_id = $course->id;
                        $user_eval->course_name = $course->fullname;

                        $results[$evaluacion->name] = $user_eval;
                    }
                }
            }

            return $results;
        } catch (Exception $e) {
            error_log('Error en get_student_evaluations: ' . $e->getMessage());
            return [];
        }
    }

    // Definir todas las posibles evaluaciones para cada tipo de usuario
    private function define_all_evaluations()
    {
        global $USER;

        $department = $USER->department;
        $isPermanent = (strpos($department, 'PERMANENTE') !== false);
        $isTemporary = (strpos($department, 'TEMPORAL') !== false);
        $isContractor = ($department === 'CONTRATISTA');

        $prefix = ($isTemporary || $isContractor) ? 'Inducción' : 'Reinducción';

        return array(
            'GENERALIDADES DE LA INDUCCIÓN Y REINDUCCIÓN' => array(
                'Evaluación de ' . $prefix . ' del Módulo de Generalidades',
            ),
            'TALENTO HUMANO' => array(
                'Evaluación de ' . $prefix . ' del Submódulo Evaluación del desempeño ( EDL )',
                'Evaluación de ' . $prefix . ' del Submódulo Novedades administrativas',
                'Evaluación de ' . $prefix . ' del Submódulo Bienestar Social',
                'Evaluación de ' . $prefix . ' del Submódulo de Capacitación',
                'Evaluación de ' . $prefix . ' del Submódulo SSGT',
                'Evaluación de ' . $prefix . ' del Submódulo Comités',
                'Evaluación de ' . $prefix . ' del Módulo de Talento Humano'
            ),
            'PROCESOS' => array(
                'Evaluación de ' . $prefix . ' del Módulo de Procesos'
            ),
            'HERRAMIENTAS TÉCNOLOGICAS' => array(
                'Evaluación de ' . $prefix . ' del Submódulo Ophelia',
                'Evaluación de ' . $prefix . ' del Módulo de Herramientas Tecnológicas'
            ),
            'SIGC' => array(
                'Evaluación de ' . $prefix . ' del Módulo de SIGC'
            ),
            'SEGURIDAD INFORMATICA' => array(
                'Evaluación de ' . $prefix . ' del Módulo de Seguridad Informática'
            ),
            'ENTÉRATE' => array(
                'Evaluación de ' . $prefix . ' del Módulo de Entérate',
                'Evaluación de ' . $prefix . ' del Submódulo Subdirección desarrollo sostenible',
                'Evaluación de ' . $prefix . ' del Submódulo Subdirección gestión comercial',
                'Evaluación de ' . $prefix . ' del Submódulo Oficina Asesora planeación',
                'Evaluación de ' . $prefix . ' del Submódulo Secretaria general'
            )
        );
    }

    public function get_content()
    {
        global $USER, $OUTPUT, $PAGE;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass;

        // Verificar que el usuario tenga un departamento válido
        $department = $USER->department;
        $isContratista = ($department === 'CONTRATISTA');
        $isPlanta = (strpos($department, 'PLANTA') !== false);

        if (!$isContratista && !$isPlanta) {
            $this->content->text = '
            <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center;">
                <p style="color: #721c24; font-weight: bold;">Departamento no válido</p>
                <p>Su departamento actual no está configurado correctamente. Por favor, contacte al administrador.</p>
            </div>';
            return $this->content;
        }

        // Add the display department conversion here
        $displayDepartment = $USER->department;
        if (strpos($displayDepartment, 'PERMANENTE') !== false) {
            $displayDepartment = 'PLANTA PERMANENTE';
        } else if (strpos($displayDepartment, 'TEMPORAL') !== false) {
            $displayDepartment = 'PLANTA TEMPORAL';
        } else if ($displayDepartment === 'CONTRATISTA') {
            $displayDepartment = 'CONTRATISTA';
        }

        // Obtener las evaluaciones del estudiante y la lista completa de evaluaciones
        $user_evaluations = $this->get_student_evaluations();
        $all_evaluations = $this->define_all_evaluations();

        // Convertir las evaluaciones del usuario a un array indexado por nombre para facilitar la búsqueda
        $user_evaluations_by_name = [];
        foreach ($user_evaluations as $eval_name => $eval) {
            $user_evaluations_by_name[$eval_name] = $eval;
        }

        // Calcular el progreso global
        $total_evaluations = 0;
        $completed_evaluations = 0;

        // Contar sólo las evaluaciones aprobadas en user_evaluations
        foreach ($user_evaluations as $eval) {
            if ($eval->estado === 'aprobado') {
                $completed_evaluations++;
            }
        }

        // También preparar los contadores por módulo
        $module_progress = [];

        foreach ($all_evaluations as $module_name => $module_evaluations) {
            $module_total = count($module_evaluations);
            $module_completed = 0;
            $total_evaluations += $module_total;

            foreach ($module_evaluations as $eval_name) {
                // Verificar explícitamente si la evaluación existe y está aprobada
                if (
                    isset($user_evaluations_by_name[$eval_name]) &&
                    $user_evaluations_by_name[$eval_name]->estado === 'aprobado'
                ) {
                    $module_completed++;
                }
            }

            // Guardar progreso del módulo
            $module_progress[$module_name] = [
                'total' => $module_total,
                'completed' => $module_completed,
                'percentage' => ($module_total > 0) ? round(($module_completed / $module_total) * 100) : 0
            ];
        }

        // Calcular porcentaje global
        $global_percentage = ($total_evaluations > 0) ? round(($completed_evaluations / $total_evaluations) * 100) : 0;

        // JavaScript para manejar la navegación entre pestañas
        $PAGE->requires->js_init_code('
           document.addEventListener("DOMContentLoaded", function() {
               // Función para mostrar un módulo y ocultar los demás
               function showModule(moduleId) {
                   var modules = document.querySelectorAll(".module-content");
                   modules.forEach(function(module) {
                       module.style.display = "none";
                   });
                   
                   var selectedModule = document.getElementById("module-" + moduleId);
                   if (selectedModule) {
                       selectedModule.style.display = "block";
                   }
                   
                   // Actualizar botones activos
                   var buttons = document.querySelectorAll(".module-button");
                   buttons.forEach(function(button) {
                       button.classList.remove("active");
                   });
                   
                   var activeButton = document.querySelector("[data-module=\'" + moduleId + "\']");
                   if (activeButton) {
                       activeButton.classList.add("active");
                   }
               }
               
               // Configurar manejadores de eventos para los botones
               var buttons = document.querySelectorAll(".module-button");
               buttons.forEach(function(button) {
                   button.addEventListener("click", function(e) {
                       e.preventDefault();
                       var moduleId = this.getAttribute("data-module");
                       showModule(moduleId);
                   });
               });
               
               // Mostrar el primer módulo por defecto
               if (buttons.length > 0) {
                   showModule(buttons[0].getAttribute("data-module"));
               }
           });
       ');

        // Información del estudiante con la barra de progreso global
        $this->content->text = '
<div class="estudiantes-seguimiento-container">
    <div class="estudiante-info">
        <div class="estudiante-nombre">Estudiante: ' . fullname($USER) . '</div>
        <div class="estudiante-depto">Departamento: ' . ($displayDepartment ?? 'No especificado') . '</div>
        <div class="progreso-global">
            <div class="progress-text">Progreso global: ' . $completed_evaluations . ' de ' . $total_evaluations . ' (' . $global_percentage . '%)</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: ' . $global_percentage . '%"></div>
            </div>
        </div>
    </div>';

        // Barra de navegación de módulos
        $this->content->text .= '<div class="module-navbar">';

        $module_count = 1;
        foreach ($all_evaluations as $module_name => $module_evaluations) {
            $button_id = 'module-button-' . $module_count;
            $module_short_name = $this->get_short_module_name($module_name);

            $this->content->text .= '
               <a href="#" class="module-button" id="' . $button_id . '" data-module="' . $module_count . '">
                   ' . $module_count . '. ' . $module_short_name . '
               </a>';

            $module_count++;
        }

        $this->content->text .= '</div>';

        // Contenido de los módulos
        $module_count = 1;
        foreach ($all_evaluations as $module_name => $module_evaluations) {
            // Obtener el progreso de este módulo
            $module_prog = $module_progress[$module_name];

            // Contenedor del módulo (inicialmente oculto excepto el primero)
            $this->content->text .= '
           <div id="module-' . $module_count . '" class="module-content" style="display: none;">
               <div class="module-header">
                   ' . $module_count . '. ' . $module_name . '
               </div>
               
               <div class="progreso-modulo">
                   <div class="progress-text">Progreso del módulo: ' . $module_prog['completed'] . ' de ' . $module_prog['total'] . ' (' . $module_prog['percentage'] . '%)</div>
                   <div class="progress-bar">
                       <div class="progress-fill" style="width: ' . $module_prog['percentage'] . '%"></div>
                   </div>
               </div>
               
               <table class="evaluaciones-table">
                   <thead>
                       <tr>
                           <th>Evaluación</th>
                           <th>Estado</th>
                           <th>Calificación</th>
                           <th>Nota mínima</th>
                           <th>Última Modificación</th>
                       </tr>
                   </thead>
                   <tbody>';

            foreach ($module_evaluations as $eval_name) {
                // Verificar si el usuario ha intentado esta evaluación
                $estado = 'pendiente';
                $calificacion = '0.00';
                $nota_minima = '6.00';  // Valor predeterminado para mostrar
                $fecha = '-';
                $estado_class = 'status-pendiente';
                $estado_text = 'Pendiente';

                if (isset($user_evaluations_by_name[$eval_name])) {
                    $eval = $user_evaluations_by_name[$eval_name];
                    $estado = $eval->estado;
                    $calificacion = number_format($eval->calificacion, 2);
                    $nota_minima = isset($eval->nota_minima) ? number_format($eval->nota_minima, 2) : '6.00';
                    $fecha = $eval->ultima_modificacion ? date('d/m/Y H:i', $eval->ultima_modificacion) : '-';

                    if ($estado === 'aprobado') {
                        $estado_class = 'status-completado';
                        $estado_text = 'Aprobado';
                    } else if ($estado === 'reprobado') {
                        $estado_class = 'status-reprobado';
                        $estado_text = 'Repetir evaluación';
                    } else {
                        $estado_text = 'Pendiente';
                    }
                }

                $this->content->text .= '
                       <tr>
                           <td>' . $eval_name . '</td>
                           <td><span class="status-badge ' . $estado_class . '">' . $estado_text . '</span></td>
                           <td>' . $calificacion . '/10</td>
                           <td>' . $nota_minima . '/10</td>
                           <td>' . $fecha . '</td>
                       </tr>';
            }

            $this->content->text .= '
                   </tbody>
               </table>
           </div>';

            $module_count++;
        }

        // Estilos CSS (actualizado para incluir los estilos de las barras de progreso y el nuevo estado)
        $this->content->text .= '
       <style>
           .estudiantes-seguimiento-container {
               font-family: Arial, sans-serif;
               background-color: #f8f9fa;
               border-radius: 5px;
               overflow: hidden;
           }
           
           .estudiante-info {
               background-color: #fff;
               padding: 15px;
               border-bottom: 1px solid #e9ecef;
           }
           
           .estudiante-nombre {
               color: #0066cc;
               font-weight: bold;
               font-size: 16px;
               margin-bottom: 5px;
           }
           
           .estudiante-depto {
               color: #666;
               font-size: 14px;
               margin-bottom: 10px;
           }
           
           .progreso-global, .progreso-modulo {
               margin-top: 10px;
               margin-bottom: 10px;
           }
           
           .progress-text {
               font-size: 13px;
               color: #333;
               margin-bottom: 5px;
           }
           
           .progress-bar {
               height: 10px;
               background-color: #e9ecef;
               border-radius: 5px;
               overflow: hidden;
           }
           
           .progress-fill {
               height: 100%;
               background-color: #0066cc;
               transition: width 0.3s ease;
           }
           
           .progreso-modulo .progress-fill {
               background-color: #28a745;
           }
           
           .module-navbar {
               display: flex;
               flex-wrap: wrap;
               background-color: #e9ecef;
               padding: 5px;
               gap: 5px;
           }
           
           .module-button {
               display: inline-block;
               padding: 8px 10px;
               background-color: #3498db;
               color: white;
               text-decoration: none;
               border-radius: 3px;
               font-size: 14px;
               transition: background-color 0.3s;
               text-align: center;
               flex-grow: 1;
           }
           
           .module-button:hover {
               background-color: #2980b9;
           }
           
           .module-button.active {
               background-color: #1a5276;
               font-weight: bold;
           }
           
           .module-content {
               padding: 15px;
               background-color: #fff;
           }
           a:hover, a:focus, a:active {
               color: #ffffff;
               /* outline: none; */
           }
           
           .module-header {
               font-size: 16px;
               font-weight: bold;
               color: #0066cc;
               padding-bottom: 10px;
               margin-bottom: 10px;
               border-bottom: 1px solid #e9ecef;
           }
           
           .evaluaciones-table {
               width: 100%;
               border-collapse: collapse;
           }
           
           .evaluaciones-table th {
               background-color: #f8f9fa;
               color: #333;
               text-align: left;
               padding: 8px;
               border-bottom: 2px solid #e9ecef;
           }
           
           .evaluaciones-table td {
               padding: 8px;
               border-bottom: 1px solid #f2f2f2;
           }
           
           .status-badge {
               display: inline-block;
               padding: 3px 8px;
               border-radius: 12px;
               font-size: 12px;
               font-weight: bold;
           }
           
           .status-completado {
               background-color: #e8f5e9;
               color: #28a745;
           }
           
           .status-pendiente {
               background-color: #ffebee;
               color: #dc3545;
           }
           
           .status-reprobado {
               background-color: #fff3cd;
               color: #fd7e14;
           }
           
           @media (max-width: 768px) {
               .module-navbar {
                   flex-direction: column;
               }
               
               .module-button {
                   text-align: left;
               }
               
               .evaluaciones-table {
                   font-size: 13px;
               }
               
               .evaluaciones-table th,
               .evaluaciones-table td {
                   padding: 5px;
               }
           }
       </style>';

        $this->content->text .= '</div>';
        return $this->content;
    }

    // Función para obtener un nombre corto del módulo para los botones
    private function get_short_module_name($long_name)
    {
        $mapping = [
            'GENERALIDADES DE LA INDUCCIÓN Y REINDUCCIÓN' => 'GENERALIDADES',
            'TALENTO HUMANO' => 'TALENTO HUMANO',
            'PROCESOS' => 'PROCESOS',
            'HERRAMIENTAS TÉCNOLOGICAS' => 'HERRAMIENTAS',
            'SIGC' => 'SIGC',
            'SEGURIDAD INFORMATICA' => 'SEGURIDAD',
            'ENTÉRATE' => 'ENTÉRATE'
        ];

        return isset($mapping[$long_name]) ? $mapping[$long_name] : $long_name;
    }

    public function applicable_formats()
    {
        return array('all' => true);
    }

    public function has_config()
    {
        return false;
    }

    public function instance_allow_multiple()
    {
        return false;
    }
}
