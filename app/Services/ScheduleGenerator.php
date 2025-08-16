<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ScheduleGenerator
{
    protected string $dates;
    protected array $students;
    protected array $rooms;
    protected array $currentSchedule;

    public function __construct(array $currentSchedule = [], array $rooms = [], string $dates = '', array $students = [])
    {
        $this->currentSchedule = $currentSchedule;
        $this->rooms = $rooms;
        $this->dates = $dates;
        $this->students = $students;
    }

    public function generate(): array
    {
        $payload = [
            'lessons' => $this->currentSchedule,
            'rooms'            => $this->rooms,
            'date_range'       => $this->dates,
            'students'         => $this->students,
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
- Assign each student the required quantity of lessons for every subject
- Analyse student assigns and ensure that no student has two different lessons scheduled in the same date and period
- If a student has two different lessons scheduled in the same date and period write reason
- Include the student IDs for each lesson in a `student_ids` array
- Choose rooms that match capacity and features so they are not overfilled
- Write brief reasons why this lesson on this place
- If max_days stated you can choose any [max_days] days of the week
- Lower number means a higher priority
- If a room is assigned to multiple subjects, give it to the subject with the highest room priority
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
            "response_format" => [
                "type" => "json_schema",
                "json_schema" => [
                    "name" => "lessons_payload",
                    "strict" => true,
                    "schema" => [
                        "type" => "object",
                        "properties" => [
                            "lessons" => [
                                "type" => "array",
                                "minItems" => 1, // require at least one lesson; remove if you want to allow []
                                "items" => [
                                    "type" => "object",
                                    "properties" => [
                                        "lesson_id" => [ "type" => "integer" ],
                                        "student_ids" => [
                                            "type" => "array",
                                            "items" => [ "type" => "integer" ],
                                            "minItems" => 1, // force non-empty array
                                            "description" => "IDs of students assigned to this lesson; must not be empty."
                                        ],
                                        "reason"  => [ "type" => "string" ],
                                        "room_id" => [ "type" => "integer" ],
                                        "date"    => [ "type" => "string", "format" => "date" ],
                                        "period"  => [ "type" => "integer" ]
                                    ],
                                    "required" => ["lesson_id","student_ids","reason","room_id","date","period"],
                                    "additionalProperties" => false
                                ]
                            ]
                        ],
                        "required" => ["lessons"],
                        "additionalProperties" => false
                    ]
                ]
            ]
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

        curl_setopt($ch, CURLOPT_TIMEOUT, 0); // no total time limit
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $result = curl_exec($ch);

        Log::info('OptimizeTeachers: generation response', [
            'result'     => $result,
        ]);

        if (curl_errno($ch)) {
            throw new \RuntimeException('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        $json = json_decode($result, true);

        // If OpenAI returned an error at the root level, surface that message
        if (isset($json['error']['message'])) {
            return json_encode(['error' => $json['error']['message']]);
        }

        $content = $json['choices'][0]['message']['content'] ?? '[]';

        // Sometimes the content itself is a JSON object with an `error` key
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['error'])) {
            return json_encode(['error' => $decoded['error']]);
        }

        return $content;
    }

    protected function prettyJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
