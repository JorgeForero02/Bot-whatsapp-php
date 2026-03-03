<?php

namespace App\Handlers;

use App\Core\Database;
use App\Core\Logger;
use App\Services\GoogleCalendarService;
use App\Services\OpenAIService;
use App\Services\ConversationService;

class CalendarFlowHandler
{
    const STEP_EXPECTING_DATE = 'expecting_date';
    const STEP_EXPECTING_TIME = 'expecting_time';
    const STEP_EXPECTING_SERVICE = 'expecting_service';
    const STEP_EXPECTING_CONFIRMATION = 'expecting_confirmation';
    const STEP_CANCEL_SELECT = 'cancel_select';
    const STEP_CANCEL_CONFIRM = 'cancel_confirm';
    const STEP_RESCHEDULE_SELECT = 'reschedule_select';
    const STEP_RESCHEDULE_REASON = 'reschedule_reason';

    const MAX_ATTEMPTS = 5;
    const FLOW_TTL_MINUTES = 30;

    private $db;
    private $logger;
    private $calendar;
    private $openai;
    private $calendarConfig;
    private $conversationService;

    public function __construct(
        Database $db,
        Logger $logger,
        GoogleCalendarService $calendar,
        OpenAIService $openai,
        array $calendarConfig,
        ConversationService $conversationService
    ) {
        $this->db = $db;
        $this->logger = $logger;
        $this->calendar = $calendar;
        $this->openai = $openai;
        $this->calendarConfig = $calendarConfig;
        $this->conversationService = $conversationService;
    }

    public function getFlowState(string $phone): ?array
    {
        $state = $this->db->fetchOne(
            'SELECT * FROM calendar_flow_state WHERE user_phone = :phone',
            [':phone' => $phone]
        );

        return $state ?: null;
    }

    public function handleActiveFlow(
        array $flowState,
        string $message,
        array $conversation,
        array $messageData
    ): array {
        if ($this->isFlowExpired($flowState)) {
            $this->logger->info('Calendar flow expired, clearing state', [
                'phone' => $messageData['from'],
                'step' => $flowState['current_step'],
                'expired_at' => $flowState['expires_at']
            ]);
            $this->clearFlowState($messageData['from']);
            return ['handled' => false, 'response' => '', 'status' => 'flow_expired'];
        }

        $this->touchFlowState($messageData['from']);

        $messageLower = mb_strtolower(trim($message));
        if ($this->isFlowCancelRequest($messageLower, $flowState['current_step'])) {
            $this->clearFlowState($messageData['from']);
            return [
                'handled' => true,
                'response' => 'Entendido, cancelé el proceso. ¿En qué más puedo ayudarte?',
                'status' => 'flow_cancelled'
            ];
        }

        // Si el mensaje es una nueva intención de agendar, limpiar flujo y dejar que el webhook lo procese como intent nuevo
        $step = $flowState['current_step'];
        if (in_array($step, [self::STEP_EXPECTING_DATE, self::STEP_EXPECTING_TIME, self::STEP_EXPECTING_SERVICE])
            && $this->isNewScheduleIntent($messageLower)
            && !$this->resolveDate($message)
            && !$this->resolveTime($message)
        ) {
            $this->clearFlowState($messageData['from']);
            return ['handled' => false, 'response' => '', 'status' => 'flow_reset_new_intent'];
        }

        switch ($flowState['current_step']) {
            case self::STEP_EXPECTING_DATE:
                return $this->handleExpectingDate($message, $flowState, $messageData);
            case self::STEP_EXPECTING_TIME:
                return $this->handleExpectingTime($message, $flowState, $messageData);
            case self::STEP_EXPECTING_SERVICE:
                return $this->handleExpectingService($message, $flowState, $messageData);
            case self::STEP_EXPECTING_CONFIRMATION:
                return $this->handleExpectingConfirmation($message, $flowState, $messageData);
            case self::STEP_CANCEL_SELECT:
                return $this->handleCancelSelect($message, $flowState, $messageData);
            case self::STEP_CANCEL_CONFIRM:
                return $this->handleCancelConfirm($message, $flowState, $messageData);
            case self::STEP_RESCHEDULE_SELECT:
                return $this->handleRescheduleSelect($message, $flowState, $messageData);
            case self::STEP_RESCHEDULE_REASON:
                return $this->handleRescheduleReason($message, $flowState, $messageData);
            default:
                $this->logger->warning('Unknown flow step', ['step' => $flowState['current_step']]);
                $this->clearFlowState($messageData['from']);
                return ['handled' => false, 'response' => '', 'status' => 'unknown_step'];
        }
    }

    public function startFlow(
        string $intent,
        array $extractedData,
        array $conversation,
        array $messageData
    ): array {
        switch ($intent) {
            case 'schedule':
                return $this->startScheduleFlow($extractedData, $conversation, $messageData);
            case 'check_availability':
                return $this->handleCheckAvailability($extractedData, $conversation, $messageData);
            case 'cancel':
                return $this->startCancelFlow($conversation, $messageData);
            case 'reschedule':
                return $this->startRescheduleFlow($conversation, $messageData);
            case 'list':
                return $this->startListFlow($messageData);
            default:
                return ['handled' => false, 'response' => '', 'status' => 'unknown_intent'];
        }
    }

    public function clearFlowState(string $phone): void
    {
        $this->db->query(
            'DELETE FROM calendar_flow_state WHERE user_phone = :phone',
            [':phone' => $phone]
        );
    }

