<?php

namespace App\Services;

use App\DTO\DaySlotData;
use App\DTO\ProcedureResult;
use App\DTO\UserAccountData;
use App\Models\Role;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Repositories\Contracts\DoctorRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Hashing\Hasher;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Datos demo ficticios para desarrollo (php artisan clinic:seed-demo).
 *
 *  - Todo se crea con los mismos procedimientos api.usp_* que usa la aplicación: las reglas
 *    de negocio, permisos y bitácora se aplican igual que en la interfaz.
 *  - Idempotente: cada elemento se busca antes de crearlo (código de sede, nombre de especialidad,
 *    CMP, documento, usuario). Las reservas solo se generan si aún no hay reservas futuras.
 *  - Las contraseñas se leen de la configuración (.env), se convierten a bcrypt y nunca se imprimen.
 */
final class DemoSeedService
{
    public const ALGORITHM = 'BCRYPT';

    public const BRANCHES = [
        'DEMO-CEN' => ['name' => 'Sede Central', 'address' => 'Av. Demostración 100 (dirección ficticia)'],
        'DEMO-NOR' => ['name' => 'Sede Norte', 'address' => 'Calle Ejemplo 250 (dirección ficticia)'],
    ];

    /** Consultorio propio por médico para evitar cruces: [sede, código, nombre, piso]. */
    public const ROOMS = [
        ['DEMO-CEN', 'C101', 'Consultorio 101', 1], ['DEMO-CEN', 'C102', 'Consultorio 102', 1],
        ['DEMO-CEN', 'C201', 'Consultorio 201', 2], ['DEMO-CEN', 'C202', 'Consultorio 202', 2],
        ['DEMO-NOR', 'N101', 'Consultorio 101', 1], ['DEMO-NOR', 'N102', 'Consultorio 102', 1],
        ['DEMO-NOR', 'N201', 'Consultorio 201', 2], ['DEMO-NOR', 'N202', 'Consultorio 202', 2],
    ];

    public const SPECIALTIES = [
        'Medicina General' => 'Atención primaria y control preventivo.',
        'Pediatría' => 'Atención de niños y adolescentes.',
        'Cardiología' => 'Evaluación y control cardiovascular.',
        'Dermatología' => 'Atención de piel, cabello y uñas.',
        'Ginecología' => 'Salud de la mujer.',
        'Traumatología' => 'Lesiones del sistema musculoesquelético.',
    ];

    /**
     * Médicos ficticios. Cada uno tiene su consultorio, días (ISO 1=lunes) y turno.
     * El primero se vincula a la cuenta demo de médico.
     */
    public const DOCTORS = [
        ['cmp' => 'DEMO-001', 'first' => 'Lucía', 'last' => 'Rivas Paredes', 'specialty' => 'Medicina General', 'branch' => 'DEMO-CEN', 'room' => 'C101', 'days' => [1, 3, 5], 'start' => '08:00', 'end' => '12:00', 'duration' => 20, 'extra_branch' => 'DEMO-NOR'],
        ['cmp' => 'DEMO-002', 'first' => 'Andrés', 'last' => 'Salazar Quispe', 'specialty' => 'Medicina General', 'branch' => 'DEMO-NOR', 'room' => 'N101', 'days' => [2, 4], 'start' => '14:00', 'end' => '18:00', 'duration' => 20],
        ['cmp' => 'DEMO-003', 'first' => 'Valeria', 'last' => 'Montes Ugarte', 'specialty' => 'Pediatría', 'branch' => 'DEMO-CEN', 'room' => 'C102', 'days' => [1, 2, 4], 'start' => '09:00', 'end' => '13:00', 'duration' => 30],
        ['cmp' => 'DEMO-004', 'first' => 'Diego', 'last' => 'Carranza Loayza', 'specialty' => 'Cardiología', 'branch' => 'DEMO-CEN', 'room' => 'C201', 'days' => [2, 5], 'start' => '15:00', 'end' => '19:00', 'duration' => 30, 'extra_specialty' => 'Medicina General'],
        ['cmp' => 'DEMO-005', 'first' => 'Camila', 'last' => 'Herrera Soto', 'specialty' => 'Dermatología', 'branch' => 'DEMO-NOR', 'room' => 'N102', 'days' => [1, 3], 'start' => '08:00', 'end' => '12:00', 'duration' => 20],
        ['cmp' => 'DEMO-006', 'first' => 'Martín', 'last' => 'Vega Flores', 'specialty' => 'Ginecología', 'branch' => 'DEMO-CEN', 'room' => 'C202', 'days' => [3, 4], 'start' => '14:00', 'end' => '18:00', 'duration' => 30],
        ['cmp' => 'DEMO-007', 'first' => 'Sofía', 'last' => 'Delgado Ríos', 'specialty' => 'Traumatología', 'branch' => 'DEMO-NOR', 'room' => 'N201', 'days' => [2, 4, 5], 'start' => '09:00', 'end' => '13:00', 'duration' => 30],
        ['cmp' => 'DEMO-008', 'first' => 'Javier', 'last' => 'Núñez Castro', 'specialty' => 'Pediatría', 'branch' => 'DEMO-NOR', 'room' => 'N202', 'days' => [1, 5], 'start' => '15:00', 'end' => '19:00', 'duration' => 20],
    ];

