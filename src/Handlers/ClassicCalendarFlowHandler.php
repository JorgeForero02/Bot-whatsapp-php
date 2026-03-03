<?php

namespace App\Handlers;

use App\Core\Database;
use App\Core\Logger;
use App\Services\GoogleCalendarService;

class ClassicCalendarFlowHandler
{
    private const TTL_MINUTES = 15;
    private const MAX_EVENTS  = 5;

    private const TIME_SLOTS = [
        '08:00', '09:00', '10:00', '11:00',
        '14:00', '15:00', '16:00', '17:00',
    ];

    private const STEP_SCHEDULE_DATE      = 'schedule_date';
    private const STEP_SCHEDULE_TIME      = 'schedule_time';
    private const STEP_SCHEDULE_CONFIRM   = 'schedule_confirm';
    private const STEP_LIST_ACTION        = 'list_action';
    private const STEP_CANCEL_PICK        = 'cancel_pick';
    private const STEP_CANCEL_CONFIRM     = 'cancel_confirm';
    private const STEP_RESCHEDULE_PICK    = 'reschedule_pick';
    private const STEP_RESCHEDULE_DATE    = 'reschedule_date';
    private const STEP_RESCHEDULE_TIME    = 'reschedule_time';
    private const STEP_RESCHEDULE_CONFIRM = 'reschedule_confirm';

    private Database $db;
    private Logger $logger;
    private GoogleCalendarService $calendar;
    private string $timezone;

    public function __construct(Database $db, Logger $logger, GoogleCalendarService $calendar, string $timezone)
    {
        $this->db       = $db;
        $this->logger   = $logger;
        $this->calendar = $calendar;
        $this->timezone = $timezone;
    }

    public function hasActiveSession(string $phone): bool
    {
        return $this->loadSession($phone) !== null;
    }

    public function start(string $intent, string $phone, string $contactName): array
    {
        $this->clearSession($phone);

        if ($intent === 'list') {
            return $this->beginList($phone, $contactName);
        }
        if ($intent === 'cancel') {
            return $this->beginCancel($phone, $contactName);
        }
        if ($intent === 'reschedule') {
            return $this->beginReschedule($phone, $contactName);
        }
        return $this->beginSchedule($phone, $contactName);
    }

    public function handleStep(string $message, string $phone, string $contactName): array
    {
        $session = $this->loadSession($phone);

        if (!$session) {
            return ['response' => "Sesión expirada. Escribe *menu* para comenzar de nuevo.", 'handled' => true];
        }

        $input = trim($message);

        if ($input === '0') {
            return $this->aborted($phone);
        }

        $step  = $session['step'];
        $data  = json_decode($session['data'], true) ?? [];

        switch ($step) {
            case self::STEP_SCHEDULE_DATE:      return $this->onScheduleDate($input, $phone, $data);
            case self::STEP_SCHEDULE_TIME:      return $this->onScheduleTime($input, $phone, $data);
            case self::STEP_SCHEDULE_CONFIRM:   return $this->onScheduleConfirm($input, $phone, $data, $contactName);
            case self::STEP_LIST_ACTION:        return $this->onListAction($input, $phone, $data);
            case self::STEP_CANCEL_PICK:        return $this->onCancelPick($input, $phone, $data);
            case self::STEP_CANCEL_CONFIRM:     return $this->onCancelConfirm($input, $phone, $data);
            case self::STEP_RESCHEDULE_PICK:    return $this->onReschedulePick($input, $phone, $data);
            case self::STEP_RESCHEDULE_DATE:    return $this->onRescheduleDate($input, $phone, $data);
            case self::STEP_RESCHEDULE_TIME:    return $this->onRescheduleTime($input, $phone, $data);
            case self::STEP_RESCHEDULE_CONFIRM: return $this->onRescheduleConfirm($input, $phone, $data);
            default:                            return $this->aborted($phone);
        }
    }