    private function startScheduleFlow(array $extractedData, array $conversation, array $messageData): array
    {
        $phone = $messageData['from'];
        $contactName = $messageData['contact_name'] ?? 'Cliente';
        $eventTitle = 'Cita - ' . $contactName;

        $date = $this->resolveDate($extractedData['date_preference'] ?? '');
        $time = $this->resolveTime($extractedData['time_preference'] ?? '');
        $service = $extractedData['service_type'] ?? null;

        if ($date && $time) {
            $validation = $this->validateAppointment($date, $time);
            if (!$validation['valid']) {
                $this->saveFlowState($phone, self::STEP_EXPECTING_TIME, $conversation['id'], [
                    'extracted_date' => $date,
                    'event_title' => $eventTitle,
                    'extracted_service' => $service
                ]);
                return [
                    'handled' => true,
                    'response' => $validation['message'] . "\n\n¿A qué hora prefieres? (Ejemplo: 14:00 o 3pm)",
                    'status' => 'schedule_validation_failed'
                ];
            }

            // Siempre pedir motivo antes de confirmar
            $this->saveFlowState($phone, self::STEP_EXPECTING_SERVICE, $conversation['id'], [
                'extracted_date' => $date,
                'extracted_time' => $time,
                'extracted_service' => $service,
                'event_title' => $eventTitle
            ]);

            if ($service) {
                // Ya tenemos motivo, pasar directo a confirmación
                return $this->buildConfirmationStep($phone, $conversation['id'], $date, $time, $service, $eventTitle);
            }

            $dateFormatted = $this->formatDateForUser($date);
            return [
                'handled' => true,
                'response' => "Perfecto, {$dateFormatted} a las {$time}.\n\n¿Cuál es el motivo de la cita? (Ej: consulta, revisión, reunión...)",
                'status' => 'expecting_service'
            ];
        }

        if ($date) {
            $dateCheck = $this->calendar->validateDateNotPast($date);
            if (!$dateCheck['valid']) {
                $this->saveFlowState($phone, self::STEP_EXPECTING_DATE, $conversation['id'], [
                    'event_title' => $eventTitle,
                    'extracted_service' => $service
                ]);
                return [
                    'handled' => true,
                    'response' => $dateCheck['message'] . "\n\nPor favor indica otra fecha.",
                    'status' => 'schedule_past_date'
                ];
            }

            $this->saveFlowState($phone, self::STEP_EXPECTING_TIME, $conversation['id'], [
                'extracted_date' => $date,
                'extracted_service' => $service,
                'event_title' => $eventTitle
            ]);

            $dateFormatted = $this->formatDateForUser($date);
            return [
                'handled' => true,
                'response' => "Perfecto, {$dateFormatted}. ¿A qué hora? (Ejemplo: 14:00 o 3pm)",
                'status' => 'expecting_time'
            ];
        }

        $this->saveFlowState($phone, self::STEP_EXPECTING_DATE, $conversation['id'], [
            'event_title' => $eventTitle,
            'extracted_service' => $service
        ]);

        return [
            'handled' => true,
            'response' => "Con gusto te agendo una cita. ¿Qué fecha y hora prefieres?\n\nEjemplos: \"mañana a las 3pm\", \"el viernes a las 10am\", \"15/03/2026 a las 14:00\"",
            'status' => 'expecting_date'
        ];
    }

    private function handleExpectingDate(string $message, array $flowState, array $messageData): array
    {
        $phone = $messageData['from'];
        $date = $this->resolveDate($message);

        if ($date) {
            $dateCheck = $this->calendar->validateDateNotPast($date);
            if (!$dateCheck['valid']) {
                return [
                    'handled' => true,
                    'response' => $dateCheck['message'],
                    'status' => 'event_flow_past_date'
                ];
            }

            // Try to extract time from the same message
            $time = $this->resolveTime($message);
            if ($time) {
                $validation = $this->validateAppointment($date, $time);
                if ($validation['valid']) {
                    $service = $flowState['extracted_service'] ?? null;
                    $this->db->query(
                        'UPDATE calendar_flow_state SET current_step = :step, extracted_date = :date, extracted_time = :time, attempts = 0, expires_at = :expires WHERE user_phone = :phone',
                        [
                            ':step'    => self::STEP_EXPECTING_SERVICE,
                            ':date'    => $date,
                            ':time'    => $time,
                            ':expires' => $this->newExpiry(),
                            ':phone'   => $phone
                        ]
                    );
                    if ($service) {
                        return $this->buildConfirmationStep($phone, $flowState['conversation_id'], $date, $time, $service, $flowState['event_title'] ?? null);
                    }
                    $dateFormatted = $this->formatDateForUser($date);
                    return [
                        'handled'  => true,
                        'response' => "Perfecto, {$dateFormatted} a las {$time}.\n\n¿Cuál es el motivo de la cita? (Ej: consulta, revisión, reunión...)",
                        'status'   => 'expecting_service'
                    ];
                }
            }

            $this->db->query(
                'UPDATE calendar_flow_state SET current_step = :step, extracted_date = :date, attempts = 0, expires_at = :expires WHERE user_phone = :phone',
                [
                    ':step' => self::STEP_EXPECTING_TIME,
                    ':date' => $date,
                    ':expires' => $this->newExpiry(),
                    ':phone' => $phone
                ]
            );

            $dateFormatted = $this->formatDateForUser($date);
            return [
                'handled' => true,
                'response' => "Perfecto, {$dateFormatted}. ¿A qué hora? (Ejemplo: 14:00 o 3pm)",
                'status' => 'expecting_time'
            ];
        }

        $attempts = intval($flowState['attempts']) + 1;
        $this->db->query(
            'UPDATE calendar_flow_state SET attempts = :attempts, expires_at = :expires WHERE user_phone = :phone',
            [':attempts' => $attempts, ':expires' => $this->newExpiry(), ':phone' => $phone]
        );

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->clearFlowState($phone);
            return [
                'handled' => true,
                'response' => "No pude entender tu respuesta. El proceso fue cancelado. Escríbeme cuando quieras intentarlo de nuevo.",
                'status' => 'flow_max_attempts'
            ];
        }

