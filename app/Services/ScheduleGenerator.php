<?php

namespace App\Services;

class ScheduleGenerator
{
    protected array $teachers;
    protected array $students;
    protected array $rooms;
    protected array $currentSchedule;

    public function __construct(array $teachers, array $students, array $rooms, array $currentSchedule = [])
    {
        $this->teachers = $teachers;
        $this->students = $students;
        $this->rooms = $rooms;
        $this->currentSchedule = $currentSchedule;
    }

    public function generate(): array
    {
        $payload = [
            'teachers' => $this->teachers,
            'students' => $this->students,
            'rooms' => $this->rooms,
            'current_schedule' => $this->currentSchedule,
        ];

        $prompt = $this->buildPrompt($payload);

        $response = $this->callOpenAi($prompt);

        return json_decode($response, true) ?? [];
    }

    protected function buildPrompt(array $data): string
    {
        return <<<PROMPT
Given the following data:
{$this->prettyJson($data)}

Rearrange the lessons to:
- Respect teacher availability and max gaps
- Prioritize lower-level subjects for students
- Assign appropriate rooms based on availability and features
- Write very short reason why this lesson on this place

Return only valid JSON array of lessons:
[
  {
    "lesson_id": null,
    "reason": "why"
    "subject_id": 101,
    "teacher_ids": [1],
    "room_id": 301,
    "date": "2025-08-05",
    "start_time": "08:00",
    "end_time": "08:45",
    "student_ids": [201]
  }
]
PROMPT;
    }

    protected function callOpenAi(string $prompt): string
    {
        $apiKey = config('services.openai.secret');
        $url = "https://api.openai.com/v1/chat/completions";

        $data = [
            "model" => "gpt-4o",
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
            "temperature" => 0.2,
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