    private function beginSchedule(string $phone, string $contactName): array
    {
        $dates = $this->buildDateOptions();
        $this->saveSession($phone, self::STEP_SCHEDULE_DATE, [
            'date_options' => $dates,
            'contact_name' => $contactName,
        ]);
        return ['response' => $this->renderDateMenu($dates), 'handled' => true];
    }

    private function beginList(string $phone, string $contactName): array
    {
        $events = $this->fetchUpcomingEvents($contactName);
        if (empty($events)) {
            $this->clearSession($phone);
            return ['response' => "No tienes citas próximas agendadas. 📭\n\nEscribe *menu* para volver al inicio.", 'handled' => true];
        }
        $this->saveSession($phone, self::STEP_LIST_ACTION, [
            'events'       => $events,
            'contact_name' => $contactName,
        ]);
        return ['response' => $this->renderEventListWithActions($events), 'handled' => true];
    }

    private function beginCancel(string $phone, string $contactName): array
    {
        $events = $this->fetchUpcomingEvents($contactName);
        if (empty($events)) {
            $this->clearSession($phone);
            return ['response' => "No tienes citas próximas para cancelar. 📭\n\nEscribe *menu* para volver al inicio.", 'handled' => true];
        }
        $this->saveSession($phone, self::STEP_CANCEL_PICK, [
            'events'       => $events,
            'contact_name' => $contactName,
        ]);
        return ['response' => $this->renderEventPickMenu($events, "¿Qué cita deseas cancelar?"), 'handled' => true];
    }

    private function beginReschedule(string $phone, string $contactName): array
    {
        $events = $this->fetchUpcomingEvents($contactName);
        if (empty($events)) {
            $this->clearSession($phone);
            return ['response' => "No tienes citas próximas para reagendar. 📭\n\nEscribe *menu* para volver al inicio.", 'handled' => true];
        }
        $this->saveSession($phone, self::STEP_RESCHEDULE_PICK, [
            'events'       => $events,
            'contact_name' => $contactName,
        ]);
        return ['response' => $this->renderEventPickMenu($events, "¿Qué cita deseas reagendar?"), 'handled' => true];
    }

    private function onScheduleDate(string $input, string $phone, array $data): array
    {
        if ($this->isExitInput($input)) {
            return $this->aborted($phone);
        }

        $dates    = $data['date_options'] ?? [];
        $selected = $this->resolveOption($input, $dates);

        if (!$selected) {
            return ['response' => "Opción no válida.\n\n" . $this->renderDateMenu($dates), 'handled' => true];
        }

        $times = $this->buildTimeOptions();
        $data['selected_date'] = $selected;
        $data['time_options']  = $times;
        $this->saveSession($phone, self::STEP_SCHEDULE_TIME, $data);

        return ['response' => $this->renderTimeMenu($times, $selected), 'handled' => true];
    }

    private function onScheduleTime(string $input, string $phone, array $data): array
    {
        if ($this->isExitInput($input)) {
            return $this->aborted($phone);
        }

        $times    = $data['time_options'] ?? [];
        $selected = $this->resolveOption($input, $times);

        if (!$selected) {
            return ['response' => "Opción no válida.\n\n" . $this->renderTimeMenu($times, $data['selected_date'] ?? ''), 'handled' => true];
        }

        $data['selected_time'] = $selected;
        $this->saveSession($phone, self::STEP_SCHEDULE_CONFIRM, $data);

        return ['response' => $this->renderScheduleConfirm($data), 'handled' => true];
    }

    private function onScheduleConfirm(string $input, string $phone, array $data, string $contactName): array
    {
        $n = mb_strtolower(trim($input));

        if ($n === '1' || $n === 'si' || $n === 'sí' || strpos($n, 'confirm') !== false) {
            $response = $this->createCalendarEvent($data, $data['contact_name'] ?? $contactName);
            $this->clearSession($phone);
            return ['response' => $response, 'handled' => true];
        }

        if ($n === '2' || $n === 'no' || strpos($n, 'cancel') !== false) {
            return $this->aborted($phone);
        }

        return ['response' => $this->renderScheduleConfirm($data), 'handled' => true];
    }

