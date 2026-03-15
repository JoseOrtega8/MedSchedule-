<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(): View
    {
        $this->assertAdminAccess();

        return view('admin.logs');
    }

    public function indexData(Request $request): JsonResponse
    {
        if ($request->user() && ! $request->user()->hasRole('admin')) {
            abort(403);
        }

        return response()->json([
            'filters' => [
                'actions' => ['login', 'logout', 'create', 'update', 'delete'],
                'models' => ['User', 'Appointment', 'Schedule', 'Specialty'],
                'defaultFrom' => '2026-03-09',
                'defaultTo' => '2026-03-14',
            ],
            'logs' => $this->logsPayload($request),
        ]);
    }

    public function show(int $id): View
    {
        $this->assertAdminAccess();

        $log = ActivityLog::with('user')->findOrFail($id);

        return view('admin.logs.show', compact('log'));
    }

    public function getByUser(int $userId): View
    {
        $this->assertAdminAccess();

        $logs = ActivityLog::with('user')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.logs.index', compact('logs'));
    }

    private function logsPayload(Request $request): array
    {
        $logs = $request->session()->get('admin_activity_logs_payload');

        if (! is_array($logs)) {
            $logs = $this->defaultLogs();
            $request->session()->put('admin_activity_logs_payload', $logs);
        }

        return $logs;
    }

    private function assertAdminAccess(): void
    {
        abort_unless(Auth::check() && Auth::user()->hasRole('admin'), 403);
    }

    private function defaultLogs(): array
    {
        return [
            [
                'id' => 101,
                'userId' => 'AL',
                'userName' => 'Ana Lopez',
                'userColor' => '#9a7b07',
                'action' => 'login',
                'modelType' => 'User',
                'description' => 'El usuario inicio sesion',
                'ipAddress' => '192.168.1.10',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-09 09:01:23',
                'oldValues' => [],
                'newValues' => ['session' => 'created', 'status' => 'authenticated'],
            ],
            [
                'id' => 102,
                'userId' => 'AL',
                'userName' => 'Ana Lopez',
                'userColor' => '#9a7b07',
                'action' => 'create',
                'modelType' => 'Appointment',
                'description' => 'Cita agendada con Dr. Garcia',
                'ipAddress' => '192.168.1.10',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-09 09:05:44',
                'oldValues' => [],
                'newValues' => ['doctor_id' => 8, 'date' => '2026-03-12', 'status' => 'pending'],
            ],
            [
                'id' => 103,
                'userId' => 'MG',
                'userName' => 'Dr. Garcia',
                'userColor' => '#28a745',
                'action' => 'update',
                'modelType' => 'Appointment',
                'description' => 'Cita confirmada',
                'ipAddress' => '192.168.1.15',
                'userAgent' => 'Mozilla/5.0 Firefox/119',
                'createdAt' => '2026-03-09 10:12:05',
                'oldValues' => ['status' => 'pending'],
                'newValues' => ['status' => 'confirmed'],
            ],
            [
                'id' => 104,
                'userId' => 'JC',
                'userName' => 'Jose Carlos',
                'userColor' => '#1976d2',
                'action' => 'delete',
                'modelType' => 'Schedule',
                'description' => 'Horario eliminado',
                'ipAddress' => '192.168.1.1',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-09 11:30:00',
                'oldValues' => ['date' => '2026-03-09', 'start_time' => '13:00', 'status' => 'available'],
                'newValues' => [],
            ],
            [
                'id' => 105,
                'userId' => 'AL',
                'userName' => 'Ana Lopez',
                'userColor' => '#9a7b07',
                'action' => 'logout',
                'modelType' => 'User',
                'description' => 'El usuario cerro sesion',
                'ipAddress' => '192.168.1.10',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-09 12:00:01',
                'oldValues' => ['session' => 'active'],
                'newValues' => ['session' => 'destroyed'],
            ],
            [
                'id' => 106,
                'userId' => 'JC',
                'userName' => 'Jose Carlos',
                'userColor' => '#1976d2',
                'action' => 'create',
                'modelType' => 'Specialty',
                'description' => 'Especialidad Cardiologia creada',
                'ipAddress' => '192.168.1.1',
                'userAgent' => 'Mozilla/5.0 Safari/17',
                'createdAt' => '2026-03-10 08:20:11',
                'oldValues' => [],
                'newValues' => ['name' => 'Cardiologia', 'status' => 1],
            ],
            [
                'id' => 107,
                'userId' => 'MG',
                'userName' => 'Dr. Garcia',
                'userColor' => '#28a745',
                'action' => 'update',
                'modelType' => 'Schedule',
                'description' => 'Horario bloqueado por consulta externa',
                'ipAddress' => '192.168.1.15',
                'userAgent' => 'Mozilla/5.0 Firefox/119',
                'createdAt' => '2026-03-10 09:35:00',
                'oldValues' => ['status' => 'available'],
                'newValues' => ['status' => 'blocked'],
            ],
            [
                'id' => 108,
                'userId' => 'JC',
                'userName' => 'Jose Carlos',
                'userColor' => '#1976d2',
                'action' => 'delete',
                'modelType' => 'Appointment',
                'description' => 'Cita cancelada por administrador',
                'ipAddress' => '192.168.1.1',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-10 11:02:40',
                'oldValues' => ['status' => 'confirmed'],
                'newValues' => ['status' => 'cancelled'],
            ],
            [
                'id' => 109,
                'userId' => 'AL',
                'userName' => 'Ana Lopez',
                'userColor' => '#9a7b07',
                'action' => 'login',
                'modelType' => 'User',
                'description' => 'Reingreso al sistema',
                'ipAddress' => '192.168.1.10',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-11 07:59:10',
                'oldValues' => [],
                'newValues' => ['session' => 'created'],
            ],
            [
                'id' => 110,
                'userId' => 'JC',
                'userName' => 'Jose Carlos',
                'userColor' => '#1976d2',
                'action' => 'update',
                'modelType' => 'Specialty',
                'description' => 'Especialidad Neurologia actualizada',
                'ipAddress' => '192.168.1.1',
                'userAgent' => 'Mozilla/5.0 Firefox/119',
                'createdAt' => '2026-03-11 13:18:31',
                'oldValues' => ['description' => 'Sistema nervioso central'],
                'newValues' => ['description' => 'Sistema nervioso'],
            ],
            [
                'id' => 111,
                'userId' => 'JC',
                'userName' => 'Jose Carlos',
                'userColor' => '#1976d2',
                'action' => 'create',
                'modelType' => 'User',
                'description' => 'Nuevo usuario paciente creado',
                'ipAddress' => '192.168.1.1',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-12 10:45:09',
                'oldValues' => [],
                'newValues' => ['role' => 'patient', 'status' => 'active'],
            ],
            [
                'id' => 112,
                'userId' => 'JC',
                'userName' => 'Jose Carlos',
                'userColor' => '#1976d2',
                'action' => 'logout',
                'modelType' => 'User',
                'description' => 'Administrador cerro sesion',
                'ipAddress' => '192.168.1.1',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-12 18:10:18',
                'oldValues' => ['session' => 'active'],
                'newValues' => ['session' => 'destroyed'],
            ],
            [
                'id' => 113,
                'userId' => 'MG',
                'userName' => 'Dr. Garcia',
                'userColor' => '#28a745',
                'action' => 'login',
                'modelType' => 'User',
                'description' => 'Doctor inicio sesion desde consultorio 2',
                'ipAddress' => '192.168.1.15',
                'userAgent' => 'Mozilla/5.0 Firefox/119',
                'createdAt' => '2026-03-13 07:10:00',
                'oldValues' => [],
                'newValues' => ['session' => 'created'],
            ],
            [
                'id' => 114,
                'userId' => 'MG',
                'userName' => 'Dr. Garcia',
                'userColor' => '#28a745',
                'action' => 'create',
                'modelType' => 'Schedule',
                'description' => 'Horario disponible creado',
                'ipAddress' => '192.168.1.15',
                'userAgent' => 'Mozilla/5.0 Firefox/119',
                'createdAt' => '2026-03-13 07:22:11',
                'oldValues' => [],
                'newValues' => ['date' => '2026-03-14', 'start_time' => '09:00', 'end_time' => '09:30'],
            ],
            [
                'id' => 115,
                'userId' => 'AL',
                'userName' => 'Ana Lopez',
                'userColor' => '#9a7b07',
                'action' => 'update',
                'modelType' => 'Appointment',
                'description' => 'Paciente actualizo motivo de consulta',
                'ipAddress' => '192.168.1.10',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-13 08:05:19',
                'oldValues' => ['reason' => 'Dolor en pecho'],
                'newValues' => ['reason' => 'Dolor en pecho despues de ejercicio'],
            ],
            [
                'id' => 116,
                'userId' => 'JC',
                'userName' => 'Jose Carlos',
                'userColor' => '#1976d2',
                'action' => 'update',
                'modelType' => 'User',
                'description' => 'Usuario doctor activado',
                'ipAddress' => '192.168.1.1',
                'userAgent' => 'Mozilla/5.0 Safari/17',
                'createdAt' => '2026-03-13 09:14:50',
                'oldValues' => ['status' => 'inactive'],
                'newValues' => ['status' => 'active'],
            ],
            [
                'id' => 117,
                'userId' => 'JC',
                'userName' => 'Jose Carlos',
                'userColor' => '#1976d2',
                'action' => 'update',
                'modelType' => 'Specialty',
                'description' => 'Especialidad Oftalmologia desactivada',
                'ipAddress' => '192.168.1.1',
                'userAgent' => 'Mozilla/5.0 Safari/17',
                'createdAt' => '2026-03-13 10:30:10',
                'oldValues' => ['status' => 1],
                'newValues' => ['status' => 0],
            ],
            [
                'id' => 118,
                'userId' => 'MG',
                'userName' => 'Dr. Garcia',
                'userColor' => '#28a745',
                'action' => 'delete',
                'modelType' => 'Schedule',
                'description' => 'Horario disponible eliminado',
                'ipAddress' => '192.168.1.15',
                'userAgent' => 'Mozilla/5.0 Firefox/119',
                'createdAt' => '2026-03-13 11:05:42',
                'oldValues' => ['date' => '2026-03-15', 'start_time' => '15:00', 'end_time' => '15:30'],
                'newValues' => [],
            ],
            [
                'id' => 119,
                'userId' => 'AL',
                'userName' => 'Ana Lopez',
                'userColor' => '#9a7b07',
                'action' => 'logout',
                'modelType' => 'User',
                'description' => 'Paciente cerro sesion',
                'ipAddress' => '192.168.1.10',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-13 12:02:55',
                'oldValues' => ['session' => 'active'],
                'newValues' => ['session' => 'destroyed'],
            ],
            [
                'id' => 120,
                'userId' => 'JC',
                'userName' => 'Jose Carlos',
                'userColor' => '#1976d2',
                'action' => 'create',
                'modelType' => 'Specialty',
                'description' => 'Especialidad Pediatria creada',
                'ipAddress' => '192.168.1.1',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-14 08:12:10',
                'oldValues' => [],
                'newValues' => ['name' => 'Pediatria', 'status' => 1],
            ],
            [
                'id' => 121,
                'userId' => 'MG',
                'userName' => 'Dr. Garcia',
                'userColor' => '#28a745',
                'action' => 'create',
                'modelType' => 'Appointment',
                'description' => 'Cita registrada desde agenda del doctor',
                'ipAddress' => '192.168.1.15',
                'userAgent' => 'Mozilla/5.0 Firefox/119',
                'createdAt' => '2026-03-14 08:25:44',
                'oldValues' => [],
                'newValues' => ['patient_id' => 42, 'status' => 'pending'],
            ],
            [
                'id' => 122,
                'userId' => 'JC',
                'userName' => 'Jose Carlos',
                'userColor' => '#1976d2',
                'action' => 'update',
                'modelType' => 'Appointment',
                'description' => 'Cita reasignada de horario',
                'ipAddress' => '192.168.1.1',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-14 09:10:18',
                'oldValues' => ['schedule_id' => 10],
                'newValues' => ['schedule_id' => 12],
            ],
            [
                'id' => 123,
                'userId' => 'AL',
                'userName' => 'Ana Lopez',
                'userColor' => '#9a7b07',
                'action' => 'delete',
                'modelType' => 'Appointment',
                'description' => 'Cita cancelada por paciente',
                'ipAddress' => '192.168.1.10',
                'userAgent' => 'Mozilla/5.0 Chrome/120',
                'createdAt' => '2026-03-14 09:28:36',
                'oldValues' => ['status' => 'confirmed'],
                'newValues' => ['status' => 'cancelled'],
            ],
            [
                'id' => 124,
                'userId' => 'JC',
                'userName' => 'Jose Carlos',
                'userColor' => '#1976d2',
                'action' => 'update',
                'modelType' => 'Specialty',
                'description' => 'Especialidad Cardiologia reactivada',
                'ipAddress' => '192.168.1.1',
                'userAgent' => 'Mozilla/5.0 Safari/17',
                'createdAt' => '2026-03-14 10:05:03',
                'oldValues' => ['status' => 0],
                'newValues' => ['status' => 1],
            ],
            [
                'id' => 125,
                'userId' => 'MG',
                'userName' => 'Dr. Garcia',
                'userColor' => '#28a745',
                'action' => 'logout',
                'modelType' => 'User',
                'description' => 'Doctor cerro sesion',
                'ipAddress' => '192.168.1.15',
                'userAgent' => 'Mozilla/5.0 Firefox/119',
                'createdAt' => '2026-03-14 11:22:41',
                'oldValues' => ['session' => 'active'],
                'newValues' => ['session' => 'destroyed'],
            ],
        ];
    }
}