        return [
            'handled' => true,
            'response' => "No entendí esa fecha. Intenta con: 25/03/2026, mañana, o el viernes.",
            'status' => 'event_flow_invalid_date'
        ];
    }

    private function handleExpectingTime(string $message, array $flowState, array $messageData): array
    {
        $phone = $messageData['from'];
        $time = $this->resolveTime($message);

        if ($time) {
            $date = $flowState['extracted_date'];
            $validation = $this->validateAppointment($date, $time);

            if (!$validation['valid']) {
                $attempts = intval($flowState['attempts']) + 1;
                $this->db->query(
                    'UPDATE calendar_flow_state SET attempts = :attempts, expires_at = :expires WHERE user_phone = :phone',
                    [':attempts' => $attempts, ':expires' => $this->newExpiry(), ':phone' => $phone]
                );

                if ($attempts >= self::MAX_ATTEMPTS) {
                    $this->clearFlowState($phone);
                    return [
                        'handled' => true,
                        'response' => "No pude entender tu respuesta. El proceso fue cancelado. Escríbeme cuando quieras intentarlo de nuevo.",
                        'status' => 'flow_max_attempts'
                    ];
                }

                return [
                    'handled' => true,
                    'response' => $validation['message'],
                    'status' => 'schedule_time_validation_failed'
                ];
            }

            $this->db->query(
                'UPDATE calendar_flow_state SET current_step = :step, extracted_time = :time, attempts = 0, expires_at = :expires WHERE user_phone = :phone',
                [
                    ':step' => self::STEP_EXPECTING_SERVICE,
                    ':time' => $time,
                    ':expires' => $this->newExpiry(),
                    ':phone' => $phone
                ]
            );

            $service = $flowState['extracted_service'];
            if ($service) {
                return $this->buildConfirmationStep($phone, $flowState['conversation_id'], $date, $time, $service, $flowState['event_title']);
            }

            return [
                'handled' => true,
                'response' => "¿Cuál es el motivo de la cita? (Ej: consulta, revisión, reunión...)",
                'status' => 'expecting_service'
            ];
        }

        // If no time found, check if message contains a new date+time to restart from
        $newDate = $this->resolveDate($message);
        if ($newDate && !empty($time = $this->resolveTime($message) ?? '')) {
            $validation = $this->validateAppointment($newDate, $time);
            if ($validation['valid']) {
                $service = $flowState['extracted_service'] ?? null;
                $this->db->query(
                    'UPDATE calendar_flow_state SET current_step = :step, extracted_date = :date, extracted_time = :time, attempts = 0, expires_at = :expires WHERE user_phone = :phone',
                    [
                        ':step'    => self::STEP_EXPECTING_SERVICE,
                        ':date'    => $newDate,
                        ':time'    => $time,
                        ':expires' => $this->newExpiry(),
                        ':phone'   => $phone
                    ]
                );
                if ($service) {
                    return $this->buildConfirmationStep($phone, $flowState['conversation_id'], $newDate, $time, $service, $flowState['event_title'] ?? null);
                }
                $dateFormatted = $this->formatDateForUser($newDate);
                return [
                    'handled'  => true,
                    'response' => "Perfecto, {$dateFormatted} a las {$time}.\n\n¿Cuál es el motivo de la cita? (Ej: consulta, revisión, reunión...)",
                    'status'   => 'expecting_service'
                ];
            }
        }

        $attempts = intval($flowState['attempts']) + 1;
        $this->db->query(
            'UPDATE calendar_flow_state SET attempts = :attempts, expires_at = :expires WHERE user_phone = :phone',
            [':attempts' => $attempts, ':expires' => $this->newExpiry(), ':phone' => $phone]
        );

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->clearFlowState($phone);
            return [
                'handled' => true,
                'response' => "No pude entender tu respuesta. El proceso fue cancelado. Escríbeme cuando quieras intentarlo de nuevo.",
                'status' => 'flow_max_attempts'
            ];
        }

        return [
            'handled' => true,
            'response' => "No entendí esa hora. Intenta con: 14:00 o 3pm",
            'status' => 'event_flow_invalid_time'
        ];
    }

    private function handleExpectingService(string $message, array $flowState, array $messageData): array
    {
        $phone = $messageData['from'];
        $service = trim($message);

        if (mb_strlen($service) < 2) {
            return [
                'handled' => true,
                'response' => "Por favor indica el motivo de la cita (Ej: consulta médica, revisión, reunión...).",
                'status' => 'expecting_service'
            ];
        }

        $this->db->query(
            'UPDATE calendar_flow_state SET extracted_service = :service, attempts = 0, expires_at = :expires WHERE user_phone = :phone',
            [':service' => $service, ':expires' => $this->newExpiry(), ':phone' => $phone]
        );

        return $this->buildConfirmationStep(
            $phone,
            $flowState['conversation_id'],
            $flowState['extracted_date'],
            $flowState['extracted_time'],
            $service,
            $flowState['event_title']
        );
    }

    private function buildConfirmationStep(string $phone, $conversationId, ?string $date, ?string $time, string $service, ?string $eventTitle): array
    {
        $this->db->query(
            'UPDATE calendar_flow_state SET current_step = :step, extracted_service = :service, attempts = 0, expires_at = :expires WHERE user_phone = :phone',
            [
                ':step'    => self::STEP_EXPECTING_CONFIRMATION,
                ':service' => $service,
                ':expires' => $this->newExpiry(),
                ':phone'   => $phone
            ]
        );

        $dateFormatted = $this->formatDateForUser($date);

        $currentState   = $this->getFlowState($phone);
        $rescheduleJson = $currentState['cancel_events_json'] ?? null;
        $rescheduleData = $rescheduleJson ? json_decode($rescheduleJson, true) : null;
        $isReschedule   = !empty($rescheduleData['is_reschedule']);

        if ($isReschedule) {
            $oldSummary = $rescheduleData['event_summary'] ?? 'Cita anterior';
            $response  = "Resumen del reagendamiento:\n\n";
            $response .= "❌ *Cita actual:* {$oldSummary}\n\n";
            $response .= "✅ *Nueva cita:*\n";
            $response .= "📅 Fecha: {$dateFormatted}\n";
            $response .= "🕐 Hora: {$time}\n";
            $response .= "📋 Motivo: {$service}\n";
            $response .= "\n¿Confirmas el cambio? (sí/no)";
        } else {
            $response  = "Resumen de tu cita:\n";
            $response .= "📅 Fecha: {$dateFormatted}\n";
            $response .= "🕐 Hora: {$time}\n";
            $response .= "📋 Motivo: {$service}\n";
            $response .= "\n¿Confirmas la cita? (sí/no)";
        }

        return ['handled' => true, 'response' => $response, 'status' => 'expecting_confirmation'];
    }

    private function handleExpectingConfirmation(string $message, array $flowState, array $messageData): array
    {
        $phone = $messageData['from'];
        $classification = $this->classifyConfirmation($message);

        if ($classification === 'yes') {
            return $this->createAppointment($flowState, $messageData);
        }

        if ($classification === 'no') {
            $this->clearFlowState($phone);
            return [
                'handled' => true,
                'response' => 'Entendido, cancelé el proceso. ¿En qué más puedo ayudarte?',
                'status' => 'flow_cancelled'
            ];
        }

        // unclear
        $attempts = intval($flowState['attempts']) + 1;
        $this->db->query(
            'UPDATE calendar_flow_state SET attempts = :attempts, expires_at = :expires WHERE user_phone = :phone',
            [':attempts' => $attempts, ':expires' => $this->newExpiry(), ':phone' => $phone]
        );

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->clearFlowState($phone);
            return [
                'handled' => true,
                'response' => "No pude entender tu respuesta. El proceso fue cancelado. Escríbeme cuando quieras intentarlo de nuevo.",
                'status' => 'flow_max_attempts'
            ];
        }

        return [
            'handled' => true,
            'response' => 'Por favor confirma: ¿agendo la cita? (sí/no)',
            'status' => 'expecting_confirmation'
        ];
    }

    private function handleCheckAvailability(array $extractedData, array $conversation, array $messageData): array
    {
        try {
            $dateRange = $extractedData['date_range'] ?? '';
            $resolved = $this->resolveDateRange($dateRange);
            $startDate = $resolved['start'];
            $endDate = $resolved['end'];

            $events = $this->calendar->getEventsByDateRange($startDate, $endDate);
            $existingEvents = $events['items'] ?? [];

            $businessHours = $this->calendarConfig['business_hours'];
            $duration = $this->calendarConfig['default_duration_minutes'] ?? 60;
            $timezone = new \DateTimeZone($this->calendarConfig['timezone']);

            $response = $this->buildAvailabilityResponse($startDate, $endDate, $existingEvents, $businessHours, $duration, $timezone);

            return ['handled' => true, 'response' => $response, 'status' => 'availability_checked'];
        } catch (\Exception $e) {
            $this->logger->error('Error checking availability', ['error' => $e->getMessage()]);
            return [
                'handled' => true,
                'response' => 'No pude consultar la disponibilidad en este momento. Por favor intenta de nuevo.',
                'status' => 'availability_error'
            ];
        }
    }

    private function buildAvailabilityResponse(string $startDate, string $endDate, array $existingEvents, array $businessHours, int $duration, \DateTimeZone $timezone): string
    {
        $current = new \DateTime($startDate, $timezone);
        $end = new \DateTime($endDate, $timezone);
        $daySlots = [];

        while ($current <= $end) {
            $dayOfWeek = strtolower($current->format('l'));
            $dateStr = $current->format('Y-m-d');

            if (isset($businessHours[$dayOfWeek]) && $businessHours[$dayOfWeek] !== null) {
                $hours = $businessHours[$dayOfWeek];
                $slots = $this->findFreeSlots($dateStr, $hours['start'], $hours['end'], $existingEvents, $duration, $timezone);
                if (!empty($slots)) {
                    $daySlots[$dateStr] = $slots;
                }
            }

            $current->modify('+1 day');
        }

        if (empty($daySlots)) {
            return "No encontré horarios disponibles en ese rango. ¿Te gustaría consultar otras fechas?";
        }

        $response = "*Horarios disponibles:*\n\n";
        foreach ($daySlots as $date => $slots) {
            $dateObj = new \DateTime($date);
            $response .= "*" . $dateObj->format('d/m/Y') . "* (" . $this->dayNameSpanish($dateObj) . ")\n";
            foreach ($slots as $slot) {
                $response .= "  • {$slot['start']} - {$slot['end']}\n";
            }
            $response .= "\n";
        }

        $response .= "¿Te gustaría agendar en alguno de estos horarios?";
        return $response;
    }

    private function findFreeSlots(string $date, string $openTime, string $closeTime, array $events, int $slotDuration, \DateTimeZone $timezone): array
    {
        $dayEvents = [];
        foreach ($events as $event) {
            $eventStart = new \DateTime($event['start']['dateTime'] ?? $event['start']['date'], $timezone);
            if ($eventStart->format('Y-m-d') === $date) {
                $eventEnd = new \DateTime($event['end']['dateTime'] ?? $event['end']['date'], $timezone);
                $dayEvents[] = ['start' => $eventStart, 'end' => $eventEnd];
            }
        }

        usort($dayEvents, function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });

        $slots = [];
        $cursor = new \DateTime("{$date} {$openTime}", $timezone);
        $dayEnd = new \DateTime("{$date} {$closeTime}", $timezone);

        $now = new \DateTime('now', $timezone);
        if ($cursor < $now && $date === $now->format('Y-m-d')) {
            $minutes = intval($now->format('i'));
            $roundedMinutes = ceil($minutes / 30) * 30;
            $cursor = clone $now;
            $cursor->setTime((int)$now->format('H'), (int)$roundedMinutes, 0);
            if ($cursor < $now) {
                $cursor->modify('+30 minutes');
            }
        }

        foreach ($dayEvents as $event) {
            $slotEnd = clone $cursor;
            $slotEnd->modify("+{$slotDuration} minutes");

            if ($slotEnd <= $event['start'] && $slotEnd <= $dayEnd) {
                $slots[] = [
                    'start' => $cursor->format('H:i'),
                    'end' => $event['start']->format('H:i')
                ];
            }

            if ($event['end'] > $cursor) {
                $cursor = clone $event['end'];
            }
        }

        $slotEnd = clone $cursor;
        $slotEnd->modify("+{$slotDuration} minutes");
        if ($slotEnd <= $dayEnd) {
            $slots[] = [
                'start' => $cursor->format('H:i'),
                'end' => $dayEnd->format('H:i')
            ];
        }

        return $slots;
    }

    private function startListFlow(array $messageData): array
    {
        $phone = $messageData['from'];
        $contactName = $messageData['contact_name'] ?? 'Cliente';

        try {
            $timezone = new \DateTimeZone($this->calendarConfig['timezone']);
            $now = new \DateTime('now', $timezone);
            $until = (clone $now)->modify('+30 days');

            $allEvents = $this->calendar->getUpcomingEvents(
                $now->format(\DateTime::RFC3339),
                $until->format(\DateTime::RFC3339),
                50
            );

            // Filtrar solo los eventos que pertenecen a este contacto
            $nameLower = mb_strtolower($contactName);
            $phoneLast = substr(preg_replace('/\D/', '', $phone), -7);
            $events = array_filter($allEvents, function($event) use ($nameLower, $phoneLast) {
                $summary = mb_strtolower($event['summary'] ?? '');
                $description = mb_strtolower($event['description'] ?? '');
                return strpos($summary, $nameLower) !== false
                    || strpos($description, $nameLower) !== false
                    || (!empty($phoneLast) && (strpos($summary, $phoneLast) !== false || strpos($description, $phoneLast) !== false));
            });

            if (empty($events)) {
                return [
                    'handled'  => true,
                    'response' => "No encontré citas próximas agendadas para ti. ¿Te gustaría agendar una?",
                    'status'   => 'list_empty'
                ];
            }

            $response = "📅 *Tus próximas citas:*\n\n";
            foreach ($events as $event) {
                $startRaw = $event['start']['dateTime'] ?? $event['start']['date'] ?? null;
                if (!$startRaw) continue;
                $start = new \DateTime($startRaw, $timezone);
                $title = $event['summary'] ?? 'Cita';
                $dateStr = $start->format('d/m/Y');
                $timeStr = isset($event['start']['dateTime']) ? $start->format('H:i') : '';
                $dayName = $this->dayNameSpanish($start);
                $response .= "• *{$title}*\n";
                $response .= "  📅 {$dateStr} ({$dayName})" . ($timeStr ? " 🕐 {$timeStr}" : '') . "\n\n";
            }

            $response .= "¿Necesitas cancelar o reagendar alguna?";

            return [
                'handled'  => true,
                'response' => $response,
                'status'   => 'list_appointments'
            ];
        } catch (\Exception $e) {
            $this->logger->error('startListFlow error: ' . $e->getMessage());
            return [
                'handled'  => true,
                'response' => "No pude consultar tus citas en este momento. Por favor intenta más tarde.",
                'status'   => 'list_error'
            ];
        }
    }

    private function startRescheduleFlow(array $conversation, array $messageData): array
    {
        $phone = $messageData['from'];
        $contactName = $messageData['contact_name'] ?? 'Cliente';

        try {
            $timezone  = new \DateTimeZone($this->calendarConfig['timezone']);
            $startDate = (new \DateTime('now', $timezone))->format('Y-m-d');
            $endDate   = (new \DateTime('+30 days', $timezone))->format('Y-m-d');

            $allEvents  = $this->calendar->getEventsByDateRange($startDate, $endDate);
            $userEvents = [];

            if (!empty($allEvents['items'])) {
                foreach ($allEvents['items'] as $event) {
                    $summary = $event['summary'] ?? '';
                    if (stripos($summary, $contactName) !== false) {
                        $userEvents[] = $event;
                    }
                }
            }

            if (empty($userEvents)) {
                return [
                    'handled'  => true,
                    'response' => 'No encontré citas tuyas en los próximos 30 días para reagendar.',
                    'status'   => 'reschedule_no_events'
                ];
            }

            $this->saveFlowState($phone, self::STEP_RESCHEDULE_SELECT, $conversation['id'], [
                'cancel_events_json' => json_encode($userEvents)
            ]);

            $response = "Estas son tus próximas citas:\n\n";
            foreach ($userEvents as $index => $event) {
                $start = new \DateTime($event['start']['dateTime'] ?? $event['start']['date']);
                $response .= ($index + 1) . ". " . $start->format('d/m/Y H:i');
                if (isset($event['summary'])) {
                    $response .= " - " . $event['summary'];
                }
                $response .= "\n";
            }
            $response .= "\n¿Cuál deseas reagendar? (escribe el número)";

            return ['handled' => true, 'response' => $response, 'status' => 'reschedule_select'];
        } catch (\Exception $e) {
            $this->logger->error('Error starting reschedule flow', ['error' => $e->getMessage()]);
            return [
                'handled'  => true,
                'response' => 'No pude consultar tus citas en este momento. Por favor intenta de nuevo.',
                'status'   => 'reschedule_error'
            ];
        }
    }

    private function handleRescheduleSelect(string $message, array $flowState, array $messageData): array
    {
        $phone     = $messageData['from'];
        $events    = json_decode($flowState['cancel_events_json'], true) ?: [];
        $selection = intval(trim($message));

        if ($selection < 1 || $selection > count($events)) {
            $attempts = intval($flowState['attempts']) + 1;
            $this->db->query(
                'UPDATE calendar_flow_state SET attempts = :attempts, expires_at = :expires WHERE user_phone = :phone',
                [':attempts' => $attempts, ':expires' => $this->newExpiry(), ':phone' => $phone]
            );

            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->clearFlowState($phone);
                return [
                    'handled'  => true,
                    'response' => 'No pude entender tu respuesta. El proceso fue cancelado. Escríbeme cuando quieras intentarlo de nuevo.',
                    'status'   => 'flow_max_attempts'
                ];
            }

            return [
                'handled'  => true,
                'response' => 'Por favor escribe un número del 1 al ' . count($events) . '.',
                'status'   => 'reschedule_invalid_selection'
            ];
        }

        $selectedEvent  = $events[$selection - 1];
        $start          = new \DateTime($selectedEvent['start']['dateTime'] ?? $selectedEvent['start']['date']);
        $summary        = $selectedEvent['summary'] ?? 'Cita';
        $rescheduleData = json_encode([
            'event_id'      => $selectedEvent['id'],
            'event_summary' => $summary . ' (' . $start->format('d/m/Y H:i') . ')',
            'is_reschedule' => true
        ]);

        $this->db->query(
            'UPDATE calendar_flow_state SET current_step = :step, cancel_events_json = :data, attempts = 0, expires_at = :expires WHERE user_phone = :phone',
            [
                ':step'    => self::STEP_RESCHEDULE_REASON,
                ':data'    => $rescheduleData,
                ':expires' => $this->newExpiry(),
                ':phone'   => $phone
            ]
        );

        return [
            'handled'  => true,
            'response' => "¿Cuál es el motivo del reagendamiento?\n(Ej: surgió un imprevisto, cambio de agenda, prefiero otro horario...)",
            'status'   => 'reschedule_reason'
        ];
    }

    private function handleRescheduleReason(string $message, array $flowState, array $messageData): array
    {
        $phone  = $messageData['from'];
        $reason = trim($message);

        if (mb_strlen($reason) < 2) {
            return [
                'handled'  => true,
                'response' => 'Por favor indica el motivo del reagendamiento (Ej: surgió un imprevisto, cambio de agenda...).',
                'status'   => 'reschedule_reason'
            ];
        }

        $this->db->query(
            'UPDATE calendar_flow_state SET current_step = :step, extracted_service = :service, attempts = 0, expires_at = :expires WHERE user_phone = :phone',
            [
                ':step'    => self::STEP_EXPECTING_DATE,
                ':service' => $reason,
                ':expires' => $this->newExpiry(),
                ':phone'   => $phone
            ]
        );

        return [
            'handled'  => true,
            'response' => "Entendido. ¿Qué nueva fecha prefieres?\n\nEjemplos: \"el viernes\", \"25/03/2026\", \"la próxima semana\"",
            'status'   => 'reschedule_expecting_date'
        ];
    }

    private function startCancelFlow(array $conversation, array $messageData): array
    {
        $phone = $messageData['from'];
        $contactName = $messageData['contact_name'] ?? 'Cliente';

        try {
            $timezone = new \DateTimeZone($this->calendarConfig['timezone']);
            $startDate = (new \DateTime('now', $timezone))->format('Y-m-d');
            $endDate = (new \DateTime('+30 days', $timezone))->format('Y-m-d');

            $allEvents = $this->calendar->getEventsByDateRange($startDate, $endDate);
            $userEvents = [];

            if (!empty($allEvents['items'])) {
                foreach ($allEvents['items'] as $event) {
                    $summary = $event['summary'] ?? '';
                    if (stripos($summary, $contactName) !== false) {
                        $userEvents[] = $event;
                    }
                }
            }

            if (empty($userEvents)) {
                return [
                    'handled' => true,
                    'response' => 'No encontré citas tuyas en los próximos 30 días.',
                    'status' => 'cancel_no_events'
                ];
            }

            $this->saveFlowState($phone, self::STEP_CANCEL_SELECT, $conversation['id'], [
                'cancel_events_json' => json_encode($userEvents)
            ]);

            $response = "Estas son tus próximas citas:\n\n";
            foreach ($userEvents as $index => $event) {
                $start = new \DateTime($event['start']['dateTime'] ?? $event['start']['date']);
                $response .= ($index + 1) . ". " . $start->format('d/m/Y H:i');
                if (isset($event['summary'])) {
                    $response .= " - " . $event['summary'];
                }
                $response .= "\n";
            }
            $response .= "\n¿Cuál deseas cancelar? (escribe el número)";

            return ['handled' => true, 'response' => $response, 'status' => 'cancel_select'];
        } catch (\Exception $e) {
            $this->logger->error('Error starting cancel flow', ['error' => $e->getMessage()]);
            return [
                'handled' => true,
                'response' => 'No pude consultar tus citas en este momento. Por favor intenta de nuevo.',
                'status' => 'cancel_error'
            ];
        }
    }

    private function handleCancelSelect(string $message, array $flowState, array $messageData): array
    {
        $phone = $messageData['from'];
        $events = json_decode($flowState['cancel_events_json'], true) ?: [];
        $selection = intval(trim($message));

        if ($selection < 1 || $selection > count($events)) {
            $attempts = intval($flowState['attempts']) + 1;
            $this->db->query(
                'UPDATE calendar_flow_state SET attempts = :attempts, expires_at = :expires WHERE user_phone = :phone',
                [':attempts' => $attempts, ':expires' => $this->newExpiry(), ':phone' => $phone]
            );

            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->clearFlowState($phone);
                return [
                    'handled' => true,
                    'response' => "No pude entender tu respuesta. El proceso fue cancelado. Escríbeme cuando quieras intentarlo de nuevo.",
                    'status' => 'flow_max_attempts'
                ];
            }

            return [
                'handled' => true,
                'response' => "Por favor escribe un número del 1 al " . count($events) . ".",
                'status' => 'cancel_invalid_selection'
            ];
        }

        $selectedEvent = $events[$selection - 1];
        $start = new \DateTime($selectedEvent['start']['dateTime'] ?? $selectedEvent['start']['date']);
        $summary = $selectedEvent['summary'] ?? 'Cita';

        $cancelData = json_encode(['event_id' => $selectedEvent['id'], 'event_summary' => $summary]);
        $this->db->query(
            'UPDATE calendar_flow_state SET current_step = :step, cancel_events_json = :data, attempts = 0, expires_at = :expires WHERE user_phone = :phone',
            [
                ':step' => self::STEP_CANCEL_CONFIRM,
                ':data' => $cancelData,
                ':expires' => $this->newExpiry(),
                ':phone' => $phone
            ]
        );

        return [
            'handled' => true,
            'response' => "¿Confirmas que deseas cancelar esta cita?\n\n" . $start->format('d/m/Y H:i') . " - {$summary}\n\n(sí/no)",
            'status' => 'cancel_confirm'
        ];
    }

    private function handleCancelConfirm(string $message, array $flowState, array $messageData): array
    {
        $phone = $messageData['from'];
        $classification = $this->classifyConfirmation($message);

        if ($classification === 'yes') {
            $cancelData = json_decode($flowState['cancel_events_json'], true);
            $eventId = $cancelData['event_id'] ?? null;
            $eventSummary = $cancelData['event_summary'] ?? 'Cita';

            if (!$eventId) {
                $this->clearFlowState($phone);
                return [
                    'handled' => true,
                    'response' => 'Ocurrió un error al identificar la cita. Por favor intenta de nuevo.',
                    'status' => 'cancel_error'
                ];
            }

            try {
                $this->calendar->deleteEvent($eventId);
                $this->clearFlowState($phone);
                return [
                    'handled' => true,
                    'response' => "La cita \"{$eventSummary}\" ha sido cancelada exitosamente.",
                    'status' => 'cancel_success'
                ];
            } catch (\Exception $e) {
                $this->logger->error('Error deleting event', [
                    'event_id' => $eventId,
                    'error' => $e->getMessage()
                ]);
                $this->clearFlowState($phone);
                return [
                    'handled' => true,
                    'response' => 'Ocurrió un problema al cancelar tu cita. Por favor intenta de nuevo en unos minutos.',
                    'status' => 'cancel_api_error'
                ];
            }
        }

        if ($classification === 'no') {
            $this->clearFlowState($phone);
            return [
                'handled' => true,
                'response' => 'Entendido, no se canceló ninguna cita. ¿En qué más puedo ayudarte?',
                'status' => 'cancel_aborted'
            ];
        }

        // unclear
        $attempts = intval($flowState['attempts']) + 1;
        $this->db->query(
            'UPDATE calendar_flow_state SET attempts = :attempts, expires_at = :expires WHERE user_phone = :phone',
            [':attempts' => $attempts, ':expires' => $this->newExpiry(), ':phone' => $phone]
        );

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->clearFlowState($phone);
            return [
                'handled' => true,
                'response' => "No pude entender tu respuesta. El proceso fue cancelado. Escríbeme cuando quieras intentarlo de nuevo.",
                'status' => 'flow_max_attempts'
            ];
        }

        return [
            'handled' => true,
            'response' => 'Por favor responde *sí* para confirmar la cancelación o *no* para mantener la cita.',
            'status' => 'cancel_confirm'
        ];
    }

    private function createAppointment(array $flowState, array $messageData): array
    {
        $phone = $messageData['from'];
        $contactName = $messageData['contact_name'] ?? 'Cliente';

        $rescheduleJson = $flowState['cancel_events_json'] ?? null;
        $rescheduleData = $rescheduleJson ? json_decode($rescheduleJson, true) : null;
        $isReschedule   = !empty($rescheduleData['is_reschedule']);

        try {
            $date    = $flowState['extracted_date'];
            $time    = $flowState['extracted_time'];
            $service = $flowState['extracted_service'] ?? null;

            if ($isReschedule && !empty($rescheduleData['event_id'])) {
                try {
                    $this->calendar->deleteEvent($rescheduleData['event_id']);
                    $this->logger->info('Old event deleted for reschedule', ['event_id' => $rescheduleData['event_id']]);
                } catch (\Exception $e) {
                    $this->logger->warning('Could not delete old event during reschedule', [
                        'event_id' => $rescheduleData['event_id'],
                        'error'    => $e->getMessage()
                    ]);
                }
            }

            $eventTitle = $service
                ? $contactName . ' - ' . $service
                : ($flowState['event_title'] ?: 'Cita - ' . $contactName);

            $description = 'Creado desde WhatsApp por ' . $contactName;
            if ($service) {
                $description .= "\nMotivo: " . $service;
            }

            $timezone = new \DateTimeZone($this->calendarConfig['timezone']);
            $startDateTime = new \DateTime("{$date} {$time}", $timezone);
            $endDateTime = clone $startDateTime;
            $durationMinutes = $this->calendarConfig['default_duration_minutes'] ?? 60;
            $endDateTime->modify("+{$durationMinutes} minutes");

            $event = $this->calendar->createEvent(
                $eventTitle,
                $description,
                $startDateTime->format(\DateTime::RFC3339),
                $endDateTime->format(\DateTime::RFC3339),
                null,
                $this->calendarConfig
            );

            $this->clearFlowState($phone);

            $confirmationDate = $startDateTime->format('d/m/Y');
            $confirmationTime = $startDateTime->format('H:i');

            $this->logger->info('Event created successfully', [
                'event_id' => $event['id'] ?? 'unknown',
                'date'     => $confirmationDate,
                'time'     => $confirmationTime,
                'contact'  => $contactName,
                'service'  => $service
            ]);

            if ($isReschedule) {
                $response  = "✅ ¡Cita reagendada exitosamente!\n";
            } else {
                $response  = "✅ ¡Cita confirmada!\n";
            }
            $response .= "📅 {$confirmationDate} a las {$confirmationTime}";
            if ($service) {
                $response .= "\n📋 Motivo: {$service}";
            }
            $response .= "\n\n¡Nos vemos!";

            return [
                'handled' => true,
                'response' => $response,
                'status'   => 'event_created'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error creating calendar event', [
                'phone' => $phone,
                'date' => $flowState['extracted_date'] ?? 'unknown',
                'time' => $flowState['extracted_time'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->clearFlowState($phone);

            return [
                'handled' => true,
                'response' => 'Ocurrió un problema al crear tu cita. Por favor intenta de nuevo en unos minutos.',
                'status' => 'event_creation_error'
            ];
        }
    }

    private function classifyConfirmation(string $message): string
    {
        try {
            $response = $this->openai->generateResponse(
                "El usuario respondió: '{$message}'. ¿Confirma la cita? Responde solo: yes, no, o unclear.",
                '',
                'Eres un clasificador. Responde SOLO con: yes, no, o unclear. Nada más.',
                0.0,
                5,
                [],
                'gpt-4o-mini'
            );

            $result = strtolower(trim($response));

            if (strpos($result, 'yes') !== false) return 'yes';
            if (strpos($result, 'no') !== false) return 'no';
            return 'unclear';
        } catch (\Exception $e) {
            $this->logger->error('Error classifying confirmation', ['error' => $e->getMessage()]);
            return 'unclear';
        }
    }

    private function saveFlowState(string $phone, string $step, int $conversationId, array $data = []): void
    {
        $expires = $this->newExpiry();

        $existing = $this->getFlowState($phone);

        if ($existing) {
            $updateFields = [
                'current_step' => $step,
                'expires_at' => $expires,
                'attempts' => 0
            ];

            foreach (['extracted_date', 'extracted_time', 'extracted_service', 'event_title', 'cancel_events_json'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateFields[$field] = $data[$field];
                }
            }

            $setParts = [];
            $params = [':phone' => $phone];
            foreach ($updateFields as $field => $value) {
                $setParts[] = "{$field} = :{$field}";
                $params[":{$field}"] = $value;
            }

            $sql = 'UPDATE calendar_flow_state SET ' . implode(', ', $setParts) . ' WHERE user_phone = :phone';
            $this->db->query($sql, $params);
        } else {
            $insertData = [
                'user_phone' => $phone,
                'conversation_id' => $conversationId,
                'current_step' => $step,
                'extracted_date' => $data['extracted_date'] ?? null,
                'extracted_time' => $data['extracted_time'] ?? null,
                'extracted_service' => $data['extracted_service'] ?? null,
                'event_title' => $data['event_title'] ?? null,
                'cancel_events_json' => $data['cancel_events_json'] ?? null,
                'attempts' => 0,
                'expires_at' => $expires
            ];

            $fields = array_keys($insertData);
            $placeholders = array_map(function ($f) { return ':' . $f; }, $fields);

            $sql = 'INSERT INTO calendar_flow_state (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $params = [];
            foreach ($insertData as $field => $value) {
                $params[':' . $field] = $value;
            }

            $this->db->query($sql, $params);
        }
    }

    private function isFlowExpired(array $flowState): bool
    {
        if (empty($flowState['expires_at'])) {
            return true;
        }

        $expiresAt = new \DateTime($flowState['expires_at']);
        $now = new \DateTime('now');

        return $now > $expiresAt;
    }

    private function touchFlowState(string $phone): void
    {
        $this->db->query(
            'UPDATE calendar_flow_state SET expires_at = :expires WHERE user_phone = :phone',
            [':expires' => $this->newExpiry(), ':phone' => $phone]
        );
    }

    private function newExpiry(): string
    {
        return (new \DateTime('+' . self::FLOW_TTL_MINUTES . ' minutes'))->format('Y-m-d H:i:s');
    }

    private function isNewScheduleIntent(string $messageLower): bool
    {
        $scheduleWords = ['agendar', 'agenda', 'agendo', 'reservar', 'reserva', 'programar', 'programa',
                          'quiero una cita', 'necesito una cita', 'sacar una cita', 'cita', 'turno',
                          'reunión', 'reunion', 'consulta'];
        foreach ($scheduleWords as $word) {
            if (strpos($messageLower, $word) !== false) {
                return true;
            }
        }
        return false;
    }

    private function isFlowCancelRequest(string $messageLower, string $currentStep): bool
    {
        if (in_array($currentStep, [
                self::STEP_CANCEL_CONFIRM, self::STEP_CANCEL_SELECT,
                self::STEP_RESCHEDULE_SELECT, self::STEP_RESCHEDULE_REASON
            ])) {
            return false;
        }

        $cancelWords = ['cancelar', 'salir', 'no quiero', 'olvida', 'dejalo', 'déjalo'];
        foreach ($cancelWords as $word) {
            if (strpos($messageLower, $word) !== false) {
                return true;
            }
        }
        return false;
    }

    private function resolveDate(string $input): ?string
    {
        if (empty(trim($input))) {
            return null;
        }

        $validated = $this->calendar->validateDateFormat($input);
        if ($validated) {
            return $validated;
        }

        $textLower = mb_strtolower(trim($input));
        $timezone = new \DateTimeZone($this->calendarConfig['timezone']);

        $relativeDays = [
            'lunes' => 'monday', 'martes' => 'tuesday', 'miércoles' => 'wednesday', 'miercoles' => 'wednesday',
            'jueves' => 'thursday', 'viernes' => 'friday', 'sábado' => 'saturday', 'sabado' => 'saturday',
            'domingo' => 'sunday'
        ];

        if (strpos($textLower, 'próxima semana') !== false || strpos($textLower, 'proxima semana') !== false) {
            return (new \DateTime('next monday', $timezone))->format('Y-m-d');
        }

        foreach ($relativeDays as $spanish => $english) {
            if (strpos($textLower, $spanish) !== false) {
                $date = new \DateTime("next {$english}", $timezone);
                $today = new \DateTime('now', $timezone);
                if ($date->format('Y-m-d') === $today->format('Y-m-d')) {
                    $date->modify('+7 days');
                }
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function resolveTime(string $input): ?string
    {
        if (empty(trim($input))) {
            return null;
        }

        $text = mb_strtolower(trim($input));

        // With am/pm: "4pm", "4:30pm", "4 pm" — colon optional
        if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm|a\.m\.|p\.m\.)/i', $text, $matches)) {
            $hour   = intval($matches[1]);
            $minute = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : 0;
            $ampm   = strtolower(str_replace('.', '', $matches[3]));

            if ($ampm === 'pm' && $hour < 12) {
                $hour += 12;
            } elseif ($ampm === 'am' && $hour === 12) {
                $hour = 0;
            }

            if ($hour >= 0 && $hour < 24 && $minute >= 0 && $minute < 60) {
                return sprintf('%02d:%02d', $hour, $minute);
            }
        }

        // Without am/pm: require explicit HH:MM colon format to avoid matching years
        if (preg_match('/\b(\d{1,2}):(\d{2})\b/', $text, $matches)) {
            $hour   = intval($matches[1]);
            $minute = intval($matches[2]);

            if ($hour >= 0 && $hour < 24 && $minute >= 0 && $minute < 60) {
                return sprintf('%02d:%02d', $hour, $minute);
            }
        }

        $timeWords = [
            'mañana temprano' => '09:00', 'en la mañana' => '10:00', 'por la mañana' => '10:00',
            'al mediodía' => '12:00', 'mediodia' => '12:00',
            'en la tarde' => '15:00', 'por la tarde' => '15:00',
            'en la noche' => '19:00', 'por la noche' => '19:00'
        ];

        foreach ($timeWords as $phrase => $time) {
            if (strpos($text, $phrase) !== false) {
                return $time;
            }
        }

        return null;
    }

    private function resolveDateRange(string $dateRange): array
    {
        $textLower = mb_strtolower(trim($dateRange));
        $timezone = new \DateTimeZone($this->calendarConfig['timezone']);
        $today = new \DateTime('now', $timezone);

        if (strpos($textLower, 'hoy') !== false) {
            $d = $today->format('Y-m-d');
            return ['start' => $d, 'end' => $d];
        }

        if (strpos($textLower, 'mañana') !== false) {
            $d = (clone $today)->modify('+1 day')->format('Y-m-d');
            return ['start' => $d, 'end' => $d];
        }

        if (strpos($textLower, 'esta semana') !== false) {
            $start = $today->format('Y-m-d');
            $end = (clone $today)->modify('next sunday')->format('Y-m-d');
            return ['start' => $start, 'end' => $end];
        }

        if (strpos($textLower, 'próxima semana') !== false || strpos($textLower, 'proxima semana') !== false) {
            $start = (new \DateTime('next monday', $timezone))->format('Y-m-d');
            $end = (new \DateTime('next monday +6 days', $timezone))->format('Y-m-d');
            return ['start' => $start, 'end' => $end];
        }

        $relativeDays = [
            'lunes' => 'monday', 'martes' => 'tuesday', 'miércoles' => 'wednesday', 'miercoles' => 'wednesday',
            'jueves' => 'thursday', 'viernes' => 'friday', 'sábado' => 'saturday', 'sabado' => 'saturday',
            'domingo' => 'sunday'
        ];

        foreach ($relativeDays as $spanish => $english) {
            if (strpos($textLower, $spanish) !== false) {
                $d = (new \DateTime("next {$english}", $timezone))->format('Y-m-d');
                return ['start' => $d, 'end' => $d];
            }
        }

        $start = $today->format('Y-m-d');
        $end = (clone $today)->modify('+7 days')->format('Y-m-d');
        return ['start' => $start, 'end' => $end];
    }

    private function validateAppointment(string $date, string $time): array
    {
        $dateCheck = $this->calendar->validateDateNotPast($date);
        if (!$dateCheck['valid']) {
            return ['valid' => false, 'message' => $dateCheck['message']];
        }

        $minAdvanceHours = $this->calendarConfig['min_advance_hours'] ?? 1;
        if ($minAdvanceHours > 0) {
            $advanceCheck = $this->calendar->validateMinAdvanceHours($date, $time, $minAdvanceHours);
            if (!$advanceCheck['valid']) {
                return ['valid' => false, 'message' => $advanceCheck['message']];
            }
        }

        $businessValidation = $this->calendar->validateBusinessHours(
            $date,
            $time,
            $this->calendarConfig['business_hours']
        );
        if (!$businessValidation['valid']) {
            return ['valid' => false, 'message' => $businessValidation['reason']];
        }

        $timezone = new \DateTimeZone($this->calendarConfig['timezone']);
        $startDateTime = new \DateTime("{$date} {$time}", $timezone);
        $endDateTime = clone $startDateTime;
        $durationMinutes = $this->calendarConfig['default_duration_minutes'] ?? 60;
        $endDateTime->modify("+{$durationMinutes} minutes");

        try {
            $overlapCheck = $this->calendar->checkEventOverlap($date, $time, $endDateTime->format('H:i'));
            if ($overlapCheck['overlap']) {
                return ['valid' => false, 'message' => "Lo siento, ya hay una cita agendada en ese horario. Por favor elige otro horario."];
            }

            $eventsCount = $this->calendar->countEventsForDay($date);
            if ($eventsCount >= ($this->calendarConfig['max_events_per_day'] ?? 10)) {
                return ['valid' => false, 'message' => "Lo siento, ya se alcanzó el máximo de citas para ese día. Por favor elige otro día."];
            }
        } catch (\Exception $e) {
            $this->logger->warning('Calendar API check skipped: ' . $e->getMessage());
            // Si la API falla (ej: token expirado), continuar sin verificar solapamiento
        }

        return ['valid' => true];
    }

    private function formatDateForUser(string $date): string
    {
        $dateObj = new \DateTime($date);
        return $dateObj->format('d/m/Y') . ' (' . $this->dayNameSpanish($dateObj) . ')';
    }

    private function dayNameSpanish(\DateTime $date): string
    {
        $days = [
            'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
            'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo'
        ];
        return $days[$date->format('l')] ?? $date->format('l');
    }
}