    private function onListAction(string $input, string $phone, array $data): array
    {
        $n      = mb_strtolower(trim($input));
        $events = $data['events'] ?? [];

        if ($n === '1' || strpos($n, 'cancel') !== false) {
            $this->saveSession($phone, self::STEP_CANCEL_PICK, $data);
            return ['response' => $this->renderEventPickMenu($events, "¿Qué cita deseas cancelar?"), 'handled' => true];
        }

        if ($n === '2' || strpos($n, 'reagend') !== false) {
            $this->saveSession($phone, self::STEP_RESCHEDULE_PICK, $data);
            return ['response' => $this->renderEventPickMenu($events, "¿Qué cita deseas reagendar?"), 'handled' => true];
        }

        if ($this->isExitInput($input)) {
            $this->clearSession($phone);
            return ['response' => '', 'handled' => true, 'exited' => true];
        }

        return ['response' => $this->renderEventListWithActions($events), 'handled' => true];
    }

    private function onCancelPick(string $input, string $phone, array $data): array
    {
        if ($this->isExitInput($input)) {
            return $this->aborted($phone);
        }

        $events  = $data['events'] ?? [];
        $eventId = $this->resolveEventOption($input, $events);

        if (!$eventId) {
            return ['response' => "Opción no válida.\n\n" . $this->renderEventPickMenu($events, "¿Qué cita deseas cancelar?"), 'handled' => true];
        }

        $event = $this->findEventById($events, $eventId);
        $data['selected_event_id']    = $eventId;
        $data['selected_event_title'] = $event['title'] ?? 'Cita';
        $data['selected_event_start'] = $event['start'] ?? '';
        $this->saveSession($phone, self::STEP_CANCEL_CONFIRM, $data);

        return ['response' => $this->renderCancelConfirm($data), 'handled' => true];
    }

    private function onCancelConfirm(string $input, string $phone, array $data): array
    {
        $n = mb_strtolower(trim($input));

        if ($n === '1' || $n === 'si' || $n === 'sí') {
            $eventId = $data['selected_event_id'] ?? null;
            try {
                if ($eventId) {
                    $this->calendar->deleteEvent($eventId);
                }
                $this->clearSession($phone);
                return ['response' => "✅ Cita cancelada correctamente.\n\nEscribe *menu* para volver al inicio.", 'handled' => true];
            } catch (\Throwable $e) {
                $this->logger->error('ClassicCalendar: deleteEvent failed', ['error' => $e->getMessage()]);
                $this->clearSession($phone);
                return ['response' => "❌ No pude cancelar la cita. Por favor inténtalo más tarde.", 'handled' => true];
            }
        }

        if ($n === '2' || $n === 'no') {
            $this->clearSession($phone);
            return ['response' => '', 'handled' => true, 'exited' => true];
        }

        return ['response' => $this->renderCancelConfirm($data), 'handled' => true];
    }

    private function onReschedulePick(string $input, string $phone, array $data): array
    {
        if ($this->isExitInput($input)) {
            return $this->aborted($phone);
        }

        $events  = $data['events'] ?? [];
        $eventId = $this->resolveEventOption($input, $events);

        if (!$eventId) {
            return ['response' => "Opción no válida.\n\n" . $this->renderEventPickMenu($events, "¿Qué cita deseas reagendar?"), 'handled' => true];
        }

        $event = $this->findEventById($events, $eventId);
        $dates = $this->buildDateOptions();
        $data['selected_event_id']    = $eventId;
        $data['selected_event_title'] = $event['title'] ?? 'Cita';
        $data['date_options']         = $dates;
        $this->saveSession($phone, self::STEP_RESCHEDULE_DATE, $data);

        return ['response' => $this->renderDateMenu($dates), 'handled' => true];
    }

