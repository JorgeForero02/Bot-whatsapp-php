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
    private $logger;

    public function __construct($accessToken, $calendarId, Logger $logger, $refreshToken = null, $clientId = null, $clientSecret = null)
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->calendarId = $calendarId;
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
                    'singleEvents' => true,
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
                    'timeMax' => $timeMax,
                    'singleEvents' => true
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
                    'timeZone' => 'America/Bogota'
                ],
                'end' => [
                    'dateTime' => $endDateTime,
                    'timeZone' => 'America/Bogota'
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
        // Try DD/MM/YYYY format
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $dateText, $matches)) {
            $day = intval($matches[1]);
            $month = intval($matches[2]);
            $year = intval($matches[3]);
            
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
        
        // Try text format: "24 de febrero del 2026", "24 de febrero de 2026"
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
        
        // Try relative dates
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

    public function parseEventFromText($text)
    {
        $result = [
            'title' => null,
            'date' => null,
            'time' => null,
            'duration' => 1
        ];

        // Patterns for extracting event info
        // Format: "agendar: Título - DD/MM/YYYY - HH:MM - X hora(s)"
        // Or: "Título para mañana a las 3pm"
        
        // Try to extract date patterns
        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $text, $matches)) {
            $result['date'] = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }
        
        // Try relative dates
        if (preg_match('/mañana/i', $text)) {
            $result['date'] = (new \DateTime('tomorrow'))->format('Y-m-d');
        } elseif (preg_match('/hoy/i', $text)) {
            $result['date'] = (new \DateTime('today'))->format('Y-m-d');
        } elseif (preg_match('/pasado mañana/i', $text)) {
            $result['date'] = (new \DateTime('+2 days'))->format('Y-m-d');
        }
        
        // Extract time (HH:MM or Xpm/Xam)
        if (preg_match('/(\d{1,2}):(\d{2})/', $text, $matches)) {
            $result['time'] = sprintf('%02d:%02d', $matches[1], $matches[2]);
        } elseif (preg_match('/(\d{1,2})\s*(pm|am)/i', $text, $matches)) {
            $hour = intval($matches[1]);
            if (strtolower($matches[2]) === 'pm' && $hour < 12) {
                $hour += 12;
            } elseif (strtolower($matches[2]) === 'am' && $hour === 12) {
                $hour = 0;
            }
            $result['time'] = sprintf('%02d:00', $hour);
        }
        
        // Extract duration
        if (preg_match('/(\d+)\s*hora/i', $text, $matches)) {
            $result['duration'] = intval($matches[1]);
        }
        
        // Extract title
        // Try to find explicit title after keywords
        $titlePattern = '/(?:agendar|crear evento|programar|apartar|reservar)[\s:]+([^-\d]+?)(?:\s+para\s+|\s+el\s+|\s+mañana|\s+hoy|\s+a\s+las\s+|\s+\d)/i';
        if (preg_match($titlePattern, $text, $matches)) {
            $result['title'] = trim($matches[1]);
        } else {
            // Fallback: take content between keyword and date/time
            $parts = preg_split('/[-–]/', $text);
            if (count($parts) > 0) {
                $result['title'] = trim(preg_replace('/(?:agendar|crear evento|programar|apartar|reservar)[\s:]*/i', '', $parts[0]));
            }
        }
        
        // If still no title or title is too generic, create default
        if (empty($result['title']) || preg_match('/^\s*(cita|evento|reunión)\s*$/i', $result['title'])) {
            $result['title'] = 'Cita agendada desde WhatsApp';
        }
        
        // Clean up title
        $result['title'] = trim(preg_replace('/\s+(para|el|a las)\s*$/i', '', $result['title']));
        
        return $result;
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