    /** Pacientes ficticios: documentos con prefijo DEMO, teléfonos 555 y correos .test. */
    public const PATIENTS = [
        ['Carmen', 'Álvarez Torres', 'F', '1985-03-14'], ['Jorge', 'Benítez Ramos', 'M', '1978-11-02'],
        ['Rosa', 'Cárdenas Villa', 'F', '1992-07-21'], ['Luis', 'Domínguez Paz', 'M', '1969-01-30'],
        ['Elena', 'Espinoza Lara', 'F', '2001-05-09'], ['Pedro', 'Fuentes Mejía', 'M', '1988-09-17'],
        ['Ana', 'Gálvez Ortiz', 'F', '1975-12-05'], ['Miguel', 'Hidalgo Rojas', 'M', '1995-04-26'],
        ['Isabel', 'Ibáñez Cruz', 'F', '1983-08-12'], ['Raúl', 'Jiménez León', 'M', '1960-02-19'],
        ['Teresa', 'Lozano Vargas', 'F', '1999-10-03'], ['Hugo', 'Medina Salas', 'M', '1972-06-28'],
        ['Patricia', 'Navarro Gil', 'F', '1990-01-15'], ['Óscar', 'Ochoa Peña', 'M', '1981-03-07'],
        ['Mónica', 'Pacheco Ruiz', 'F', '1966-09-22'], ['Tomás', 'Quiroga Díaz', 'M', '2015-04-11'],
        ['Sara', 'Romero Castillo', 'F', '2012-12-01'], ['Iván', 'Suárez Molina', 'M', '1993-07-04'],
        ['Natalia', 'Tapia Aguirre', 'F', '1987-05-18'], ['Gabriel', 'Ugarte Campos', 'M', '1970-10-25'],
    ];

    /** Cuentas demo: clave de configuración → [rol, nombres, apellidos]. */
    public const ACCOUNTS = [
        'superadmin' => [Role::SUPERADMIN, 'Super', 'Administrador Demo'],
        'admin' => [Role::ADMIN, 'Adriana', 'Administradora Demo'],
        'reception' => [Role::RECEPTIONIST, 'Renato', 'Recepción Demo'],
        'doctor' => [Role::DOCTOR, 'Lucía', 'Rivas Paredes'],
        'patient' => [Role::PATIENT, 'Carmen', 'Álvarez Torres'],
        'auditor' => [Role::AUDITOR, 'Aurelio', 'Auditor Demo'],
    ];

    private const AGENDA_DAYS = 28;

    private const RESERVATIONS_PER_PATIENT = 2;