    private function onRescheduleDate(string $input, string $phone, array $data): array
    {
        if ($this->isExitInput($input)) {
            return $this->aborted($phone);
        }

        $dates    = $data['date_options'] ?? [];
        $selected = $this->resolveOption($input, $dates);

        if (!$selected) {
            return ['response' => "Opción no válida.\n\n" . $this->renderDateMenu($dates), 'handled' => true];
        }

        $times = $this->buildTimeOptions();
        $data['selected_date'] = $selected;
        $data['time_options']  = $times;
        $this->saveSession($phone, self::STEP_RESCHEDULE_TIME, $data);

        return ['response' => $this->renderTimeMenu($times, $selected), 'handled' => true];
    }

    private function onRescheduleTime(string $input, string $phone, array $data): array
    {
        if ($this->isExitInput($input)) {
            return $this->aborted($phone);
        }

        $times    = $data['time_options'] ?? [];
        $selected = $this->resolveOption($input, $times);

        if (!$selected) {
            return ['response' => "Opción no válida.\n\n" . $this->renderTimeMenu($times, $data['selected_date'] ?? ''), 'handled' => true];
        }

        $data['selected_time'] = $selected;
        $this->saveSession($phone, self::STEP_RESCHEDULE_CONFIRM, $data);

        return ['response' => $this->renderRescheduleConfirm($data), 'handled' => true];
    }

    private function onRescheduleConfirm(string $input, string $phone, array $data): array
    {
        $n = mb_strtolower(trim($input));

        if ($n === '1' || $n === 'si' || $n === 'sí' || strpos($n, 'confirm') !== false) {
            $response = $this->performReschedule($data);
            $this->clearSession($phone);
            return ['response' => $response, 'handled' => true];
        }

        if ($n === '2' || $n === 'no' || strpos($n, 'cancel') !== false) {
            $this->clearSession($phone);
            return ['response' => '', 'handled' => true, 'exited' => true];
        }

        return ['response' => $this->renderRescheduleConfirm($data), 'handled' => true];
    }

    private function renderDateMenu(array $dates): string
    {
        $lines = ["📅 *¿Qué día prefieres?*\n"];
        foreach ($dates as $num => $date) {
            $lines[] = "{$num}. " . $this->formatDateLabel($date);
        }
        $lines[] = "\nEscribe *menu* para cancelar";
        return implode("\n", $lines);
    }

    private function renderTimeMenu(array $times, string $date): string
    {
        $lines = ["🕐 *¿A qué hora el " . $this->formatDateLabel($date) . "?*\n"];
        foreach ($times as $num => $time) {
            $lines[] = "{$num}. " . $this->formatTimeLabel($time);
        }
        $lines[] = "\nEscribe *menu* para cancelar";
        return implode("\n", $lines);
    }

    private function renderScheduleConfirm(array $data): string
    {
        $date = $this->formatDateLabel($data['selected_date'] ?? '');
        $time = $this->formatTimeLabel($data['selected_time'] ?? '');
        return "📋 *Confirma tu cita:*\n\n📆 {$date}\n🕐 {$time}\n\n1. ✅ Confirmar\n2. ❌ Cancelar";
    }

    private function renderEventListWithActions(array $events): string
    {
        $lines = ["📅 *Tus próximas citas:*\n"];
        foreach ($events as $i => $event) {
            $n = $i + 1;
            $lines[] = "{$n}. *{$event['title']}*";
            $lines[] = "   📆 " . $this->formatEventDate($event['start']) . "  🕐 " . $this->formatEventTime($event['start']);
        }
        $lines[] = "\n¿Qué deseas hacer?\n1. ❌ Cancelar una cita\n2. 🔄 Reagendar una cita\n\nEscribe *menu* para volver al inicio";
        return implode("\n", $lines);
    }

    private function renderEventPickMenu(array $events, string $heading): string
    {
        $lines = ["*{$heading}*\n"];
        foreach ($events as $i => $event) {
            $n = $i + 1;
            $lines[] = "{$n}. *{$event['title']}*";
            $lines[] = "   📆 " . $this->formatEventDate($event['start']) . "  🕐 " . $this->formatEventTime($event['start']);
        }
        $lines[] = "\nEscribe *menu* para cancelar";
        return implode("\n", $lines);
    }

