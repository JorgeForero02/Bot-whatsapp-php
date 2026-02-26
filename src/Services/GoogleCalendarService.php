<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Core\Logger;

class GoogleCalendarService
{
    private $client;
    private $accessToken;
    private $refreshToken;
    private $clientId;
    private $clientSecret;
    private $calendarId;
    private $timezone;
    private $logger;

    public function __construct($accessToken, $calendarId, Logger $logger, $timezone = 'America/Bogota', $refreshToken = null, $clientId = null, $clientSecret = null)
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->calendarId = $calendarId;
        $this->timezone = $timezone;
        $this->logger = $logger;

        $this->client = new Client([
            'base_uri' => 'https://www.googleapis.com/calendar/v3/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'verify' => false
        ]);
    }

    private function refreshAccessToken()
    {
        if (!$this->refreshToken || !$this->clientId || !$this->clientSecret) {
            throw new \Exception('Refresh token not configured');
        }

        try {
            $tokenClient = new Client(['verify' => false]);
            $response = $tokenClient->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                    'grant_type' => 'refresh_token'
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            
            if (isset($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                
                $this->client = new Client([
                    'base_uri' => 'https://www.googleapis.com/calendar/v3/',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ],
                    'verify' => false
                ]);
                
                $this->logger->info('Access token refreshed successfully');
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to refresh access token: ' . $e->getMessage());
            return false;
        }
    }

    private function makeRequest($method, $endpoint, $options = [])
    {
        try {
            $response = $this->client->$method($endpoint, $options);
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), '401') !== false && $this->refreshAccessToken()) {
                $response = $this->client->$method($endpoint, $options);
                return json_decode($response->getBody(), true);
            }
            throw $e;
        }
    }

    public function listUpcomingEvents($maxResults = 10)
    {
        try {
            return $this->makeRequest('get', "calendars/{$this->calendarId}/events", [
                'query' => [
                    'maxResults' => $maxResults,
                    'orderBy' => 'startTime',
                    'timeMin' => date('c')
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error listing events: ' . $e->getMessage());
            throw $e;
        }
    }

    public function checkAvailability($date, $startHour, $endHour)
    {
        try {
            $timeMin = "{$date}T{$startHour}:00:00";
            $timeMax = "{$date}T{$endHour}:00:00";
            
            $data = $this->makeRequest('get', "calendars/{$this->calendarId}/events", [
                'query' => [
                    'timeMin' => $timeMin,
                    'timeMax' => $timeMax
                ]
            ]);
            
            return empty($data['items']);
        } catch (\Exception $e) {
            $this->logger->error('Error checking availability: ' . $e->getMessage());
            throw $e;
        }
    }

    public function createEvent($summary, $description, $startDateTime, $endDateTime, $attendeeEmail = null)
    {
        try {
            $event = [
                'summary' => $summary,
                'description' => $description,
                'start' => [
                    'dateTime' => $startDateTime,
                    'timeZone' => $this->timezone
                ],
                'end' => [
                    'dateTime' => $endDateTime,
                    'timeZone' => $this->timezone
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'email', 'minutes' => 24 * 60],
                        ['method' => 'popup', 'minutes' => 30]
                    ]
                ]
            ];

            if ($attendeeEmail) {
                $event['attendees'] = [
                    ['email' => $attendeeEmail]
                ];
            }

            return $this->makeRequest('post', "calendars/{$this->calendarId}/events", [
                'json' => $event
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error creating event: ' . $e->getMessage());
            throw $e;
        }
    }

    public function validateDateFormat($dateText)
    {
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $dateText, $matches)) {
            $day = intval($matches[1]);
            $month = intval($matches[2]);
            $year = intval($matches[3]);
            
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
        
        $months = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
        ];
        
        foreach ($months as $monthName => $monthNum) {
            if (preg_match('/(\d{1,2})\s+de\s+' . $monthName . '\s+(?:del?\s+)?(\d{4})/i', $dateText, $matches)) {
                $day = intval($matches[1]);
                $year = intval($matches[2]);
                
                if (checkdate($monthNum, $day, $year)) {
                    return sprintf('%04d-%02d-%02d', $year, $monthNum, $day);
                }
            }
        }
        
        $textLower = mb_strtolower($dateText);
        if (strpos($textLower, 'mañana') !== false) {
            return date('Y-m-d', strtotime('+1 day'));
        }
        if (strpos($textLower, 'hoy') !== false) {
            return date('Y-m-d');
        }
        if (strpos($textLower, 'pasado mañana') !== false) {
            return date('Y-m-d', strtotime('+2 days'));
        }
        
        return null;
    }

    public function checkEventOverlap($date, $startTime, $endTime)
    {
        try {
            $timeMin = (new \DateTime("{$date} {$startTime}", new \DateTimeZone($this->timezone)))->format(\DateTime::RFC3339);
            $timeMax = (new \DateTime("{$date} {$endTime}", new \DateTimeZone($this->timezone)))->format(\DateTime::RFC3339);
            
            $data = $this->makeRequest('get', "calendars/{$this->calendarId}/events", [
                'query' => [
                    'timeMin' => $timeMin,
                    'timeMax' => $timeMax
                ]
            ]);
            
            if (!empty($data['items'])) {
                return [
                    'overlap' => true,
                    'events' => $data['items']
                ];
            }
            
            return ['overlap' => false, 'events' => []];
        } catch (\Exception $e) {
            $this->logger->error('Error checking overlap: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getEventsByDateRange($startDate, $endDate, $maxResults = 50)
    {
        try {
            $timeMin = (new \DateTime($startDate, new \DateTimeZone($this->timezone)))->format(\DateTime::RFC3339);
            $timeMax = (new \DateTime($endDate . ' 23:59:59', new \DateTimeZone($this->timezone)))->format(\DateTime::RFC3339);
            
            return $this->makeRequest('get', "calendars/{$this->calendarId}/events", [
                'query' => [
                    'timeMin' => $timeMin,
                    'timeMax' => $timeMax,
                    'maxResults' => $maxResults,
                    'orderBy' => 'startTime'
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting events by date range: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getNextEvent()
    {
        try {
            $events = $this->listUpcomingEvents(1);
            return !empty($events['items']) ? $events['items'][0] : null;
        } catch (\Exception $e) {
            $this->logger->error('Error getting next event: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getTodayEvents()
    {
        $today = (new \DateTime('now', new \DateTimeZone($this->timezone)))->format('Y-m-d');
        return $this->getEventsByDateRange($today, $today);
    }

    public function getEventsForDay($date)
    {
        return $this->getEventsByDateRange($date, $date);
    }

    public function countEventsForDay($date)
    {
        try {
            $events = $this->getEventsForDay($date);
            return count($events['items'] ?? []);
        } catch (\Exception $e) {
            $this->logger->error('Error counting events: ' . $e->getMessage());
            return 0;
        }
    }

    public function validateBusinessHours($date, $time, $businessHours)
    {
        try {
            $datetime = new \DateTime($date . ' ' . $time, new \DateTimeZone($this->timezone));
            $dayOfWeek = strtolower($datetime->format('l'));
            
            $dayMap = [
                'monday' => 'monday',
                'tuesday' => 'tuesday',
                'wednesday' => 'wednesday',
                'thursday' => 'thursday',
                'friday' => 'friday',
                'saturday' => 'saturday',
                'sunday' => 'sunday'
            ];
            
            $day = $dayMap[$dayOfWeek] ?? null;
            
            if (!$day || !isset($businessHours[$day]) || $businessHours[$day] === null) {
                return [
                    'valid' => false,
                    'reason' => 'No atendemos ese día'
                ];
            }
            
            $hours = $businessHours[$day];
            $startTime = $hours['start'];
            $endTime = $hours['end'];
            
            $requestedTime = $datetime->format('H:i');
            
            if ($requestedTime < $startTime || $requestedTime >= $endTime) {
                return [
                    'valid' => false,
                    'reason' => "Horario fuera de atención. Atendemos de {$startTime} a {$endTime}"
                ];
            }
            
            return ['valid' => true];
        } catch (\Exception $e) {
            $this->logger->error('Error validating business hours: ' . $e->getMessage());
            return ['valid' => false, 'reason' => 'Error al validar horario'];
        }
    }

    public function formatEventsForWhatsApp($events)
    {
        if (empty($events)) {
            return "No hay eventos próximos agendados.";
        }

        $message = "📅 *Próximos eventos:*\n\n";
        
        foreach ($events as $index => $event) {
            $start = new \DateTime($event['start']['dateTime'] ?? $event['start']['date']);
            $summary = $event['summary'] ?? 'Sin título';
            
            $message .= ($index + 1) . ". *" . $summary . "*\n";
            $message .= "   📆 " . $start->format('d/m/Y H:i') . "\n";
            
            if (isset($event['description'])) {
                $message .= "   📝 " . substr($event['description'], 0, 50) . "...\n";
            }
            
            $message .= "\n";
        }

        return $message;
    }
}
