<?php

namespace App\Services;

class ScheduleGenerator
{
    protected array $teachers;
    protected array $students;
    protected array $rooms;
    protected array $currentSchedule;

    public function __construct(array $currentSchedule = [], array $rooms = [])
    {
        $this->currentSchedule = $currentSchedule;
        $this->rooms = $rooms;
    }

    public function generate(): array
    {
        $payload = [
            'current_schedule' => $this->currentSchedule,
            'rooms'            => $this->rooms,
        ];

        $prompt = $this->buildPrompt($payload);

        $response = $this->callOpenAi($prompt);

        return json_decode($response, true) ?? [];
    }

    protected function buildPrompt(array $data): string
    {
        $data = json_encode($data);
        return <<<PROMPT
Given the following data:
{$data}

Rearrange ALL the lessons to:
- Respect teacher availability and max gaps
- Analyse students subjects, and their load
- Choose rooms that match capacity and features so they are not overfilled
- Write brief reasons why this lesson on this place
- Use these fixed periods (start | end):
  1st lesson - 09:00 | 09:45
  2nd lesson - 09:50 | 10:35
  3rd lesson - 10:50 | 11:35
  4th lesson - 11:40 | 12:25
  5th lesson - 13:00 | 13:45
  6th lesson - 13:50 | 14:35
  7th lesson - 14:40 | 15:25
- Week only from monday to friday

Return only valid JSON array of lessons:
[
  {
    "lesson_id": 1,
    "reason": "why"
    "room_id": 301,
    "date": "2025-08-05",
    "start_time": "09:00",
    "end_time": "09:45",
  }
]
PROMPT;
    }

    protected function callOpenAi(string $prompt): string
    {
        $apiKey = config('services.openai.secret');
        $url = "https://api.openai.com/v1/chat/completions";

        $data = [
            "model" => "gpt-5",
            "messages" => [
                [
                    "role" => "system",
                    "content" => "You are a scheduling assistant for a school."
                ],
                [
                    "role" => "user",
                    "content" => $prompt
                ]
            ],
            "response_format" => array(
                "type" => "json_object",
            ),
        ];

        $headers = [
            "Authorization: Bearer {$apiKey}",
            "Content-Type: application/json"
        ];

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers
        ]);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \RuntimeException('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        $json = json_decode($result, true);
        return $json['choices'][0]['message']['content'] ?? '[]';
    }

    protected function prettyJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