    private function renderCancelConfirm(array $data): string
    {
        $title = $data['selected_event_title'] ?? 'Cita';
        $start = $data['selected_event_start'] ?? '';
        $when  = $start ? $this->formatEventDate($start) . ' ' . $this->formatEventTime($start) : '';
        return "⚠️ *¿Confirmas cancelar esta cita?*\n\n*{$title}*\n📆 {$when}\n\n1. ✅ Sí, cancelar\n2. ❌ No, mantener";
    }

    private function renderRescheduleConfirm(array $data): string
    {
        $title = $data['selected_event_title'] ?? 'Cita';
        $date  = $this->formatDateLabel($data['selected_date'] ?? '');
        $time  = $this->formatTimeLabel($data['selected_time'] ?? '');
        return "📋 *Confirma el reagendamiento:*\n\n*{$title}*\n📆 {$date}\n🕐 {$time}\n\n1. ✅ Confirmar\n2. ❌ Cancelar";
    }

    private function aborted(string $phone): array
    {
        $this->clearSession($phone);
        return ['response' => '', 'handled' => true, 'exited' => true];
    }

    private function createCalendarEvent(array $data, string $contactName): string
    {
        $date    = $data['selected_date'] ?? '';
        $time    = $data['selected_time'] ?? '';
        $startDt = $date . 'T' . $time . ':00';
        $endDt   = $date . 'T' . date('H:i', strtotime($time) + 3600) . ':00';

        try {
            $this->calendar->createEvent(
                "Cita - {$contactName}",
                "Agendada desde WhatsApp por {$contactName}",
                $startDt,
                $endDt
            );
            return "✅ *¡Cita agendada!*\n\n📆 " . $this->formatDateLabel($date) . "\n🕐 " . $this->formatTimeLabel($time) . "\n\nTe esperamos. Escribe *menu* para volver al inicio.";
        } catch (\Throwable $e) {
            $this->logger->error('ClassicCalendar: createEvent failed', ['error' => $e->getMessage()]);
            return "❌ No pude agendar la cita. Por favor inténtalo más tarde o contáctanos directamente.";
        }
    }

    private function performReschedule(array $data): string
    {
        $eventId = $data['selected_event_id'] ?? null;
        $date    = $data['selected_date'] ?? '';
        $time    = $data['selected_time'] ?? '';
        $startDt = $date . 'T' . $time . ':00';
        $endDt   = $date . 'T' . date('H:i', strtotime($time) + 3600) . ':00';

        try {
            $this->calendar->rescheduleEvent($eventId, $startDt, $endDt);
            return "✅ *¡Cita reagendada!*\n\n📆 " . $this->formatDateLabel($date) . "\n🕐 " . $this->formatTimeLabel($time) . "\n\nEscribe *menu* para volver al inicio.";
        } catch (\Throwable $e) {
            $this->logger->error('ClassicCalendar: rescheduleEvent failed', ['error' => $e->getMessage()]);
            return "❌ No pude reagendar la cita. Por favor inténtalo más tarde o contáctanos directamente.";
        }
    }

