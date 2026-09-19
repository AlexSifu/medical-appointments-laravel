<?php

use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\ReservationAdminController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auditor\AuditController;
use App\Http\Controllers\Auditor\ReportController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Doctor\AgendaController;
use App\Http\Controllers\DoctorDirectoryController;
use App\Http\Controllers\Patient\ProfileController;
use App\Http\Controllers\Reception\PatientController;
use App\Http\Controllers\Reception\ReceptionController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

/*
| Todas las rutas autenticadas pasan por "refresh.user" (relee roles/permisos de SQL Server)
| y por "permission:<codigo>[,<codigo>]" (OR). Los SP vuelven a validar permiso y pertenencia.
| Los parámetros {reservation}, {patient}, ... son enteros; nunca nombres de SP ni columnas.
*/

Route::pattern('reservation', '[0-9]+');
Route::pattern('patient', '[0-9]+');
Route::pattern('doctor', '[0-9]+');
Route::pattern('agenda', '[0-9]+');
Route::pattern('block', '[0-9]+');
Route::pattern('user', '[0-9]+');
Route::pattern('specialty', '[0-9]+');
Route::pattern('branch', '[0-9]+');

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'refresh.user'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // ---- Reserva (paciente para sí; recepción para un paciente) ----
    Route::middleware('permission:reservas.crear')->group(function (): void {
        Route::get('/reservar', [BookingController::class, 'create'])->name('booking.create');
        Route::get('/reservar/medicos', [BookingController::class, 'doctors'])->name('booking.doctors');
        Route::get('/reservar/fechas', [BookingController::class, 'dates'])->name('booking.dates');
        Route::get('/reservar/horarios', [BookingController::class, 'slots'])->name('booking.slots');
        Route::post('/reservas', [BookingController::class, 'store'])->middleware('throttle:30,1')->name('reservations.store');
    });

    Route::get('/medicos', DoctorDirectoryController::class)->middleware('permission:medicos.ver')->name('doctors.directory');

    // ---- Reserva existente (el SP valida pertenencia) ----
    Route::get('/reservas/{reservation}', [ReservationController::class, 'show'])
        ->middleware('permission:reservas.propias,reservas.gestionar,reservas.estado')->name('reservations.show');
    Route::post('/reservas/{reservation}/cancelar', [ReservationController::class, 'cancel'])
        ->middleware('permission:reservas.cancelar')->name('reservations.cancel');
    Route::get('/reservas/{reservation}/reprogramar', [ReservationController::class, 'editReschedule'])
        ->middleware('permission:reservas.reprogramar')->name('reservations.reschedule.edit');
    Route::post('/reservas/{reservation}/reprogramar', [ReservationController::class, 'reschedule'])
        ->middleware(['permission:reservas.reprogramar', 'throttle:30,1'])->name('reservations.reschedule');
    Route::post('/reservas/{reservation}/atendida', [ReservationController::class, 'attended'])
        ->middleware('permission:reservas.estado')->name('reservations.attended');
    Route::post('/reservas/{reservation}/no-asistio', [ReservationController::class, 'noShow'])
        ->middleware('permission:reservas.estado')->name('reservations.no-show');

    // ---- Paciente ----
    Route::middleware('permission:reservas.propias')->name('patient.')->group(function (): void {
        Route::get('/mis-citas', [ReservationController::class, 'mine'])->name('appointments');
        Route::get('/mi-perfil', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/mi-perfil', [ProfileController::class, 'update'])->name('profile.update');
    });

    // ---- Médico ----
    Route::middleware('permission:agenda.ver')->prefix('medico')->name('doctor.')->group(function (): void {
        Route::get('/hoy', [AgendaController::class, 'day'])->name('day');
        Route::get('/agenda', [AgendaController::class, 'agenda'])->name('agenda');
    });

    // ---- Recepción ----
    Route::prefix('recepcion')->name('reception.')->group(function (): void {
        Route::get('/', ReceptionController::class)->middleware('permission:reservas.gestionar')->name('index');
        Route::get('/pacientes', [PatientController::class, 'index'])->middleware('permission:pacientes.ver')->name('patients.index');
        Route::get('/pacientes/nuevo', [PatientController::class, 'create'])->middleware('permission:pacientes.crear')->name('patients.create');
        Route::post('/pacientes', [PatientController::class, 'store'])->middleware('permission:pacientes.crear')->name('patients.store');
        Route::get('/pacientes/{patient}', [PatientController::class, 'show'])->middleware('permission:pacientes.ver')->name('patients.show');
        Route::get('/pacientes/{patient}/editar', [PatientController::class, 'edit'])->middleware('permission:pacientes.editar')->name('patients.edit');
        Route::put('/pacientes/{patient}', [PatientController::class, 'update'])->middleware('permission:pacientes.editar')->name('patients.update');
    });

    // ---- Administración ----
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/reservas', [ReservationAdminController::class, 'index'])->middleware('permission:reservas.gestionar')->name('reservations.index');

        Route::middleware('permission:medicos.ver')->group(function (): void {
            Route::get('/medicos', [DoctorController::class, 'index'])->name('doctors.index');
        });
        Route::middleware('permission:medicos.crear')->group(function (): void {
            Route::get('/medicos/nuevo', [DoctorController::class, 'create'])->name('doctors.create');
            Route::post('/medicos', [DoctorController::class, 'store'])->name('doctors.store');
        });
        Route::middleware('permission:medicos.editar')->group(function (): void {
            Route::get('/medicos/{doctor}/editar', [DoctorController::class, 'edit'])->name('doctors.edit');
            Route::put('/medicos/{doctor}', [DoctorController::class, 'update'])->name('doctors.update');
            Route::post('/medicos/{doctor}/estado', [DoctorController::class, 'status'])->name('doctors.status');
            Route::post('/medicos/{doctor}/asignaciones', [DoctorController::class, 'assign'])->name('doctors.assign');
        });

        Route::middleware('permission:agenda.gestionar')->group(function (): void {
            Route::get('/agendas', [ScheduleController::class, 'index'])->name('schedules.index');
            Route::get('/agendas/nueva', [ScheduleController::class, 'create'])->name('schedules.create');
            Route::post('/agendas', [ScheduleController::class, 'store'])->name('schedules.store');
            Route::post('/agendas/{agenda}/desactivar', [ScheduleController::class, 'deactivate'])->name('schedules.deactivate');
        });
        Route::get('/agendas/dia', [ScheduleController::class, 'day'])
            ->middleware('permission:agenda.gestionar,reservas.gestionar')->name('schedules.day');
        Route::middleware('permission:agenda.bloquear')->group(function (): void {
            Route::get('/bloqueos', [ScheduleController::class, 'blocks'])->name('schedules.blocks');
            Route::post('/bloqueos', [ScheduleController::class, 'block'])->name('schedules.block');
            Route::post('/bloqueos/{block}/levantar', [ScheduleController::class, 'unblock'])->name('schedules.unblock');
        });

        Route::get('/usuarios', [UserController::class, 'index'])->middleware('permission:usuarios.ver')->name('users.index');
        Route::get('/usuarios/nuevo', [UserController::class, 'create'])->middleware('permission:usuarios.crear')->name('users.create');
        Route::post('/usuarios', [UserController::class, 'store'])->middleware('permission:usuarios.crear')->name('users.store');
        Route::get('/usuarios/{user}', [UserController::class, 'show'])->middleware('permission:usuarios.ver')->name('users.show');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->middleware('permission:usuarios.editar')->name('users.update');
        Route::post('/usuarios/{user}/roles', [UserController::class, 'role'])->middleware('permission:usuarios.roles')->name('users.role');
        Route::post('/usuarios/{user}/password', [UserController::class, 'password'])->middleware('permission:usuarios.editar')->name('users.password');

        Route::middleware('permission:especialidades.gestionar')->group(function (): void {
            Route::get('/especialidades', [CatalogController::class, 'specialties'])->name('specialties.index');
            Route::post('/especialidades', [CatalogController::class, 'saveSpecialty'])->name('specialties.store');
            Route::put('/especialidades/{specialty}', [CatalogController::class, 'saveSpecialty'])->name('specialties.update');
        });
        Route::middleware('permission:sedes.gestionar')->group(function (): void {
            Route::get('/sedes', [CatalogController::class, 'branches'])->name('branches.index');
            Route::post('/sedes', [CatalogController::class, 'saveBranch'])->name('branches.store');
            Route::put('/sedes/{branch}', [CatalogController::class, 'saveBranch'])->name('branches.update');
        });
    });

    // ---- Auditoría y reportes ----
    Route::get('/auditoria', AuditController::class)->middleware('permission:auditoria.ver')->name('audit.index');
    Route::get('/reportes/{report?}', ReportController::class)
        ->middleware('permission:reportes.ver')
        ->where('report', 'especialidades|ocupacion|cancelaciones|no-asistencia')
        ->name('reports.index');
});
