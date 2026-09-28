<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Empleado.php';
require_once __DIR__ . '/../models/Evaluable.php';
require_once __DIR__ . '/../models/EmpleadoTiempoCompleto.php';
require_once __DIR__ . '/../models/EmpleadoPorHoras.php';
require_once __DIR__ . '/../models/Encargado.php';
require_once __DIR__ . '/../models/Nomina.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ==========================================================
// CONTROLADOR DE REPORTES
// Se encarga de generar los archivos descargables (PDF y Excel).
// Reutiliza el Modelo (Nomina + clases de Empleado) igual que
// EmpleadoController, pero su única responsabilidad es exportar.
// ==========================================================
class ReporteController {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::obtenerConexion();
    }

    /**
     * Lee la base de datos y reconstruye los objetos del Modelo.
     * (Mismo patrón que EmpleadoController::obtenerNominaActual)
     */
    private function obtenerNomina(): Nomina {
        $filas = $this->pdo->query("SELECT * FROM empleados ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $nomina = new Nomina();

        foreach ($filas as $fila) {
            switch ($fila['tipo']) {
                case 'tiempoCompleto':
                    $empleado = new EmpleadoTiempoCompleto($fila['nombre'], $fila['codigo'], $fila['fecha_contratacion'], (float) $fila['salario_mensual'], (float) $fila['bono_anual']);
                    break;
                case 'porHoras':
                    $empleado = new EmpleadoPorHoras($fila['nombre'], $fila['codigo'], $fila['fecha_contratacion'], (float) $fila['horas_trabajadas'], (float) $fila['tarifa_por_hora']);
                    break;
                case 'encargado':
                    $empleado = new Encargado($fila['nombre'], $fila['codigo'], $fila['fecha_contratacion'], (float) $fila['salario_mensual'], (float) $fila['bono_anual'], (float) $fila['bono_liderazgo']);
                    break;
                default:
                    continue 2;
            }
            $nomina->agregarEmpleado($empleado);
        }

        return $nomina;
    }

    // ---------- PDF con Dompdf ----------
    public function generarPdf(): void {
        $nomina = $this->obtenerNomina();
        $empleados = $nomina->getEmpleados();
        $total = $nomina->calcularTotalNomina();
        $fecha = date('d/m/Y H:i');

        // Construimos el HTML que Dompdf va a convertir en PDF.
        // (Dompdf no entiende PHP directo, así que armamos el HTML como texto)
        $filasHtml = '';
        foreach ($empleados as $empleado) {
            $filasHtml .= '<tr>'
                . '<td>' . htmlspecialchars($empleado->getId()) . '</td>'
                . '<td>' . htmlspecialchars($empleado->getNombre()) . '</td>'
                . '<td>' . htmlspecialchars(get_class($empleado)) . '</td>'
                . '<td style="text-align:right;">$' . number_format($empleado->calcularSalario(), 2) . '</td>'
                . '</tr>';
        }

        $html = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; color: #222; }
                h1 { color: #2c3e50; font-size: 20px; margin-bottom: 0; }
                .fecha { color: #777; font-size: 11px; margin-top: 4px; }
                table { width: 100%; border-collapse: collapse; margin-top: 16px; }
                th { background: #2c3e50; color: white; padding: 6px; text-align: left; }
                td { padding: 6px; border-bottom: 1px solid #ddd; }
                .total { text-align: right; font-size: 14px; font-weight: bold; margin-top: 12px; color: #27ae60; }
            </style>
        </head>
        <body>
            <h1>Sistema de Gestion de Nomina</h1>
            <div class="fecha">Generado el ' . $fecha . '</div>
            <table>
                <thead>
                    <tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Salario</th></tr>
                </thead>
                <tbody>' . $filasHtml . '</tbody>
            </table>
            <div class="total">TOTAL A PAGAR: $' . number_format($total, 2) . '</div>
        </body>
        </html>';

        $opciones = new Options();
        $opciones->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        // 'stream' envía el PDF directo al navegador para descargar/ver
        $dompdf->stream('reporte_nomina.pdf', ['Attachment' => true]);
    }

    // ---------- Excel con PhpSpreadsheet ----------
    public function generarExcel(): void {
        $nomina = $this->obtenerNomina();
        $empleados = $nomina->getEmpleados();
        $total = $nomina->calcularTotalNomina();

        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Nomina');

        // Encabezados
        $encabezados = ['ID', 'Nombre', 'Tipo', 'Salario calculado'];
        $hoja->fromArray($encabezados, null, 'A1');

        $hoja->getStyle('A1:D1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('A1:D1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('2C3E50');
        $hoja->getStyle('A1:D1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Filas de datos
        $fila = 2;
        foreach ($empleados as $empleado) {
            $hoja->setCellValue('A' . $fila, $empleado->getId());
            $hoja->setCellValue('B' . $fila, $empleado->getNombre());
            $hoja->setCellValue('C' . $fila, get_class($empleado));
            $hoja->setCellValue('D' . $fila, $empleado->calcularSalario());
            $hoja->getStyle('D' . $fila)->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $fila++;
        }

        // Fila de total
        $hoja->setCellValue('C' . $fila, 'TOTAL:');
        $hoja->setCellValue('D' . $fila, $total);
        $hoja->getStyle('C' . $fila . ':D' . $fila)->getFont()->setBold(true);
        $hoja->getStyle('D' . $fila)->getNumberFormat()->setFormatCode('"$"#,##0.00');

        // Ajustar ancho de columnas automáticamente
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $hoja->getColumnDimension($col)->setAutoSize(true);
        }

        // Enviar el archivo al navegador como descarga
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="reporte_nomina.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }
}
