<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\Request;

trait NormalizesMobilePayload
{
    /**
     * Ensure required fields exist on the request even when upstream
     * gateways mangle the JSON body (double-escaped quotes, etc.).
     */
    protected function normalizeMobilePayload(Request $request, array $fields): void
    {
        $missing = array_filter($fields, fn (string $field) => ! $request->filled($field));

        if (empty($missing)) {
            return;
        }

        $content = $request->getContent();
        if (! is_string($content) || trim($content) === '') {
            return;
        }

        $payload = $this->decodePayload($content);

        if (is_array($payload)) {
            $request->merge(array_intersect_key($payload, array_flip($fields)));
            return;
        }

        $extracted = [];
        foreach ($missing as $field) {
            if (preg_match('/' . preg_quote($field, '/') . '[\\\\\"\\s:=]+(?P<value>[\\+0-9]+)/i', $content, $matches)) {
                $extracted[$field] = trim($matches['value'], "\" \t\n\r\0\x0B");
            }
        }

        if (! empty($extracted)) {
            $request->merge($extracted);
        }
    }

    private function decodePayload(string $content): ?array
    {
        $json = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        $cleaned = str_replace(['\\"', '\\\\'], ['"', '\\'], trim($content));
        $cleaned = trim($cleaned, '"');

        $json = json_decode($cleaned, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($json) ? $json : null;
    }
}