    /** Códigos de rechazo tras los que se intenta con el siguiente horario libre. */
    private const RETRY_CODES = ['SLOT_OCUPADO', 'SLOT_BLOQUEADO', 'PACIENTE_CITA_SOLAPADA', 'RESERVA_EXISTENTE', 'ANTICIPACION_INSUFICIENTE'];

    /** @var Closure(string, string): void */
    private Closure $log;

    /** @var array<string, int> */
    private array $stats = [];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly CatalogRepositoryInterface $catalog,
        private readonly DoctorRepositoryInterface $doctors,
        private readonly PatientRepositoryInterface $patients,
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly ReservationRepositoryInterface $reservations,
        private readonly AuthRepositoryInterface $auth,
        private readonly Hasher $hasher,
    ) {
        $this->log = static function (string $level, string $message): void {};
    }

    /**
     * @param  array<string, array{user: ?string, email: ?string, password: ?string}>  $accounts
     * @param  Closure(string, string): void|null  $log  (nivel: info|warn, mensaje sin datos sensibles)
     * @return array<string, int> contadores de lo creado/omitido
     */
    public function run(array $accounts, bool $resetPasswords = false, ?Closure $log = null): array
    {
        $this->log = $log ?? $this->log;
        $this->stats = [];
        $this->validateAccounts($accounts);

        $actor = $this->bootstrapSuperadmin($accounts['superadmin'], $resetPasswords);
        $branches = $this->seedBranches($actor);
        $rooms = $this->seedRooms($actor, $branches);
        $specialties = $this->seedSpecialties($actor);
        $doctors = $this->seedDoctors($actor, $specialties, $branches);
        $patients = $this->seedPatients($actor, $accounts['patient']['email'] ?? null);
        $userIds = $this->seedAccounts($actor, $accounts, $resetPasswords, $doctors[self::DOCTORS[0]['cmp']], $patients[0]);
        $this->seedAgendas($actor, $doctors, $specialties, $branches, $rooms);
        $this->seedReservations($userIds['reception'] ?? $actor, $doctors, $patients);
        $this->closePastReservations($actor);

        return $this->stats;
    }

    /** @param  array<string, array{user: ?string, email: ?string, password: ?string}>  $accounts */
    private function validateAccounts(array $accounts): void
    {
        foreach (array_keys(self::ACCOUNTS) as $key) {
            $a = $accounts[$key] ?? [];
            if (blank($a['user'] ?? null) || blank($a['password'] ?? null)) {
                throw new RuntimeException("Falta usuario o contraseña de la cuenta demo «{$key}» en .env (DEMO_*_USER / DEMO_*_PASSWORD).");
            }
            if (preg_match('/^[A-Za-z0-9._-]{3,50}$/', (string) $a['user']) !== 1) {
                throw new RuntimeException("El usuario demo «{$key}» solo admite letras, números, punto, guion y guion bajo.");
            }
            if (strlen((string) $a['password']) < 10) {
                throw new RuntimeException("La contraseña demo «{$key}» debe tener al menos 10 caracteres.");
            }
        }
    }

    private function bootstrapSuperadmin(array $account, bool $resetPasswords): int
    {
        [, $first, $last] = self::ACCOUNTS['superadmin'];
        $result = $this->users->bootstrapSuperadmin(
            ['username' => $account['user'], 'email' => $account['email'] ?? null, 'first_names' => $first, 'last_names' => $last],
            $this->hasher->make((string) $account['password']),
            self::ALGORITHM,
        );

        if (! $result->success && $result->code !== 'YA_INICIALIZADO') {
            throw new RuntimeException('No se pudo inicializar el superadministrador: '.$result->userMessage());
        }
        if ($result->entityId === null) {
            throw new RuntimeException('El arranque no devolvió el superadministrador.');
        }

        $this->count($result->success ? 'usuarios creados' : 'usuarios existentes');
        $this->info($result->success ? "Superadministrador «{$account['user']}» creado." : 'Superadministrador existente reutilizado.');

        if (! $result->success && $resetPasswords) {
            $existing = $this->findUser($result->entityId, (string) $account['user']);
            if ($existing !== null) {
                $this->resetPassword($result->entityId, $existing->id, (string) $account['password'], 'superadmin');
            }
        }

        return $result->entityId;
    }

    /** @return array<string, int> código → SedeId */
    private function seedBranches(int $actor): array
    {
        $existing = [];
        foreach ($this->catalog->branches(false) as $row) {
            $existing[(string) $row['Codigo']] = (int) $row['SedeId'];
        }

        foreach (self::BRANCHES as $code => $b) {
            if (! isset($existing[$code])) {
                $existing[$code] = $this->created('sedes creadas', $this->catalog->saveBranch($actor, null, ['code' => $code, 'name' => $b['name'], 'address' => $b['address']]), "sede {$code}");
            } else {
                $this->count('sedes existentes');
            }
        }

        return array_intersect_key($existing, self::BRANCHES);
    }

    /** @return array<string, int> "sede|código" → ConsultorioId */
    private function seedRooms(int $actor, array $branches): array
    {
        $existing = [];
        foreach ($this->catalog->rooms(null, false) as $row) {
            $existing[$row['SedeId'].'|'.$row['Codigo']] = (int) $row['ConsultorioId'];
        }

        $rooms = [];
        foreach (self::ROOMS as [$branch, $code, $name, $floor]) {
            $key = $branches[$branch].'|'.$code;
            if (! isset($existing[$key])) {
                $existing[$key] = $this->created('consultorios creados', $this->catalog->saveRoom($actor, null, ['branch_id' => $branches[$branch], 'code' => $code, 'name' => $name, 'floor' => $floor]), "consultorio {$code}");
            } else {
                $this->count('consultorios existentes');
            }
            $rooms[$branch.'|'.$code] = $existing[$key];
        }

        return $rooms;
    }

    /** @return array<string, int> nombre → EspecialidadId */
    private function seedSpecialties(int $actor): array
    {
        $existing = [];
        foreach ($this->catalog->specialties(false) as $row) {
            $existing[mb_strtolower((string) $row['Nombre'])] = (int) $row['EspecialidadId'];
        }

        $ids = [];
        foreach (self::SPECIALTIES as $name => $description) {
            $key = mb_strtolower($name);
            if (! isset($existing[$key])) {
                $existing[$key] = $this->created('especialidades creadas', $this->catalog->saveSpecialty($actor, null, ['name' => $name, 'description' => $description]), "especialidad {$name}");
            } else {
                $this->count('especialidades existentes');
            }
            $ids[$name] = $existing[$key];
        }

        return $ids;
    }

    /** @return array<string, int> CMP → MedicoId */
    private function seedDoctors(int $actor, array $specialties, array $branches): array
    {
        $ids = [];
        foreach (self::DOCTORS as $i => $d) {
            $found = collect($this->doctors->search($actor, $d['cmp'], null, null, null, 1, 10)->items)
                ->first(fn ($doc) => $doc->cmp === $d['cmp']);

            if ($found !== null) {
                $this->count('médicos existentes');
                $doctor = $found;
                $id = $found->id;
            } else {
                $id = $this->created('médicos creados', $this->doctors->create($actor, [
                    'cmp' => $d['cmp'],
                    'first_names' => $d['first'],
                    'last_names' => $d['last'],
                    'phone' => sprintf('01-555-02%02d', $i + 1),
                    'email' => 'medico'.($i + 1).'.demo@nexasalud.test',
                    'specialty_id' => $specialties[$d['specialty']],
                    'branch_id' => $branches[$d['branch']],
                ]), "médico {$d['cmp']}");
                $doctor = $this->doctors->find($actor, $id);
            }

            if (isset($d['extra_specialty']) && ! in_array($specialties[$d['extra_specialty']], $doctor?->specialtyIds ?? [], true)) {
                $this->ensure($this->doctors->assignSpecialty($actor, $id, $specialties[$d['extra_specialty']], true), "especialidad adicional de {$d['cmp']}");
            }
            if (isset($d['extra_branch']) && ! in_array($branches[$d['extra_branch']], $doctor?->branchIds ?? [], true)) {
                $this->ensure($this->doctors->assignBranch($actor, $id, $branches[$d['extra_branch']], true), "sede adicional de {$d['cmp']}");
            }

            $ids[$d['cmp']] = $id;
        }

        return $ids;
    }

    /** @return list<int> PacienteId en el orden de PATIENTS */
    private function seedPatients(int $actor, ?string $firstPatientEmail): array
    {
        $ids = [];
        foreach (self::PATIENTS as $i => [$first, $last, $sex, $birth]) {
            $n = $i + 1;
            $type = $n % 5 === 0 ? 'PASAPORTE' : 'CE';
            $document = ($type === 'CE' ? 'DEMO' : 'DEMOP').sprintf('%04d', $n);

            $found = collect($this->patients->search($actor, $document, null, 1, 10)->items)
                ->first(fn ($p) => $p->documentNumber === $document);

            if ($found !== null) {
                $this->count('pacientes existentes');
                $ids[] = $found->id;

                continue;
            }

            $ids[] = $this->created('pacientes creados', $this->patients->create($actor, [
                'document_type' => $type,
                'document_number' => $document,
                'first_names' => $first,
                'last_names' => $last,
                'birth_date' => $birth,
                'sex' => $sex,
                'phone' => sprintf('01-555-01%02d', $n),
                'email' => $n === 1 && filled($firstPatientEmail) ? $firstPatientEmail : "paciente{$n}.demo@nexasalud.test",
                'address' => "Calle Ficticia {$n}00",
                'emergency_contact' => 'Contacto demo 01-555-0999',
            ]), "paciente {$document}");
        }

        return $ids;
    }

    /** @return array<string, int> clave de cuenta → UsuarioId */
    private function seedAccounts(int $actor, array $accounts, bool $resetPasswords, int $doctorId, int $patientId): array
    {
        $ids = [];
        foreach (self::ACCOUNTS as $key => [$role, $first, $last]) {
            $a = $accounts[$key];
            $username = (string) $a['user'];
            $existing = $this->findUser($actor, $username);
            $link = match ($role) {
                Role::DOCTOR => ['doctor_id' => $doctorId],
                Role::PATIENT => ['patient_id' => $patientId],
                default => [],
            };

            if ($existing === null) {
                $id = $this->created('usuarios creados', $this->users->create($actor, [
                    'username' => $username,
                    'email' => $a['email'] ?? null,
                    'first_names' => $first,
                    'last_names' => $last,
                    'role' => $role,
                ] + $link, $this->hasher->make((string) $a['password']), self::ALGORITHM), "usuario {$username}");
                $this->info("Cuenta demo «{$username}» creada con rol ".Role::label($role).'.');
                $ids[$key] = $id;

                continue;
            }

            $ids[$key] = $existing->id;
            if ($key !== 'superadmin') {
                $this->count('usuarios existentes');
            }
            if (! in_array($role, $existing->roles, true)) {
                $this->ensure($this->users->assignRole($actor, $existing->id, $role, true), "rol {$role} de {$username}");
            }
            if (($link['doctor_id'] ?? null) !== null && $existing->doctorId === null
                || ($link['patient_id'] ?? null) !== null && $existing->patientId === null) {
                $this->ensure($this->users->linkProfile($actor, $existing->id, $link['doctor_id'] ?? null, $link['patient_id'] ?? null), "vínculo de {$username}");
            }
            if ($resetPasswords && $key !== 'superadmin') {
                $this->resetPassword($actor, $existing->id, (string) $a['password'], $key);
            }
        }

        return $ids;
    }

    private function seedAgendas(int $actor, array $doctors, array $specialties, array $branches, array $rooms): void
    {
        $today = LocalTime::today();
        $from = $today->addDay();
        $to = $today->addDays(self::AGENDA_DAYS);
        $careType = collect($this->catalog->careTypes())->firstWhere('Codigo', 'PRESENCIAL')['TipoAtencionId'] ?? 1;

        foreach (self::DOCTORS as $d) {
            $doctorId = $doctors[$d['cmp']];
            $future = $this->schedules->listAgendas($actor, $doctorId, null, null, $from->toDateString(), $to->toDateString(), true, 1, 1);
            if ($future->total > 0) {
                $this->count('médicos con agenda existente');

                continue;
            }

            $result = $this->schedules->generateRange($actor, [
                'doctor_id' => $doctorId,
                'specialty_id' => $specialties[$d['specialty']],
                'branch_id' => $branches[$d['branch']],
                'room_id' => $rooms[$d['branch'].'|'.$d['room']],
                'care_type_id' => $careType,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'weekdays' => $d['days'],
                'start' => $d['start'],
                'end' => $d['end'],
                'duration' => $d['duration'],
            ]);
            $this->ensure($result, "agenda de {$d['cmp']}");
            $this->count('rangos de agenda generados');
        }
    }

    /**
     * Reservas en varios estados (confirmadas, canceladas y reprogramadas), creadas por recepción.
     * Solo se generan si no existen reservas futuras: volver a ejecutar no duplica.
     */
    private function seedReservations(int $actor, array $doctors, array $patients): void
    {
        $today = LocalTime::today();
        $existing = $this->reservations->search($actor, ['FechaDesde' => $today->toDateString(), 'FechaHasta' => $today->addDays(self::AGENDA_DAYS + 30)->toDateString()], 1, 1);
        if ($existing->total > 0) {
            $this->info('Ya existen reservas futuras: no se generan nuevas.');
            $this->count('reservas existentes', $existing->total);

            return;
        }

        $free = $this->freeSlots($actor, array_values($doctors), $today);
        $doctorIds = array_values($doctors);
        $reasons = array_column($this->catalog->cancellationReasons(true), 'MotivoCancelacionId');
        $created = [];

        foreach ($patients as $p => $patientId) {
            for ($r = 0; $r < self::RESERVATIONS_PER_PATIENT; $r++) {
                $k = $p * self::RESERVATIONS_PER_PATIENT + $r;
                $doctorId = $doctorIds[($p + $r * 3) % count($doctorIds)];
                $id = $this->book($actor, $patientId, $free[$doctorId], $k);
                if ($id !== null) {
                    $created[] = ['id' => $id, 'doctor' => $doctorId, 'k' => $k];
                }
            }
        }

        foreach ($created as $c) {
            if ($c['k'] % 7 === 3) {
                $detail = $this->reservations->find($actor, $c['id']);
                $reason = (int) $reasons[$c['k'] % max(1, count($reasons))];
                if ($detail !== null && $this->ensure($this->reservations->cancel($actor, $c['id'], $reason, 'Cancelación de demostración', $detail->version), 'cancelación demo')) {
                    $this->count('reservas canceladas');
                }
            } elseif ($c['k'] % 9 === 5) {
                $detail = $this->reservations->find($actor, $c['id']);
                $slot = array_pop($free[$c['doctor']]);
                if ($detail !== null && $slot !== null
                    && $this->ensure($this->reservations->reschedule($actor, $c['id'], $slot->slotId, 'Reprogramación de demostración', $detail->version, $this->key('reprogramar', $c['k'])), 'reprogramación demo')) {
                    $this->count('reservas reprogramadas');
                }
            }
        }
    }

    /** Cierra (atendida / no asistió) las reservas demo confirmadas cuya hora ya pasó. */
    private function closePastReservations(int $actor): void
    {
        $today = LocalTime::today();
        $page = $this->reservations->search($actor, [
            'FechaDesde' => $today->subDays(60)->toDateString(),
            'FechaHasta' => $today->toDateString(),
            'EstadoReservaId' => 1,
        ], 1, 100);

        $now = LocalTime::now();
        foreach ($page->items as $i => $r) {
            if (CarbonImmutable::parse(substr($r->date, 0, 10).' '.$r->start, $now->getTimezone())->greaterThan($now)) {
                continue;
            }
            $result = $i % 4 === 3
                ? $this->reservations->markNoShow($actor, $r->id, 'Cierre demo', $r->version)
                : $this->reservations->markAttended($actor, $r->id, 'Cierre demo', $r->version);
            if ($this->ensure($result, "cierre de {$r->code}")) {
                $this->count($i % 4 === 3 ? 'reservas no asistió' : 'reservas atendidas');
            }
        }
    }

    /** @return array<int, list<DaySlotData>> MedicoId → horarios libres futuros (orden cronológico) */
    private function freeSlots(int $actor, array $doctorIds, CarbonImmutable $today): array
    {
        $free = [];
        foreach ($doctorIds as $doctorId) {
            $free[$doctorId] = [];
            $agendas = $this->schedules->listAgendas($actor, $doctorId, null, null, $today->addDay()->toDateString(), $today->addDays(self::AGENDA_DAYS)->toDateString(), true, 1, 100);
            foreach ($agendas->items as $agenda) {
                foreach ($this->schedules->doctorDay($actor, $doctorId, $agenda->date) as $slot) {
                    if ($slot->isFree()) {
                        $free[$doctorId][] = $slot;
                    }
                }
            }
        }

        return $free;
    }

    /** @param  list<DaySlotData>  $slots  se consumen los horarios usados */
    private function book(int $actor, int $patientId, array &$slots, int $k): ?int
    {
        // Distribuye las citas en el tiempo: cada reserva salta algunos horarios.
        for ($attempt = 0; $attempt < 6 && $slots !== []; $attempt++) {
            $index = min(count($slots) - 1, ($k * 5 + $attempt) % max(1, intdiv(count($slots), 2)));
            $slot = $slots[$index];
            array_splice($slots, $index, 1);

            $result = $this->reservations->createForPatient($actor, $patientId, $slot->slotId, $k % 3 === 0 ? 'Reserva de demostración' : null, $this->key('reservar', $k, $attempt));
            if ($result->success) {
                $this->count('reservas creadas');

                return $result->entityId;
            }
            if (! in_array($result->code, self::RETRY_CODES, true)) {
                $this->warn("Reserva demo omitida ({$result->code}): {$result->userMessage()}");

                return null;
            }
        }

        return null;
    }

    private function findUser(int $actor, string $username): ?UserAccountData
    {
        $match = collect($this->users->search($actor, $username, null, null, 1, 20)->items)
            ->first(fn (UserAccountData $u) => strcasecmp($u->username, $username) === 0);

        return $match === null ? null : ($this->users->find($actor, $match->id) ?? $match);
    }

    private function resetPassword(int $actor, int $userId, string $password, string $key): void
    {
        $this->ensure(
            $this->auth->updatePasswordHash($actor, $userId, $this->hasher->make($password), self::ALGORITHM),
            "contraseña de la cuenta {$key}",
        );
        $this->info("Contraseña de la cuenta demo «{$key}» actualizada desde .env.");
    }

    /** Clave de idempotencia determinista: reintentar el comando no duplica la operación. */
    private function key(string $operation, int ...$parts): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'nexa-demo:'.$operation.':'.implode(':', $parts).':'.LocalTime::today()->toDateString())->toString();
    }

    private function created(string $stat, ProcedureResult $result, string $label): int
    {
        if (! $result->success || $result->entityId === null) {
            throw new RuntimeException("No se pudo crear {$label}: {$result->userMessage()} ({$result->code})");
        }
        $this->count($stat);

        return $result->entityId;
    }

    private function ensure(ProcedureResult $result, string $label): bool
    {
        if (! $result->success) {
            $this->warn("{$label}: {$result->userMessage()} ({$result->code})");
        }

        return $result->success;
    }

    private function count(string $key, int $by = 1): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + $by;
    }

    private function info(string $message): void
    {
        ($this->log)('info', $message);
    }

    private function warn(string $message): void
    {
        ($this->log)('warn', $message);
    }
}
