<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Core\Logger;

class GoogleCalendarService
{
    private $client;
    private $accessToken;
    private $calendarId;
    private $logger;

    public function __construct($accessToken, $calendarId, Logger $logger)
    {
        $this->accessToken = $accessToken;
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

    public function listUpcomingEvents($maxResults = 10)
    {
        try {
            $timeMin = (new \DateTime())->format(\DateTime::RFC3339);
            
            $response = $this->client->get("calendars/{$this->calendarId}/events", [
                'query' => [
                    'timeMin' => $timeMin,
                    'maxResults' => $maxResults,
                    'singleEvents' => true,
                    'orderBy' => 'startTime'
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('Google Calendar: Events listed', [
                'count' => count($data['items'] ?? [])
            ]);

            return $data['items'] ?? [];

        } catch (\Exception $e) {
            $this->logger->error('Google Calendar List Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function checkAvailability($date, $startHour, $endHour)
    {
        try {
            $dateObj = new \DateTime($date);
            $timeMin = $dateObj->setTime($startHour, 0)->format(\DateTime::RFC3339);
            $timeMax = $dateObj->setTime($endHour, 0)->format(\DateTime::RFC3339);
            
            $response = $this->client->get("calendars/{$this->calendarId}/events", [
                'query' => [
                    'timeMin' => $timeMin,
                    'timeMax' => $timeMax,
                    'singleEvents' => true,
                    'orderBy' => 'startTime'
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $events = $data['items'] ?? [];
            
            $availableSlots = [];
            $busyTimes = [];

            foreach ($events as $event) {
                if (isset($event['start']['dateTime'])) {
                    $busyTimes[] = [
                        'start' => $event['start']['dateTime'],
                        'end' => $event['end']['dateTime']
                    ];
                }
            }

            $this->logger->info('Google Calendar: Availability checked', [
                'date' => $date,
                'busy_slots' => count($busyTimes)
            ]);

            return [
                'available' => count($busyTimes) === 0,
                'busy_times' => $busyTimes
            ];

        } catch (\Exception $e) {
            $this->logger->error('Google Calendar Availability Error: ' . $e->getMessage());
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

            $response = $this->client->post("calendars/{$this->calendarId}/events", [
                'json' => $event
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('Google Calendar: Event created', [
                'event_id' => $data['id'] ?? null,
                'summary' => $summary
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Google Calendar Create Event Error: ' . $e->getMessage());
            throw $e;
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