    private function fetchUpcomingEvents(string $contactName = ''): array
    {
        try {
            $timeMin = (new \DateTime('now', new \DateTimeZone($this->timezone)))->format(\DateTime::RFC3339);
            $timeMax = (new \DateTime('+30 days', new \DateTimeZone($this->timezone)))->format(\DateTime::RFC3339);
            $limit   = $contactName !== '' ? self::MAX_EVENTS * 4 : self::MAX_EVENTS;
            $raw     = $this->calendar->getUpcomingEvents($timeMin, $timeMax, $limit);
            $events  = [];
            $nameLow = $contactName !== '' ? mb_strtolower(trim($contactName)) : '';
            foreach ($raw as $item) {
                $title = $item['summary'] ?? 'Cita';
                if ($nameLow !== '' && mb_stripos($title, $nameLow) === false) {
                    continue;
                }
                $events[] = [
                    'id'    => $item['id'],
                    'title' => $title,
                    'start' => $item['start']['dateTime'] ?? $item['start']['date'] ?? '',
                ];
                if (count($events) >= self::MAX_EVENTS) {
                    break;
                }
            }
            return $events;
        } catch (\Throwable $e) {
            $this->logger->error('ClassicCalendar: fetchUpcomingEvents failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function buildDateOptions(): array
    {
        $tz      = new \DateTimeZone($this->timezone);
        $today   = new \DateTime('now', $tz);
        $options = [];
        $count   = 0;
        $offset  = 0;

        while ($count < 7) {
            $day = (clone $today)->modify("+{$offset} days");
            $offset++;
            if ((int) $day->format('N') <= 5) {
                $count++;
                $options[(string) $count] = $day->format('Y-m-d');
            }
        }

        return $options;
    }

    private function buildTimeOptions(): array
    {
        $options = [];
        foreach (self::TIME_SLOTS as $i => $slot) {
            $options[(string) ($i + 1)] = $slot;
        }
        return $options;
    }

    private function resolveOption(string $input, array $map): ?string
    {
        $key = trim($input);
        return $map[$key] ?? null;
    }

    private function resolveEventOption(string $input, array $events): ?string
    {
        $index = (int) trim($input) - 1;
        return isset($events[$index]) ? $events[$index]['id'] : null;
    }

    private function findEventById(array $events, string $id): ?array
    {
        foreach ($events as $event) {
            if ($event['id'] === $id) {
                return $event;
            }
        }
        return null;
    }

    private function isExitInput(string $input): bool
    {
        return in_array(mb_strtolower(trim($input)), ['0', 'cancelar', 'cancel', 'salir', 'menu', 'menú', 'inicio', 'volver'], true);
    }

    private function formatDateLabel(string $date): string
    {
        if (!$date) {
            return '';
        }
        $tz     = new \DateTimeZone($this->timezone);
        $dt     = new \DateTime($date, $tz);
        $days   = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $dow    = $days[(int) $dt->format('N') - 1];
        return "{$dow} " . $dt->format('j') . '/' . $months[(int) $dt->format('n') - 1];
    }

    private function formatTimeLabel(string $time): string
    {
        if (!$time) {
            return '';
        }
        [$h, $m]  = explode(':', $time);
        $hour     = (int) $h;
        $suffix   = $hour >= 12 ? 'PM' : 'AM';
        $display  = $hour > 12 ? $hour - 12 : ($hour === 0 ? 12 : $hour);
        return sprintf('%d:%s %s', $display, $m, $suffix);
    }

    private function formatEventDate(string $startDt): string
    {
        if (!$startDt) {
            return '';
        }
        return $this->formatDateLabel(substr($startDt, 0, 10));
    }

    private function formatEventTime(string $startDt): string
    {
        if (strlen($startDt) <= 10) {
            return '';
        }
        return $this->formatTimeLabel(substr($startDt, 11, 5));
    }

    private function loadSession(string $phone): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM classic_calendar_sessions WHERE user_phone = :phone AND expires_at > NOW()",
            [':phone' => $phone]
        );
        return $row ?: null;
    }

    private function saveSession(string $phone, string $step, array $data): void
    {
        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);
        $ttl      = self::TTL_MINUTES;
        $this->db->query(
            "INSERT INTO classic_calendar_sessions (user_phone, step, data, expires_at)
             VALUES (:phone, :step, :data, DATE_ADD(NOW(), INTERVAL {$ttl} MINUTE))
             ON DUPLICATE KEY UPDATE
                 step       = VALUES(step),
                 data       = VALUES(data),
                 expires_at = VALUES(expires_at)",
            [
                ':phone' => $phone,
                ':step'  => $step,
                ':data'  => $dataJson,
            ]
        );
    }

    private function clearSession(string $phone): void
    {
        $this->db->query(
            "DELETE FROM classic_calendar_sessions WHERE user_phone = :phone",
            [':phone' => $phone]
        );
    }
}
